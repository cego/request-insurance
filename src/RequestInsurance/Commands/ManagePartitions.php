<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class ManagePartitions extends Command
{
    protected $signature = 'request-insurance:manage-partitions';

    protected $description = 'Pre-creates upcoming RequestInsurance partitions and drops partitions older than the retention window';

    public function handle(): int
    {
        $manager = PartitionManagerFactory::for(DB::connection());

        if ( ! $manager->isSupported()) {
            $this->info('Driver does not support partitioning; nothing to manage.');

            return self::SUCCESS;
        }

        $keepDays = (int) Config::get('request-insurance.cleanUpKeepDays', 14);
        $olderThan = CarbonImmutable::now('UTC')->subDays($keepDays);
        $terminal = [State::COMPLETED, State::ABANDONED];

        $parentTable = resolve(RequestInsurance::class)->getTable();
        $logsTable = resolve(RequestInsuranceLog::class)->getTable();

        foreach ([$parentTable, $logsTable] as $table) {
            $manager->ensureFuturePartitions($table);
        }

        // Prune parent first (guarded), then logs (no guard needed; tied to parent retention).
        $guard = $manager->nonTerminalGuardFor($parentTable, $terminal);
        $droppedParent = $manager->pruneOldPartitions($parentTable, $olderThan, $guard);
        $droppedLogs = $manager->pruneOldPartitions($logsTable, $olderThan, fn () => true);

        $this->info(sprintf('Dropped %d parent and %d logs partitions.', count($droppedParent), count($droppedLogs)));

        return self::SUCCESS;
    }
}
