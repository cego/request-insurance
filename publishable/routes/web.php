<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;

Route::namespace('Cego\RequestInsurance\Controllers')
    ->prefix('vendor')
    ->middleware('api')
    ->group(function () {
        Route::get('request-insurances/load', [
            'uses' => 'RequestInsuranceController@load',
            'as'   => 'request-insurances.load',
        ]);

        Route::get('request-insurances/monitor', [
            'uses' => 'RequestInsuranceController@monitor',
            'as'   => 'request-insurances.monitor',
        ]);
    });

Route::namespace('Cego\RequestInsurance\Controllers')
    ->prefix('vendor')
    ->middleware('web')
    ->group(function () {
        Route::resource('request-insurances', 'RequestInsuranceController')
            ->only(['index', 'show', 'destroy'])
            ->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/retry', [
            'uses' => 'RequestInsuranceController@retry',
            'as'   => 'request-insurances.retry',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/unlock', [
            'uses' => 'RequestInsuranceController@unlock',
            'as'   => 'request-insurances.unlock',
        ])->withoutMiddleware(VerifyCsrfToken::class);
    });
