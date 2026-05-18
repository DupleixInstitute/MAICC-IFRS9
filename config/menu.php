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

$leaf = fn ($name, $route, $icon = 'circle') => [
    'name' => $name, 'icon' => $icon, 'route' => $route, 'route_check' => $route,
    'permissions' => '', 'dropdown' => false, 'children' => [], 'order' => 0,
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
        ], 2),

        $group('Collateral Management', 'building', [
            $leaf('Collateral Types', 'collateral.types.index'),
            $leaf('Collateral Allocation', 'collateral.allocations.index'),
        ], 3),

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
            ], 3),
            $group('Management Overlays', 'circle', [
                $leaf('Economic Scenarios', 'fli.scenarios.index'),
                $leaf('External Calculations', 'fli.external.index'),
                $leaf('Calculation History', 'fli.external.list'),
            ], 4),
        ], 4),

        $group('ECL Processing', 'check', [
            $leaf('Run ECL Calculation', 'expected-credit-loss.index'),
            $leaf('Expected Credit Loss', 'expected-credit-loss.index'),
        ], 5),

        // Single, de-duplicated Reports group. The 19 IFRS 9 reports +
        // interactive Sensitivity live inside the tabbed hub.
        $group('Reports', 'chart-bar', [
            $leaf('IFRS 9 Reports', 'ifrs9-reports.index'),
            $leaf('ECL Reconciliation', 'reports.ecl-reconciliation'),
            $leaf('Loan Book Reconciliation', 'reports.loan-book-reconciliation'),
            $leaf('Loan Book Export', 'reports.loan-book-export'),
            $leaf('ECL Export', 'reports.ecl-export'),
            $leaf('Disbursement Report', 'reports.disbursement-report'),
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
