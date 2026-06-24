<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;

// The monitoring endpoints are fetched by the dashboard (asynchronously, so a slow
// count never blocks page load), so they run under the same `web` session as the
// dashboard rather than `api` — otherwise SSO/forward-auth redirects the XHR to the
// login provider and the cross-origin redirect is blocked by CORS. Defined before the
// resource route so the literal paths match ahead of show's {request_insurance}.
Route::namespace('Cego\RequestInsurance\Controllers')
    ->prefix('vendor')
    ->middleware('web')
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

        Route::post('request-insurances/retry-selected', [
            'uses' => 'RequestInsuranceController@retrySelected',
            'as'   => 'request-insurances.retry-selected',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/abandon-selected', [
            'uses' => 'RequestInsuranceController@abandonSelected',
            'as'   => 'request-insurances.abandon-selected',
        ])->withoutMiddleware(VerifyCsrfToken::class);

        Route::post('request-insurances/{request_insurance}/retry', [
            'uses' => 'RequestInsuranceController@retry',
            'as'   => 'request-insurances.retry',
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
