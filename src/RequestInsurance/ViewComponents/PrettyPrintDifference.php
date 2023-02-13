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
        'detailLevel'           => 'word',
        'showHeader'            => true,
        'separateBlock'         => true,
        'resultForIdenticals'   => null,
        'lineNumbers'           => false,
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
            /*if ($this->validJson($oldContent)) {
                return $this->prettyPrintDifferenceJson($oldContent[0], $newContent[0]);
            } */

            // DiffHelper returns a string in html format.
            $content = DiffHelper::calculate($oldContent, $newContent, 'Json', $this->differOptions);

            $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
            $renderedContent = $htmlRenderer->renderArray(json_decode($content, true));

            return $renderedContent;

        } catch (Exception $exception) {
            return "ERROR IN GENERAL " . $exception->getMessage();
        }
    }

    protected function prettyPrintDifferenceJson($oldContent, $newContent) : string
    {

        $oldFormattedJson = $this->reformatJson($oldContent);
        $newFormattedJson = $this->reformatJson($newContent);

        $differ = new Differ([$oldFormattedJson], [$newFormattedJson], $this->differOptions);

        $this->rendererOptions['cliColorization'] = RendererConstant::CLI_COLOR_ENABLE;

        $htmlRenderer = RendererFactory::make('Combined', $this->rendererOptions);
        $renderedContent = $htmlRenderer->render($differ);

        return $renderedContent;
    }

    protected function reformatJson($content) : string
    {
        $jsonContent = json_decode($content, true, JSON_THROW_ON_ERROR);

        return json_encode($jsonContent, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    protected function validJson($content) : bool
    {
        foreach ($content as $element) {
            // A string of a number or a number will be recognized as json
            if (is_numeric($element)) {
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