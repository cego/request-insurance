<?php

namespace Cego\RequestInsurance\ViewComponents;

use JsonException;
use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class InlinePrint extends Component
{
    /**
     * Holds the json to be printed
     *
     * @var string $content
     */
    public $content;

    /**
     * PrettyJson constructor.
     *
     * @param object|array|string
     *
     * @throws JsonException
     */
    public function __construct($content)
    {
        $this->content = $content;
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
