<?php

namespace Cego\RequestInsurance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class RequestInsuranceCleaner
{
    /**
     * Cleans up old request insurances.
     *
     *  - Main tables: on partition-capable drivers whole partitions older than the
     *    retention window are dropped. The guard throws if an aged partition still
     *    holds a non-COMPLETED row (those should have been extracted to the
     *    exceptions tables). On other drivers retention falls back to row deletes.
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

        $chunkSize = (int) Config::get('request-insurance.cleanChunkSize', 1000);

        $connection->table($failed)
            ->where('state', State::ABANDONED)
            ->where('created_at', '<', $olderThan->toDateTimeString())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($connection, $failed, $failedLogs) {
                $ids = collect($rows)->pluck('id')->all();
                $connection->table($failedLogs)->whereIn('request_insurance_id', $ids)->delete();
                $connection->table($failed)->whereIn('id', $ids)->delete();
                usleep(10000);
            });
    }
}
