<?php

namespace Tests\Unit;

use Tests\TestCase;
use InvalidArgumentException;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceBuilderTest extends TestCase
{
    public function test_it_can_create_a_request_insurance(): void
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
        $this->assertEquals(json_encode(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123', 'X-Sensitive-Request-Headers-JSON' => json_encode(['Authorization', 'authorization'])]), $requestInsurance->headers);
        $this->assertEquals('POST', $requestInsurance->method);
        $this->assertEquals('https://MyDev.lupinsdev.dk', $requestInsurance->url);
    }

    public function test_it_can_set_retry_inconsistent(): void
    {
        // Arrange

        // Act
        RequestInsurance::getBuilder()
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->headers(['Content-Type' => 'application/json'])
            ->payload(['data' => [1, 2, 3]])
            ->retryInconsistentState()
            ->create();

        // Assert
        $this->assertCount(1, RequestInsurance::all());
        $requestInsurance = RequestInsurance::first();

        $this->assertEquals(true, $requestInsurance->retry_inconsistent);
    }

    public function test_it_does_not_allow_empty_method(): void
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

    public function test_it_does_not_allow_empty_url(): void
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

    public function test_it_allows_url_with_special_chars(): void
    {
        // Act
        $builder = RequestInsurance::getBuilder()
            ->payload(['data' => [1, 2, 3]])
            ->headers(['Content-Type' => 'application/json'])
            ->method('POST')
            ->url('https://marketing-automation-test.spilnu.dk/api/v1/contacts/æøåäö@gmail.com')
            ->create();

        // Assert
        $this->assertEquals('https://marketing-automation-test.spilnu.dk/api/v1/contacts/æøåäö@gmail.com', $builder->url);
    }

    public function test_it_can_convert_arrays_to_json(): void
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
        $this->assertEquals(json_encode(['Content-Type' => 'application/json', 'X-Request-Trace-Id' => '123', 'X-Sensitive-Request-Headers-JSON' => json_encode(['Authorization', 'authorization'])], JSON_THROW_ON_ERROR), $requestInsurance->headers);
    }
}
