<?php

namespace Cego\RequestInsurance\JobSupplier;

use Exception;
use Nbj\Stopwatch;
use Carbon\Carbon;
use Nbj\StopwatchMeasurement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceQueue
{
    /**
     * The list of request insurances that have been locked for processing
     *
     * @var Collection
     */
    protected Collection $queue;

    /**
     * Timestamp at which the queue was initialized
     *
     * @var float
     */
    protected float $initialisedAt;

    /**
     * Constructor
     *
     * @param int $batchSize
     */
    public function __construct(int $batchSize)
    {
        $this->queue = $this->initQueue($batchSize);
        $this->initialisedAt = microtime(true);
    }

    /**
     * Static constructor
     *
     * @param int $batchSize
     *
     * @return static
     */
    public static function forBatchSize(int $batchSize): self
    {
        return new self($batchSize);
    }

    /**
     * Returns the micro timestamp of when the queue was initialized
     *
     * @return float
     */
    public function getInitialisedAt(): float
    {
        return $this->initialisedAt;
    }

    /**
     * Returns how many micro seconds since the queue was initialized
     *
     * @return float
     */
    public function timeSinceInitialization(): float
    {
        return microtime(true) - $this->initialisedAt;
    }

    /**
     * Locs a given batch size of rows to be processed by this queue
     *
     * @param int $batchSize
     *
     * @return Collection
     */
    public function initQueue(int $batchSize): Collection
    {
        $requestIds = $this->getRequestIdsOfJobsToConsume($batchSize);

        // Get requests to process ordered by priority
        return resolve(RequestInsurance::class)::query()
            ->whereIn('id', $requestIds)
            ->whereNotNull('locked_at')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Removes and returns the first job on the queue
     *
     * @return ?RequestInsurance
     */
    public function pop(): ?RequestInsurance
    {
        return $this->queue->pop();
    }

    /**
     * Returns true if the queue is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    /**
     * Returns true if the queue is not empty
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Releases all jobs in the queue
     *
     * @return void
     */
    public function releaseAll(): void
    {
        RequestInsurance::query()->whereIn('id', $this->queue->pluck('id'))->update(['locked_at' => null, 'updated_at' => Carbon::now()]);
        $this->queue = new Collection();
    }

    /**
     * @return int[]
     */
    protected function getRequestIdsOfJobsToConsume(int $batchSize): array
    {
        return DB::transaction(function () use ($batchSize) {
            $measurement = Stopwatch::time(fn () => $this->acquireLockOnRowsToProcess($batchSize));

            $this->logSlowQuery($measurement);

            return $measurement->result();
        }, 3);
    }

    /**
     * Log if selecting rows to process is getting too slow
     *
     * @param StopwatchMeasurement $measurement
     *
     * @return void
     */
    protected function logSlowQuery(StopwatchMeasurement $measurement): void
    {
        if ($measurement->seconds() >= 80) {
            Log::critical(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
        } else if ($measurement->seconds() >= 60) {
            Log::warning(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
        } else if ($measurement->seconds() >= 30) {
            Log::info(sprintf('%s: Selecting RI rows for processing took %d seconds!', __METHOD__, $measurement->seconds()));
        }
    }

    /**
     * Acquires a lock on the next rows to process, by setting the locked_at column
     *
     * @param int $batchSize
     *
     * @return Collection
     * @throws Exception
     */
    protected function acquireLockOnRowsToProcess(int $batchSize): Collection
    {
        $requestIds = $this->getIdsOfReadyRequests($batchSize);

        // Bail if no request are ready to be processed
        if ($requestIds->isEmpty()) {
            return $requestIds;
        }

        $locksWereObtained = resolve(RequestInsurance::class)::query()
            ->whereIn('id', $requestIds)
            ->update(['locked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        if (! $locksWereObtained) {
            throw new Exception(sprintf('RequestInsurance failed to obtain lock on ids: [%s]', $requestIds->implode(',')));
        }

        return $requestIds;
    }

    /**
     * Gets a collection of RequestInsurances ready to be processed
     *
     * @return mixed
     */
    public function getIdsOfReadyRequests(int $batchSize)
    {
        return resolve(RequestInsurance::class)
            ->query()
            ->select('id')
            ->readyToBeProcessed()
            ->take($batchSize)
            ->lockForUpdate()
            ->pluck('id');
    }
}