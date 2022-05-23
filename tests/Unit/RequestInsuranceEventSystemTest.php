<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Events\RequestFailed;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Events\RequestSuccessful;
use Cego\RequestInsurance\Events\RequestClientError;
use Cego\RequestInsurance\Events\RequestServerError;
use Cego\RequestInsurance\Events\RequestBeforeProcess;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;

class RequestInsuranceEventSystemTest extends TestCase
{
    /** @test */
    public function it_triggers_successful_event_for_200_responses(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $this->runWorkerOnce();

        // Assert
        Event::assertDispatched(RequestSuccessful::class);
        Event::assertNotDispatched(RequestFailed::class);
    }

    /** @test */
    public function it_triggers_failed_event_for_400_responses(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 400));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $this->runWorkerOnce();

        // Assert
        Event::assertDispatched(RequestFailed::class);
        Event::assertNotDispatched(RequestSuccessful::class);
    }

    /** @test */
    public function it_triggers_failed_event_for_500_responses(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 500));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $this->runWorkerOnce();

        // Assert
        Event::assertDispatched(RequestFailed::class);
        Event::assertNotDispatched(RequestSuccessful::class);
    }

    /** @test */
    public function it_triggers_client_error_event_for_400_responses(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 400));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $this->runWorkerOnce();

        // Assert
        Event::assertDispatched(RequestClientError::class);
        Event::assertNotDispatched(RequestServerError::class);
    }

    /** @test */
    public function it_triggers_server_error_event_for_500_responses(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 500));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        // Act
        Event::fake();

        $this->runWorkerOnce();

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
        $this->assertTrue($requestInsurance->hasState(State::READY));

        // Act
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertTrue($requestInsurance->hasState(State::ABANDONED));
    }

    /** @test */
    public function it_can_complete_requests_before_they_are_sent(): void
    {
        // Arrange
        Event::listen(function (RequestBeforeProcess $event) {
            $event->requestInsurance->update(['state' => State::COMPLETED]);
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertTrue($requestInsurance->hasState(State::READY));

        // Act
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertTrue($requestInsurance->hasState(State::COMPLETED));
    }

    /** @test */
    public function it_can_fail_requests_before_they_are_sent(): void
    {
        // Arrange
        Event::listen(function (RequestBeforeProcess $event) {
            $event->requestInsurance->setState(State::FAILED);
            $event->requestInsurance->save();
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        // Act
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(0, $requestInsurance->retry_count);
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }
}
