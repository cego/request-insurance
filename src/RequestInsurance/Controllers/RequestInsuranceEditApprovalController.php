<?php

namespace Cego\RequestInsurance\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Cego\RequestInsurance\Providers\IdentityProvider;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;
use Cego\RequestInsurance\Models\RequestInsuranceEditApproval;

class RequestInsuranceEditApprovalController extends Controller
{
    /**
     * @param Request $request
     * @param RequestInsuranceEdit $requestInsuranceEdit
     * @param IdentityProvider $identityProvider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request, RequestInsuranceEdit $requestInsuranceEdit, IdentityProvider $identityProvider)
    {
        $user = $identityProvider->getUser($request);
        // Only allow approvals from users that did not create the edit
        if ($requestInsuranceEdit->admin_user == $user) {
            return redirect()->back();
        }

        RequestInsuranceEditApproval::create([
            'request_insurance_edit_id' => $requestInsuranceEdit->id,
            'approver_admin_user'       => $user,
        ]);

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param RequestInsuranceEditApproval $requestInsuranceEditApproval
     * @param IdentityProvider $identityProvider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, RequestInsuranceEditApproval $requestInsuranceEditApproval, IdentityProvider $identityProvider)
    {
        $user = $identityProvider->getUser($request);
        // Only allow if not already applied and request is from the approver
        if ($requestInsuranceEditApproval->requestInsuranceEdit->applied_at != null || $user != $requestInsuranceEditApproval->approver_admin_user) {
            return redirect()->back();
        }
        $requestInsuranceEditApproval->delete();

        return redirect()->back();
    }
}
