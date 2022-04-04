<?php

namespace Cego\RequestInsurance\Contracts;

use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\HttpResponse;
use Cego\RequestInsurance\Exceptions\MethodNotAllowedForRequestInsurance;

abstract class HttpRequest
{
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
        $instance = (new $class);

        $instance->setOption(CURLOPT_USERAGENT, 'RequestInsurance CurlRequest')
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->setOption(CURLOPT_FOLLOWLOCATION, true)
            ->setOption(CURLOPT_TCP_KEEPALIVE, Config::get('request-insurance.keepAlive', true))
            ->setTimeoutMs(Config::get('request-insurance.timeoutInSeconds', 5) * 1000);

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
        return $this->setOption(CURLOPT_URL, $url);
    }

    /**
     * Sets the timeout in ms
     *
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeoutMs(int $timeout)
    {
        if ($timeout < 1000) {
            /**
             * There is a bug with the CURLOPT_TIMEOUT_MS variant, if the timeout is less than a second.
             * To get around this, we need to set the CURLOPT_NOSIGNAL to 1.
             *
             * See the link below for an explanation.
             *
             * @url https://www.php.net/manual/en/function.curl-setopt.php#104597
             */
            $this->setOption(CURLOPT_NOSIGNAL, 1);
        }

        return $this->setOption(CURLOPT_TIMEOUT_MS, $timeout);
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

        if ( ! in_array($method, $allowedMethods)) {
            throw new MethodNotAllowedForRequestInsurance;
        }

        return $this->setOption(CURLOPT_CUSTOMREQUEST, mb_strtoupper($method));
    }

    /**
     * Sets the headers for the request
     *
     * @param string|array $headers
     *
     * @return $this
     */
    public function setHeaders($headers)
    {
        // If $headers is already an array we assume it is correct and pass it directly on
        if (is_array($headers)) {
            return $this->setOption(CURLOPT_HTTPHEADER, $headers);
        }

        // Otherwise it is a string and we assume it is json.
        // An exception is thrown if we cannot decode the string
        $headers = collect(json_decode($headers, true, JSON_THROW_ON_ERROR))
            ->map(function ($value, $header) {
                return sprintf('%s: %s', $header, $value);
            })
            ->flatten()
            ->toArray();

        return $this->setOption(CURLOPT_HTTPHEADER, $headers);
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
        return $this->setOption(CURLOPT_POSTFIELDS, $payload);
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
     * Sets an option
     *
     * @param int $option
     * @param mixed $value
     *
     * @return $this
     */
    abstract public function setOption($option, $value);

    /**
     * Gets information about the request
     *
     * @return mixed
     */
    abstract public function getInfo();

    /**
     * Gets the error number if set
     *
     * @return int
     */
    abstract public function getErrorNumber();

    /**
     * Gets the error message if set
     *
     * @return string
     */
    abstract public function getError();

    /**
     * Executes the request
     *
     * @return string|bool
     */
    abstract public function getResponse();

    /**
     * Closes the resource
     *
     * @return $this
     */
    abstract public function close();
}
