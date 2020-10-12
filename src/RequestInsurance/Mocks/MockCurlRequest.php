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
        return [
            'http_code'  => 200,
            'total_time' => 1.0
        ];
    }

    public function getErrorNumber()
    {
        return 0;
    }

    public function getError()
    {
        return 'no errors';
    }

    public function getResponse()
    {
        return 'mock-response';
    }

    public function close()
    {
        // TODO: Implement close() method.
    }
}
