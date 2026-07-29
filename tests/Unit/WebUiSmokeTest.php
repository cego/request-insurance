<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;

class WebUiSmokeTest extends TestCase
{
    private function authenticate(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'test-admin']));
    }

    public function test_index_page_renders(): void
    {
        RequestInsurance::factory(3)->create(['state' => State::READY]);

        $this->get(route('request-insurances.index'))
            ->assertOk()
            ->assertSee('Request pipeline');
    }

    public function test_index_page_renders_with_filters_and_page_size(): void
    {
        RequestInsurance::factory(3)->create(['state' => State::READY]);

        $this->get(route('request-insurances.index', ['per_page' => 50, 'url' => '%', State::READY => 'on']))
            ->assertOk();
    }

    public function test_index_page_falls_back_to_default_page_size_for_invalid_per_page(): void
    {
        RequestInsurance::factory(1)->create(['state' => State::READY]);

        $this->get(route('request-insurances.index', ['per_page' => 123]))
            ->assertOk()
            ->assertViewHas('perPage', 25);
    }

    public function test_index_filters_on_from_and_to_as_full_datetimes(): void
    {
        $old = RequestInsurance::factory()->create(['state' => State::READY, 'created_at' => '2026-07-18 15:00:00']);
        // Time-of-day (08:00) earlier than the filter's (14:30) — must still
        // match a from on an earlier date.
        $recent = RequestInsurance::factory()->create(['state' => State::READY, 'created_at' => '2026-07-20 08:00:00']);

        $ids = collect($this->get(route('request-insurances.index', ['from' => '2026-07-19T14:30']))
            ->assertOk()->viewData('requestInsurances')->items())->pluck('id');

        $this->assertTrue($ids->contains($recent->id));
        $this->assertFalse($ids->contains($old->id));

        $ids = collect($this->get(route('request-insurances.index', ['to' => '2026-07-19T14:30']))
            ->assertOk()->viewData('requestInsurances')->items())->pluck('id');

        $this->assertTrue($ids->contains($old->id));
        $this->assertFalse($ids->contains($recent->id));
    }

    public function test_show_page_renders(): void
    {
        $this->authenticate();
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $this->get(route('request-insurances.show', $requestInsurance))
            ->assertOk()
            ->assertSee('#' . $requestInsurance->id, false);
    }

    public function test_show_page_renders_for_a_failed_request_in_the_exceptions_table(): void
    {
        $this->authenticate();
        // Inspecting a row in the exceptions table must resolve the logs/edits
        // relationships by request_insurance_id, not request_insurance_failed_id.
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);
        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        $this->get(route('request-insurances.show', $requestInsurance))->assertOk();
    }

    public function test_edit_history_page_renders(): void
    {
        $this->authenticate();
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $this->get(route('request-insurances.edit-history', $requestInsurance))
            ->assertOk()
            ->assertSee('Edit history');
    }

    public function test_index_page_survives_missing_exceptions_table(): void
    {
        // Simulate a rolling deploy where the new code runs before the migration:
        // the exceptions table does not exist yet. The index page must still render
        // (it only unions in the exceptions table once it exists).
        RequestInsurance::factory(2)->create(['state' => State::READY]);

        \Illuminate\Support\Facades\Schema::dropIfExists(FailedRequestMover::failedLogsTable());
        \Illuminate\Support\Facades\Schema::dropIfExists(FailedRequestMover::failedTable());

        $available = (new \ReflectionClass(FailedRequestMover::class))->getProperty('available');
        $available->setAccessible(true);
        $available->setValue(null, []);

        $this->get(route('request-insurances.index'))->assertOk();
    }

    public function test_monitor_segmented_does_not_include_completed(): void
    {
        $response = $this->getJson(route('request-insurances.monitor_segmented'))->assertOk();

        $this->assertArrayNotHasKey(State::COMPLETED, $response->json());
        $this->assertArrayHasKey(State::FAILED, $response->json());
    }

    public function test_retry_selected_restores_failed_requests_to_the_active_table(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);
        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        $this->post(route('request-insurances.retry-selected'), ['ids' => [$requestInsurance->id]])
            ->assertRedirect();

        $restored = RequestInsurance::query()->find($requestInsurance->id);
        $this->assertNotNull($restored);
        $this->assertSame(State::READY, $restored->state);
    }

    public function test_abandon_selected_abandons_active_requests(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $this->post(route('request-insurances.abandon-selected'), ['ids' => [$requestInsurance->id]])
            ->assertRedirect();

        // Abandoned active rows move to the exceptions table.
        $this->assertNull(RequestInsurance::query()->find($requestInsurance->id));
    }
}
