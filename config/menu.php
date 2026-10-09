<?php

/*
|--------------------------------------------------------------------------
| MAIIC IFRS 9 - contract-aligned navigation, with tabbed sections
|--------------------------------------------------------------------------
| Groups mirror the Schedule 1 solution components of the MAIIC-Dupleix
| implementation agreement (data onboarding, collateral, EIR, IFRS 9 model
| setup, ECL engine, reports, audit trail, dashboard, administration).
|
| Rules (nav audit, Aug 2026, and the consolidation of 9 October 2026):
|  - Reports is one entry: the hub. Every report, reconciliation and export
|    is reached from the hub, never listed beside it.
|  - Screens that do one job together are one menu entry with tabs (a
|    $section). The tab bar is drawn by the layout from 'tabs'; each tab is
|    the screen's own route, so links and bookmarks keep working.
|  - The report-hub tiles (Early Warning, AI Commentary, Executive Summary,
|    the ifrs9-reports.* views) are never menu items.
|  - The legacy /report (reports.index) and the legacy one-click regression
|    screen (system audit of 9 October 2026, finding M6) are not linked.
|
| 'color' on a top-level item tints its sidebar icon (bright on the dark
| green). 'match' lists further route patterns that light the entry up.
| Every route named here must be registered (tests/Feature/NavigationTest).
*/

// $download=true => the route returns a file (e.g. PDF). The sidebar must
// render it as a plain <a>, not an Inertia <Link>, or the SPA hangs trying
// to parse the binary as an Inertia response.
// $permission hides the entry from users who lack it (HandleInertiaRequests
// filters the menu); it must match the permission the route's controller
// enforces, or a user sees a link that answers 403.
$leaf = fn ($name, $route, $icon = 'circle', $download = false, $permission = '') => [
    'name' => $name, 'icon' => $icon, 'route' => $route, 'route_check' => $route,
    'permissions' => $permission, 'dropdown' => false, 'children' => [], 'order' => 0,
    'download' => $download,
];
// One tab of a section. $params opens a tab inside the screen itself (for
// example eir-data.index with tab=cashflows), so one row of tabs carries
// both the screens and their inner views.
$tab = fn ($name, $route, $permission = '', $params = []) => ['name' => $name, 'route' => $route, 'permissions' => $permission, 'params' => $params];
// A menu entry whose screens are tabs. It opens the first tab the user may see.
$section = fn ($name, $tabs, $icon = 'circle') => [
    'name' => $name, 'icon' => $icon, 'route' => $tabs[0]['route'], 'route_check' => $tabs[0]['route'],
    'permissions' => '', 'dropdown' => false, 'children' => [], 'order' => 0,
    'download' => false, 'tabs' => $tabs,
];
$group = fn ($name, $icon, $children, $order) => [
    'name' => $name, 'icon' => $icon, 'route' => '', 'permissions' => '',
    'dropdown' => true, 'children' => $children, 'order' => $order,
];

