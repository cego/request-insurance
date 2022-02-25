<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Cego\RequestInsurance\Events\RequestFailed;
use Cego\RequestInsurance\Mocks\MockCurlRequest;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Events\RequestSuccessful;
use Cego\RequestInsurance\Events\RequestClientError;
use Cego\RequestInsurance\Events\RequestServerError;
use Cego\RequestInsurance\Events\RequestBeforeProcess;

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

    /** @test */
    public function it_can_abandon_requests_before_they_are_sent(): void
    {
        // Arrange
        Event::listen(function (RequestBeforeProcess $event) {
            $event->requestInsurance->abandon();
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);

        // Act
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNotNull($requestInsurance->abandoned_at);
    }

    /** @test */
    public function it_can_complete_requests_before_they_are_sent(): void
    {
        // Arrange
        Event::listen(function (RequestBeforeProcess $event) {
            $event->requestInsurance->update(['completed_at' => Carbon::now()]);
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);

        // Act
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNotNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);
    }

    /** @test */
    public function it_can_pause_requests_before_they_are_sent(): void
    {
        // Arrange
        Event::listen(function (RequestBeforeProcess $event) {
            $event->requestInsurance->pause();
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);

        // Act
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);
    }
}
