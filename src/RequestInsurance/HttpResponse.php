<?php

namespace Cego\RequestInsurance;

use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Exception\ConnectException;

class HttpResponse
{
    protected Response $response;
    protected ConnectException $connectException;

    /**
     * @param Response|ConnectException $response
     */
    public function __construct($response)
    {
        if ($response instanceof ConnectException) {
            $this->connectException = $response;
        } else {
            $this->response = $response;
        }
    }

    public function timedOut(): bool
    {
        return isset($this->connectException);
    }

    /**
     * Tells if the request was successful
     *
     * @return bool
     */
    public function wasSuccessful()
    {
        if ($this->timedOut()) {
            return false;
        }

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
    public function getCode(): int
    {
        if ($this->timedOut()) {
            return 0;
        }

        return $this->response->status();
    }

    /**
     * Gets the body of the response
     *
     * @return string
     */
    public function getBody(): string
    {
        if ($this->timedOut()) {
            throw new \RuntimeException('No body for timed out request');
        }

        return $this->response->body();
    }

    /**
     * @return Collection
     */
    public function getHeaders(): Collection
    {
        if ($this->timedOut()) {
            throw new \RuntimeException('No headers for timed out request');
        }

        return collect($this->response->headers());
    }

    /**
     * Gets the execution tim of the request
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        if ($this->timedOut()) {
            return -1;
        }

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
        if ($this->timedOut()) {
            return false;
        }

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
        if ($this->timedOut()) {
            return false;
        }

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
