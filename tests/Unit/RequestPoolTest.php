<?php

namespace Tests\Unit;

use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection;
use OpenTelemetry\API\Trace\SpanContextInterface;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\AsyncRequests\RequestPool;

class RequestPoolTest extends TestCase
{
    public function test_it_uppercases_a_lowercase_method(): void
    {
        $this->assertMethodGivenToClient('post', 'POST');
    }

    public function test_it_leaves_an_uppercase_method_untouched(): void
    {
        $this->assertMethodGivenToClient('PUT', 'PUT');
    }

    public function test_it_uppercases_a_mixed_case_method(): void
    {
        $this->assertMethodGivenToClient('DeLeTe', 'DELETE');
    }

    public function test_it_normalizes_header_values_before_sending(): void
    {
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('POST')
            ->headers([
                'X-Integer'  => 123,
                'X-Multiple' => [456, 'value'],
                'X-Empty'    => [],
            ])
            ->create();

        $client = new class () extends Client {
            public array $headers = [];

            public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
            {
                $this->headers = $options['headers'];

                return new FulfilledPromise(new Response(200));
            }
        };

        (new RequestPool($client, new Collection([$requestInsurance])))->getResponses();

        $this->assertSame('123', $client->headers['X-Integer']);
        $this->assertSame(['456', 'value'], $client->headers['X-Multiple']);
        $this->assertSame('', $client->headers['X-Empty']);
    }

    public function test_it_sends_a_request_under_the_trace_context_stored_on_it(): void
    {
        // Arrange
        $traceId = '0af7651916cd43dd8448eb211c80319c';
        $spanId = 'b7ad6b7169203331';

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('POST')
            ->headers(['traceparent' => sprintf('00-%s-%s-01', $traceId, $spanId)])
            ->create();

        // Act
        $spanContext = $this->captureActiveSpanContextWhileSending($requestInsurance);

        // Assert
        $this->assertSame($traceId, $spanContext->getTraceId());
        $this->assertSame($spanId, $spanContext->getSpanId());
    }

    public function test_it_leaves_the_active_context_alone_for_a_request_without_a_trace_context(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('POST')
            ->create();

        // Act
        $spanContext = $this->captureActiveSpanContextWhileSending($requestInsurance);

        // Assert
        $this->assertFalse($spanContext->isValid());
    }

    /**
     * Returns the span context that was active while the pool handed the request to the Guzzle client
     *
     * @param RequestInsurance $requestInsurance
     *
     * @return SpanContextInterface
     */
    protected function captureActiveSpanContextWhileSending(RequestInsurance $requestInsurance): SpanContextInterface
    {
        $client = new class () extends Client {
            public ?SpanContextInterface $spanContext = null;

            public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
            {
                $this->spanContext = Span::fromContext(Context::getCurrent())->getContext();

                return new FulfilledPromise(new Response(200));
            }
        };

        (new RequestPool($client, new Collection([$requestInsurance])))->getResponses();

        $this->assertNotNull($client->spanContext, 'the client was never handed a request');

        return $client->spanContext;
    }

    /**
     * Asserts which HTTP method the pool hands to the Guzzle client for a stored method
     *
     * Guzzle 7 still uppercases the method itself, so this asserts on the client
     * argument rather than on the outgoing request.
     *
     * @param string $storedMethod
     * @param string $expectedMethod
     *
     * @return void
     */
    protected function assertMethodGivenToClient(string $storedMethod, string $expectedMethod): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method($storedMethod)
            ->create();

        $client = new class () extends Client {
            /** @var string[] */
            public array $methods = [];

            public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
            {
                $this->methods[] = $method;

                return new FulfilledPromise(new Response(200));
            }
        };

        // Act
        (new RequestPool($client, new Collection([$requestInsurance])))->getResponses();

        // Assert
        $this->assertSame([$expectedMethod], $client->methods);
    }
}
