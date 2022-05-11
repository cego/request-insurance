<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Cego\RequestInsurance\RequestInsuranceServiceProvider;

/**
 * Class TestCase
 *
 * Used for implementing common method across test cases
 */
class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            RequestInsuranceServiceProvider::class,
        ];
    }
}
