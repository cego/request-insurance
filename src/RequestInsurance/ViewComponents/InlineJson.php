<?php

namespace Nbj\RequestInsurance\ViewComponents;

use JsonException;
use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class InlineJson extends Component
{
    /**
     * Holds the json to be printed
     *
     * @var string $json
     */
    public $json;

    /**
     * PrettyJson constructor.
     *
     * @param object|array|string
     *
     * @throws JsonException
     */
    public function __construct($json)
    {
        $this->json = $json;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return Factory|View
     */
    public function render()
    {
        return view('request-insurance::components.pretty-json');
    }
}
