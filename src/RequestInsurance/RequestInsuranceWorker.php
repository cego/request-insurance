<?php

namespace Cego\RequestInsurance;

use Exception;
use Throwable;
use Carbon\Carbon;
use Nbj\Stopwatch;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceWorker
{
    /**
     * Holds a hash identifier for the service instance once set
     *
     * @var string|null $runningHash
     */
    protected ?string $runningHash = null;

    /**
     * Once set, holds the number of microseconds to wait between each cycle
     *
     * @var int $microSecondsToWait
     */
    protected int $microSecondsToWait;

    /**
     * Boolean flag, used to indicate if the service has received an outside signal to shutdown processing of records.
     * This allows for graceful shutdown, instead of shutting down the service hard - Causing unwanted states in Request Insurance rows.
     *
     * @var bool
     */
    protected bool $shutdownSignalReceived = false;

    /**
     * The number of request insurances each worker processes pr. epoch
     *
     * @var int
     */
    protected int $batchSize;

    /**
     * RequestInsuranceService constructor.
     */
    public function __construct(int $batchSize = 100, int $microSecondsToWait = 100000)
    {
        $this->microSecondsToWait = 100000; //$microSecondsToWait;
        $this->batchSize = $batchSize;
        $this->runningHash = Str::random(8);
        Log::withContext(["worker.id" => $this->runningHash]);
    }

    /**
     * Runs the service
     *
     * @param false $runOnlyOnce
     *
     * @throws Throwable
     */
    public function run(bool $runOnlyOnce = true): void
    {
        Log::info(sprintf('RequestInsurance Worker (#%s) has started', $this->runningHash));

        $this->setupShutdownSignalHandler();

        do {
            $this->memDebug("Loop started");

            try {
                if (env('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT', true)) {
                    DB::reconnect();
                }

                $executionTime = Stopwatch::time(function () {
                    $this->processRequestInsurances();
                });

                $waitTime = (int) max($this->microSecondsToWait - $executionTime->microseconds(), 0);

                $this->memDebug("Sleep");
                usleep($waitTime);
            } catch (Throwable $throwable) {
                Log::error($throwable);
                sleep(5); // Sleep to avoid spamming the log
            }

            pcntl_signal_dispatch();
            $this->memDebug("Loop ended");
        } while ( ! $runOnlyOnce && ! $this->shutdownSignalReceived);

        Log::info(sprintf('RequestInsurance Worker (#%s) has gracefully stopped', $this->runningHash));
    }

    protected function memDebug(string $message)
    {
        $memoryUsage = round(memory_get_usage(false) / 1048576);
        $memoryRealUsage = round(memory_get_usage(true) / 1048576);

        $memoryPeakUsage = round(memory_get_peak_usage(false) / 1048576);
        $memoryPeakRealUsage = round(memory_get_peak_usage(true) / 1048576);

        Log::debug(sprintf("[%4dmb - %4dmb] [%4dmb - %4dmb] %s", $memoryUsage, $memoryRealUsage, $memoryPeakUsage, $memoryPeakRealUsage, $message));
    }

    /**
     * Sets up signal handler to make sure that request insurance can shutdown gracefully.
     *
     * This is required to avoid shutting request insurance workers down while they are still processing requests.
     * A force shutdown tends to put requests in a limbo state, where they are locked and never unlocked again.
     */
    protected function setupShutdownSignalHandler(): void
    {
        pcntl_signal(SIGQUIT, [$this, 'sig_handler']); // Code 3
        pcntl_signal(SIGTERM, [$this, 'sig_handler']); // Code 15
    }

    /**
     * The shutdown signal handler method responsible to stop further processing of rows.
     *
     * @param int $signo
     * @param mixed $siginfo
     */
    public function sig_handler(int $signo, $siginfo): void
    {
        Log::info(sprintf('RequestInsurance Worker (#%s) received a shutdown signal - Beginning graceful shutdown', $this->runningHash));

        $this->shutdownSignalReceived = true;
    }

    /**
     * Processes all requests ready to be processed
     *
     * @throws Throwable
     */
    protected function processRequestInsurances(): void
    {
        $this->memDebug('#### Sleeping getting stuff');
        sleep(5);

        /** @var Collection $requestIds */
        $requestIds = DB::transaction(function () {
            try {
                $this->memDebug('START: locks on rows to process');
                $measurement = Stopwatch::time(function () {
                    return $this->acquireLockOnRowsToProcess();
                });

                $this->memDebug('END:   locks on rows to process');

                if ($measurement->seconds() >= 80) {
                    Log::critical(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
                } elseif ($measurement->seconds() >= 60) {
                    Log::warning(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
                } elseif ($measurement->seconds() >= 30) {
                    Log::info(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
                }

                return $measurement->result();
            }
            catch (Throwable $throwable) {
                Log::error($throwable);
                throw $throwable;
            }
        });

        $this->memDebug('START: FETCH RI');
        // Gets requests to process ordered by priority
        $requests = resolve(RequestInsurance::class)::query()
            ->whereIn('id', $requestIds)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $this->memDebug('END:   FETCH RI');

        $this->memDebug('#### Sleeping before processing 4444444444444');
        sleep(5);

        $requests->each(function ($request) {
            /** @var RequestInsurance $request */
            try {
                $this->memDebug('START: Process RI');
                $request->process();
            } catch (Throwable $throwable) {
                Log::error($throwable);

                $request->pause();
            } finally {
                $request->unlock();
            }

            $this->memDebug('END:   Process RI');
        });

        $this->memDebug('#### Sleeping after processing');
        sleep(5);
    }

    /**
     * Acquires a lock on the next rows to process, by setting the locked_at column
     *
     * @throws Exception
     *
     * @return Collection
     */
    protected function acquireLockOnRowsToProcess(): Collection
    {
        $this->memDebug('START: Get ids of ready requests');
        $requestIds = $this->getIdsOfReadyRequests();
        $this->memDebug('END:   Get ids of ready requests');

        // Bail if no request are ready to be processed
        if ($requestIds->isEmpty()) {
            return $requestIds;
        }

        $this->memDebug('START: GET LOCK');
        $locksWereObtained = resolve(RequestInsurance::class)::query()
            ->whereIn('id', $requestIds)
            ->update(['locked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        $this->memDebug('END:   GET LOCK');

        if ( ! $locksWereObtained) {
            throw new Exception(sprintf('RequestInsurance failed to obtain lock on ids: [%s]', $requestIds->implode(',')));
        }

        return $requestIds;
    }

    /**
     * Gets a collection of RequestInsurances ready to be processed
     *
     * @return mixed
     */
    public function getIdsOfReadyRequests()
    {
        return resolve(RequestInsurance::class)::query()
            ->select('id')
            ->readyToBeProcessed()
            ->take($this->batchSize)
            ->lockForUpdate()
            ->pluck('id');
    }
}
