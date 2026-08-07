<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: creates the ticketing permissions and grants them to internal
 * staff roles (everything except client/member/patient portals). Safe to run
 * repeatedly — nothing is truncated. Run with:
 *
 *   php artisan db:seed --class=TicketsPermissionsSeeder
 */
class TicketsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'tickets' => 'Support Tickets',
            'tickets.index' => 'Support Tickets',
            'tickets.create' => 'Support Tickets',
            'tickets.update' => 'Support Tickets',
            'tickets.destroy' => 'Support Tickets',
        ];

        $created = [];
        foreach ($permissions as $name => $module) {
            $permission = Permission::findOrCreate($name, 'web');
            // `module` groups the permission in the roles UI; set directly so we
            // don't depend on it being mass-assignable.
            if (empty($permission->module)) {
                $permission->module = $module;
                $permission->save();
            }
            $created[] = $name;
        }

        // Grant to all internal staff roles (exclude external portal roles).
        $excluded = ['client', 'member', 'patient'];
        Role::where('guard_name', 'web')->get()
            ->reject(fn ($role) => in_array($role->name, $excluded, true))
            ->each(fn ($role) => $role->givePermissionTo($created));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Ticketing permissions created and granted to internal staff roles.');
    }
}
