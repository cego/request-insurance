<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;
use Cego\RequestInsurance\Models\RequestInsuranceEdit;

class EditApprovalsStatus extends Component
{
    /**
     * Holds the RequestInsurance instance
     *
     * @var RequestInsuranceEdit $requestInsuranceEdit
     */
    public $requestInsuranceEdit;

    /**
     * PrettyJson constructor.
     *
     * @param RequestInsuranceEdit $requestInsuranceEdit
     *
     * @throws JsonException
     */
    public function __construct(RequestInsuranceEdit $requestInsuranceEdit)
    {
        $this->requestInsuranceEdit = $requestInsuranceEdit;
    }

    /**
     * Gets the boot strap color of the status
     *
     * @return string
     */
    public function statusColor()
    {
        return $this->requestInsuranceEdit->approvals->count() >= $this->requestInsuranceEdit->required_number_of_approvals ? 'success' : 'warning';
    }

    /**
     * Gets the text of the status
     *
     * @return string
     */
    public function statusText()
    {
        return sprintf('%d/%d', $this->requestInsuranceEdit->approvals->count(), $this->requestInsuranceEdit->required_number_of_approvals);
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
