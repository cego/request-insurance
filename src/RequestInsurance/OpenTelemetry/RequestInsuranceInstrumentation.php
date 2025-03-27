<?php

namespace Cego\RequestInsurance\OpenTelemetry;

use Throwable;
use OpenTelemetry\API\Trace\Span;
use Illuminate\Support\Collection;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\Context\ContextInterface;

use function OpenTelemetry\Instrumentation\hook;

use Cego\RequestInsurance\RequestInsuranceWorker;

use Cego\RequestInsurance\Models\RequestInsurance;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

class RequestInsuranceInstrumentation
{
    public const NAME = 'cego-request-insurance';

    /**
     * Instruments the given class method, and gives the span around it the given spanName.
     *
     * @param CachedInstrumentation $instrumentation
     * @param string $className
     * @param string $methodName
     * @param string $spanName
     *
     * @return void
     */
    private static function hookClassMethod(CachedInstrumentation $instrumentation, string $className, string $methodName, string $spanName): void
    {
        hook(
            $className,
            $methodName,
            static function () use ($instrumentation, $spanName) {
                $span = $instrumentation->tracer()->spanBuilder($spanName)->startSpan();
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            static function ($object, $params, $result, ?Throwable $exception) {
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
        );
    }

    /**
     * @param Collection<array-key, RequestInsurance> $collection
     *
     * @return Collection<array-key, ContextInterface>
     */
    private static function getLinksFromCollection(Collection $collection): Collection
    {
        $traceContextPropagator = TraceContextPropagator::getInstance();

        return $collection->map(function (RequestInsurance $requestInsurance) use ($traceContextPropagator) {
            $headers = $requestInsurance->headers;

            if ( ! is_array($headers)) {
                $headers = json_decode($requestInsurance->headers, true, 512, JSON_THROW_ON_ERROR);
            }

            return $traceContextPropagator->extract($headers);
        });
    }

    private static function hookBatchProcessing(CachedInstrumentation $instrumentation)
    {
        hook(
            RequestInsuranceWorker::class,
            'processHttpRequestChunk',
            static function ($object, $params) use ($instrumentation) {
                $spanBuilder = $instrumentation->tracer()->spanBuilder('RequestInsuranceWorker::processHttpRequestChunk');

                // Set messaging.batch.message_count https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
                $spanBuilder->setAttribute('messaging.system', 'request-insurance-worker');

                $count = $params[0]->count();

                if ($count > 1) {
                    $spanBuilder->setAttribute('messaging.batch.message_count', $count);
                    $spanContexts = self::getLinksFromCollection($params[0]);
                    $spanContexts->each(function (ContextInterface $spanContext) use ($spanBuilder) {
                        $spanBuilder->addLink(Span::fromContext($spanContext)->getContext());
                    });
                } elseif ($count === 1) {
                    $spanBuilder->setParent(self::getLinksFromCollection($params[0])->first());
                }

                $spanBuilder->setSpanKind(SpanKind::KIND_CONSUMER);

                $span = $spanBuilder->startSpan();
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            static function ($object, $params, $result, ?Throwable $exception) {
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
        );
    }

    /**
     * Initiated by ./_register.php
     *
     * @return void
     */
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('dk.cego.request-insurance-instrumentation');

        $methodsToHook = [
            [RequestInsuranceWorker::class, 'getRequestsToProcess', 'RequestInsuranceWorker::getRequestsToProcess'],
            [RequestInsuranceWorker::class, 'readyWaitingRequestInsurances', 'RequestInsuranceWorker::readyWaitingRequestInsurances'],
        ];

        foreach ($methodsToHook as $methodToHook) {
            self::hookClassMethod($instrumentation, $methodToHook[0], $methodToHook[1], $methodToHook[2]);
        }
        self::hookBatchProcessing($instrumentation);
    }
}
