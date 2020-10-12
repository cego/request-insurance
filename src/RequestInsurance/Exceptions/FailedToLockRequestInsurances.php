<?php

namespace Cego\RequestInsurance\Exceptions;

use Exception;
use Throwable;

class FailedToLockRequestInsurances extends Exception
{
    /**
     * FailedToLockRequestInsurances constructor.
     *
     * @param Throwable|null $previous
     */
    public function __construct(Throwable $previous = null)
    {
        $message = 'RequestInsurance failed to lock requests for processing';
        $code = 500;

        parent::__construct($message, $code, $previous);
    }
}
