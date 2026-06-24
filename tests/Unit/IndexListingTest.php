<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;

class IndexListingTest extends TestCase
{
    public function test_listing_unions_main_and_exceptions_tables(): void
    {
        $active = RequestInsurance::factory()->create(['state' => State::READY]);

        $toFail = RequestInsurance::factory()->create(['state' => State::READY]);
        $toFail->setState(State::FAILED);
        $toFail->save();
        FailedRequestMover::moveToFailed($toFail);

        $request = new Request();

        $paginator = RequestInsurance::query()
            ->filteredByRequest($request)
            ->unionAll(RequestInsuranceFailed::query()->filteredByRequest($request))
            ->orderByDesc('id')
            ->paginate(25);

        $ids = collect($paginator->items())->pluck('id')->all();

        $this->assertContains($active->id, $ids, 'active (main) request should be listed');
        $this->assertContains($toFail->id, $ids, 'failed (exceptions) request should be listed');
        $this->assertSame(2, $paginator->total());
    }
}
