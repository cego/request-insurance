<?php

namespace Cego\RequestInsurance\Controllers;

use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Providers\IdentityProvider;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class RequestInsuranceController extends Controller
{
    /**
     * Frontend view for displaying and index of RequestLogs
     *
     * @param Request $request
     *
     * @throws Exception
     *
     * @return View|Factory
     */
    public function index(Request $request)
    {
        // Flash the request parameters, so we can redisplay the same filter parameters.
        $request->flash();

        $paginator = RequestInsurance::query()
            ->orderByDesc('id')
            ->filteredByRequest($request)
            ->paginate(25);

        return view('request-insurance::index')->with([
            'requestInsurances' => $paginator,
        ]);
    }

    /**
     * Shows a specific request insurance
     *
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @param IdentityProvider $identityProvider
     *
     * @return View|Factory
     */
    public function show(Request $request, RequestInsurance $requestInsurance, IdentityProvider $identityProvider)
    {
        $requestInsurance->load('logs');

        return view('request-insurance::show')->with([
            'requestInsurance' => $requestInsurance,
            'user'             => $identityProvider->getUser($request),
        ]);
    }

    /**
     * Shows edit history for a specific request insurance
     *
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @param IdentityProvider $identityProvider
     *
     * @return View|Factory
     */
    public function editHistory(Request $request, RequestInsurance $requestInsurance, IdentityProvider $identityProvider)
    {
        $requestInsurance->load('edits');

        return view('request-insurance::edit-history')->with([
            'requestInsurance' => $requestInsurance,
            'user'             => $identityProvider->getUser($request),
        ]);
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
        $requestInsurance->retryNow();

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
        $requestInsurance->unstuckPending();

        return redirect()->back();
    }

    /**
     * Gets json representation of service load
     *
     * @return array
     */
    public function load()
    {
        $files = Storage::disk('local')->files('load-statistics');

        $loadFiveMinutes = 0;
        $loadTenMinutes = 0;
        $loadFifteenMinutes = 0;

        foreach ($files as $file) {
            try {
                $loadStatistics = json_decode(Storage::disk('local')->get($file));

                $loadFiveMinutes += $loadStatistics->loadFiveMinutes;
                $loadTenMinutes += $loadStatistics->loadTenMinutes;
                $loadFifteenMinutes += $loadStatistics->loadFifteenMinutes;
            } catch (FileNotFoundException $exception) {
                // Ignore for now
            }
        }

        if (Config::get('request-insurance.condenseLoad')) {
            $numberOfFiles = count($files);

            $loadFiveMinutes = $loadFiveMinutes / $numberOfFiles;
            $loadTenMinutes = $loadTenMinutes / $numberOfFiles;
            $loadFifteenMinutes = $loadFifteenMinutes / $numberOfFiles;
        }

        return [
            'loadFiveMinutes'    => $loadFiveMinutes,
            'loadTenMinutes'     => $loadTenMinutes,
            'loadFifteenMinutes' => $loadFifteenMinutes,
        ];
    }

    /**
     * Gets json representation of failed and active requests
     *
     * @return JsonResponse
     */
    public function monitor(): JsonResponse
    {
        return response()->json([
            'activeCount' => (int) RequestInsurance::query()->where('state', State::READY)->count(),
            'failCount'   => (int) RequestInsurance::query()->where('state', State::FAILED)->count(),
        ]);
    }

    /**
     * Gets a collection of segmented number of requests
     *
     * @return JsonResponse
     */
    public function monitor_segmented(): JsonResponse
    {
        $stateCounts = DB::query()
            ->from(RequestInsurance::make()->getTable())
            ->selectRaw('state as state, COUNT(*) as count')
            ->groupBy('state')
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->state => $row->count]);

        return response()->json(
            collect(State::getAll())
                // Add default value of 0 for all states
                ->map(fn () => 0)
                // Merge actual state counts into the collection (not all states are present within the state counts)
                ->merge($stateCounts)
                // Force integer type, since the query returns strings
                ->map(fn ($value) => (int) $value)
                ->toArray()
        );
    }
}
