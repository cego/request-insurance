<?php

namespace Cego\RequestInsurance;

use Illuminate\Support\Collection;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\Contracts\ContainsResponseHeaders;

class HttpResponse
{
    /**
     * Holds the request associated with the response
     *
     * @var HttpResponse $request
     */
    protected $request = null;

    /**
     * Holds the body of the response
     *
     * @var bool|string $body
     */
    protected $body;

    /**
     * Holds the error message of the response if present
     *
     * @var string $errorMessage
     */
    protected $errorMessage;

    /**
     * Holds a collection of response information
     *
     * @var \Illuminate\Support\Collection $info
     */
    protected $info;

    /**
     * Holds all the headers of the response if set
     *
     * @var \Illuminate\Support\Collection $responseHeaders
     */
    protected $responseHeaders;

    /**
     * Protected constructor to force use of named constructor
     */
    protected function __construct()
    {
        $this->info = new Collection;
        $this->responseHeaders = new Collection;
    }

    /**
     * Named constructor for creating a new response instance
     *
     * @param HttpRequest $request
     *
     * @return HttpResponse
     */
    public static function create(HttpRequest $request)
    {
        return (new static)
            ->setRequest($request);
    }

    /**
     * Sets the request that produced the response
     *
     * @param HttpRequest $request
     *
     * @return $this
     */
    public function setRequest(HttpRequest $request)
    {
        $this->request = $request;

        $this->body = $request->getResponse();
        $this->errorMessage = $request->getError();
        $this->info = collect($request->getInfo());

        if ($request instanceof ContainsResponseHeaders) {
            $this->responseHeaders = collect($request->getResponseHeaders());
        }

        $request->close();

        return $this;
    }

    /**
     * Tells if the request was successful
     *
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->getCode() == 200;
    }

    /**
     * Syntactic sugar for negation waSuccessful()
     *
     * @return bool
     */
    public function wasNotSuccessful()
    {
        return ! $this->wasSuccessful();
    }

    /**
     * Tells if the request should not be retried
     *
     * @return bool
     */
    public function isNotRetryable()
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    /**
     * Syntactic sugar for negating isNotRetryable()
     *
     * @return bool
     */
    public function isRetryable()
    {
        return ! $this->isNotRetryable();
    }

    /**
     * Gets the response code of the request performed
     *
     * @return int
     */
    public function getCode()
    {
        return (int) $this->info->get('http_code');
    }

    /**
     * Gets the body of the response
     *
     * @return string
     */
    public function getBody()
    {
        return (string) $this->body;
    }

    /**
     * @return Collection
     */
    public function getHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * Gets the execution tim of the request
     *
     * @return float
     */
    public function getExecutionTime()
    {
        return (float) $this->info->get('total_time');
    }
}
