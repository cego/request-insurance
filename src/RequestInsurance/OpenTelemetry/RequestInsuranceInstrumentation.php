<?php

namespace Cego\RequestInsurance\OpenTelemetry;

use Cego\RequestInsurance\RequestInsuranceWorker;
use Throwable;
use RdKafka\KafkaConsumer;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\SpanKind;
use Cego\Kafka\Kafka\Consumer\Consumer;
use Cego\Kafka\Kafka\Producer\Producer;
use OpenTelemetry\API\Trace\StatusCode;
use Cego\Kafka\Common\CurrentApplication;
use OpenTelemetry\SemConv\TraceAttributes;
use Cego\Kafka\Kafka\Consumer\KafkaMessage;
use Cego\Kafka\Kafka\Consumer\TopicHandler;
use Cego\Kafka\Kafka\Consumer\CommitManager;
use Cego\Kafka\Laravel\Commands\KafkaProducer;
use Cego\Kafka\Kafka\Consumer\MessageRetriever;
use function OpenTelemetry\Instrumentation\hook;
use Cego\Kafka\Database\Consumer\DatabaseConsumer;
use Cego\Kafka\Kafka\MessageHandlers\MessageHandler;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

class RequestInsuranceInstrumentation
{
    public const NAME = 'cego-request-insurance';

    private static function hookClassMethod(CachedInstrumentation $instrumentation, string $className, string $methodName, string $spanName): void
    {
        hook(
            $className,
            $methodName,
            static function () use ($instrumentation, $className, $methodName, $spanName) {
                $span = $instrumentation->tracer()->spanBuilder($spanName)->startSpan();
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            static function ($object, $params, $result, ?Throwable $exception) {
                self::endCurrentSpan($exception);
            }
        );
    }

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('dk.cego.request-insurance-instrumentation');

        $methodsToHook = [
            [RequestInsuranceWorker::class, 'processRequestInsurances', 'Process request insurances'],
            [RequestInsuranceWorker::class, 'readyWaitingRequestInsurances', 'Ready waiting request insurances'],
        ];

        foreach ($methodsToHook as $methodToHook) {
            self::hookClassMethod($instrumentation, $methodToHook[0], $methodToHook[1], $methodToHook[2]);
        }
    }

    /**
     * @param Throwable|null $exception
     *
     * @return void
     */
    private static function endCurrentSpan(?Throwable $exception): void
    {
        $scope = Context::storage()->scope();

        if ( ! $scope) {
            return;
        }

        $scope->detach();
        $span = Span::fromContext($scope->context());

        if ($exception) {
            $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
    }
}
