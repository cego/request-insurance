<?php

namespace Nbj\RequestInsurance\Controllers;

use Exception;
use App\RequestInsurance;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RequestInsuranceController extends Controller
{
    /**
     * Frontend view for displaying and index of RequestLogs
     *
     * @param Request $request
     *
     * @return View|Factory
     *
     * @throws Exception
     */
    public function index(Request $request)
    {
        // Flash the request parameters, so we can redisplay the same filter parameters.
        $request->flash();

        $requestInsurances = RequestInsurance::latest()
            ->filteredByRequest($request)
            ->paginate(25);

        return view('request-insurance::index')->with(['requestInsurances' => $requestInsurances]);
    }

    /**
     * Shows a specific request insurance
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return View|Factory
     */
    public function show(RequestInsurance $requestInsurance)
    {
        $requestInsurance->load('logs');

        return view('request-insurance::show')->with(['requestInsurance' => $requestInsurance]);
    }

    /**
     * Abandons a request insurance
     *
     * @param RequestInsurance $requestInsurance
     * @param Request $request
     *
     * @return mixed
     */
    public function destroy(RequestInsurance $requestInsurance, Request $request)
    {
        $requestInsurance->abandon();

        return redirect()->back();
    }
}
