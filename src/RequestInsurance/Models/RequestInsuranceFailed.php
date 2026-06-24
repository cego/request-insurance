<?php

namespace Cego\RequestInsurance\Models;

use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\FailedRequestMover;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FAILED and ABANDONED request insurances live in their own ("failed jobs" style)
 * table so the partitioned main table only ever holds the success lifecycle and
 * whole partitions can be dropped at retention. Extends RequestInsurance so all
 * view components and accessors keep working unchanged.
 */
class RequestInsuranceFailed extends RequestInsurance
{
    public function getTable(): string
    {
        return FailedRequestMover::failedTable();
    }

    /**
     * Logs and edits reference request_insurance_id regardless of which table the
     * request lives in, so the relationship foreign key must not be derived from
     * this subclass's name (which would yield request_insurance_failed_id).
     */
    public function getForeignKey(): string
    {
        return 'request_insurance_id';
    }

    /**
     * Relationship with RequestInsuranceFailedLog
     *
     * @return HasMany
     */
    public function logs()
    {
        return $this->hasMany(RequestInsuranceFailedLog::class, 'request_insurance_id');
    }

    /**
     * Restore this request into the active main table as READY (with a fresh
     * created_at, so it lands in a current partition) and remove it from here.
     * Returns the restored row from the main table (this exceptions-table instance
     * no longer exists once restored).
     */
    public function retryNow(): RequestInsurance
    {
        if ( ! $this->isRetryable()) {
            return $this;
        }

        FailedRequestMover::restoreToActive($this->getKey());

        return RequestInsurance::query()->find($this->getKey()) ?? $this;
    }

    /**
     * The request is already in the exceptions table; just mark it ABANDONED in
     * place (it remains retryable from here).
     */
    public function abandon(): RequestInsurance
    {
        $this->setState(State::ABANDONED);
        $this->save();

        return $this;
    }
}
