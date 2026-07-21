<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class Timestamp extends Component
{
    /**
     * The timestamp to render (or null).
     *
     * @var \Carbon\Carbon|\DateTimeInterface|null
     */
    public $value;

    /**
     * @param \Carbon\Carbon|\DateTimeInterface|null $value
     */
    public function __construct($value = null)
    {
        $this->value = $value;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return Factory|View
     */
    public function render()
    {
        return view('request-insurance::components.timestamp');
    }
}
