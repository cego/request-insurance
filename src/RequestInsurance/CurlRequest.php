<?php

namespace Cego\RequestInsurance;

use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\Contracts\ContainsResponseHeaders;

class CurlRequest extends HttpRequest implements ContainsResponseHeaders
{
    /**
     * The curl handle
     *
     * @var false|resource
     */
    protected $handle;

    /**
     * Holds a list of all response headers once request has been executed
     *
     * @var array $responseHeaders
     */
    protected $responseHeaders = [];

    /**
     * CurlRequest constructor.
     */
    protected function __construct()
    {
        parent::__construct();

        $this->handle = curl_init();
    }

    /**
     * Sets a curl option
     *
     * @param int $option
     * @param mixed $value
     *
     * @return $this
     */
    public function setOption($option, $value)
    {
        curl_setopt($this->handle, $option, $value);

        return $this;
    }

    /**
     * Gets information about the request
     *
     * @return mixed
     */
    public function getInfo()
    {
        return curl_getinfo($this->handle);
    }

    /**
     * Gets the curl error number if set
     *
     * @return int
     */
    public function getErrorNumber()
    {
        return curl_errno($this->handle);
    }

    /**
     * Gets the curl error message if set
     *
     * @return string
     */
    public function getError()
    {
        return curl_error($this->handle);
    }

    /**
     * Executes the request
     *
     * @return string|bool
     */
    public function getResponse()
    {
        curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, [$this, 'handleHeaderLine']);

        return curl_exec($this->handle);
    }

    /**
     * Closes the curl resource
     *
     * @return $this
     */
    public function close()
    {
        curl_close($this->handle);

        return $this;
    }

    /**
     * Custom function to for curl to use when reading headers
     *
     * @param $curlResource
     * @param string $headerLine
     *
     * @return int
     */
    protected function handleHeaderLine($curlResource, $headerLine)
    {
        // As this function must return the length of the header line we store this for later
        $headerLineLength = strlen($headerLine);

        // We need to first check if the header line is valid and bail if it is not
        $headerLineKeyValuePair = explode(':', $headerLine, 2);

        if (count($headerLineKeyValuePair) < 2) {
            return $headerLineLength;
        }

        // Now we can manhandle the header line and store it in the instance headers array
        [$key, $value] = $headerLineKeyValuePair;
        $this->responseHeaders[trim($key)] = trim($value);

        // As promised we return the length of the header line
        return $headerLineLength;
    }

    /**
     * Gets a list of all response headers
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }
}
