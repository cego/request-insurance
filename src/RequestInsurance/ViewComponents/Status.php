<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\View;
use Illuminate\View\Component;
use Cego\RequestInsurance\Enums\State;
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
        switch ($this->requestInsurance->state) {
            case State::WAITING:
            case State::READY:
            case State::PENDING:
            case State::PROCESSING:
                return 'secondary';

            case State::COMPLETED:
                return 'success';

            case State::FAILED:
                return 'danger';

            case State::ABANDONED:
                return 'warning';

            default:
                return 'primary';
        }
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
