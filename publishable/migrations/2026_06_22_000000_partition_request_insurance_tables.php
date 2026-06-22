<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Migrations\Migration;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class PartitionRequestInsuranceTables extends Migration
{
    /**
     * PostgreSQL keeps the default wrapping migration transaction so the
     * LOCK + rename + create + copy is atomic and concurrent inserts block
     * safely until commit. MySQL/MariaDB auto-commit DDL, so the migration
     * cannot meaningfully run inside a transaction there; running it wrapped
     * would only give a false sense of atomicity. The driver is detected in
     * the constructor and this flag is set accordingly.
     */
    public $withinTransaction = true;

    public function __construct()
    {
        $driver = DB::connection()->getDriverName();
        $this->withinTransaction = $driver === 'pgsql';
    }

    public function up(): void
    {
        // Allow consumers/tests to opt out of the in-place cutover during an
        // ordinary migration run (e.g. when seeding plain tables in tests, or
        // when the cutover is performed manually in a controlled maintenance
        // window). Defaults to enabled so a normal `migrate` performs the
        // cutover.
        if ( ! Config::get('request-insurance.run_partition_migration', true)) {
            return;
        }

        $manager = PartitionManagerFactory::for(DB::connection());

        if ( ! $manager->isSupported()) {
            return; // sqlite and friends keep the plain table from the base migrations
        }

        $table = Config::get('request-insurance.table') ?? 'request_insurances';

        // The manager migrates the parent table and its logs table together
        // inside migrateToPartitioned(); do NOT migrate the logs table again
        // here or it would be double-processed.
        $manager->migrateToPartitioned($table, [State::COMPLETED, State::ABANDONED]);

        // Pre-create the forward partitions for both the parent and the logs
        // table so inserts immediately following the cutover have a target.
        $logsTable = Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';

        $manager->ensureFuturePartitions($table);
        $manager->ensureFuturePartitions($logsTable);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Reversing the request_insurance partition migration is not supported automatically. '
            . 'The pre-migration data is preserved in the *_legacy tables; restore manually if required.'
        );
    }
}
