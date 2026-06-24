<?php

namespace Cego\RequestInsurance\Partitioning;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

class MySqlPartitionManager extends PartitionManager
{
    public function isSupported(): bool
    {
        return true;
    }

    public function createPlainLike(string $source, string $target): void
    {
        $this->connection->statement("CREATE TABLE IF NOT EXISTS `{$target}` LIKE `{$source}`");
    }

    /**
     * Convert the given non-partitioned table into a RANGE COLUMNS(created_at)
     * partitioned table with a composite primary key (id, created_at).
     *
     * The original table is preserved (renamed to `{table}_legacy`) and only
     * non-terminal rows are copied into the new partitioned table. The logs
     * table is migrated alongside it, carrying over only the logs belonging to
     * the rows that were copied.
     *
     * Idempotent: a second call is a no-op once the table is partitioned.
     *
     * @param array<int, string> $terminalStates
     */
    public function migrateToPartitioned(string $table, array $terminalStates): void
    {
        if ($this->isPartitioned($table)) {
            return; // idempotent
        }

        $newTable = $table . '_new';
        $legacyTable = $table . '_legacy';

        $maxId = (int) ($this->connection->table($table)->max('id') ?? 0);
        $oldestActive = $this->connection->table($table)
            ->whereNotIn('state', $terminalStates)
            ->min('created_at');

        $createSql = $this->buildPartitionedCreateSql($table, $newTable, $oldestActive, $maxId);

        $this->connection->statement("DROP TABLE IF EXISTS `{$newTable}`");
        $this->connection->statement($createSql);

        // Atomic swap: no window where the canonical name is missing.
        $this->connection->statement(
            "RENAME TABLE `{$table}` TO `{$legacyTable}`, `{$newTable}` TO `{$table}`"
        );

        // Copy only non-terminal rows; INSERT IGNORE guards against re-created ids.
        $placeholders = implode(',', array_fill(0, count($terminalStates), '?'));
        $this->connection->statement(
            "INSERT IGNORE INTO `{$table}` SELECT * FROM `{$legacyTable}` WHERE state NOT IN ({$placeholders})",
            array_values($terminalStates)
        );

        // Also migrate the logs table for the active rows (logs of terminal rows
        // remain in the legacy logs table and are pruned along with it).
        $this->migrateLogsTable($table);
    }

    public function ensureFuturePartitions(string $table): void
    {
        if ( ! $this->isPartitioned($table)) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $windows = PartitionWindow::range($now, $now->addDays($this->precreateAhead), $this->granularity);
        $existing = $this->existingPartitionNames($table);

        foreach ($windows as $window) {
            if (in_array($window->name(), $existing, true)) {
                continue;
            }

            // Split the empty MAXVALUE tail to add the new range before it.
            $this->connection->statement(sprintf(
                "ALTER TABLE `%s` REORGANIZE PARTITION pmax INTO (PARTITION %s VALUES LESS THAN ('%s'), PARTITION pmax VALUES LESS THAN (MAXVALUE))",
                $table,
                $window->name(),
                $window->end()->toDateTimeString()
            ));

            $existing[] = $window->name();
        }
    }

    /**
     * @param array<int, string> $terminalStates ignored here; safety is delegated to the guard closure
     *
     * @return array<int, string> dropped partition names
     */
    public function pruneOldPartitions(string $table, CarbonImmutable $olderThan, Closure $partitionIsSafeToDrop): array
    {
        if ( ! $this->isPartitioned($table)) {
            return [];
        }

        $logsTable = $this->logsTableFor($table);
        $logsIsPartitioned = $this->isPartitioned($logsTable);
        $logsPartitions = $logsIsPartitioned ? $this->existingPartitionNames($logsTable) : [];

        $dropped = [];

        foreach ($this->partitionRanges($table) as $name => [$start, $end]) {
            if ($name === 'pmax' || $end === null) {
                continue; // never drop the catch-all
            }

            if ($end->greaterThan($olderThan)) {
                continue; // partition still within retention window
            }

            if ( ! $partitionIsSafeToDrop($start, $end)) {
                throw new PartitionNotDroppableException("Refusing to drop partition {$name} on {$table}: it still holds non-COMPLETED rows that should have been extracted to the exceptions tables");
            }

            $this->connection->statement("ALTER TABLE `{$table}` DROP PARTITION {$name}");

            if ($logsIsPartitioned && in_array($name, $logsPartitions, true)) {
                $this->connection->statement("ALTER TABLE `{$logsTable}` DROP PARTITION {$name}");
            }

            $dropped[] = $name;
        }

        return $dropped;
    }

