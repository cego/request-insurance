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
     *
     * @return View|Factory
     */
    public function show(Request $request, RequestInsurance $requestInsurance)
    {
        $requestInsurance->load('logs');

        return view('request-insurance::show')->with([
            'requestInsurance' => $requestInsurance,
            'user' => resolve(Config::get('request-insurance.identityProvider'))->getUser($request),
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
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @return \Illuminate\Http\RedirectResponse
     */
    public function edit(Request $request, RequestInsurance $requestInsurance)
    {
        // Only allow updates for requests that have not completed or been abandoned
        if ($requestInsurance->inOneOfStates(State::COMPLETED, State::ABANDONED)){
            return redirect()->back();//TODO more error handling?
        }

        RequestInsuranceEdit::create([
            'request_insurance_id' => $requestInsurance->id,
            'old_priority' => $requestInsurance->priority,
            'new_priority' => $requestInsurance->priority,
            'old_url' => $requestInsurance->url,
            'new_url' => $requestInsurance->url,
            'old_method' => $requestInsurance->method,
            'new_method' => $requestInsurance->method,
            'old_headers' => $requestInsurance->getOriginal('headers'),
            'new_headers' => $requestInsurance->getOriginal('headers'),
            'old_payload' => $requestInsurance->getOriginal('payload'),
            'new_payload' => $requestInsurance->getOriginal('payload'),
            'old_encrypted_fields' => $requestInsurance->encrypted_fields,
            'new_encrypted_fields' => $requestInsurance->encrypted_fields,
            'applied_at' => Carbon::now(),// TODO delete this line - this is for testing only
            'admin_user' => resolve(Config::get('request-insurance.identityProvider'))->getUser($request),
        ]);

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve_edit(Request $request, RequestInsurance $requestInsurance)
    {
        return redirect()->back();//TODO actually implement
    }

    /**
     * @param Request $request
     * @param RequestInsurance $requestInsurance
     * @return \Illuminate\Http\RedirectResponse
     */
    public function apply_edit(Request $request, RequestInsurance $requestInsurance)
    {
        return redirect()->back();//TODO actually implement
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
     * @return array
     */
    public function monitor()
    {
        return [
            'activeCount' => RequestInsurance::query()->where('state', State::READY)->count(),
            'failCount'   => RequestInsurance::query()->where('state', State::FAILED)->count(),
        ];
    }

    /**
     * Gets a collection of segmented number of requests
     *
     * @return \Illuminate\Support\Collection
     */
    public function monitor_segmented()
    {
        $stateCounts = DB::query()
            ->from(RequestInsurance::make()->getTable())
            ->selectRaw('state as state, COUNT(*) as count')
            ->groupBy('state')
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->state => $row->count]);

        // Add default value of 0
        return collect(State::getAll())->map(fn () => 0)->merge($stateCounts);
    }
}
