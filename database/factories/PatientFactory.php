<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->randomElement($gender = ['male', 'female']),
            'marital_status' => $this->faker->randomElement($maritalStatus = ['married', 'single', 'divorced', 'widowed', 'other']),
            'address' => $this->faker->address(),
            'mobile' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'dob' => $this->faker->dateTimeBetween('-100 years')->format('Y-m-d'),
        ];
    }
}
