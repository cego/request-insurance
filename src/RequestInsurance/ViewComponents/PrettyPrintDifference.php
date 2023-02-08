<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\Component;
use Illuminate\View\View;
use Illuminate\View\Factory;
use Exception;

use \Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;


class PrettyPrintDifference extends Component
{
    public $content;

    public function __construct($oldValues, $newValues)
    {
        $this->content = $this->prettyPrint($oldValues, $newValues);
    }

    protected array $rendererOptions = [
        'detailLevel'           => 'char',
        //'showHeader'            => true,
        //'separateBlock'         => true,
        'resultForIdenticals'   => null,
        'lineNumbers'           => false,
        'wordGlues'             => ['-'],
        'wrapperClasses'        => ['diff-wrapper'],

        ];

    protected array $differOptions = [
        'ignoreWhitespace' => false,
        'context'          => 3
    ];

    protected function prettyPrint($oldContent, $newContent) : string
    {
        try
        {
            // Must always include the same amount of fields
            if (count($oldContent) != count($newContent) || count($oldContent) == 0) {
                return " ";
            }

            // DiffHelper returns a string in html format.
            $content = DiffHelper::calculate($oldContent, $newContent, 'Json', $this->differOptions);

            $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
            $renderedContent = $htmlRenderer->renderArray(json_decode($content, true));

            return $renderedContent;

        } catch (Exception $exception) {
            return "ERROR IN GENERAL $content" . $exception->getMessage();
        }
    }

    protected function validJson($content) {
        json_decode($content);

        // if error occurs during json decode, json_last_error will return an integer representing the error; else it returns 0.
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function render()
    {
        return view('request-insurance::components.pretty-print-difference');
    }
}