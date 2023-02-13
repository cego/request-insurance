<?php

namespace Cego\RequestInsurance\Contracts;

use JsonException;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\HttpResponse;
use Cego\RequestInsurance\Exceptions\MethodNotAllowedForRequestInsurance;

abstract class HttpRequest
{
    protected string $userAgent = 'RequestInsurance';
    protected array $headers = [];
    protected int $timeout;
    protected string $url;
    protected string $method;
    protected string $payload;

    /**
     * Protected constructor to force use of named constructor
     */
    protected function __construct()
    {
    }

    /**
     * Named construct for HttpRequest
     *
     * @return static
     */
    public static function create(): self
    {
        $class = app()->get(HttpRequest::class);

        /** @var HttpRequest $instance */
        $instance = (new $class());

        $instance->timeout = Config::get('request-insurance.timeoutInSeconds', 5);

        return $instance;
    }

    /**
     * Sets the url for the request
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Sets the timeout in seconds
     *
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Sets the method of the request
     *
     * @param string $method
     *
     * @throws MethodNotAllowedForRequestInsurance
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $method = mb_strtoupper($method);

        $allowedMethods = [
            'GET', 'HEAD', 'POST', 'DELETE', 'PUT', 'PATCH',
        ];

        if ( ! in_array($method, $allowedMethods, false)) {
            throw new MethodNotAllowedForRequestInsurance($method);
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Sets the headers for the request
     *
     * @param string|array $headers
     *
     * @throws JsonException
     *
     * @return $this
     */
    public function setHeaders($headers)
    {
        if ( ! is_array($headers)) {
            $headers = json_decode($headers, true, 512, JSON_THROW_ON_ERROR);
        }

        $this->headers = $headers;

        return $this;
    }

    /**
     * Sets the payload for the request
     *
     * @param string $payload
     *
     * @return $this
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Sends the request and gets a response
     *
     * @return HttpResponse
     */
    public function send()
    {
        return HttpResponse::create($this);
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }
}
