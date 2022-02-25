<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Cego\RequestInsurance\Events\RequestFailed;
use Cego\RequestInsurance\Contracts\HttpRequest;
use Cego\RequestInsurance\Mocks\MockCurlRequest;
use Cego\RequestInsurance\RequestInsuranceWorker;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Events\RequestSuccessful;

class RequestInsuranceWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT=false');
        parent::setUp();
    }

    /** @test */
    public function it_has_a_mocked_curl_client(): void
    {
        // A protective test, which makes sure we always use the mocked curl request
        // instead of the actual one.

        // Act
        $class = app()->get(HttpRequest::class);

        // Assert
        $this->assertEquals(MockCurlRequest::class, $class);
    }

    /** @test */
    public function it_can_process_a_single_available_record(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNotNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);
    }

    /** @test */
    public function it_can_process_a_multiple_available_record(): void
    {
        // Arrange
        $requestInsurance1 = RequestInsurance::getBuilder()->url('https://test.lupinsdev.dk')->method('get')->create();
        $requestInsurance2 = RequestInsurance::getBuilder()->url('https://test.lupinsdev.dk')->method('get')->create();

        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertNull($requestInsurance1->completed_at);
        $this->assertNull($requestInsurance1->paused_at);
        $this->assertNull($requestInsurance1->abandoned_at);
        $this->assertNull($requestInsurance1->locked_at);

        $this->assertNull($requestInsurance2->completed_at);
        $this->assertNull($requestInsurance2->paused_at);
        $this->assertNull($requestInsurance2->abandoned_at);
        $this->assertNull($requestInsurance2->locked_at);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertNotNull($requestInsurance1->completed_at);
        $this->assertNull($requestInsurance1->paused_at);
        $this->assertNull($requestInsurance1->abandoned_at);
        $this->assertNull($requestInsurance1->locked_at);

        $this->assertNotNull($requestInsurance2->completed_at);
        $this->assertNull($requestInsurance2->paused_at);
        $this->assertNull($requestInsurance2->abandoned_at);
        $this->assertNull($requestInsurance2->locked_at);
    }

    /** @test */
    public function it_does_not_consume_paused_records(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->update(['paused_at' => Carbon::now()]);
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);
    }

    /** @test */
    public function it_does_not_consume_abandoned_records(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->update(['abandoned_at' => Carbon::now()]);
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNotNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNotNull($requestInsurance->abandoned_at);
        $this->assertNull($requestInsurance->locked_at);
    }

    /** @test */
    public function it_does_not_consume_locked_records(): void
    {
        // Arrange
        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->update(['locked_at' => Carbon::now()]);
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNotNull($requestInsurance->locked_at);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->abandoned_at);
        $this->assertNotNull($requestInsurance->locked_at);
    }

    /** @test */
    public function it_only_consumes_to_a_given_batch_size(): void
    {
        // Arrange
        $requestInsurance1 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance2 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $this->assertCount(2, RequestInsurance::query()->whereNull('completed_at')->get());

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertCount(1, RequestInsurance::query()->whereNull('completed_at')->get());
    }

    /** @test */
    public function it_pauses_requests_with_listeners_that_throw_exceptions_when_the_response_is_not_200(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 400]]);

        Event::listen(function (RequestFailed $event) {
            throw new \InvalidArgumentException();
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNotNull($requestInsurance->paused_at);
        $this->assertNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);
    }

    /** @test */
    public function it_completes_requests_with_listeners_that_throw_exceptions_when_the_response_is_200(): void
    {
        // Arrange
        MockCurlRequest::setNextResponse(['info' => ['http_code' => 200]]);

        Event::listen(function (RequestSuccessful $event) {
            throw new \InvalidArgumentException();
        });

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertNull($requestInsurance->paused_at);
        $this->assertNotNull($requestInsurance->completed_at);
        $this->assertNull($requestInsurance->locked_at);
        $this->assertNull($requestInsurance->abandoned_at);
    }
}
