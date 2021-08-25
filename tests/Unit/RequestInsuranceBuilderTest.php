<?php

namespace Tests\Unit;

use Tests\TestCase;
use InvalidArgumentException;
use Illuminate\Support\Facades\Event;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;

class RequestInsuranceBuilderTest extends TestCase
{
    /** @test */
    public function it_can_create_a_request_insurance(): void
    {
        // Arrange

        // Act
        RequestInsurance::getBuilder()
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->headers(['Content-Type' => 'application/json'])
            ->payload(['data' => [1, 2, 3]])
            ->traceId('123')
            ->create();

        // Assert
        $this->assertCount(1, RequestInsurance::all());
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(['data' => [1, 2, 3]], json_decode($requestInsurance->payload, true, 512, JSON_THROW_ON_ERROR));
        $this->assertEquals(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123'], json_decode($requestInsurance->headers, true, 512, JSON_THROW_ON_ERROR));
        $this->assertEquals('POST', $requestInsurance->method);
        $this->assertEquals('https://MyDev.lupinsdev.dk', $requestInsurance->url);
    }

    /** @test */
    public function it_does_not_allow_empty_method(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        RequestInsurance::getBuilder()
            ->payload(['data' => [1, 2, 3]])
            ->headers(['Content-Type' => 'application/json'])
            ->method('')
            ->url('https://MyDev.lupinsdev.dk')
            ->create();
    }

    /** @test */
    public function it_does_not_allow_empty_url(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        RequestInsurance::getBuilder()
            ->payload(['data' => [1, 2, 3]])
            ->headers(['Content-Type' => 'application/json'])
            ->method('POST')
            ->url('')
            ->create();
    }

    /** @test */
    public function it_can_convert_arrays_to_json(): void
    {
        // Arrange

        // Act
        RequestInsurance::getBuilder()
            ->payload(['data' => [1, 2, 3]])
            ->headers(['Content-Type' => 'application/json'])
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->traceId('123')
            ->create();

        // Assert
        $this->assertCount(1, RequestInsurance::all());

        /** @var RequestInsurance $requestInsurance */
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(json_encode(['data' => [1, 2, 3]], JSON_THROW_ON_ERROR), $requestInsurance->payload);
        $this->assertEquals(json_encode(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123'], JSON_THROW_ON_ERROR), $requestInsurance->headers);
    }

    /** @test */
    public function it_can_build_with_a_encrypted_header(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json'])
            ->encryptHeader('Content-Type')
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
    }

    /** @test */
    public function it_only_encrypts_the_requested_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'X-test' => 'CEGO'])
            ->encryptHeader('Content-Type')
            ->create();

        // Assert

        // Make sure it is not encrypted after creation
        $this->assertEquals('CEGO', $requestInsurance->getHeadersCastToArray()['X-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Make sure it is not encrypted in DB
        $this->assertEquals('CEGO', $requestInsurance->getHeadersCastToArray()['X-test']);
    }

    /** @test */
    public function it_can_use_dot_notation_for_encrypted_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => ['a' => 1, 'b' => 2]])
            ->encryptHeader('x-test.a')
            ->create();

        // Assert


        // An RI should be left decryoted after creation
        $this->assertEquals(1, $requestInsurance->getHeadersCastToArray()['x-test']['a']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals(1, $requestInsurance->getHeadersCastToArray()['x-test']['a']);
    }

    /** @test */
    public function it_can_encrypt_arrays(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => ['a' => 1, 'b' => 2]])
            ->encryptHeader('x-test')
            ->create();

        // Assert


        // An RI should be left decryoted after creation
        $this->assertEquals(['a' => 1, 'b' => 2], $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals(['a' => 1, 'b' => 2], $requestInsurance->getHeadersCastToArray()['x-test']);
        $this->assertIsString($requestInsurance->getHeadersCastToArray()['x-test']);
    }

    /** @test */
    public function it_can_build_with_multiple_encrypted_headers(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeaders(['Content-Type', 'x-test'])
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Disable events, so that we can assert that the header was encrypted
        // since it is auto decrypted by model events
        Event::fake();

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertNotEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertNotEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);
    }

    /** @test */
    public function it_can_auto_decrypt_after_resolving_from_db(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc'])
            ->encryptHeaders(['Content-Type', 'x-test'])
            ->create();

        // Assert

        // An RI should be left decryoted after creation
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);

        $this->assertCount(1, RequestInsurance::all());

        // Extract it RAW from the DB (Since Events are disabled)
        $requestInsurance = RequestInsurance::first();

        // Assert that it is encrypted in DB
        $this->assertEquals('application/json', $requestInsurance->getHeadersCastToArray()['Content-Type']);
        $this->assertEquals('abc', $requestInsurance->getHeadersCastToArray()['x-test']);
    }
}