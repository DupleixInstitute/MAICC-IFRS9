<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: permissions for admin modules that were routed but never
 * seeded, leaving live 403s (Financial Periods was a dead menu item for
 * every user, admin included; Currencies, linked from Settings, likewise).
 * Grants to internal staff roles.
 *
 * Also the EIR module's own permissions (spec v3 section 12.1, phase P1):
 * eir.view for the read-only screens, eir.run for calculations and runs,
 * eir.export for downloads and eir.govern for the Governance Centre. Until
 * the other EIR routes move off the broad Settings permission, only
 * eir.view and eir.govern are enforced.
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
            'currencies.index' => 'Currencies',
            'currencies.create' => 'Currencies',
            'currencies.update' => 'Currencies',
            'currencies.destroy' => 'Currencies',
            'eir.view' => 'EIR & Revenue Recognition',
            'eir.run' => 'EIR & Revenue Recognition',
            'eir.export' => 'EIR & Revenue Recognition',
            'eir.govern' => 'EIR & Revenue Recognition',
        ];

        $names = [];
        foreach ($permissions as $name => $module) {
            $permission = Permission::findOrCreate($name, 'web');
            $dirty = false;
            if (empty($permission->module)) {
                $permission->module = $module;
                $dirty = true;
            }
            if (empty($permission->display_name)) {
                $action = str_contains($name, '.') ? ucfirst(substr($name, strrpos($name, '.') + 1)) : '(module)';
                $permission->display_name = trim($module . ' ' . $action);
                $dirty = true;
            }
            if ($dirty) {
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
