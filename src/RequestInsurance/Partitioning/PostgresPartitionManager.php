<?php

namespace Cego\RequestInsurance\Partitioning;

use Carbon\CarbonImmutable;

class PostgresPartitionManager extends PartitionManager
{
    public function isSupported(): bool
    {
        return true;
    }

    public function createPlainLike(string $source, string $target): void
    {
        $this->connection->statement("CREATE TABLE IF NOT EXISTS \"{$target}\" (LIKE \"{$source}\" INCLUDING DEFAULTS INCLUDING INDEXES)");
    }

    /**
     * Convert the given non-partitioned table into a declarative RANGE
     * partitioned table (PARTITION BY RANGE (created_at)) with a composite
     * primary key (id, created_at).
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

        $logsTable = $this->logsTableFor($table);

        $this->migrateOneTable($table, $terminalStates, isLogs: false, parentTable: null);
        $this->migrateOneTable($logsTable, $terminalStates, isLogs: true, parentTable: $table);
    }

    /** @param array<int, string> $terminalStates */
    private function migrateOneTable(string $table, array $terminalStates, bool $isLogs, ?string $parentTable): void
    {
        if ($this->isPartitioned($table)) {
            return;
        }

        $legacy = $table . '_legacy';

        // ACCESS EXCLUSIVE blocks concurrent readers/writers; held until the
        // wrapping migration transaction commits, after which inserts resolve to
        // the freshly created partitioned table under the canonical name.
        $this->connection->statement("LOCK TABLE \"{$table}\" IN ACCESS EXCLUSIVE MODE");
        $this->connection->statement("ALTER TABLE \"{$table}\" RENAME TO \"{$legacy}\"");
        $this->connection->statement("CREATE TABLE \"{$table}\" (LIKE \"{$legacy}\" INCLUDING DEFAULTS INCLUDING GENERATED) PARTITION BY RANGE (created_at)");
        $this->connection->statement("ALTER TABLE \"{$table}\" ADD PRIMARY KEY (id, created_at)");

        // Re-own the legacy sequence to the new table's id column so identity keeps incrementing.
        $seqRow = $this->connection->selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$legacy, 'id']);
        $seq = $seqRow?->s;

        if ($seq !== null) {
            $this->connection->statement("ALTER SEQUENCE {$seq} OWNED BY \"{$table}\".id");
            $this->connection->statement("ALTER TABLE \"{$table}\" ALTER COLUMN id SET DEFAULT nextval('{$seq}')");
        }

        // Forward partitions + DEFAULT catch-all.
        $oldestActive = $isLogs
            ? $this->connection->table($legacy)->min('created_at')
            : $this->connection->table($legacy)->whereNotIn('state', $terminalStates)->min('created_at');

        $first = $oldestActive !== null
            ? PartitionWindow::forDate(CarbonImmutable::parse($oldestActive, 'UTC'))
            : PartitionWindow::forDate(CarbonImmutable::now('UTC'));

        $windows = PartitionWindow::range($first->start(), CarbonImmutable::now('UTC')->addDays($this->precreateAhead));

        foreach ($windows as $window) {
            $this->createPartition($table, $window);
        }

        // DEFAULT partition kept empty: pre-creation keeps range partitions ahead
        // of inserts, so no rows ever land here, and prune never needs to scan it.
        $this->connection->statement("CREATE TABLE \"{$table}_default\" PARTITION OF \"{$table}\" DEFAULT");

        // Copy active rows (or logs of active rows).
        if ($isLogs) {
            $this->connection->statement(
                "INSERT INTO \"{$table}\" SELECT l.* FROM \"{$legacy}\" l WHERE l.request_insurance_id IN (SELECT id FROM \"{$parentTable}\")"
            );
        } else {
            $placeholders = implode(',', array_fill(0, count($terminalStates), '?'));
            $this->connection->statement(
                "INSERT INTO \"{$table}\" SELECT * FROM \"{$legacy}\" WHERE state NOT IN ({$placeholders})",
                array_values($terminalStates)
            );
        }

        // Advance the sequence past the highest id seen in the legacy table so
        // new inserts never collide with not-yet-copied (terminal) rows.
        if ($seq !== null) {
            $this->connection->statement(
                "SELECT setval('{$seq}', GREATEST((SELECT COALESCE(MAX(id), 1) FROM \"{$legacy}\"), 1))"
            );
        }
    }

    public function ensureFuturePartitions(string $table): void
    {
        if ( ! $this->isPartitioned($table)) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $windows = PartitionWindow::range($now, $now->addDays($this->precreateAhead));
        $existing = $this->existingPartitionNames($table);

        foreach ($windows as $window) {
            $child = "{$table}_{$window->name()}";

            if ( ! in_array($child, $existing, true)) {
                $this->createPartition($table, $window);
            }
        }
    }

    // DROP TABLE on a child detaches and removes it atomically.
    protected function dropPartition(string $table, string $name): void
    {
        $this->connection->statement("DROP TABLE IF EXISTS \"{$name}\"");
    }

    private function createPartition(string $table, PartitionWindow $window): void
    {
        $child = "{$table}_{$window->name()}";

        $this->connection->statement(sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" PARTITION OF "%s" FOR VALUES FROM (\'%s\') TO (\'%s\')',
            $child,
            $table,
            $window->start()->toDateTimeString(),
            $window->end()->toDateTimeString()
        ));
    }

    protected function isPartitioned(string $table): bool
    {
        $row = $this->connection->selectOne('SELECT relkind FROM pg_class WHERE relname = ?', [$table]);

        return $row !== null && $row->relkind === 'p';
    }

    /** @return array<int, string> child relation names */
    private function existingPartitionNames(string $table): array
    {
        $rows = $this->connection->select(
            'SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent WHERE p.relname = ?',
            [$table]
        );

        return array_map(fn ($r) => $r->relname, $rows);
    }

    /** @return array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable}> */
    protected function partitionRanges(string $table): array
    {
        $rows = $this->connection->select(
            'SELECT c.relname, pg_get_expr(c.relpartbound, c.oid) AS bound
             FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent
             WHERE p.relname = ?',
            [$table]
        );

        $ranges = [];

        foreach ($rows as $r) {
            if (str_contains($r->bound, 'DEFAULT')) {
                $ranges[$r->relname] = [CarbonImmutable::createFromTimestamp(0, 'UTC'), null];

                continue;
            }

            // bound looks like: FOR VALUES FROM ('2026-06-22 00:00:00') TO ('2026-06-23 00:00:00')
            if (preg_match("/FROM \\('([^']+)'\\) TO \\('([^']+)'\\)/", $r->bound, $m)) {
                $ranges[$r->relname] = [CarbonImmutable::parse($m[1], 'UTC'), CarbonImmutable::parse($m[2], 'UTC')];
            }
        }

        return $ranges;
    }
}