return [
    'admin' => [

        ['color' => '#FBBF24'] + $leaf('Dashboard', 'dashboard', 'home'),

        ['color' => '#86EFAC'] + $leaf('Workspace', 'workspace.index', 'tasks'),

        // The hub lists every report, reconciliation and export; the screens
        // it opens light this entry up.
        ['color' => '#F6B131', 'match' => [
            'ifrs9-reports.*', 'reports.*', 'stress-testing.*', 'rbm-return.*', 'auditor-pack.*',
        ]] + $leaf('Reports', 'ifrs9-reports.index', 'chart-bar'),

        ['color' => '#38BDF8'] + $section('Portfolio Setup', [
            $tab('Loan Portfolios', 'portfolios.index'),
            $tab('Product Groups', 'groups.index'),
            $tab('Sector Types', 'industry_types.index'),
        ], 'database'),

        ['color' => '#5EEAD4'] + $group('Customer & Loan Data', 'users', [
            $leaf('Clients', 'clients.index'),
            $leaf('Loan Book', 'loan_applications.loan-book'),
            // In the order the data comes in: the file imports, the E-Banker
            // packs and the loan book built from them, then the take-on
            // workbook. The feed and take-on screens open on the view named
            // by tab=; seven tabs is the most one row carries.
            $section('Imports & Feeds', [
                $tab('Imports', 'imports.index'),
                $tab('E-Banker Loads', 'eir-feed.index', 'eir.view', ['tab' => 'loads']),
                $tab('Loan Book Builds', 'eir-feed.index', 'eir.view', ['tab' => 'builds']),
                $tab('Periods Built', 'eir-feed.index', 'eir.view', ['tab' => 'periods']),
                $tab('Query Register', 'eir-feed.index', 'eir.view', ['tab' => 'queries']),
                $tab('Take-on Blocks', 'eir-takeon.index', 'eir.view', ['tab' => 'blocks']),
                $tab('Take-on Population', 'eir-takeon.index', 'eir.view', ['tab' => 'population']),
            ]),
        ], 2),

        ['color' => '#FDBA74'] + $section('Collateral Management', [
            $tab('Collateral Register', 'collateral.register.index'),
            $tab('Collateral Allocation', 'collateral.allocations.index'),
            $tab('Collateral Types', 'collateral.types.index'),
        ], 'building'),

        // One pipeline: rules suggest -> intake imports -> classification applies
        // maker/checker. Kept together (contract: EIR module).
        ['color' => '#A3E635'] + $group('EIR & Revenue Recognition', 'percent', [
            // In the order the data comes in: the contracts, their cash flows,
            // the tranches drawn, the PLR they reprice from, then the schedule
            // check. The GL reconciliation sits with the EIR calculations.
            $section('EIR Data', [
                $tab('Contract Master', 'eir-data.index', 'eir.view', ['tab' => 'contracts']),
                $tab('Cash Flows', 'eir-data.index', 'eir.view', ['tab' => 'cashflows']),
                $tab('Drawdowns', 'eir-drawdowns.index', 'eir.view'),
                $tab('Reference Rates', 'eir-reference-rates.index', 'eir.view'),
                $tab('Schedule Review', 'eir-data.index', 'eir.view', ['tab' => 'schedules']),
            ]),
            $section('EIR Rules', [
                $tab('Accounting Rules', 'eir-accounting-rules.index', 'settings'),
                $tab('Fee Classification', 'eir-fee-classification.index', 'settings'),
            ]),
            // One row: the calculations, the three views of what blocks the
            // rest of the book, then the EIR as at a date and the GL check.
            $section('EIR Calculations', [
                $tab('Calculations', 'eir-calculations.index', 'settings'),
                $tab('Blockers', 'eir-coverage.index', 'eir.view', ['tab' => 'blockers']),
                $tab('Coverage by Portfolio', 'eir-coverage.index', 'eir.view', ['tab' => 'portfolios']),
                $tab('Largest Facilities', 'eir-coverage.index', 'eir.view', ['tab' => 'facilities']),
                $tab('EIR as at a Date', 'eir-as-at.index', 'eir.view'),
                $tab('GL Reconciliation', 'eir-reconciliation.index', 'eir.view'),
            ]),
            $leaf('Governance Centre', 'eir-governance.index', permission: 'eir.govern'),
        ], 4),

        ['color' => '#C084FC'] + $group('IFRS 9 Model Setup', 'chart-line', [
            $section('Staging & SICR Rules', [
                $tab('Quantitative Thresholds', 'stageing-rules.index'),
                $tab('SICR Groups', 'sicr-groups.index'),
                $tab('SICR Alert Items', 'sicr-items.index'),
                $tab('SICR Trigger Alerts', 'sicr-triggers.index'),
            ]),
            $section('PD Model', [
                $tab('Transition Profiles', 'transition-profiles.index'),
                $tab('Monthly Probability', 'transition-matrices.index'),
                $tab('Cumulative Probability', 'transition-matrix-cummulative.index'),
                $tab('Internal Grades', 'internal-grading.profiles'),
            ]),
            $section('LGD Model', [
                $tab('Monthly LGD', 'loss-given-default.index'),
                $tab('Cumulative LGD', 'lgd-cummulative.index'),
            ]),
            // The macro inputs first, then the model that uses them.
            $section('Macro Statistics', [
                $tab('Dashboard', 'macro-statistics.index', 'macro.view', ['tab' => 'dashboard']),
                $tab('Variables', 'macro-statistics.index', 'macro.view', ['tab' => 'variables']),
                $tab('Data Entry', 'macro-statistics.index', 'macro.view', ['tab' => 'entry']),
                $tab('Scenario Assumptions', 'macro-statistics.index', 'macro.view', ['tab' => 'scenarios']),
                $tab('Import / Export', 'macro-statistics.index', 'macro.view', ['tab' => 'import']),
            ]),
            $section('Forward-Looking Model', [
                $tab('Weighted Forecast', 'macro-forecast-weighted.index'),
                $tab('Credit Loss Data', 'credit-loss-data.index'),
                $tab('Adjusted Forecast', 'forecasting.manual'),
                $tab('Correlation Finder', 'fli-correlation.index'),
                // The regression runs on FLI Adjustments under maker-checker.
                $tab('Regression (FLI Adjustments)', 'fli-adjustments.index'),
            ]),
            $section('Scenarios & Overlays', [
                $tab('Scenario Sets', 'scenario-sets.index'),
                $tab('Scenario Profiles', 'scenarios.profiles'),
                $tab('Manual Overlays', 'fli-overlays.index'),
                $tab('Economic Scenarios', 'fli.scenarios.index'),
                $tab('External Calculations', 'fli.external.index', 'reports.ifrs9'),
                $tab('Calculation History', 'fli.external.list', 'reports.ifrs9'),
            ]),
        ], 5),

        ['color' => '#F87171'] + $group('ECL Processing', 'calculator', [
            $leaf('ECL Calculation', 'expected-credit-loss.index'),
            $leaf('Mega Farm Programme', 'megafarm.index'),
        ], 6),

        // Contract Schedule 1 deliverables 5, 6 and 7. The two manuals are
        // database content (help centre); the technical manual and the
        // installation guide are repository Markdown under docs/manuals.
        ['color' => '#67E8F9'] + $group('System Documentation', 'book-open', [
            $leaf('User Manual', 'help.index'),
            $leaf('Administrator Manual', 'help.admin'),
            $leaf('Technical Manual', 'docs.technical'),
            $leaf('Installation Guide', 'docs.installation'),
        ], 7),

        ['color' => '#C4B5FD'] + $group('Administration', 'cog', [
            $section('Users & Roles', [
                $tab('Users', 'users.index'),
                $tab('Roles & Permissions', 'users.roles.index'),
            ]),
            $leaf('Financial Periods', 'accounting.financial_periods.index'),
            $section('Audit', [
                $tab('Audit Trail', 'audit-trail.index'),
                $tab('Audit Trace', 'audit-trace.index'),
                $tab('Compliance Audits', 'compliance-audits.index'),
            ]),
            $leaf('Support Tickets', 'tickets.index'),
            $leaf('Settings', 'settings.index'),
        ], 8),

    ],
    'member' => [],
];
