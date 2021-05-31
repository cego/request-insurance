<?php

namespace Tests\Unit;

use Tests\TestCase;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceTest extends TestCase
{
    /** @test */
    public function it_can_create_a_request_insurance(): void
    {
        // Arrange

        // Act
        RequestInsurance::create([
            'payload' => ['data' => [1,2,3]],
            'headers' => ['Content-Type' => 'application/json'],
            'method' => 'POST',
            'url' => 'https://MyDev.lupinsdev.dk',
        ]);

        // Assert
        $this->assertCount(1, RequestInsurance::all());
    }
}