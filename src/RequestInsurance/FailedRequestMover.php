<?php

namespace Cego\RequestInsurance;

use Carbon\CarbonImmutable;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\ConnectionInterface;
use Cego\RequestInsurance\Models\RequestInsurance;

/**
 * Moves FAILED and ABANDONED request insurances out of the partitioned main
 * tables and into the exceptions ("failed jobs") tables, and restores them on
 * retry. Keeping non-completed rows out of the main table is what lets retention
 * drop whole partitions instead of deleting rows.
 */
class FailedRequestMover
{
    /** @var array<string, bool> exceptions-table availability, cached per process once present */
    private static array $available = [];

    public static function mainTable(): string
    {
        return Config::get('request-insurance.table') ?? 'request_insurances';
    }

    public static function mainLogsTable(): string
    {
        return Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';
    }

    public static function failedTable(): string
    {
        return Config::get('request-insurance.table_failed') ?? static::mainTable() . '_failed';
    }

    public static function failedLogsTable(): string
    {
        return Config::get('request-insurance.table_failed_logs') ?? static::mainLogsTable() . '_failed';
    }

    /**
     * Move a FAILED/ABANDONED request and its logs from the main tables into the
     * exceptions tables, in a single transaction. A no-op until the exceptions
     * tables exist, so the feature is inert until its migration has run.
     */
    public static function moveToFailed(RequestInsurance $requestInsurance): void
    {
        $connection = $requestInsurance->getConnection();

        if ( ! static::isAvailable($connection)) {
            return;
        }

        $id = $requestInsurance->getKey();
        $createdAt = $requestInsurance->getRawOriginal($requestInsurance->getCreatedAtColumn());

        $main = static::mainTable();
        $mainLogs = static::mainLogsTable();
        $failed = static::failedTable();
        $failedLogs = static::failedLogsTable();

        $connection->transaction(function () use ($connection, $id, $createdAt, $main, $mainLogs, $failed, $failedLogs) {
            static::copyAll($connection, $main, $failed, 'id', $id);
            static::copyAll($connection, $mainLogs, $failedLogs, 'request_insurance_id', $id);

            $connection->table($mainLogs)->where('request_insurance_id', $id)->delete();

            // Constrain by created_at too so the delete prunes to a single partition.
            $deleteMain = $connection->table($main)->where('id', $id);

            if ($createdAt !== null) {
                $deleteMain->where('created_at', $createdAt);
            }

            $deleteMain->delete();
        });
    }

    /**
     * Restore an exceptions row back into the main table as READY with a fresh
     * created_at (so it lands in a current partition), and remove it from the
     * exceptions table. Historical logs stay behind in the exceptions logs table
     * as an audit record. Returns the id of the restored request.
     */
    public static function restoreToActive(int $id): int
    {
        $connection = resolve(RequestInsurance::class)->getConnection();
        $main = static::mainTable();
        $failed = static::failedTable();

        $row = (array) $connection->table($failed)->where('id', $id)->first();

        $now = CarbonImmutable::now('UTC')->toDateTimeString('microsecond');
        $row['state'] = State::READY;
        $row['state_changed_at'] = $now;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $row['retry_at'] = null;
        $row['retry_count'] = 0;

        $connection->transaction(function () use ($connection, $main, $failed, $id, $row) {
            $connection->table($main)->insert($row);
            $connection->table($failed)->where('id', $id)->delete();
        });

        return $id;
    }

    public static function isAvailable(ConnectionInterface $connection): bool
    {
        $key = $connection->getName() . '|' . static::failedTable();

        if ( ! empty(self::$available[$key])) {
            return true;
        }

        if ($connection->getSchemaBuilder()->hasTable(static::failedTable())) {
            return self::$available[$key] = true;
        }

        return false;
    }

    /**
     * Copy every row of $from matching $keyColumn = $id into $to. The exceptions
     * tables share the main tables' columns, so SELECT * lines up.
     */
    private static function copyAll(ConnectionInterface $connection, string $from, string $to, string $keyColumn, mixed $id): void
    {
        $grammar = $connection->getQueryGrammar();

        $connection->insert(
            sprintf(
                'INSERT INTO %s SELECT * FROM %s WHERE %s = ?',
                $grammar->wrapTable($to),
                $grammar->wrapTable($from),
                $grammar->wrap($keyColumn)
            ),
            [$id]
        );
    }
}
