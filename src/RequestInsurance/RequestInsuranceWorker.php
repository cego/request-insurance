<?php

namespace Cego\RequestInsurance;

use Throwable;
use Exception;
use Nbj\Stopwatch;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\JobSupplier\JobSupplier;

class RequestInsuranceWorker
{
    /**
     * Holds a hash identifier for the service instance once set
     *
     * @var string|null $runningHash
     */
    protected ?string $runningHash = null;

    /**
     * Boolean flag, used to indicate if the service has received an outside signal to shutdown processing of records.
     * This allows for graceful shutdown, instead of shutting down the service hard - Causing unwanted states in Request Insurance rows.
     *
     * @var bool
     */
    protected bool $shutdownSignalReceived = false;

    /**
     * The request insurance job supplier responsible for
     * fetching the next request to be processed.
     *
     * @var JobSupplier
     */
    protected JobSupplier $jobSupplier;

    /**
     * RequestInsuranceService constructor.
     */
    public function __construct()
    {
        $this->runningHash = Str::random(8);
        $this->jobSupplier = resolve(JobSupplier::class);
    }

    /**
     * Runs the service
     *
     * @param false $runOnlyOnce
     *
     * @throws Throwable
     */
    public function run(bool $runOnlyOnce = false): void
    {
        Log::info(sprintf('RequestInsurance Worker (#%s) has started', $this->runningHash));

        $this->setupShutdownSignalHandler();

        do {
            $this->processingStep();
        } while ($this->shouldContinueApplicationLoop($runOnlyOnce));

        if ($this->jobSupplier->hasAnyQueuedJobs()) {
            Log::critical(sprintf('RequestInsurance Worker (#%s) was unable to release all queued jobs', $this->runningHash));
        } else {
            Log::info(sprintf('RequestInsurance Worker (#%s) has gracefully stopped', $this->runningHash));
        }
    }

    /**
     * Runs a single processing step
     *
     * @return void
     */
    protected function processingStep(): void
    {
        try {
            // We call DB::reconnect() to handle lost db connections.
            DB::reconnect();

            // The next job can be null if there are no more jobs left to process
            $job = $this->jobSupplier->getNextJob();

            if ($job === null) {
                return;
            }

            $this->process($job);
        } catch (Throwable $throwable) {
            Log::error($throwable);
            sleep(5); // Sleep to avoid spamming the log
        }

        pcntl_signal_dispatch();
    }

    /**
     * Returns true if the worker should continue its application loop
     *
     * @param bool $runOnlyOnce
     *
     * @return bool
     */
    protected function shouldContinueApplicationLoop(bool $runOnlyOnce): bool
    {
        if ($runOnlyOnce) {
            return $this->jobSupplier->hasAnyQueuedJobs();
        }

        return ! $this->shutdownSignalReceived;
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

        $this->jobSupplier->releaseAllQueuedJobs();
    }

    /**
     * Processes the given request
     *
     * @throws Throwable
     */
    protected function process(RequestInsurance $request): void
    {
        try {
            $request->process();
        } catch (Throwable $throwable) {
            Log::error($throwable);

            $request->pause();
        } finally {
            $request->unlock();
        }
    }
}
