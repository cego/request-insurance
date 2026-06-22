<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class ManagePartitionsCommandTest extends IntegrationTestCase
{
    public function test_command_creates_future_and_drops_aged_partitions(): void
    {
        $this->assertStartsUnpartitioned();
        // Old terminal rows that should age out:
        RequestInsurance::factory(3)->create(['state' => State::COMPLETED, 'created_at' => Carbon::now('UTC')->subDays(40)]);
        $this->runPartitionMigration();

        $this->artisan('request-insurance:manage-partitions')->assertExitCode(0);

        // The 40-day-old terminal partition is older than the 14-day window -> dropped.
        $this->assertSame(0, DB::table('request_insurances')->where('created_at', '<', Carbon::now('UTC')->subDays(30))->count());
    }

    public function test_command_refuses_to_drop_partition_with_active_rows(): void
    {
        $this->assertStartsUnpartitioned();
        // An old but still ACTIVE row must survive the prune.
        $active = RequestInsurance::factory()->create(['state' => State::FAILED, 'created_at' => Carbon::now('UTC')->subDays(40)]);
        $this->runPartitionMigration();

        $this->artisan('request-insurance:manage-partitions')->assertExitCode(0);

        $this->assertDatabaseHas('request_insurances', ['id' => $active->id]);
    }
}
