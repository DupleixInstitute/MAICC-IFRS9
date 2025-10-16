<?php

namespace Database\Factories;

use App\Models\ReferringPractitioner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ReferringPractitionerFactory extends Factory
{
    protected $model = ReferringPractitioner::class;

    public function definition(): array
    {
        return [
            'created_by_id' => $this->faker->randomNumber(),
            'name' => $this->faker->name(),
            'gender' => $this->faker->randomElement(['male','gender']),
            'practice_name' => $this->faker->name(),
            'practice_number' => $this->faker->word(),
            'mobile' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
