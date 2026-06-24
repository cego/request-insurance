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
use Cego\RequestInsurance\FailedRequestMover;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Providers\IdentityProvider;
use Cego\RequestInsurance\Models\RequestInsuranceFailed;
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

        $perPage = (int) $request->input('per_page', 25);

        if ( ! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        // The listing spans both the partitioned main table and the exceptions
        // ("failed jobs") table, so FAILED/ABANDONED requests remain visible. Cursor
        // pagination avoids an exact COUNT over the (large, partitioned) main table.
        // The exceptions table is only unioned in once it exists (i.e. after the
        // migration has run) so the page does not break during a rolling deploy.
        $query = RequestInsurance::query()->filteredByRequest($request);

        if (FailedRequestMover::isAvailable(DB::connection())) {
            $query->unionAll(RequestInsuranceFailed::query()->filteredByRequest($request));
        }

        $paginator = $query->orderByDesc('id')->cursorPaginate($perPage)->withQueryString();

        return view('request-insurance::index')->with([
            'requestInsurances' => $paginator,
            'perPage'           => $perPage,
        ]);
    }

    /**
     * Retries a batch of selected (FAILED/ABANDONED) request insurances at once,
     * restoring each from the exceptions tables into the active pipeline.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function retrySelected(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if ( ! empty($ids)) {
            RequestInsuranceFailed::query()
                ->whereIn('id', $ids)
                ->get()
                ->each(fn (RequestInsuranceFailed $requestInsurance) => $requestInsurance->retryNow());
        }

        return redirect()->back();
    }

    /**
     * Abandons a batch of selected request insurances at once.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function abandonSelected(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        foreach ($ids as $id) {
            $requestInsurance = RequestInsurance::query()->find($id) ?? RequestInsuranceFailed::query()->find($id);

            if ($requestInsurance !== null && $requestInsurance->doesNotHaveState(State::COMPLETED) && $requestInsurance->doesNotHaveState(State::ABANDONED)) {
                $requestInsurance->abandon();
            }
        }

        return redirect()->back();
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
            'activeCount' => (int) RequestInsurance::query()->whereIn('state', [ State::READY, State::PROCESSING, State::PENDING ])->count(),
            'failCount'   => (int) RequestInsuranceFailed::query()->where('state', State::FAILED)->count(),
        ]);
    }

    /**
     * Gets a collection of segmented number of requests
     *
     * @return JsonResponse
     */
    public function monitor_segmented(): JsonResponse
    {
        // COMPLETED is deliberately not counted: it is the bulk of the partitioned
        // main table and an exact COUNT would scan everything. The remaining states
        // are cheap — the transient states are indexed, and FAILED/ABANDONED live in
        // the small exceptions table.
        $transient = [State::WAITING, State::READY, State::PENDING, State::PROCESSING];

        $mainCounts = DB::query()
            ->from(resolve(RequestInsurance::class)->getTable())
            ->select('state', DB::raw('COUNT(*) as count'))
            ->whereIn('state', $transient)
            ->groupBy('state')
            ->pluck('count', 'state');

        $failedCounts = DB::query()
            ->from(FailedRequestMover::failedTable())
            ->select('state', DB::raw('COUNT(*) as count'))
            ->groupBy('state')
            ->pluck('count', 'state');

        $counts = collect(State::getAll())
            ->reject(fn (string $state) => $state === State::COMPLETED)
            ->mapWithKeys(fn (string $state) => [$state => 0])
            ->merge($mainCounts)
            ->merge($failedCounts)
            ->map(fn ($value) => (int) $value)
            ->toArray();

        return response()->json($counts);
    }
}
