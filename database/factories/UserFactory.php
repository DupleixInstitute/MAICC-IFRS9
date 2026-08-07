<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'gender' => $this->faker->randomElement($gender = ['male', 'female']),
            'mobile' => $this->faker->phoneNumber(),
            'email_verified_at' => now(),
            // Mirror the DB default explicitly: CheckIfUserIsActive reads this
            // attribute, and actingAs() uses the in-memory instance where a
            // missing attribute would read as null (= inactive, 403).
            'active' => 1,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function configure()
    {
        return $this->afterMaking(function (User $user) {
            //
        })->afterCreating(function (User $user) {
            // Assign a random EXISTING role. The previous random_int(1, 8)
            // guessed at ids and blew up whenever fewer than 8 roles existed.
            $role = Role::inRandomOrder()->first()
                ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }
}
