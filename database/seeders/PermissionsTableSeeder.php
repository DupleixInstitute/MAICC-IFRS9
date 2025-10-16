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
                'name' => 'clients.shareholders.index',
                'module' => 'Clients',
                'display_name' => 'View Client Shareholders',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.shareholders.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Shareholders',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.shareholders.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Shareholders',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.shareholders.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Shareholders',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.balance_sheet.index',
                'module' => 'Clients',
                'display_name' => 'View Client Balance Sheet',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.balance_sheet.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Balance Sheet',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.balance_sheet.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Balance Sheet',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.balance_sheet.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Balance Sheet',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.income_statement.index',
                'module' => 'Clients',
                'display_name' => 'View Client Income Statement',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.income_statement.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Income Statement',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.income_statement.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Income Statement',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.income_statement.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Income Statement',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.poters_five_forces_analysis.index',
                'module' => 'Clients',
                'display_name' => 'View Client Poters Five Forces Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.poters_five_forces_analysis.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Poters Five Forces Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.poters_five_forces_analysis.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Poters Five Forces Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.poters_five_forces_analysis.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Poters Five Forces Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.ratio_analysis.index',
                'module' => 'Clients',
                'display_name' => 'View Client Ratio Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.ratio_analysis.create',
                'module' => 'Clients',
                'display_name' => 'Create Client Ratio Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.ratio_analysis.update',
                'module' => 'Clients',
                'display_name' => 'Update Client Ratio Analysis',
                'guard_name' => 'web',
            ],
            [
                'name' => 'clients.ratio_analysis.destroy',
                'module' => 'Clients',
                'display_name' => 'Delete Client Ratio Analysis',
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
                'name' => 'locations',
                'module' => 'Locations',
                'display_name' => 'Access Locations',
                'guard_name' => 'web',
            ],
            [
                'name' => 'locations.index',
                'module' => 'Locations',
                'display_name' => 'View Locations',
                'guard_name' => 'web',
            ],
            [
                'name' => 'locations.create',
                'module' => 'Locations',
                'display_name' => 'Create Locations',
                'guard_name' => 'web',
            ],
            [
                'name' => 'locations.update',
                'module' => 'Accounting',
                'display_name' => 'Update Locations',
                'guard_name' => 'web',
            ],
            [
                'name' => 'locations.destroy',
                'module' => 'Locations',
                'display_name' => 'Delete Locations',
                'guard_name' => 'web',
            ],

            [
                'name' => 'loans',
                'module' => 'Loans',
                'display_name' => 'Access Loans Module',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.index',
                'module' => 'Loans',
                'display_name' => 'View Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.view_assigned_loan_applications_only',
                'module' => 'Loans',
                'display_name' => 'View Assigned Loan Applications Only',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.create',
                'module' => 'Loans',
                'display_name' => 'Create Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.update',
                'module' => 'Loans',
                'display_name' => 'Update Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.assign_approver',
                'module' => 'Loans',
                'display_name' => 'Assign Loan Applications Approvers',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.view_approvals',
                'module' => 'Loans',
                'display_name' => 'View Loan Applications Approvals',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.approve',
                'module' => 'Loans',
                'display_name' => 'Approve Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.recommend',
                'module' => 'Loans',
                'display_name' => 'Recommend Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.resend',
                'module' => 'Loans',
                'display_name' => 'Resend returned Loan Applications(notify previous approvers)',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.applications.destroy',
                'module' => 'Loans',
                'display_name' => 'Delete Loan Applications',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.approval_stages.index',
                'module' => 'Loans',
                'display_name' => 'View Loan Approval Stages',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.approval_stages.create',
                'module' => 'Loans',
                'display_name' => 'Create Loan Approval Stages',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.approval_stages.update',
                'module' => 'Loans',
                'display_name' => 'Update Loan Approval Stages',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.approval_stages.destroy',
                'module' => 'Loans',
                'display_name' => 'Delete Loan Approval Stages',
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
            [
                'name' => 'loans.scoring_attributes.index',
                'module' => 'Loans',
                'display_name' => 'View Scoring Attributes',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.scoring_attributes.create',
                'module' => 'Loans',
                'display_name' => 'Create Scoring Attributes',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.scoring_attributes.update',
                'module' => 'Loans',
                'display_name' => 'Update Scoring Attributes',
                'guard_name' => 'web',
            ],
            [
                'name' => 'loans.scoring_attributes.destroy',
                'module' => 'Loans',
                'display_name' => 'Delete Scoring Attributes',
                'guard_name' => 'web',
            ],
            [
                'name' => 'communication',
                'module' => 'Communication',
                'display_name' => 'Access communication Module',
                'guard_name' => 'web',
            ],
            [
                'name' => 'communication.sms_gateways.index',
                'module' => 'Communication',
                'display_name' => 'View SMS Gateways',
                'guard_name' => 'web',
            ],
            [
                'name' => 'communication.sms_gateways.create',
                'module' => 'Communication',
                'display_name' => 'Create SMS Gateways',
                'guard_name' => 'web',
            ],
            [
                'name' => 'communication.sms_gateways.update',
                'module' => 'Communication',
                'display_name' => 'Update SMS Gateways',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.sms_gateways.destroy',
                'module' => 'Communication',
                'display_name' => 'Delete SMS Gateways',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.campaigns.index',
                'module' => 'Communication',
                'display_name' => 'View Campaigns',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.campaigns.create',
                'module' => 'Communication',
                'display_name' => 'Create Campaigns',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.campaigns.update',
                'module' => 'Communication',
                'display_name' => 'Update Campaigns',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.campaigns.destroy',
                'module' => 'Communication',
                'display_name' => 'Delete Campaigns',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.templates.index',
                'module' => 'Communication',
                'display_name' => 'View Templates',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.templates.create',
                'module' => 'Communication',
                'display_name' => 'Create Templates',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.templates.update',
                'module' => 'Communication',
                'display_name' => 'Update Templates',
                'guard_name' => 'web'
            ],
            [
                'name' => 'communication.templates.destroy',
                'module' => 'Communication',
                'display_name' => 'Delete Templates',
                'guard_name' => 'web'
            ],
            //reports
            [
                'name' => 'reports',
                'module' => 'Reports',
                'display_name' => 'Access Reports',
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
            ]


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
