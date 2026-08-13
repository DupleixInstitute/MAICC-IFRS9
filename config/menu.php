<?php

/*
|--------------------------------------------------------------------------
| MAIIC IFRS 9 - contract-aligned navigation
|--------------------------------------------------------------------------
| Groups mirror the Schedule 1 solution components of the MAIIC-Dupleix
| implementation agreement (data onboarding, collateral, EIR, IFRS 9 model
| setup, ECL engine, reports, audit trail, dashboard, administration).
|
| Duplication rules applied (nav audit, Aug 2026):
|  - The four report/export screens that lived under "Customer & Loan Data"
|    (Disbursements, Loan Book Reconciliation, Loan Book Export, ECL Export)
|    are reports - they live under Reports, next to the hub.
|  - ECL Reconciliation (the richest reconciliation page) is surfaced; it was
|    previously reachable only through the hidden legacy /report hub.
|  - The EIR trio (Accounting Rules -> Schedule Intake -> Fee Classification)
|    is one pipeline and sits together in one group.
|  - Early Warning System & AI Executive Commentary are tiles INSIDE the
|    IFRS 9 Reports hub - they are not duplicated as menu items.
|  - Regression Analysis is forward-looking model fitting; it lives under
|    the Forward-Looking Model group, not a separate Analytics group.
|  - The IFRS 9 Reports hub contains the full 30-report catalogue (incl. the
|    Sensitivity/stress tile); the standalone Stress Testing page is the
|    loan-level engine and is kept as a separate Reports entry.
|
| The sidebar renderer (Jetstream/DropdownMenu.vue) is recursive with
| accordion behaviour, so groups may nest. Every leaf points to a real
| registered route. The legacy /report (reports.index) is intentionally
| NOT linked.
*/

// $download=true => the route returns a file (e.g. PDF). The sidebar must
// render it as a plain <a>, not an Inertia <Link>, or the SPA hangs trying
// to parse the binary as an Inertia response.
$leaf = fn ($name, $route, $icon = 'circle', $download = false) => [
    'name' => $name, 'icon' => $icon, 'route' => $route, 'route_check' => $route,
    'permissions' => '', 'dropdown' => false, 'children' => [], 'order' => 0,
    'download' => $download,
];
$group = fn ($name, $icon, $children, $order) => [
    'name' => $name, 'icon' => $icon, 'route' => '', 'permissions' => '',
    'dropdown' => true, 'children' => $children, 'order' => $order,
];

return [
    'admin' => [

        [
            'name' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard',
            'route_check' => 'dashboard', 'permissions' => '', 'dropdown' => false,
            'children' => [], 'order' => 0,
        ],

        $leaf('Workspace', 'workspace.index', 'tasks'),

        // Contract Schedule 1 "Reports". The hub carries the full 30-report
        // catalogue; only distinct operational pages sit beside it. The two
        // CSV export screens are NOT listed: Loan Book and ECL Calculation
        // pages already carry their own export buttons.
        $group('Reports', 'chart-bar', [
            $leaf('IFRS 9 Reports', 'ifrs9-reports.index'),
            $leaf('ECL Reconciliation', 'reports.ecl-reconciliation'),
            $leaf('Loan Book Reconciliation', 'reports.loan-book-reconciliation'),
            $leaf('Disbursements (Vintage)', 'reports.disbursement-report'),
            $leaf('Stress Testing', 'stress-testing.index'),
        ], 1),

        $group('Portfolio Setup', 'database', [
            $leaf('Loan Portfolios', 'portfolios.index'),
            $leaf('Sector Types', 'industry_types.index'),
            $leaf('Product Groups', 'groups.index'),
        ], 1),

        $group('Customer & Loan Data', 'users', [
            $leaf('Clients', 'clients.index'),
            $leaf('Loan Book', 'loan_applications.loan-book'),
            $leaf('Imports', 'imports.index'),
        ], 2),

        $group('Collateral Management', 'building', [
            $leaf('Collateral Register', 'collateral.register.index'),
            $leaf('Collateral Types', 'collateral.types.index'),
            $leaf('Collateral Allocation', 'collateral.allocations.index'),
        ], 3),

        // One pipeline: rules suggest -> intake imports -> classification applies
        // maker/checker. Kept together (contract: EIR module).
        $group('EIR & Revenue Recognition', 'percent', [
            $leaf('Accounting Rules', 'eir-accounting-rules.index'),
            $leaf('Schedule Intake', 'eir-intake.index'),
            $leaf('Fee Classification', 'eir-fee-classification.index'),
        ], 4),

        $group('IFRS 9 Model Setup', 'chart-line', [
            $group('Staging & SICR Rules', 'circle', [
                $leaf('Quantitative Thresholds', 'stageing-rules.index'),
                $leaf('SICR Groups Setup', 'sicr-groups.index'),
                $leaf('SICR Alert Items', 'sicr-items.index'),
                $leaf('SICR Trigger Alerts', 'sicr-triggers.index'),
            ], 0),
            $group('PD Model Setup', 'circle', [
                $leaf('Transition Profiles', 'transition-profiles.index'),
                $leaf('Monthly Probability', 'transition-matrices.index'),
                $leaf('Cumulative Probability', 'transition-matrix-cummulative.index'),
                $leaf('Internal Grades', 'internal-grading.profiles'),
            ], 1),
            $group('LGD Model Setup', 'circle', [
                $leaf('Monthly LGD', 'loss-given-default.index'),
                $leaf('Cumulative LGD', 'lgd-cummulative.index'),
            ], 2),
            $group('Forward-Looking Model', 'circle', [
                $leaf('Macro Elements', 'macro-statistics.index'),
                $leaf('Scenario Profiles', 'scenarios.profiles'),
                $leaf('Weighted Forecast', 'macro-forecast-weighted.index'),
                $leaf('Credit Loss Data', 'credit-loss-data.index'),
                $leaf('Adjusted Forecast', 'forecasting.manual'),
                $leaf('Regression Analysis', 'regression.index'),
            ], 3),
            $group('Management Overlays', 'circle', [
                $leaf('Economic Scenarios', 'fli.scenarios.index'),
                $leaf('External Calculations', 'fli.external.index'),
                $leaf('Calculation History', 'fli.external.list'),
            ], 4),
        ], 5),

        $group('ECL Processing', 'check', [
            $leaf('ECL Calculation', 'expected-credit-loss.index'),
        ], 6),

        // Contract deliverables 5-7: user manual now, administrator/technical
        // manuals and the installation guide join here as they are finalised.
        $group('System Documentation', 'book-open', [
            $leaf('User Manual', 'help.index'),
        ], 7),

        $group('Administration', 'cog', [
            $leaf('User Management', 'users.index'),
            $leaf('Roles & Permissions', 'users.roles.index'),
            $leaf('Financial Periods', 'accounting.financial_periods.index'),
            $leaf('Audit Trail', 'audit-trail.index'),
            $leaf('Support Tickets', 'tickets.index'),
            $leaf('Settings', 'settings.index'),
        ], 8),

    ],
    'member' => [],
];
