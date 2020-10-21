<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;
use Cego\RequestInsurance\Models\RequestInsurance;

class Status extends Component
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
        if ($this->requestInsurance->isCompleted()) {
            return 'success';
        }

        if ($this->requestInsurance->isPaused()) {
            return 'primary';
        }

        if ($this->requestInsurance->isAbandoned()) {
            return 'warning';
        }

        if ($this->requestInsurance->isLocked()) {
            return 'danger';
        }

        return 'secondary';
    }

    /**
     * Gets the text of the status
     *
     * @return string
     */
    public function statusText()
    {
        if ($this->requestInsurance->isCompleted()) {
            return 'Completed';
        }

        if ($this->requestInsurance->isPaused()) {
            return 'Paused';
        }

        if ($this->requestInsurance->isAbandoned()) {
            return 'Abandoned';
        }

        if ($this->requestInsurance->isLocked()) {
            return 'Locked';
        }

        return 'noStatus';
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
