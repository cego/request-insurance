<?php

namespace Tests\Unit;

use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection;
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
