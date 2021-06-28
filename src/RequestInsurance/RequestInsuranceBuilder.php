<?php

namespace Cego\RequestInsurance;

use InvalidArgumentException;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceBuilder
{
    /**
     * The builder data used to created the request insurance instance once ->create() is called.
     *
     * @var array
     */
    protected $data = [
        'payload' => '',
    ];

    /**
     * Static method for getting a builder instance
     *
     * @return static
     */
    public static function new() {
        return new static();
    }

    /**
     * Sets the url parameter
     *
     * @param string $url
     *
     * @return RequestInsuranceBuilder
     */
    public function url(string $url): RequestInsuranceBuilder
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            throw new InvalidArgumentException(sprintf('Invalid request insurance url: "%s"', $url));
        }

        return $this->set('url', $url);
    }

    /**
     * Sets the method parameter
     *
     * @param string $method
     *
     * @return RequestInsuranceBuilder
     */
    public function method(string $method): RequestInsuranceBuilder
    {
        if (! in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete'])) {
            throw new InvalidArgumentException(sprintf('Invalid request insurance method: "%s"', $method));
        }

        return $this->set('method', $method);
    }

    /**
     * Sets the headers field
     *
     * @param array $headers
     *
     * @return $this
     */
    public function headers(array $headers): RequestInsuranceBuilder
    {
        return $this->set('headers', $headers);
    }

    /**
     * Sets the payload field
     *
     * @param string|array $payload
     */
    public function payload($payload): RequestInsuranceBuilder
    {
        return $this->set('payload', $payload);
    }

    /**
     * Sets the timeout_ms field
     *
     * @param int $timeout
     *
     * @return RequestInsuranceBuilder
     */
    public function timeoutMs(int $timeout): RequestInsuranceBuilder
    {
        if ($timeout <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid request insurance timeout_ms: "%s"', $timeout));
        }

        return $this->set('timeout_ms', $timeout);
    }

    /**
     * Sets the priority field
     *
     * @param int $priority
     *
     * @return $this
     */
    public function priority(int $priority): RequestInsuranceBuilder
    {
        if ($priority < 0) {
            throw new InvalidArgumentException(sprintf('Invalid request insurance priority: "%s"', $priority));
        }

        return $this->set('priority', $priority);
    }

    /**
     * Finishes the builder and creates an instance of a persisted Request Insurance row
     *
     * @return RequestInsurance
     */
    public function create(): RequestInsurance
    {
        return RequestInsurance::create($this->data);
    }

    /**
     * Sets a single field to a given value
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    protected function set($field, $value): RequestInsuranceBuilder
    {
        $this->data[$field] = $value;

        return $this;
    }
}