<?php

namespace Cego\RequestInsurance\AsyncRequests;

use Generator;
use JsonException;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use OpenTelemetry\Context\Context;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection;
use Cego\RequestInsurance\Models\RequestInsurance;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

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
        $headers = $requestInsurance->getHeadersCastToArray();

        $send = fn () => $client->requestAsync(mb_strtoupper($requestInsurance->method), $requestInsurance->url, [
            'headers'     => array_merge($headers, ['User-Agent' => sprintf('RequestInsurance %s', Config::get('app.name', 'unknown'))]),
            'body'        => $requestInsurance->payload,
            'timeout'     => $requestInsurance->getEffectiveTimeout(),
            'on_stats'    => fn (TransferStats $stats) => $requestInsurance->setTimings($stats),
            'http_errors' => false,
        ]);

        return $this->withTraceContextOf($headers, $send);
    }

    /**
     * Runs the given callback with the trace context stored on the request insurance as the active context
     *
     * Auto-instrumentation strips the stored traceparent off the outgoing request and re-injects
     * one built from the active context. Without this, every request in a chunk is sent under the
     * worker's own span instead of under the trace that created the row.
     *
     * @param array<string, string> $headers
     * @param callable(): PromiseInterface $callback
     *
     * @return PromiseInterface
     */
    private function withTraceContextOf(array $headers, callable $callback): PromiseInterface
    {
        if ( ! class_exists(TraceContextPropagator::class)) {
            return $callback();
        }

        $scope = Context::storage()->attach(TraceContextPropagator::getInstance()->extract($headers));

        try {
            return $callback();
        } finally {
            $scope->detach();
        }
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
