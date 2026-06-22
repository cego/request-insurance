<?php

// stub — replaced in Task 6
namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;

class PostgresPartitionManager extends PartitionManager
{
    public function isSupported(): bool
    {
        return true;
    }

    public function migrateToPartitioned(string $table, array $terminalStates): void
    {
        throw new \LogicException('not yet implemented');
    }

    public function ensureFuturePartitions(string $table): void
    {
        throw new \LogicException('not yet implemented');
    }

    public function pruneOldPartitions(string $table, CarbonImmutable $olderThan, Closure $partitionIsSafeToDrop): array
    {
        throw new \LogicException('not yet implemented');
    }
}
