<?php

namespace Cego\RequestInsurance;

use Illuminate\Support\Arr;
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
    public static function new()
    {
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
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
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
        if ( ! in_array(strtolower($method), ['get', 'post', 'put', 'patch', 'delete'])) {
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
     * Sets the trace id field
     *
     * @param string $traceId
     *
     * @return $this
     */
    public function traceId(string $traceId): RequestInsuranceBuilder
    {
        return $this->set('trace_id', $traceId);
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
     * Adds a header to the list of headers to encrypt.
     * The key supports dot notation.
     *
     * @param string $headerKey
     *
     * @return RequestInsuranceBuilder
     */
    public function encryptHeader(string $headerKey): RequestInsuranceBuilder
    {
        // We put the encrypted headers into the sub-key 'headers'
        // so that this implementation is forward compatible with increased encryption
        // support for things like the payload.
        return $this->append('encrypted_fields.headers', $headerKey);
    }

    /**
     * Adds a list of headers to the list of headers to encrypt.
     * The keys support dot notation.
     *
     * @param string[] $headerKeys
     */
    public function encryptHeaders(array $headerKeys): RequestInsuranceBuilder
    {
        foreach ($headerKeys as $headerKey) {
            $this->encryptHeader($headerKey);
        }

        return $this;
    }

    /**
     * Adds a header to the list of payload fields to encrypt.
     * The key supports dot notation.
     *
     * @param string $payloadKey
     *
     * @return RequestInsuranceBuilder
     */
    public function encryptPayloadField(string $payloadKey): RequestInsuranceBuilder
    {
        return $this->append('encrypted_fields.payload', $payloadKey);
    }

    /**
     * Adds a list of payload fields to encrypt.
     * The keys support dot notation.
     *
     * @param string[] $payloadKeys
     */
    public function encryptPayload(array $payloadKeys): RequestInsuranceBuilder
    {
        foreach ($payloadKeys as $payloadKey) {
            $this->encryptPayloadField($payloadKey);
        }

        return $this;
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
        Arr::set($this->data, $field, $value);

        return $this;
    }

    /**
     * Appends a value to the end of an array
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    protected function append($field, $value): RequestInsuranceBuilder
    {
        if ( ! Arr::has($this->data, $field)) {
            return $this->set($field, [$value]);
        }

        $data = Arr::get($this->data, $field);

        if ( ! is_array($data)) {
            throw new InvalidArgumentException('Cannot append to a non-array index');
        }

        $data[] = $value;
        Arr::set($this->data, $field, $data);

        return $this;
    }
}
