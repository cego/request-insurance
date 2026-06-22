<?php

namespace Tests\Integration;

class HarnessSmokeTest extends IntegrationTestCase
{
    public function test_runs_only_on_partitionable_driver(): void
    {
        $this->assertContains($this->driverName(), ['mysql', 'pgsql']);
    }
}
