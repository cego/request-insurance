<?php

namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\ConnectionInterface;

abstract class PartitionManager
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly int                 $precreateAhead = 7,
    ) {
    }

    abstract public function isSupported(): bool;

    /**
     * Create a plain (non-partitioned) table $target shaped like $source, used
     * for the exceptions ("failed jobs") tables. Idempotent.
     */
    abstract public function createPlainLike(string $source, string $target): void;

    /** @param array<int, string> $terminalStates */
    abstract public function migrateToPartitioned(string $table, array $terminalStates): void;

    abstract public function ensureFuturePartitions(string $table): void;

    /**
     * Drops partitions whose upper bound is at or before $olderThan, provided the
     * guard confirms the range holds no non-terminal rows. A partition failing the
     * guard is skipped with a warning so the remaining partitions still get pruned.
     * Driver specifics are delegated to isPartitioned()/partitionRanges()/dropPartition().
     *
     * @return array<int, string> dropped partition names
     */
    public function pruneOldPartitions(string $table, CarbonImmutable $olderThan, Closure $partitionIsSafeToDrop): array
    {
        if ( ! $this->isPartitioned($table)) {
            return [];
        }

        $dropped = [];

        foreach ($this->partitionRanges($table) as $name => [$start, $end]) {
            if ($end === null) {
                continue; // catch-all partition (pmax / DEFAULT) is never dropped
            }

            if ($end->greaterThan($olderThan)) {
                continue; // still within the retention window
            }

            if ( ! $partitionIsSafeToDrop($start, $end)) {
                Log::warning("Refusing to drop aged partition {$name} on {$table}: it still holds non-COMPLETED rows that should have been extracted to the exceptions tables");

                continue;
            }

            $this->dropPartition($table, $name);
            $dropped[] = $name;
        }

        return $dropped;
    }

    abstract protected function isPartitioned(string $table): bool;

    /** @return array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable}> name => [start, end] (end null for the catch-all) */
    abstract protected function partitionRanges(string $table): array;

    abstract protected function dropPartition(string $table, string $name): void;

    /**
     * Returns a closure(CarbonImmutable $start, CarbonImmutable $end): bool
     * that is true only when the given date range contains zero non-terminal rows.
     *
     * @param array<int, string> $terminalStates
     */
    public function nonTerminalGuardFor(string $table, array $terminalStates): Closure
    {
        return function (CarbonImmutable $start, CarbonImmutable $end) use ($table, $terminalStates): bool {
            $count = $this->connection->table($table)
                ->where('created_at', '>=', $start->toDateTimeString())
                ->where('created_at', '<', $end->toDateTimeString())
                ->whereNotIn('state', $terminalStates)
                ->count();

            return $count === 0;
        };
    }

    /**
     * Resolve the canonical logs table name.
     *
     * The logs table is `request_insurance_logs` (singular "insurance"); it must
     * never be derived by concatenating `_logs` onto the parent table name.
     */
    protected function logsTableFor(string $table): string
    {
        $configuredParent = Config::get('request-insurance.table') ?? 'request_insurances';

        if ($table === $configuredParent) {
            return Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';
        }

        return $table . '_logs';
    }
}
