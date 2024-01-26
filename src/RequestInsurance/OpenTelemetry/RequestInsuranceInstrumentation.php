<?php

namespace Cego\Kafka\OpenTelemetry;

use Cego\RequestInsurance\RequestInsuranceWorker;
use Throwable;
use RdKafka\KafkaConsumer;
use OpenTelemetry\API\Trace\Span;
use Illuminate\Support\Collection;
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

    private static function hookClassMethod(CachedInstrumentation $instrumentation, string $className, string $methodName): void
    {
        hook(
            $className,
            $methodName,
            static function () use ($instrumentation, $className, $methodName) {
                $span = $instrumentation->tracer()->spanBuilder($className . '::' . $methodName)->startSpan();
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
            [RequestInsuranceWorker::class, 'processRequestInsurances'],
            [RequestInsuranceWorker::class, 'readyWaitingRequestInsurances'],
        ];

        foreach ($methodsToHook as $methodToHook) {
            self::hookClassMethod($instrumentation, $methodToHook[0], $methodToHook[1]);
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
