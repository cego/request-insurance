<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\RequestInsurance;
use App\RequestInsuranceLog;
use Faker\Generator as Faker;

$factory->define(RequestInsuranceLog::class, function (Faker $faker) {
    return [
        'response_headers'     => '{"Content-Type": "application/json"}',
        'response_body'        => '{}',
        'response_code'        => $faker->randomElement([200, 401, 404, 405, 422, 500]),
        'request_insurance_id' => function () {
            return factory(RequestInsurance::class)->create()->id;
        }
    ];
});
