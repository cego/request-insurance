<?php

namespace Cego\RequestInsurance\JobSupplier;

use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;

class JobSupplier
{
    protected int $batchSize;
    protected RequestInsuranceQueue $queue;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->batchSize = Config::get('request-insurance.batchSize', 100);
        $this->initQueue();
    }

    /**
     * Initializes a new request insurance queue
     *
     * @return void
     */
    protected function initQueue(): void
    {
        // Make sure to release any unprocessed jobs before fetching new jobs to process
        // We should really never hit this case. But better safe than sorry.
        if (isset($this->queue) && $this->queue->isNotEmpty()) {
            $this->queue->releaseAll();
        }

        $this->queue = RequestInsuranceQueue::forBatchSize($this->batchSize);
    }

    /**
     * Returns the next job to process
     *
     * @return RequestInsurance|null
     */
    public function getJob(): ?RequestInsurance
    {
        if ($this->queue->isEmpty()) {
            $this->initQueue();
        }

        // Can return null if the current queue is empty and no new jobs are ready to be processed.
        return $this->queue->pop();
    }
}