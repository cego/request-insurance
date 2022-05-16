<?php

namespace Cego\RequestInsurance;

use Illuminate\Http\Client\Response;
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
     * @return HttpResponse
     */
    public function get(RequestInsurance $requestInsurance): HttpResponse
    {
        return new HttpResponse($this->responses[$requestInsurance->id] ?? null);
    }
}
