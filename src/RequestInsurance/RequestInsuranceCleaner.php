<?php

namespace Cego\RequestInsurance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;

class RequestInsuranceCleaner
{
    /**
     * Cleans up old 2xx request insurances.
     */
    public static function cleanUp(): void
    {
        DB::transaction(function () {
            $daysToKeepRecords = Carbon::now()->subDays(Config::get('requestInsurance.cleanUpKeepDays', 14));

            // Get RI ids to delete
            $idsToDelete = resolve(RequestInsurance::class)::whereBetween('response_code', [200, 299])
                ->where('created_at', '<', $daysToKeepRecords)
                ->forUpdate()
                ->get(['id'])
                ->pluck('id');

            // Clean up RequestInsurances table
            resolve(RequestInsurance::class)->whereIn('id', $idsToDelete)->delete();

            // Clean up RequestInsuranceLogs table
            resolve(RequestInsuranceLog::class)::whereIn('request_insurance_id', $idsToDelete)->delete();
        });
    }
}