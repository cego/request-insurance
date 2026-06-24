<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Cego\RequestInsurance\Partitioning\PartitionManager;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        // Partition DDL (CREATE/ALTER ... PARTITION, DROP PARTITION) auto-commits on
        // MySQL/MariaDB and so is not rolled back by RefreshDatabase's wrapping
        // transaction. Forcing migrate:fresh per test is the only reliable isolation.
        RefreshDatabaseState::$migrated = false;

        parent::setUp();

        if ( ! in_array($this->driverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Requires a mysql or pgsql driver');
        }
    }

    protected function manager(): PartitionManager
    {
        return PartitionManagerFactory::for(DB::connection());
    }

    protected function isPartitioned(string $table): bool
    {
        if ($this->driverName() === 'pgsql') {
            $row = DB::selectOne('SELECT relkind FROM pg_class WHERE relname = ?', [$table]);

            return $row !== null && $row->relkind === 'p';
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            [$table]
        );

        return (int) $row->c > 0;
    }

    protected function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
