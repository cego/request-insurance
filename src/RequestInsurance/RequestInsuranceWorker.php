<?php

namespace Cego\RequestInsurance;

use Closure;
use Exception;
use Throwable;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\DetectsLostConnections;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RequestInsuranceWorker
{
    use DetectsLostConnections;

    private const TIMEOUT_EXIT_CODE = 124;

    /**
     * The number of consecutive lost database connections to recover from quietly,
     * before treating them as an outage worth reporting
     */
    private const QUIET_LOST_CONNECTION_RECOVERIES = 3;

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
     * Timestamp used for running stuff at most once every second
     *
     * @var array
     */
    protected array $secondIntervalTimestamp;

    protected RequestInsuranceClient $client;

    protected string $currentPhase = 'idle';

    protected ?string $currentRequestInsuranceIds = null;

    protected int $consecutiveLostConnections = 0;

    /**
     * RequestInsuranceService constructor.
     */
    public function __construct()
    {
        $this->runningHash = Str::random(8);
        $this->secondIntervalTimestamp = hrtime();
        $this->client = resolve(RequestInsuranceClient::class);
        Log::withContext(['worker.id' => $this->runningHash]);
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
            try {
                $this->registerTimeoutHandler();

                if (Config::get('request-insurance.useDbReconnect')) {
                    $this->setWorkerPhase('database_reconnect');
                    $this->reconnectToDatabase();
                }

                $start = hrtime(true);

                $this->processRequestInsurances();
                $this->atMostOnceEverySecond(function () {
                    $this->setWorkerPhase('waiting_request_readiness_update');
                    $this->readyWaitingRequestInsurances();
                });

                $this->consecutiveLostConnections = 0;

                $executionTimeNs = hrtime(true) - $start;

                $waitTime = (int) max(Config::get('request-insurance.microSecondsToWait') - ($executionTimeNs / 1000), 0);

                $this->setWorkerPhase('cycle_sleep');
                usleep($waitTime);
            } catch (Throwable $throwable) {
                $this->resetTimeoutHandler(); // We need to reset here before logging the error and sleeping, otherwise the timeout handler might trigger while we are sleeping/logging, which is not desirable.

                $this->handleCycleFailure($throwable);

                if ($runOnlyOnce) {
                    throw $throwable;
                }

                usleep(100_000); // Sleep to avoid spamming the log
            } finally {
                $this->resetTimeoutHandler();
            }
        } while ( ! $runOnlyOnce && ! $this->shutdownSignalReceived);

        Log::info(sprintf('RequestInsurance Worker (#%s) has gracefully stopped', $this->runningHash));
    }

    /**
     * Handles a failed worker cycle
     *
     * A dropped database connection is expected of a long-lived worker, and is recovered from
     * without noise, until it keeps happening and starts looking like an outage instead.
     *
     * @param Throwable $throwable
     *
     * @return void
     */
    protected function handleCycleFailure(Throwable $throwable): void
    {
        if ( ! $this->wasCausedByLostConnection($throwable)) {
            $this->consecutiveLostConnections = 0;

            Log::error($throwable);

            return;
        }

        $this->consecutiveLostConnections++;

        $message = sprintf(
            'RequestInsurance Worker (#%s) lost its database connection during %s and is reconnecting (%d in a row)',
            $this->runningHash,
            $this->currentPhase,
            $this->consecutiveLostConnections
        );

        if ($this->consecutiveLostConnections > self::QUIET_LOST_CONNECTION_RECOVERIES) {
            Log::error($message, ['exception' => $throwable]);
        } else {
            Log::debug($message);
        }

        rescue(fn () => $this->reconnectToDatabase(), null, false);
    }

    /**
     * Tells if the given throwable, or anything it wraps, was caused by a lost database connection
     *
     * @param Throwable $throwable
     *
     * @return bool
     */
    protected function wasCausedByLostConnection(Throwable $throwable): bool
    {
        for ($exception = $throwable; $exception !== null; $exception = $exception->getPrevious()) {
            if ($this->causedByLostConnection($exception)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reconnects the database connection holding the request insurance tables
     *
     * @return void
     */
    protected function reconnectToDatabase(): void
    {
        DB::reconnect(resolve(RequestInsurance::class)->getConnectionName());
    }

    /**
     * Sets up signal handler to make sure that request insurance can shutdown gracefully.
     *
     * This is required to avoid shutting request insurance workers down while they are still processing requests.
     * A force shutdown tends to put requests in a limbo state, where they are locked and never unlocked again.
     */
    protected function setupShutdownSignalHandler(): void
    {
        pcntl_async_signals(true);
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
     * Method for running the given closure at most once every second.
     * This method cannot be reused multiple time.
     *
     * @param Closure $closure
     *
     * @return void
     */
    protected function atMostOnceEverySecond(Closure $closure): void
    {
        // $now[0 => seconds, 1 => nanoseconds]
        $now = hrtime();

        // If a second has passed
        if ($this->secondIntervalTimestamp[0] < $now[0]) {
            $this->secondIntervalTimestamp = $now;
            $closure();
        }
    }

    /**
     * Marks waiting request insurances as ready
     *
     * @return void
     */
    protected function readyWaitingRequestInsurances(): void
    {
        TransactionIsolation::readCommittedForNextTransaction();

        RequestInsurance::query()
            ->where('state', State::WAITING)
            ->where('retry_at', '<=', Carbon::now('UTC'))
            ->update(['state' => State::READY, 'state_changed_at' => Carbon::now('UTC'), 'retry_at' => null]);
    }

    /**
     * Processes all requests ready to be processed
     *
     * @throws Throwable
     */
    protected function processRequestInsurances(): void
    {
        $this->setWorkerPhase('request_acquisition');

        $this->getRequestsToProcess()
            ->chunk($this->getRequestChunkSize())
            ->each(fn (EloquentCollection $requestChunk) => rescue(function () use ($requestChunk) {
                $this->processHttpRequestChunk($requestChunk);
            }));
    }

    /**
     * @param EloquentCollection<RequestInsurance> $requests
     *
     * @noinspection CallableParameterUseCaseInTypeContextInspection
     *
     * @throws Throwable
     *
     * @return void
     */
    protected function processHttpRequestChunk(EloquentCollection $requests): void
    {
        // An event is dispatched before processing begins
        // allowing the application to abandon/complete/fail the requests before processing.
        $this->setWorkerPhase('before_process_events', $requests->pluck('id'));

        $requests = $requests
            ->each(fn (RequestInsurance $requestInsurance) => Events\RequestBeforeProcess::dispatch($requestInsurance))
            ->filter(fn (RequestInsurance $requestInsurance) => $requestInsurance->hasState(State::PENDING));

        // If all requests were cancelled by the listeners, then bail out.
        if ($requests->isEmpty()) {
            return;
        }

        $requestIds = $requests->pluck('id');

        // Increment the number of attempts and set state to PROCESSING as the very first action
        $this->setWorkerPhase('state_transition', $requestIds);
        $this->setStateToProcessingAndIncrementAttempts($requests);

        // Send the requests concurrently
        $this->setWorkerPhase('http_sending', $requestIds);
        $responses = $this->client->pool($requests);

        // Handle the responses sequentially - Rescue is used to avoid it breaking the handling of the full batch
        $this->setWorkerPhase('response_handling', $requestIds);

        /** @var RequestInsurance $request */
        foreach ($requests as $request) {
            rescue(fn () => $request->handleResponse($responses->get($request)));
        }
    }

    /**
     * Sets the state to processing and increments the amount of attempts for the given requests
     *
     * @param EloquentCollection $requests
     *
     * @return void
     */
    protected function setStateToProcessingAndIncrementAttempts(EloquentCollection $requests): void
    {
        $now = Carbon::now('UTC');

        $updatedRows = RequestInsurance::query()
            ->whereIn('id', $requests->pluck('id'))
            ->update([
                'state'            => State::PROCESSING,
                'state_changed_at' => $now,
                'retry_count'      => DB::raw('retry_count + 1'),
            ]);

        if ( ! $updatedRows) {
            throw new \RuntimeException('Could not update jobs before processing begins');
        }

        // Reflect the same change in-memory
        $requests->each(fn (RequestInsurance $requestInsurance) => $requestInsurance->forceFill([
            'state'            => State::PROCESSING,
            'state_changed_at' => $now,
            'retry_count'      => $requestInsurance->retry_count + 1,
        ]));
    }

    /**
     * Returns the number of requests to send concurrently
     *
     * @return int
     */
    protected function getRequestChunkSize(): int
    {
        // Deprecated flag, still honoured for configs that set it explicitly
        if (Config::get('request-insurance.concurrentHttpEnabled') === false) {
            return 1;
        }

        $chunkSize = Config::get('request-insurance.concurrentHttpChunkSize')
            ?? Config::get('request-insurance.batchSize', 100);

        return max(1, (int) $chunkSize);
    }

    /**
     * Returns a collection of requests to process
     *
     * @throws Exception
     *
     * @return EloquentCollection
     */
    protected function getRequestsToProcess(): EloquentCollection
    {
        $requestIds = $this->acquireLockOnRowsToProcess();

        if ($requestIds->isEmpty()) {
            return EloquentCollection::empty();
        }

        // Gets requests to process ordered by priority and id
        return resolve(RequestInsurance::class)::query()
            ->whereIn('id', $requestIds)
            ->get()
            ->sortBy(['priority', 'id']);
    }

    /**
     * Acquires a lock on the next rows to process
     *
     * @throws Exception
     *
     * @return Collection
     */
    public function acquireLockOnRowsToProcess(): Collection
    {
        TransactionIsolation::readCommittedForNextTransaction();

        return DB::transaction(function () {
            $requestIds = $this->getIdsOfReadyRequests();

            // Bail if no request are ready to be processed
            if ($requestIds->isEmpty()) {
                return $requestIds;
            }

            // Mark the selected jobs as PENDING so other workers do not try to consume them
            $now = CarbonImmutable::now();

            $locksWereObtained = resolve(RequestInsurance::class)::query()
                ->whereIn('id', $requestIds)
                ->update([
                    'state'            => State::PENDING,
                    'state_changed_at' => $now,
                ]);

            if ( ! $locksWereObtained) {
                throw new Exception(sprintf('RequestInsurance failed to obtain lock on ids: [%s]', $requestIds->implode(',')));
            }

            return $requestIds;
        }, 5);
    }

    /**
     * Gets a collection of RequestInsurances ready to be processed
     *
     * @return mixed
     */
    public function getIdsOfReadyRequests()
    {
        $model = resolve(RequestInsurance::class);

        $builder = $model::query()
            ->select('id')
            ->readyToBeProcessed()
            ->take(Config::get('request-insurance.batchSize'));

        if (Config::get('request-insurance.useForceIndex', true) && $model->getConnection()->getDriverName() === 'mysql') {
            $builder->from(DB::raw(sprintf('`%s` FORCE INDEX (`%s_state_priority_index`)', $model->getTable(), $model->getTable())));
        }

        if (config('request-insurance.useSkipLocked')) {
            $builder->lock('FOR UPDATE SKIP LOCKED');
        } else {
            $builder->lockForUpdate();
        }

        return $builder->pluck('id');
    }

    protected function setWorkerPhase(string $phase, $requestInsuranceIds = null): void
    {
        $this->currentPhase = $phase;

        if ($requestInsuranceIds === null) {
            $this->currentRequestInsuranceIds = null;

            return;
        }

        $this->currentRequestInsuranceIds = Collection::wrap($requestInsuranceIds)->implode(',');
    }

    protected function timeoutDiagnosticMessage(): string
    {
        $message = sprintf(
            'Timeout handler was triggered indicating stuck worker during %s',
            $this->currentPhase
        );

        if ($this->currentRequestInsuranceIds !== null && $this->currentRequestInsuranceIds !== '') {
            $message .= sprintf(' [request_insurance_ids: %s]', $this->currentRequestInsuranceIds);
        }

        return $message . ', exiting...';
    }

    protected function writeTimeoutDiagnosticToStdout(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }

    protected function handleTimeoutSignal(): void
    {
        $message = $this->timeoutDiagnosticMessage();

        try {
            $this->writeTimeoutDiagnosticToStdout($message);
        } catch (Throwable) {
        }

        try {
            Log::debug($message);
        } finally {
            $this->terminateWorkerAfterTimeout();
        }
    }

    protected function terminateWorkerAfterTimeout(): void
    {
        if (($pid = getmypid()) === false) {
            posix_kill($pid, SIGKILL);
        }
        exit(self::TIMEOUT_EXIT_CODE);
    }

    private function registerTimeoutHandler()
    {
        pcntl_signal(SIGALRM, function () {
            $this->handleTimeoutSignal();
        });
        pcntl_alarm((int) Config::get('request-insurance.maximumSecondsPerWorkerCycle', 120));
    }

    private function resetTimeoutHandler(): void
    {
        pcntl_alarm(0);
    }
}
