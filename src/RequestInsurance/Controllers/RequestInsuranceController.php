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

        $paginator = RequestInsurance::latest()
            ->filteredByRequest($request)
            ->paginate(25);

        $requestInsurances = RequestInsurance::latest()
            ->select([
                'response_code',
                'completed_at',
                'paused_at',
                'abandoned_at',
                'locked_at',
            ])
            ->filteredByRequest($request)
            ->get();

        return view('request-insurance::index')->with([
            'requestInsurances'         => $paginator,
            'numberOfActiveRequests'    => $requestInsurances->where('response_code', null)->count(),
            'numberOfCompletedRequests' => $requestInsurances->where('completed_at', '!=', null)->count(),
            'numberOfPausedRequests'    => $requestInsurances->where('paused_at', '!=', null)->count(),
            'numberOfAbandonedRequests' => $requestInsurances->where('abandoned_at', '!=', null)->count(),
            'numberOfLockedRequests'    => $requestInsurances->where('locked_at', '!=', null)->count(),
        ]);
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
     *
     * @return mixed
     */
    public function destroy(RequestInsurance $requestInsurance)
    {
        $requestInsurance->abandon();

        return redirect()->back();
    }

    /**
     * Retries a request insurance
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return mixed
     */
    public function retry(RequestInsurance $requestInsurance)
    {
        $requestInsurance->resume();

        return redirect()->back();
    }

    /**
     * Unlocks a request insurance
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return mixed
     */
    public function unlock(RequestInsurance $requestInsurance)
    {
        $requestInsurance->unlock();

        return redirect()->back();
    }
}
