<?php

namespace Cego\RequestInsurance\Controllers;

use Exception;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Storage;
use Cego\RequestInsurance\Models\RequestInsurance;
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
     * @return array
     */
    public function monitor()
    {
        $activeCount = RequestInsurance::where('completed_at', null)
            ->where('abandoned_at', null)
            ->where('paused_at', null)
            ->count();

        $failCount = RequestInsurance::where('abandoned_at', null)
            ->where('paused_at', '!=', null)
            ->count();

        return [
            'activeCount' => $activeCount,
            'failCount'   => $failCount,
        ];
    }

    /**
     * Gets a collection of segmented number of requests
     *
     * @return \Illuminate\Support\Collection
     */
    public function monitor_segmented()
    {
        $stateCounts = RequestInsurance::query()
            ->selectRaw('state as state, COUNT(*) as count')
            ->groupBy('state')
            ->get()
            ->mapWithKeys(fn (int $count, string $state) => [$state, $count]);

        // Add default value of 0
        return collect(State::getAll())->map(fn () => 0)->merge($stateCounts);
    }
}
