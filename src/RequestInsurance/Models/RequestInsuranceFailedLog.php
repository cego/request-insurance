<?php

namespace Cego\RequestInsurance\Models;

use Cego\RequestInsurance\FailedRequestMover;

/**
 * Logs belonging to FAILED/ABANDONED request insurances, stored alongside their
 * request in the exceptions tables.
 */
class RequestInsuranceFailedLog extends RequestInsuranceLog
{
    public function getTable(): string
    {
        return FailedRequestMover::failedLogsTable();
    }
}
