<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the DB-driven User Manual (Ticket #010) with the platform's
 * documentation, the captured screenshots and the per-page route mappings.
 * Runs only into an EMPTY manual so authored edits are never clobbered.
 *
 *   php artisan db:seed --class=HelpContentSeeder
 */
class HelpContentSeeder extends Seeder
{
    public function run(): void
    {
        if (HelpCategory::exists()) {
            $this->command?->info('Manual already has content; seeder skipped.');

            return;
        }

        $order = 0;
        foreach ($this->content() as $chapterTitle => $articles) {
            $category = HelpCategory::create([
                'title' => $chapterTitle,
                'slug' => Str::slug($chapterTitle),
                'order' => ++$order,
            ]);

            $aOrder = 0;
            foreach ($articles as $title => $spec) {
                $article = HelpArticle::create([
                    'help_category_id' => $category->id,
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'body' => $spec['body'] ?? '',
                    'order' => ++$aOrder,
                    'status' => 'published',
                    'updated_by' => 'System seed',
                ]);
                foreach (array_values($spec['steps'] ?? []) as $i => $text) {
                    $article->steps()->create(['step_no' => $i + 1, 'text' => $text]);
                }
                $iOrder = 0;
                foreach ($spec['images'] ?? [] as $file => $caption) {
                    $article->images()->create([
                        'path' => 'manual/screenshots/' . $file . '.jpg',
                        'caption' => $caption,
                        'order' => ++$iOrder,
                    ]);
                }
                foreach ($spec['routes'] ?? [] as $routeName) {
                    $article->routes()->create(['route_name' => $routeName]);
                }
            }
        }

        $this->command?->info('User Manual seeded: ' . HelpCategory::count() . ' chapters, ' . HelpArticle::count() . ' articles.');
    }

    /** @return array<string, array<string, array>> chapter => [article => spec] */
    private function content(): array
    {
        return [
            'Getting Started' => [
                'System Overview' => [
                    'body' => '<p>The MAIIC IFRS 9 platform calculates Expected Credit Loss (ECL) and Effective Interest Rate (EIR) revenue for the whole loan book, end to end: data intake, staging, PD / LGD modelling, forward-looking adjustment, the ECL run, reporting and stress testing. Every figure on every screen comes from the database for the reporting period you select; nothing is hardcoded.</p><p>Work flows left to right through the sidebar: set up portfolios, load customer and loan data, configure the IFRS 9 models, run ECL, then read the results in Reports. The Period Workspace tracks each month-end close, and Support Tickets records every enhancement and issue with a unique reference.</p>',
                ],
                'Signing In & Security' => [
                    'body' => '<p>Sign in with the email address and password issued by your administrator. The login page includes a security check (CAPTCHA): type the characters shown in the image, not case-sensitive, and use the refresh button beside it if the code is hard to read.</p><p>Passwords and optional two-factor authentication are managed from your profile menu (top-right avatar). After five failed attempts the account throttles briefly before you can retry.</p>',
                    'steps' => [
                        'Open the platform address in your browser; you land on the sign-in page.',
                        'Enter your email address and password.',
                        'Type the characters from the security-check image into the code box.',
                        'Press Sign in. You land on the IFRS 9 ECL Dashboard.',
                        'To change your password or enable two-factor authentication, open the avatar menu and choose Profile.',
                    ],
                    'images' => ['login' => 'The sign-in screen with the security check'],
                ],
                'In-System Assistance' => [
                    'body' => '<p>Wherever you see the round green question mark, click it for help written for that exact page. This manual is the full reference: searchable on screen, downloadable as a branded PDF, and illustrated with live screenshots of the system that are refreshed whenever the interface changes.</p>',
                    'routes' => ['help.index'],
                ],
            ],

            'Daily Use' => [
                'Dashboard' => [
                    'body' => '<p>The dashboard is the one-page position: total exposure (EAD), total ECL, coverage, Stage 3 exposure and weighted PD / LGD, each with a change chip against the compare-to period. The green filter bar drives the whole page: pick the reporting period, a portfolio (or all), and the period to compare against. Stage cards, the composition doughnut, the coverage trend and the Portfolio Summary all move with the filters.</p><p>On the ECL Coverage Trend, the From / To selectors default to January of the reporting year through the latest month with data; chart and table views are toggleable on each panel.</p>',
                    'images' => ['dashboard' => 'IFRS 9 ECL dashboard with period, portfolio and compare-to filters'],
                    'routes' => ['dashboard'],
                ],
                'Period Workspace' => [
                    'body' => '<p>The workspace tracks the month-end IFRS 9 close as a ten-step checklist, from loan-book import through to management sign-off, with a deep link to the right screen at every step. Progress is shown per reporting period; administrators tick steps off as they complete. Team Messages beneath the checklist keeps the conversation for each period in one place.</p>',
                    'images' => ['workspace' => 'Period workspace: close checklist and team messages'],
                    'routes' => ['workspace.index'],
                ],
                'Support Tickets' => [
                    'body' => '<p>Every enhancement request, issue and change is logged under a unique reference (for example #001) with a category, priority, assignee and a full activity trail. Both MAIIC and Dupleix follow progress here: each status change and progress note is stamped on the ticket, and the resolution is recorded when work completes.</p>',
                    'images' => ['tickets' => 'Support ticket tracker'],
                    'routes' => ['tickets.index'],
                ],
            ],

            'Customer & Loan Data' => [
                'Clients' => [
                    'body' => '<p>The client register holds the customer master used across the loan book, collateral and reporting. Search by name or identifier; each client profile links to their exposures and documents.</p>',
                    'images' => ['clients' => 'Client register'],
                    'routes' => ['clients.index'],
                ],
                'Loan Portfolios & Sector Types' => [
                    'body' => '<p>Portfolios segment the book (for example Agriculture, SME, Corporate) and drive portfolio-level ECL reporting and dashboard filtering. Sector types carry the Reserve Bank of Malawi economic-sector classification used in sector ECL and concentration reports.</p>',
                    'images' => ['portfolios' => 'Loan portfolio setup'],
                    'routes' => ['portfolios.index', 'industry_types.index'],
                ],
                'Loan Book' => [
                    'body' => '<p>The loan book lists every contract for the selected reporting period with its IFRS 9 stage, EAD, PD, LGD, ECL and coverage, in accounting format. It opens on the latest period automatically; the filter bar narrows by year, month, stage or free-text search, and the tiles above summarise the filtered book (total loans, EAD, Stage 3 exposure, coverage, ECL). Export produces the same data as CSV.</p>',
                    'images' => ['loanbook' => 'Loan book with stage, EAD, PD, LGD and ECL columns'],
                    'routes' => ['loan_applications.loan-book'],
                ],
                'Imports' => [
                    'body' => '<p>Monthly loan-book files load through Imports, which validates every row (required columns, numeric checks) before writing anything, then reports progress and errors per job. Re-running an import for a period replaces that period cleanly.</p>',
                    'steps' => [
                        'Prepare the month-end extract with the required columns (contract, customer, balances, overdue days).',
                        'Open Imports and choose the file and reporting period.',
                        'Start the import and watch the job status; open the error log if any rows are rejected.',
                        'Confirm the period appears in the Loan Book and the Dashboard.',
                    ],
                    'images' => ['imports' => 'Import job history'],
                    'routes' => ['imports.index'],
                ],
                'Collateral' => [
                    'body' => '<p>Collateral types define standard haircuts and realisation periods; the register holds the valued items per period; allocation links collateral to customer exposures, either manually or with the automatic allocator. The discounted, allocated value feeds LGD and the LGD &amp; Collateral report.</p>',
                    'images' => ['collateral' => 'Collateral allocations'],
                    'routes' => ['collateral.allocations.index', 'collateral.types.index'],
                ],
            ],

            'EIR & Revenue Recognition' => [
                'EIR Accounting Rules' => [
                    'body' => '<p>Rules classify fees as integral or non-integral to the effective interest rate, by fee type, description pattern, GL reference or cash direction, each with a written rationale. Rules follow maker-checker: they must be approved by a second person before the classification screen applies their suggestions, and editing a rule resets its approval.</p>',
                    'images' => ['eir-rules' => 'EIR accounting rules register'],
                    'routes' => ['eir-accounting-rules.index'],
                ],
                'EIR Schedule Intake' => [
                    'body' => '<p>Intake loads repayment schedules and fee files in any column layout: upload, map the columns once, save the mapping as a template, and the import runs in the background. Coverage statistics show how much of the book has schedules loaded.</p>',
                    'images' => ['eir-intake' => 'EIR schedule intake'],
                    'routes' => ['eir-intake.index'],
                ],
                'EIR Fee Classification' => [
                    'body' => '<p>The classification queue works through contract fees: filter by status, type or contract, classify each line integral or non-integral with a reason (rule suggestions pre-fill where they match), and a second person reviews. A classifier can never approve their own decision; every action lands in the audit log.</p>',
                    'images' => ['eir-fees' => 'EIR fee classification work queue'],
                    'routes' => ['eir-fee-classification.index'],
                ],
            ],

            'IFRS 9 Model Setup' => [
                'Staging & SICR Rules' => [
                    'body' => '<p>Quantitative thresholds set the days-past-due boundaries for Stages 1, 2 and 3. Qualitative SICR groups and alert items capture judgement-based triggers (sector distress, restructuring, watchlist events); raising a trigger pushes the affected contracts to the target stage in the loan book, with the pre- and post-qualitative stages kept separately for transparency.</p>',
                    'images' => ['staging' => 'Staging and SICR thresholds'],
                    'routes' => ['stageing-rules.index', 'sicr-groups.index', 'sicr-triggers.index'],
                ],
                'Transition Profiles' => [
                    'body' => '<p>Transition profiles define the PD segmentation: which slice of the book (by portfolio, product or sector) gets its own transition matrix. Profiles keep segment PDs statistically meaningful while letting distinct books behave differently.</p>',
                    'images' => ['tprofiles' => 'Transition profile definitions'],
                    'routes' => ['transition-profiles.index'],
                ],
                'Transition Matrices (PD)' => [
                    'body' => '<p>Monthly matrices measure movement between delinquency buckets from one period to the next; the cumulative view compounds them into 12-month and lifetime PD term structures. Matrices can be reviewed cell by cell, exported, and applied to the loan book to write each contract\'s PD.</p>',
                    'images' => ['tmatrix' => 'Monthly transition matrices'],
                    'routes' => ['transition-matrices.index', 'transition-matrix-cummulative.index'],
                ],
                'Loss Given Default' => [
                    'body' => '<p>LGD runs measure recovery experience on defaulted exposures, including collateral realisation (with haircuts and discounting) and collection costs; the cumulative view builds the term LGD used by the ECL engine. Payment-based recovery calculations and discounted recovery cash flows support the estimate.</p>',
                    'images' => ['lgd' => 'Loss Given Default runs'],
                    'routes' => ['loss-given-default.index', 'lgd-cummulative.index'],
                ],
                'Forward-Looking Engine' => [
                    'body' => '<p>Macro elements hold the economic series (GDP growth, inflation, exchange rate and others) with their historical values. Scenario profiles weight upside, base and downside paths; the weighted forecast combines them into the probability-weighted macro outlook; regression links macro movements to credit losses; and the resulting forward-looking factor adjusts PDs from their through-the-cycle values to point-in-time.</p>',
                    'images' => ['fli' => 'Probability-weighted macro forecast'],
                    'routes' => ['macro-statistics.index', 'macro-forecast-weighted.index', 'regression.index'],
                ],
            ],

            'ECL & Reporting' => [
                'ECL Calculation' => [
                    'body' => '<p>The ECL run computes EAD &times; PD &times; LGD per contract with the discounting convention configured for the book: 12-month ECL for Stage 1, lifetime for Stages 2 and 3. Results are written to the loan book and can be inspected loan by loan, filtered by stage, and exported with selectable columns.</p>',
                    'steps' => [
                        'Confirm staging, PD, LGD and the forward-looking adjustment are in place for the period (the Workspace checklist tracks this).',
                        'Open ECL Calculation and select the reporting period.',
                        'Run the calculation and wait for the job to complete.',
                        'Review the per-loan results and the stage totals against the dashboard.',
                        'Mark the workspace step done and move to Reports.',
                    ],
                    'images' => ['ecl' => 'ECL calculation screen'],
                    'routes' => ['expected-credit-loss.index'],
                ],
                'IFRS 9 Reports' => [
                    'body' => '<p>The reporting suite covers the full IFRS 9 pack: core ECL summaries, staging and movement (including the opening-to-closing ECL reconciliation and gross carrying movement), model component reports (PD, LGD &amp; collateral, EAD), forward-looking views, RBM prudential comparisons, financial-statement disclosure tables and data quality. Pick a section tab, then a report; only the report you open is loaded, and every report exports to PDF.</p>',
                    'images' => ['reports' => 'IFRS 9 reporting suite'],
                    'routes' => ['ifrs9-reports.index'],
                ],
                'Stress Testing' => [
                    'body' => '<p>Stress testing recomputes ECL loan by loan under your scenario, in two modes. Driver mode applies per-stage PD multipliers and LGD add-ons (agriculture-relevant presets included). Macro mode runs a macro shock through a saved regression model (or a manual slope and intercept) to an implied PD adjustment, and shows the full derivation. Results break down by stage and portfolio; scenarios can be saved, reloaded and compared.</p>',
                    'steps' => [
                        'Choose the reporting period and, optionally, a portfolio.',
                        'Pick a mode: PD / LGD drivers, or Macro scenario.',
                        'Enter the shocks (or choose a preset / regression model) and press Run Stress Test.',
                        'Review base vs stressed ECL by stage and portfolio.',
                        'Save the scenario with a name so it can be reloaded for the next committee pack.',
                    ],
                    'images' => ['stress-testing' => 'Stress testing: PD/LGD drivers and macro scenario modes'],
                    'routes' => ['stress-testing.index'],
                ],
                'Early Warning System' => [
                    'body' => '<p>The EWS surfaces forward risk signals before default: Stage 1 accounts already in arrears, facilities near their limits, and fresh Stage 1-to-2 migrations, with a ranked watchlist of the largest at-risk performing exposures and a severity flag on each.</p>',
                    'images' => ['ews' => 'Early warning system watchlist'],
                    'routes' => ['ifrs9-reports.ews'],
                ],
            ],

            'Administration' => [
                'Users, Roles & Permissions' => [
                    'body' => '<p>User Management creates and maintains accounts; Roles &amp; Permissions defines what each role can see and do through a permission matrix grouped by module. Only IFRS 9-relevant permissions exist; assign the least access each role needs. New users receive their credentials and must set their own password.</p>',
                    'images' => ['users' => 'User management', 'roles' => 'Roles and permission matrix'],
                    'routes' => ['users.index', 'users.roles.index'],
                ],
                'Settings' => [
                    'body' => '<p>Settings holds organisation details (name, logo, contacts), system preferences (timezone, currency), email for notifications, and the organisation reference data hub (branches, currencies, chart of accounts, sector types, financial periods). Changes here apply system-wide.</p>',
                    'images' => ['settings' => 'Settings hub'],
                    'routes' => ['settings.index'],
                ],
                'Maintaining This Manual' => [
                    'body' => '<p>The manual is content in the database, so administrators keep it current without a developer. Edit manual (on the User Manual page) opens the authoring screen: chapters, articles, numbered steps, page mappings for the contextual help button, and uploaded figures. The system screenshots are refreshed by one command whenever the interface changes, and the PDF is always generated from the same content.</p>',
                    'steps' => [
                        'After any interface change, run: php artisan manual:screenshots (with the app running locally).',
                        'Review the refreshed images under public/manual/screenshots and commit them.',
                        'For wording changes, open User Manual, press Edit manual, and edit the article.',
                        'Set an article to Draft to hide it from readers while you work on it.',
                    ],
                    'routes' => ['help.manage.index'],
                ],
            ],

            'Reference' => [
                'Glossary' => [
                    'body' => '<p><b>EAD</b>: Exposure at Default, the amount owed if the borrower defaults, including drawn balances and a share of undrawn commitments. <b>PD</b>: Probability of Default over 12 months (Stage 1) or lifetime (Stages 2-3). <b>LGD</b>: Loss Given Default, the share of exposure lost after recoveries and collateral. <b>ECL</b>: Expected Credit Loss, EAD &times; PD &times; LGD, discounted. <b>SICR</b>: Significant Increase in Credit Risk, the trigger moving a loan from Stage 1 to Stage 2. <b>EIR</b>: Effective Interest Rate, the rate that exactly discounts contractual cash flows (including integral fees) to the carrying amount. <b>FLI</b>: Forward-Looking Information, the macro adjustment to PDs. <b>Coverage</b>: ECL as a percentage of exposure.</p>',
                ],
                'Troubleshooting & FAQ' => [
                    'body' => '<p><b>A page shows no data.</b> Check the reporting-period filter first; most screens are period-scoped and open on the latest period with data.</p><p><b>The security code is rejected at login.</b> Codes are single-use and expire after a few minutes; press the refresh button beside the image and type the new code.</p><p><b>An import rejected rows.</b> Open the job\'s error log in Imports; each rejected row states the reason (missing column, non-numeric value). Fix the file and re-run.</p><p><b>I cannot see a menu item.</b> Your role may not have that permission; ask an administrator to review Roles &amp; Permissions.</p><p><b>The dashboard compare shows huge percentage moves.</b> Early periods hold partial books, so growth against them is naturally large; compare against a recent full month.</p>',
                ],
            ],
        ];
    }
}
