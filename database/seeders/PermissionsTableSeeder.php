<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'name' => 'users',
                'module' => 'Users',
                'display_name' => 'Access Users',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.index',
                'module' => 'Users',
                'display_name' => 'View Users',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.create',
                'module' => 'Users',
                'display_name' => 'Create Users',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.update',
                'module' => 'Users',
                'display_name' => 'Update Users',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.destroy',
                'module' => 'Users',
                'display_name' => 'Delete Users',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.roles',
                'module' => 'Users',
                'display_name' => 'Access Roles',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.roles.index',
                'module' => 'Users',
                'display_name' => 'View Roles',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.roles.create',
                'module' => 'Users',
                'display_name' => 'Create Roles',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.roles.update',
                'module' => 'Users',
                'display_name' => 'Update Roles',
                'guard_name' => 'web',
            ],
            [
                'name' => 'users.roles.destroy',
                'module' => 'Users',
                'display_name' => 'Delete Roles',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients',
                'module' => 'Clients',
                'display_name' => 'Access Clients',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.index',
                'module' => 'Clients',
                'display_name' => 'View Clients',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.create',
                'module' => 'Clients',
                'display_name' => 'Create Clients',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.update',
                'module' => 'Clients',
                'display_name' => 'Update Clients',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Clients',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.files.index',
                'module' => 'Clients',
                'display_name' => 'View Client Files',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.files.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Files',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.files.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Files',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.files.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Files',
                'guard_name' => 'web',
            ],
            [
                'name' => 'branches',
                'module' => 'Branches',
                'display_name' => 'Access Branches',
                'guard_name' => 'web',
            ],
            [
                'name' => 'branches.index',
                'module' => 'Branches',
                'display_name' => 'View Branches',
                'guard_name' => 'web',
            ],
            [
                'name' => 'branches.create',
                'module' => 'Branches',
                'display_name' => 'Create Branches',
                'guard_name' => 'web',
            ],
            [
                'name' => 'branches.update',
                'module' => 'Branches',
                'display_name' => 'Update Branches',
                'guard_name' => 'web',
            ],
            [
                'name' => 'branches.destroy',
                'module' => 'Branches',
                'display_name' => 'Delete Branches',
                'guard_name' => 'web',
            ],
            [
                'name' => 'banks',
                'module' => 'Banks',
                'display_name' => 'Access Banks',
                'guard_name' => 'web',
            ],
            [
                'name' => 'banks.index',
                'module' => 'Banks',
                'display_name' => 'View Banks',
                'guard_name' => 'web',
            ],
            [
                'name' => 'banks.create',
                'module' => 'Banks',
                'display_name' => 'Create Banks',
                'guard_name' => 'web',
            ],
            [
                'name' => 'banks.update',
                'module' => 'Banks',
                'display_name' => 'Update Banks',
                'guard_name' => 'web',
            ],
            [
                'name' => 'banks.destroy',
                'module' => 'Banks',
                'display_name' => 'Delete Banks',
                'guard_name' => 'web',
            ],
            [
                'name' => 'chart_of_accounts',
                'module' => 'Accounting',
                'display_name' => 'Access Chart of Accounts',
                'guard_name' => 'web',
            ],
            [
                'name' => 'chart_of_accounts.index',
                'module' => 'Accounting',
                'display_name' => 'View Chart of Accounts',
                'guard_name' => 'web',
            ],
            [
                'name' => 'chart_of_accounts.create',
                'module' => 'Accounting',
                'display_name' => 'Create Chart of Accounts',
                'guard_name' => 'web',
            ],
            [
                'name' => 'chart_of_accounts.update',
                'module' => 'Accounting',
                'display_name' => 'Update Chart of Accounts',
                'guard_name' => 'web',
            ],
            [
                'name' => 'chart_of_accounts.destroy',
                'module' => 'Accounting',
                'display_name' => 'Delete Chart of Accounts',
                'guard_name' => 'web',
            ],
            [
                'name' => 'industry_types',
                'module' => 'Industry Types',
                'display_name' => 'Access Industry Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'industry_types.index',
                'module' => 'Industry Types',
                'display_name' => 'View Industry Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'industry_types.create',
                'module' => 'Industry Types',
                'display_name' => 'Create Industry Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'industry_types.update',
                'module' => 'Industry Types',
                'display_name' => 'Update Industry Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'industry_types.destroy',
                'module' => 'Industry Types',
                'display_name' => 'Delete Industry Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'legal_types',
                'module' => 'Legal Types',
                'display_name' => 'Access Legal Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'legal_types.index',
                'module' => 'Legal Types',
                'display_name' => 'View Legal Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'legal_types.create',
                'module' => 'Legal Types',
                'display_name' => 'Create Legal Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'legal_types.update',
                'module' => 'Legal Types',
                'display_name' => 'Update Legal Types',
                'guard_name' => 'web',
            ],
            [
                'name' => 'legal_types.destroy',
                'module' => 'Legal Types',
                'display_name' => 'Delete Legal Types',
                'guard_name' => 'web',
            ],

            [
                'name' => 'loans',
                'module' => 'Loans',
                'display_name' => 'Access Loans Module',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.products.index',
                'module' => 'Loans',
                'display_name' => 'View Loan Products',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.products.create',
                'module' => 'Loans',
                'display_name' => 'Create Loan Products',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.products.update',
                'module' => 'Loans',
                'display_name' => 'Update Loan Products',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.products.destroy',
                'module' => 'Loans',
                'display_name' => 'Delete Loan Products',
                'guard_name' => 'web',
            ],
            //reports
            [
                'name' => 'reports',
                'module' => 'Reports',
                'display_name' => 'Access Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'reports.ifrs9',
                'module' => 'Reports',
                'display_name' => 'Access IFRS 9 Regulatory Reports',
                'guard_name' => 'web',
            ],
            [
                'name' => 'manual.view',
                'module' => 'Reports',
                'display_name' => 'Access IFRS 9 User Manual',
                'guard_name' => 'web',
            ],
            //audit logs
            [
                'name' => 'activity_logs',
                'module' => 'Activity Logs',
                'display_name' => 'View Activity Logs',
                'guard_name' => 'web',
            ],
            [
                'name' => 'settings',
                'module' => 'Settings',
                'display_name' => 'Access Settings',
                'guard_name' => 'web',
            ],
            [
                'name' => 'settings.configuration',
                'module' => 'Settings',
                'display_name' => 'Access Configuration Menu',
                'guard_name' => 'web',
            ],
            [
                'name' => 'settings.update',
                'module' => 'Settings',
                'display_name' => 'Update Settings',
                'guard_name' => 'web',
            ],
            [
                'name' => 'dashboard',
                'module' => 'Dashboard',
                'display_name' => 'Access Dashboard',
                'guard_name' => 'web',
            ],
            [
                'name' => 'settings.loan_application_bands',
                'module' => 'Settings',
                'display_name' => 'Access Loan Application Scoring Bands',
                'guard_name' => 'web',
            ],

            // Stageing Rules Module
            [ 'name' => 'stageing-rules', 'module' => 'Stageing Rules', 'display_name' => 'Access Stageing Rules', 'guard_name' => 'web' ],
            [ 'name' => 'stageing-rules.index', 'module' => 'Stageing Rules', 'display_name' => 'View Thresholds', 'guard_name' => 'web' ],
            [ 'name' => 'stageing-rules.store', 'module' => 'Stageing Rules', 'display_name' => 'Update Thresholds', 'guard_name' => 'web' ],

            [ 'name' => 'sicr-groups.index', 'module' => 'Stageing Rules', 'display_name' => 'View SICR Groups', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-groups.store', 'module' => 'Stageing Rules', 'display_name' => 'Create SICR Groups', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-groups.update', 'module' => 'Stageing Rules', 'display_name' => 'Update SICR Groups', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-groups.destroy', 'module' => 'Stageing Rules', 'display_name' => 'Delete SICR Groups', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-groups.import', 'module' => 'Stageing Rules', 'display_name' => 'Import SICR Groups', 'guard_name' => 'web' ],

            [ 'name' => 'sicr-items.index', 'module' => 'Stageing Rules', 'display_name' => 'View SICR Items', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-items.store', 'module' => 'Stageing Rules', 'display_name' => 'Create SICR Items', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-items.update', 'module' => 'Stageing Rules', 'display_name' => 'Update SICR Items', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-items.toggle', 'module' => 'Stageing Rules', 'display_name' => 'Toggle SICR Items', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-items.destroy', 'module' => 'Stageing Rules', 'display_name' => 'Delete SICR Items', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-items.import', 'module' => 'Stageing Rules', 'display_name' => 'Import SICR Items', 'guard_name' => 'web' ],

            [ 'name' => 'sicr-triggers.index', 'module' => 'Stageing Rules', 'display_name' => 'View SICR Triggers', 'guard_name' => 'web' ],
            [ 'name' => 'sicr-triggers.store', 'module' => 'Stageing Rules', 'display_name' => 'Create SICR Triggers', 'guard_name' => 'web' ],


        ];
        foreach ($permissions as $permission) {
            // dd($permission);
            // Check if the permission already exists in the database
            $exists = DB::table('permissions')->where('name', $permission['name'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert($permission);
            }
        }
    }
}
