<?php

namespace Cego\RequestInsurance\ViewComponents;

use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class Timings extends Component
{
    /**
     * Holds the RequestInsurance instance
     *
     * @var RequestInsurance $requestInsurance
     */
    public $requestInsurance;

    /**
     * PrettyJson constructor.
     *
     * @param RequestInsurance $requestInsurance
     *
     * @throws JsonException
     */
    public function __construct(RequestInsurance $requestInsurance)
    {
        $this->requestInsurance = $requestInsurance;
    }

    /**
     * Gets the boot strap color of the status
     *
     * @return string
     */
    public function statusColor()
    {
        return State::getBootstrapColor($this->requestInsurance->state);
    }

    /**
     * Gets the text of the status
     *
     * @return string
     */
    public function statusText()
    {
        return $this->requestInsurance->state;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return Factory|View
     */
    public function render()
    {
        return view('request-insurance::components.status');
    }
}