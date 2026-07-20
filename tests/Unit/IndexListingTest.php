<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;

class IndexListingTest extends TestCase
{
    public function test_listing_merges_bounded_queries_without_a_database_union(): void
    {
        $active = RequestInsurance::factory()->create(['state' => State::READY]);

        $toFail = RequestInsurance::factory()->create(['state' => State::READY]);
        $toFail->setState(State::FAILED);
        $toFail->save();
        FailedRequestMover::moveToFailed($toFail);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get(route('request-insurances.index'))->assertOk();
        $paginator = $response->viewData('requestInsurances');

        $ids = collect($paginator->items())->pluck('id')->all();

        $this->assertContains($active->id, $ids, 'active (main) request should be listed');
        $this->assertContains($toFail->id, $ids, 'failed (exceptions) request should be listed');
        $this->assertFalse(collect(DB::getQueryLog())->contains(
            fn (array $query) => str_contains(strtolower($query['query']), ' union ')
        ));
    }

    public function test_listing_cursor_pages_remain_globally_ordered_across_both_tables(): void
    {
        $requests = RequestInsurance::factory(40)->create(['state' => State::READY]);

        $requests->filter(fn (RequestInsurance $requestInsurance) => $requestInsurance->id % 2 === 0)
            ->each(function (RequestInsurance $requestInsurance) {
                $requestInsurance->setState(State::FAILED);
                $requestInsurance->save();
                FailedRequestMover::moveToFailed($requestInsurance);
            });

        $first = $this->get(route('request-insurances.index', ['per_page' => 25]))->assertOk()
            ->viewData('requestInsurances');
        $second = $this->get($first->nextPageUrl())->assertOk()->viewData('requestInsurances');

        $firstIds = collect($first->items())->pluck('id');
        $secondIds = collect($second->items())->pluck('id');

        $this->assertSame($firstIds->sortDesc()->values()->all(), $firstIds->values()->all());
        $this->assertSame($secondIds->sortDesc()->values()->all(), $secondIds->values()->all());
        $this->assertEmpty($firstIds->intersect($secondIds));
        $this->assertSame(40, $firstIds->concat($secondIds)->unique()->count());
    }
}
