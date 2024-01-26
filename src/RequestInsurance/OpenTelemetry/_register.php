<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Cego\RequestInsurance\OpenTelemetry\RequestInsuranceInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(RequestInsuranceInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Cego request-insurance auto-instrumentation', E_USER_WARNING);

    return;
}

RequestInsuranceInstrumentation::register();
