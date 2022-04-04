<?php

namespace Cego\RequestInsurance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Cego\RequestInsurance\Models\RequestInsurance;

/**
 * Class AbstractRequestInsuranceEvent
 *
 * @mixin RequestInsurance
 *
 * @package Cego\RequestInsurance\Events
 */
abstract class AbstractRequestInsuranceEvent
{
    use Dispatchable;

    /**
     * The order instance.
     *
     * @var RequestInsurance
     */
    public RequestInsurance $requestInsurance;

    /**
     * Create a new event instance.
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return void
     */
    public function __construct(RequestInsurance $requestInsurance)
    {
        $this->requestInsurance = $requestInsurance;
    }

    /**
     * Php magic method to pass method calls to the request insurance model
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->requestInsurance->$name($arguments);
    }

    /**
     * Php magic method to pass property access to the request insurance model
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->requestInsurance->$name;
    }
}
