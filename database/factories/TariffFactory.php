<?php

namespace Database\Factories;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Factories\Factory;

class TariffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tariff::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name'=>$this->faker->name(),
            'code'=>$this->faker->randomLetter(),
            'film_code'=>$this->faker->randomLetter(),
            'type'=>$this->faker->randomElement(['lab','pharmacy','radiology','operation']),
            'amount'=>$this->faker->randomFloat(2,10,5000),
        ];
    }
}
