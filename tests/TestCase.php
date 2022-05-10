<?php

namespace Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Application;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\Mocks\MockCurlRequest;
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
