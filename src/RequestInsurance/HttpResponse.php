<?php

namespace Cego\RequestInsurance;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class HttpResponse
{
    protected Response $response;
    protected ConnectException $connectException;
    protected RequestException $requestException;

    /**
     * @param Response|ConnectException|RequestException $response
     */
    public function __construct($response)
    {
        if ($response instanceof ConnectException) {
            $this->connectException = $response;
        } elseif ($response instanceof RequestException) {
            $this->requestException = $response;
        } elseif ($response !== null) {
            $this->response = $response;
        }
    }

    /**
     * Returns true if the request is in an inconsistent state,
     * caused by any reason which left us without any real response.
     *
     * @return bool
     */
    public function isInconsistent(): bool
    {
        return ! isset($this->response);
    }

    /**
     * Returns true if the request has content-type image
     *
     * @return bool
     */
    public function isImageResponse(): bool
    {
        return collect($this->getHeaders()
            ->keyBy(fn ($value, $key) => strtolower($key))
            ->get('content-type', []))
            ->filter(fn ($type) => str_starts_with($type, 'image'))
            ->isNotEmpty();
    }

    /**
     * Returns true when the request timed out
     *
     * @return bool
     */
    public function isTimedOut(): bool
    {
        return isset($this->connectException);
    }

    /**
     * Returns true when the request failed due to a Guzzle RequestException.
     * The RequestException is a generalization of some other Guzzle exceptions,
     * specifically BadResponseException, TooManyRedirectsException.
     *
     * @return bool
     */
    public function isRequestException(): bool
    {
        return isset($this->requestException);
    }

    /**
     * Logs the reason for the inconsistent state if the response is in an inconsistent state
     *
     * @return void
     */
    public function logInconsistentReason(): void
    {
        if ( ! $this->isInconsistent()) {
            return;
        }

        if ($this->isTimedOut()) {
            Log::error($this->connectException);

            return;
        }

        if ($this->isRequestException()) {
            Log::error($this->requestException);

            return;
        }

        Log::error('No response object, connect exception nor request exception was received for request');
    }

    /**
     * Tells if the request was successful
     *
     * @return bool
     */
    public function wasSuccessful()
    {
        if ($this->isInconsistent()) {
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
        if ($this->isTimedOut()) {
            return 0;
        }

        if ($this->isInconsistent()) {
            return -1;
        }

        return $this->response->getStatusCode();
    }

    /**
     * Gets the body of the response
     *
     * @return string|null
     */
    public function getBody(): ?string
    {
        if ($this->isTimedOut()) {
            return '<REQUEST_TIMED_OUT : THIS MESSAGE WAS ADDED BY REQUEST INSURANCE>';
        }

        if ($this->isRequestException()) {
            return '<REQUEST_EXCEPTION : THIS MESSAGE WAS ADDED BY REQUEST INSURANCE>';
        }

        if ($this->isInconsistent()) {
            return '<REQUEST_INCONSISTENT : THIS MESSAGE WAS ADDED BY REQUEST INSURANCE>';
        }

        if ($this->isImageResponse()) {
            return '<REQUEST_IMAGE_GIF_RESPONSE : THIS MESSAGE WAS ADDED BY REQUEST INSURANCE>';
        }

        return $this->response->getBody()->getContents();
    }

    /**
     * @return Collection
     */
    public function getHeaders(): Collection
    {
        if ($this->isInconsistent()) {
            return new Collection();
        }

        return collect($this->response->getHeaders());
    }

    /**
     * Gets the execution time of the request
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        if ($this->isInconsistent()) {
            return -1;
        }

        return $this->response->getHandlerContext()['total_time'] ?? 0;
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
        if ($this->isInconsistent()) {
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
        if ($this->isInconsistent()) {
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
