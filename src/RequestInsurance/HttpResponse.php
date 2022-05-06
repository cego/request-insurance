<?php

namespace Cego\RequestInsurance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\Contracts\ContainsResponseHeaders;

class HttpResponse
{
    protected Response $response;

    /**
     * Holds the body of the response
     *
     * @var bool|string $body
     */
    protected $body;

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
        $this->response = Http::withBody($request->getPayload())
            ->withHeaders($request->getHeaders())
            ->withUserAgent($request->getUserAgent())
            ->timeout()
            ->send($request->getMethod(), $request->getUrl());

        return $this;
    }

    /**
     * Tells if the request was successful
     *
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->isResponseCodeBetween(200, 299);
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
        return $this->isResponseCodeBetween(400, 499);
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
        return $this->response->status();
    }

    /**
     * Gets the body of the response
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->response->body();
    }

    /**
     * @return Collection
     */
    public function getHeaders(): Collection
    {
        return collect($this->response->headers());
    }

    /**
     * Gets the execution tim of the request
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->response->handlerStats()['total_time'] ?? 0;
    }

    /**
     * Returns true if the response was a client error
     *
     * Code range [400;499]
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->isResponseCodeBetween(400, 499);
    }

    /**
     * Returns true if the response was a server error
     *
     * Code range [500;599]
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->isResponseCodeBetween(500, 599);
    }

    /**
     * Checks if the response code is between a given range INCLUSIVE in both ends
     *
     * @param int $start
     * @param int $end
     *
     * @return bool
     */
    protected function isResponseCodeBetween(int $start, int $end): bool
    {
        return $start <= $this->getCode() && $this->getCode() <= $end;
    }
}
