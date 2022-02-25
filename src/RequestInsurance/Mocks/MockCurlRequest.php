<?php

namespace Cego\RequestInsurance\Mocks;

use Cego\RequestInsurance\Contracts\HttpRequest;

class MockCurlRequest extends HttpRequest
{
    /**
     * Holds all options set
     *
     * @var array $options
     */
    public $options = [];

    /**
     * Holds the next mocked response to return
     *
     * @var array
     */
    public static $mockedResponse;

    /**
     * Sets an option
     *
     * @param int $option
     * @param mixed $value
     *
     * @return $this
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    public function getInfo()
    {
        return static::$mockedResponse['info'] ?? [
            'http_code'  => 200,
            'total_time' => 1.0,
        ];
    }

    public function getErrorNumber()
    {
        return static::$mockedResponse['error_number'] ?? 0;
    }

    public function getError()
    {
        return static::$mockedResponse['error'] ?? 'no errors';
    }

    public function getResponse()
    {
        return static::$mockedResponse['response'] ?? 'mock-response';
    }

    public function close()
    {
        // No active connection to close, so just do nothing
    }

    public static function setNextResponse(array $response)
    {
        static::$mockedResponse = $response;
    }
}
