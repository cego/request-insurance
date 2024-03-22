<?php

use Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Cego\RequestInsurance\HttpResponse;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TooManyRedirectsException;

class HttpResponseTest extends TestCase
{
    public function test_it_can_initialize_with_request_exception()
    {
        $httpResponse = new HttpResponse(new RequestException('F', new Request('GET', 'www.notaplaceforsho.dk')));

        $this->assertTrue($httpResponse->isRequestException());
    }

    public function test_it_can_initialize_with_bad_response_exception()
    {
        $httpResponse = new HttpResponse(new BadResponseException('F', new Request('GET', 'www.notaplaceforsho.dk'), new Response()));

        $this->assertTrue($httpResponse->isRequestException());
    }

    public function test_it_can_initialize_with_too_many_redirects_exception()
    {
        $httpResponse = new HttpResponse(new TooManyRedirectsException('F', new Request('GET', 'www.notaplaceforsho.dk')));

        $this->assertTrue($httpResponse->isRequestException());
    }
}
