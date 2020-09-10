<?php

namespace Nbj\RequestInsurance;

use Illuminate\Support\ServiceProvider;
use Nbj\RequestInsurance\Contracts\HttpRequest;
use Nbj\RequestInsurance\Commands\InstallRequestInsurance;
use Nbj\RequestInsurance\Commands\RequestInsuranceService;

class RequestInsuranceServiceProvider extends ServiceProvider
{
    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Make sure migrations and factories are published to the project consuming this package
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->loadFactoriesFrom(__DIR__ . '/../../factories');

        // Makes sure essential files are published to the consuming project
        $this->publishes([
            __DIR__ . '/../../config/request-insurance.php'         => config_path() . '/request-insurance.php',
            __DIR__ . '/../../models/RequestInsurance.php'          => app_path() . '/RequestInsurance.php',
            __DIR__ . '/../../models/RequestInsuranceLog.php'       => app_path() . '/RequestInsuranceLog.php',
        ]);

        // Add the installation command to Artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallRequestInsurance::class,
                RequestInsuranceService::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(HttpRequest::class, function () {
            return config('request-insurance.httpRequestClass');
        });
    }
}
