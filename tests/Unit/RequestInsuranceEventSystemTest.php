<?php

namespace Tests\Unit;

use Exception;
use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Events\RequestFailed;
use Cego\RequestInsurance\Mocks\MockCurlRequest;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Events\RequestSuccessful;
use Cego\RequestInsurance\Events\RequestServerError;
use Cego\RequestInsurance\Events\RequestClientError;
use Cego\RequestInsurance\Exceptions\EmptyPropertyException;

class RequestInsuranceEventSystemTest extends TestCase
{
    /** @test */
    public function it_triggers_successful_event_for_200_responses(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 200]]);

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $requestInsurance->process();

        // Assert
        Event::assertDispatched(RequestSuccessful::class);
        Event::assertNotDispatched(RequestFailed::class);
    }

    /** @test */
    public function it_triggers_failed_event_for_400_responses(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 400]]);

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $requestInsurance->process();

        // Assert
        Event::assertDispatched(RequestFailed::class);
        Event::assertNotDispatched(RequestSuccessful::class);
    }

    /** @test */
    public function it_triggers_failed_event_for_500_responses(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 500]]);

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $requestInsurance->process();

        // Assert
        Event::assertDispatched(RequestFailed::class);
        Event::assertNotDispatched(RequestSuccessful::class);
    }

    /** @test */
    public function it_triggers_client_error_event_for_400_responses(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 400]]);

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $requestInsurance->process();

        // Assert
        Event::assertDispatched(RequestClientError::class);
        Event::assertNotDispatched(RequestServerError::class);
    }

    /** @test */
    public function it_triggers_server_error_event_for_500_responses(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 500]]);

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $requestInsurance->process();

        // Assert
        Event::assertNotDispatched(RequestClientError::class);
        Event::assertDispatched(RequestServerError::class);
    }
}
