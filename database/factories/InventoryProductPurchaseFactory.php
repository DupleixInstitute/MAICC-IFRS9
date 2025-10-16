<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'purchase_date' =>  $this->faker->date(),
            'purchase_time' =>  $this->faker->time(),
            'inventory_warehouse_id' =>  $this->faker->randomDigit(),
            'status' =>$this->faker->text(),
        ];
    }
}
