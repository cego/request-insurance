<?php

namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

abstract class PartitionManager
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly string $granularity = PartitionGranularity::DAILY,
        protected readonly int $precreateAhead = 7,
    ) {
        PartitionGranularity::assertValid($this->granularity);
    }

    abstract public function isSupported(): bool;

    /** @param array<int, string> $terminalStates */
    abstract public function migrateToPartitioned(string $table, array $terminalStates): void;

    abstract public function ensureFuturePartitions(string $table): void;

    /** @return array<int, string> dropped partition names */
    abstract public function pruneOldPartitions(string $table, CarbonImmutable $olderThan, Closure $partitionIsSafeToDrop): array;

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
}
