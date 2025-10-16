<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductPurchaseReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            
            'purchase_return_date' =>  $this->faker->date(),
            'inventory_warehouse_id' =>  $this->faker->randomDigit(),
            'supplier_id' => $this->faker->randomDigit() ,
            'status' =>$this->faker->text(),
        ];
    }
}
