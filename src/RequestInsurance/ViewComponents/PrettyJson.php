<?php

namespace Nbj\RequestInsurance\ViewComponents;

use JsonException;
use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class PrettyJson extends Component
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
        $this->json = $this->prettifyJson($json);
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

    /**
     * @param $json
     *
     * @return false|string
     *
     * @throws JsonException
     */
    protected function prettifyJson($json)
    {
        if ( ! is_string($json) && ! is_array($json) && ! is_object($json)) {
            return '{}';
        }

        if (is_string($json) && mb_strtolower($json) == 'null') {
            return '{}';
        }

        if (is_string($json)) {
            $json = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        return json_encode($json, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
