<?php

namespace Cego\RequestInsurance\Controllers;

use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Providers\IdentityProvider;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;

class RequestInsuranceEditController extends Controller
{
    /**
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @param IdentityProvider $identityProvider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request, RequestInsurance $requestInsurance, IdentityProvider $identityProvider)
    {
        // Only allow updates for requests that have not completed or been abandoned
        if ($requestInsurance->inOneOfStates(State::COMPLETED, State::ABANDONED)) {
            // This should not be possible from the view, so we don't send any error messages
            return redirect()->back();
        }

        RequestInsuranceEdit::create([
            'request_insurance_id' => $requestInsurance->id,
            'old_priority'         => $requestInsurance->priority,
            'new_priority'         => $requestInsurance->priority,
            'old_url'              => $requestInsurance->url,
            'new_url'              => $requestInsurance->url,
            'old_method'           => $requestInsurance->method,
            'new_method'           => $requestInsurance->method,
            'old_headers'          => $requestInsurance->getOriginal('headers'),
            'new_headers'          => $requestInsurance->getOriginal('headers'),
            'old_payload'          => $requestInsurance->getOriginal('payload'),
            'new_payload'          => $requestInsurance->getOriginal('payload'),
            'old_encrypted_fields' => $requestInsurance->encrypted_fields,
            'new_encrypted_fields' => $requestInsurance->encrypted_fields,
            'admin_user'           => $identityProvider->getUser($request),
        ]);

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param RequestInsuranceEdit $requestInsuranceEdit
     * @param IdentityProvider $identityProvider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, RequestInsuranceEdit $requestInsuranceEdit, IdentityProvider $identityProvider)
    {
        // Only allow delete if not already applied and request is from the edit author
        if ($requestInsuranceEdit->applied_at != null || $identityProvider->getUser($request) != $requestInsuranceEdit->admin_user) {
            // Both these cases should not be possible from the view, so we don't send any error message
            return redirect()->back();
        }
        $requestInsuranceEdit->delete();

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param RequestInsuranceEdit $requestInsuranceEdit
     * @param IdentityProvider $identityProvider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, RequestInsuranceEdit $requestInsuranceEdit, IdentityProvider $identityProvider)
    {
        // Only allow updates if it has not been applied and the updating user is the edit author
        if ($requestInsuranceEdit->applied_at != null || $identityProvider->getUser($request) != $requestInsuranceEdit->admin_user) {
            // Both these cases should not be possible from the view, so we don't send any error message
            return redirect()->back();
        }

        DB::transaction(function () use ($request, $requestInsuranceEdit) {
            // Remove all approvals
            $requestInsuranceEdit->approvals()->delete();

            // Update the edit
            $requestInsuranceEdit->update([
                'new_priority'         => $request->post('new_priority', $requestInsuranceEdit->new_priority),
                'new_url'              => $request->post('new_url', $requestInsuranceEdit->new_url),
                'new_method'           => $request->post('new_method', $requestInsuranceEdit->new_method),
                'new_headers'          => $request->post('new_headers', ''),
                'new_payload'          => $request->post('new_payload', ''),
                'new_encrypted_fields' => $request->post('new_encrypted_fields', ''),
            ]);
        });

        return redirect()->back();
    }

    /**
     * @param RequestInsuranceEdit $requestInsuranceEdit
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function apply(RequestInsuranceEdit $requestInsuranceEdit)
    {
        // If already applied, do nothing
        if ($requestInsuranceEdit->applied_at != null) {
            return redirect()->back();
        }

        $errors = [];

        if ($requestInsuranceEdit->approvals()->count() < $requestInsuranceEdit->required_number_of_approvals) {
            $errors['requestInsuranceEdit'] = $requestInsuranceEdit;
            $errors['requestErrors'] = ['approval' => 'Not enough approvals to apply'];
        }

        if ( ! $this->is_valid_header_format($requestInsuranceEdit->new_headers)) {
            $errors['requestInsuranceEdit'] = $requestInsuranceEdit;
            $errors['requestErrors'] = ['header' => 'Invalid header format'];
        }

        // If any errors redirect back with all errors
        if ( ! empty($errors)) {
            return redirect()->back()->with($errors);
        }

        DB::transaction(function () use ($requestInsuranceEdit) {
            $requestInsuranceEdit->update(['applied_at' => Carbon::now()]);

            // Update the request insurance
            $requestInsuranceEdit->parent()->update([
                'priority'         => $requestInsuranceEdit->new_priority,
                'url'              => $requestInsuranceEdit->new_url,
                'method'           => $requestInsuranceEdit->new_method,
                'headers'          => $requestInsuranceEdit->new_headers,
                'payload'          => $requestInsuranceEdit->new_payload,
                'encrypted_fields' => $requestInsuranceEdit->new_encrypted_fields,
            ]);
        });

        return redirect()->back();
    }

    /**
     * Returns whether the data is empty, an array or json
     *
     * @param $data
     *
     * @return bool
     */
    private function is_valid_header_format($data)
    {
        if (empty($data) || is_array($data)) {
            return true;
        }

        try {
            json_decode($data);

            return true;
        } finally {
            return false;
        }
    }
}
