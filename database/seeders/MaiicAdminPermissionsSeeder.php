<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: permissions for admin modules that were routed but never
 * seeded, leaving live 403s (Financial Periods was a dead menu item for
 * every user, admin included). Grants to internal staff roles.
 *
 *   php artisan db:seed --class=MaiicAdminPermissionsSeeder
 */
class MaiicAdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting.financial_periods.index' => 'Financial Periods',
            'accounting.financial_periods.create' => 'Financial Periods',
            'accounting.financial_periods.update' => 'Financial Periods',
            'accounting.financial_periods.destroy' => 'Financial Periods',
            'accounting.financial_periods.close' => 'Financial Periods',
        ];

        $names = [];
        foreach ($permissions as $name => $module) {
            $permission = Permission::findOrCreate($name, 'web');
            if (empty($permission->module)) {
                $permission->module = $module;
                $permission->save();
            }
            $names[] = $name;
        }

        $excluded = ['client', 'member', 'patient'];
        Role::where('guard_name', 'web')->get()
            ->reject(fn ($role) => in_array($role->name, $excluded, true))
            ->each(fn ($role) => $role->givePermissionTo($names));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Admin module permissions ensured and granted to internal staff roles.');
    }
}
