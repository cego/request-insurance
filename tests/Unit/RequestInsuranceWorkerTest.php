<?php

namespace Tests\Unit;

use Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Cego\RequestInsurance\Enums\State;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Exception\ConnectException;
use Cego\RequestInsurance\Events\RequestFailed;
use Cego\RequestInsurance\RequestInsuranceWorker;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\Events\RequestSuccessful;
use Cego\RequestInsurance\AsyncRequests\RequestInsuranceClient;

class RequestInsuranceWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT=false');
        parent::setUp();
    }

    /** @test */
    public function it_can_process_a_single_available_record(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->refresh();

        $this->assertTrue($requestInsurance->hasState(State::READY));

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertTrue($requestInsurance->hasState(State::COMPLETED));
    }

    /** @test */
    public function it_can_process_a_multiple_available_record(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance1 = RequestInsurance::getBuilder()->url('https://test.lupinsdev.dk')->method('get')->create();
        $requestInsurance2 = RequestInsurance::getBuilder()->url('https://test.lupinsdev.dk')->method('get')->create();

        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertTrue($requestInsurance1->hasState(State::READY));
        $this->assertTrue($requestInsurance2->hasState(State::READY));

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertTrue($requestInsurance1->hasState(State::COMPLETED));
        $this->assertTrue($requestInsurance2->hasState(State::COMPLETED));
    }

    /** @test */
    public function it_does_not_consume_failed_records(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->updateOrFail(['state' => State::FAILED]);
        $requestInsurance->refresh();

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(State::FAILED, $requestInsurance->state);
        $this->assertEquals(0, $requestInsurance->retry_count);
    }

    /** @test */
    public function it_does_not_consume_abandoned_records(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->updateOrFail(['state' => State::ABANDONED]);
        $requestInsurance->refresh();

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $this->assertEquals(State::ABANDONED, $requestInsurance->state);
        $this->assertEquals(0, $requestInsurance->retry_count);
    }

    /** @test */
    public function it_does_not_consume_pending_records(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance->updateOrFail(['state' => State::PENDING]);
        $requestInsurance->refresh();

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance->refresh();

        $requestInsurance->updateOrFail(['state' => State::PENDING]);
        $this->assertEquals(0, $requestInsurance->retry_count);
    }

    /** @test */
    public function it_only_consumes_to_a_given_batch_size(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        $requestInsurance1 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance2 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $this->assertCount(2, RequestInsurance::query()->where('state', '!=', State::COMPLETED)->get());

        Config::set('request-insurance.batchSize', 1);

        // Act
        $worker = new RequestInsuranceWorker();
        $worker->run(true);

        // Assert
        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertCount(1, RequestInsurance::query()->where('state', '!=', State::COMPLETED)->get());
    }

    /** @test */
    public function it_pauses_requests_with_listeners_that_throw_exceptions_when_the_response_is_not_200(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 400));
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

        $this->assertEquals(State::FAILED, $requestInsurance->state);
        $this->assertEquals(1, $requestInsurance->retry_count);
    }

    /** @test */
    public function it_does_not_exit_processing_of_other_jobs_if_a_listener_throws_an_exception(): void
    {
        // Arrange
        Config::set('request-insurance.concurrentHttpEnabled', true);
        Config::set('request-insurance.concurrentHttpChunkSize', 2);

        RequestInsuranceClient::fake([
            Http::response([], 400),
            Http::response([], 200),
        ]);

        Event::listen(function (RequestFailed $event) {
            throw new \InvalidArgumentException();
        });

        $requestInsurance1 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance2 = RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('get')
            ->create();

        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        // Act
        $this->runWorkerOnce();

        // Assert
        $requestInsurance1->refresh();
        $requestInsurance2->refresh();

        $this->assertEquals(State::FAILED, $requestInsurance1->state);
        $this->assertEquals(1, $requestInsurance1->retry_count);

        $this->assertEquals(State::COMPLETED, $requestInsurance2->state);
        $this->assertEquals(1, $requestInsurance2->retry_count);
    }

    /** @test */
    public function it_completes_requests_with_listeners_that_throw_exceptions_when_the_response_is_200(): void
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

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

        $this->assertEquals(State::COMPLETED, $requestInsurance->state);
        $this->assertEquals(1, $requestInsurance->retry_count);
    }

    /** @test */
    public function headers_are_still_encrypted_in_db_after_processing_unkeyed_payload()
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200));

        RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('post')
            ->headers(['Authorization' => 'Basic 12345'])
            ->payload('The payload is not an array json_encoded string.')
            ->create();

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $authorizationHeaderInDB = json_decode(RequestInsurance::first()->getOriginal('headers'), true)['Authorization'];
        $this->assertNotEquals('Basic 12345', $authorizationHeaderInDB);
        $this->assertEquals('Basic 12345', Crypt::decrypt($authorizationHeaderInDB));
    }

    /** @test */
    public function it_marks_timeouts_as_inconsistent()
    {
        // Arrange
        RequestInsuranceClient::fake(function () {
            throw new ConnectException('', new Request('get', ''));
        });

        RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('post')
            ->headers(['Authorization' => 'Basic 12345'])
            ->payload('The payload is not an array json_encoded string.')
            ->create();

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $this->assertEquals(State::FAILED, RequestInsurance::first()->state);
    }

    /** @test */
    public function it_can_retry_inconsistent_jobs()
    {
        // Arrange
        RequestInsuranceClient::fake(function () {
            throw new ConnectException('', new Request('get', ''));
        });

        RequestInsurance::getBuilder()
            ->url('https://test.lupinsdev.dk')
            ->method('post')
            ->headers(['Authorization' => 'Basic 12345'])
            ->payload('The payload is not an array json_encoded string.')
            ->retryInconsistentState()
            ->create();

        // Act
        $worker = new RequestInsuranceWorker(1);
        $worker->run(true);

        // Assert
        $this->assertEquals(State::WAITING, RequestInsurance::first()->state);
    }
}
