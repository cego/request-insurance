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
}
