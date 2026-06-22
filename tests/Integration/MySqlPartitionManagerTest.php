<?php

namespace Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Partitioning\PartitionManagerFactory;

class MySqlPartitionManagerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->driverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only test');
        }
    }

    public function test_migrate_freezes_terminal_rows_and_copies_active_rows(): void
    {
        $this->seedPlainTableWithRows();

        $manager = PartitionManagerFactory::for(DB::connection());

        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        $partitions = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.partitions WHERE table_schema=DATABASE() AND table_name=? AND partition_name IS NOT NULL',
            ['request_insurances']
        );
        $this->assertGreaterThan(0, $partitions->c);

        $this->assertSame(2, DB::table('request_insurances')->count());
        $this->assertSame(0, DB::table('request_insurances')->whereIn('state', [State::COMPLETED, State::ABANDONED])->count());
        $this->assertTrue($this->tableExists('request_insurances_legacy'));
        $this->assertSame(5, DB::table('request_insurances_legacy')->count());

        // Logs table is partitioned too, and only logs of active rows are copied.
        $logsPartitions = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.partitions WHERE table_schema=DATABASE() AND table_name=? AND partition_name IS NOT NULL',
            ['request_insurance_logs']
        );
        $this->assertGreaterThan(0, $logsPartitions->c);
        $this->assertTrue($this->tableExists('request_insurance_logs_legacy'));
        // Only the 2 logs belonging to active rows (ids 4 + 5) were copied; all 5 retained in legacy.
        $this->assertSame(2, DB::table('request_insurance_logs')->count());
        $this->assertSame(5, DB::table('request_insurance_logs_legacy')->count());
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

        $after = DB::select("SELECT partition_name pn FROM information_schema.partitions WHERE table_schema=DATABASE() AND table_name='request_insurances' AND partition_name IS NOT NULL");
        // At least the pre-create window (7 days ahead) + pmax.
        $this->assertGreaterThanOrEqual(2, count($after));

        // Guard must keep the partition still holding the 2 active rows, even
        // though the retention cutoff is far in the future (everything is "old").
        // Empty pre-created partitions older than the cutoff may be dropped — only
        // the partition with non-terminal rows must survive.
        $activePartition = $this->partitionHoldingActiveRows();
        $guard = $manager->nonTerminalGuardFor('request_insurances', [State::COMPLETED, State::ABANDONED]);
        $dropped = $manager->pruneOldPartitions('request_insurances', CarbonImmutable::now('UTC')->addDays(30), $guard);
        $this->assertNotContains($activePartition, $dropped);
        $this->assertSame(2, DB::table('request_insurances')->count());
    }

    private function partitionHoldingActiveRows(): string
    {
        $minCreatedAt = DB::table('request_insurances')->min('created_at');

        return \Cego\RequestInsurance\Partitioning\PartitionWindow::forDate(
            CarbonImmutable::parse($minCreatedAt, 'UTC'),
            'daily'
        )->name();
    }

    public function test_prune_drops_old_empty_partitions(): void
    {
        $this->seedPlainTableWithRows();
        $manager = PartitionManagerFactory::for(DB::connection());
        $manager->migrateToPartitioned('request_insurances', [State::COMPLETED, State::ABANDONED]);

        // Add a future empty partition (well beyond any active row), then prune
        // everything older than a cutoff that sits between the active rows'
        // partition and the new empty one. The empty partition is droppable
        // (zero non-terminal rows -> guard satisfied), the populated one is not.
        $futureName = 'p29990101';
        DB::statement("ALTER TABLE `request_insurances` REORGANIZE PARTITION pmax INTO (PARTITION {$futureName} VALUES LESS THAN ('2999-01-02 00:00:00'), PARTITION pmax VALUES LESS THAN (MAXVALUE))");

        $guard = $manager->nonTerminalGuardFor('request_insurances', [State::COMPLETED, State::ABANDONED]);

        // Cutoff far in the future: the empty 2999 partition is "older than" the
        // cutoff and empty, so it is dropped; the active-row partition is too but
        // the guard protects it.
        $dropped = $manager->pruneOldPartitions('request_insurances', CarbonImmutable::parse('3000-01-01', 'UTC'), $guard);

        $this->assertContains($futureName, $dropped);
        $this->assertSame(2, DB::table('request_insurances')->count());
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

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [$table]
        );

        return (int) $row->c > 0;
    }
}
