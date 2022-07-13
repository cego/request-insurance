<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\RequestInsuranceWorker;
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

    protected function runWorkerOnce(int $batchSize = 100): void
    {
        $this->getWorker($batchSize)->run(true);
    }

    protected function getWorker(int $batchSize = 100): RequestInsuranceWorker
    {
        putenv('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT=false');
        Config::set('request-insurance.useDbReconnect', false);

        return new RequestInsuranceWorker($batchSize);
    }
}
