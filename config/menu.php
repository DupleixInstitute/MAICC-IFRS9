<?php

/*
|--------------------------------------------------------------------------
| MAIIC IFRS 9 — workflow-based navigation
|--------------------------------------------------------------------------
| Organised by how the bank uses the system (setup -> data -> model ->
| ECL -> reports -> analytics -> admin), not by database tables. The
| sidebar renderer (Jetstream/DropdownMenu.vue) is recursive with
| accordion behaviour, so groups may nest. Every leaf points to a real
| registered route. The legacy /report (reports.index) is intentionally
| NOT linked. The 19 IFRS 9 reports + interactive Sensitivity live inside
| the tabbed hub, so they are not repeated in the menu.
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

        $group('Portfolio Setup', 'database', [
            $leaf('Loan Portfolios', 'portfolios.index'),
            $leaf('Sector Types', 'industry_types.index'),
            $leaf('Product Groups', 'groups.index'),
        ], 1),

        $group('Customer & Loan Data', 'users', [
            $leaf('Clients', 'clients.index'),
            $leaf('Loan Book', 'loan_applications.loan-book'),
            $leaf('Imports', 'imports.index'),
            $leaf('Disbursements', 'reports.disbursement-report'),
            $leaf('Loan Book Reconciliation', 'reports.loan-book-reconciliation'),
            $leaf('Loan Book Export', 'reports.loan-book-export'),
            $leaf('ECL Export', 'reports.ecl-export'),
            $leaf('EIR Schedule Intake', 'eir-intake.index'),
            $leaf('EIR Fee Classification', 'eir-fee-classification.index'),
        ], 2),

        $group('Collateral Management', 'building', [
            $leaf('Collateral Types', 'collateral.types.index'),
            $leaf('Collateral Allocation', 'collateral.allocations.index'),
        ], 3),

        $group('IFRS 9 Model Setup', 'chart-line', [
            $leaf('EIR Accounting Rules', 'eir-accounting-rules.index'),
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
            ], 3),
            $group('Management Overlays', 'circle', [
                $leaf('Economic Scenarios', 'fli.scenarios.index'),
                $leaf('External Calculations', 'fli.external.index'),
                $leaf('Calculation History', 'fli.external.list'),
            ], 4),
        ], 4),

        $group('ECL Processing', 'check', [
            $leaf('ECL Calculation', 'expected-credit-loss.index'),
        ], 5),

        // Reports = the IFRS 9 hub (19 reports + interactive Sensitivity, all
        // inside the tabbed hub) + the downloadable manual. Operational exports
        // and the duplicate ECL/Disbursement reconciliations were moved out /
        // removed so this group is not a dumping ground.
        $group('Reports', 'chart-bar', [
            $leaf('IFRS 9 Reports', 'ifrs9-reports.index'),
            $leaf('Stress Testing', 'stress-testing.index', 'bolt'),
            $leaf('User Manual', 'manual.view', 'book-open'),
        ], 6),

        $group('Analytics', 'chart-line', [
            $leaf('Early Warning System', 'ifrs9-reports.ews'),
            $leaf('AI Executive Commentary', 'ifrs9-reports.ai-narrative'),
            $leaf('Regression Analysis', 'regression.index'),
        ], 7),

        $group('Administration', 'cog', [
            $leaf('User Management', 'users.index'),
            $leaf('Settings', 'settings.index'),
        ], 8),

    ],
    'member' => [],
];
