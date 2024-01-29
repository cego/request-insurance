<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Cego\RequestInsurance\OpenTelemetry\RequestInsuranceInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(RequestInsuranceInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    return;
}

RequestInsuranceInstrumentation::register();
