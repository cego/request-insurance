<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class PartitionRequestInsuranceTables extends Migration
{
    /**
     * PostgreSQL keeps the wrapping transaction so the cutover (LOCK + rename +
     * create + copy) is atomic. MySQL/MariaDB auto-commit DDL, so wrapping would
     * give only a false sense of atomicity.
     */
    public $withinTransaction = false;

    public function __construct()
    {
        $this->withinTransaction = DB::connection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        $connection = DB::connection();
        $manager = PartitionManagerFactory::for($connection);

        $main = FailedRequestMover::mainTable();
        $mainLogs = FailedRequestMover::mainLogsTable();
        $failed = FailedRequestMover::failedTable();
        $failedLogs = FailedRequestMover::failedLogsTable();

        // 1. Create the exceptions ("failed jobs") tables, shaped like the main tables.
        $manager->createPlainLike($main, $failed);
        $manager->createPlainLike($mainLogs, $failedLogs);

        // 2. Move existing FAILED/ABANDONED rows (and their logs) out of the main
        //    tables so they survive the partition drops and the main tables only
        //    hold the success lifecycle.
        $this->extractExisting($connection, $main, $mainLogs, $failed, $failedLogs, [State::FAILED, State::ABANDONED]);

        // 3. On supported drivers, convert the main tables to RANGE partitioning by
        //    created_at. Only the small set of non-COMPLETED (transient) rows is
        //    copied into the new partitioned tables; COMPLETED rows stay in the
        //    renamed *_legacy tables and age out there.
        if ($manager->isSupported()) {
            $manager->migrateToPartitioned($main, [State::COMPLETED]);
            $manager->ensureFuturePartitions($main);
            $manager->ensureFuturePartitions($mainLogs);
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Reversing the request_insurance partition migration is not supported automatically. '
            . 'Pre-migration COMPLETED rows are preserved in the *_legacy tables; FAILED/ABANDONED rows in the *_failed tables.'
        );
    }

    /**
     * @param array<int, string> $states
     */
    private function extractExisting(ConnectionInterface $connection, string $main, string $mainLogs, string $failed, string $failedLogs, array $states): void
    {
        if ($connection->table($main)->whereIn('state', $states)->doesntExist()) {
            return;
        }

        $grammar = $connection->getQueryGrammar();
        $placeholders = implode(',', array_fill(0, count($states), '?'));

        // Logs first (their subquery still sees the rows in the main table).
        $connection->insert(sprintf(
            'INSERT INTO %s SELECT * FROM %s WHERE request_insurance_id IN (SELECT id FROM %s WHERE state IN (%s))',
            $grammar->wrapTable($failedLogs),
            $grammar->wrapTable($mainLogs),
            $grammar->wrapTable($main),
            $placeholders
        ), $states);

        $connection->delete(sprintf(
            'DELETE FROM %s WHERE request_insurance_id IN (SELECT id FROM (SELECT id FROM %s WHERE state IN (%s)) sub)',
            $grammar->wrapTable($mainLogs),
            $grammar->wrapTable($main),
            $placeholders
        ), $states);

        $connection->insert(sprintf(
            'INSERT INTO %s SELECT * FROM %s WHERE state IN (%s)',
            $grammar->wrapTable($failed),
            $grammar->wrapTable($main),
            $placeholders
        ), $states);

        $connection->table($main)->whereIn('state', $states)->delete();
    }
}
