<?php

namespace Tests\Unit;

use Exception;
use Throwable;
use Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
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

    public function test_it_can_process_a_single_available_record(): void
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

    public function test_it_can_process_a_multiple_available_record(): void
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

    public function test_it_does_not_consume_failed_records(): void
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

    public function test_it_does_not_consume_abandoned_records(): void
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

    public function test_it_does_not_consume_pending_records(): void
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

    public function test_it_only_consumes_to_a_given_batch_size(): void
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

    public function test_it_pauses_requests_with_listeners_that_throw_exceptions_when_the_response_is_not_200(): void
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

    public function test_it_does_not_exit_processing_of_other_jobs_if_a_listener_throws_an_exception(): void
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

    public function test_it_completes_requests_with_listeners_that_throw_exceptions_when_the_response_is_200(): void
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

    public function test_headers_are_still_encrypted_in_db_after_processing_unkeyed_payload()
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

    public function test_it_marks_timeouts_as_inconsistent()
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

    public function test_it_can_retry_inconsistent_jobs()
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

    public function test_it_can_process_image_response()
    {
        // Arrange
        RequestInsuranceClient::fake(fn () => Http::response([], 200, ['content-type' => 'image/gif', 'some-header' => 'some-value']));

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
        $this->assertEquals('<REQUEST_IMAGE_GIF_RESPONSE : THIS MESSAGE WAS ADDED BY REQUEST INSURANCE>', $requestInsurance->response_body);
    }

    public function test_timeout_diagnostic_message_includes_phase_and_request_ids(): void
    {
        $worker = new class () extends RequestInsuranceWorker {
            public function exposeSetWorkerPhase(string $phase, $requestInsuranceIds = null): void
            {
                $this->setWorkerPhase($phase, $requestInsuranceIds);
            }

            public function exposeTimeoutDiagnosticMessage(): string
            {
                return $this->timeoutDiagnosticMessage();
            }
        };

        $worker->exposeSetWorkerPhase('http_sending', [12, 34]);

        $this->assertSame(
            'Timeout handler was triggered indicating stuck worker during http_sending [request_insurance_ids: 12,34], exiting...',
            $worker->exposeTimeoutDiagnosticMessage()
        );

        $worker->exposeSetWorkerPhase('cycle_sleep');

        $this->assertSame(
            'Timeout handler was triggered indicating stuck worker during cycle_sleep, exiting...',
            $worker->exposeTimeoutDiagnosticMessage()
        );
    }

    public function test_timeout_diagnostic_is_written_to_stdout_before_log(): void
    {
        $worker = new class () extends RequestInsuranceWorker {
            public array $events = [];

            public function exposeSetWorkerPhase(string $phase, $requestInsuranceIds = null): void
            {
                $this->setWorkerPhase($phase, $requestInsuranceIds);
            }

            public function exposeHandleTimeoutSignal(): void
            {
                $this->handleTimeoutSignal();
            }

            protected function writeTimeoutDiagnosticToStdout(string $message): void
            {
                $this->events[] = ['stdout', $message . "\n"];
            }

            protected function terminateWorkerAfterTimeout(): void
            {
                $this->events[] = ['terminate'];
            }
        };

        \Illuminate\Support\Facades\Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message) use ($worker) {
                $worker->events[] = ['log', $message];

                return $message === 'Timeout handler was triggered indicating stuck worker during response_handling [request_insurance_ids: 7], exiting...';
            });

        $worker->exposeSetWorkerPhase('response_handling', [7]);
        $worker->exposeHandleTimeoutSignal();

        $this->assertSame([
            ['stdout', "Timeout handler was triggered indicating stuck worker during response_handling [request_insurance_ids: 7], exiting...\n"],
            ['log', 'Timeout handler was triggered indicating stuck worker during response_handling [request_insurance_ids: 7], exiting...'],
            ['terminate'],
        ], $worker->events);
    }

    public function test_the_request_chunk_size_defaults_to_the_batch_size(): void
    {
        Config::set('request-insurance.batchSize', 42);
        Config::set('request-insurance.concurrentHttpChunkSize', null);

        $this->assertSame(42, $this->getWorkerProbe()->exposeGetRequestChunkSize());
    }

    public function test_the_request_chunk_size_can_be_set_below_the_batch_size(): void
    {
        Config::set('request-insurance.batchSize', 42);
        Config::set('request-insurance.concurrentHttpChunkSize', 7);

        $this->assertSame(7, $this->getWorkerProbe()->exposeGetRequestChunkSize());
    }

    public function test_the_deprecated_concurrent_http_enabled_flag_still_disables_concurrency(): void
    {
        Config::set('request-insurance.batchSize', 42);
        Config::set('request-insurance.concurrentHttpEnabled', false);

        $this->assertSame(1, $this->getWorkerProbe()->exposeGetRequestChunkSize());
    }

    public function test_it_recovers_quietly_from_a_lost_database_connection(): void
    {
        $worker = $this->getWorkerProbe();

        Log::shouldReceive('debug')
            ->once()
            ->with('RequestInsurance Worker (#' . $worker->exposeRunningHash() . ') lost its database connection during idle and is reconnecting (1 in a row)');

        Log::shouldReceive('error')->never();

        $worker->exposeReportCycleFailure(new Exception('Cycle failed', 0, new Exception('MySQL server has gone away')));

        $this->assertSame(1, $worker->reconnects);
    }

    public function test_it_reports_lost_database_connections_that_keep_happening(): void
    {
        $worker = $this->getWorkerProbe();

        Log::shouldReceive('debug')->times(3);
        Log::shouldReceive('error')->twice();

        foreach (range(1, 5) as $ignored) {
            $worker->exposeReportCycleFailure(new Exception('Lost connection to server'));
        }

        $this->assertSame(5, $worker->reconnects);
    }

    public function test_it_reports_cycle_failures_that_are_not_lost_connections(): void
    {
        $worker = $this->getWorkerProbe();
        $throwable = new Exception('Something else went wrong');

        Log::shouldReceive('error')->once()->with($throwable);
        Log::shouldReceive('debug')->never();

        $worker->exposeReportCycleFailure($throwable);

        $this->assertSame(0, $worker->reconnects);
    }

    /**
     * Returns a worker exposing the internals needed to assert on cycle failure handling
     */
    private function getWorkerProbe(): RequestInsuranceWorker
    {
        return new class () extends RequestInsuranceWorker {
            public int $reconnects = 0;

            public function exposeGetRequestChunkSize(): int
            {
                return $this->getRequestChunkSize();
            }

            public function exposeReportCycleFailure(Throwable $throwable): void
            {
                $this->handleCycleFailure($throwable);
            }

            public function exposeRunningHash(): ?string
            {
                return $this->runningHash;
            }

            protected function reconnectToDatabase(): void
            {
                $this->reconnects++;
            }
        };
    }
}
