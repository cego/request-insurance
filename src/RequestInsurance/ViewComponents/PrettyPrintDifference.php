<?php

namespace Cego\RequestInsurance\ViewComponents;

use Illuminate\View\Component;
use Illuminate\View\View;
use Illuminate\View\Factory;
use Exception;

use \Jfcherng\Diff\Differ;
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
        'showHeader'            => true,
        'separateBlock'         => true,
        'resultForIdenticals'   => null,
        'lineNumbers'           => false,
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

            // We need to use a different renderer for capturing differences in json, otherwise the result is quite useless
            if ($this->validJson($oldContent)) {
                return $this->prettyPrintJson($oldContent, $newContent);
            }

            // DiffHelper returns a string in html format.
            $content = DiffHelper::calculate($oldContent, $newContent, 'Json', $this->differOptions);

            $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
            $renderedContent = $htmlRenderer->renderArray(json_decode($content, true));

            return $renderedContent;

        } catch (Exception $exception) {
            return "ERROR IN GENERAL " . $exception->getMessage();
        }
    }

    protected function prettyPrintJson($oldContent, $newContent) : string
    {

        $differ = new Differ($oldContent, $newContent, $this->differOptions);

       //$this->rendererOptions['jsonEncodeFlags'] = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

        $this->rendererOptions['cliColorization'] = RendererConstant::CLI_COLOR_AUTO;

        $htmlRenderer = RendererFactory::make('Combined', $this->rendererOptions);
        $renderedContent = $htmlRenderer->render($differ);

        return $renderedContent;
    }

    protected function validJson($content) : bool
    {
        foreach ($content as $element) {
            // A string of a number or a number will be recognized as json
            if (is_numeric($content)) {
                return false;
            }

            json_decode($element);

            // if error occurs during json decode, json_last_error will return an integer representing the error; else it returns 0
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
        }

        return true;
    }

    public function render()
    {
        return view('request-insurance::components.pretty-print-difference');
    }
}