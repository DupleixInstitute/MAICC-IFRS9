<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'expense_type_id' =>  $this->faker->randomDigit(),
            'amount' =>  $this->faker->randomDigit(),
            'date' =>  $this->faker->date(),
            'currency_id'=> $this->faker->randomDigit(),
        ];
    }
}
