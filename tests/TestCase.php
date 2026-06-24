<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\RequestInsuranceWorker;
use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;
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

    protected function defineEnvironment($app): void
    {
        $connection = env('DB_CONNECTION', 'testing');
        $app['config']->set('database.default', $connection);

        if ($connection === 'mysql') {
            $app['config']->set('database.connections.mysql', [
                'driver'   => 'mysql',
                'host'     => env('DB_HOST', '127.0.0.1'),
                'port'     => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'root'),
                'charset'  => 'utf8mb4',
                'prefix'   => '',
            ]);
        }

        if ($connection === 'pgsql') {
            $app['config']->set('database.connections.pgsql', [
                'driver'   => 'pgsql',
                'host'     => env('DB_HOST', '127.0.0.1'),
                'port'     => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
                'charset'  => 'utf8',
                'prefix'   => '',
            ]);
        }
    }

    protected function driverName(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName();
    }

    /**
     * Reload a request from the main table, falling back to the exceptions table
     * once it has FAILED/ABANDONED and been moved there. Mirrors refresh() but is
     * aware of the move to the exceptions tables.
     */
    protected function reloadOrFailed(RequestInsurance $requestInsurance): RequestInsurance
    {
        $fresh = RequestInsurance::query()->find($requestInsurance->getKey());

        if ($fresh !== null) {
            return $fresh;
        }

        $failed = RequestInsuranceFailed::query()->find($requestInsurance->getKey());

        $this->assertNotNull($failed, 'Request insurance was found in neither the main nor the exceptions table');

        return $failed;
    }
}
