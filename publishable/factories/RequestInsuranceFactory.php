<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use Cego\RequestInsurance\Models\RequestInsurance;

$factory->define(RequestInsurance::class, function (Faker $faker) {
    return [
        'priority'         => $faker->numberBetween(0, 9999),
        'url'              => $faker->url,
        'method'           => $faker->randomElement(['post', 'get', 'delete', 'patch', 'put']),
        'headers'          => '{"Content-type":"application/json","Accept":"application/json"}',
        'payload'          => '{}',
        'response_headers' => '{"Content-type":"application/json","Accept":"application/json"}',
        'response_body'    => '{}',
        'response_code'    => $faker->randomElement([200, 300, 400, 500]),
        'completed_at'     => null,
        'abandoned_at'     => null,
        'paused_at'        => null,
        'locked_at'        => null,
        'retry_count'      => 0,
        'retry_factor'     => 2,
        'retry_cap'        => 3600,
        'retry_at'         => null
    ];
});
