<?php

namespace Tests\Unit;

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
}