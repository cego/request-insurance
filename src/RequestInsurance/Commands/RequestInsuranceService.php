<?php

namespace Cego\RequestInsurance\Commands;

use Exception;
use Carbon\Carbon;
use Nbj\Stopwatch;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Exceptions\FailedToLockRequestInsurances;

class RequestInsuranceService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:request-insurances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes request insurances that are ready to be sent';

    /**
     * Once set, holds the number of microseconds to wait between each cycle
     *
     * @var int $microSecondsToWait
     */
    protected $microSecondsToWait;

    /**
     * Holds all recorded time series data
     *
     * @var array[] $timeSeries
     */
    protected $timeSeries = [
        'five'    => [],
        'ten'     => [],
        'fifteen' => [],
    ];

    /**
     * Holds a hash created when the instance started running
     *
     * @var string $runningHash
     */
    protected $runningHash;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->runningHash = Str::random(8);

        // Clean up load statistics
        $this->cleanUpLoadStatistics();

        // Run the service
        return $this->runService();
    }

    /**
     * Runs the service
     *
     * @param bool $onlyOnce
     *
     * @return int
     */
    public function runService($onlyOnce = false)
    {
        // Bail-out early if request insurance is not enabled
        if (config('request-insurance.enabled') == false) {
            Log::info('RequestInsuranceService is not enabled. Enable it before starting again.');

            return 0;
        }

        $this->microSecondsToWait = config('request-insurance.microSecondsToWait', 100000);

        Log::info(sprintf('[%s] RequestInsuranceService has started. Running interval is [%d] microseconds', $this->runningHash, $this->microSecondsToWait));

        do {
            $executionTime = Stopwatch::time(function () {
                $this->processRequestInsurances();
            });

            $this->recordLoadDataPoint($executionTime->microseconds());
            $waitTime = (int) max($this->microSecondsToWait - $executionTime->microseconds(), 0);

            usleep($waitTime);
        } while ( ! $onlyOnce);

        Log::info('[%s] RequestInsuranceService has stopped.', $this->runningHash);

        return 0;
    }

    /**
     * Processed all ready RequestInsurances
     */
    protected function processRequestInsurances()
    {
        // Fetch all RequestInsurances ready to be processed and lock them
        // This prevents other processing instances of selecting the same
        // RequestInsurance rows for processing.
        $requestIds = DB::transaction(function () {
            $requestIds = $this->getIdsOfReadyRequests();
            Log::info(sprintf('[%s] Found [%s] requests ready for processing!', $this->runningHash, $requestIds->count()));

            if ($requestIds->isEmpty()) {
                return $requestIds;
            }

            // Lock the request with the ids
            Log::debug(sprintf('[%s] Locking [%d] requests with ids [%s]!', $this->runningHash, $requestIds->count(), $requestIds->implode(',')));

            $lockingSucceeded = RequestInsurance::query()
                ->whereIn('id', $requestIds)
                ->update(['locked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

            if ( ! $lockingSucceeded) {
                throw new FailedToLockRequestInsurances();
            }

            return $requestIds;
        });

        // Before we process the newly locked requests, we fetch all info for the specific requests
        $requests = RequestInsurance::whereIn('id', $requestIds)->orderBy('priority', 'asc')->get();

        // Process each RequestInsurance making sure to unlock it
        // whether it fails or completes successfully
        Log::info(sprintf('[%s] Processing started...', $this->runningHash));

        $requests->each(function (RequestInsurance $request) {
            try {
                $request->process();
            } catch (Exception $exception) {
                Log::error(sprintf('Failed to process RequestInsurance with id [%d] - ErrorMessage: %s', $request->id, $exception->getTraceAsString()));

                $request->pause();
            } finally {
                $request->unlock();
            }
        });

        Log::info(sprintf('[%s] Processing of %s requests has completed!', $this->runningHash, $requests->count()));
    }

    /**
     * Gets a collection of RequestInsurances ready to be processed
     *
     * @return mixed
     */
    public function getIdsOfReadyRequests()
    {
        $batchSize = Config::get('request-log.batchSize', 100);

        return RequestInsurance::query()
            ->select('id')
            ->readyToBeProcessed()
            ->take($batchSize)
            ->lockForUpdate()
            ->get()
            ->pluck('id');
    }

    /**
     * Records and calculates the current load
     *
     * @param int $executionTime
     */
    protected function recordLoadDataPoint($executionTime)
    {
        // This is really a poor implementation of keeping records segregated in five, ten and fifteen minutes
        // As we base the number of records we want to keep on the assumption of how many cycles will
        // approximately run within these intervals. This is done to keep the clean up logic within
        // simple array instructions and have the calculations have no need for timestamps
        // as a result this should perform better
        $numberOfEntriesForFiveMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 5;
        $numberOfEntriesForTenMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 10;
        $numberOfEntriesForFifteenMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 15;

        // Clean up five minute series
        while (count($this->timeSeries['five']) >= $numberOfEntriesForFiveMinutes) {
            array_shift($this->timeSeries['five']);
        }

        // Clean up ten minute series
        while (count($this->timeSeries['ten']) >= $numberOfEntriesForTenMinutes) {
            array_shift($this->timeSeries['ten']);
        }

        // Clean up fifteen minute series
        while (count($this->timeSeries['fifteen']) >= $numberOfEntriesForFifteenMinutes) {
            array_shift($this->timeSeries['fifteen']);
        }

        // Record the execution time
        $this->timeSeries['five'][] = $executionTime;
        $this->timeSeries['ten'][] = $executionTime;
        $this->timeSeries['fifteen'][] = $executionTime;

        // Calculate mean load
        $fiveMinuteLoad = (array_sum($this->timeSeries['five']) / count($this->timeSeries['five'])) / 1000000;
        $tenMinuteLoad = (array_sum($this->timeSeries['ten']) / count($this->timeSeries['ten'])) / 1000000;
        $fifteenMinuteLoad = (array_sum($this->timeSeries['fifteen']) / count($this->timeSeries['fifteen'])) / 1000000;

        $json = json_encode([
            'loadFiveMinutes'    => $fiveMinuteLoad,
            'loadTenMinutes'     => $tenMinuteLoad,
            'loadFifteenMinutes' => $fifteenMinuteLoad,
        ]);

        $filePath = sprintf('load-statistics/load-%s.json', $this->runningHash);

        Storage::disk('local')->put($filePath, $json);
    }

    /**
     * Cleans up the load statistics files
     *
     * @return void
     */
    protected function cleanUpLoadStatistics()
    {
        if ( ! Storage::disk('local')->exists('load-statistics')) {
            Log::debug('Load statistics directory did not exist, it has been created');
            Storage::disk('local')->makeDirectory('load-statistics');

            return;
        }

        // Delete old files as they will not be relevant
        $files = Storage::disk('local')->files('load-statistics');
        Storage::disk('local')->delete($files);
    }
}
