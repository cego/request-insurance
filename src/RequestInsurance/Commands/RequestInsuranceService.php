<?php

namespace Cego\RequestInsurance\Commands;

use Exception;
use Carbon\Carbon;
use Nbj\Stopwatch;
use App\RequestInsurance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
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

        $microSecondsToWait = config('request-insurance.microSecondsToWait', 100000);

        Log::info(sprintf('RequestInsuranceService has started. Running interval is [%d] microseconds', $microSecondsToWait));

        do {
            $executionTime = Stopwatch::time(function () {
                $this->processRequestInsurances();
            });

            $waitTime = (int) max($microSecondsToWait - $executionTime->microseconds(), 0);

            usleep($waitTime);
        } while ( ! $onlyOnce);

        Log::info('RequestInsuranceService has stopped.');

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
            Log::info(sprintf('Found [%s] requests ready for processing!', $requestIds->count()));

            if ($requestIds->isEmpty()) {
                return $requestIds;
            }

            // Lock the request with the ids
            Log::debug(sprintf('Locking [%d] requests with ids [%s]!', $requestIds->count(), $requestIds->implode(',')));

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
        Log::info('Processing started...');

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

        Log::info(sprintf('Processing of %s requests has completed!', $requests->count()));
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
}
