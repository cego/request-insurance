<?php

namespace Cego\RequestInsurance;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;

class RequestInsuranceCleaner
{
    /**
     * Cleans up old completed request insurances.
     */
    public static function cleanUp(): void
    {
        $deletionCutoff = Carbon::now()->subDays(Config::get('requestInsurance.cleanUpKeepDays', 14));
        $chunkSize = Config::get('requestInsurance.cleanChunkSize', 1000);

        // Get RI ids to delete
        /** @var Builder $query */
        $query = resolve(RequestInsurance::class)::query();

        // Delete RI that have been completed for more than cleanUpKeepDays
        $query->select(['id'])
            ->where('completed_at', '<', $deletionCutoff)
            ->chunkById($chunkSize, fn (Collection $idsToDelete) => static::deleteChunk($idsToDelete->pluck('id')));
    }

    /**
     * Deletes a chunk of request insurances and their logs
     *
     * @param Collection $idsToDelete
     */
    protected static function deleteChunk(Collection $idsToDelete): void
    {
        // Clean up RequestInsurances table
        resolve(RequestInsurance::class)->whereIn('id', $idsToDelete)->delete();

        // Clean up RequestInsuranceLogs table
        resolve(RequestInsuranceLog::class)::whereIn('request_insurance_id', $idsToDelete)->delete();

        // Be a good boy and sleep 10ms
        usleep(10000);
    }
}
