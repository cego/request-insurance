<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;

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
        $this->assertEquals(json_encode(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123'], JSON_THROW_ON_ERROR), $requestInsurance->headers);
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
        $maskedHeaders = $requestInsurance->getPayloadWithMaskingApplied();

        $this->assertStringNotContainsString('Value1', $maskedHeaders);
        $this->assertStringContainsString('[ ENCRYPTED ]', $maskedHeaders);
        $this->assertStringContainsString('Value2', $maskedHeaders);
    }

    /** @test */
    public function it_always_increment_the_tries_count(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        // Act & Assert
        $this->assertEquals(0, $requestInsurance->retry_count);

        $requestInsurance->process();
        $this->assertEquals(1, $requestInsurance->retry_count);

        $requestInsurance->update(['completed_at' => null]);
        $requestInsurance->process();

        $this->assertEquals(2, $requestInsurance->retry_count);
    }

    /** @test */
    public function it_can_resume_request_insurances(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->update(['paused_at' => Carbon::now(), 'abandoned_at' => Carbon::now()]);
        $requestInsurance->refresh();

        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNotNull($requestInsurance->abandoned_at);

        // Act & Assert
        $requestInsurance->resume();

        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);
    }

    /** @test */
    public function it_can_retry_request_insurances(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->update(['paused_at' => Carbon::now(), 'abandoned_at' => Carbon::now(), 'completed_at' => Carbon::now()]);
        $requestInsurance->refresh();

        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNotNull($requestInsurance->completed_at);
        $this->assertNotNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->retry_at);
        $this->assertNull($requestInsurance->locked_at);

        // Act & Assert
        $requestInsurance->retry();

        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNotNull($requestInsurance->retry_at);
        $this->assertNull($requestInsurance->locked_at);
    }

    /** @test */
    public function it_uses_exponential_backoff(): void
    {
        // Arrange
        $now = Carbon::now()->startOfSecond();

        $this->travelTo($now); // Freeze time

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->locked_at);

        // Act & Assert
        $requestInsurance->retry_count = 0;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(1));

        $requestInsurance->retry_count = 1;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(2));

        $requestInsurance->retry_count = 2;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(4));

        $requestInsurance->retry_count = 3;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(8));

        $requestInsurance->retry_count = 4;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(16));

        $requestInsurance->retry_count = 5;
        $requestInsurance->retry();
        $this->assertEquals($requestInsurance->retry_at, $now->addSeconds(32));
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
        $requestInsurance->update(['completed_at' => Carbon::now()]);

        // Assert
        $this->assertFalse($requestInsurance->isRetryable());
    }

    /** @test */
    public function it_is_retryable_if_not_completed_but_paused(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        $requestInsurance->update(['completed_at' => null, 'paused_at' => Carbon::now()]);

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
        $requestInsurance->update(['completed_at' => null, 'abandoned_at' => Carbon::now()]);

        // Assert
        $this->assertTrue($requestInsurance->isRetryable());
    }
}
