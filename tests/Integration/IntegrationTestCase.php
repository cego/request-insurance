<?php

namespace Tests\Integration;

use PartitionRequestInsuranceTables;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        // Suppress the cutover migration during the RefreshDatabase auto-run so
        // every integration test starts from the plain base schema. Tests that
        // need the cutover re-enable the flag explicitly (see PartitionMigrationTest).
        // This seam is a test-only static on the migration class; it is NOT
        // reachable via any consumer config key.
        $this->requireMigrationClass();
        PartitionRequestInsuranceTables::$runForTesting = false;

        parent::setUp();

        if ( ! in_array($this->driverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Requires mysql or pgsql driver');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Restore so no test leaks the disabled state.
        PartitionRequestInsuranceTables::$runForTesting = true;
    }

    /**
     * The plain base tables are created automatically by RefreshDatabase running
     * loadMigrationsFrom(); the partition cutover is suppressed during that run
     * (see setUp). This helper asserts the expected starting point — the tables
     * exist but are NOT yet partitioned.
     */
    protected function assertStartsUnpartitioned(): void
    {
        $this->assertFalse($this->isPartitioned('request_insurances'), 'base migrations must leave the table un-partitioned');
        $this->assertFalse($this->isPartitioned('request_insurance_logs'), 'base migrations must leave the logs table un-partitioned');
    }

    /**
     * Run the in-place partition cutover migration exactly as a real `migrate`
     * would: re-enable the test-only seam and invoke the published migration
     * class up().
     */
    protected function runPartitionMigration(): void
    {
        PartitionRequestInsuranceTables::$runForTesting = true;

        (new PartitionRequestInsuranceTables())->up();

        $this->assertTrue($this->isPartitioned('request_insurances'), 'parent table must be partitioned after cutover');
        $this->assertTrue($this->isPartitioned('request_insurance_logs'), 'logs table must be partitioned after cutover');
    }

    protected function isPartitioned(string $table): bool
    {
        if ($this->driverName() === 'pgsql') {
            $row = DB::selectOne('SELECT relkind FROM pg_class WHERE relname = ?', [$table]);

            return $row !== null && $row->relkind === 'p';
        }

        // mysql / mariadb
        $row = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            [$table]
        );

        return (int) $row->c > 0;
    }

    /**
     * Ensure the migration class is loaded before setUp() manipulates its
     * static property. require_once is safe to call multiple times.
     */
    private function requireMigrationClass(): void
    {
        require_once __DIR__ . '/../../publishable/migrations/2026_06_22_000000_partition_request_insurance_tables.php';
    }
}
