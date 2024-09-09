<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use Cego\RequestInsurance\OpenTelemetry\RequestInsuranceInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(RequestInsuranceInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    return;
}

if (class_exists(CachedInstrumentation::class) === false) {
    return;
}

RequestInsuranceInstrumentation::register();
