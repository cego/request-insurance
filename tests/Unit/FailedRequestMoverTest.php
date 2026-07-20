<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\RequestInsuranceCleaner;
use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;

class FailedRequestMoverTest extends TestCase
{
    public function test_failing_a_request_moves_it_and_its_logs_to_the_exceptions_tables(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);
        RequestInsuranceLog::factory(2)->create(['request_insurance_id' => $requestInsurance->id]);

        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        // Gone from the main tables.
        $this->assertNull(RequestInsurance::query()->find($requestInsurance->id));
        $this->assertSame(0, DB::table(FailedRequestMover::mainLogsTable())->where('request_insurance_id', $requestInsurance->id)->count());

        // Present in the exceptions tables.
        $failed = RequestInsuranceFailed::query()->find($requestInsurance->id);
        $this->assertNotNull($failed);
        $this->assertSame(State::FAILED, $failed->state);
        $this->assertSame(2, $failed->logs()->count());
    }

    public function test_abandon_moves_the_request_to_the_exceptions_table(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $requestInsurance->abandon();

        $this->assertNull(RequestInsurance::query()->find($requestInsurance->id));
        $failed = RequestInsuranceFailed::query()->find($requestInsurance->id);
        $this->assertNotNull($failed);
        $this->assertSame(State::ABANDONED, $failed->state);
    }

    public function test_route_binding_resolves_main_then_exceptions(): void
    {
        $active = RequestInsurance::factory()->create(['state' => State::READY]);

        $failedSource = RequestInsurance::factory()->create(['state' => State::READY]);
        $failedSource->setState(State::FAILED);
        $failedSource->save();
        FailedRequestMover::moveToFailed($failedSource);

        $binder = new RequestInsurance();

        $this->assertInstanceOf(RequestInsurance::class, $binder->resolveRouteBinding($active->id));
        $this->assertInstanceOf(RequestInsuranceFailed::class, $binder->resolveRouteBinding($failedSource->id));
    }

    public function test_retrying_a_failed_request_restores_it_to_the_main_table_as_ready(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);
        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        $failed = RequestInsuranceFailed::query()->find($requestInsurance->id);
        $failed->retryNow();

        // Back in the main table as READY, gone from the exceptions table.
        $restored = RequestInsurance::query()->find($requestInsurance->id);
        $this->assertNotNull($restored);
        $this->assertSame(State::READY, $restored->state);
        $this->assertNull(RequestInsuranceFailed::query()->find($requestInsurance->id));
    }

    public function test_restoring_an_already_restored_request_is_a_no_op(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);
        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        FailedRequestMover::restoreToActive($requestInsurance->id);
        FailedRequestMover::restoreToActive($requestInsurance->id);

        // Exactly one active copy — the second restore found no exceptions row.
        $this->assertSame(1, DB::table(FailedRequestMover::mainTable())->where('id', $requestInsurance->id)->count());
        $this->assertNull(RequestInsuranceFailed::query()->find($requestInsurance->id));
    }

    public function test_cleaner_moves_stranded_failed_rows_to_the_exceptions_table(): void
    {
        // A FAILED row still in the main table (its move was interrupted) is swept
        // into the exceptions table by the cleaner.
        $stranded = RequestInsurance::factory()->create(['state' => State::FAILED]);

        RequestInsuranceCleaner::cleanUp();

        $this->assertNull(RequestInsurance::query()->find($stranded->id));
        $this->assertNotNull(RequestInsuranceFailed::query()->find($stranded->id));
    }

    public function test_one_unmovable_stranded_row_does_not_block_the_rest_of_the_cleaner_sweep(): void
    {
        $unmovable = RequestInsurance::factory()->create(['state' => State::FAILED]);
        DB::table(FailedRequestMover::failedTable())->insert(
            (array) DB::table(FailedRequestMover::mainTable())->where('id', $unmovable->id)->first()
        );
        DB::statement('CREATE UNIQUE INDEX failed_request_id_for_test ON ' . FailedRequestMover::failedTable() . ' (id)');
        $movable = RequestInsurance::factory()->create(['state' => State::FAILED]);

        Log::shouldReceive('error')->once();

        RequestInsuranceCleaner::cleanUp();

        $this->assertNotNull(RequestInsurance::query()->find($unmovable->id));
        $this->assertNull(RequestInsurance::query()->find($movable->id));
        $this->assertNotNull(RequestInsuranceFailed::query()->find($movable->id));
    }
}
