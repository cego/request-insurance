<?php

namespace Cego\RequestInsurance\OpenTelemetry;

use Throwable;
use OpenTelemetry\API\Trace\Span;
use Illuminate\Support\Collection;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

use Cego\RequestInsurance\RequestInsuranceWorker;

use OpenTelemetry\API\Trace\SpanContextInterface;
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
     * @return Collection<array-key, SpanContextInterface>
     */
    private function getLinksFromCollection(Collection $collection): Collection
    {
        $traceContextPropagator = TraceContextPropagator::getInstance();

        return $collection->map(function (RequestInsurance $requestInsurance) use ($traceContextPropagator) {
            return Span::fromContext($traceContextPropagator->extract($requestInsurance->headers))->getContext();
        })->filter(function (SpanContextInterface $spanContext) {
            return $spanContext->isValid();
        });
    }

    private static function hookBatchProcessing(CachedInstrumentation $instrumentation)
    {
        hook(
            RequestInsuranceWorker::class,
            'processHttpRequestChunk',
            static function ($object, $params) use ($instrumentation) {
                $spanBuilder = $instrumentation->tracer()->spanBuilder('Process batch of http requests');

                $spanContexts = $this->getLinksFromCollection($params[0]);

                // Set messaging.batch.message_count https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
                $spanBuilder->setAttribute('messaging.system', 'request-insurance-worker');

                if ($params->count() > 1) {
                    $spanBuilder->setAttribute('messaging.batch.message_count', $params->count());
                    $spanContexts->each(function (SpanContextInterface $spanContext) use ($spanBuilder) {
                        $spanBuilder->addLink($spanContext);
                    });
                } elseif ($params->count() === 1) {
                    $spanBuilder->setParent($spanContexts->first());
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
            [RequestInsuranceWorker::class, 'processRequestInsurances', 'Process request insurances'],
            [RequestInsuranceWorker::class, 'readyWaitingRequestInsurances', 'Ready waiting request insurances'],
        ];

        foreach ($methodsToHook as $methodToHook) {
            self::hookClassMethod($instrumentation, $methodToHook[0], $methodToHook[1], $methodToHook[2]);
        }
        self::hookBatchProcessing($instrumentation);
    }
}
