<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;

class RequestInsuranceTest extends TestCase
{
    /** @test */
    public function it_can_create_a_request_insurance(): void
    {
        // Arrange

        // Act
        RequestInsurance::create([
            'payload' => ['data' => [1, 2, 3]],
            'headers' => ['Content-Type' => 'application/json'],
            'method'  => 'POST',
            'url'     => 'https://MyDev.lupinsdev.dk',
        ]);

        // Assert
        $this->assertCount(1, RequestInsurance::all());
    }

    /** @test */
    public function it_does_not_allow_empty_method(): void
    {
        // Assert
        $this->expectException(EmptyPropertyException::class);

        // Act
        RequestInsurance::create([
            'payload' => ['data' => [1, 2, 3]],
            'headers' => ['Content-Type' => 'application/json'],
            'method'  => '',
            'url'     => 'https://MyDev.lupinsdev.dk',
        ]);
    }

    /** @test */
    public function it_does_not_allow_empty_url(): void
    {
        // Assert
        $this->expectException(EmptyPropertyException::class);

        // Act
        RequestInsurance::create([
            'payload' => ['data' => [1, 2, 3]],
            'headers' => ['Content-Type' => 'application/json'],
            'method'  => 'POST',
            'url'     => '',
        ]);
    }

    /** @test */
    public function it_can_convert_arrays_to_json(): void
    {
        // Arrange

        // Act
        RequestInsurance::create([
            'payload'  => ['data' => [1, 2, 3]],
            'headers'  => ['Content-Type' => 'application/json'],
            'method'   => 'POST',
            'url'      => 'https://MyDev.lupinsdev.dk',
            'trace_id' => '123',
        ]);

        // Assert
        $this->assertCount(1, RequestInsurance::all());

        /** @var RequestInsurance $requestInsurance */
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(json_encode(['data' => [1, 2, 3]], JSON_THROW_ON_ERROR), $requestInsurance->payload);
        $this->assertEquals(json_encode(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123', 'X-Sensitive-Request-Headers-JSON' => json_encode(['Authorization', 'authorization'])], JSON_THROW_ON_ERROR), $requestInsurance->headers);
    }

    /** @test */
    public function it_can_mask_encrypted_headers(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'headers' => ['x-test'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->headers(['Content-Type' => 'application/json', 'x-test' => 'abc123'])
            ->create();

        // Assert
        $maskedHeaders = $requestInsurance->getHeadersWithMaskingApplied();

        $this->assertStringNotContainsString('abc123', $maskedHeaders);
        $this->assertStringContainsString('[ ENCRYPTED ]', $maskedHeaders);
        $this->assertStringContainsString('application\/json', $maskedHeaders);
    }

    /** @test */
    public function it_can_mask_encrypted_payload(): void
    {
        // Arrange
        Config::set('request-insurance.fieldsToAutoEncrypt', [
            'payload' => ['Key1'],
        ]);

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload(['Key1' => 'Value1', 'Key2' => 'Value2'])
            ->create();

        // Assert
        $maskedPayload = $requestInsurance->getPayloadWithMaskingApplied();

        $this->assertStringNotContainsString('Value1', $maskedPayload);
        $this->assertStringContainsString('[ ENCRYPTED ]', $maskedPayload);
        $this->assertStringContainsString('Value2', $maskedPayload);
    }

    /** @test */
    public function it_can_get_payload_when_it_is_not_an_array(): void
    {
        // Arrange

        // Act
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://MyDev.lupinsdev.dk')
            ->method('POST')
            ->payload('The payload is not an array json_encoded string.')
            ->create();

        // Assert
        $payload = $requestInsurance->getPayloadWithMaskingApplied();

        $this->assertStringContainsString('The payload is not an array json_encoded string.', $payload);
    }

    /** @test */
    public function it_always_increment_the_tries_count(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        // Act & Assert
        $this->assertEquals(0, $requestInsurance->retry_count);

        $this->runWorkerOnce();
        $this->assertEquals(1, $requestInsurance->refresh()->retry_count);

        $requestInsurance->update(['state' => State::READY]);
        $this->runWorkerOnce();

        $this->assertEquals(2, $requestInsurance->refresh()->retry_count);
    }

    /** @test */
    public function it_can_resume_request_insurances(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->updateOrFail(['state' => State::FAILED]);
        $requestInsurance->refresh();

        // Act & Assert
        $requestInsurance->retryNow();

        $this->assertTrue($requestInsurance->hasState(State::READY));
        $this->assertNull($requestInsurance->retry_at);
    }

    /** @test */
    public function it_is_not_retryable_if_completed(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        $requestInsurance->update(['state' => State::COMPLETED]);

        // Assert
        $this->assertFalse($requestInsurance->isRetryable());
    }

    /** @test */
    public function it_is_retryable_if_not_completed_but_failed(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        $requestInsurance->update(['state' => State::FAILED]);

        // Assert
        $this->assertTrue($requestInsurance->isRetryable());
    }

    /** @test */
    public function it_is_retryable_if_not_completed_but_abandoned(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        $requestInsurance->updateOrFail(['state' => State::ABANDONED]);

        // Assert
        $this->assertTrue($requestInsurance->isRetryable());
    }
}