    private function isPartitioned(string $table): bool
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) c FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            [$table]
        );

        return (int) $row->c > 0;
    }

    /** @return array<int, string> */
    private function existingPartitionNames(string $table): array
    {
        $rows = $this->connection->select(
            'SELECT partition_name pn FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            [$table]
        );

        return array_map(fn ($r) => $r->pn, $rows);
    }

    /** @return array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable}> */
    private function partitionRanges(string $table): array
    {
        // RANGE COLUMNS(created_at) stores the quoted upper bound in partition_description.
        $rows = $this->connection->select(
            'SELECT partition_name pn, partition_description pd FROM information_schema.partitions WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL ORDER BY partition_ordinal_position',
            [$table]
        );

        $ranges = [];
        $prevEnd = null;

        foreach ($rows as $r) {
            if (str_contains($r->pd, 'MAXVALUE')) {
                $ranges[$r->pn] = [$prevEnd ?? CarbonImmutable::createFromTimestamp(0, 'UTC'), null];

                continue;
            }

            $end = CarbonImmutable::parse(trim($r->pd, "'\""), 'UTC');
            $ranges[$r->pn] = [$prevEnd ?? CarbonImmutable::createFromTimestamp(0, 'UTC'), $end];
            $prevEnd = $end;
        }

        return $ranges;
    }

    private function buildPartitionedCreateSql(string $table, string $newTable, ?string $oldestActive, int $maxId): string
    {
        $row = $this->connection->selectOne("SHOW CREATE TABLE `{$table}`");
        $ddl = $row->{'Create Table'};

        // Rename target table.
        $ddl = preg_replace('/^CREATE TABLE `' . preg_quote($table, '/') . '`/', "CREATE TABLE `{$newTable}`", $ddl);

        // RANGE COLUMNS partitioning requires the partition column to be a
        // DATE/DATETIME (TIMESTAMP is not permitted), and a primary-key column
        // may not be NULLable. The base schema stores created_at as a nullable
        // timestamp, so rewrite it to a NOT NULL datetime before it becomes part
        // of the composite key and the partition expression.
        $ddl = preg_replace(
            '/`created_at`\s+timestamp(\(\d+\))?\s+(?:NOT\s+NULL|NULL)?(?:\s+DEFAULT\s+[^,\n]+)?/i',
            '`created_at` datetime$1 NOT NULL DEFAULT CURRENT_TIMESTAMP$1',
            $ddl,
            1
        );

        // Rewrite the primary key to a composite that includes the partition column.
        $ddl = preg_replace('/PRIMARY KEY \(`id`\)/', 'PRIMARY KEY (`id`,`created_at`)', $ddl);

        // Drop any AUTO_INCREMENT table option; we set it explicitly below.
        $ddl = preg_replace('/\s+AUTO_INCREMENT=\d+/', '', $ddl);

        // Build the partition list. The first partition's lower bound is open in
        // RANGE COLUMNS, so any historical rows below it fall into it naturally.
        $first = $oldestActive !== null
            ? PartitionWindow::forDate(CarbonImmutable::parse($oldestActive, 'UTC'), $this->granularity)
            : PartitionWindow::forDate(CarbonImmutable::now('UTC'), $this->granularity);

        $now = CarbonImmutable::now('UTC');
        $windows = PartitionWindow::range($first->start(), $now->addDays($this->precreateAhead), $this->granularity);

        $parts = [];

        foreach ($windows as $w) {
            $parts[] = sprintf("PARTITION %s VALUES LESS THAN ('%s')", $w->name(), $w->end()->toDateTimeString());
        }
        $parts[] = 'PARTITION pmax VALUES LESS THAN (MAXVALUE)';

        $seededAutoIncrement = $maxId + 100000; // buffer so new rows never collide with not-yet-copied actives

        return rtrim($ddl)
            . " AUTO_INCREMENT={$seededAutoIncrement}"
            . ' PARTITION BY RANGE COLUMNS (created_at) ('
            . implode(', ', $parts)
            . ')';
    }

    private function migrateLogsTable(string $table): void
    {
        $logsTable = $this->logsTableFor($table);

        if ($this->isPartitioned($logsTable)) {
            return;
        }

        $newLogs = $logsTable . '_new';
        $legacyLogs = $logsTable . '_legacy';
        $maxId = (int) ($this->connection->table($logsTable)->max('id') ?? 0);

        // Logs reuse the same partition windows keyed on their own created_at.
        $createSql = $this->buildPartitionedCreateSql($logsTable, $newLogs, null, $maxId);
        $this->connection->statement("DROP TABLE IF EXISTS `{$newLogs}`");
        $this->connection->statement($createSql);
        $this->connection->statement("RENAME TABLE `{$logsTable}` TO `{$legacyLogs}`, `{$newLogs}` TO `{$logsTable}`");

        // Copy logs belonging to the active rows that were copied into the new parent table.
        $this->connection->statement(
            "INSERT IGNORE INTO `{$logsTable}` SELECT l.* FROM `{$legacyLogs}` l WHERE l.request_insurance_id IN (SELECT id FROM `{$table}`)"
        );
    }

    /**
     * Resolve the canonical logs table name.
     *
     * The logs table is `request_insurance_logs` (singular "insurance"); it must
     * never be derived by concatenating `_logs` onto the parent table name.
     */
    private function logsTableFor(string $table): string
    {
        $configuredParent = Config::get('request-insurance.table') ?? 'request_insurances';

        if ($table === $configuredParent) {
            return Config::get('request-insurance.table_logs') ?? 'request_insurance_logs';
        }

        return $table . '_logs';
    }
}
