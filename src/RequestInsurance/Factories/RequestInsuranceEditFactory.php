<?php

namespace Cego\RequestInsurance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RequestInsuranceEditFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RequestInsuranceEdit::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'required_number_of_approvals' => 1,
            'old_priority'                 => 9999,
            'new_priority'                 => 9999,
            'old_url'                      => '127.0.0.1',
            'new_url'                      => '127.0.0.1',
            'old_method'                   => 'GET',
            'new_method'                   => 'GET',
            'old_headers'                  => '[]',
            'new_headers'                  => '[]',
            'old_payload'                  => '',
            'new_payload'                  => '',
            'old_encrypted_fields'         => null,
            'new_encrypted_fields'         => null,
            'admin_user'                   => '',
            'applied_at'                   => null,
        ];
    }
}
