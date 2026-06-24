<?php

namespace Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;
use Cego\RequestInsurance\Partitioning\PartitionNotDroppableException;

class ExceptionsPartitioningTest extends IntegrationTestCase
{
    public function test_migration_partitions_main_tables_and_creates_exceptions_tables(): void
    {
        $this->assertTrue($this->isPartitioned(FailedRequestMover::mainTable()));
        $this->assertTrue($this->isPartitioned(FailedRequestMover::mainLogsTable()));

        $this->assertTrue($this->tableExists(FailedRequestMover::failedTable()));
        $this->assertTrue($this->tableExists(FailedRequestMover::failedLogsTable()));
        $this->assertFalse($this->isPartitioned(FailedRequestMover::failedTable()));
    }

    public function test_failing_then_retrying_moves_a_row_out_of_and_back_into_the_partitioned_table(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        $this->assertNull(RequestInsurance::query()->find($requestInsurance->id));
        $this->assertNotNull(RequestInsuranceFailed::query()->find($requestInsurance->id));

        RequestInsuranceFailed::query()->find($requestInsurance->id)->retryNow();

        $restored = RequestInsurance::query()->find($requestInsurance->id);
        $this->assertNotNull($restored);
        $this->assertSame(State::READY, $restored->state);
        $this->assertNull(RequestInsuranceFailed::query()->find($requestInsurance->id));
    }

    public function test_prune_drops_a_partition_holding_only_completed_rows(): void
    {
        RequestInsurance::factory()->create(['state' => State::COMPLETED]);
        $this->assertSame(1, DB::table(FailedRequestMover::mainTable())->count());

        // A cutoff far in the future makes every pre-created partition "aged"; the
        // one holding the COMPLETED row is droppable, so the row disappears wholesale.
        $manager = $this->manager();
        $manager->pruneOldPartitions(
            FailedRequestMover::mainTable(),
            CarbonImmutable::now('UTC')->addDays(60),
            $manager->nonTerminalGuardFor(FailedRequestMover::mainTable(), [State::COMPLETED])
        );

        $this->assertSame(0, DB::table(FailedRequestMover::mainTable())->count());
    }

    public function test_prune_throws_when_an_aged_partition_holds_a_non_completed_row(): void
    {
        // A non-COMPLETED row that was never extracted to the exceptions tables must
        // make retention fail loud rather than silently drop a row needing attention.
        RequestInsurance::factory()->create(['state' => State::READY]);

        $manager = $this->manager();

        $this->expectException(PartitionNotDroppableException::class);

        $manager->pruneOldPartitions(
            FailedRequestMover::mainTable(),
            CarbonImmutable::now('UTC')->addDays(60),
            $manager->nonTerminalGuardFor(FailedRequestMover::mainTable(), [State::COMPLETED])
        );
    }
}
