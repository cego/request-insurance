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
    public $content;

    /**
     * Create a new component instance.
     *
     * @param object|array|string $content
     *
     * @throws \JsonException
     */
    public function __construct($content)
    {
        $this->content = $this->prettyPrint($content);
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
     * @param object|array|string $content
     *
     * @return string
     */
    private function prettyPrint($content)
    {
        try {
            // Try to pretty print as json
            return $this->prettyPrintJson($content);
        } catch (Exception $jsonException) {
            // If invalid json then just display the data
            return print_r($content, true);
        }
    }

    /**
     * Takes either a json string or some data, and returns a json string that has been pretty printed.
     *
     * @param object|array|string $content
     *
     * @return string
     *
     * @throws \JsonException
     */
    private function prettyPrintJson($content)
    {
        if ( ! is_string($content) && ! is_array($content) && ! is_object($content)) {
            return '{}';
        }

        if (is_string($content) && mb_strtolower($content) === 'null') {
            return '{}';
        }

        // If it is already in json format, we then need to turn it into an object, so it can be reformatted in pretty print
        if (is_string($content)) {
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        return json_encode($content, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
