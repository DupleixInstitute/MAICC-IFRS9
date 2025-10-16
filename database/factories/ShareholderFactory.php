<?php

namespace Database\Factories;

use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ShareholderFactory extends Factory
{
    protected $model = Shareholder::class;

    public function definition(): array
    {
        return [
            'client_id' => $this->faker->randomNumber(),
            'name' => $this->faker->name(),
            'gender' => $this->faker->word(),
            'date' => Carbon::now(),
            'dob' => Carbon::now(),
            'shares' => $this->faker->randomFloat(),
            'itc_ref_no' => $this->faker->word(),
            'itc_ref_date' => Carbon::now(),
            'number_of_paid_debts' => $this->faker->randomNumber(),
            'number_of_defaulted_debts' => $this->faker->randomNumber(),
            'number_of_judgements' => $this->faker->randomNumber(),
            'number_of_trace_alerts' => $this->faker->randomNumber(),
            'blacklisted' => $this->faker->randomNumber(),
            'fraud_alert' => $this->faker->randomNumber(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'created_by_id' => User::factory(),
        ];
    }
}
