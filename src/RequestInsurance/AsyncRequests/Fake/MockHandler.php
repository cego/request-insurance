<?php

namespace Cego\RequestInsurance\AsyncRequests\Fake;

use Closure;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Handler\MockHandler as GuzzleMockHandler;

class MockHandler extends GuzzleMockHandler
{
    /**
     * @var Closure|Response[]
     */
    private $responses;

    public function __construct($responses)
    {
        parent::__construct(is_callable($responses) ? [] : $responses);

        $this->responses = $responses;
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
            $this->append(call_user_func($this->responses));
        }

        return parent::__invoke($request, $options);
    }
}
