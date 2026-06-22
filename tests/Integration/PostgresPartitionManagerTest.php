<?php

namespace Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Partitioning\PartitionWindow;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class PostgresPartitionManagerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->driverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only test');
        }
    }

    public function test_migrate_freezes_terminal_rows_and_copies_active_rows(): void
    {
        $this->seedPlainTableWithRows();

        $manager = PartitionManagerFactory::for(DB::connection());

        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        $this->assertTrue($this->isPartitioned('request_insurances'));

        $this->assertSame(2, DB::table('request_insurances')->count());
        $this->assertSame(0, DB::table('request_insurances')->whereIn('state', [State::COMPLETED, State::ABANDONED])->count());
        $this->assertTrue($this->tableExists('request_insurances_legacy'));
        $this->assertSame(5, DB::table('request_insurances_legacy')->count());

        // Logs table is partitioned too, and only logs of active rows are copied.
        $this->assertTrue($this->isPartitioned('request_insurance_logs'));
        $this->assertTrue($this->tableExists('request_insurance_logs_legacy'));
        // Only the 2 logs belonging to active rows (ids 4 + 5) were copied; all 5 retained in legacy.
        $this->assertSame(2, DB::table('request_insurance_logs')->count());
        $this->assertSame(5, DB::table('request_insurance_logs_legacy')->count());
    }

    public function test_sequence_continues_after_migration(): void
    {
        $this->seedPlainTableWithRows();

        $manager = PartitionManagerFactory::for(DB::connection());
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        // Inserting without an explicit id must continue past the legacy MAX(id) (=5).
        $id = DB::table('request_insurances')->insertGetId([
            'priority'     => 9999,
            'url'          => 'https://example.test/new',
            'method'       => 'POST',
            'headers'      => '[]',
            'payload'      => '[]',
            'retry_count'  => 0,
            'retry_factor' => 2,
            'retry_cap'    => 3600,
            'state'        => State::READY,
            'created_at'   => CarbonImmutable::now('UTC')->toDateTimeString(),
            'updated_at'   => CarbonImmutable::now('UTC')->toDateTimeString(),
        ]);

        $this->assertGreaterThan(5, $id);
    }

    public function test_migrate_is_idempotent(): void
    {
        $this->seedPlainTableWithRows();
        $manager = PartitionManagerFactory::for(DB::connection());
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]); // second call no-op
        $this->assertSame(2, DB::table('request_insurances')->count());
    }

    public function test_ensure_creates_future_partitions_and_prune_respects_guard(): void
    {
        $this->seedPlainTableWithRows();
        $manager = PartitionManagerFactory::for(DB::connection());
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        $manager->ensureFuturePartitions('request_insurances');

        $after = $this->childPartitions('request_insurances');
        // At least the pre-create window (7 days ahead) + default.
        $this->assertGreaterThanOrEqual(2, count($after));

        // Guard must keep the partition still holding the 2 active rows, even
        // though the retention cutoff is far in the future (everything is "old").
        $activePartition = $this->partitionHoldingActiveRows();
        $guard = $manager->nonTerminalGuardFor('request_insurances', [State::COMPLETED, State::ABANDONED]);
        $dropped = $manager->pruneOldPartitions('request_insurances', CarbonImmutable::now('UTC')->addDays(30), $guard);
        $this->assertNotContains($activePartition, $dropped);
        $this->assertSame(2, DB::table('request_insurances')->count());
    }

    public function test_prune_drops_old_empty_partitions(): void
    {
        $this->seedPlainTableWithRows();
        $manager = PartitionManagerFactory::for(DB::connection());
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        // Add a future empty partition (well beyond any active row), then prune
        // everything older than a cutoff that sits beyond it. The empty partition
        // is droppable (zero non-terminal rows -> guard satisfied), the populated
        // one is protected by the guard.
        $futureName = 'request_insurances_p29990101';
        DB::statement("CREATE TABLE \"{$futureName}\" PARTITION OF \"request_insurances\" FOR VALUES FROM ('2999-01-01 00:00:00') TO ('2999-01-02 00:00:00')");

        $guard = $manager->nonTerminalGuardFor('request_insurances', [State::COMPLETED, State::ABANDONED]);

        $dropped = $manager->pruneOldPartitions('request_insurances', CarbonImmutable::parse('3000-01-01', 'UTC'), $guard);

        $this->assertContains($futureName, $dropped);
        $this->assertSame(2, DB::table('request_insurances')->count());
    }

    private function partitionHoldingActiveRows(): string
    {
        $minCreatedAt = DB::table('request_insurances')->min('created_at');

        $window = PartitionWindow::forDate(
            CarbonImmutable::parse($minCreatedAt, 'UTC'),
            'daily'
        );

        return 'request_insurances_' . $window->name();
    }

    private function seedPlainTableWithRows(): void
    {
        $now = CarbonImmutable::now('UTC');

        $rows = [];
        $states = [State::COMPLETED, State::COMPLETED, State::COMPLETED, State::READY, State::PENDING];

        foreach ($states as $i => $state) {
            $createdAt = $now->subMinutes(($i + 1) * 5)->toDateTimeString();
            $rows[] = [
                'id'           => $i + 1,
                'priority'     => 9999,
                'url'          => 'https://example.test/' . ($i + 1),
                'method'       => 'POST',
                'headers'      => '[]',
                'payload'      => '[]',
                'retry_count'  => 0,
                'retry_factor' => 2,
                'retry_cap'    => 3600,
                'state'        => $state,
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ];
        }

        DB::table('request_insurances')->insert($rows);

        // One log per row so we can verify only active rows' logs are carried over.
        $logs = [];

        foreach ($rows as $i => $row) {
            $logs[] = [
                'id'                   => $i + 1,
                'request_insurance_id' => $row['id'],
                'response_headers'     => '[]',
                'response_body'        => 'ok',
                'response_code'        => 200,
                'created_at'           => $row['created_at'],
                'updated_at'           => $row['created_at'],
            ];
        }
        DB::table('request_insurance_logs')->insert($logs);
    }

    /** @return array<int, string> */
    private function childPartitions(string $table): array
    {
        $rows = DB::select(
            'SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_class p ON p.oid = i.inhparent WHERE p.relname = ?',
            [$table]
        );

        return array_map(fn ($r) => $r->relname, $rows);
    }

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.tables WHERE table_name = ?',
            [$table]
        );

        return (int) $row->c > 0;
    }
}
