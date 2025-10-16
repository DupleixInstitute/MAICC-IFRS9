<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('roles')->insert([
            [

                'name' => 'admin',
                'display_name' => 'Administrator',
                'guard_name' => 'web',
                'access_days' => '[]',
                'is_system' => 1,
            ],
            [
                'name' => 'client',
                'display_name' => 'Client',
                'guard_name' => 'web',
                'access_days' => '[]',
                'is_system' => 1,
            ],
        ]);
        //assign role permissions
        $admin = Role::findByName('admin');
        $admin->syncPermissions(Permission::all());

    }
}
