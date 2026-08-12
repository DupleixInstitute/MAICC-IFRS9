<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The platform was converted from a credit-scoring / loan-origination app
 * to an IFRS 9 ECL & EIR system. This removes the credit-scoring-era roles
 * and permissions so the Roles matrix only offers IFRS 9-aligned choices,
 * and backfills display names on permissions that rendered as blank
 * checkboxes in the Roles form. Idempotent.
 *
 *   php artisan db:seed --class=LegacyPermissionsCleanupSeeder
 */
class LegacyPermissionsCleanupSeeder extends Seeder
{
    /** Permission name prefixes that belong to the credit-scoring era. */
    private const LEGACY_PREFIXES = [
        'clients.balance_sheet',
        'clients.income_statement',
        'clients.poters_five_forces_analysis',
        'clients.ratio_analysis',
        'clients.shareholders',
        'loans.applications',
        'loans.approval_stages',
        'loans.scoring_attributes',
        'communication',
        'locations',
    ];

    /** Roles from the borrower-portal era. Nothing in code references them. */
    private const LEGACY_ROLES = ['client', 'member', 'patient'];

    public function run(): void
    {
        $deleted = 0;
        foreach (self::LEGACY_PREFIXES as $prefix) {
            $query = Permission::where('name', $prefix)
                ->orWhere('name', 'like', $prefix . '.%');
            $count = $query->count();
            if ($count > 0) {
                // detach from roles/users first, then remove
                foreach ($query->get() as $permission) {
                    $permission->roles()->detach();
                    $permission->users()->detach();
                    $permission->delete();
                }
                $deleted += $count;
            }
        }

        $rolesRemoved = 0;
        foreach (self::LEGACY_ROLES as $name) {
            $role = Role::where('name', $name)->first();
            if ($role) {
                $role->users()->detach();
                $role->permissions()->detach();
                $role->delete();
                $rolesRemoved++;
            }
        }

        // Blank checkbox labels: the Roles form shows display_name, which
        // the tickets / financial-periods permissions were seeded without.
        foreach (Permission::whereNull('display_name')->orWhere('display_name', '')->get() as $permission) {
            $permission->display_name = $this->humanise($permission->name);
            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("Removed {$deleted} legacy permissions and {$rolesRemoved} legacy roles; blank permission labels backfilled.");
    }

    private function humanise(string $name): string
    {
        $parts = explode('.', $name);
        $action = count($parts) > 1 ? array_pop($parts) : null;
        $subject = str_replace(['accounting.', '_', '.'], [' ', ' ', ' '], implode('.', $parts));
        $label = ucwords(trim($subject));

        return $action ? $label . ' ' . ucfirst($action) : $label . ' (module)';
    }
}
