<?php

namespace Cego\RequestInsurance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RequestInsuranceEditApprovalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RequestInsuranceApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'request_insurance_edit_id' => RequestInsuranceEdit::factory(),
            'approver_admin_user'       => '',
        ];
    }
}
