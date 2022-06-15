<?php

namespace Cego\RequestInsurance\Controllers;

use Carbon\Carbon;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Models\RequestInsuranceEditApproval;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class RequestInsuranceEditApprovalController extends Controller
{
    /**
     * @param Request $request
     * @param RequestInsuranceEdit $requestInsuranceEdit
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request, RequestInsurance $requestInsuranceEdit)
    {
        $user = resolve(Config::get('request-insurance.identityProvider'))->getUser($request);
        // Only allow approvals from users that did not create the edit
        if ($requestInsuranceEdit->admin_user == $user){
            return redirect()->back();//TODO more error handling?
        }

        RequestInsuranceEditApproval::create([
            'request_insurance_edit_id' => $requestInsuranceEdit->id,
            'approver_admin_user' => $user,
        ]);

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param RequestInsuranceEditApproval $requestInsuranceEditApproval
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, RequestInsuranceEditApproval $requestInsuranceEditApproval)
    {
        $user = resolve(Config::get('request-insurance.identityProvider'))->getUser($request);
        // Only allow if not already applied and request is from the approver
        if ($requestInsuranceEditApproval->edit->applied_at != null || $user != $requestInsuranceEditApproval->approver_admin_user){
            return redirect()->back();//TODO more error handling
        }
        $requestInsuranceEditApproval->delete();

        return redirect()->back();
    }
}
