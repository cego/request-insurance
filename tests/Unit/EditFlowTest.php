<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;

class EditFlowTest extends TestCase
{
    private function actingAsUser(string $name): void
    {
        $this->actingAs(new GenericUser(['id' => crc32($name), 'name' => $name]));
    }

    private function failedRequest(): RequestInsurance
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::READY, 'url' => 'https://example.test/original']);
        $requestInsurance->setState(State::FAILED);
        $requestInsurance->save();
        FailedRequestMover::moveToFailed($requestInsurance);

        return $requestInsurance;
    }

    public function test_edits_can_only_be_created_for_failed_requests(): void
    {
        $this->actingAsUser('alice');

        $active = RequestInsurance::factory()->create(['state' => State::READY]);
        $this->post(route('request-insurance-edits.create', $active))->assertRedirect();
        $this->assertSame(0, RequestInsuranceEdit::query()->where('request_insurance_id', $active->id)->count(), 'active requests are not editable');

        $failed = $this->failedRequest();
        $this->post(route('request-insurance-edits.create', $failed))->assertRedirect();
        $this->assertSame(1, RequestInsuranceEdit::query()->where('request_insurance_id', $failed->id)->count(), 'failed requests are editable');
    }

    public function test_show_renders_for_a_failed_request_with_a_pending_edit(): void
    {
        $this->actingAsUser('alice');
        $failed = $this->failedRequest();
        $this->post(route('request-insurance-edits.create', $failed))->assertRedirect();

        // Renders the pending-edit cards, diff and approvals for an exceptions-table row.
        $this->get(route('request-insurances.show', $failed))->assertOk();
    }

    public function test_applying_an_edit_updates_the_request_in_the_exceptions_table(): void
    {
        $this->actingAsUser('alice');
        $failed = $this->failedRequest();

        // Create the baseline edit (as alice), then change the url on it.
        $this->post(route('request-insurance-edits.create', $failed))->assertRedirect();
        $edit = RequestInsuranceEdit::query()->where('request_insurance_id', $failed->id)->firstOrFail();
        $edit->update(['new_url' => 'https://example.test/edited']);

        // A second user approves (required_number_of_approvals defaults to 1).
        $edit->approvals()->create(['approver_admin_user' => 'bob']);

        // The author applies.
        $this->post(route('request-insurances-edits.apply', $edit))->assertRedirect();

        $this->assertSame('https://example.test/edited', RequestInsuranceFailed::query()->find($failed->id)->url, 'apply must write to the exceptions table');
        $this->assertNotNull($edit->fresh()->applied_at);
    }
}
