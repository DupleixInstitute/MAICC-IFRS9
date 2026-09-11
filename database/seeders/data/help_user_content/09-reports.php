<?php

/**
 * User Manual chapter: Reports (Ticket #011 rewrite).
 * Loaded by HelpContentSeeder from database/seeders/data/help_user_content.
 */
return [

    'Reports' => [

        'The IFRS 9 Reports hub' => [
            'body' => '<p>The hub is the front door to the reporting suite. It holds twenty nine reports grouped into seven sections, all reading the same calculated figures, so two reports on the same period can never disagree. Open the sidebar, expand <b>Reports</b> and choose <b>IFRS 9 Reports</b>.</p><h4>What you see on the screen</h4><ul><li>The page header reads <b>IFRS 9 Reporting Suite</b> with the subtitle <b>IFRS 9 Reports</b>.</li><li>A <b>Reporting Period</b> selector with a calendar icon. It lists only periods that have a calculated ECL, newest first, because a report on a period with no calculation would be empty. If none exist it reads <b>No ECL-calculated periods</b>.</li><li>Seven coloured section tabs, each showing how many reports it contains: Core ECL, Staging and Movement, Model Components, Forward-Looking, RBM Prudential, Disclosure and Audit, and Analytics. The tabs stay visibly coloured whether or not they are selected; the active one is filled and the report cards below take its colour.</li><li>A grid of report cards. Each card shows the report title and a one-line description of what it contains. Selecting a card opens that report for the period in the selector.</li></ul><h4>What happens next</h4><p>Choosing a period here carries it into the report you open, and the report keeps it in its own period control so you can move month to month without coming back.</p><h4>Common problems</h4><ul><li><b>The period you want is not listed.</b> Only periods with a calculated ECL appear. Run the ECL calculation for that month first.</li><li><b>A report opens with no rows.</b> The period has a calculation but the underlying inputs for that particular report are missing, for example collateral allocations for the LGD and Collateral report. Each report says "No data available for this section." rather than showing a zero.</li></ul>',
            'steps' => [
                'Open Reports, then IFRS 9 Reports.',
                'Choose the reporting period in the selector at the top.',
                'Select a section tab to narrow the list, for example RBM Prudential.',
                'Select the report card you need.',
            ],
            'images' => [
                'reports' => 'The IFRS 9 Reports hub with the period selector, the seven coloured section tabs and the report cards',
            ],
            'routes' => ['ifrs9-reports.index'],
        ],

        'Reading a report page' => [
            'body' => '<p>Every report in the hub is built the same way, so once you can read one you can read all twenty nine. This article explains the furniture; the articles that follow list what each report contains.</p><h4>What you see on the screen</h4><ul><li>The report title and, under it, the subtitle that describes the report and the reporting period it is showing.</li><li>A <b>Period</b> control. Changing it reloads the same report for another month. Only periods with a calculated ECL are offered, and any value you type is checked against that list before it is used.</li><li>On the Concentration and Large Exposures report only, an extra control headed <b>Run with your own inputs</b> with a threshold box (placeholder <code>e.g. 10,25,50</code>) that sets the large-exposure threshold.</li><li>A row of KPI tiles. Each has a label, a value and, under it, a comparison line against the previous period, written as an up or down arrow with the percentage change and the period it is comparing with, or the words "no prior period" when there is nothing to compare. Tiles are coloured by meaning: green for exposure and counts, red for loss, amber for coverage.</li><li>One or more sections. Each has a heading and a table. Numeric columns are right aligned in accounting format: thousands separated, two decimals, negatives in parentheses. Percentages carry the per cent sign.</li><li>Export buttons. <b>CSV</b> downloads the visible rows straight from the browser. <b>Download PDF</b> produces the branded A4 landscape document with the MAIIC logo, the tricolour bar, the generating user and page numbers. <b>Excel</b> produces a branded workbook with the same content, where figures stay as real numbers so they remain summable.</li><li>Empty sections show <b>No data available for this section.</b> rather than a row of zeros.</li></ul><h4>What happens next</h4><p>Nothing you do on a report page changes any figure. Reports are read-only views of the calculated results, which is why they are safe to hand to an auditor.</p><h4>Common problems</h4><ul><li><b>The Excel file opens with numbers as text.</b> It should not: the exporter converts the display strings back to typed numbers. If it happens, report it with the report name attached.</li><li><b>The PDF is blank or missing glyphs.</b> Usually a server memory or font issue. Raise a support ticket naming the report and period.</li></ul>',
            'steps' => [
                'Open a report from the hub.',
                'Check the period shown in the subtitle is the one you want, and change the Period control if not.',
                'Read the KPI tiles first, including the comparison line against the prior period.',
                'Work down the sections; each table heading says what the rows are.',
                'Press CSV, Download PDF or Excel to take a copy.',
            ],
            'images' => [
                'report-executive' => 'A report page showing the period control, KPI tiles with comparison lines, sections and the export buttons',
            ],
            'routes' => ['ifrs9-reports.executive'],
        ],

        'Core ECL reports' => [
            'body' => '<p>Eight reports that answer the basic question: how much do we expect to lose, and where does it sit. All of them use one definition of exposure at default: the carrying amount plus any undrawn commitment multiplied by the utilisation rate.</p><ul><li><b>Executive Summary</b>. A one-page position: exposure, ECL, coverage and loan count as KPI tiles, then the split by stage, by portfolio and by exposure band, with the data-quality checks at the foot. Start here in a review meeting.</li><li><b>ECL Summary by Stage</b>. Total exposure, average probability of default, average loss given default and ECL for Stage 1, Stage 2 and Stage 3, with the totals row.</li><li><b>Portfolio ECL Trend</b>. ECL and coverage over time, split by portfolio, so you can see whether a movement is one segment or the whole book.</li><li><b>ECL by Sector</b>. Exposure, probability, loss rate and ECL by economic sector, matching the sector concentration disclosure in the annual report.</li><li><b>ECL by Product Group</b>. The same view by lending product group.</li><li><b>ECL by Internal Grade</b>. The A to G master scale with the exposure, average probability, average loss rate and ECL in each grade. The bands are A up to 2 per cent, B to 5, C to 10, D to 20, E to 40, F to 100 and G for default.</li><li><b>Account-Level ECL Calculation</b>. The loan-by-loan calculation trail: exposure, probability, loss rate, the multiplication, and the ECL actually booked, for the two hundred largest exposures. This is the report an auditor asks for when they want to re-perform the arithmetic.</li><li><b>Stage Allocation</b>. How every exposure was classified into Stage 1, 2 or 3, with the counts and amounts behind each stage.</li></ul><h4>Common problems</h4><ul><li><b>The account-level trail and the summary disagree.</b> The trail shows the largest two hundred exposures only; the summary covers the whole book.</li><li><b>A grade band is empty.</b> No loan carried a probability in that band for the period. That is a finding, not an error.</li></ul>',
            'images' => [
                'report-ecl' => 'ECL Summary by Stage with the KPI tiles and the per-stage table',
                'report-portfolio-trend' => 'Portfolio ECL Trend, showing ECL and coverage over time for each portfolio',
                'report-sector-ecl' => 'ECL by Sector, the view that matches the sector concentration disclosure',
                'report-product-group-ecl' => 'ECL by Product Group',
                'report-grade-ecl' => 'ECL by Internal Grade across the A to G master scale',
                'report-account-ecl' => 'The account-level ECL calculation trail',
                'report-stage-allocation' => 'Stage Allocation, with the counts and amounts behind each stage',
            ],
            'routes' => ['ifrs9-reports.executive', 'ifrs9-reports.ecl', 'ifrs9-reports.portfolio-trend', 'ifrs9-reports.sector-ecl', 'ifrs9-reports.product-group-ecl', 'ifrs9-reports.grade-ecl', 'ifrs9-reports.account-ecl', 'ifrs9-reports.stage-allocation'],
        ],

        'Staging and movement reports' => [
            'body' => '<p>Five reports that explain what changed since the previous period. They all compare the selected period with the one immediately before it in the list of calculated periods.</p><ul><li><b>SICR Trigger</b>. The exposures that a significant increase in credit risk trigger moved, and which trigger fired. Significant increase in credit risk is the test that moves a loan from Stage 1 to Stage 2.</li><li><b>Stage Migration</b>. A grid of movements between stages against the prior period: how much exposure moved from Stage 1 to Stage 2, from Stage 2 to Stage 3, and back again.</li><li><b>Opening to Closing ECL Reconciliation</b>. The bridge from the opening allowance to the closing allowance, showing transfers, remeasurement, new lending and derecognition.</li><li><b>Gross Carrying Amount Movement</b>. The same bridge for the gross book: opening, disbursements, repayments, closing.</li><li><b>ECL Charge or Release</b>. The impairment charge or release that goes to profit or loss for the period, which is the figure the income statement needs.</li></ul><h4>Common problems</h4><ul><li><b>The report says "no prior period".</b> The selected period is the earliest one with a calculation, so there is nothing to compare against.</li><li><b>The movement does not tie to the balance change.</b> Check that both periods used the same calculation level and scope; a portfolio-level run and a total-level run are different populations.</li></ul>',
            'images' => [
                'report-sicr-trigger' => 'The SICR Trigger report, naming the exposures a trigger moved and which trigger fired',
                'report-stage-migration' => 'Stage migration against the prior period',
                'report-ecl-reconciliation' => 'The opening to closing ECL reconciliation in the reports hub',
                'report-gross-movement' => 'Gross carrying amount movement: opening, disbursements, repayments, closing',
                'report-ecl-charge' => 'The ECL charge or release for the period',
            ],
            'routes' => ['ifrs9-reports.sicr-trigger', 'ifrs9-reports.stage-migration', 'ifrs9-reports.ecl-reconciliation', 'ifrs9-reports.gross-movement', 'ifrs9-reports.ecl-charge'],
        ],

        'Model component reports' => [
            'body' => '<p>Four reports that show the inputs rather than the answer, so a model reviewer can test each ingredient separately.</p><ul><li><b>PD Report</b>. The twelve-month and lifetime probabilities of default in use, by grade and by segment.</li><li><b>LGD and Collateral</b>. Recovery assumptions, the collateral cover held against each exposure and the net unsecured amount, which is the exposure less the allocated discounted collateral value, floored at zero.</li><li><b>Credit Risk Mitigation (Agri)</b>. The agricultural credit enhancements MAIIC uses, off-take agreements, warehouse receipts, group guarantees and anchor-buyer cover, set against the loss given default they support.</li><li><b>EAD and Off-Balance Sheet</b>. Exposure at default including undrawn commitments, showing the utilisation rate applied as the credit conversion factor.</li></ul><h4>Common problems</h4><ul><li><b>Net unsecured equals the full exposure.</b> No collateral has been allocated to that exposure. Allocation happens under Collateral Management, not automatically.</li><li><b>The credit conversion factor looks like one hundred per cent everywhere.</b> No utilisation rate is recorded, so full utilisation is assumed. There is no separate conversion-factor model yet.</li></ul>',
            'images' => [
                'report-pd' => 'The PD report, showing the twelve-month and lifetime probabilities in use by grade and segment',
                'report-lgd-collateral' => 'LGD and collateral cover with the net unsecured column',
                'report-crm-agri' => 'Credit Risk Mitigation (Agri), setting the agricultural enhancements against the loss given default they support',
                'report-ead' => 'Exposure at default including off-balance-sheet commitments',
            ],
            'routes' => ['ifrs9-reports.pd-report', 'ifrs9-reports.lgd-collateral', 'ifrs9-reports.crm-agri', 'ifrs9-reports.ead-report'],
        ],

        'Forward-looking reports' => [
            'body' => '<p>Two reports that show the economic assumptions behind the provision, and therefore how much of the expected loss comes from the outlook rather than from what has already happened. IFRS 9 requires an expected credit loss to reflect reasonable and supportable forward-looking information, so these two reports are how that requirement is evidenced.</p><ul><li><b>Macro Scenario and Forward-Looking</b>. The macroeconomic assumptions in force for the period and the scenarios built on them.</li><li><b>Scenario-Weighted ECL</b>. The probability-weighted expected credit loss across the base, upside and downside scenarios, with the weight applied to each and the weighted total at the foot. Each scenario column shows the loss that scenario alone would produce, so you can see the spread as well as the answer.</li></ul><h4>Reading them together</h4><ul><li>Start with Macro Scenario to confirm the assumptions in force are the ones the committee approved for the period, then read Scenario-Weighted ECL to see what those assumptions cost.</li><li>Compare the weighted figure with the base scenario alone. The gap is the price of prudence, and it is the number a reviewer will ask you to justify.</li><li>Compare a probability of default before the forward-looking adjustment with the one after it, by running the ECL calculation on both bases, to see the adjustment separately from the underlying model.</li></ul><h4>Common problems</h4><ul><li><b>The report shows placeholder rows.</b> The macro tables for the period are empty. Enter the macro elements and their values under IFRS 9 Model Setup before relying on these reports.</li><li><b>The weighted figure equals the base figure.</b> Only one scenario carries a weight. Check the scenario profile weights sum to one hundred per cent across three scenarios.</li></ul>',
            'images' => [
                'report-macro-scenario' => 'Macro Scenario and Forward-Looking, listing the assumptions in force for the period',
                'report-scenario-ecl' => 'Scenario-weighted ECL across the approved scenarios',
            ],
            'routes' => ['ifrs9-reports.macro-scenario', 'ifrs9-reports.scenario-ecl'],
        ],

        'RBM prudential reports' => [
            'body' => '<p>Six reports written for the Reserve Bank of Malawi Financial Asset Classification Directive of 2018, and for the comparison between that directive and IFRS 9. The directive bands are: Pass, nought to thirty days past due, one per cent minimum provision; Special Mention, thirty one to eighty nine days, one per cent; Substandard, ninety to one hundred and seventy nine days, twenty per cent; Doubtful, one hundred and eighty to three hundred and sixty four days, fifty per cent; Loss, three hundred and sixty five days and over, one hundred per cent.</p><ul><li><b>RBM Asset Classification</b>. Every exposure placed in its prudential class by days past due, with the minimum provision the directive requires. Its KPI tiles include the non-performing loan ratio, which is Substandard plus Doubtful plus Loss exposure divided by total exposure, and the RBM provision total against the IFRS 9 ECL.</li><li><b>IFRS 9 Stage versus RBM Mapping</b>. A cross-reference of the two systems, showing where a Stage 2 exposure sits in the prudential classes and vice versa.</li><li><b>NPL and Arrears</b>. Non-performing loans and an ageing of arrears.</li><li><b>Provision Comparison</b>. The IFRS 9 expected credit loss against the RBM prudential provision, with the shortfall or excess. Where the directive requires more than IFRS 9, the difference is the regulatory reserve.</li><li><b>Concentration and Large Exposures</b>. Single-name and portfolio concentration with the Herfindahl-Hirschman index, a standard concentration measure where a value above 2,500 counts as highly concentrated and the tile turns red. The large-exposure threshold is set by the control at the top of the page.</li><li><b>Cooperative and Anchor Linkage</b>. Correlated exposure grouped by cooperative or anchor buyer, so contagion through a single off-taker is visible. The loss estimate here is deliberately simple; full asset-correlation modelling is a separate piece of work.</li></ul><h4>Common problems</h4><ul><li><b>The NPL ratio and Stage 3 do not match.</b> They measure different things. Stage 3 is the IFRS 9 credit-impaired population; NPL is the directive\'s ninety-day classification. The IFRS 9 versus RBM report exists to explain the difference.</li><li><b>Concentration looks extreme.</b> Check the threshold in the control at the top before drawing a conclusion; a low threshold pulls many exposures into the large-exposure table.</li></ul>',
            'images' => [
                'report-rbm-classification' => 'RBM asset classification with the prudential bands and provision rates',
                'report-ifrs9-vs-rbm' => 'IFRS 9 Stage versus RBM Mapping, cross-referencing the two classification systems',
                'report-npl-arrears' => 'NPL and Arrears, with the ageing of overdue amounts',
                'report-provision-comparison' => 'IFRS 9 ECL against the RBM prudential provision',
                'report-concentration' => 'Concentration and Large Exposures with the Herfindahl-Hirschman index and the threshold control',
                'report-coop-linkage' => 'Cooperative and Anchor Linkage, grouping correlated exposure by off-taker',
            ],
            'routes' => ['ifrs9-reports.rbm-classification', 'ifrs9-reports.ifrs9-vs-rbm', 'ifrs9-reports.npl-arrears', 'ifrs9-reports.provision-comparison', 'ifrs9-reports.concentration', 'ifrs9-reports.coop-linkage'],
        ],

        'Disclosure and audit reports' => [
            'body' => '<p>Two reports aimed at the year-end file rather than at monthly management.</p><ul><li><b>Financial Statement Disclosure</b>. The IFRS 9 note tables laid out the way MAIIC\'s audited annual report presents them: gross loans, expected credit losses and net loans by Stage 1, 2 and 3 for each portfolio segment, and the movement roll-forward from opening to closing for both the gross book and the allowance. This is the report the finance team hands to the auditor.</li><li><b>Data Quality and Exceptions</b>. The integrity checks: records missing a required field, overrides applied, exposures with no probability or loss rate, and anything else that would make a figure unreliable. Read this before you circulate any other report.</li></ul><h4>Common problems</h4><ul><li><b>The disclosure tables do not match the signed accounts.</b> Check the period, the portfolio segments and whether the accounts include an adjustment made outside the platform. A presentation difference is not a calculation error, but it must be documented.</li><li><b>Data quality shows exceptions you cannot explain.</b> Raise a support ticket with the report attached rather than reporting the period.</li></ul>',
            'images' => [
                'report-fs-disclosure' => 'The financial statement disclosure tables in annual-report layout',
                'report-data-quality' => 'Data quality and exceptions',
            ],
            'routes' => ['ifrs9-reports.fs-disclosure', 'ifrs9-reports.data-quality'],
        ],

        'Analytics: Early Warning System and AI Executive Commentary' => [
            'body' => '<p>Two reports that look forward rather than back.</p><h4>Early Warning System</h4><p>A watchlist built from rules, not from a model. Its KPI tiles count the Stage 1 accounts already in arrears and their exposure, the accounts using ninety per cent or more of their facility, and the accounts that moved from Stage 1 to Stage 2 since the previous period. The watchlist table lists the forty largest exposures in Stage 1 or 2 that are past due, with a severity of <b>HIGH</b> from sixty days past due, <b>MEDIUM</b> from thirty, and <b>WATCH</b> below that. Use it to prompt a collections conversation before an exposure migrates.</p><h4>AI Executive Commentary</h4><p>A short written commentary on the position, generated from the calculated figures. It is important to be plain about what this is: the commentary is <b>rule-based</b>. The platform assembles sentences from the numbers using fixed thresholds; there is no language model and no external service involved, and the report says so itself. It covers the position (exposure, loan count, ECL, coverage), the movement against the prior period and whether that is a charge or a release, the non-performing ratio with a comment when it passes ten per cent, and the coverage with a comment when it passes fifteen per cent. It closes by stating that it supports rather than replaces management and audit judgement.</p><h4>Common problems</h4><ul><li><b>The commentary says there is no prior period.</b> The movement sentence is omitted when the selected period is the earliest calculated one.</li><li><b>The watchlist is empty.</b> No Stage 1 or 2 exposure is past due in that period, which is the good outcome.</li></ul>',
            'images' => [
                'ews' => 'The Early Warning System with its four indicators and the watchlist',
                'report-ai-narrative' => 'The rule-based executive commentary',
            ],
            'routes' => ['ifrs9-reports.ews', 'ifrs9-reports.ai-narrative'],
        ],

        'ECL reconciliation report' => [
            'body' => '<p>This is the fullest movement report in the platform: an IFRS 9 bridge between two periods for one portfolio, showing every reason the allowance changed. Open the sidebar, expand <b>Reports</b> and choose <b>ECL Reconciliation</b>.</p><h4>What you see on the screen</h4><ul><li><b>Loan Portfolio</b>, required. Only active portfolios are listed.</li><li><b>Start Period</b> and <b>End Period</b>, required. The reporting periods you are bridging between.</li><li><b>Movement Type</b>. <b>ECL Allowance</b> bridges the provision; <b>Carrying Amount</b> bridges the gross book.</li><li><b>Report Type</b>. <b>Summary</b> gives the bridge; <b>Detailed</b> lists the individual contracts behind a chosen category.</li><li><b>Detail Type</b>, shown for the detailed report: new loans, derecognised loans, or stage transitions.</li><li>A <b>Generate</b> action to build the report and an export action to download it as CSV.</li></ul><h4>The nine movement rows</h4><ol><li><b>Opening</b> balance at the start period, split across Stage 1, 2 and 3.</li><li><b>Transfers to stage 1</b>.</li><li><b>Transfers to stage 2</b>.</li><li><b>Transfers to stage 3</b>. A transfer takes the amount out of the stage the loan came from and adds it to the stage it moved to, so the three transfer rows net to nil overall.</li><li><b>Net remeasurement</b>, the change on loans that stayed in the same stage.</li><li><b>New financial assets originated</b>, loans present at the end period but not at the start.</li><li><b>Financial assets derecognised</b>, loans present at the start but not at the end.</li><li><b>Amounts written off</b>.</li><li><b>Closing</b> balance at the end period.</li></ol><p>Each row shows Stage 1, Stage 2, Stage 3 and a Total column. Opening plus every movement row should equal Closing.</p><h4>What happens next</h4><p>The CSV carries a metadata block (report, portfolio, start and end period, movement type and the export date), then the bridge. It is the working paper for the movement note in the accounts.</p><h4>Common problems</h4><ul><li><b>The written-off row is always empty.</b> A known limitation: the feed for this report does not yet supply the write-off flag, so a loan that disappears is classified as derecognised. If write-offs matter for the period, identify them separately and say so in the note. This is recorded as an issue with Dupleix.</li><li><b>Opening plus movements does not equal closing.</b> Check both periods use the same portfolio and that no loan changed portfolio between them.</li></ul>',
            'steps' => [
                'Open Reports, then ECL Reconciliation.',
                'Choose the loan portfolio, the start period and the end period.',
                'Choose the movement type: ECL Allowance or Carrying Amount.',
                'Choose Summary for the bridge, or Detailed and a detail type to see the contracts.',
                'Generate the report, check that opening plus movements equals closing, then export the CSV.',
            ],
            'images' => [
                'ecl-reconciliation' => 'The ECL reconciliation bridge between two periods',
            ],
            'routes' => ['reports.ecl-reconciliation'],
        ],

        'Loan book reconciliation' => [
            'body' => '<p>A simpler roll-forward for the gross book, used to prove that the loan book you loaded moves the way the ledger says it moved. Open the sidebar, expand <b>Reports</b> and choose <b>Loan Book Reconciliation</b>.</p><h4>What you see on the screen</h4><ul><li>Selectors for the loan portfolio, the start period and the end period.</li><li>A reconciliation table with the rows: <b>Opening Balance</b>, <b>Add: New Disbursements</b>, <b>Less: Repayments</b>, <b>Less: Write-offs</b>, then <b>Expected Closing Balance</b>, <b>Actual Closing Balance</b> and <b>Variance</b>.</li><li>An export action that downloads the same table as CSV with a header block.</li></ul><h4>How each row is worked out</h4><ul><li><b>Opening</b> and <b>Actual Closing</b> are the sum of carrying amounts at the two periods.</li><li><b>New Disbursements</b> is the closing balance of contracts that appear at the end period but not at the start.</li><li><b>Repayments</b> is the fall in balance on contracts present at both periods.</li><li><b>Write-offs</b> are contracts at the end period whose status contains "write".</li><li><b>Expected Closing</b> is opening plus disbursements less repayments less write-offs, and <b>Variance</b> is actual less expected.</li></ul><h4>Common problems</h4><ul><li><b>A variance equal to the write-off line.</b> Written-off contracts still carry a balance at the end period, so they are already inside the actual closing figure and subtracting them again creates a variance. Read the write-off row as information and explain the variance in your note.</li><li><b>Negative repayments.</b> A contract whose balance grew, usually through capitalised interest, appears as a negative repayment. That is arithmetic, not an error.</li></ul>',
            'steps' => [
                'Open Reports, then Loan Book Reconciliation.',
                'Choose the portfolio, the start period and the end period.',
                'Generate the report and read the variance line at the foot.',
                'Explain any variance against the write-off and capitalised-interest notes above.',
                'Export the CSV for the working-paper file.',
            ],
            'images' => [
                'loanbook-reconciliation' => 'The loan book roll-forward with expected and actual closing balances',
            ],
            'routes' => ['reports.loan-book-reconciliation'],
        ],

        'Disbursements (vintage) report' => [
            'body' => '<p>A vintage view: for each month of origination, how much was lent and how those loans behaved over their first three months. Open the sidebar, expand <b>Reports</b> and choose <b>Disbursements (Vintage)</b>.</p><h4>What you see on the screen</h4><ul><li>Selectors for the start period, the end period and, optionally, the portfolio.</li><li>A mode choice between <b>Summary</b> and <b>Detailed</b>.</li><li>The summary table with the columns <b>Reporting Period</b> (the month of origination), <b>Total Disbursement</b>, <b>No Of Contracts</b>, <b>Average Disbursement</b>, <b>Movement 30 days</b>, <b>Movement 60 days</b> and <b>Movement 90 days</b>. The movement columns are the outstanding balance of that cohort one, two and three months after origination.</li><li>In detailed mode, a contract breakdown with the contract identifier, the external identity, the disbursement amount, the create date and the portfolio group.</li><li>An export action producing a CSV with both blocks.</li></ul><h4>Reading it</h4><p>A cohort whose balance barely falls over three months is either an interest-only or moratorium structure, or an early-arrears problem. Compare cohorts rather than reading one in isolation.</p><h4>Common problems</h4><ul><li><b>A cohort shows no movement figures.</b> The loan book snapshots for the following months have not been loaded, so there is nothing to measure against.</li><li><b>Disbursement totals differ from the ledger.</b> The report uses the carrying amount in the month of origination, which is the IFRS 9 source of truth in the loan book, not a separate disbursement feed.</li></ul>',
            'steps' => [
                'Open Reports, then Disbursements (Vintage).',
                'Choose the start and end periods, and a portfolio if you want to narrow it.',
                'Choose Summary for the cohort table or Detailed for the contract list.',
                'Generate the report and compare the thirty, sixty and ninety day movements across cohorts.',
                'Export the CSV if you need it in the file.',
            ],
            'images' => [
                'disbursements' => 'The disbursement vintage report by origination month',
            ],
            'routes' => ['reports.disbursement-report'],
        ],

        'Stress testing' => [
            'body' => '<p>Stress testing asks what the provision would be under harsher assumptions. It recomputes the expected credit loss loan by loan rather than scaling a total, so the answer respects each loan\'s own exposure, probability and loss rate. Open the sidebar, expand <b>Reports</b> and choose <b>Stress Testing</b>.</p><h4>What you see on the screen</h4><ul><li>The page title <b>Stress Testing</b> with the panel <b>ECL Stress Scenario</b>.</li><li>Two mode buttons: <b>PD / LGD drivers</b> and <b>Macro scenario</b>.</li><li><b>Reporting Period</b> and <b>Portfolio</b> selectors; the portfolio list includes <b>All portfolios</b>.</li><li>In driver mode, a small table with a row per <b>Stage</b> and two columns: <b>PD multiplier (x)</b> and <b>LGD add-on (% pts)</b>. A multiplier of 1 leaves the probability unchanged; 1.5 raises it by half. An add-on of 5 adds five percentage points to the loss rate. Both stressed values are capped at one hundred per cent.</li><li>In macro mode, a <b>Regression model</b> selector or <b>Manual slope / intercept</b> with <b>Slope</b> and <b>Intercept</b> boxes, plus <b>Base macro value</b> and <b>Macro shock (%)</b>. The page then shows how the shock became a single probability multiplier, so the derivation is visible rather than hidden.</li><li>Results: the tiles <b>Base ECL</b> and <b>Stressed ECL</b> with the change between them, then <b>By IFRS 9 Stage</b> with the columns Stage, Accounts, Exposure, Base ECL and Stressed ECL, and <b>By Portfolio</b> with the same columns.</li><li><b>Save this scenario</b> with a <b>Name</b> (placeholder <code>e.g. 2025 severe drought</code>) and a <b>Description</b>, which stores the inputs and a snapshot of the results so the run can be produced again later.</li></ul><h4>What happens next</h4><p>Nothing is written to the loan book or to the reported provision. A stress run is an analysis, and the saved scenario is the evidence of what was tested. Note that stress testing is outside the contracted scope and is provided as an extra.</p><h4>Common problems</h4><ul><li><b>The stressed figure barely moves.</b> Check the multipliers were entered per stage; leaving them at 1 stresses nothing.</li><li><b>Macro mode gives a multiplier of one.</b> The predicted value did not change, usually because the slope is zero or the base macro value is zero.</li><li><b>Stressed ECL equals exposure on some loans.</b> The caps have bitten: probability and loss rate cannot exceed one hundred per cent.</li></ul>',
            'steps' => [
                'Open Reports, then Stress Testing.',
                'Choose the reporting period and the portfolio, or All portfolios.',
                'Choose PD / LGD drivers, then set a PD multiplier and an LGD add-on for each stage.',
                'Or choose Macro scenario, pick a regression model or enter a slope and intercept, then the base macro value and the shock.',
                'Run the scenario and read the Base ECL and Stressed ECL tiles and the two breakdown tables.',
                'Press Save this scenario, give it a name and a description, and save it for the file.',
            ],
            'images' => [
                'stress-testing' => 'The stress testing screen with the driver inputs and the base against stressed results',
            ],
            'routes' => ['stress-testing.index'],
        ],

    ],

];
