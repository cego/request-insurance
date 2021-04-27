<?php

namespace Cego\RequestInsurance\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
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

        $segmentedNumberOfRequests = $this->getSegmentedNumberOfRequests();

        return view('request-insurance::index')->with([
            'requestInsurances'         => $paginator,
            'numberOfActiveRequests'    => $segmentedNumberOfRequests->get('active'),
            'numberOfCompletedRequests' => $segmentedNumberOfRequests->get('completed'),
            'numberOfPausedRequests'    => $segmentedNumberOfRequests->get('paused'),
            'numberOfAbandonedRequests' => $segmentedNumberOfRequests->get('abandoned'),
            'numberOfLockedRequests'    => $segmentedNumberOfRequests->get('locked'),
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

        if (config('request-insurance.condenseLoad')) {
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
    protected function getSegmentedNumberOfRequests()
    {
        $table = RequestInsurance::make()->getTable();

        $query = <<<SQL
select 
	a.active, 
	b.completed,
	c.paused,
	d.abandoned,
	e.locked
from 
	(
		select count(*) as active 
		from $table 
		where response_code is null
	) as a,
	(
		select count(*) as completed 
		from $table 
		where completed_at is not null
	) as b,
	(
		select count(*) as paused 
		from $table 
		where paused_at is not null
	) as c,
	(
		select count(*) as abandoned 
		from $table 
		where abandoned_at is not null
	) as d,
	(
		select count(*) as locked 
		from $table 
		where locked_at is not null
	) as e
SQL;

        return collect(DB::select($query)[0]);
    }
}
