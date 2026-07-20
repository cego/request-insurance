<?php

namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\RequestInsuranceCleaner;

class UnsupportedPartitionManager extends PartitionManager
{
    public function isSupported(): bool
    {
        return false;
    }

    // Never partitioned on unsupported drivers; retention is handled by the
    // pruneOldPartitions() row-delete override below, so these are never reached.
    protected function isPartitioned(string $table): bool
    {
        return false;
    }

    /** @return array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable}> */
    protected function partitionRanges(string $table): array
    {
        return [];
    }

    protected function dropPartition(string $table, string $name): void
    {
        // No-op: unsupported drivers have no partitions to drop.
    }

    public function createPlainLike(string $source, string $target): void
    {
        // sqlite (and friends): clone columns only — no PK/index needed for the
        // small exceptions tables.
        $this->connection->statement("CREATE TABLE IF NOT EXISTS \"{$target}\" AS SELECT * FROM \"{$source}\" WHERE 1 = 0");
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
        $logsTable = Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';

        $query = $this->connection->table($table)
            ->where('created_at', '<', $olderThan->toDateTimeString())
            ->whereIn('state', [State::COMPLETED, State::ABANDONED]);

        RequestInsuranceCleaner::deleteRowsInChunks($this->connection, $query, $table, $logsTable);

        return [];
    }
}
