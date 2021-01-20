<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Cego\RequestInsurance\Controllers')->prefix('vendor')->group(function () {
    Route::get('request-insurances/load', [
        'uses' => 'RequestInsuranceController@load',
        'as'   => 'request-insurances.load',
    ])->middleware('api');

    Route::get('request-insurances/monitor', [
        'uses' => 'RequestInsuranceController@monitor',
        'as'   => 'request-insurances.monitor',
    ])->middleware('api');
}