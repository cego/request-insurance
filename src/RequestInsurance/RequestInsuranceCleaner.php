<?php

namespace Cego\RequestInsurance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class RequestInsuranceCleaner
{
    /**
     * Cleans up old completed request insurances.
     */
    public static function cleanUp(): void
    {
        $manager = PartitionManagerFactory::for(DB::connection());
        $keepDays = (int) Config::get('request-insurance.cleanUpKeepDays', 14);
        $olderThan = CarbonImmutable::now('UTC')->subDays($keepDays);
        $terminal = [State::COMPLETED, State::ABANDONED];

        $parentTable = resolve(RequestInsurance::class)->getTable();
        $logsTable = resolve(RequestInsuranceLog::class)->getTable();

        if ($manager->isSupported()) {
            $manager->ensureFuturePartitions($parentTable);
            $manager->ensureFuturePartitions($logsTable);
            $manager->pruneOldPartitions($parentTable, $olderThan, $manager->nonTerminalGuardFor($parentTable, $terminal));
            $manager->pruneOldPartitions($logsTable, $olderThan, fn () => true);

            return;
        }

        // Unsupported driver (e.g. sqlite): legacy row-delete retention.
        $manager->pruneOldPartitions($parentTable, $olderThan, fn () => true);
    }
}
