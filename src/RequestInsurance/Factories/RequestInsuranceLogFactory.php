<?php

namespace Cego\RequestInsurance\Factories;

use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Cego\RequestInsurance\Models\RequestInsuranceLog;

class RequestInsuranceLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RequestInsuranceLog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'response_headers'     => '{"Content-Type": "application/json"}',
            'response_body'        => '{}',
            'response_code'        => $this->faker->randomElement([200, 401, 404, 405, 422, 500]),
            'request_insurance_id' => RequestInsurance::factory(),
        ];
    }
}
