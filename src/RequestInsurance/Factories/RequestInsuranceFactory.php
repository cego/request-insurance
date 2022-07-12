<?php

namespace Cego\RequestInsurance\Factories;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestInsuranceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RequestInsurance::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'priority'         => $this->faker->numberBetween(0, 9999),
            'url'              => $this->faker->url,
            'method'           => $this->faker->randomElement(['post', 'get', 'delete', 'patch', 'put']),
            'headers'          => '{"Content-type":"application/json","Accept":"application/json"}',
            'payload'          => '{}',
            'timeout_ms'       => null,
            'trace_id'         => Uuid::uuid6()->toString(),
            'response_headers' => '{"Content-type":"application/json","Accept":"application/json"}',
            'response_body'    => '{}',
            'response_code'    => $this->faker->randomElement([200, 300, 400, 500]),
            'retry_count'      => 0,
            'retry_factor'     => 2,
            'retry_cap'        => 3600,
            'retry_at'         => null,
            'state'            => State::READY,
            'state_changed_at' => Carbon::now('UTC'),
        ];
    }
}
