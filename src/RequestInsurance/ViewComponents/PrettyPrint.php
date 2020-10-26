<?php

namespace Cego\RequestInsurance\ViewComponents;

use Exception;
use Illuminate\View\View;
use Illuminate\View\Component;
use Illuminate\Contracts\View\Factory;

class PrettyPrint extends Component
{
    /**
     * Json string pretty printed
     *
     * @var string
     */
    public $data;

    /**
     * Create a new component instance.
     *
     * @param object|array|string $data
     *
     * @throws \JsonException
     */
    public function __construct($data)
    {
        $this->data = $this->prettyPrint($data);
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

    /**
     * @param object|array|string $data
     *
     * @return string
     */
    private function prettyPrint($data)
    {
        try {
            // Try to pretty print as json
            return $this->prettyPrintJson($data);
        } catch (Exception $jsonException) {
            // If invalid json then just display the data
            return print_r($data, true);
        }
    }

    /**
     * Takes either a json string or some data, and returns a json string that has been pretty printed.
     *
     * @param object|array|string $data
     *
     * @return string
     *
     * @throws \JsonException
     */
    private function prettyPrintJson($data)
    {
        if ( ! is_string($data) && ! is_array($data) && ! is_object($data)) {
            return '{}';
        }

        if (is_string($data) && mb_strtolower($data) === 'null') {
            return '{}';
        }

        // If it is already in json format, we then need to turn it into an object, so it can be reformatted in pretty print
        if (is_string($data)) {
            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
