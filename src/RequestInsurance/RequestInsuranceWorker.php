<?php

namespace Cego\RequestInsurance;

use Closure;
use Exception;
use Throwable;
use Carbon\Carbon;
use Nbj\Stopwatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
     * Timestamp used for running stuff at most once every second
     *
     * @var array
     */
    protected array $secondIntervalTimestamp;

    protected RequestInsuranceClient $client;

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
                if (Config::get('request-insurance.useDbReconnect')) {
                    DB::reconnect();
                }

                $executionTime = Stopwatch::time(function () {
                    $this->processRequestInsurances();
                    $this->atMostOnceEverySecond(fn () => $this->readyWaitingRequestInsurances());
                });

                $waitTime = (int) max(Config::get('request-insurance.microSecondsToWait') - $executionTime->microseconds(), 0);

                usleep($waitTime);
            } catch (Throwable $throwable) {
                Log::error($throwable);

                if ($runOnlyOnce) {
                    throw $throwable;
                }

                sleep(5); // Sleep to avoid spamming the log
            }

            pcntl_signal_dispatch();
        } while ( ! $runOnlyOnce && ! $this->shutdownSignalReceived);

        Log::info(sprintf('RequestInsurance Worker (#%s) has gracefully stopped', $this->runningHash));
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
        $requests = $requests
            ->each(fn (RequestInsurance $requestInsurance) => Events\RequestBeforeProcess::dispatch($requestInsurance))
            ->filter(fn (RequestInsurance $requestInsurance) => $requestInsurance->hasState(State::PENDING));

        // If all requests were cancelled by the listeners, then bail out.
        if ($requests->isEmpty()) {
            return;
        }

        // Increment the number of attempts and set state to PROCESSING as the very first action
        $this->setStateToProcessingAndIncrementAttempts($requests);
        // Send the requests concurrently
        $responses = $this->client->pool($requests);

        // Handle the responses sequentially - Rescue is used to avoid it breaking the handling of the full batch
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
     * Returns the concurrent request chunk size
     *
     * @return int
     */
    protected function getRequestChunkSize(): int
    {
        if (Config::get('request-insurance.concurrentHttpEnabled', false)) {
            return Config::get('request-insurance.concurrentHttpChunkSize', 5);
        }

        return 1;
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
        return resolve(RequestInsurance::class)::query()
            ->select('id')
            ->readyToBeProcessed()
            ->take(Config::get('request-insurance.batchSize'))
            ->lock('FOR UPDATE SKIP LOCKED')
            ->pluck('id');
    }
}
