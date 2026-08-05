<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Cego\RequestInsurance\Enums\State;
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

    public function test_page_size_selector_submits_the_filter_form(): void
    {
        RequestInsurance::factory(30)->create(['state' => State::READY]);

        $html = $this->get(route('request-insurances.index', ['per_page' => 50]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<form method="get" id="ri-filters"', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*name="per_page"[^>]*form="ri-filters"/', $html);
        // The selector must be the only per_page control, or the form serializes a
        // stale page size alongside the chosen one.
        $this->assertSame(1, substr_count($html, 'name="per_page"'));
        $this->assertStringContainsString('<option value="50" selected>', $html);
    }

    public function test_page_size_applies_when_submitted_with_blank_filter_fields(): void
    {
        RequestInsurance::factory(30)->create(['state' => State::READY]);

        // The shape the filter form submits: every unused field present but empty.
        $paginator = $this->get(route('request-insurances.index', [
            'trace_id' => '', 'url' => '', 'from' => '', 'to' => '', 'per_page' => 50,
        ]))->assertOk()->viewData('requestInsurances');

        $this->assertSame(50, $paginator->perPage());
        $this->assertCount(30, $paginator->items());
    }

    public function test_show_page_renders(): void
    {
        $this->authenticate();
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $this->get(route('request-insurances.show', $requestInsurance))
            ->assertOk()
            ->assertSee('#' . $requestInsurance->id, false);
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

    public function test_show_page_renders_with_a_pending_edit(): void
    {
        $this->authenticate();
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::FAILED]);

        $this->post(route('request-insurance-edits.create', $requestInsurance))->assertRedirect();

        $this->get(route('request-insurances.show', $requestInsurance))
            ->assertOk()
            ->assertSee('Pending edits');
    }

    public function test_edit_history_page_renders(): void
    {
        $this->authenticate();
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY]);

        $this->get(route('request-insurances.edit-history', $requestInsurance))
            ->assertOk()
            ->assertSee('Edit history');
    }

    public function test_retry_selected_readies_failed_requests(): void
    {
        $failed = RequestInsurance::factory()->create(['state' => State::FAILED]);
        $completed = RequestInsurance::factory()->create(['state' => State::COMPLETED]);

        $this->post(route('request-insurances.retry-selected'), ['ids' => [$failed->id, $completed->id]])
            ->assertRedirect();

        $this->assertSame(State::READY, $failed->fresh()->state);
        $this->assertSame(State::COMPLETED, $completed->fresh()->state);
    }

    public function test_abandon_selected_abandons_active_requests(): void
    {
        $active = RequestInsurance::factory()->create(['state' => State::READY]);
        $completed = RequestInsurance::factory()->create(['state' => State::COMPLETED]);

        $this->post(route('request-insurances.abandon-selected'), ['ids' => [$active->id, $completed->id]])
            ->assertRedirect();

        $this->assertSame(State::ABANDONED, $active->fresh()->state);
        $this->assertSame(State::COMPLETED, $completed->fresh()->state);
    }
}
