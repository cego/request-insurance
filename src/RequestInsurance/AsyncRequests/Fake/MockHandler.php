<?php

namespace Cego\RequestInsurance\AsyncRequests\Fake;

use Closure;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Handler\MockHandler as GuzzleMockHandler;

class MockHandler
{
    /**
     * @var Closure|Response[]
     */
    private $responses;

    private GuzzleMockHandler $handler;

    public function __construct($responses)
    {
        $this->responses = $responses;
        $this->handler = new GuzzleMockHandler(is_callable($responses) ? [] : $responses);
    }

    /**
     * Magic invoke
     *
     * @param RequestInterface $request
     * @param array $options
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        if (is_callable($this->responses)) {
            $this->handler->append(call_user_func($this->responses));
        }

        return ($this->handler)($request, $options);
    }
}
