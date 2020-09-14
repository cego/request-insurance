<?php

namespace Nbj\RequestInsurance;

use Illuminate\Support\ServiceProvider;
use Nbj\RequestInsurance\Contracts\HttpRequest;
use Nbj\RequestInsurance\ViewComponents\Status;
use Nbj\RequestInsurance\ViewComponents\HttpCode;
use Nbj\RequestInsurance\ViewComponents\InlineJson;
use Nbj\RequestInsurance\ViewComponents\PrettyJson;
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
        $this->loadMigrationsFrom(__DIR__ . '/../../publishable/migrations');
        $this->loadFactoriesFrom(__DIR__ . '/../../publishable/factories');

        // Makes sure essential files are published to the consuming project
        $this->publishes([
            __DIR__ . '/../../publishable/config/request-insurance.php'   => config_path() . '/request-insurance.php',
            __DIR__ . '/../../publishable/models/RequestInsurance.php'    => app_path() . '/RequestInsurance.php',
            __DIR__ . '/../../publishable/models/RequestInsuranceLog.php' => app_path() . '/RequestInsuranceLog.php',
        ]);

        // Make sure that routes are added
        $this->loadRoutesFrom(__DIR__ . '/../../publishable/routes/web.php');

        // Make sure that views and view-components are added
        $this->loadViewsFrom(__DIR__ . '/../../publishable/views', 'request-insurance');
        $this->loadViewComponentsAs('request-insurance', [
            HttpCode::class,
            PrettyJson::class,
            InlineJson::class,
            Status::class,
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
