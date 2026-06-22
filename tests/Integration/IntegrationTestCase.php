<?php

namespace Tests\Integration;

use PartitionRequestInsuranceTables;
use Tests\TestCase;

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
     * Ensure the migration class is loaded before setUp() manipulates its
     * static property. require_once is safe to call multiple times.
     */
    private function requireMigrationClass(): void
    {
        require_once __DIR__ . '/../../publishable/migrations/2026_06_22_000000_partition_request_insurance_tables.php';
    }
}
