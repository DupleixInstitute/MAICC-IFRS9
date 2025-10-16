<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            "name" => 'Admin',
            "gender" => 'male',
            "email" => 'admin@localhost.com',
            "password" => Hash::make('password'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),

        ]);
        $admin = Role::findByName('admin');
        $user->assignRole($admin);
    }
}
