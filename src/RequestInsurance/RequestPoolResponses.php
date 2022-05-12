<?php

namespace Cego\RequestInsurance;

use Illuminate\Http\Client\Response;
use GuzzleHttp\Exception\ConnectException;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestPoolResponses
{
    /** @var array<Response> */
    private array $responses;

    /**
     * @param array<Response> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /**
     * Returns the response for the given request insurance if it exists or null.
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return Response|ConnectException|null
     */
    public function get(RequestInsurance $requestInsurance)
    {
        $response = $this->responses[$requestInsurance->id] ?? null;

        if ($response === null) {
            return null;
        }

        return new HttpResponse($response);
    }
}
