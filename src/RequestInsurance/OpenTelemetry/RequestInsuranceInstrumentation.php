<?php

namespace Cego\RequestInsurance\OpenTelemetry;

use Throwable;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;

use function OpenTelemetry\Instrumentation\hook;

use Cego\RequestInsurance\RequestInsuranceWorker;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

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
    }
}
