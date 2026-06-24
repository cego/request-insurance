<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
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
}
