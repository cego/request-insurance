<?php

namespace Cego\RequestInsurance\Exceptions;

use Exception;
use Throwable;

class MethodNotAllowedForRequestInsurance extends Exception
{
    /**
     * FailedToLockRequestInsurances constructor.
     *
     * @param string $method
     * @param Throwable|null $previous
     */
    public function __construct($method, Throwable $previous = null)
    {
        $message = sprintf('RequestInsurance - HttpRequest does not accept the method [%s]', $method);
        $code = 500;

        parent::__construct($message, $code, $previous);
    }
}
