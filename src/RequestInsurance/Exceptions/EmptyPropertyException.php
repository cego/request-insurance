<?php

namespace Cego\RequestInsurance\Exceptions;

use Cego\RequestInsurance\Models\RequestInsurance;
use Exception;
use Throwable;

class EmptyPropertyException extends Exception
{
    /**
     * EmptyPropertyException constructor.
     * 
     * @param string $property
     * @param RequestInsurance $requestInsuranceModel
     * @param Throwable|null $previous
     */
    public function __construct(string $property, RequestInsurance $requestInsuranceModel, Throwable $previous = null)
    {
        $message = sprintf("Error saving Request Insurance. The '%s' property must not be empty. The following Request Insurance was not saved in DB: %s.", $property, $requestInsuranceModel->toJson());
        $code = 500;

        parent::__construct($message, $code, $previous);
    }
}