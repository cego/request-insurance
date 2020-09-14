<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Nbj\RequestInsurance\Controllers')->prefix('vendor')->group(function () {
    Route::resource('request-insurances', 'RequestInsuranceController')
        ->only(['index', 'show', 'destroy'])
        ->middleware('web');
});
