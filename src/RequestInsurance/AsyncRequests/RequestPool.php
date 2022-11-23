<?php

namespace Cego\RequestInsurance\AsyncRequests;

use Cego\RequestInsurance\Models\RequestInsuranceLog;
use Generator;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Log;
use JsonException;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection;
use Cego\RequestInsurance\Models\RequestInsurance;
use Symfony\Component\HttpKernel\Controller\TraceableArgumentResolver;

class RequestPool
{
    private Pool $guzzlePool;
    private RequestPoolResponses $requestPoolResponses;

    /**
     * Constructor
     *
     * @param Client $client
     * @param Collection $requestInsurances
     */
    public function __construct(Client $client, Collection $requestInsurances)
    {
        $this->guzzlePool = $this->createPool($client, $requestInsurances);
        $this->requestPoolResponses = new RequestPoolResponses();
    }

    /**
     * Creates a guzzle request pool
     *
     * @param Client $client
     * @param Collection $requestInsurances
     *
     * @return Pool
     */
    private function createPool(Client $client, Collection $requestInsurances): Pool
    {
        $responseHandler = fn ($response, $requestId) => $this->addResponse($requestId, $response);

        return new Pool($client, $this->requestProvider($client, $requestInsurances), [
            'concurrency' => $requestInsurances->count(),
            'fulfilled'   => $responseHandler,
            'rejected'    => $responseHandler,
        ]);
    }

    /**
     * Provides the Request Promise interface required by the Guzzle pool
     *
     * @param Client $client
     * @param Collection $requestInsurances
     *
     * @return Generator
     */
    protected function requestProvider(Client $client, Collection $requestInsurances): Generator
    {
        /** @var RequestInsurance $request */
        foreach ($requestInsurances as $request) {
            yield $request->id => fn () => $this->convertRequestToPromise($client, $request);
        }
    }

    /**
     * Converts the given request insurance into an async request promise
     *
     * @param Client $client
     * @param RequestInsurance $requestInsurance
     *
     * @throws JsonException
     *
     * @return PromiseInterface
     */
    private function convertRequestToPromise(Client $client, RequestInsurance $requestInsurance): PromiseInterface
    {
        return $client->requestAsync($requestInsurance->method, $requestInsurance->url, [
            'headers'     => array_merge($requestInsurance->getHeadersCastToArray(), ['User-Agent' => 'RequestInsurance']),
            'body'        => $requestInsurance->payload,
            'timeout'     => $requestInsurance->getEffectiveTimeout(),
            'on_stats'    => function (TransferStats $stats) use($requestInsurance) {
                $requestInsurance->timings = json_encode($stats->getHandlerStats()) ?? "nostats";

                if ($requestInsurance->timings == "") {
                    $requestInsurance->timings = "failed";
                }
            },
            'http_errors' => false,
        ]);
    }

    /**
     * Adds the given response to the response pool
     *
     * @param int $requestInsuranceId
     * @param $response
     *
     * @return void
     */
    private function addResponse(int $requestInsuranceId, $response): void
    {
        $this->requestPoolResponses->put($requestInsuranceId, $response);
    }

    /**
     * Returns the responses for the requests within the pool
     *
     * @return RequestPoolResponses
     */
    public function getResponses(): RequestPoolResponses
    {
        // Create the actual promise, and await the responses
        $this->guzzlePool
            ->promise()
            ->wait(false);

        // Return the responses
        return $this->requestPoolResponses;
    }
}
