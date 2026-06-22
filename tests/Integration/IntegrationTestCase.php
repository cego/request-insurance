<?php

namespace Tests\Integration;

use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ( ! in_array($this->driverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Requires mysql or pgsql driver');
        }
    }

    /**
     * The partition cutover migration is auto-loaded via loadMigrationsFrom and
     * would otherwise run during RefreshDatabase, partitioning the (empty)
     * tables before a test could seed plain rows. Disable the cutover during
     * the auto-run so tests start from the plain base schema; tests that need
     * the cutover invoke it explicitly.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('request-insurance.run_partition_migration', false);
    }
}
