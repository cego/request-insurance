<?php

namespace Tests\Unit;

use Tests\TestCase;
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
            'method' => 'POST',
            'url' => 'https://MyDev.lupinsdev.dk',
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
            'method' => '',
            'url' => 'https://MyDev.lupinsdev.dk',
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
            'method' => 'POST',
            'url' => '',
        ]);
    }

    /** @test */
    public function it_can_convert_arrays_to_json(): void
    {
        // Arrange

        // Act
        RequestInsurance::create([
            'payload' => ['data' => [1, 2, 3]],
            'headers' => ['Content-Type' => 'application/json'],
            'method' => 'POST',
            'url' => 'https://MyDev.lupinsdev.dk',
        ]);

        // Assert
        $this->assertCount(1, RequestInsurance::all());

        /** @var RequestInsurance $requestInsurance */
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(json_encode(['data' => [1, 2, 3]], JSON_THROW_ON_ERROR), $requestInsurance->payload);
        $this->assertEquals(json_encode(['Content-Type' => 'application/json'], JSON_THROW_ON_ERROR), $requestInsurance->headers);
    }
}