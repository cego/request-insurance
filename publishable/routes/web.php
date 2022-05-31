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

        Route::get('request-insurances/monitor_segmented', [
            'uses' => 'RequestInsuranceController@monitor_segmented',
            'as'   => 'request-insurances.monitor_segmented',
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

        Route::post('request-insurances/{request_insurance}/edit', [
            'uses' => 'RequestInsuranceController@edit',
            'as'   => 'request-insurances.edit',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/update_edit', [
            'uses' => 'RequestInsuranceController@update_edit',
            'as'   => 'request-insurances.update_edit',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/approve_edit', [
            'uses' => 'RequestInsuranceController@approve_edit',
            'as'   => 'request-insurances.approve_edit',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/remove_edit_approval', [
            'uses' => 'RequestInsuranceController@remove_edit_approval',
            'as'   => 'request-insurances.remove_edit_approval',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/apply_edit', [
            'uses' => 'RequestInsuranceController@apply_edit',
            'as'   => 'request-insurances.apply_edit',
        ])->withoutMiddleware(VerifyCsrfToken::class);
    });
