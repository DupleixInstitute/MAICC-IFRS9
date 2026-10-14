<?php

/**
 * User Manual chapter: Financial Modelling: the ECL (Ticket #011 rewrite; the
 * suite's group name, 'ECL Processing' until the system audit of 9 October
 * 2026, finding M16).
 * Loaded by HelpContentSeeder from database/seeders/data/help_user_content.
 * Documentation audit of 14 October 2026: the three ECL screens re-read
 * against the code, and the Mega Farm Programme article added.
 */
return [

    'Financial Modelling: the ECL' => [

        'Running an ECL calculation' => [
            'body' => '<p>The expected credit loss (ECL) calculation turns the loan book, the PD, the LGD and the forward-looking adjustment into a provision. For every loan of the month and scope you choose it works out exposure at default (EAD) times the PD over the stage horizon times the LGD, writes the result to the loan, and saves a summary row per stage. On the discounted basis it also runs the time-phased engine, which discounts each month\'s expected shortfall at the loan\'s locked effective interest rate (EIR): that discounted figure is the allowance MAIIC books at the month-end. To reach the form, open the sidebar and choose <b>Risk &amp; regulatory</b>, then <b>ECL Processing</b>, then <b>ECL Calculation</b>, and click <b>Run ECL calculation</b>. The page is titled <b>Run ECL calculation</b>.</p>'
                . '<p>Run it only after the month is built, staged, and the PD and LGD are applied (and the forward-looking route has run, for the PD after FLI). A run with a missing input is refused, never filled with zero.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>The line <code>Choose the month, the portfolio or sector, and the PD, LGD and measurement basis, then run the calculation</code> and, at the top right, <b>Back to ECL results</b>.</li>'
                . '<li><b>What to calculate</b>: <b>Loan book month</b> (a month picker, required), <b>Calculate by</b> (<code>Portfolio</code> or <code>Sector</code>), then <b>Portfolio</b> (<code>Select a portfolio</code>) or <b>Sector</b> (<code>Select a sector</code>, listed as code and name). One run covers one portfolio or one sector; run each in turn.</li>'
                . '<li><b>Inputs</b>: <b>PD to use</b> (<code>PD before forward-looking adjustment</code>, the default, or <code>PD after forward-looking adjustment</code>, the reported basis) and <b>LGD to use</b> (<code>Customer LGD</code>, <code>Collection LGD</code>, the default, or <code>Both (customer x collection)</code>).</li>'
                . '<li><b>Measurement basis</b>: two cards. <b>Discounted ECL, at the EIR</b> (<code>Discounts the expected loss at the approved, locked effective interest rate. The undiscounted amount is kept as a check.</code>) and <b>Undiscounted ECL</b> (<code>PD x LGD x exposure with no interest rate applied. Use it for comparison and transition analysis.</code>, the default). With the discounted card chosen, an amber note reads <code>Loans without a locked EIR or a usable remaining term stay visible as unresolved exceptions.</code></li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Run ECL calculation</b> (calculator icon; <code>Calculating...</code> with a spinner while it runs): starts the run.</li>'
                . '<li><b>Cancel</b> and <b>Back to ECL results</b>: return to the results without running.</li>'
                . '</ul>'
                . '<p>Who may run it: any signed-in user; the page checks no permission. Two controls protect the figures: a locked reporting period refuses the run, and only one run per month, level and scope can be in progress at a time.</p>'
                . '<h4>How the figures are built</h4>'
                . '<ul>'
                . '<li><b>Stage</b>: the post-qualitative stage the staging run wrote.</li>'
                . '<li><b>PD used</b>: Stage 1 carries the 12-month PD over the shorter of twelve months and the remaining term; Stage 2 carries the lifetime PD, 1 minus (1 minus PD) to the power of the remaining months over 12; Stage 3 is 100 percent. A loan with no remaining term is given twelve months.</li>'
                . '<li><b>EAD</b>: carrying amount plus undrawn commitment times utilisation, never below nil.</li>'
                . '<li><b>Discounted basis</b>: the time-phased engine projects each loan month by month under the approved scenario set, discounts each month at the locked EIR, weights the scenarios, and writes the discounted ECL beside the undiscounted one. A defaulted (Stage 3) loan with no approved recovery plan is measured at the reporting date, so it needs no rate. A loan with no locked EIR is left unresolved and named; it is never given a rate.</li>'
                . '</ul>'
                . '<h4>To run the ECL</h4>'
                . '<ol>'
                . '<li>Choose the <b>Loan book month</b>, for example the month-end being reported.</li>'
                . '<li>Choose <b>Calculate by</b> and the portfolio or sector.</li>'
                . '<li>Choose <b>PD to use</b> and <b>LGD to use</b> as the approved methodology says.</li>'
                . '<li>Choose <b>Discounted ECL, at the EIR</b> for the booked figure, or <b>Undiscounted ECL</b> for comparison.</li>'
                . '<li>Click <b>Run ECL calculation</b> and wait for the results page.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<ul>'
                . '<li>Each loan in scope gets its ECL and, on the discounted basis, its discounted ECL, discounting effect, rate, rate source, horizon and discount status. An undiscounted run clears any discounted figure an earlier run left on those loans.</li>'
                . '<li>A summary row per stage is saved with the loans, EAD, average PD and LGD used, undiscounted and discounted ECL and the basis; it shows on the <b>Run summary by stage</b> tab.</li>'
                . '<li>The month is marked as having an ECL, which puts it in the dashboard and the IFRS 9 Reports period lists, and the run is written to the audit trail.</li>'
                . '<li>The banner reads <code>ECL calculated at portfolio level in N minutes for (yyyy-mm).</code>, followed on the discounted basis by <code>Discounted ECL calculated for N loan(s); N remain unresolved.</code></li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>ECL not calculated for (yyyy-mm): N loan(s) with exposure have no stage, PD (pd_prefli) or LGD, for example (contracts). Run staging, the PD and LGD engines (and the forward-looking route for pd_post_fli) first.</code></li>'
                . '<li><code>An ECL calculation for (yyyy-mm) (portfolio) is already running. Please try again once it finishes.</code></li>'
                . '<li><code>Discounted ECL not calculated for (yyyy-mm): no approved scenario set covers the reporting period ... no proxy is applied.</code></li>'
                . '<li><code>Reporting period (yyyy-mm) is locked: the ECL cannot be written. ...</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The run is refused for missing PD or LGD</b>: apply the PD (monthly matrix or internal grades) and the cumulative LGD for the month and scope, and for the PD after FLI run the forward-looking route.</li>'
                . '<li><b>Many loans unresolved on the discounted basis</b>: they have no approved and locked EIR. Resolve them on EIR Calculations (Blockers), then run again.</li>'
                . '<li><b>The Mega Farm portfolio</b>: do not run it here. The programme is measured on the Mega Farm Programme screen with its own PD and LGD.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Risk & regulatory, then ECL Processing, then ECL Calculation, and click Run ECL calculation.',
                'Choose the Loan book month.',
                'Choose Calculate by and the portfolio or sector.',
                'Choose PD to use and LGD to use to match the approved methodology.',
                'Choose the measurement basis: Discounted ECL, at the EIR for the booked figure, Undiscounted ECL for comparison.',
                'Click Run ECL calculation and wait for the results page.',
                'Check the banner for unresolved loans, then the stage totals on Run summary by stage.',
            ],
            'images' => [
                'ecl-create' => 'Run ECL calculation: Loan book month, Calculate by, the portfolio or sector, PD to use, LGD to use and the two measurement-basis cards',
            ],
            'routes' => ['expected-credit-loss.create', 'expected-credit-loss.calculation'],
        ],

        'ECL results' => [
            'body' => '<p>The <b>ECL Calculation</b> page shows how each loan\'s ECL was built and what the saved run recorded, and exports it. Open the sidebar and choose <b>Risk &amp; regulatory</b>, then <b>ECL Processing</b>, then <b>ECL Calculation</b>. It opens on the latest month that has an ECL, and on the discounted tab when that month was discounted. Any signed-in user can open it; the page checks no permission.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> ECL Calculation, with the line <code>How each loan\'s ECL was built: EAD x PD over the horizon x LGD, with the PD before and after the forward-looking adjustment</code>.</li>'
                . '<li><b>Six figure cards</b> for the filtered loans, in MWK (millions as <code>m</code>, billions as <code>bn</code>; hover for the full figure): <b>EAD (MWK)</b>; <b>ECL before FLI</b>; <b>FLI effect</b> (signed, with overlays in the tooltip); <b>ECL undiscounted</b>; <b>ECL booked (discounted)</b>, the allowance MAIIC books at the month-end, which becomes <b>ECL discounted (part of the book)</b> with <code>N of N loans</code> when not every loan with an ECL in view is discounted (unresolved loans, or Mega Farm programme loans, which carry their own undiscounted figure), and shows a dash when the run was undiscounted; and <b>Coverage</b> (<code>booked</code> ECL over EAD when every loan is discounted, otherwise <code>undiscounted</code>).</li>'
                . '<li><b>Three tabs</b>, each with a count: <b>Undiscounted ECL</b>, <b>Discounted ECL</b> and <b>Run summary by stage</b>.</li>'
                . '<li>An information line under the filters: what the tab shows, <code>Amounts in MWK.</code>, then the month\'s saved run (for example <code>December 2025 run:</code> with a <code>Portfolio: (name)</code> badge, <code>Discounted at EIR</code> or <code>Undiscounted</code>, and the time), or gold <code>No ECL run saved for (month)</code>; and, in amber, why the ECL before FLI is not shown, or <code>N loan(s) could not be discounted: check their approved EIR and remaining term.</code></li>'
                . '<li><b>Undiscounted ECL tab</b> columns: <b>Contract / stage</b> (contract, stage badge, customer), <b>EAD</b>, <b>PD before FLI</b>, <b>FLI adj.</b> (red when it raises the PD, green when it lowers it), <b>PD after FLI</b>, <b>PD used</b> (hover for the horizon, for example <code>Stage 2: lifetime PD over 48 months</code>), <b>LGD</b>, <b>ECL before FLI</b>, <b>ECL</b> (amber when it no longer equals EAD x PD used x LGD, tooltip <code>... run the ECL again</code>) and <b>Coverage</b>.</li>'
                . '<li><b>Discounted ECL tab</b> columns: <b>Contract / stage</b>, <b>EAD</b>, <b>PD after FLI</b>, <b>LGD</b>, <b>Undiscounted ECL</b>, <b>EIR</b> (the locked effective annual rate; hover for its basis: <code>Fixed, original rate</code>, <code>Floating, original rate used as proxy</code>, <code>Calculated, not approved</code>, <code>Not calculated</code> or <code>No EIR</code>), <b>Horizon</b> (in months), <b>Discounted ECL</b>, <b>Discounting effect</b> and <b>Discount status</b>. The time-phased engine writes the status <code>CALCULATED_TIME_PHASED</code> (discounted) or <code>TIME_PHASED_UNRESOLVED</code> (could not be discounted); the badge shows these codes as they are. <code>Not requested</code> means the run was undiscounted.</li>'
                . '<li>Both loan tabs end with a <b>Total, N loans</b> row and are paged fifteen loans at a time.</li>'
                . '<li><b>Run summary by stage</b> tab columns: <b>Calculated for</b> (<code>Portfolio: (name)</code> or <code>Sector: (name)</code>), <b>Stage</b>, <b>Loans</b>, <b>EAD (MWK)</b>, <b>Average PD</b>, <b>Average LGD</b>, <b>Undiscounted ECL (MWK)</b>, <b>Discounted ECL (MWK)</b>, <b>Coverage</b>, <b>Basis</b> (<code>Undiscounted</code>, <code>Discounted</code> or <code>Discounted, some unresolved</code>) and <b>Run at</b>, with a <b>Total</b> row.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<p>One row: <b>Year</b>, <b>Month</b>, <b>Portfolio</b> (<code>All portfolios</code>), <b>Stage</b> (<code>All stages</code>, Stage 1 to 3), <b>Product group</b> and <b>Sector</b> (those present in the month shown), and the search box <code>Search contract or customer</code> with the magnifier (<code>Search</code>) or Enter. <b>Clear</b> resets them. The figure cards and totals follow the filters.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li>Round <b>?</b> (<code>Help for this screen</code>): opens this article.</li>'
                . '<li><b>Run ECL calculation</b> (calculator): opens the run form.</li>'
                . '<li><b>ECL reconciliation</b> (scales, gold): opens a window with <b>Portfolio</b>, <b>Start period</b>, <b>End period</b>, <b>Movement of</b> (<code>ECL</code> or <code>Carrying amount</code>), <b>Report type</b> (<code>Summary</code> or <code>Detailed</code>), <b>Detailed section</b> (<code>New loans</code>, <code>Derecognised loans</code>, <code>Stage transitions</code>), <b>Cancel</b> and <b>Generate report</b> (<code>Preparing...</code>). The movement report opens in a new tab.</li>'
                . '<li><b>Export report</b> (file icon): opens <b>Export ECL report</b>.</li>'
                . '<li><b>Projection audit</b> (chart icon): opens the time-phased projection evidence.</li>'
                . '</ul>'
                . '<h4>To export the results</h4>'
                . '<ol>'
                . '<li>Click <b>Export report</b>.</li>'
                . '<li>Choose the <b>Portfolio</b> (<code>All portfolios</code>) and the <b>Reporting period</b> (it starts on the month in view).</li>'
                . '<li>Choose the <b>Mode</b>: <code>Summary by stage</code> (stage, total EAD, PD, LGD and total ECL) or <code>Total loan book (loan level)</code>, then tick the <b>Columns</b> wanted (<b>Select all</b>, <b>Clear all</b>).</li>'
                . '<li>Click <b>Export</b>. A CSV named <code>ecl_report_(yyyy-mm)_(mode).csv</code> opens in a new tab. <b>Cancel</b> or the x closes the window.</li>'
                . '</ol>'
                . '<h4>The discounted month-end ECL</h4>'
                . '<p>The booked allowance is the discounted ECL (IFRS 9 5.5.17): the time-phased engine\'s expected shortfalls discounted at each loan\'s locked EIR and weighted by the approved scenarios. The undiscounted ECL stays on every loan as the engine measure and the check. The difference between the two is the discounting effect (Discounted ECL tab), and the time-phased projection also runs the exposure down the repayment schedule, so the projected undiscounted shortfall on the Projection audit can differ from the ECL undiscounted card here. Reports and the dashboard read the discounted figure where it exists.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No ECL results for this period and these filters. Pick another month, clear the filters, or run the ECL calculation.</code></li>'
                . '<li><code>No ECL run has been saved for (month). Use Run ECL calculation to create one.</code></li>'
                . '<li><code>Choose the reporting period to export.</code>; <code>Choose a portfolio, a start period and an end period.</code>; <code>The end period must be after the start period.</code>; <code>Choose which detailed section to produce.</code></li>'
                . '<li>Amber reasons for a missing build-up, for example <code>The saved ECL was run on the PD before FLI, so no forward-looking effect is booked. Run the ECL on the PD after FLI to include it.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The discounted card reads part of the book</b>: some loans in view were not discounted (check the amber count) or the view includes Mega Farm programme loans. Filter to a MAIIC portfolio to see its booked total.</li>'
                . '<li><b>ECL before FLI shows a dash</b>: the PD, LGD or book changed after the last run; the amber line says how many loans no longer match. Run the ECL again.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Risk & regulatory, then ECL Processing, then ECL Calculation.',
                'Choose the Year and Month, and narrow by portfolio, stage, product group, sector or contract.',
                'Read the six figure cards, especially ECL booked (discounted) and Coverage.',
                'Open the Discounted ECL tab to check the EIR, horizon and discount status of each loan.',
                'Open Run summary by stage to see what the saved run recorded.',
                'Click Export report, choose the mode and click Export to download the CSV.',
            ],
            'images' => [
                'ecl' => 'ECL Calculation: the six figure cards, the Undiscounted, Discounted and Run summary tabs, the filter row and the loan table',
            ],
            'routes' => ['expected-credit-loss.index', 'expected-credit-loss.reports', 'expected-credit-loss.summary'],
        ],

        'ECL projections (time-phased)' => [
            'body' => '<p>The <b>ECL Projection Audit</b> shows the month-by-month working behind a discounted ECL run, so a reviewer or auditor can see how each discounted figure was built. For each contract the engine projects the exposure, the marginal PD and the LGD month by month under each approved scenario, works out the expected shortfall, discounts it at the contract\'s locked EIR and weights it by the scenario weight. Open <b>Risk &amp; regulatory</b>, then <b>ECL Processing</b>, then <b>ECL Calculation</b>, and click <b>Projection audit</b>. Any signed-in user may open it.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> ECL Projection Audit, with the line <code>The month-by-month working behind a discounted ECL run: marginal PD x LGD x exposure, scenario weights and discounting at the locked EIR</code>, and <b>Back to ECL results</b> at the top right.</li>'
                . '<li><b>Four figure cards</b> for the run shown: <b>Contracts calculated</b>, <b>Unresolved</b>, <b>Undiscounted ECL (MWK)</b> and <b>Discounted ECL (MWK)</b>.</li>'
                . '<li><b>Contract totals</b> tab (with a count): <b>Contract</b>, <b>Stage</b>, <b>Undiscounted ECL (MWK)</b> (the scenario-weighted projected shortfall), <b>Discounted ECL (MWK)</b> and <b>Horizon</b> (years). Fifteen per page.</li>'
                . '<li><b>Projection evidence</b> tab (with a count): <b>Contract</b>, <b>Scenario</b>, <b>Month</b>, <b>Date</b>, <b>Opening EAD</b>, <b>Marginal PD</b>, <b>LGD</b>, <b>Shortfall</b>, <b>Discount factor</b> and <b>Weighted, discounted</b>. One row per contract, scenario and month, fifteen per page.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<p><b>Run</b> (the last twenty discounted runs, shown as <code>(yyyy-mm) - run (first 8 characters)</code>; the newest by default), <b>Scenario</b> (<code>All scenarios</code>, or one with its weight), <b>Contract</b> (<code>Contract number</code>) and the <b>Apply</b> button.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Back to ECL results</b>, <b>Apply</b>, the two tab names, and the page links. On the empty page, <b>Run ECL calculation</b> opens the run form.</li>'
                . '</ul>'
                . '<h4>How to read a row</h4>'
                . '<ul>'
                . '<li><b>Opening EAD</b> is the exposure at the start of the month; for a performing loan it falls as the schedule repays, for a Stage 3 loan it does not amortise.</li>'
                . '<li><b>Marginal PD</b> is the chance of default in that month alone.</li>'
                . '<li><b>Shortfall</b> is the cash expected not to be received that month; <b>Discount factor</b> comes from the locked EIR; <b>Weighted, discounted</b> is shortfall times factor times the scenario weight. The sum of that column for a contract is its discounted ECL.</li>'
                . '<li>Stage 1 projects twelve months; Stage 2 the remaining life; a Stage 3 loan with an approved recovery plan places its shortfall on the plan dates, otherwise it is measured at the reporting date.</li>'
                . '</ul>'
                . '<h4>What happens next</h4>'
                . '<p>Nothing is written from this page; it is evidence. The discounted ECL it explains is the figure on the Discounted ECL tab and in the reports.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No time-phased ECL run yet</code> and <code>This page fills in after an ECL calculation is run on the discounted basis with approved scenario assumptions. Run one from the ECL Calculation page, choosing "Discounted ECL, at the EIR".</code></li>'
                . '<li><code>No contracts in this run.</code> and <code>No projection lines match these filters.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>A contract is missing</b>: it was unresolved (no locked EIR), so there is no projection. Check its discount status on the ECL results.</li>'
                . '<li><b>Undiscounted here differs from ECL undiscounted on the results page</b>: the projection runs the exposure down the schedule; the results page shows the engine measure at the reporting date.</li>'
                . '</ul>',
            'steps' => [
                'Open Risk & regulatory, then ECL Processing, then ECL Calculation, and click Projection audit.',
                'Choose the Run (and a Scenario or Contract if wanted) and click Apply.',
                'On Contract totals, find the contract and note its stage, discounted ECL and horizon.',
                'Open Projection evidence and read the contract\'s rows scenario by scenario.',
                'Add the Weighted, discounted column for the contract and confirm it equals its discounted ECL.',
            ],
            'images' => [
                'ecl-projections' => 'ECL Projection Audit: the four run figures, the Run, Scenario and Contract filters and the Contract totals and Projection evidence tabs',
            ],
            'routes' => ['expected-credit-loss.projections'],
        ],

        'Mega Farm Programme' => [
            'body' => '<p>The Mega Farm programme is Government money that MAIIC runs on the Government\'s behalf. Its loans sit on the programme\'s own general ledger codes (1050301 to 1050307) and most of them are in default, so they are kept out of MAIIC\'s own models: the PD segmentation leaves the programme\'s portfolio out (a portfolio that mixes programme and MAIIC loans stops the PD run until the book is split), the forward-looking build-up shows the programme as its own line, and the programme is staged, given a PD and an LGD, and provided on this screen. MAIIC\'s allowance is its share of the programme ECL, as the governed scope states it (as shipped, 5 percent on net). To reach the screen, open the sidebar and choose <b>Risk &amp; regulatory</b>, then <b>ECL Processing</b>, then <b>Mega Farm Programme</b>.</p>'
                . '<p>Who may act: any signed-in user can view the screen; only users with the <b>eir.govern</b> permission see and may use <b>Run programme ECL</b>. The three programme settings change only in the Governance Centre, where one person proposes and a second approves.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Mega Farm Programme, with the line <code>Government money MAIIC manages on its behalf: the programme\'s loans, their staging and PD, and MAIIC\'s share of the ECL</code>.</li>'
                . '<li><b>Settings line</b>: a green <code>Settings approved</code> or gold <code>Settings not yet approved</code> badge; <b>Scope</b> (for example <code>Out of EIR engine; in ECL module; 5 percent share on net</code>), <b>PD method</b> (for example <code>Agricultural-sector PD scaled to the programme</code>) and <b>Scalar ceiling</b> (for example <code>40 times the sector PD</code>); while not approved, in amber, <code>Runs use the seeded settings and are system figures, not a MAIIC approval.</code>; and the link <b>Change in the Governance Centre</b>.</li>'
                . '<li><b>Four figure cards</b> for the month chosen: <b>Loans in (yyyy-mm)</b>, <b>Gross carrying amount (MWK)</b>, <b>Stage 3 (N loans)</b> (the Stage 3 carrying amount) and <b>MAIIC\'s ECL, last run</b> (the share written to the loans by the last successful run; a declined run changes nothing). Amounts show in millions (<code>m</code>) or billions (<code>bn</code>); hover for the full figure.</li>'
                . '<li><b>By scheme</b> tab: <b>Scheme</b> (product group), <b>GL</b>, <b>Loans</b>, <b>Carrying (MWK)</b>, <b>Stage 3 (MWK)</b>, <b>Average PD</b>, <b>MAIIC ECL (MWK)</b> and a <b>Total</b> row.</li>'
                . '<li><b>By stage</b> tab: <b>Stage</b> (green Stage 1, gold Stage 2, red Stage 3, grey <code>Not staged</code>), <b>Loans</b>, <b>Carrying (MWK)</b>, <b>MAIIC ECL (MWK)</b> and <b>Coverage</b> (MAIIC ECL over carrying amount).</li>'
                . '<li><b>Runs</b> tab (the last twenty runs, every month): the note <code>Each run records its scope, method, scalar, LGD and share, or why it was declined. Nothing is filled in when a method\'s conditions are not met.</code> and the columns <b>Run</b>, <b>Period</b>, <b>Scope</b>, <b>Method</b>, <b>Loans</b>, <b>Gross (MWK)</b>, <b>Programme ECL</b>, <b>Share</b>, <b>MAIIC ECL</b>, <b>Basis</b> and <b>Run by</b> (name, or <code>System</code>, with the time). <b>Basis</b> shows a red <code>Declined</code> badge and the reason, or <code>PD: Stage 1 N%, Stage 2 N%, Stage 3 100.00%</code>, the scalar applied with the measured scalar and the ceiling, and the LGD. Fifteen per page.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Period</b> drop-down (top right): the months that hold programme loans, newest first. Choosing one reloads the screen for it.</li>'
                . '<li><b>Run programme ECL</b> (calculator, eir.govern only): asks <code>Run the Mega Farm ECL for (yyyy-mm)?</code> (<code>The programme ECL is calculated under the settings in force and saved as a new run.</code>) with <b>Cancel</b> and <b>Run</b>. There is no second approval of a run; the control sits on the settings, which are maker-checker in the Governance Centre, and on the locked period, which refuses a run.</li>'
                . '<li><b>Change in the Governance Centre</b>: opens the Governance Centre (eir.govern).</li>'
                . '<li>The tab names <b>By scheme</b>, <b>By stage</b> and <b>Runs</b>, each with a count.</li>'
                . '</ul>'
                . '<h4>How the programme ECL is measured</h4>'
                . '<ul>'
                . '<li><b>Loans</b>: those on the programme GLs (or a product group starting Mega Farm) in the month. Every loan must carry a stage; the Mega Farm staging class puts a loan in Stage 3 from 91 days past due.</li>'
                . '<li><b>PD</b> by the governed method. <code>Agricultural-sector PD scaled to the programme</code>: the average PD before FLI of MAIIC\'s own agricultural loans for each stage, times a measured scalar (the share of last year\'s non-defaulted programme balance that is now in Stage 3, over the sector Stage 1 PD), capped at the ceiling; Stage 3 is 100 percent. <code>Seasonal cohort default rate with a benchmark prior</code> needs two or more seasons of books; <code>Transition matrix on the programme history</code> needs twelve or more months; <code>Expert judgement with overlay</code> needs an approved overlay. A method whose condition is not met is declined with the reason; nothing is invented.</li>'
                . '<li><b>LGD</b>: the programme\'s own cohort workout, following the Stage 3 programme loans of twelve months earlier to the month. With no programme portfolio or no Stage 3 cohort, the run is declined.</li>'
                . '<li><b>ECL</b>: for each loan, EAD times the stage PD times the LGD is the programme ECL; MAIIC\'s ECL is that times the share. The run writes the PD (before and after FLI, the same), the LGD, the EAD and MAIIC\'s ECL to each loan, marked as the Mega Farm programme route. The programme ECL is not discounted, so on the ECL Calculation page these loans appear with an undiscounted ECL only.</li>'
                . '</ul>'
                . '<h4>To run the programme ECL</h4>'
                . '<ol>'
                . '<li>Make sure the month is built and staged, and the PD is applied to MAIIC\'s own agricultural book for the month.</li>'
                . '<li>Choose the month in the <b>Period</b> drop-down.</li>'
                . '<li>Click <b>Run programme ECL</b> and confirm.</li>'
                . '<li>Read the banner and the new line on the <b>Runs</b> tab; check <b>By scheme</b> and <b>By stage</b>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Every run, declined or not, is saved with its basis and written to the audit trail (<code>Mega Farm ECL Run</code>). MAIIC\'s share on the loans is included in the month\'s ECL totals and reports as the programme line. No run is a MAIIC approval until the CFO confirms the programme settings in the Governance Centre (the gold badge turns green).</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No Mega Farm loans in any loan book yet</code> with <code>The programme\'s loans arrive with the monthly E-Banker extracts. Once a month is loaded, its loans, staging and ECL appear here.</code></li>'
                . '<li><code>No programme loans in (yyyy-mm).</code> and <code>No run yet. Use Run programme ECL to calculate the selected month.</code></li>'
                . '<li>Success: <code>Mega Farm (yyyy-mm): programme ECL N, MAIIC share 5% = N.</code></li>'
                . '<li>Declined (red banner): <code>Declined: (reason)</code>, for example <code>N programme loans carry no stage for (yyyy-mm): run staging first</code>, <code>no PD on MAIIC\'s own agricultural book for the period: run the PD engine first</code>, <code>the programme\'s default rate cannot be measured: no earlier Mega Farm book to compare with</code> or <code>the programme has no portfolio of its own: run ifrs9:segment-portfolios so its LGD can be measured on its own loans</code>.</li>'
                . '<li>A locked month: <code>Reporting period (yyyy-mm) is locked: the Mega Farm programme ECL cannot be written. ...</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>No Run programme ECL button</b>: your role lacks eir.govern, or no month holds programme loans.</li>'
                . '<li><b>Every run is declined</b>: read the reason in the Basis column; it names the step to run first.</li>'
                . '<li><b>Do not run the programme portfolio from ECL Calculation</b>: that screen would overwrite the programme figures with MAIIC\'s own PD and LGD.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Risk & regulatory, then ECL Processing, then Mega Farm Programme.',
                'Read the settings line: scope, PD method, scalar ceiling and whether they are approved.',
                'Choose the month in the Period drop-down.',
                'With the eir.govern right, click Run programme ECL and confirm.',
                'Read the banner and the Basis of the new line on the Runs tab.',
                'Check the By scheme and By stage tabs for MAIIC ECL and coverage.',
            ],
            'images' => [
                'megafarm' => 'Mega Farm Programme: the settings line, the four figure cards and the By scheme, By stage and Runs tabs with the Period drop-down and Run programme ECL',
            ],
            'routes' => ['megafarm.index', 'megafarm.run'],
        ],

    ],

];
