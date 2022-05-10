<?php

namespace Tests\Unit;

use Exception;
use Carbon\Carbon;
use Tests\TestCase;
use RuntimeException;
use Illuminate\Support\Facades\Http;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\RequestInsuranceWorker;
use Cego\RequestInsurance\Models\RequestInsurance;

class RequestInsuranceStateTest extends TestCase
{
    /** @test */
    public function it_defaults_to_active_state(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::now());

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();

        // Assert
        $this->assertEquals(State::ACTIVE, $requestInsurance->state);
        $this->assertEquals(Carbon::now()->toDateTimeString(), $requestInsurance->state_changed_at->toDateTimeString());
    }

    /** @test */
    public function it_sets_state_completed_on_successful_processing(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 200));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::COMPLETED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_failed_on_400(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 400));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_active_on_500(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 500));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $requestInsurance->process();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::ACTIVE, $requestInsurance->state);
    }

    /** @test */
    public function it_exits_on_state_processing_on_unhandled_exceptions_in_processing(): void
    {
        // Arrange
        Http::fake(function () {
            throw new RuntimeException('test');
        });

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();

        try {
            $requestInsurance->process();
        } catch (Exception $exception) {
            // Do nothing
        }

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::PROCESSING, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_active_when_worker_process_jobs_with_500_response(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 500));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::ACTIVE, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_failed_when_worker_process_jobs_with_400_response(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 400));

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_failed_when_worker_process_jobs_with_exception(): void
    {
        // Arrange
        Http::fake(function () {
            throw new RuntimeException('test');
        });

        // Act
        $requestInsurance = $this->createDummyRequestInsurance();
        $this->runWorkerOnce();

        // Assert
        $requestInsurance->refresh();
        $this->assertEquals(State::FAILED, $requestInsurance->state);
    }

    /** @test */
    public function it_sets_state_to_completed_when_worker_process_jobs_with_200_response(): void
    {
        // Arrange
        Http::fake(fn () => Http::response([], 200));

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
        Http::fake(fn () => Http::response([], 200));

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

    protected function runWorkerOnce(): void
    {
        $this->getWorker()->run(true);
    }

    protected function getWorker(): RequestInsuranceWorker
    {
        putenv('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT=false');

        return new RequestInsuranceWorker();
    }
}
