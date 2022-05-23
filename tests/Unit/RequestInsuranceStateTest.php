<?php

namespace Tests\Unit;

use Exception;
use Carbon\Carbon;
use Tests\TestCase;
use RuntimeException;
use Illuminate\Support\Facades\Http;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;

class RequestInsuranceStateTest extends TestCase
{
    /** @test */
    public function it_defaults_to_ready_state(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::now());

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();

        // Assert
        $this->assertEquals(State::READY, $requestInsurance->state);
        $this->assertEquals(Carbon::now()->toDateTimeString(), $requestInsurance->state_changed_at->toDateTimeString());
    }

    /** @test */
    public function it_sets_state_completed_on_successful_processing(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::COMPLETED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_failed_on_400(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 400));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_waiting_on_500(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 500));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::WAITING, $requestInsurance->state);
    }

    /** @test */
    public function it_exits_on_state_processing_on_unhandled_exceptions_in_processing(): void
    {
        // Arrange
        RequestInsuranceClient::fake(function () {
            throw new RuntimeException('test');
        });

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();

        try {
            $this->runWorkerOnce();
        } catch (Exception $exception) {
            // Do nothing
        }

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::PROCESSING, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_ready_when_worker_process_jobs_with_500_response(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 500));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::WAITING, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_failed_when_worker_process_jobs_with_400_response(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 400));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }

    /** @test */
    public function it_leaves_the_request_in_processing_state_when_worker_process_jobs_with_exception(): void
    {
        // Arrange
        RequestInsuranceClient::fake(function () {
            throw new RuntimeException('test');
        });

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::PROCESSING, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_completed_when_worker_process_jobs_with_200_response(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::COMPLETED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_pending_when_workers_lock_rows(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->getWorker()->acquireLockOnRowsToProcess();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::PENDING, $requestInsurance->state);
    }

    protected function createDummyRequestInsurance(): RequestInsurance
    {
        $requestInsurance = RequestInsurance::getBuilder()
            ->method('POST')
            ->url('https://MyDev.lupinsdev.dk')
            ->headers(['Content-Type' => 'application/json'])
            ->payload(['data' => [1, 2, 3]])
            ->traceId('123')
            ->create();

        return $requestInsurance->fresh();
    }
}
