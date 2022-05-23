<?php

namespace Cego\RequestInsurance\AsyncRequests;

use Iterator;
use Exception;
use Traversable;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\Eloquent\Collection;
use Cego\RequestInsurance\Models\RequestInsurance;

/**
 * This is just a simple iterator implementation over the given request insurances,
 * which converts the request insurances into request promises.
 *
 * @implements Iterator<int, RequestInsurance>
 */
class RequestPoolProvider implements Iterator
{
    /** @var Collection<array-key, RequestInsurance> */
    private Collection $requestInsurances;
    private Client $client;
    private int $iteratorKey = 0;

    /**
     * @param Client $client
     * @param Collection $requestInsurances
     */
    public function __construct(Client $client, Collection $requestInsurances)
    {
        $this->client = $client;
        $this->requestInsurances = $requestInsurances->values();
    }

    /**
     * Retrieve an external iterator
     *
     * @throws Exception on failure.
     *
     * @return Traversable<int, PromiseInterface>|PromiseInterface[] An instance of an object implementing <b>Iterator</b> or
     */
    public function getIterator()
    {
        /** @var RequestInsurance $request */
        foreach ($this->requestInsurances as $request) {
            yield $request->id => fn () => $request->toRequestPromise($this->client);
        }
    }

    /**
     * Return the current element
     *
     * @return RequestInsurance Can return any type.
     */
    public function current(): RequestInsurance
    {
        return $this->requestInsurances[$this->iteratorKey];
    }

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next(): void
    {
        $this->iteratorKey++;
    }

    /**
     * Return the key of the current element
     *
     * @return int|null TKey on success, or null on failure.
     */
    public function key(): ?int
    {
        return $this->current()->id;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return $this->requestInsurances->has($this->iteratorKey);
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return void Any returned value is ignored.
     */
    public function rewind(): void
    {
        $this->iteratorKey = 0;
    }
}
