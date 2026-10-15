<?php

/**
 * User Manual chapter: Reports (Ticket #011 rewrite; documentation audit of 14 October 2026).
 * Loaded by HelpContentSeeder from database/seeders/data/help_user_content.
 *
 * One article per hub tile (or a small family of tiles), each with the screen, the buttons,
 * the downloads, the permission, the empty states and how to read it. The article titles of
 * the earlier edition are kept (HELP_SEED_REFRESH matches by chapter and title) and now carry
 * the tile they map to; new articles follow them.
 */
return [

    'Reports' => [

        'The IFRS 9 Reports hub' => [
            'body' => '<p>The Reports hub is the one place every report, reconciliation and export of the system is opened from. Open the sidebar and choose <b>Reports</b>, the entry at the top under Workspace. Anyone holding at least one of the permissions <b>reports.ifrs9</b>, <b>reports</b> or <b>eir.view</b> can open the hub, and it shows each person only the tiles their permissions allow. The old address of the reports list (/report) now opens the hub.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>Reports</b> with the line <b>Every report, reconciliation and export of the system in one place. Pick a group, then a report; most download as PDF, Excel or CSV.</b></li>'
                . '<li><b>Reporting period</b>, top right: the months that have a calculated ECL (a saved ECL run), newest first. The month chosen is carried into every tile that takes a period: the month-end ECL reports, Data quality and exceptions, RBM classification and provisioning, the RBM return and Early warning signals. When no month has an ECL the box reads <b>No ECL periods</b> and is greyed out.</li>'
                . '<li>Four summary tiles: <b>Reports you can open</b> (the tiles your permissions allow), <b>Report groups</b>, <b>Latest ECL period</b> and <b>Periods with a calculated ECL</b> (the last two in amber).</li>'
                . '<li>A row of group tabs, each with a chip counting its tiles (green on the open tab, grey on the others): <b>Month-end ECL</b>, <b>Annual report and audit</b>, <b>Regulatory (RBM)</b>, <b>EIR</b>, <b>Risk and analytics</b> and <b>Exports</b>. A group in which you may open nothing is not shown. The open group is kept in the address (tab=...), so <b>Back to reports</b> on any report returns you to its own group.</li>'
                . '<li>Under the tabs, the name of the group and one line on what it holds, and a search box <b>Find a report</b> with a magnifier icon.</li>'
                . '<li>The report cards. Each card shows an icon, the report title, a right arrow (it slides when you point at the card), the question the report answers and the formats it downloads in, for example <b>Downloads as PDF, Excel, CSV</b>. Select a card to open the report.</li></ul>'
                . '<h4>The tiles in each group</h4><ul>'
                . '<li><b>Month-end ECL</b> (permission reports.ifrs9, all take the hub period): <b>Executive summary</b>, <b>ECL by stage</b>, <b>ECL by segment</b>, <b>Portfolio ECL trend</b>, <b>Account-level ECL calculation</b>, <b>Stage movement and SICR</b>, <b>Model inputs</b> and <b>Forward-looking and scenarios</b>.</li>'
                . '<li><b>Annual report and audit</b>: <b>IFRS 9 note (annual financial statements)</b> (reports.ifrs9; PDF, Word, Excel and CSV; it chooses its own two months), <b>Loan book reconciliation</b> (reports) and <b>Data quality and exceptions</b> (reports.ifrs9).</li>'
                . '<li><b>Regulatory (RBM)</b>: <b>RBM return (provisional)</b> and <b>RBM classification and provisioning</b> (both reports.ifrs9).</li>'
                . '<li><b>EIR</b>: <b>EIR as at a date</b> and <b>GL reconciliation (EIR)</b> (eir.view). They are explained in the EIR and revenue recognition chapter.</li>'
                . '<li><b>Risk and analytics</b>: <b>Stress testing</b> and <b>Early warning signals</b> (reports.ifrs9).</li>'
                . '<li><b>Exports</b>: <b>Loan book export</b>, <b>ECL export</b> and <b>Disbursements (vintage)</b> (reports; CSV), and <b>Auditor pack</b> (eir.export; ZIP).</li></ul>'
                . '<p>Several former reports now open as tabs inside one tile, for example Stage allocation and PD by stage inside ECL by stage, and the movement tables inside the IFRS 9 note. Their old addresses still open the single report, so a bookmark keeps working; each article in this chapter says where a former report went.</p>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Reporting period</b>: choose the month the period-taking tiles open on. It changes nothing in the data.</li>'
                . '<li>The group tabs: show that group\'s tiles. Choosing a tab clears the search.</li>'
                . '<li><b>Find a report</b>: type a word to search the titles and descriptions of every group at once. The heading becomes <b>Search results</b> with a line such as <b>3 report(s) match "stage" across all groups</b>, and each card carries a grey badge naming its group. Clear the box to return to the open tab.</li>'
                . '<li>A report card (with its icon and right arrow): opens the report. Nothing on the hub changes a figure.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>With no month holding an ECL, an amber box with a warning triangle: <b>No reporting period has a calculated ECL yet</b> and <b>Run the ECL calculation for a month first; the ECL reports then open on that month. The reconciliations, exports and EIR reports still work.</b></li>'
                . '<li>A search with no match: <b>No report matches "...". Try a shorter word, such as ECL, stage or RBM.</b></li>'
                . '<li>A group with no tile you may open: <b>You do not have access to any report in this group. Ask your administrator if you need one.</b></li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The month you want is not in Reporting period.</b> Only months with a saved ECL run are listed. Run the ECL calculation for that month first (ECL Processing).</li>'
                . '<li><b>A tile you expect is missing.</b> The hub hides a tile whose permission you do not hold, for example the Auditor pack without eir.export. Ask the administrator to add the permission to your role.</li>'
                . '<li><b>A report opens on another month than the hub showed.</b> The IFRS 9 note and the reconciliations choose their own months on their pages; only the tiles listed above take the hub period.</li></ul>',
            'steps' => [
                'Open Reports in the sidebar.',
                'Choose the month in Reporting period at the top right.',
                'Choose a group tab, for example Month-end ECL, or type a word in Find a report.',
                'Select the report card you need.',
                'On the report, press Back to reports to return to the same group.',
            ],
            'images' => [
                'reports' => 'The Reports hub: the Reporting period at the top right, the four summary tiles, the group tabs with their counts, the Find a report box and the report cards',
            ],
            'routes' => ['ifrs9-reports.index', 'reports.index'],
        ],

        'Reading a report page' => [
            'body' => '<p>Every tile of the Month-end ECL group, Data quality and exceptions, RBM classification and provisioning and Early warning signals opens on the same report page, so once you can read one you can read them all. This article explains that page; the articles that follow explain what each report contains. The page needs the permission <b>reports.ifrs9</b>. Nothing on it changes a figure: a report is a read-only view of the calculated results, which is why it is safe to hand to an auditor.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>Above the title, the link <b>Back to reports</b> with a left arrow.</li>'
                . '<li>The report title and its subtitle, which says what the report holds and often names the month or the two months compared. When the currency is set in Settings the subtitle ends with <b>amounts in</b> and the currency code. When some loans of the month have no discounted allowance, the subtitle adds <b>(the allowance includes N loans with no discounted figure, at their undiscounted ECL: see the basis section)</b>.</li>'
                . '<li>A row of KPI tiles. Each has a label, a value and often a comparison line against the previous ECL month: an up, down or level arrow with the percentage change and the month compared with, for example <b>4.2% vs 2025-11</b>, or <b>no prior period</b> when the month is the earliest one. The accent colour carries meaning: green for exposure and counts, red for loss, amber or gold for coverage and warnings, grey for information.</li>'
                . '<li>Most tiles are consolidated: their parts are tabs in one row under the tiles, each tab with a chip giving its number of rows, and a grey line under the tabs describing the open part. The open tab is kept in the address (part=0, 1, 2 ...). When a part has headline figures of its own they appear at its top as a small table headed <b>Key figures</b> with the columns Figure, Value and Note.</li>'
                . '<li>Sections. Each has a heading, a grey badge such as <b>42 rows</b>, sometimes a short grey note under the heading, and a table. Amounts are right aligned with thousands separators and two decimals; percentages carry the per cent sign. Rows starting Total, Totals, Grand total, Net or Closing are shown in bold as totals.</li>'
                . '<li>Every report that carries a loss allowance ends with a section headed <b>Basis of the loss allowance</b> (on a consolidated report, a tab of its own named <b>Basis of the allowance</b>). See the article Booked ECL: the basis of the loss allowance.</li>'
                . '<li>A table longer than fifteen rows is paged: <b>Showing 1 to 15 of 200 rows</b>, with <b>Previous</b>, numbered pages (the current one in green) and <b>Next</b>.</li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b> (left arrow): returns to the hub on the report\'s own group.</li>'
                . '<li><b>Period</b>, top right: reloads the same report for another month with a calculated ECL. Any other month typed into the address is ignored and the latest month is used.</li>'
                . '<li><b>CSV</b> (download arrow, grey): the report\'s tables as plain data, with a heading block (company, title, reporting period, prepared on, amounts in, the KPI tiles), then each section with its note and its rows; on a consolidated report each part is marked <b>Part:</b>. Figures are written as plain numbers (brackets become a minus sign) so the file sums in Excel.</li>'
                . '<li><b>Excel</b> (download arrow, green): a formatted, branded workbook. A consolidated report gives one sheet per part, the basis part included.</li>'
                . '<li><b>Download PDF</b> (the green button): a branded A4 landscape PDF with the logo set in Settings (else the MAIIC logo), the user who produced it, the date, and Page N of M on every page.</li>'
                . '<li>The part tabs: switch between the parts of a consolidated report. The downloads always carry every part.</li>'
                . '<li><b>Run</b> (a play icon), on the concentration report only (the Concentration and large exposures tab of ECL by segment): reloads it with the large-exposure threshold typed beside it.</li>'
                . '<li><b>Previous</b>, the page numbers and <b>Next</b>: page through a long table; the downloads hold every row.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>A section with no rows: <b>No data for this section in 2025-12.</b> (the month shown is the one selected).</li>'
                . '<li>A report with no section at all: <b>This report has no content for the selected period. Choose another period, or run the ECL calculation for this one.</b></li>'
                . '<li>Some sections show a placeholder row instead of nothing, for example <b>No approved scenario set for this month</b>; each article names them.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The Excel file shows numbers as text.</b> It should not: the exporter writes typed numbers. Raise a support ticket naming the report and the month.</li>'
                . '<li><b>The PDF is blank or missing glyphs.</b> Usually a server memory or font limit on a very long report. Raise a support ticket naming the report and the month, and use the Excel copy meanwhile.</li>'
                . '<li><b>A total on screen differs from the same total in another report.</b> Check the heading: some tables count the exposure at default (carrying amount plus the drawn share of undrawn commitments) and some the gross balance (carrying amount only).</li></ul>',
            'steps' => [
                'Open a report from the hub.',
                'Check the month in Period at the top right and change it if needed.',
                'Read the KPI tiles first, including the comparison line against the previous month.',
                'Work through the part tabs and the sections; each heading says what the rows are.',
                'Read the Basis of the loss allowance section before quoting any ECL figure.',
                'Press CSV, Excel or Download PDF to take a copy.',
            ],
            'images' => [
                'report-executive' => 'A report page: Back to reports, the Period box and the CSV, Excel and Download PDF buttons, the KPI tiles with their comparison lines, the part tabs and the first sections',
            ],
            'routes' => [],
        ],

        'Core ECL reports' => [
            'body' => '<p>The core ECL report is the tile <b>ECL by stage</b>: how much ECL is held in each stage, how the book is staged, and on what probability of default (PD) and loss given default (LGD). It absorbs the former reports Stage allocation and PD report. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>ECL by stage</b>. It needs <b>reports.ifrs9</b> and opens on the month chosen in the hub.</p>'
                . '<p>The stage used throughout is the stage after the qualitative significant-increase-in-credit-risk triggers, the stage the ECL was provided on. Exposure at default (EAD) is the carrying amount plus undrawn commitments times the utilisation rate (a missing rate counts as full use).</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b> (the booked allowance), <b>Coverage Ratio</b> (ECL over EAD) and <b>Loans</b>, each with its comparison against the previous month.</li>'
                . '<li>Tab <b>ECL summary by stage</b>: the section <b>Expected Credit Loss by Portfolio and Stage (the saved ECL runs)</b> with the columns <b>Portfolio</b>, <b>Stage</b>, <b>Loans</b>, <b>EAD</b>, <b>Avg PD</b>, <b>Avg LGD</b>, <b>ECL undiscounted</b>, <b>ECL discounted</b> and <b>Discounting</b>. These are the rows each ECL run saved: the undiscounted ECL the engine built (EAD x PD x LGD) and the discounted ECL of the loans the run discounted. Discounting shows the status the run recorded, with the number of loans not discounted in brackets; a dash in ECL discounted means the run was undiscounted. The note under the heading warns that where a run is PARTIAL the discounted column covers only the loans it discounted, and that the booked allowance of the month is in the basis section.</li>'
                . '<li>Tab <b>Stage allocation</b>: the section <b>Exposure Classification by IFRS 9 Stage</b> with <b>Stage</b>, <b>Loans</b>, <b>Current</b> (no days past due), <b>In Arrears</b>, <b>Exposure (EAD)</b>, <b>ECL</b> (booked) and <b>Coverage %</b>.</li>'
                . '<li>Tab <b>PD by stage</b>: the section <b>Average Probability of Default by Stage</b> with <b>Stage</b>, <b>Loans</b>, <b>Avg PD (pre-FLI / 12m)</b>, <b>Avg Lifetime PD</b> and <b>Avg PD (post-FLI)</b>, so the forward-looking adjustment can be seen separately from the underlying model.</li>'
                . '<li>Tab <b>Basis of the allowance</b>: the booked basis and its reconciliation from the undiscounted measure.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons (Back to reports, Period, CSV, Excel, Download PDF, the part tabs and the pager), explained in Reading a report page. The Excel workbook has one sheet per tab.</p>'
                . '<h4>How to read it</h4><ul>'
                . '<li>Compare <b>ECL undiscounted</b> with the ECL in Stage allocation: the first is the engine build-up before discounting, the second the booked (discounted) allowance. The difference is explained line by line in the basis tab.</li>'
                . '<li>Read Coverage % by stage: it should rise from Stage 1 to Stage 3. A Stage 3 coverage below the Stage 2 coverage is worth a question to the model owner.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No data for this section in ...</b> on the first tab: no ECL run is saved for that month (the hub offers only months with one, so this follows a deleted run).</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>ECL discounted is lower than ECL undiscounted by a large margin.</b> Expected: discounting at the original effective interest rate reduces a lifetime loss. The basis tab shows the projection and the discounting effect separately.</li>'
                . '<li><b>Discounting says loans were not discounted.</b> Those loans have no locked original EIR or their projection could not run; they are counted at their undiscounted ECL. Lock their EIR and run the month-end ECL again.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then ECL by stage.',
                'Check the month in Period.',
                'Read ECL summary by stage for the saved runs, undiscounted and discounted, by portfolio and stage.',
                'Open Stage allocation for the loans, arrears, EAD, booked ECL and coverage of each stage.',
                'Open PD by stage to compare the PD before and after the forward-looking adjustment.',
                'Open Basis of the allowance, then download the PDF or Excel for the file.',
            ],
            'images' => [
                'report-ecl' => 'ECL by stage: the saved ECL runs by portfolio and stage, with the undiscounted and discounted ECL and the discounting status',
                'report-stage-allocation' => 'Stage allocation (the second tab of ECL by stage): loans, current, in arrears, EAD, booked ECL and coverage by stage',
                'report-pd' => 'PD by stage (the third tab of ECL by stage): the PD before and after the forward-looking adjustment and the lifetime PD',
            ],
            'routes' => ['ifrs9-reports.ecl', 'ifrs9-reports.stage-allocation', 'ifrs9-reports.pd-report'],
        ],

        'Staging and movement reports' => [
            'body' => '<p>The tile <b>Stage movement and SICR</b> answers which loans changed stage since the previous month with a calculated ECL, and which triggers put loans into Stage 2 (a significant increase in credit risk, SICR). It absorbs the former SICR Trigger report. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Stage movement and SICR</b>. It needs <b>reports.ifrs9</b>. The movement of the allowance itself (opening to closing ECL, the charge and the gross carrying amount) is in the IFRS 9 note.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b> and <b>Loans</b>.</li>'
                . '<li>Tab <b>Stage migration</b>: the subtitle names the two months compared, then the section <b>Stage Migration Matrix (count)</b>, a grid with the rows Stage 1, Stage 2 and Stage 3 (the stage in the previous month) and the columns <b>Stage 1</b>, <b>Stage 2</b> and <b>Stage 3</b> (the stage now). Each cell counts the loans that made that move; the diagonal is the loans that stayed. Only contracts present in both months are counted: new loans and loans that left the book are not in the grid.</li>'
                . '<li>Tab <b>SICR triggers</b>: a <b>Key figures</b> table (<b>Stage 2 Loans</b> and <b>Period</b>), then the section <b>Stage 2 by SICR Trigger</b> with <b>Trigger</b>, <b>Loans</b>, <b>Exposure (EAD)</b> and <b>ECL</b>. Each Stage 2 loan is placed under the first reason that applies: <b>SICR flag set</b>, <b>Arrears 31-90 DPD</b>, <b>Arrears 1-30 DPD</b>, else <b>Other / qualitative</b>.</li>'
                . '<li>Tab <b>Basis of the allowance</b>.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>How to read it</h4><ul>'
                . '<li>Read the row Stage 1 across: the cell under Stage 2 is the loans that deteriorated this month. The SICR triggers tab then says why loans sit in Stage 2.</li>'
                . '<li>Movements back to Stage 1 (the cells left of the diagonal) are cures; check them against the cure rules in the Governance Centre.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>On the earliest month the subtitle reads <b>No prior ECL-calculated period to compare against.</b> and the Stage migration tab has no section.</li>'
                . '<li>The SICR triggers tab with no rows: no loan is in Stage 2 that month.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The grid total is smaller than the loan count.</b> It counts only contracts present in both months.</li>'
                . '<li><b>Many loans under Other / qualitative.</b> They are in Stage 2 by a qualitative trigger or a rule the four reasons do not name, for example a watch-list flag; see SICR Triggers in the Governance Centre.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Stage movement and SICR.',
                'Check the two months named in the subtitle.',
                'Read the Stage migration grid row by row: the previous stage across to the stage now.',
                'Open SICR triggers to see why the Stage 2 loans are there.',
                'Download the PDF or Excel for the file.',
            ],
            'images' => [
                'report-stage-migration' => 'Stage movement and SICR: the stage migration grid of loan counts against the previous month',
                'report-sicr-trigger' => 'SICR triggers: Stage 2 loans, EAD and ECL by the trigger that put them there',
            ],
            'routes' => ['ifrs9-reports.stage-migration', 'ifrs9-reports.sicr-trigger'],
        ],

        'Model component reports' => [
            'body' => '<p>The tile <b>Model inputs</b> shows the ingredients rather than the answer, so a model reviewer can test each one: the LGD and collateral, the agricultural risk cover, and the exposure at default including undrawn commitments. It absorbs the former reports Credit risk mitigation (agri) and EAD and off-balance sheet. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Model inputs</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b> and <b>Loans</b>.</li>'
                . '<li>Tab <b>LGD and collateral</b>: the section <b>LGD and collateral by stage</b> with <b>Stage</b>, <b>Loans</b>, <b>EAD</b>, <b>Collateral (gross)</b>, <b>Collateral (discounted)</b>, <b>Net unsecured</b> (EAD less the discounted collateral, never below nil), <b>Avg customer LGD</b> and <b>Avg collection LGD</b> (the two LGD methods; a dash where no loan carries that LGD). The collateral is the collateral allocated to each loan for the month under Collateral Management.</li>'
                . '<li>Tab <b>Agricultural risk cover</b>: the section <b>Exposure &amp; LGD by Credit Enhancement</b> with <b>Credit Enhancement</b> (off-take, warehouse receipt, group guarantee, AIP cover; <b>Unspecified</b> where none is recorded), <b>Loans</b>, <b>EAD</b>, <b>Avg LGD</b> (the collection LGD), <b>ECL</b> (booked) and <b>Coverage</b>.</li>'
                . '<li>Tab <b>EAD and off-balance sheet</b>: the section <b>Exposure at Default by Stage</b> with <b>Stage</b>, <b>Loans</b>, <b>Carrying Amount</b>, <b>Undrawn Commitments</b>, <b>Avg CCF</b> (the average utilisation rate applied as the credit conversion factor) and <b>EAD</b>.</li></ul>'
                . '<p>This tile has no basis section of its own: the allowance it shows on the Agricultural risk cover tab is the booked allowance, as in every report.</p>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>Empty states and messages</h4><ul><li><b>No data for this section in ...</b>: no loan book for that month.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Net unsecured equals the full EAD.</b> No collateral is allocated to those loans for the month. Allocation is done under Collateral Management, not automatically.</li>'
                . '<li><b>Avg CCF is 1.0000 everywhere.</b> No utilisation rate is recorded, so full use of the commitment is assumed. There is no separate conversion-factor model.</li>'
                . '<li><b>Most of the book is Unspecified.</b> The credit enhancement is not captured on the loan book; capture it at source.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Model inputs.',
                'Read LGD and collateral: the collateral held, the net unsecured amount and both LGD methods by stage.',
                'Open Agricultural risk cover to see the book by credit enhancement and the LGD each implies.',
                'Open EAD and off-balance sheet to check the undrawn commitments and the conversion factor.',
                'Download the PDF or Excel for the model review file.',
            ],
            'images' => [
                'report-lgd-collateral' => 'Model inputs: LGD and collateral by stage, with the gross and discounted collateral, the net unsecured amount and both LGD methods',
                'report-crm-agri' => 'Agricultural risk cover: exposure, LGD and booked ECL by credit enhancement',
                'report-ead' => 'EAD and off-balance sheet: carrying amount, undrawn commitments, the conversion factor and EAD by stage',
            ],
            'routes' => ['ifrs9-reports.lgd-collateral', 'ifrs9-reports.crm-agri', 'ifrs9-reports.ead-report'],
        ],

        'Forward-looking reports' => [
            'body' => '<p>The tile <b>Forward-looking and scenarios</b> shows the macro-economic data and the governed scenario set behind the forward-looking adjustment (FLI) of the month, and the probability-weighted ECL. IFRS 9 requires the ECL to reflect reasonable and supportable forward-looking information; this report is how that is evidenced. It absorbs the former Scenario-weighted ECL report. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Forward-looking and scenarios</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b>, <b>Loans</b>, <b>Scenario set</b> (the set used, for example its name, the version and its status in brackets; green; or <b>None approved</b> in amber) and <b>Macro vintage</b> (the vintage of the macro data the FLI close used).</li>'
                . '<li>Tab <b>Macro scenarios</b>, three sections: <b>Macro-Economic Data Held for the Month</b> (<b>Variable</b>, <b>Code</b>, <b>Latest period</b>, <b>Type</b>, <b>Value</b>, <b>Source</b>: the latest observation of each variable up to the month); <b>Economic Scenarios &amp; Weights</b> followed by the set\'s name (<b>Scenario</b>, <b>Weight</b>, <b>Anchored to</b>, <b>PD multiplier</b>; the base scenario is marked (base)); and <b>Scenario Shocks</b> (<b>Scenario</b>, <b>Variable</b>, <b>Forecast year</b>, <b>Shock</b>: Percent change, Add, Replace or Multiply, and <b>Value</b>).</li>'
                . '<li>Tab <b>Scenario-weighted ECL</b>: a <b>Key figures</b> table with <b>Booked ECL</b> (the allowance the FLI month-end close recorded), <b>Probability-Weighted ECL</b>, <b>Difference</b> and <b>Close status</b> (<b>Signed off</b>, or <b>Recorded, awaiting sign-off</b>); then the section <b>ECL by Scenario</b> with <b>Scenario</b>, <b>Weight</b>, <b>ECL under the scenario</b> and <b>Weighted contribution</b>, and the line <b>Probability-weighted</b> at the foot.</li></ul>'
                . '<p>The scenario set is the one the FLI month-end close used, else the approved (or locked) set of the month. This tile has no basis section; the booked allowance is on the other ECL reports.</p>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>How to read it</h4><ul>'
                . '<li>First confirm on Macro scenarios that the set and the shocks are the ones the committee approved for the month, then read Scenario-weighted ECL to see what they cost.</li>'
                . '<li>Compare the weighted result with the base scenario alone: the gap is the cost of the downside weighting, the number a reviewer asks you to justify.</li>'
                . '<li><b>Difference</b> is green when the close reconciled the weighted ECL to the booked ECL, red when it did not.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>Placeholder rows: <b>No macro-economic data held up to this month</b>, <b>No approved scenario set for this month</b> and <b>No shocks recorded on the set</b>.</li>'
                . '<li>With no FLI close for the month the Key figures read <b>FLI close</b>: <b>Not recorded for this month</b>, and ECL by Scenario shows <b>Run the FLI month-end close for this month (FLI Month-End, Overview)</b>.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Scenario set reads None approved.</b> No set is approved for the month. Approve one under Scenario Sets (a second person approves), then run the FLI close.</li>'
                . '<li><b>The weighted figure equals the base figure.</b> Only one scenario carries weight, or every scenario has a PD multiplier of one. Check the weights of the set sum to one hundred per cent.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Forward-looking and scenarios.',
                'Check the Scenario set and Macro vintage tiles name the approved set and data.',
                'Read the macro data, the scenario weights and the shocks on Macro scenarios.',
                'Open Scenario-weighted ECL and compare each scenario with the weighted result.',
                'Check Difference and Close status before quoting the figures.',
            ],
            'images' => [
                'report-macro-scenario' => 'Forward-looking and scenarios: the macro data held for the month, the scenario set with its weights, and the shocks',
                'report-scenario-ecl' => 'Scenario-weighted ECL: booked against probability-weighted ECL, and the ECL under each scenario with its weighted contribution',
            ],
            'routes' => ['ifrs9-reports.macro-scenario', 'ifrs9-reports.scenario-ecl'],
        ],

        'RBM prudential reports' => [
            'body' => '<p>The tile <b>RBM classification and provisioning</b> answers how the book is classified under the Reserve Bank of Malawi Credit Risk Management for DFIs Directive, 2018, how the IFRS 9 stages map to the classes, whether the IFRS 9 allowance is above the directive\'s minimum provision, and how large the non-performing loans and arrears are. It absorbs the former reports IFRS 9 Stage versus RBM Mapping, Provision Comparison and NPL and Arrears. Open <b>Reports</b>, the <b>Regulatory (RBM)</b> tab, then <b>RBM classification and provisioning</b>. It needs <b>reports.ifrs9</b>. The return itself, in the directive\'s order, is the RBM return (see that article).</p>'
                . '<h4>The directive\'s classes</h4><p>A loan is classed by its days past due and by the term of the facility (short term is twelve months or less): Pass, up to 30 days (short term) or 90 days (medium and long term), minimum provision 0 per cent; Special Mention, 31 to 90 or 91 to 180 days, 5 per cent; Substandard, 91 to 180 or 181 to 365 days, 20 per cent; Doubtful, 181 to 365 or 366 to 746 days, 50 per cent; Loss, over 365 or over 746 days, 100 per cent. The minimum rates apply to the gross balance (the carrying amount); undrawn commitments are not a classified balance. These are the same bands, rates and basis as the RBM return.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>NPL Ratio (substandard and below)</b> (Substandard, Doubtful and Loss balance over the total gross balance), <b>RBM Provision</b>, <b>IFRS 9 ECL</b> (booked) and <b>ECL - RBM</b> (green when the allowance is at or above the minimum, red when below).</li>'
                . '<li>Tab <b>RBM classification</b>: the section <b>RBM Asset Classification (by days past due and term of facility)</b> with <b>RBM Class</b>, <b>Loans</b>, <b>Gross balance</b>, <b>Minimum Rate</b>, <b>RBM Provision</b>, <b>IFRS 9 ECL</b> and <b>ECL - RBM</b>.</li>'
                . '<li>Tab <b>IFRS 9 stage against RBM class</b>: the section <b>IFRS 9 Stage vs RBM Class</b> with <b>IFRS 9 Stage</b>, <b>RBM Classification</b>, <b>Loans</b> and <b>Exposure (EAD)</b>. The class shown is an indicative, fixed cross-reference (Stage 1 to Pass, Stage 2 to Special Mention, Stage 3 to Non-Performing), not a loan-by-loan count; the loan-by-loan classes are on the first tab.</li>'
                . '<li>Tab <b>Provision comparison</b>: <b>Key figures</b> (<b>IFRS 9 ECL</b>, <b>RBM Provision</b>, <b>Shortfall / (Excess)</b>), then <b>IFRS 9 ECL vs RBM Prudential Provision (by class, on the gross balance)</b> with <b>RBM Class</b>, <b>Gross balance</b>, <b>RBM Rate</b>, <b>RBM Provision</b>, <b>IFRS 9 ECL</b> and <b>ECL - RBM</b>. The note says that where the RBM provision is higher, paragraph 15(1) treats the excess as an appropriation of retained earnings to the loan loss reserve.</li>'
                . '<li>Tab <b>PD by RBM class</b>: the observed one-year PD by class over the window shown in the heading, with <b>RBM class</b>, <b>Stage 3</b>, <b>Performing</b>, <b>Defaulted</b>, <b>PD count</b>, <b>PD balance</b>, <b>RBM min</b>, <b>Loans now</b>, <b>EAD now</b>, <b>Coverage</b> and <b>PD now</b>. A report only: the ECL does not use the RBM class. When it cannot be measured the row reads <b>Not measured:</b> and the reason.</li>'
                . '<li>Tab <b>NPL and arrears</b>: <b>Key figures</b> (<b>NPL Exposure</b>, <b>NPL Loans</b>, <b>NPL Ratio</b>, <b>Loans</b>), then <b>Arrears Ageing (days past due, with the RBM class by term of facility)</b> with <b>DPD Band</b> (0-30, 31-90, 91-180, 181-365, 366-746, 747+), <b>RBM Class</b>, <b>Loans</b>, <b>Exposure (EAD)</b> and <b>ECL</b>. The NPL figures on this tab are the Stage 3 loans and their EAD.</li>'
                . '<li>Tab <b>Basis of the allowance</b>.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>How to read it</h4><ul>'
                . '<li>Read ECL - RBM by class: a negative value is a class where the directive asks for more than IFRS 9 holds, and the total shortfall is the loan loss reserve.</li>'
                . '<li>This report covers the whole book, Mega Farm programme loans included. The RBM return leaves them out unless <b>Include Mega Farm</b> is ticked, so the two agree only on the same population.</li></ul>'
                . '<h4>Empty states and messages</h4><ul><li>A class with no loan shows a row of zeros (every class is always listed).</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The NPL ratio on the first tab differs from NPL and arrears.</b> They measure different things: the first is the directive\'s Substandard and below on the gross balance; the NPL and arrears tab counts Stage 3 on EAD. IFRS 9 stage against RBM class exists to explain the difference.</li>'
                . '<li><b>The figures differ from the RBM return.</b> Check Include Mega Farm on the return, and the month: the return lists every loan book month, this report only months with an ECL.</li></ul>',
            'steps' => [
                'Open Reports, the Regulatory (RBM) tab, then RBM classification and provisioning.',
                'Read the NPL ratio and ECL - RBM tiles.',
                'Read the classification by class, then Provision comparison for the shortfall or excess.',
                'Open NPL and arrears for the ageing by days past due.',
                'Download the PDF or Excel and keep it with the RBM return of the month.',
            ],
            'images' => [
                'report-rbm-classification' => 'RBM classification and provisioning: the classes by days past due and term, the minimum provision and the IFRS 9 ECL',
                'report-ifrs9-vs-rbm' => 'IFRS 9 stage against RBM class: the indicative cross-reference with loans and EAD',
                'report-provision-comparison' => 'Provision comparison: the IFRS 9 ECL against the RBM minimum provision by class, with the shortfall or excess',
                'report-npl-arrears' => 'NPL and arrears: the ageing by days past due with the RBM class',
            ],
            'routes' => ['ifrs9-reports.rbm-classification', 'ifrs9-reports.ifrs9-vs-rbm', 'ifrs9-reports.provision-comparison', 'ifrs9-reports.npl-arrears'],
        ],

        'Disclosure and audit reports' => [
            'body' => '<p>Two kinds of year-end support sit here. The tile <b>Data quality and exceptions</b> (Reports, <b>Annual report and audit</b> tab) lists the integrity checks to read before you circulate any other report. And the former note tables, <b>Opening to closing ECL</b>, <b>Gross carrying amount movement</b> and <b>ECL charge or release</b>, still open on their own from an old link or bookmark, although the hub now shows them inside the IFRS 9 note. All need <b>reports.ifrs9</b>. The note itself has its own article: IFRS 9 note (financial statements disclosure).</p>'
                . '<h4>Data quality and exceptions</h4><ul>'
                . '<li>KPI tiles: <b>Loans Checked</b>, <b>Missing Sector Tag</b> and <b>Unmapped Portfolio</b> (each red when above nil, green at nil).</li>'
                . '<li>The section <b>Data Quality Exceptions</b> with <b>Check</b> and <b>Records</b>: <b>Missing sector tag (RBM classification)</b>, <b>Unmapped to a real portfolio (blended "Loans")</b>, <b>Missing customer name</b>, <b>Missing / zero EAD</b>, <b>Negative balance</b>, <b>Missing stage</b>, <b>ECL not calculated</b>, <b>Zero ECL on Stage 3</b>, <b>ECL exceeds EAD</b> and <b>Missing remaining tenor</b>. The last two ECL checks use the booked allowance.</li>'
                . '<li>The <b>Basis of the loss allowance</b> section.</li></ul>'
                . '<h4>Opening to closing ECL (standalone)</h4><ul>'
                . '<li>Compares the month with the previous month that has an ECL, contract by contract, with the IFRS 9 note\'s attribution. KPI tiles <b>Opening ECL</b>, <b>Amounts written off</b>, <b>Charge / (release)</b> (red for a charge, green for a release) and <b>Closing ECL</b>.</li>'
                . '<li>The section <b>ECL Reconciliation</b> with <b>Particulars</b>, <b>Stage 1</b>, <b>Stage 2</b>, <b>Stage 3</b> and <b>Total</b>: the opening allowance, <b>Transfers to Stage 1</b>, <b>Transfers to Stage 2</b>, <b>Transfers to Stage 3</b>, <b>Net remeasurement of ECL</b>, <b>New financial assets originated</b>, <b>Financial assets derecognised</b>, <b>Amounts written off</b>, the closing allowance, and the line <b>Charge / (release) to profit or loss (closing less opening, less amounts written off)</b>.</li>'
                . '<li>The section <b>Amounts written off</b> with <b>Date</b>, <b>Contract</b>, <b>Customer</b>, <b>Gross written off</b> and <b>Record</b> (Ledger: or Register: and the reference). Its note names the write-off rule (type 300, a batch debiting GL 1064, an ACCOUNT CLOSURE on a status-H account) and the approved write-off register, and the IFRS 7.35L statement on amounts still subject to enforcement.</li>'
                . '<li>On the earliest month (no previous month): the KPI tiles <b>Opening ECL</b> (nil), <b>Net Movement</b> and <b>Closing ECL</b>, and the section <b>Closing ECL by Stage</b>.</li></ul>'
                . '<h4>ECL charge or release (standalone)</h4><ul>'
                . '<li>KPI tiles <b>P&amp;L Impact</b>, <b>Direction</b> (Charge or Release) and <b>Allowance used by write-offs</b>.</li>'
                . '<li>The section <b>Impairment Charge / (Release) by Stage</b> with <b>Stage</b>, <b>Prior ECL</b>, <b>Written off</b>, <b>Current ECL</b> and <b>Charge / (Release)</b>, then the Amounts written off section.</li></ul>'
                . '<h4>Gross carrying amount movement (standalone)</h4><ul>'
                . '<li>The section <b>Gross Carrying Amount Movement by Stage</b> with <b>Stage</b>, <b>Loans</b>, <b>Opening</b> (the previous month\'s balance in that stage), <b>Disbursements</b>, <b>Repayments</b>, <b>Closing</b> and <b>Net Movement</b>. It is a stage-by-stage comparison of the two books, not a contract-by-contract bridge: for the bridge with transfers and write-offs use the IFRS 9 note, table (b).</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page. Back to reports opens the Annual report and audit group.</p>'
                . '<h4>Empty states and messages</h4><ul><li><b>No data for this section in ...</b>, and on the movement tables the subtitle <b>No prior period</b> or <b>Closing position (no prior period)</b> on the earliest month.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Data quality shows exceptions you cannot explain.</b> Raise a support ticket with the report attached before you circulate the month.</li>'
                . '<li><b>The standalone movement and the note differ.</b> The standalone pages compare with the previous ECL month; the note compares the two months you choose, often December to December. Choose the same two months in the note.</li></ul>',
            'steps' => [
                'Open Reports, the Annual report and audit tab, then Data quality and exceptions.',
                'Check every exception count is nil or explained before circulating any report.',
                'For the movement of the allowance, open the IFRS 9 note rather than the standalone tables.',
                'Download the PDF or Excel of the data quality checks for the month-end file.',
            ],
            'images' => [
                'report-data-quality' => 'Data quality and exceptions: the integrity checks with their record counts',
                'report-ecl-reconciliation' => 'The standalone opening to closing ECL by stage, with the write-off line and the charge',
                'report-gross-movement' => 'The standalone gross carrying amount movement by stage',
                'report-ecl-charge' => 'The standalone ECL charge or release by stage, with the allowance used by write-offs',
            ],
            'routes' => ['ifrs9-reports.data-quality', 'ifrs9-reports.ecl-reconciliation', 'ifrs9-reports.gross-movement', 'ifrs9-reports.ecl-charge'],
        ],

        'Analytics: Early Warning System and AI Executive Commentary' => [
            'body' => '<p>Two reports that look forward rather than back. <b>Early warning signals</b> is a tile of its own (Reports, <b>Risk and analytics</b> tab). The <b>Executive commentary</b> is the second tab of the Executive summary tile, and its old address still opens it on its own. Both need <b>reports.ifrs9</b> and read the month chosen in the hub.</p>'
                . '<h4>Early warning signals</h4><ul>'
                . '<li>A watchlist built from rules, not from a model. The KPI tiles: <b>Stage 1 in Arrears</b> (Stage 1 loans with any days past due), <b>S1 Arrears Exposure</b> (their EAD), <b>High Utilisation (&gt;=90%)</b> (loans using ninety per cent or more of their facility) and a fourth tile counting the new migrations from Stage 1 to Stage 2 since the previous month (labelled New S1 to S2 Migrations, written with an arrow).</li>'
                . '<li>The section <b>Early-Warning Watchlist (largest at-risk performing exposures)</b>: the forty largest Stage 1 or Stage 2 loans that are past due, with <b>Contract</b>, <b>Client</b>, <b>Stage</b>, <b>Days Past Due</b>, <b>Exposure</b> and <b>Severity</b>: <b>HIGH</b> from 60 days past due, <b>MEDIUM</b> from 30, <b>WATCH</b> below that.</li>'
                . '<li>Use it to prompt a collections conversation before a loan migrates. It has no basis section: it shows exposure, not allowance.</li></ul>'
                . '<h4>Executive commentary</h4><ul>'
                . '<li>The subtitle reads <b>Auto-generated executive commentary (rule-based AI assistant)</b>, and the section <b>AI Executive Commentary</b> lists numbered sentences (<b>#</b>, <b>Commentary</b>).</li>'
                . '<li>Be plain about what it is: the commentary is <b>rule-based</b>. The system assembles sentences from the calculated figures with fixed thresholds; no language model and no outside service is involved. It covers the position (EAD, number of facilities, the booked ECL and the coverage ratio), the movement against the previous month and whether it is a charge or a release, the Stage 3 exposure as an NPL ratio with a warning above ten per cent, and the coverage with a comment above fifteen per cent. It closes by saying it supports, not replaces, management and audit judgement.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>An empty watchlist (<b>No data for this section in ...</b>): no Stage 1 or Stage 2 loan is past due that month, which is the good outcome.</li>'
                . '<li>On the earliest month the commentary has no movement sentence and the migrations tile reads 0.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The commentary NPL ratio differs from the RBM report.</b> The commentary uses Stage 3 over EAD; the RBM report uses Substandard and below over the gross balance.</li>'
                . '<li><b>High Utilisation reads 0.</b> Only a recorded utilisation rate of 0.9 or more is counted; a loan with no utilisation rate on the loan book is not.</li></ul>',
            'steps' => [
                'Open Reports, the Risk and analytics tab, then Early warning signals.',
                'Read the four tiles, then the watchlist from the top.',
                'Start with the HIGH rows and agree actions with collections.',
                'For the written commentary, open Executive summary and its Executive commentary tab.',
            ],
            'images' => [
                'ews' => 'Early warning signals: the four indicators and the watchlist with its severity column',
                'report-ai-narrative' => 'The rule-based executive commentary, sentence by sentence',
            ],
            'routes' => ['ifrs9-reports.ews', 'ifrs9-reports.ai-narrative'],
        ],

        'ECL reconciliation report' => [
            'body' => '<p>The former ECL Reconciliation screen (Reports, ECL Reconciliation, with the Loan Portfolio, Start Period, End Period, Movement Type and Report Type boxes) has been absorbed by the IFRS 9 note, which carries the same movement by stage between two months, contract by contract, with transfers that net to nil, write-offs from the ledger and the register, and every column tied. There is no separate tile for it in the hub.</p>'
                . '<h4>What happens when you open it</h4><ul>'
                . '<li>Opening the old address, or an old link or bookmark to it, takes you to the IFRS 9 note with its start and end months and portfolio carried over (start becomes the opening month, end the closing month).</li>'
                . '<li>An old export link for the summary bridge now downloads the IFRS 9 note as CSV in full currency units for those months.</li>'
                . '<li>The detailed contract lists (new loans, derecognised loans or stage transitions) still download here as CSV when the address carries export=1, report_type=detailed, the detail type, a portfolio and both months. The file carries a heading block (report, portfolio, start and end period, movement type, export date) and then the contracts.</li></ul>'
                . '<p>No permission beyond signing in is checked on this address; the IFRS 9 note it opens needs <b>reports.ifrs9</b>.</p>'
                . '<h4>Empty states and messages</h4><ul><li>A detailed export that fails returns to the previous page with <b>Failed to export report:</b> and the reason.</li></ul>'
                . '<h4>Common problems</h4><ul><li><b>The Amounts written off line used to be empty.</b> The old screen had no write-off feed. The IFRS 9 note takes the write-offs from the ledger (by the write-off rule) and the approved write-off register, so use the note.</li></ul>',
            'steps' => [
                'Open Reports, the Annual report and audit tab, then IFRS 9 note (annual financial statements).',
                'Choose the opening and closing months, the units and, if needed, the portfolio.',
                'Read table (a) for the allowance and table (b) for the gross carrying amount.',
                'Download the CSV, Excel, Word or PDF once every check passes.',
            ],
            'images' => [
                'ecl-reconciliation' => 'The old ECL reconciliation address opens the IFRS 9 note with the same months and portfolio',
            ],
            'routes' => ['reports.ecl-reconciliation'],
        ],

        'Loan book reconciliation' => [
            'body' => '<p>A roll-forward of the gross book for one portfolio between two months, used to prove that the loan book you loaded moves the way it should: opening book plus new loans, less loans that left, plus the change on continuing loans, less write-offs, against the closing book. Open <b>Reports</b>, the <b>Annual report and audit</b> tab, then <b>Loan book reconciliation</b>. The hub shows the tile to holders of the <b>reports</b> permission. It is not a period-taking tile: the page chooses its own months.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>Loan book reconciliation</b> with the line <b>Opening book plus new loans, less loans that left, plus the change on continuing loans, against the closing book.</b></li>'
                . '<li>A bar with <b>Portfolio</b> (<b>Choose a portfolio</b>), <b>From</b> (<b>Opening month</b>) and <b>To</b> (<b>Closing month</b>), listing the months held for that portfolio. Opened with nothing chosen, the page picks the largest portfolio of the latest month and compares the latest month with the one before it.</li>'
                . '<li>KPI tiles: <b>Opening loan book</b>, <b>New loans</b> and <b>Closing loan book</b> (each with its number of loans) and <b>Difference</b> (green when the bridge ties, red when not).</li>'
                . '<li>A panel headed with the portfolio and the two months, with a badge <b>Ties</b> (green) or <b>Does not tie</b> (red), and the table with <b>Loans</b> and <b>Carrying amount</b>: <b>Opening loan book</b> (month), <b>Add: new loans (in the closing book only)</b>, <b>Less: loans repaid in full or derecognised (in the opening book only)</b>, <b>Net change on continuing loans (interest and drawdowns less repayments)</b>, <b>Less: amounts written off (ledger write-offs and the approved register)</b>, <b>Expected closing loan book</b>, <b>Closing loan book</b> (month) and <b>Difference</b> (in red and bold when it does not tie). Negative amounts are in brackets.</li>'
                . '<li>A line on write-offs: how many loans were written off between the two month-ends and the amount, from the ledger by the write-off rule (type 300, a batch debiting GL 1064, an ACCOUNT CLOSURE on a status-H account) and the approved write-off register; then, when there are any, a table with <b>Date</b>, <b>Contract</b>, <b>Customer</b>, <b>Written off</b> and <b>Record</b> (Ledger or Register and the reference).</li></ul>'
                . '<p>A write-off takes its amount out of the line the contract would otherwise sit in (new, gone or continuing) and shows it on the write-off line, so it is never counted twice.</p>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Annual report and audit group.</li>'
                . '<li>Changing <b>Portfolio</b> reloads the page and clears both months.</li>'
                . '<li><b>Run</b> (a play icon): builds the bridge. It stays greyed out until a portfolio and two months are chosen and From is before To.</li>'
                . '<li><b>CSV</b>, <b>Excel</b> and <b>Download PDF</b>: the bridge, the KPI tiles, the write-off list and two notes (whether it ties, and the write-offs) as plain data, a branded workbook or a branded A4 portrait PDF. They are greyed out until the bridge is shown.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>Before a run: <b>Choose a portfolio and two periods above, then Run.</b></li>'
                . '<li>Months in the wrong order: <b>The end period must be after the start period.</b></li>'
                . '<li>No write-off: the line reads <b>Write-offs: none in the ledger (by the write-off rule) or approved in the write-off register between the two month-ends.</b></li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Does not tie.</b> The bridge ties by construction when each contract appears once in each month. A difference almost always means a contract appears more than once in one month\'s book for the portfolio, so it is counted twice; the IFRS 9 note\'s checks name duplicated contracts. Correct the loan book and run again.</li>'
                . '<li><b>A negative net change on continuing loans.</b> Repayments exceeded interest and drawdowns. That is arithmetic, not an error.</li></ul>',
            'steps' => [
                'Open Reports, the Annual report and audit tab, then Loan book reconciliation.',
                'Choose the portfolio, then the From and To months.',
                'Press Run and check the badge reads Ties.',
                'Read the write-off line and list, and explain any Difference.',
                'Download the PDF or Excel for the working-paper file.',
            ],
            'images' => [
                'loanbook-reconciliation' => 'Loan book reconciliation: the portfolio and months, the KPI tiles, the bridge with the Ties badge and the write-off line',
            ],
            'routes' => ['reports.loan-book-reconciliation'],
        ],

        'Disbursements (vintage) report' => [
            'body' => '<p>A vintage view as a CSV file: for each month of origination, how much was lent and how much of it was still outstanding one, two and three months later. Open <b>Reports</b>, the <b>Exports</b> tab, then <b>Disbursements (vintage)</b>. The hub shows the tile to holders of the <b>reports</b> permission. The page has no on-screen table: it builds the file.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>Disbursements (vintage)</b> with the line <b>Loans disbursed each month between two months, with the balances still outstanding one, two and three months later, as CSV data.</b></li>'
                . '<li><b>Portfolio</b> (<b>All portfolios</b> or one portfolio), <b>Start month</b> and <b>End month</b> (month pickers) and <b>Detail</b>: <b>Summary by month</b> or <b>Loan by loan</b>.</li>'
                . '<li>The hint <b>Choose the months, then Download CSV at the top right. The file opens in Excel.</b></li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Exports group.</li>'
                . '<li><b>Download CSV</b> (download arrow): builds and downloads the file. It is greyed out, with the tip <b>Choose the start and end months first</b>, until both months are chosen and the end is not before the start.</li></ul>'
                . '<h4>What the file holds</h4><ul>'
                . '<li>A heading block: <b>Loan Disbursement Export Report</b>, the period range, the export mode, the portfolio and the export date.</li>'
                . '<li><b>DISBURSEMENT SUMMARY</b> with <b>Reporting Period</b> (the month of origination), <b>Total Disbursement</b> (the carrying amount of the cohort in its month of origination), <b>Number of Contracts</b>, <b>Average Disbursement</b>, <b>Movement 30 Days</b>, <b>Movement 60 Days</b> and <b>Movement 90 Days</b> (the cohort\'s outstanding carrying amount one, two and three months after origination).</li>'
                . '<li>With Loan by loan, also <b>DETAILED CONTRACT BREAKDOWN</b> with <b>Contract ID</b>, <b>External Identity ID</b>, <b>Disbursement Amount</b>, <b>Create Date</b> and <b>Portfolio Group</b>.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>End before start: <b>The end month must not be before the start month.</b> under End month.</li>'
                . '<li>A failure returns to the page with <b>Failed to generate report:</b> and the reason.</li></ul>'
                . '<h4>How to read it</h4><p>A cohort whose balance barely falls over three months is either an interest-only or grace-period structure or an early-arrears problem. Compare cohorts rather than reading one alone.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A cohort has no movement figures.</b> The loan books of the following months are not loaded, so there is nothing to measure against.</li>'
                . '<li><b>Totals differ from the ledger disbursements.</b> The file uses the carrying amount in the month of origination from the loan book, not a separate disbursement feed.</li></ul>',
            'steps' => [
                'Open Reports, the Exports tab, then Disbursements (vintage).',
                'Choose the portfolio, or leave All portfolios.',
                'Choose the start and end months.',
                'Choose Summary by month, or Loan by loan for the contract list as well.',
                'Press Download CSV at the top right and open the file in Excel.',
            ],
            'images' => [
                'disbursements' => 'Disbursements (vintage): the portfolio, month and detail choices and the Download CSV button',
            ],
            'routes' => ['reports.disbursement-report'],
        ],

        'Stress testing' => [
            'body' => '<p>Stress testing asks how much the ECL would rise under harsher assumptions: per-stage shocks to the PD and LGD, or a macro-economic shock run through a regression model. It recomputes loan by loan rather than scaling a total. Open <b>Reports</b>, the <b>Risk and analytics</b> tab, then <b>Stress testing</b>. Running, saving, loading and deleting scenarios all need <b>reports.ifrs9</b>; there is no second-person approval because nothing is posted.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>Stress testing</b> with the line <b>How much ECL would rise under per-stage PD and LGD shocks or a macro scenario, recomputed loan by loan.</b></li>'
                . '<li>The <b>Scenario</b> panel with two mode buttons, <b>PD / LGD drivers</b> and <b>Macro scenario</b> (the active one in green), <b>Reporting Period</b> (every month with a loan book) and <b>Portfolio</b> (<b>All portfolios</b> or one).</li>'
                . '<li>In driver mode: four preset buttons, <b>Baseline</b>, <b>Mild drought</b>, <b>Severe drought</b> and <b>Currency / input shock</b>, which fill the table; and a table with a row per <b>Stage</b> and the columns <b>PD multiplier</b> (1 leaves the PD unchanged, 1.5 raises it by half; the least accepted is 0.01) and <b>LGD add-on (% pts)</b> (5 adds five percentage points to the LGD). Stressed PD and LGD are capped at one hundred per cent.</li>'
                . '<li>In macro mode: <b>Regression model</b> (a saved model, or <b>Manual slope / intercept</b>, which shows <b>Slope</b> and <b>Intercept</b>), <b>Base macro value</b> and <b>Macro shock (%)</b>. The shock moves the macro value, the model turns it into an implied PD adjustment, and that becomes one PD multiplier applied to every loan.</li>'
                . '<li>Results, after a run: in macro mode an amber line naming the model, the base and shocked macro values, the implied PD adjustment and the multiplier; the tiles <b>Base ECL</b>, <b>Stressed ECL</b>, the change in ECL and the change in per cent (red when ECL rises); <b>By IFRS 9 Stage</b> with <b>Stage</b>, <b>Accounts</b>, <b>Exposure</b>, <b>Base ECL</b>, <b>Stressed ECL</b> and the change; and <b>By Portfolio</b> with the same columns and the change in per cent.</li>'
                . '<li><b>Save this scenario</b> with <b>Name</b> (placeholder <code>e.g. 2025 severe drought</code>) and <b>Description</b> (<code>Optional notes</code>).</li>'
                . '<li><b>Saved Scenarios</b>, when any exist: <b>Name</b>, <b>Period</b>, the PD multipliers and the LGD add-ons for Stage 1, 2 and 3, <b>Saved by</b>, and <b>Actions</b>.</li></ul>'
                . '<p>The base is each loan\'s ECL as the engine measured it (the undiscounted ECL, EAD x PD x LGD, scaled by the stressed over the base PD and LGD), not the discounted booked allowance, so Base ECL can differ from the ECL Provision on the hub reports. A loan with no ECL is measured directly.</p>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Risk and analytics group.</li>'
                . '<li><b>PD / LGD drivers</b> and <b>Macro scenario</b>: switch the input mode.</li>'
                . '<li>The preset buttons: fill the driver table; they do not run it.</li>'
                . '<li><b>Run Stress Test</b> (<b>Running...</b> while it works): runs the scenario. Greyed out without a period.</li>'
                . '<li><b>CSV</b>, <b>Excel</b> and <b>Download PDF</b>: the run on screen (its inputs, the tiles and both tables), recomputed from the same inputs. Greyed out until a run.</li>'
                . '<li><b>Save</b>: stores the period, portfolio, the six driver inputs, the description and a snapshot of the results, with your name.</li>'
                . '<li><b>Load</b>: puts a saved scenario\'s inputs back in the driver table and runs it.</li>'
                . '<li><b>Delete</b> (red): asks <b>Delete this scenario?</b> with <b>The saved scenario "..." is removed. This cannot be undone.</b>, and removes it on <b>Delete</b>.</li></ul>'
                . '<h4>What happens next</h4><p>Nothing is written to the loan book or the reported allowance. A run is an analysis; a saved scenario is the evidence of what was tested.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>Run failed. Check the selected period has data.</b> (or the server\'s reason).</li>'
                . '<li><b>Give the scenario a name first.</b> and <b>Could not save scenario.</b></li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The stressed figure barely moves.</b> Check the multipliers per stage; 1 stresses nothing. A Stage 3 PD is already one hundred per cent, so only the LGD add-on moves Stage 3.</li>'
                . '<li><b>Macro mode gives a multiplier of one.</b> The predicted value did not change, usually because the slope or the base macro value is zero.</li>'
                . '<li><b>A saved macro run reloads as drivers.</b> Save stores the driver table, not the macro inputs; note the macro inputs in the description.</li></ul>',
            'steps' => [
                'Open Reports, the Risk and analytics tab, then Stress testing.',
                'Choose the reporting period and the portfolio, or All portfolios.',
                'Choose PD / LGD drivers and a preset or your own multipliers and add-ons per stage; or choose Macro scenario, a regression model or a slope and intercept, the base macro value and the shock.',
                'Press Run Stress Test and read the Base ECL and Stressed ECL tiles and both tables.',
                'Give the scenario a name and description and press Save.',
                'Download the PDF or Excel of the run for the file.',
            ],
            'images' => [
                'stress-testing' => 'Stress testing: the scenario panel with the driver inputs and presets, the base against stressed results and the saved scenarios',
            ],
            'routes' => ['stress-testing.index', 'stress-testing.run', 'stress-testing.run-macro', 'stress-testing.save', 'stress-testing.destroy'],
        ],

        'Booked ECL: the basis of the loss allowance' => [
            'body' => '<p>Every report in the hub that carries a loss allowance shows the <b>booked</b> allowance: the expected credit loss projected over each loan\'s life and discounted at its original effective interest rate (IFRS 9 5.5.17, B5.5.44), as the month-end ECL run discounts it. The undiscounted engine build-up (EAD x PD x LGD at the reporting date) is shown beside it where it helps, and is labelled as undiscounted. This article explains the section every such report ends with, so the two figures are never confused.</p>'
                . '<h4>Where you see it</h4><ul>'
                . '<li>At the end of Executive summary, ECL by stage, ECL by segment, Stage movement and SICR, RBM classification and provisioning (on these consolidated reports as a tab of its own, <b>Basis of the allowance</b>), and of Portfolio ECL trend, Account-level ECL calculation, Data quality and exceptions and the standalone Opening to closing ECL and ECL charge or release tables, as a section headed <b>Basis of the loss allowance</b>.</li>'
                . '<li>In the IFRS 9 note as section (f), in the RBM return above table B, and in the CSV, Excel and PDF of each report.</li></ul>'
                . '<h4>What the section shows</h4><ul>'
                . '<li>The columns <b>Basis</b>, <b>Loans</b> and <b>Amount</b>. One line per basis that has loans: <b>Discounted at the original EIR (booked)</b>; <b>Mega Farm programme measure (D30, outside the EIR)</b> (the Mega Farm programme loans, measured by the programme method at MAIIC\'s share and booked as such); <b>No discounted figure: undiscounted ECL shown</b>; <b>Nil ECL, nothing to discount</b>; <b>No ECL calculated</b>. Then <b>Allowance shown</b>.</li>'
                . '<li>The reconciliation: <b>Reconciliation: ECL at the reporting date, undiscounted (EAD x PD x LGD)</b>, <b>Add: lifetime time-phased projection over the reporting-date measure (discounted loans)</b>, <b>Less: effect of discounting at the original EIR (discounted loans)</b> and <b>Allowance shown</b>. It ties loan by loan.</li>'
                . '<li>The note under the heading in words, for example the number of loans discounted at the original EIR and their amount, and the number of Mega Farm programme loans at the programme measure.</li></ul>'
                . '<h4>When the allowance is not wholly booked</h4><ul>'
                . '<li>If some loans with an ECL have no discounted figure, the heading reads <b>Basis of the loss allowance: not wholly booked</b>, the report subtitle adds <b>(the allowance includes N loans with no discounted figure, at their undiscounted ECL: see the basis section)</b>, and the note gives the number, the amount and the reason for each group, for example no original EIR to discount at, the time-phased projection could not run, only a proxy horizon or rate was applied, or the ECL run for the month was undiscounted. Those loans are shown at their undiscounted ECL, never silently.</li>'
                . '<li>The IFRS 9 note refuses to issue in that state: its check fails and its downloads stop until every loan carries its discounted allowance.</li></ul>'
                . '<h4>Where the undiscounted figure still appears</h4><ul>'
                . '<li>ECL by stage, columns <b>ECL undiscounted</b> and <b>ECL discounted</b> (the saved runs), and Account-level ECL calculation, column <b>EAD x PD x LGD</b> beside <b>Booked ECL</b>.</li>'
                . '<li>Stress testing (Base ECL), the ECL export (column ECL and ECL Value) and the ECL by stage file in the Auditor pack use the undiscounted engine ECL. Read them as the build-up, not as the booked allowance.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The heading says not wholly booked.</b> Lock the original EIR of each loan the ECL run names and run the month-end ECL again with discounting.</li>'
                . '<li><b>The booked figure is below the engine build-up.</b> Expected: discounting reduces a lifetime loss; the reconciliation lines show by how much.</li></ul>',
            'steps' => [
                'Open any ECL report from the hub.',
                'Open the Basis of the allowance tab, or scroll to the Basis of the loss allowance section.',
                'Check the heading does not say not wholly booked.',
                'Read the reconciliation from the undiscounted ECL to the allowance shown.',
                'If loans are not booked, lock their EIR and run the month-end ECL again before reporting.',
            ],
            'images' => [
                'report-basis' => 'The Basis of the allowance tab: the loans and amount on each basis and the reconciliation from the undiscounted ECL to the booked allowance',
            ],
            'routes' => [],
        ],

        'Executive summary report' => [
            'body' => '<p>The tile <b>Executive summary</b> is the ECL position for a month on one page: the headline figures, the split by stage and by portfolio, the ten largest exposures, the data-quality flags and a written commentary. Start here in a review meeting. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Executive summary</b>. It needs <b>reports.ifrs9</b> and opens on the month chosen in the hub.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The subtitle <b>Consolidated IFRS 9 ECL position for</b> and the month.</li>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b> (the booked allowance), <b>Coverage Ratio</b> and <b>Loans</b>, each with its comparison against the previous month.</li>'
                . '<li>Tab <b>Executive summary</b>, four sections: <b>ECL by IFRS 9 Stage</b> and <b>ECL by Portfolio</b> (each with <b>Loans</b>, <b>EAD</b>, <b>ECL</b> and <b>Coverage</b>; loans with no portfolio show as Unmapped; portfolios largest ECL first), <b>Top 10 Exposures</b> (<b>Contract</b>, <b>Client</b>, <b>Stage</b>, <b>EAD</b>, <b>ECL</b>) and <b>Data Quality Flags</b> (<b>Check</b>, <b>Records</b>: Missing sector tag, Unmapped portfolio, ECL not calculated, Zero ECL on Stage 3).</li>'
                . '<li>Tab <b>Executive commentary</b>: the rule-based commentary (see Analytics: Early Warning System and AI Executive Commentary).</li>'
                . '<li>Tab <b>Basis of the allowance</b>: the booked basis and its reconciliation.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons (Back to reports, Period, CSV, Excel, Download PDF, the part tabs), explained in Reading a report page. The Excel workbook has one sheet per tab.</p>'
                . '<h4>How to read it</h4><ul>'
                . '<li>Read the comparison lines on the tiles first: a rise in ECL Provision with a flat Exposure means risk worsened rather than the book grew.</li>'
                . '<li>Any Data Quality Flag above nil should be explained before the figures are circulated; the full checks are in Data quality and exceptions.</li></ul>'
                . '<h4>Empty states and messages</h4><ul><li><b>No data for this section in ...</b> when the month has no loan book.</li></ul>'
                . '<h4>Common problems</h4><ul><li><b>ECL Provision differs from ECL undiscounted on ECL by stage.</b> The tile is the booked (discounted) allowance; see Booked ECL: the basis of the loss allowance.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Executive summary.',
                'Check the month in Period.',
                'Read the four tiles and their comparison with the previous month.',
                'Read the stage and portfolio split, the top ten exposures and the data quality flags.',
                'Open the Executive commentary and Basis of the allowance tabs.',
                'Press Download PDF for the meeting pack.',
            ],
            'images' => [
                'report-executive' => 'Executive summary: the four tiles, the part tabs, ECL by stage and by portfolio, the top ten exposures and the data quality flags',
            ],
            'routes' => ['ifrs9-reports.executive'],
        ],

        'ECL by segment report' => [
            'body' => '<p>The tile <b>ECL by segment</b> answers where exposure and ECL sit: by sector, by portfolio and sector PD, by product group, by internal grade, by concentration and by cooperative link. It absorbs the former reports ECL by Product Group, ECL by Internal Grade, Concentration and Large Exposures, and Cooperative and Anchor Linkage. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>ECL by segment</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b> and <b>Loans</b>.</li>'
                . '<li>Tab <b>By sector</b>: <b>ECL by Economic Sector</b> with <b>Sector</b> (the RBM economic sector; <b>Untagged</b> where none), <b>Loans</b>, <b>EAD</b>, <b>Avg PD</b>, <b>Avg LGD</b>, <b>ECL</b> and <b>Coverage</b>, largest ECL first.</li>'
                . '<li>Tabs <b>PD by portfolio</b> and <b>PD by sector</b>: <b>Observed one-year PD by portfolio</b> (or by RBM sector) with the window in brackets, and the columns <b>Portfolio</b> (or <b>RBM sector</b>), <b>Stage</b>, <b>Loans at start</b>, <b>Defaults</b>, <b>Observed PD</b> (<b>(thin)</b> marks a stage below the minimum for its own PD; <b>in default</b> for Stage 3), <b>PD applied</b>, <b>PD from</b> (own, pooled book, or the segment it came from; shown only on the governed basis), <b>Loans now</b>, <b>EAD</b>, <b>ECL</b> and <b>Coverage</b>, with the lines <b>Pooled book (the parent)</b>. The note says whether the cut is the governed basis the ECL uses or a report only. PD by sector adds <b>Loans on an E-Banker sector code held apart</b> (<b>Code</b>, <b>Product group</b>, <b>Loans</b>, <b>EAD</b>, <b>ECL</b>). When the PD cannot be measured the row reads <b>Not measured:</b> and the reason.</li>'
                . '<li>Tab <b>By product group</b>: <b>ECL by Product Group</b> with <b>Product Group</b> (<b>Unspecified</b> where none), <b>Loans</b>, <b>EAD</b>, <b>ECL</b> and <b>Coverage</b>.</li>'
                . '<li>Tab <b>By internal grade</b>: <b>ECL by Internal Risk Grade</b> (A lowest risk, G default) with <b>Grade</b>, <b>PD Band</b> (A 0 - 2%, B 2 - 5%, C 5 - 10%, D 10 - 20%, E 20 - 40%, F 40 - 100%, G Default (100%)), <b>Loans</b>, <b>EAD</b>, <b>Avg PD</b> (the 12-month PD), <b>Avg LGD</b>, <b>ECL</b> and <b>Coverage</b>. Every grade is listed, empty ones as zeros.</li>'
                . '<li>Tab <b>Concentration and large exposures</b>: a filter bar with <b>Large-exposure threshold</b> and the currency (default 1,000,000) and <b>Run</b>; <b>Key figures</b> (<b>Largest Single Name</b> and <b>Top 10 Names</b> as a share of the book, <b>Portfolio HHI</b>, <b>Total EAD</b>); <b>Top 20 Single-Name Exposures</b> (<b>Customer</b>, <b>Loans</b>, <b>EAD</b>, <b>ECL</b>, <b>% of Book</b>); <b>Portfolio Concentration (HHI = ...)</b> (<b>Portfolio</b>, <b>EAD</b>, <b>% of Book</b>); and <b>Large Exposures</b> at or above the threshold, up to fifty (<b>Contract</b>, <b>Client</b>, <b>Stage</b>, <b>EAD</b>, <b>ECL</b>). The Herfindahl-Hirschman index sums the squared portfolio shares; above 2,500 counts as highly concentrated.</li>'
                . '<li>Tab <b>Cooperative and anchor links</b>: <b>Key figures</b> (<b>Cooperative/Anchor Groups</b>, <b>Largest Linked Group</b>, <b>Largest Group Exposure</b>, <b>Contagion Loss (top group defaults)</b>), then <b>Exposure by Cooperative / Anchor (correlated default risk)</b> with <b>Cooperative / Anchor</b>, <b>Loans</b>, <b>EAD</b>, <b>% of Book</b> and <b>ECL</b>. The contagion loss is indicative: the largest linked group (direct loans excluded) defaulting at its average collection LGD, less the ECL already held on it. Full asset-correlation modelling is separate work.</li>'
                . '<li>Tab <b>Basis of the allowance</b>.</li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li>The standard report buttons, explained in Reading a report page.</li>'
                . '<li><b>Run</b> (play icon), on the Concentration tab only: reloads the report with the threshold typed in the box. The threshold travels into the downloads.</li></ul>'
                . '<h4>Empty states and messages</h4><ul><li><b>No data for this section in ...</b>; a Large Exposures section with no row means no loan reaches the threshold.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Concentration looks extreme.</b> Check the threshold before drawing a conclusion; a low one pulls many loans into the large-exposure table.</li>'
                . '<li><b>A large Untagged sector.</b> Loans without a sector tag; they also show in Data quality and exceptions. Capture the sector at source.</li>'
                . '<li><b>A grade band is empty.</b> No loan carried a PD in that band that month; that is a finding, not an error.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then ECL by segment.',
                'Read By sector, then the PD by portfolio and PD by sector tabs.',
                'Open By product group and By internal grade.',
                'On Concentration and large exposures, type the threshold and press Run.',
                'Read Cooperative and anchor links for correlated exposure.',
                'Download the PDF or Excel; the workbook has one sheet per tab.',
            ],
            'images' => [
                'report-sector-ecl' => 'ECL by segment: the By sector tab with EAD, PD, LGD, ECL and coverage by economic sector',
                'report-product-group-ecl' => 'ECL by product group (the By product group tab of ECL by segment)',
                'report-grade-ecl' => 'ECL by internal grade across the A to G master scale with the PD bands',
                'report-concentration' => 'Concentration and large exposures: the threshold box and Run, the top single names, the portfolio HHI and the large exposures',
                'report-coop-linkage' => 'Cooperative and anchor links: exposure by cooperative or anchor buyer and the indicative contagion loss',
            ],
            'routes' => ['ifrs9-reports.sector-ecl', 'ifrs9-reports.product-group-ecl', 'ifrs9-reports.grade-ecl', 'ifrs9-reports.concentration', 'ifrs9-reports.coop-linkage'],
        ],

        'Portfolio ECL trend report' => [
            'body' => '<p>The tile <b>Portfolio ECL trend</b> shows how the booked allowance has moved month by month, by portfolio, so you can see whether a movement is one segment or the whole book. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Portfolio ECL trend</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The subtitle <b>The loss allowance (discounted at the EIR where the engine discounted it) by portfolio over the last N periods</b>.</li>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b> and <b>Loans</b>, for the month chosen.</li>'
                . '<li>The section <b>ECL by Portfolio over Time</b>: one row per month (up to the last twelve months with an ECL up to the month chosen, oldest first), one column per portfolio with its booked allowance, then <b>Total</b> and <b>Of which undiscounted</b>. The last column counts, for each month, the loans that have no discounted allowance and are included at their undiscounted ECL, with their amount (for example 12 loans, 45,000.00), or a dash when every loan is booked.</li>'
                . '<li>The <b>Basis of the loss allowance</b> section for the month chosen.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page.</p>'
                . '<h4>How to read it</h4><p>Compare Total month to month, then find the portfolio that carries the change. A month with a non-dash Of which undiscounted is not on a like-for-like basis with a fully booked month.</p>'
                . '<h4>Empty states and messages</h4><ul><li>A month with only an old sector-level test run (no portfolio-level ECL) is left out of the trend.</li></ul>'
                . '<h4>Common problems</h4><ul><li><b>A jump in one month.</b> Check Of which undiscounted for that month first: a change of basis looks like a change of risk.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Portfolio ECL trend.',
                'Choose the latest month in Period; the table goes back up to twelve months.',
                'Read the Total column, then the portfolio that drives the change.',
                'Check Of which undiscounted is a dash in every month you compare.',
            ],
            'images' => [
                'report-portfolio-trend' => 'Portfolio ECL trend: the booked allowance by portfolio for each month, the total and the undiscounted count',
            ],
            'routes' => ['ifrs9-reports.portfolio-trend'],
        ],

        'Account-level ECL calculation report' => [
            'body' => '<p>The tile <b>Account-level ECL calculation</b> is the loan-by-loan trail an auditor asks for to re-perform the arithmetic: for the two hundred largest exposures, the EAD, PD, LGD, their product and the booked ECL. Open <b>Reports</b>, the <b>Month-end ECL</b> tab, then <b>Account-level ECL calculation</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The subtitle <b>Loan-by-loan ECL = EAD x PD x LGD (top 200 by exposure)</b>.</li>'
                . '<li>KPI tiles: <b>Exposure (EAD)</b>, <b>ECL Provision</b>, <b>Coverage Ratio</b> and <b>Loans</b> for the whole book.</li>'
                . '<li>The section <b>Account-Level ECL Calculation Trail</b> with <b>Contract</b>, <b>Client</b> (<b>(Unnamed)</b> where none), <b>Stage</b>, <b>EAD</b>, <b>PD</b> (after the forward-looking adjustment where it ran, else before), <b>LGD</b>, <b>EAD x PD x LGD</b> (the undiscounted reporting-date measure) and <b>Booked ECL</b> (the discounted allowance, or the programme measure for a Mega Farm loan). Fifteen rows a page.</li>'
                . '<li>The <b>Basis of the loss allowance</b> section.</li></ul>'
                . '<h4>Buttons and icons</h4><p>The standard report buttons, explained in Reading a report page. The downloads carry all two hundred rows.</p>'
                . '<h4>How to read it</h4><p>EAD x PD x LGD and Booked ECL differ by the lifetime projection and the discounting at the original EIR. Where they are equal the loan has no discounted figure and is shown at its undiscounted ECL; the basis section counts those loans.</p>'
                . '<h4>Common problems</h4><ul><li><b>The trail and the summary totals disagree.</b> The trail shows the largest two hundred exposures only; the tiles cover the whole book.</li></ul>',
            'steps' => [
                'Open Reports, the Month-end ECL tab, then Account-level ECL calculation.',
                'Check the month in Period.',
                'Re-perform EAD x PD x LGD for a sample of rows.',
                'Compare it with Booked ECL and read the basis section for the difference.',
                'Download the Excel copy for the audit working papers.',
            ],
            'images' => [
                'report-account-ecl' => 'Account-level ECL calculation: the loan-by-loan trail with EAD, PD, LGD, their product and the booked ECL',
            ],
            'routes' => ['ifrs9-reports.account-ecl'],
        ],

        'IFRS 9 note (financial statements disclosure)' => [
            'body' => '<p>The IFRS 9 note is the IFRS 7 disclosure of loans and advances to customers between two months with a calculated ECL: the reconciliations of the loss allowance and of the gross carrying amount by stage, the position with its comparative, the charge, the basis of measurement, the undiscounted measure to the booked allowance, the write-offs and the modified financial assets. It absorbs the former Financial Statement Disclosure, Opening to closing ECL, Gross carrying amount movement and ECL charge or release reports. This is the report the finance team hands to the auditor. Open <b>Reports</b>, the <b>Annual report and audit</b> tab, then <b>IFRS 9 note (annual financial statements)</b>. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>IFRS 9 note for the annual financial statements</b> with the line <b>The IFRS 7 note: loss allowance and gross carrying amount by stage between two months.</b></li>'
                . '<li>At the top right, the opening month (<b>From</b> and the month, for example From Dec 2025), the closing month (<b>To</b> and the month), the units (the currency, the currency followed by \'000, or the currency followed by million; thousands by default) and, when there is more than one portfolio, <b>All portfolios</b> or one portfolio. By default the closing month is the latest month with an ECL and the opening month is the December before it when that month has an ECL, else the first month of the same year, else the latest earlier month.</li>'
                . '<li>A status line: green with a tick, <b>Every column of both reconciliations ties, every loan carries its discounted allowance, and the figures agree with the loan book and the saved ECL runs.</b> followed by the scope and the units; or a red banner <b>Do not use this note:</b> with the reason, <b>Downloads are stopped until every check passes. See Workings and checks (N failed).</b></li>'
                . '<li>KPI tiles: <b>Opening ECL</b> and <b>Closing ECL</b> (in the units chosen), <b>Charge for the period</b>, <b>Coverage at</b> the closing month and <b>Contracts at</b> the closing month.</li>'
                . '<li>Two tabs: <b>The note</b> and <b>Workings and checks</b> (with the number of checks).</li></ul>'
                . '<h4>The note, section by section</h4><ul>'
                . '<li><b>(a) Reconciliation of the loss allowance</b> (IFRS 7.35H) and <b>(b) Reconciliation of the gross carrying amount</b> (IFRS 7.35I). Columns <b>Stage 1</b> (12-month ECL), <b>Stage 2</b> (Lifetime ECL not credit-impaired), <b>Stage 3</b> (Lifetime ECL credit-impaired) and <b>Total</b> with the units. Rows: the opening balance with its month, <b>Transfers to Stage 1</b>, <b>Transfers to Stage 2</b>, <b>Transfers to Stage 3</b>, <b>Net remeasurement of ECL</b> (in (b) <b>Repayments and other changes in balances</b>), <b>New financial assets originated</b>, <b>Financial assets derecognised</b>, <b>Amounts written off</b> and the closing balance. The movement is attributed contract by contract: a loan that changed stage moves its opening balance to the new stage (the transfers net to nil), loans only in the closing book are originated, loans only in the opening book are derecognised, and a write-off carries the amount written off on the gross line and the allowance it used on the allowance line. Nil shows as a dash and negatives in brackets.</li>'
                . '<li><b>(c) Exposure, loss allowance and coverage by stage</b> (IFRS 7.35M), at the closing month with the opening month for comparison: <b>Gross carrying amount</b>, <b>Loss allowance</b>, <b>Net carrying amount</b>, <b>ECL coverage</b> and <b>Number of contracts</b>.</li>'
                . '<li><b>(d) Impairment charge to profit or loss</b>: <b>ECL charge / (release) for the period</b>, the closing allowance less the opening, less the amounts written off.</li>'
                . '<li><b>(e) Basis of measurement</b>: paragraphs written from the system\'s settings and the inputs applied: the staging thresholds in force at the closing month (including the Mega Farm programme facilities where a row exists), the PD and LGD applied by stage, the PD and LGD records used, how the movement is attributed, the discounting at the closing month in figures, and the write-off policy with the IFRS 7.35L statement.</li>'
                . '<li><b>(f) The loss allowance: undiscounted measure to the discounted allowance</b>, for the closing and the opening month: <b>ECL at the reporting date, undiscounted (EAD x PD x LGD)</b>, <b>Add: lifetime projection over the reporting-date measure</b>, <b>Less: effect of discounting at the original EIR</b>, <b>Loss allowance</b>, and in grey <b>Of which with no discounted figure, shown undiscounted (N loans)</b>. When a month is not wholly booked an amber box gives the reason.</li>'
                . '<li><b>(g) Amounts written off</b> (IFRS 7.35I, 35L): each write-off between the two months from the ledger (the write-off rule) and the approved write-off register, with <b>Date</b>, <b>Contract</b>, <b>Customer</b>, <b>Amount</b> and <b>Record</b> (Ledger or Register and the reference), and <b>Total written off</b>; then the sentence on amounts still subject to enforcement activity.</li>'
                . '<li><b>(h) Modified financial assets</b> (IFRS 7.35J(a), 35J(b), 20A; IFRS 9 5.4.3, B5.4.6), from the restructure register (EIR &amp; Revenue Recognition, Restructures &amp; Resets): only modifications one person classified, a second person approved and the revenue run of their month applied. The table has <b>Reference</b>, the line, <b>Contracts</b> and the amount: amortised cost before modification of assets modified while on lifetime ECL and their net gain or loss (35J(a)); the gross carrying amount of assets modified while on lifetime ECL that moved to 12-month ECL in the period (35J(b)); the net modification gain or loss of all modifications that did not derecognise (5.4.3); the carrying amount derecognised on a substantial modification and the gain or loss on it (20A); and the catch-up on revised cash-flow estimates (B5.4.6). A gold badge counts the candidates awaiting confirmation (hover it for not yet classified and proposed); they are never disclosed. The message under the table turns red when a confirmed modification has not been applied by the revenue run or was stopped by it.</li></ul>'
                . '<h4>Workings and checks</h4><ul>'
                . '<li><b>Checks</b> with <b>Result</b> (<b>PASS</b> green, <b>WARN</b> gold, <b>FAIL</b> red, <b>INFO</b> grey), <b>Check</b> and <b>Detail</b>: both reconciliations tie in every column; the opening and closing figures agree with the loan books; each month agrees with its saved ECL run; each month\'s allowance is the booked (discounted) allowance; loans without a stage, duplicated contracts, loans without an ECL value; the amounts written off; write-offs on contracts outside the note; and the modified financial assets.</li>'
                . '<li><b>Rounding reconciliation</b> for the loss allowance and the gross carrying amount (in thousands or millions only): each figure is rounded on its own and the rounding left in a column is absorbed in its remeasurement line so the column casts.</li>'
                . '<li><b>Number of contracts behind each movement</b>, with transfers and remeasurement counted by their closing stage.</li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Annual report and audit group.</li>'
                . '<li>The From, To, units and portfolio boxes: each reloads the note. A closing month on or before the opening month cannot be chosen.</li>'
                . '<li><b>CSV</b> (the note and workings as data), <b>Excel</b> (a workbook), <b>Word</b> (the note as a Word document, ready to paste into the financial statements) and <b>Download PDF</b> (the note and workings as a branded PDF). All four are greyed out unless every check passes; the file name carries the two months and the units.</li>'
                . '<li>The tabs <b>The note</b> and <b>Workings and checks</b>.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>Fewer than two ECL months: <b>The note compares two months with a calculated ECL, and fewer than two months have one. Run the ECL calculation for another month first.</b></li>'
                . '<li>A month without a saved ECL run: <b>No ECL has been calculated for</b> the month, <b>so there is no loss allowance to reconcile. Choose months with a calculated ECL, or run the ECL calculation for that month first.</b></li>'
                . '<li>A failed check: <b>A check failed (the reconciliation does not tie, or a loan has no discounted allowance). The note must not be used in the financial statements until the failed checks below are cleared.</b></li>'
                . '<li>No write-off: <b>No loan was written off in the period.</b> No modification: the message says there is nothing to disclose under IFRS 7.35J(a), 35J(b) or 20A, and no B5.4.6 catch-up.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>FAIL on loans without a discounted allowance.</b> Lock the original EIR of each loan the ECL run names and run the month-end ECL again.</li>'
                . '<li><b>FAIL on modified financial assets.</b> Run the EIR revenue for the month of each confirmed modification, or clear the reason it was stopped on the register.</li>'
                . '<li><b>WARN that a month disagrees with its saved ECL run.</b> The loan book changed after the run; run the ECL calculation for that month again.</li>'
                . '<li><b>The note does not match the signed accounts.</b> Check the months, the portfolio and any adjustment made outside the system; a presentation difference must be documented.</li></ul>',
            'steps' => [
                'Open Reports, the Annual report and audit tab, then IFRS 9 note (annual financial statements).',
                'Choose the opening and closing months, the units and, if needed, the portfolio.',
                'Check the status line is green; if not, open Workings and checks and clear each FAIL.',
                'Read (a) to (d) for the reconciliations, the position and the charge.',
                'Read (f) to (h) for the booked basis, the write-offs and the modified assets.',
                'Download the Word copy for the financial statements and the PDF or Excel for the audit file.',
            ],
            'images' => [
                'report-fs-disclosure' => 'The IFRS 9 note: the months, units and download buttons, the status line, the tiles and reconciliation (a) by stage',
                'report-fs-disclosure-workings' => 'The Workings and checks tab of the IFRS 9 note: each check with its result and detail, and the rounding reconciliation',
            ],
            'routes' => ['ifrs9-reports.fs-disclosure'],
        ],

        'RBM return' => [
            'body' => '<p>The RBM return is the classification and provisioning return of the Reserve Bank of Malawi Credit Risk Management for DFIs Directive, 2018 (paragraphs 10 to 16), filled from the system in the directive\'s own order. Its status is <b>provisional</b>: the lines follow the directive until MAIIC Risk\'s prescribed form arrives, when they are re-ordered and re-worded to it line for line while the figures stay as they are. Open <b>Reports</b>, the <b>Regulatory (RBM)</b> tab, then <b>RBM return (provisional)</b>. It needs <b>reports.ifrs9</b> and opens on the month chosen in the hub.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>RBM return (provisional)</b> with a line naming the directive and noting that the loan loss reserve is reported on the call report the Registrar prescribes (paragraph 15(3)).</li>'
                . '<li>At the top right: the month (the last thirty-six months with a loan book; it opens on the latest month built from the feed), the tick box <b>Include Mega Farm</b>, and the downloads.</li>'
                . '<li>KPI tiles: <b>Facilities</b>, <b>Total balance</b>, <b>NPL ratio (substandard and below)</b> and <b>Provision: the higher of the two</b>, with the line minimum ... , IFRS 9 ... under it.</li>'
                . '<li><b>How this return is laid out</b>, a fold-out with the notes: the provisional layout, that section B applies the minimum rates to the gross balance because the eligible security is not yet held, the basis of the IFRS 9 allowance, the paragraph 12 summary and the section C sources.</li>'
                . '<li><b>A. Classification of facilities (paragraphs 10 and 11)</b>: the bands by term (short term to 30, 90, 180 and 365 days; medium and long term to 90, 180, 365 and 746 days), then <b>Class</b> (Pass, Special mention, Substandard, Doubtful, Loss), <b>Short-term accounts</b>, <b>Short-term balance</b>, <b>Medium/long accounts</b>, <b>Medium/long balance</b>, <b>Total accounts</b>, <b>Total balance</b> and <b>IFRS 9 allowance</b>, with the total.</li>'
                . '<li><b>B. Provisions (paragraph 14 and the Schedule; loan loss reserve, paragraph 15)</b>: a line <b>IFRS 9 allowance:</b> with the basis of the allowance (amber when not wholly booked), then <b>Class</b>, <b>Balance</b>, <b>Minimum rate</b> (0, 5, 20, 50 and 100 per cent), <b>Minimum provision</b>, <b>IFRS 9 allowance</b> (the booked, discounted allowance) and <b>Shortfall</b> (red when above nil); <b>Totals</b> with <b>higher of the two</b>; and <b>Loan loss reserve: the minimum provision over the IFRS 9 allowance, an appropriation of retained earnings (paragraph 15(1))</b>.</li>'
                . '<li><b>Loss facilities to be written off (paragraph 12)</b>: a facility classified loss is to be written off in the quarter after the one in which it became loss. A badge reads N of M past the write-off quarter with their balance (red when any, green when none). Columns <b>Contract</b>, <b>Customer</b>, <b>Term</b> (Short or Medium/long), <b>Days past due</b>, <b>Loss since</b> (the first month-end of the unbroken run of loss classification; <b>at or before</b> when the run reaches the first month the books hold), <b>Months loss</b>, <b>Write off by</b>, <b>Balance</b>, <b>IFRS 9 allowance</b> and <b>Status</b> (<b>overdue</b> red; <b>due this quarter</b> or <b>due next quarter</b> gold). Write-offs themselves are proposed and approved in the write-off register (EIR Data, Write-offs).</li>'
                . '<li><b>C. Interest in suspense (paragraph 13)</b>, line by line: non performing accounts and balance; interest posted in period; interest recognised eir net basis; interest in suspense (the contractual interest E-Banker posted in the month less the EIR interest on the net basis); accounts with interest posted; and the cumulative lines of paragraph 13(1): <b>cumulative interest charged since non performing</b> (the contractual interest charged on each facility non-performing at the month-end since the month it became non-performing on the loan books), <b>cash received since non performing</b> (shown beside it because the ledger does not split a repayment between principal and interest), <b>facilities non performing from the first month held</b>, <b>gl 1320 interest suspense</b> (<b>not held</b> when the trial balance is not loaded) and <b>difference cumulative less gl 1320</b>. The note under it explains what is and is not measured.</li>'
                . '<li><b>D. Restructured facilities (paragraph 16(1))</b>: the facilities of this return with a restructure confirmed on the register (EIR &amp; Revenue Recognition, Restructures &amp; Resets) by the month-end, each with the Board approval paragraph 16(1) requires. A red badge counts those <b>without a Board approval recorded</b>. Tiles <b>Facilities restructured</b>, <b>Their balance</b>, <b>Restructured in the month</b>, <b>Without Board approval</b> and <b>Candidates awaiting confirmation</b> (not reported as restructured). Columns <b>Contract</b>, <b>Customer</b>, <b>Restructured on</b> (a gold <b>this month</b> badge for the month\'s own), <b>Classification</b>, <b>Treatment</b> (or <b>none booked: before the take-on, or not yet applied by the revenue run</b>), <b>Board approval</b> (the reference and date, or a red <b>none recorded</b>), <b>Class</b> and <b>Balance</b>; the total counts a facility restructured twice once.</li>'
                . '<li><b>E. Security against classified facilities</b>: classified accounts, security rows against them and security value on register, with the note that these are E-Banker register values, not valuations.</li></ul>'
                . '<h4>Mega Farm</h4><p>By default the return covers the loan GLs of the E-Banker feed and leaves out the Mega Farm programme loans, which are measured by the programme method outside the EIR. Tick <b>Include Mega Farm</b> to add them; the page, the basis line and the downloads follow the tick.</p>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Regulatory (RBM) group.</li>'
                . '<li>The month box and <b>Include Mega Farm</b>: each reloads the return.</li>'
                . '<li><b>CSV</b>, <b>Excel</b> and <b>Download PDF</b>: the return as plain data, a branded workbook or a branded PDF, each with the KPI tiles, sections A to E, the paragraph 12 list and a <b>Per facility</b> section (contract, class, term, days past due, balance and IFRS 9 stage) that the screen does not show. Greyed out when the return could not be built. The file name is MAIIC-RBM-return-provisional and the month.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>A red box with the reason when the return cannot be built for the month.</li>'
                . '<li><b>No facility is classified loss at this month-end.</b></li>'
                . '<li><b>No facility in this return has a restructure confirmed on the register by the month-end.</b>, or <b>The restructure register is not installed (run the migrations).</b></li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Facilities past the write-off quarter.</b> Propose the write-offs in the write-off register; a second person approves them.</li>'
                . '<li><b>Without Board approval above nil.</b> Record the Board or credit committee approval on the restructure in the register.</li>'
                . '<li><b>The figures differ from RBM classification and provisioning.</b> That report includes Mega Farm; tick Include Mega Farm to compare like for like.</li></ul>',
            'steps' => [
                'Open Reports, the Regulatory (RBM) tab, then RBM return (provisional).',
                'Check the month, and tick Include Mega Farm only if the return should carry the programme loans.',
                'Read the tiles, then sections A and B and the loan loss reserve.',
                'Clear the paragraph 12 list: propose the overdue write-offs in the write-off register.',
                'Check section C against GL 1320 and section D for Board approvals.',
                'Download the Excel and PDF for submission and the file.',
            ],
            'images' => [
                'rbm-return' => 'The RBM return (provisional): the month and Include Mega Farm, the tiles, section A by class and term, and section B with the loan loss reserve',
            ],
            'routes' => ['rbm-return.index', 'rbm-return.export'],
        ],

        'Loan book export and ECL export' => [
            'body' => '<p>Two data exports for analysis outside the system, both as CSV files that open in Excel. Open <b>Reports</b>, the <b>Exports</b> tab, then <b>Loan book export</b> or <b>ECL export</b>. The hub shows these tiles to holders of the <b>reports</b> permission. Neither page shows a table: each builds a file.</p>'
                . '<h4>Loan book export</h4><ul>'
                . '<li>The line <b>The loan book between two months, as a summary by month or loan by loan, as CSV data.</b></li>'
                . '<li><b>Portfolio</b> (<b>All portfolios</b> or one), <b>Start month</b>, <b>End month</b> and <b>Detail</b> (<b>Summary by month</b> or <b>Loan by loan</b>), and the hint <b>Choose the months, then Download CSV at the top right. The file opens in Excel.</b></li>'
                . '<li>The file: <b>Loan Book Export Report</b>, the date range and the mode, then for the summary <b>Reporting Period</b>, <b>Stage 1</b>, <b>Stage 2</b>, <b>Stage 3</b>, <b>Total Carrying Amount</b>, <b>Stage 1 Count</b>, <b>Stage 2 Count</b>, <b>Stage 3 Count</b> and <b>Total Count</b>; or loan by loan <b>Contract ID</b>, <b>Carrying Amount</b>, <b>IFRS9 Stage</b> and <b>Reporting Period</b>. The stage in this file is the calculated stage on the loan book.</li></ul>'
                . '<h4>ECL export</h4><ul>'
                . '<li>The line <b>ECL by stage, or the full loan book with PD, LGD and ECL, for one month, as CSV data.</b></li>'
                . '<li><b>Portfolio</b> (<b>Select a portfolio</b>; active portfolios only; choosing one reloads the month list), <b>Reporting month</b> (<b>Choose a portfolio first</b>, then <b>Select a month</b>; the last twenty-four months of that portfolio) and <b>Content</b> (<b>ECL by stage</b> or <b>Full loan book with PD, LGD and ECL</b>).</li>'
                . '<li>For the full loan book, a <b>Columns</b> panel: <b>Tick the columns to include; leave all unticked for every column.</b> The columns are Contract ID, Carrying Amount, PD, LGD, ECL Value, IFRS9 Stage, Reporting Period, External ID, Create Date, Due Date, Contract Status and Overdue Days.</li>'
                . '<li>The file: <b>ECL Export Report</b> with the portfolio, month, mode and export date, then for ECL by stage <b>Stage</b>, <b>Loans</b>, <b>Gross carrying amount</b>, <b>EAD</b>, <b>PD (EAD-weighted)</b>, <b>LGD (EAD-weighted)</b>, <b>ECL</b> and <b>Coverage of EAD</b>, with a Total row; or one row per loan with the columns ticked. The ECL in this file is the undiscounted engine ECL (EAD x PD x LGD), not the booked allowance; use the hub reports for the booked figure.</li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Exports group.</li>'
                . '<li><b>Download CSV</b> (download arrow): builds and downloads the file. Greyed out until the choices are complete, with the tip <b>Choose the start and end months first</b> (loan book) or <b>Choose the portfolio and month first</b> (ECL).</li>'
                . '<li>The column tick boxes (ECL export, full loan book only).</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>The end month must not be before the start month.</b> (loan book export).</li>'
                . '<li>An ECL file with nothing to export holds <b>No records found for the given criteria.</b></li>'
                . '<li>A failure returns to the page with <b>Failed to generate report:</b> and the reason.</li></ul>'
                . '<h4>Common problems</h4><ul><li><b>The ECL export total differs from ECL Provision on the hub.</b> The export carries the undiscounted ECL; the hub carries the booked (discounted) allowance. See Booked ECL: the basis of the loss allowance.</li></ul>',
            'steps' => [
                'Open Reports, the Exports tab, then Loan book export or ECL export.',
                'Choose the portfolio and the month or months.',
                'Choose the detail: summary or loan by loan; for the ECL full loan book, tick the columns you need.',
                'Press Download CSV at the top right and open the file in Excel.',
            ],
            'images' => [
                'loanbook-export' => 'Loan book export: the portfolio, start and end months, the detail choice and the Download CSV button',
                'ecl-export' => 'ECL export: the portfolio, reporting month and content choices with the column tick boxes for the full loan book',
            ],
            'routes' => ['reports.loan-book-export', 'reports.ecl-export'],
        ],

        'Sensitivity report' => [
            'body' => '<p>The former Sensitivity tile of the hub has been retired: it duplicated the Stress testing engine. Its macro mode, a macro shock run through a regression model to a PD multiplier, now lives in Stress testing under the <b>Macro scenario</b> button. An old link or bookmark to the Sensitivity report opens Stress testing. It needs <b>reports.ifrs9</b>.</p>'
                . '<h4>To run what the Sensitivity report used to show</h4><p>Open Stress testing, press <b>Macro scenario</b>, choose a <b>Regression model</b> (or <b>Manual slope / intercept</b> with a slope and intercept), enter the <b>Base macro value</b> and the <b>Macro shock (%)</b>, and press <b>Run Stress Test</b>. The amber line shows how the shock became the PD multiplier applied to every loan, and the tables show the base against the stressed ECL by stage and by portfolio. See the Stress testing article for every button.</p>',
            'steps' => [
                'Open Reports, the Risk and analytics tab, then Stress testing.',
                'Press Macro scenario.',
                'Choose the regression model or enter a slope and intercept, the base macro value and the shock.',
                'Press Run Stress Test and read the multiplier line and the results.',
            ],
            'images' => [
                'report-sensitivity' => 'The Sensitivity address opens Stress testing, where the macro scenario mode carries the former sensitivity analysis',
            ],
            'routes' => ['ifrs9-reports.sensitivity'],
        ],

        'Auditor pack' => [
            'body' => '<p>The Auditor pack is one zip per period for the external auditors: the compliance workbooks, the EIR book as at the period end, the baselines and the ECL by stage, with a manifest giving the SHA-256 checksum of every file so the auditor can prove nothing changed after it was handed over. Open <b>Reports</b>, the <b>Exports</b> tab, then <b>Auditor pack</b>. The hub shows the tile to holders of <b>eir.export</b>; the page itself opens with <b>eir.view</b> or <b>eir.export</b>, but only <b>eir.export</b> may build or download a pack.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header <b>Auditor pack</b> with the line <b>One zip per period: the compliance workbooks, the EIR book, the baselines and the ECL by stage, with a SHA-256 for every file.</b></li>'
                . '<li>Top right (eir.export only): the period (the last thirty-six months with a loan book; a month picker when there is none) and the button <b>Build the pack for</b> and the period.</li>'
                . '<li>KPI tiles: <b>Packs on file</b>, <b>Latest period packed</b> and <b>Last build from this screen</b> (the period and time, or <b>None</b>).</li>'
                . '<li><b>What the last build said</b>, a fold-out with the output of the last build.</li>'
                . '<li><b>The packs</b>: <b>Period</b>, <b>File</b> (for example MAIIC auditor pack 2025-12.zip), <b>Built</b> (with a red <b>no manifest</b> badge when the zip has none), <b>Files in manifest</b>, <b>With SHA-256</b>, <b>Size</b>, <b>Contents</b> (the file names) and, for eir.export, a download icon.</li></ul>'
                . '<h4>What a pack holds</h4><ul>'
                . '<li>The compliance workbooks and documents of the compliance register.</li>'
                . '<li><b>Baselines</b> for the period: the baseline checks as read at the build.</li>'
                . '<li><b>EIR as at</b> the period end: contract, customer, product, GL, EIR interest year to date, contractual year to date, difference, amortised cost and gross (or a note saying why it is not available).</li>'
                . '<li><b>ECL by stage</b> for the period: loans, carrying amount and ECL by stage. This ECL is the undiscounted engine ECL; give the auditor the IFRS 9 note for the booked allowance.</li>'
                . '<li><b>manifest.json</b>: the pack name, when it was built, and each file with its size and SHA-256.</li></ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Back to reports</b>: returns to the Exports group.</li>'
                . '<li><b>Build the pack for</b> the period (<b>Building...</b> while it works): builds the pack. A pack is never edited: building again replaces the zip for that period whole, and its manifest says when.</li>'
                . '<li>The download icon on a row: downloads that zip.</li></ul>'
                . '<h4>What happens next</h4><p>Each build is written to the audit trail as <b>Auditor\'s Pack Built</b> with its output, and each download as <b>Auditor\'s Pack Downloaded</b> with the file\'s SHA-256, so the trail shows which pack went to whom.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No pack has been built: choose a period and build one.</b></li>'
                . '<li>After a build, a green message with the output, for example the pack written and the number of files; or a red <b>The pack could not be built:</b> or <b>The pack could not be built for</b> the period, with the reason.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>No build button.</b> You do not hold eir.export.</li>'
                . '<li><b>EIR as at - not available.txt in the pack.</b> The EIR book could not be produced for that month end; the file gives the reason. Run the EIR revenue for the month and build again.</li></ul>',
            'steps' => [
                'Open Reports, the Exports tab, then Auditor pack.',
                'Choose the period at the top right.',
                'Press Build the pack for the period and wait for the message.',
                'Check the new row: the files in the manifest and the SHA-256 count agree.',
                'Press the download icon and hand the zip to the auditor.',
            ],
            'images' => [
                'auditor-pack' => 'The Auditor pack: the period and Build button, the tiles and the packs on file with their manifest counts and download icons',
            ],
            'routes' => ['auditor-pack.index', 'auditor-pack.build', 'auditor-pack.download'],
        ],

    ],

];
