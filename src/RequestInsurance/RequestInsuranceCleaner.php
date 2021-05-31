<?php

namespace Cego\RequestInsurance;

use Carbon\Carbon;
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
        $daysToKeepRecords = Carbon::now()->subDays(Config::get('requestInsurance.cleanUpKeepDays', 14));

        // Clean up RequestInsurances table
        resolve(RequestInsurance::class)::whereBetween('response_code', [200, 299])
            ->where('created_at', '<', $daysToKeepRecords)
            ->delete();

        // Clean up RequestInsuranceLogs table
        resolve(RequestInsuranceLog::class)::doesntHave('parent')->delete();
    }
}