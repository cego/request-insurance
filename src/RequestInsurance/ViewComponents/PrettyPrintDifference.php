<?php

namespace Cego\RequestInsurance\ViewComponents;

use Exception;
use Illuminate\View\View;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Illuminate\View\Component;
use Jfcherng\Diff\Factory\RendererFactory;

class PrettyPrintDifference extends Component
{
    /**
     * String with HTML displaying the difference between old string and new string.
     *
     * @var string
     */
    public $content;

    /**
     * Rendering options used when creating string of HTML.
     *
     * @var array
     */
    protected array $rendererOptions = [
        'detailLevel'         => 'word',
        'showHeader'          => true,
        'separateBlock'       => true,
        'resultForIdenticals' => null,
        'lineNumbers'         => true,
    ];

    /**
     * Additional options used when looking for differences in the two given strings.
     *
     * @var array
     */
    protected array $differOptions = [
        'ignoreWhitespace' => false,
        'context'          => 3,
    ];

    /**
     * Create a new component instance.
     *
     * @param object|array|string $content
     *
     * @throws \JsonException
     */
    public function __construct($oldValues, $newValues)
    {
        $this->content = $this->prettyPrint($oldValues, $newValues);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return string
     */
    public function render()
    {
        return $this->content;
    }

    /**
     * Pretty prints the difference between an old string and a new string.
     *
     * @param string $oldContent
     * @param string $newContent
     *
     * @return string
     */
    protected function prettyPrint(string $oldContent, string $newContent): string
    {
        try {
            // We need to use different rendering options for capturing differences in json, otherwise the result is quite useless
            if ($this->validJson($oldContent)) {
                return $this->prettyPrintDifferenceJson($oldContent, $newContent);
            }

            // DiffHelper returns content in json format
            $content = DiffHelper::calculate($oldContent, $newContent, 'Json', $this->differOptions);

            $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
            $renderedContent = $htmlRenderer->renderArray(json_decode($content, true));

            return $renderedContent;
        } catch (Exception $exception) {
            return ' ';
        }
    }

    /**
     * Pretty print differences for json strings. This is needed as it will otherwise not print the differences clearly
     * when using the Inline renderer option.
     *
     * @param string $oldContent
     * @param string $newContent
     *
     * @throws \JsonException
     *
     * @return string
     */
    protected function prettyPrintDifferenceJson(string $oldContent, string $newContent): string
    {
        $oldFormattedJson = $this->formatJson($oldContent);
        $newFormattedJson = $newContent;

        // Only try formatting if modified string is still valid json
        if ($this->validJson($newContent)) {
            $newFormattedJson = $this->formatJson($newContent);
        }

        $differ = new Differ(explode("\n", $oldFormattedJson), explode("\n", $newFormattedJson), $this->differOptions);

        // Change rendering options to better be able to capture differences
        $this->rendererOptions['detailLevel'] = 'char';

        $htmlRenderer = RendererFactory::make('Inline', $this->rendererOptions);
        $renderedContent = $htmlRenderer->render($differ);

        return $renderedContent;
    }

    /**
     * Returns true if string is valid json else it returns false.
     * Special case for string numbers, these are returned as false as well.
     *
     * @param string $content
     *
     * @return bool
     */
    protected function validJson(string $content): bool
    {
        if (is_numeric($content)) {
            return false;
        }

        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Formats a string of valid json to make it more readable.
     *
     * @param string $content
     *
     * @throws \JsonException
     *
     * @return string
     */
    protected function formatJson(string $content): string
    {
        $jsonContent = json_decode($content, true, JSON_THROW_ON_ERROR);

        return json_encode($jsonContent, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
