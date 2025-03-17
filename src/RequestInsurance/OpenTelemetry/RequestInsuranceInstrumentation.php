<?php

namespace Cego\RequestInsurance\OpenTelemetry;

use Throwable;
use OpenTelemetry\API\Trace\Span;
use Illuminate\Support\Collection;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

use Cego\RequestInsurance\RequestInsuranceWorker;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanContextValidator;
use Cego\RequestInsurance\Models\RequestInsurance;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

use OpenTelemetry\API\Trace\Propagation\TraceContextValidator;

use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;

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
        return $collection->map(function (RequestInsurance $requestInsurance) {
            return $this->extractSpanContextFromRequestInsurance($requestInsurance);
        })->filter(function (SpanContextInterface $spanContext) {
            return $spanContext->isValid();
        });
    }

    private function extractSpanContextFromRequestInsurance(RequestInsurance $requestInsurance): SpanContextInterface
    {
        return self::extractImpl($requestInsurance->headers, ArrayAccessGetterSetter::getInstance());
    }

    // Copied from OpenTelemetry SDK, working on a refactor
    private static function extractImpl($carrier, PropagationGetterInterface $getter): SpanContextInterface
    {
        $traceparent = $getter->get($carrier, 'traceparent');

        if ($traceparent === null) {
            return SpanContext::getInvalid();
        }

        // traceParent = {version}-{trace-id}-{parent-id}-{trace-flags}
        $pieces = explode('-', $traceparent);

        // If the header does not have at least 4 pieces, it is invalid -- restart the trace.
        if (count($pieces) < 4) {
            return SpanContext::getInvalid();
        }

        [$version, $traceId, $spanId, $traceFlags] = $pieces;

        /**
         * Return invalid if:
         * - Version is invalid (not 2 char hex or 'ff')
         * - Trace version, trace ID, span ID or trace flag are invalid
         */
        if ( ! TraceContextValidator::isValidTraceVersion($version)
            || ! SpanContextValidator::isValidTraceId($traceId)
            || ! SpanContextValidator::isValidSpanId($spanId)
            || ! TraceContextValidator::isValidTraceFlag($traceFlags)
        ) {
            return SpanContext::getInvalid();
        }

        // Return invalid if the trace version is not a future version but still has > 4 pieces.
        $versionIsFuture = hexdec($version) > hexdec('00');

        if (count($pieces) > 4 && ! $versionIsFuture) {
            return SpanContext::getInvalid();
        }

        // Only the sampled flag is extracted from the traceFlags (00000001)
        $convertedTraceFlags = hexdec($traceFlags);
        $isSampled = ($convertedTraceFlags & TraceFlags::SAMPLED) === TraceFlags::SAMPLED;

        // Only traceparent header is extracted. No tracestate.
        return SpanContext::createFromRemoteParent(
            $traceId,
            $spanId,
            $isSampled ? TraceFlags::SAMPLED : TraceFlags::DEFAULT
        );
    }

    private static function hookBatchProcessing(CachedInstrumentation $instrumentation)
    {
        hook(
            RequestInsuranceWorker::class,
            'processHttpRequestChunk',
            static function ($object, $params) use ($instrumentation) {
                $spanBuilder = $instrumentation->tracer()->spanBuilder('Process batch of http requests');

                $spanContexts = $this->getLinksFromCollection($params[0]);

                $spanContexts->each(function (SpanContextInterface $spanContext) use ($spanBuilder) {
                    $spanBuilder->addLink($spanContext);
                });

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
