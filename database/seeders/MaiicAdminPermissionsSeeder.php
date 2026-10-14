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
 * eir.export for downloads and eir.govern for the governed acts (the
 * Governance Centre, the feed builds and period locks, the take-on, the
 * write-off, restructure and reset registers, the staging thresholds, the
 * reopening of a locked EIR), and eir.fees.import for Finance's fee intake.
 * All five are enforced by controller middleware: eir.view on the EIR
 * screens, eir.export on the auditor pack and the reconciliation export,
 * eir.run on the forward-looking correlation run, eir.govern on the governed
 * acts and eir.fees.import on the fee intake and the Fee Classification
 * screen. Some EIR screens (the calculations, the accounting rules, the fee
 * classification's actions) still sit behind the broad settings permission. The Macro
 * Statistics screen has its own pair, macro.view and macro.manage (spec v4
 * section 13).
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
            // Finance loading fees by loan account without the broad settings permission (11 Oct 2026, spec v4 section 7.4):
            // EIR Data Intake for the fee type only, and Fee Classification to read the fee GL check.
            'eir.fees.import' => 'EIR & Revenue Recognition',
            // Macro Statistics (spec v4 section 13; system audit of 9 October 2026, finding M16):
            // macro.view for the screen and its previews, macro.manage for a commit or a manual entry.
            'macro.view' => 'Macro Statistics',
            'macro.manage' => 'Macro Statistics',
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
