<?php

namespace Cego\RequestInsurance\AsyncRequests;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Database\Eloquent\Collection;
use Cego\RequestInsurance\AsyncRequests\Fake\MockHandler;

class RequestInsuranceClient
{
    private Client $guzzle;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->guzzle = new Client([
            'http_errors' => false,
        ]);
    }

    /**
     * Pools the given request insurances, and sends the concurrently
     *
     * @param Collection $requestInsurances
     *
     * @return RequestPoolResponses
     */
    public function pool(Collection $requestInsurances): RequestPoolResponses
    {
        return (new RequestPool($this->guzzle, $requestInsurances))->getResponses();
    }

    /**
     * Fakes the client
     *
     * @param array|Closure $responses
     *
     * @return void
     */
    public static function fake($responses): void
    {
        $fakedClient = new RequestInsuranceClient();
        $fakedClient->guzzle = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        app()->instance(__CLASS__, $fakedClient);
    }
}
