<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductSalePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
                              'payment_type_id' =>  $this->faker->randomDigit(),
                              'amount' => $this->faker->randomDigit(),
                              'date' =>$this->faker->date(),
                              
        ];
    }
}
