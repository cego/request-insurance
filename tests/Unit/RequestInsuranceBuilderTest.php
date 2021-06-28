<?php

namespace Tests\Unit;

use Tests\TestCase;
use InvalidArgumentException;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;

class RequestInsuranceBuilderTest extends TestCase
{
    /** @test */
    public function it_can_create_a_request_insurance(): void
    {
        // Arrange

        // Act
        RequestInsurance::builder()
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->headers(['Content-Type' => 'application/json'])
            ->payload(['data' => [1, 2, 3]])
            ->create();

        // Assert
        $this->assertCount(1, RequestInsurance::all());
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(['data' => [1, 2, 3]], json_decode($requestInsurance->payload, true, 512, JSON_THROW_ON_ERROR));
        $this->assertEquals(['Content-Type' => 'application/json'], json_decode($requestInsurance->headers, true, 512, JSON_THROW_ON_ERROR));
        $this->assertEquals('POST', $requestInsurance->method);
        $this->assertEquals('https://MyDev.lupinsdev.dk', $requestInsurance->url);
    }

    /** @test */
    public function it_does_not_allow_empty_method(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        RequestInsurance::builder()
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
        RequestInsurance::builder()
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
        RequestInsurance::builder()
            ->payload(['data' => [1, 2, 3]])
            ->headers(['Content-Type' => 'application/json'])
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->create();

        // Assert
        $this->assertCount(1, RequestInsurance::all());

        /** @var RequestInsurance $requestInsurance */
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(json_encode(['data' => [1, 2, 3]], JSON_THROW_ON_ERROR), $requestInsurance->payload);
        $this->assertEquals(json_encode(['Content-Type' => 'application/json'], JSON_THROW_ON_ERROR), $requestInsurance->headers);
    }
}