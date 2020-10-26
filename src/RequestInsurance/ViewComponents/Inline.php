<?php

namespace Cego\RequestInsurance\ViewComponents;

use JsonException;
use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class Inline extends Component
{
    /**
     * Holds the json to be printed
     *
     * @var string $data
     */
    public $data;

    /**
     * PrettyJson constructor.
     *
     * @param object|array|string
     *
     * @throws JsonException
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return Factory|View
     */
    public function render()
    {
        return view('request-insurance::components.pretty-print');
    }
}