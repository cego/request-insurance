<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\Component;
use Illuminate\View\View;
use Illuminate\View\Factory;
use Exception;

use \Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;


class PrettyPrintDifference extends Component
{
    public $content;

    public function __construct($oldValues, $newValues)
    {
        $this->content = $this->prettyPrint($oldValues, $newValues);
    }

    protected array $rendererOptions = ['detailLevel' => 'line'];

    protected function prettyPrint($oldContent, $newContent) : string
    {
        try
        {
            // Must always include the same amount of fields
            if (count($oldContent) != count($newContent) || count($oldContent)) {
                return "";
            }

            // DiffHelper returns a string of the html.
            $htmlToRender = [];
            for ($i = 0; $i < count($oldContent); $i++) {
                $htmlToRender[] = DiffHelper::calculate($oldContent[$i],$newContent[$i]);
            }

            $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
            $content = $htmlRenderer->renderArray($htmlToRender);

            return $content;

        } catch (Exception $exception) {
            echo("error");
            return "";
        }
    }

    public function render()
    {
        return view('request-insurance::components.pretty-print-difference');
    }
}