<?php

namespace Cego\RequestInsurance\AsyncRequests;

use Illuminate\Http\Client\Response;
use Cego\RequestInsurance\HttpResponse;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestPoolResponses
{
    /** @var array<Response> */
    private array $responses;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->responses = [];
    }

    /**
     * Inserts the response for the given request insurance id
     *
     * @param int $key
     * @param \GuzzleHttp\Psr7\Response|ConnectException|RequestException $response
     *
     * @return void
     */
    public function put(int $key, $response): void
    {
        if (isset($this->responses[$key])) {
            throw new \InvalidArgumentException("Cannot override response for request insurance id [$key]");
        }

        $this->responses[$key] = $response;
    }

    /**
     * Returns the response for the given request insurance if it exists or null.
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return HttpResponse
     */
    public function get(RequestInsurance $requestInsurance): HttpResponse
    {
        return new HttpResponse($this->responses[$requestInsurance->id] ?? null);
    }
}
