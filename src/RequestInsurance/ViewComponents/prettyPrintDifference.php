<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\Component;
use \Jfcherng\Diff\DiffHelper;



class prettyPrintDifference extends Component
{
    public function __construct($oldContent, $newContent, $options)
    {
        $this->content = $this->prettyPrint($oldContent, $newContent, $options);
        $this->options = $options;
    }

    private function prettyPrint($oldContent, $newContent, $options) : string
    {
        try
        {
            DiffHelper::calculate($oldContent, $newContent, $options);
        } catch (\Exception $exception) {

        }
    }

    public function render()
    {
        // TODO: Implement render() method.
    }
}