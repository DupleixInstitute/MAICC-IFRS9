<?php

/*
|--------------------------------------------------------------------------
| MAIIC IFRS 9 - the Dupleix-suite navigation (spec v4 section 11, D24)
|--------------------------------------------------------------------------
| Six working groups, each with its own colour, in the order a finance
| officer does the work: what comes in (Data Foundation), the rules the
| engines obey (Governance Centre), the engines (Financial Modelling), the
| regulatory views (Risk & Regulatory), the watch-lists (Monitoring) and
| what goes out (Report Hub), then System Documentation and Administration.
|
| Three rules behind the placement (spec 11.3): a screen that captures or
| shows what came in is Data Foundation, whichever module uses it; a screen
| that sets a rule the engines obey is Governance Centre; a screen that
| produces a figure is Financial Modelling, and one that re-presents figures
| for a regulator or a reader is Risk & Regulatory or the Report Hub.
|
| The tree is the single source of truth: HandleInertiaRequests filters it
| by the user's permissions and sends it to the browser, which renders the
| panel and derives the breadcrumb from it. Routes do not change; only the
| grouping does. Every leaf names a registered route (tests/Feature/
| NavigationTest). Every group carries an accent, one of the Tailwind
| families written in full in resources/js/navAccents.js.
*/

// $download=true => the route returns a file; the sidebar renders a plain <a>.
// $permission hides the leaf from users who lack it; it must match what the
// route's controller enforces, or a user sees a link that answers 403.
$leaf = fn ($name, $route, $icon = 'circle', $download = false, $permission = '', $description = '') => [
    'name' => $name, 'icon' => $icon, 'route' => $route, 'route_check' => $route,
    'permissions' => $permission, 'dropdown' => false, 'children' => [], 'order' => 0,
    'download' => $download, 'description' => $description,
];
$group = fn ($name, $icon, $children, $order, $accent = 'slate', $description = '') => [
    'name' => $name, 'icon' => $icon, 'route' => '', 'permissions' => '',
    'dropdown' => true, 'children' => $children, 'order' => $order, 'accent' => $accent, 'description' => $description,
];

