<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Cego\RequestInsurance\Controllers')->prefix('vendor')->group(function () {
    Route::get('request-insurances/load', [
        'uses' => 'RequestInsuranceController@load',
        'as'   => 'request-insurances.load',
    ])->middleware('api');

    Route::group(function() {
        Route::get('request-insurances/monitor', [
            'uses' => 'RequestInsuranceController@monitor',
            'as'   => 'request-insurances.monitor',
        ]);

        Route::resource('request-insurances', 'RequestInsuranceController')
            ->only(['index', 'show', 'destroy']);

        Route::post('request-insurances/{request_insurance}/retry', [
            'uses' => 'RequestInsuranceController@retry',
            'as'   => 'request-insurances.retry',
        ]);

        Route::post('request-insurances/{request_insurance}/unlock', [
            'uses' => 'RequestInsuranceController@unlock',
            'as'   => 'request-insurances.unlock',
        ]);
    })->middleware([
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);
});
