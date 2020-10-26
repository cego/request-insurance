<?php

namespace Cego\RequestInsurance;

use Cego\RequestInsurance\Commands;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\ViewComponents\Status;
use Cego\RequestInsurance\ViewComponents\HttpCode;
use Cego\RequestInsurance\ViewComponents\InlinePrint;
use Cego\RequestInsurance\ViewComponents\PrettyPrint;

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
            __DIR__ . '/../../publishable/config/request-insurance.php' => config_path() . '/request-insurance.php',
        ]);

        // Make sure that routes are added
        $this->loadRoutesFrom(__DIR__ . '/../../publishable/routes/web.php');

        // Make sure that views and view-components are added
        $this->loadViewsFrom(__DIR__ . '/../../publishable/views', 'request-insurance');
        $this->loadViewComponentsAs('request-insurance', [
            HttpCode::class,
            PrettyPrint::class,
            InlinePrint::class,
            Status::class,
        ]);

        // Add all commands to Artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\RequestInsuranceService::class,
                Commands\UnlockBlockedRequestInsurances::class,
                Commands\CleanUpRequestInsurances::class,
            ]);
        }

        // Add specific commands to the schedule
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('unlock:request-insurances')->everyFiveMinutes();
            $schedule->command('clean:request-insurances')->dailyAt('03:00');
        });

        $this->setPaginatorStyling();
    }

    /**
     * Sets the paginator styling to bootstrap if using Laravel 8
     */
    private function setPaginatorStyling()
    {
        // If Laravel version 8
        if (version_compare(app()->version(), '8.0.0', '>=') === true) {
            // Use bootstrap for the paginator instead of tailwind, since the rest of the interface uses bootstrap
            Paginator::useBootstrap();
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
