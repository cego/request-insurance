<?php

namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;

class UnsupportedPartitionManager extends PartitionManager
{
    public function isSupported(): bool
    {
        return false;
    }

    public function migrateToPartitioned(string $table, array $terminalStates): void
    {
        // Driver does not support partitioning (e.g. sqlite). The plain table
        // created by the base migrations is used as-is. Nothing to do.
    }

    public function ensureFuturePartitions(string $table): void
    {
        // No partitions on unsupported drivers.
    }

    public function pruneOldPartitions(string $table, CarbonImmutable $olderThan, Closure $partitionIsSafeToDrop): array
    {
        // Fallback to row-based deletion for unsupported drivers (legacy behaviour).
        $chunkSize = (int) Config::get('request-insurance.cleanChunkSize', 1000);
        $logsTable = Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';

        $this->connection->table($table)
            ->where('created_at', '<', $olderThan->toDateTimeString())
            ->whereIn('state', [State::COMPLETED, State::ABANDONED])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($table, $logsTable) {
                $ids = collect($rows)->pluck('id')->all();
                $this->connection->table($logsTable)->whereIn('request_insurance_id', $ids)->delete();
                $this->connection->table($table)->whereIn('id', $ids)->delete();
                usleep(10000);
            });

        return [];
    }
}