return [
    'admin' => [

        [
            'name' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard',
            'route_check' => 'dashboard', 'permissions' => '', 'dropdown' => false,
            'children' => [], 'order' => 0, 'description' => 'The book, the allowance and the open work at a glance',
        ],

        $leaf('Workspace', 'workspace.index', 'tasks', description: 'The period\'s tasks and who holds them'),

        $group('Data Foundation', 'database', [
            $leaf('Clients', 'clients.index', description: 'The borrowers and their facilities'),
            $leaf('Loan Book', 'loan_applications.loan-book', description: 'The monthly loan book, every account and month-end'),
            $leaf('Imports', 'imports.index', description: 'Files loaded by hand and what became of them'),
            $leaf('E-Banker Feed', 'eir-feed.index', 'cloud-download-alt', false, 'eir.view', 'The queries, the loads, the watermarks, the quarantine and the build (spec v4 section 6.8)'),
            $leaf('Take-on Schedules', 'eir-takeon.index', 'file-invoice', false, 'eir.view', 'The take-on workbook: blocks, mapping, fees and the build (spec v4 section 6.9)'),
            $leaf('Loan Portfolios', 'portfolios.index'),
            $leaf('Product Groups', 'groups.index'),
            $leaf('Sector Types', 'industry_types.index'),
            $leaf('Collateral Register', 'collateral.register.index'),
            $leaf('Collateral Types', 'collateral.types.index'),
            $leaf('Collateral Allocation', 'collateral.allocations.index'),
            $leaf('EIR Data', 'eir-data.index', permission: 'eir.view'),
            $leaf('Drawdowns', 'eir-drawdowns.index', permission: 'eir.view'),
            $leaf('Reference Rates', 'eir-reference-rates.index', permission: 'eir.view'),
            $leaf('Macro Statistics', 'macro-statistics.index', description: 'The macroeconomic series and their sources (spec v4 section 13)'),
        ], 1, 'teal', 'What comes in'),

        $group('Governance Centre', 'shield-alt', [
            $leaf('Governance Centre', 'eir-governance.index', 'gavel', false, 'eir.govern', 'Every governed setting, proposed by one person and approved by another (spec v4 section 4.2)'),
            $leaf('Accounting Rules', 'eir-accounting-rules.index', permission: 'settings'),
            $leaf('Fee Classification', 'eir-fee-classification.index', permission: 'settings'),
            $group('Staging & SICR Rules', 'circle', [
                $leaf('Quantitative Thresholds', 'stageing-rules.index'),
                $leaf('SICR Groups Setup', 'sicr-groups.index'),
                $leaf('SICR Alert Items', 'sicr-items.index'),
            ], 0, 'amber'),
            $leaf('Scenario Sets', 'scenarios.profiles', description: 'The scenario sets and their weights (spec v4 section 15)'),
            $leaf('Financial Periods', 'accounting.financial_periods.index'),
            $leaf('Audit Trail', 'audit-trail.index'),
        ], 2, 'amber', 'The rules and settings the engines obey'),

        $group('Financial Modelling', 'chart-line', [
            $group('PD Model', 'circle', [
                $leaf('Transition Profiles', 'transition-profiles.index'),
                $leaf('Monthly Probability', 'transition-matrices.index'),
                $leaf('Cumulative Probability', 'transition-matrix-cummulative.index'),
                $leaf('Internal Grades', 'internal-grading.profiles'),
            ], 0, 'sky'),
            $group('LGD Model', 'circle', [
                $leaf('Monthly LGD', 'loss-given-default.index'),
                $leaf('Cumulative LGD', 'lgd-cummulative.index'),
            ], 1, 'sky'),
            $group('Forward-Looking Model', 'circle', [
                $leaf('Regression Analysis', 'regression.index'),
                $leaf('Weighted Forecast', 'macro-forecast-weighted.index'),
                $leaf('Credit Loss Data', 'credit-loss-data.index'),
                $leaf('Adjusted Forecast', 'forecasting.manual'),
            ], 2, 'sky'),
            $group('Management Overlays', 'circle', [
                $leaf('Economic Scenarios', 'fli.scenarios.index'),
                $leaf('External Calculations', 'fli.external.index'),
                $leaf('Calculation History', 'fli.external.list'),
            ], 3, 'sky'),
            $leaf('ECL Calculation', 'expected-credit-loss.index', 'calculator'),
            $leaf('EIR Calculations', 'eir-calculations.index', 'percent', false, 'settings'),
            $leaf('Coverage & Blockers', 'eir-coverage.index', permission: 'eir.view'),
        ], 3, 'sky', 'The engines'),

        $group('Risk & Regulatory', 'balance-scale', [
            $leaf('Stress Testing', 'stress-testing.index'),
            $leaf('Sensitivity', 'ifrs9-reports.sensitivity'),
            $leaf('IFRS 9 Disclosure', 'ifrs9-reports.fs-disclosure'),
            $leaf('RBM Classification', 'ifrs9-reports.rbm-classification'),
            $leaf('IFRS 9 vs RBM', 'ifrs9-reports.ifrs9-vs-rbm'),
            $leaf('Concentration', 'ifrs9-reports.concentration'),
        ], 4, 'indigo', 'The regulatory views'),

        $group('Monitoring', 'bell', [
            $leaf('SICR Trigger Alerts', 'sicr-triggers.index'),
            $leaf('SICR Trigger Report', 'ifrs9-reports.sicr-trigger'),
            $leaf('Early Warning System', 'ifrs9-reports.ews'),
            $leaf('Data Quality', 'ifrs9-reports.data-quality'),
        ], 5, 'emerald', 'The watch-lists and alerts'),

        $group('Report Hub', 'chart-bar', [
            $leaf('IFRS 9 Reports', 'ifrs9-reports.index', description: 'The catalogue of thirty reports'),
            $leaf('EIR as at a Date', 'eir-as-at.index', 'calendar-day', false, 'eir.view', 'The EIR computation for any loan, or the book, as at any date (spec v4 section 6.11)'),
            $leaf('Executive Summary', 'ifrs9-reports.executive'),
            $leaf('AI Commentary', 'ifrs9-reports.ai-narrative'),
            $leaf('ECL Reconciliation', 'reports.ecl-reconciliation'),
            $leaf('GL Reconciliation (EIR)', 'eir-reconciliation.index', permission: 'eir.view'),
            $leaf('Loan Book Reconciliation', 'reports.loan-book-reconciliation'),
            $leaf('Disbursements (Vintage)', 'reports.disbursement-report'),
        ], 6, 'violet', 'What goes out'),

        $group('System Documentation', 'book-open', [
            $leaf('User Manual', 'help.index'),
            $leaf('Administrator Manual', 'help.admin'),
            $leaf('Technical Manual', 'docs.technical'),
            $leaf('Installation Guide', 'docs.installation'),
        ], 7, 'slate'),

        $group('Administration', 'cog', [
            $leaf('User Management', 'users.index'),
            $leaf('Roles & Permissions', 'users.roles.index'),
            $leaf('Support Tickets', 'tickets.index'),
            $leaf('Settings', 'settings.index'),
        ], 8, 'rose'),

    ],
    'member' => [],
];
