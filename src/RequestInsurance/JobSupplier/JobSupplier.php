<?php

namespace Cego\RequestInsurance\JobSupplier;

use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;

class JobSupplier
{
    protected int $microSecondsToWait;
    protected int $batchSize;
    protected RequestInsuranceQueue $queue;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->microSecondsToWait = Config::get('request-insurance.microSecondsToWait');
        $this->batchSize = Config::get('request-insurance.batchSize');

        $this->initQueue(true);
    }

    /**
     * Initializes a new request insurance queue
     *
     * @param bool $initial
     *
     * @return void
     */
    protected function initQueue(bool $initial = false): void
    {
        // Make sure to release any unprocessed jobs before fetching new jobs to process
        // We should really never hit this case. But better safe than sorry.
        if (isset($this->queue) && $this->queue->isNotEmpty()) {
            $this->queue->releaseAll();
        }

        // Make sure to not keep hammering the DB for jobs to consume
        if (! $initial) {
            usleep($this->getTimeToSleep());
        }

        $this->queue = RequestInsuranceQueue::forBatchSize($this->batchSize);
    }

    /**
     * Returns the next job to process
     *
     * @return RequestInsurance|null
     */
    public function getNextJob(): ?RequestInsurance
    {
        if ($this->queue->isEmpty()) {
            $this->initQueue();
        }

        // Can return null if the current queue is empty and no new jobs are ready to be processed.
        return $this->queue->pop();
    }

    /**
     * Returns how many micro seconds should be slept before querying the DB for the next jobs to process
     *
     * @return float
     */
    protected function getTimeToSleep(): float
    {
        return max($this->microSecondsToWait - $this->queue->timeSinceInitialization(), 0);
    }

    /**
     * Releases any queued job, so another worker might try to consume those jobs
     *
     * @return void
     */
    public function releaseAllQueuedJobs(): void
    {
        $this->queue->releaseAll();
    }

    /**
     * Returns true if the job supplier has any queued up jobs
     *
     * @return bool
     */
    public function hasAnyQueuedJobs(): bool
    {
        return $this->queue->isNotEmpty();
    }
}