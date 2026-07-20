<?php

namespace Cego\RequestInsurance;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\ConnectionInterface;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class RequestInsuranceCleaner
{
    /**
     * Cleans up old request insurances.
     *
     *  - Main tables: on partition-capable drivers whole partitions older than the
     *    retention window are dropped. An aged partition still holding a
     *    non-COMPLETED row is skipped with a warning (those rows should have been
     *    extracted to the exceptions tables; the sweep below re-attempts that).
     *    On other drivers retention falls back to row deletes.
     *  - Exceptions tables: aged ABANDONED rows are removed by row delete (FAILED
     *    rows are kept until a human resolves them, as before).
     */
    public static function cleanUp(): void
    {
        $manager = PartitionManagerFactory::for(DB::connection());
        $keepDays = (int) Config::get('request-insurance.cleanUpKeepDays', 14);
        $olderThan = CarbonImmutable::now('UTC')->subDays($keepDays);

        $mainTable = resolve(RequestInsurance::class)->getTable();
        $logsTable = resolve(RequestInsuranceLog::class)->getTable();

        static::moveStrandedExceptions();

        if ($manager->isSupported()) {
            $manager->ensureFuturePartitions($mainTable);
            $manager->ensureFuturePartitions($logsTable);
            $manager->pruneOldPartitions($mainTable, $olderThan, $manager->nonTerminalGuardFor($mainTable, [State::COMPLETED]));
            $manager->pruneOldPartitions($logsTable, $olderThan, fn () => true);
        } else {
            // Unsupported driver (e.g. sqlite): legacy row-delete retention of the
            // completed lifecycle in the main table.
            $manager->pruneOldPartitions($mainTable, $olderThan, fn () => true);
        }

        static::pruneAbandonedExceptions($olderThan);
    }

    /**
     * Move FAILED/ABANDONED rows still sitting in the partitioned main table into
     * the exceptions tables. The move at failure time runs best-effort and can be
     * interrupted (deadlock, lost connection); a stranded row would otherwise never
     * be retryable and would keep its partition from ever being dropped.
     */
    protected static function moveStrandedExceptions(): void
    {
        if ( ! FailedRequestMover::isAvailable(DB::connection())) {
            return;
        }

        RequestInsurance::query()
            ->whereIn('state', [State::FAILED, State::ABANDONED])
            ->chunkById(
                (int) Config::get('request-insurance.cleanChunkSize', 1000),
                fn ($rows) => $rows->each(function (RequestInsurance $requestInsurance) {
                    try {
                        FailedRequestMover::moveToFailed($requestInsurance);
                    } catch (Throwable $throwable) {
                        Log::error("Failed moving stranded request insurance {$requestInsurance->getKey()} to the exceptions table", [
                            'exception' => $throwable,
                        ]);
                    }
                })
            );
    }

    /**
     * Remove ABANDONED rows (and their logs) from the exceptions tables once they
     * fall outside the retention window, preserving the previous behaviour where
     * abandoned requests are eventually deleted. FAILED rows are left untouched.
     */
    protected static function pruneAbandonedExceptions(CarbonImmutable $olderThan): void
    {
        $connection = DB::connection();
        $failed = FailedRequestMover::failedTable();
        $failedLogs = FailedRequestMover::failedLogsTable();

        if ( ! $connection->getSchemaBuilder()->hasTable($failed)) {
            return;
        }

        $query = $connection->table($failed)
            ->where('state', State::ABANDONED)
            ->where('created_at', '<', $olderThan->toDateTimeString());

        static::deleteRowsInChunks($connection, $query, $failed, $failedLogs);
    }

    /**
     * Delete the rows selected by $query from $table (and their logs from
     * $logsTable) in throttled chunks, keeping lock times short on large tables.
     */
    public static function deleteRowsInChunks(ConnectionInterface $connection, Builder $query, string $table, string $logsTable): void
    {
        $chunkSize = (int) Config::get('request-insurance.cleanChunkSize', 1000);

        $query->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($connection, $table, $logsTable) {
                $ids = collect($rows)->pluck('id')->all();
                $connection->table($logsTable)->whereIn('request_insurance_id', $ids)->delete();
                $connection->table($table)->whereIn('id', $ids)->delete();
                usleep(10000);
            });
    }
}
