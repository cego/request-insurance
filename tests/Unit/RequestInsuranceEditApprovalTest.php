<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;

class RequestInsuranceEditApprovalTest extends TestCase
{
    private function actingAsUsername(string $username): self
    {
        return $this->actingAs(new GenericUser(['id' => $username, 'username' => $username]));
    }

    private function createEdit(string $author): RequestInsuranceEdit
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::FAILED]);

        $this->actingAsUsername($author)->post(route('request-insurance-edits.create', $requestInsurance));

        return RequestInsuranceEdit::firstOrFail();
    }

    public function test_edit_author_is_the_authenticated_users_username(): void
    {
        $edit = $this->createEdit('alice');

        $this->assertSame('alice', $edit->admin_user);
    }

    public function test_remote_user_header_is_used_without_an_authenticated_user(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::FAILED]);

        $this->withHeader('remote-user', 'alice')->post(route('request-insurance-edits.create', $requestInsurance));

        $this->assertSame('alice', RequestInsuranceEdit::firstOrFail()->admin_user);
    }

    public function test_another_user_can_approve_and_the_author_can_apply(): void
    {
        $edit = $this->createEdit('alice');

        $html = $this->actingAsUsername('bob')->get(route('request-insurances.show', $edit->request_insurance_id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"\s*>Approve<\/button>/', $html);
        $this->assertStringNotContainsString('>Apply</button>', $html);

        $this->actingAsUsername('bob')->post(route('request-insurance-edit-approvals.create', $edit));
        $this->assertSame(1, $edit->approvals()->count());

        $this->actingAsUsername('alice')->post(route('request-insurances-edits.apply', $edit));
        $this->assertNotNull($edit->fresh()->applied_at);
    }

    public function test_the_author_cannot_approve_their_own_edit(): void
    {
        $edit = $this->createEdit('alice');

        $this->actingAsUsername('alice')->post(route('request-insurance-edit-approvals.create', $edit));

        $this->assertSame(0, $edit->approvals()->count());
    }

    public function test_an_unidentified_user_cannot_create_an_edit(): void
    {
        $requestInsurance = RequestInsurance::factory()->create(['state' => State::FAILED]);

        $this->post(route('request-insurance-edits.create', $requestInsurance));

        $this->assertSame(0, RequestInsuranceEdit::count());
    }

    public function test_an_unidentified_user_cannot_approve_an_edit(): void
    {
        $edit = $this->createEdit('alice');
        $this->app['auth']->forgetGuards();

        $this->post(route('request-insurance-edit-approvals.create', $edit));

        $this->assertSame(0, $edit->approvals()->count());
    }

    public function test_an_unidentified_user_is_not_treated_as_the_author_of_an_anonymous_edit(): void
    {
        $edit = RequestInsuranceEdit::create([
            'request_insurance_id' => RequestInsurance::factory()->create(['state' => State::FAILED])->id,
            'old_priority'         => 9999,
            'new_priority'         => 9999,
            'old_url'              => 'https://example.test',
            'new_url'              => 'https://example.test',
            'old_method'           => 'POST',
            'new_method'           => 'POST',
            'old_headers'          => '[]',
            'new_headers'          => '[]',
            'old_payload'          => '',
            'new_payload'          => '',
            'admin_user'           => '',
        ]);
        $edit->approvals()->create(['approver_admin_user' => 'bob']);

        $html = $this->get(route('request-insurances.show', $edit->request_insurance_id))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Apply</button>', $html);
        $this->assertStringNotContainsString('>Approve</button>', $html);

        $this->post(route('request-insurances-edits.apply', $edit));
        $this->assertNull($edit->fresh()->applied_at);
    }
}
