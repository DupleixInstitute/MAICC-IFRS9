<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'date' => $this->faker->date(),
            'currency_id' => $this->faker->randomDigit(),
            'sponsor' =>$this->faker->name(),
            'patient_co_payer_id' =>$this->faker->randomDigit(),
        ];
    }
}
