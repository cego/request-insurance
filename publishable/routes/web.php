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

        Route::get('request-insurances/{request_insurance}/edit-history', [
            'uses' => 'RequestInsuranceController@editHistory',
            'as'   => 'request-insurances.edit-history',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/edit', [
            'uses' => 'RequestInsuranceEditController@create',
            'as'   => 'request-insurance-edits.create',
        ],)->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance_edit}/update-edit', [
            'uses' => 'RequestInsuranceEditController@update',
            'as'   => 'request-insurance-edits.update',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::delete('request-insurances/{request_insurance_edit}/delete-edit', [
            'uses' => 'RequestInsuranceEditController@destroy',
            'as'   => 'request-insurance-edits.destroy',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance_edit}/approve-edit', [
            'uses' => 'RequestInsuranceEditApprovalController@create',
            'as'   => 'request-insurance-edit-approvals.create',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::delete('request-insurances/{request_insurance_edit_approval}/remove-edit-approval', [
            'uses' => 'RequestInsuranceEditApprovalController@destroy',
            'as'   => 'request-insurance-edit-approvals.destroy',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance_edit}/apply-edit', [
            'uses' => 'RequestInsuranceEditController@apply',
            'as'   => 'request-insurances-edits.apply',
        ])->withoutMiddleware(VerifyCsrfToken::class);
    });
