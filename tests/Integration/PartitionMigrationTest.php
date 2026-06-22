<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class PartitionMigrationTest extends IntegrationTestCase
{
    public function test_migration_moves_active_rows_and_keeps_terminal_in_legacy(): void
    {
        $this->assertStartsUnpartitioned(); // creates the plain tables (auto-run by RefreshDatabase)

        RequestInsurance::factory(3)->create(['state' => State::COMPLETED, 'created_at' => Carbon::now('UTC')->subDay()]);
        RequestInsurance::factory(2)->create(['state' => State::READY, 'created_at' => Carbon::now('UTC')]);

        $this->runPartitionMigration();

        $this->assertSame(2, DB::table('request_insurances')->count());
        $this->assertSame(5, DB::table('request_insurances_legacy')->count());

        // A row inserted AFTER migration lands in the partitioned table and is processable.
        $new = RequestInsurance::factory()->create(['state' => State::READY, 'created_at' => Carbon::now('UTC')]);
        $this->assertDatabaseHas('request_insurances', ['id' => $new->id]);
    }

    public function test_id_sequence_continues_past_legacy_max(): void
    {
        $this->assertStartsUnpartitioned();

        RequestInsurance::factory(5)->create(['state' => State::COMPLETED, 'created_at' => Carbon::now('UTC')->subDay()]);
        $maxBefore = (int) DB::table('request_insurances')->max('id');

        $this->runPartitionMigration();

        $new = RequestInsurance::factory()->create(['state' => State::READY, 'created_at' => Carbon::now('UTC')]);
        $this->assertGreaterThan($maxBefore, $new->id, 'new ids must not collide with legacy ids');
    }

    public function test_no_active_row_is_lost_and_post_migration_inserts_persist(): void
    {
        $this->assertStartsUnpartitioned();

        // A mix of terminal and active rows spread across two days.
        RequestInsurance::factory(4)->create(['state' => State::COMPLETED, 'created_at' => Carbon::now('UTC')->subDays(2)]);
        RequestInsurance::factory(3)->create(['state' => State::READY, 'created_at' => Carbon::now('UTC')->subDay()]);
        RequestInsurance::factory(2)->create(['state' => State::PENDING, 'created_at' => Carbon::now('UTC')]);

        $activeIdsBefore = DB::table('request_insurances')
            ->whereNotIn('state', [State::COMPLETED, State::ABANDONED])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertCount(5, $activeIdsBefore);

        $this->runPartitionMigration();

        // Every active row survives the cutover into the partitioned table; none dropped.
        $activeIdsAfter = DB::table('request_insurances')->orderBy('id')->pluck('id')->all();
        $this->assertSame($activeIdsBefore, $activeIdsAfter, 'all active rows must survive the cutover');

        // The full original set (active + terminal) is preserved in legacy.
        $this->assertSame(9, DB::table('request_insurances_legacy')->count());

        // Rows inserted immediately after the cutover persist in the partitioned table.
        $insertedIds = [];
        for ($i = 0; $i < 3; $i++) {
            $insertedIds[] = RequestInsurance::factory()->create(['state' => State::READY, 'created_at' => Carbon::now('UTC')])->id;
        }

        foreach ($insertedIds as $id) {
            $this->assertDatabaseHas('request_insurances', ['id' => $id]);
        }

        // Post-migration rows do not collide with any pre-existing id.
        $this->assertEmpty(array_intersect($insertedIds, $activeIdsBefore));
    }
}
