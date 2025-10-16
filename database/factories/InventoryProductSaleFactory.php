<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
                            'sale_date' =>  $this->faker->date(),
                            'sale_time' =>  $this->faker->time(),
                            'inventory_warehouse_id' => $this->faker->randomDigit(),
                            'items' =>$this->faker->randomElement, 
                            'status' => $this->faker->text(),
        ];
    }
}
