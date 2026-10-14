<?php

/*
|--------------------------------------------------------------------------
| User Manual: Financial Modelling (second half), Forward-Looking Information
|--------------------------------------------------------------------------
| The forward-looking module as rebuilt on 13 October 2026 (FLI module
| specification v1) and documented screen by screen on 14 October 2026
| (branch head 467ef7f): the month-end Overview and Impact Analysis, the
| five Macro Statistics tabs, the Forward-Looking Model tabs, and the
| Scenarios & Overlays tabs. The article titles of the earlier chapter are
| kept so the seeder refreshes them in place (HELP_SEED_REFRESH=1); the
| first half of the chapter (staging, PD and LGD) lives in a sibling file.
*/

return [
    'Financial Modelling' => [

        'Forward-looking information: how it works' => [
            'body' => '<p>IFRS 9 asks for an expected credit loss (ECL) that looks ahead: a probability-weighted amount over a range of economic outcomes, using information that was reasonably available at the reporting date. In this system that is the <b>Forward-Looking Information</b> entry in the <b>Financial modelling</b> block of the sidebar. Risk, finance and the model reviewer use it every month-end; the CFO or ALCO sets the scenario weights each quarter. The entry has four sections, each a row of tabs, in the order a month-end uses them.</p>'
                . '<ol>'
                . '<li><b>FLI Month-End</b>: the <b>Overview</b> walks a month through its ten steps and runs the automated ones; <b>Impact Analysis</b> shows what the forward-looking information did to the ECL.</li>'
                . '<li><b>Macro Statistics</b>: <b>Dashboard</b>, <b>Variables</b>, <b>Data Entry</b>, <b>Scenario Assumptions</b> (the weight of each scenario and the shocks that move each variable) and <b>Import / Export</b>.</li>'
                . '<li><b>Forward-Looking Model</b>: <b>Credit Loss Data</b> (the history the model is fitted on), <b>Correlation Finder</b> and <b>Regression</b> (which fit the model), <b>Model Versions</b> (the version in force for each month), <b>Adjusted Forecast</b> (the PD year by year under each scenario) and <b>Weighted Forecast</b>.</li>'
                . '<li><b>Scenarios &amp; Overlays</b>: <b>Scenario Profiles</b> (templates), <b>Scenario Sets</b> (propose, approve, lock), <b>Economic Scenarios</b> (the workbench for reading a set), <b>Manual Overlays</b>, <b>External Calculations</b> and <b>Calculation History</b>.</li>'
                . '</ol>'
                . '<h4>How the figure is made</h4>'
                . '<p>The model links one economic variable (the driver) to one credit-loss measure, for example the prime lending rate nine months earlier to the share of accounts in Stage 3. For each scenario of the approved scenario set, the model predicts the measure from that scenario\'s path and compares it with the prediction from today\'s value; that ratio, less one, is the adjustment that moves each loan\'s PD. The ECL is measured under each scenario and weighted by the scenario weights, and the PD the system books on each loan is the one whose ECL equals that weighted ECL. Stage 3 loans are measured on the default that has happened, so no scenario moves them. The Governance Centre settings <b>How the forward-looking adjustment is produced</b>, <b>How the adjustment reaches the PD</b> and <b>How scenarios are weighted</b> decide the route, the method and the weighting; the method cards on the Regression tab explain each one.</p>'
                . '<h4>What changes when</h4>'
                . '<ul>'
                . '<li><b>Every month-end</b>: the model version in force is applied to the month; nothing is re-fitted. The month is recorded and a second person signs it off.</li>'
                . '<li><b>Every quarter</b>, or when the outlook moves: a new scenario set (or a new version of the month\'s set), proposed by one person and approved by another.</li>'
                . '<li><b>Once a year</b>, or after an event that breaks the relationship (a devaluation, a drought): the model is recalibrated and approved as a new version. The Governance Centre setting <b>How often the forward-looking model is recalibrated</b> can instead ask for a version every month.</li>'
                . '</ul>'
                . '<h4>Who may do what</h4>'
                . '<ul>'
                . '<li>Every signed-in user can open and read the Overview, Impact Analysis, Model Versions, Regression, Correlation Finder, Adjusted Forecast, Weighted Forecast, the scenario screens and Manual Overlays.</li>'
                . '<li><b>Macro Statistics</b> opens with <b>macro.view</b> (or <b>eir.view</b>); adding, correcting, importing and editing the assumptions needs <b>macro.manage</b> (or <b>eir.govern</b>).</li>'
                . '<li><b>External Calculations</b> and <b>Calculation History</b> open with <b>reports.ifrs9</b>.</li>'
                . '<li>Running a step, proposing, approving, rejecting, locking, retiring and signing off need <b>eir.govern</b>; running the Correlation Finder from its own screen needs <b>eir.run</b>.</li>'
                . '<li>Maker-checker everywhere: the person who proposed a fit, a scenario set, an overlay or an external model cannot approve it, and the person who recorded a month cannot sign it off.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The Overview says no model version is in force.</b> What happens next is the Governance Centre setting <b>A month-end with no forward-looking model version in force</b>. Under <b>Proceed</b> (the seeded default) every step still runs: the PD holds with no forward-looking adjustment, an amber banner says so, the projection is skipped, the record reads <b>No forward-looking model in force</b>, and signing off needs a commentary saying so. Under <b>Refuse</b> the month-end stops at step 4 with the reason. To bring a model in, run the correlation sweep, propose an applied fit on Regression and have a second person approve it; approval publishes it as the version in force from that month.</li>'
                . '<li><b>A red banner says the month is mid-run.</b> Staging, the PD or LGD run or the route ran again after the ECL or the record, usually because a month-end stopped part-way. The ECL and the record on screen are not current, and the record cannot be signed off: run the remaining steps (<b>Run the remaining steps</b>, or the Maintenance month-end).</li>'
                . '<li><b>The ECL step is waiting.</b> A new PD run clears the post-FLI PD; apply the route again (Overview, step 7), then calculate the ECL.</li>'
                . '<li><b>The weighted ECL does not tie to the booked ECL.</b> The ECL was calculated before the route last ran: calculate it again, then record the month again.</li>'
                . '</ul>',
            'steps' => [
                'Load or refresh the economic data on Macro Statistics.',
                'Make sure a model version is in force (Model Versions) and a scenario set is approved for the month (Scenario Sets).',
                'Open FLI Month-End, Overview, choose the month and press Run the remaining steps.',
                'Read Impact Analysis.',
                'Have a second person sign the month off on the Overview.',
            ],
            'images' => [],
            'routes' => ['fli.transmission-methods'],
        ],

        'The forward-looking month-end' => [
            'body' => '<p>The <b>Forward-Looking Overview</b> takes one month through its forward-looking close: what each step needs, where it stands, and the button that runs it. It is where the month-end starts and where a second person signs the month off. To reach it, expand <b>Forward-Looking Information</b> in the sidebar, choose <b>FLI Month-End</b> and the <b>Overview</b> tab. Anyone signed in can read it; the Run buttons, approvals, commentary and sign-off need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Month</b> selector (top right): the months the system holds a loan book for, newest first. The page opens on the latest month whose loans carry a PD before FLI.</li>'
                . '<li>Five tiles: <b>Steps done</b> (for example 7 of 10, with a progress bar); <b>Model in force</b> (the version, hover for the full line: driver, measure, lag, R-squared, in force since); <b>Scenario set</b> (<b>Approved</b> or <b>Not approved</b>, hover for the set and its weights); <b>ECL booked, discounted</b> (or <b>ECL booked</b> when the month has no discounted figure), with the forward-looking effect beneath it on the undiscounted measure and the number of loans not discounted, or <b>month not recorded</b>; and <b>Waiting for approval</b> (red when anything waits).</li>'
                . '<li>A banner with the next step, <b>Next: ...</b> and what it needs, or <b>Every step of ... is done.</b></li>'
                . '<li><b>The month-end, in order</b>: the ten steps. Each row shows a numbered circle (a tick when done), the step, a badge and a one-line status with the figures behind it. Badges: <b>Done</b>, <b>To do</b>, <b>Waiting on an earlier step</b>, <b>Done, with a warning</b> and <b>Optional</b>. The steps are: 1 <b>Loan book staged, PD before FLI</b>; 2 <b>Economic data as at the month-end</b> (warns when no forecast is knowable or a series is more than 15 months old); 3 <b>Credit-loss history</b>; 4 <b>Model version in force</b> (warns when the version is twelve months old); 5 <b>Scenario set approved</b>; 6 <b>Overlays reviewed</b>; 7 <b>Forward-looking route applied</b>; 8 <b>Scenario projection (term structure)</b>, evidence only, so it never holds up the close; 9 <b>ECL calculated on the post-FLI PD</b>; 10 <b>Month recorded and signed off</b>.</li>'
                . '<li><b>Waiting for a second person</b>: what is proposed for the month and waits for approval: fits, scenario sets, overlays and external models. Columns <b>What</b> (with a note, for example that approving a fit publishes it as the model version in force) and <b>Proposed by</b>. Your own proposals show <b>your proposal</b> instead of a button.</li>'
                . '<li><b>The month\'s record</b> (once step 10 has recorded the month), with a <b>Recorded</b> or <b>Signed off</b> badge and who recorded and signed it. The left table is the build-up on the engine measure: <b>ECL before FLI</b>, <b>Forward-looking effect of the model</b>, <b>Manual overlays</b>, <b>ECL, engine measure (undiscounted)</b>, <b>Weighted over the scenarios</b> and <b>Difference</b> with <b>(ties)</b> in green or <b>(does not tie)</b> in red. Below it, under <b>Discounted at the EIR (IFRS 9 5.5.17)</b>: <b>Less: loans not discounted</b> (when any), <b>Projection effect (EAD run down the schedule)</b>, <b>Discounting effect</b>, <b>ECL booked, discounted</b> with the number of loans, <b>Weighted over the scenarios, discounted</b> and its own <b>Difference</b>. Then the <b>Mega Farm programme, its own engine (D30), not in the figures above</b> line when the month has programme loans, and <b>Against the previous month-end</b> (the movement, red when it needs a commentary).</li>'
                . '<li>The right table: one row per scenario with its <b>Weight</b>, <b>ECL</b> and, when the month is discounted, <b>Discounted</b>. An amber box lists the loans that have no discounted allowance (up to eight, with the reason, usually no locked original EIR).</li>'
                . '<li><b>Recent runs</b>: the steps run from here and the forward-looking jobs run from Maintenance: <b>Started</b>, <b>Step</b>, <b>Run by</b>, <b>Took</b>, <b>Status</b> (done, running or failed) and <b>Output</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Run the remaining steps</b> (top right, <b>eir.govern</b>): applies the route, projects the scenarios, calculates the ECL and records the month, in order, and stops at the first step that needs a person (an import, an approval). A confirmation names the month first.</li>'
                . '<li><b>Open</b> on a step: goes to the screen that does it (Maintenance, Macro Statistics, Credit Loss Data, Model Versions, Scenario Sets, Manual Overlays, External Calculations, Adjusted Forecast or ECL Calculation) for the same month.</li>'
                . '<li><b>Run</b> or <b>Run again</b> on steps 3, 7, 8, 9 and 10 (<b>eir.govern</b>; hidden while the step is waiting on an earlier one): <b>Refresh the data and fit the candidates</b>, <b>Apply the forward-looking route</b>, <b>Project the scenarios year by year</b>, <b>Calculate the ECL on the post-FLI PD</b> and <b>Record the month</b>. Each runs in the background after a confirmation; only one runs at a time, and the buttons are disabled while one runs.</li>'
                . '<li>Output panel: opens on the right while a step runs, with the step, the command, a status badge and the time it took; the cross icon (<b>Close</b>) closes it. The page reloads when the step ends.</li>'
                . '<li>Tick icon in <b>Waiting for a second person</b> (<b>eir.govern</b>): approves the item after a confirmation. The proposer cannot approve; the system refuses with a maker-checker message.</li>'
                . '<li><b>Impact Analysis</b> (on the record): opens Impact Analysis for the month.</li>'
                . '<li><b>Commentary</b> box, <b>Save commentary</b> and <b>Sign off the month</b> (shown while the record is Recorded, <b>eir.govern</b>). Save commentary keeps the text without signing off. Sign off the month is disabled for the person who recorded the month (hover: <b>A second person signs off the month</b>), when the undiscounted figures do not tie, and when the discounted figures do not tie. The label says when a commentary is required: <b>(required: the movement is above 10%)</b> (the Governance Centre tolerance) or <b>(required: loans are not discounted)</b>.</li>'
                . '<li>Eye icon (<b>View the output</b>) in Recent runs: shows the output of that run in the panel.</li>'
                . '</ul>'
                . '<h4>To close a month</h4>'
                . '<ol>'
                . '<li>Choose the month in the selector.</li>'
                . '<li>Read the first step that is not done; press <b>Open</b> for a step that needs a person (load the economic data, approve the scenario set or the fit).</li>'
                . '<li>A second person approves what is in <b>Waiting for a second person</b> with the tick icon.</li>'
                . '<li>Press <b>Run the remaining steps</b> and confirm. Watch the output on the right.</li>'
                . '<li>Read the record: both <b>Difference</b> lines should say <b>(ties)</b>. Open <b>Impact Analysis</b> to read the effect.</li>'
                . '<li>The reviewer (not the person who recorded the month) writes the commentary when one is required, presses <b>Save commentary</b>, then <b>Sign off the month</b> and confirms.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Recording writes one month record with the ECL of every loan under every scenario, the inputs in force (model version, scenario set and its weights, overlays, PD and ECL runs, the discounting basis) and the movement against the previous recorded month. Recording again replaces the earlier record, which stays in Calculation History marked superseded. Sign-off marks the record <b>Signed off</b> in your name and is written to the audit log; it completes the forward-looking step of the Workspace close checklist and is what the forward-looking and scenario-weighted ECL reports show as the month\'s status.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>Nothing is waiting for approval this month.</b> and <b>No step has been run from here yet.</b> when those tables are empty. The record panel does not show until the month is recorded.</li>'
                . '<li>Success: <b>The month is signed off.</b> and <b>Commentary saved.</b></li>'
                . '<li>A run refused: <b>Choose a month the system holds a loan book for.</b>, <b>"..." is still running. Wait for it to finish.</b>, or <b>Refused: running a step needs the governance permission.</b></li>'
                . '<li>Recording refused (shown in the output): the ECL is not on the PD after FLI; the ECL was calculated before the route last ran (<b>Calculate the ECL again, then close the month.</b>); the loans ran under more than one scenario set; no scenario set was applied; every loan is in the Mega Farm programme.</li>'
                . '<li>Sign-off refused: <b>only a recorded month can be signed off</b>; the weighted ECL differs from the booked ECL; the discounted allowance weighted over the scenarios differs from the one booked (<b>run the ECL again, record the month again, then sign it off</b>); the ECL moved more than the governed tolerance (<b>say why before signing off</b>); loans have no discounted allowance (<b>say why the month is signed off before they are resolved</b>); <b>Maker-checker: the person who recorded the month cannot sign it off.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>Run is missing on a step.</b> You do not hold <b>eir.govern</b>, or the step is waiting on an earlier one.</li>'
                . '<li><b>Step 9 went back to To do.</b> The route ran after the last ECL calculation; run step 9 again, then step 10.</li>'
                . '<li><b>Sign off the month is greyed out.</b> You recorded the month, or a Difference says does not tie: run the ECL and record the month again.</li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Information, FLI Month-End, Overview, and choose the month.',
                'Use Open on any step that needs a person, and have a second person approve what is waiting.',
                'Press Run the remaining steps and confirm.',
                'Check that both Difference lines of the month\'s record say ties.',
                'Read Impact Analysis.',
                'A second person writes the commentary if asked and presses Sign off the month.',
            ],
            'images' => ['fli-overview' => 'The Forward-Looking Overview: the five tiles, the next step, the ten steps with Open and Run, Waiting for a second person, and the month\'s record with its sign-off'],
            'routes' => ['fli.overview', 'fli.run', 'fli.run.output', 'fli.sign-off', 'fli.comment'],
        ],

        'Impact analysis' => [
            'body' => '<p><b>Impact Analysis</b> shows what the forward-looking information did to the ECL of a recorded month: each scenario, the weighted result, by stage and by portfolio, against the previous month-end, the loans it moved most, and a scenario set measured before anyone approves it. Reviewers and approvers read it before approving a set or signing off a month. To reach it, expand <b>Forward-Looking Information</b>, choose <b>FLI Month-End</b> and the <b>Impact Analysis</b> tab, or press <b>Impact Analysis</b> on the Overview\'s record. Any signed-in user can read it; it has no buttons that change anything.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Month</b> selector (top right).</li>'
                . '<li>Five tiles: <b>ECL before FLI</b>; <b>Forward-looking effect</b> (the model effect plus the overlays, green when it lowers the ECL and red when it raises it, with its share of the ECL before FLI); <b>ECL booked</b>; <b>Weighted over the scenarios</b> (<b>ties to the booked ECL</b> or <b>does not tie</b>); <b>Status</b> (<b>Recorded</b> or <b>Signed off</b>). Before the month is recorded only a <b>Month</b> tile shows.</li>'
                . '<li><b>ECL under each scenario</b>: a bar per scenario with its weight and ECL (the base in green, the others in gold) and a blue <b>Weighted</b> bar. The weighted figure is the ECL booked; any difference is the rounding of the stored PDs.</li>'
                . '<li><b>Against</b> the previous recorded month: columns for that month, this month and <b>Movement</b>; rows <b>ECL before FLI</b>, <b>Forward-looking effect</b>, <b>Manual overlays</b> and <b>ECL booked</b>.</li>'
                . '<li><b>By stage</b> and <b>By portfolio</b>: the ECL under each scenario and the <b>Booked</b> figure. Stage 3 is the same in every scenario.</li>'
                . '<li><b>A scenario set measured before it is approved</b>: choose any set of the month in its selector (a proposed set is chosen first). Columns <b>Scenario</b>, <b>Weight</b>, <b>Driver</b> (the driver value of the scenario), <b>Adjustment to the PD</b>, <b>ECL</b> and <b>Weighted part</b>; then <b>Weighted ECL under</b> the set, <b>ECL booked now</b> and <b>Change if this set were applied</b> (amber when it is more than MWK 1). A note under it says the measure uses the loans whose booked ECL ties to their saved PD, LGD and EAD, without the overlays, and names the model version and the number of loans. Nothing is written.</li>'
                . '<li><b>The loans the forward-looking information moved most</b>: up to 300 loans, largest effect first, 15 to a page: <b>Contract</b>, <b>Customer</b>, <b>Portfolio</b>, <b>Stage</b>, <b>PD before</b>, <b>PD after</b>, <b>ECL before FLI</b>, <b>ECL booked</b> and <b>Effect</b> (green when the loan\'s ECL went down, red when it went up). The pager at the bottom shows <b>Showing ... to ... of ...</b>.</li>'
                . '</ul>'
                . '<p>All figures on this page are on the engine measure (undiscounted). The discounted allowance, weighted per scenario and reconciled, is on the Overview\'s record.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Month</b> and the scenario-set selector reload the page; there is nothing to approve here.</li>'
                . '<li><b>Open the Overview</b> (in the empty state) goes to the Overview for the month.</li>'
                . '<li>Page numbers under the loan table move through the loans.</li>'
                . '</ul>'
                . '<h4>To check a proposed scenario set before approving it</h4>'
                . '<ol>'
                . '<li>Choose the month.</li>'
                . '<li>In <b>A scenario set measured before it is approved</b>, choose the proposed set.</li>'
                . '<li>Read the ECL of each scenario and <b>Change if this set were applied</b>.</li>'
                . '<li>Approve the set on Scenario Sets (or on the Overview), or ask the proposer for a change.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>... has not been recorded yet. Record it on the Overview (step 10) to see the ECL under each scenario.</b> with the link <b>Open the Overview</b>.</li>'
                . '<li><b>No earlier month is recorded, so there is no movement to show yet.</b></li>'
                . '<li><b>No scenario set for ...</b> under the what-if panel; in red, for example <b>No model version is in force for ..., so no scenario can be measured.</b></li>'
                . '<li><b>No loan has an ECL before and after FLI for this month.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul><li><b>The page is almost empty.</b> The month is not recorded; record it on the Overview first.</li><li><b>The what-if figure differs slightly from the booked one under the same set.</b> The what-if leaves out the overlays and any loan whose ECL does not tie to its parameters.</li></ul>',
            'steps' => [
                'Open Forward-Looking Information, FLI Month-End, Impact Analysis.',
                'Choose the month.',
                'Read the four tiles and ECL under each scenario.',
                'Compare with the previous month and read By stage and By portfolio.',
                'Choose a proposed set in the what-if panel and read Change if this set were applied.',
                'Read the loans moved most for anything unexpected.',
            ],
            'images' => ['fli-impact' => 'Impact Analysis: the tiles, ECL under each scenario, the movement against the previous month, by stage and portfolio, and the what-if of a proposed set'],
            'routes' => ['fli.impact'],
        ],

        'Macro elements' => [
            'body' => '<p><b>Macro Statistics</b> holds the economic variables the forward-looking model reads, their values with where each came from, and the scenario assumptions built on them. This article covers the <b>Dashboard</b> and <b>Variables</b> tabs; Data Entry, Scenario Assumptions and Import / Export have their own articles. To reach it, expand <b>Forward-Looking Information</b>, choose <b>Macro Statistics</b> and a tab. Each tab in the row shows a count (variables, values of the chosen variable, sets, batches). The screen opens with <b>macro.view</b> (or <b>eir.view</b>); adding, editing and deleting need <b>macro.manage</b> (or <b>eir.govern</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Dashboard</b>: one card per variable: its code and unit, its name, how many values it holds and from which month to which (with the number of forecasts), and <b>last from</b> the source and date of the latest load, or <b>no values yet</b> in amber. Clicking a card opens Data Entry on that variable.</li>'
                . '<li><b>Variables</b>: the list, 15 to a page: <b>Code</b>, <b>Variable</b>, <b>Unit</b>, <b>Frequency</b>, <b>Source</b>, <b>Source codes</b> (<b>WB:</b> the World Bank code, <b>IMF:</b> the WEO code, <b>RBM:</b> or <b>E-Banker:</b> the other code), <b>Values</b> and <b>Why MAIIC needs it</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Add variable</b> (top right on Variables, <b>macro.manage</b>): opens <b>Add a variable</b> with <b>Code</b> (capitals, digits and underscores, starting with a letter, for example MAIZE_PRICE), <b>Name</b>, <b>Unit</b>, <b>Frequency</b> (Monthly, Quarterly or Yearly), <b>Source</b>, <b>Link</b>, <b>World Bank code</b>, <b>IMF WEO code</b>, <b>RBM or other code</b> and <b>Why MAIIC needs it</b>; then <b>Cancel</b> or <b>Add the variable</b>.</li>'
                . '<li><b>Import</b> and <b>Export CSV</b> (top right on every tab): see the article on import and export. Import needs <b>macro.manage</b>; anyone who can open the screen can export.</li>'
                . '<li>Eye icon (<b>See its values</b>): opens Data Entry on the variable.</li>'
                . '<li>Pencil icon (<b>Edit the variable</b>): the same window titled <b>Edit</b> and the code, with <b>Save</b>. The code cannot change once a model version or a scenario set uses it.</li>'
                . '<li>Bin icon (<b>Delete the variable</b>): asks <b>Delete ...?</b>. The system refuses while the variable holds values or a model version or a scenario set uses it, and says which.</li>'
                . '<li>The three row icons show only to users with <b>macro.manage</b> or <b>eir.govern</b>.</li>'
                . '</ul>'
                . '<h4>To add a new economic variable</h4>'
                . '<ol>'
                . '<li>Open <b>Variables</b> and press <b>Add variable</b>.</li>'
                . '<li>Type the code and the name, the unit and the frequency.</li>'
                . '<li>Give the source and, if it is fetched or imported, its World Bank, IMF or RBM code.</li>'
                . '<li>Say why MAIIC needs it and press <b>Add the variable</b>.</li>'
                . '<li>Load its values on Data Entry or Import / Export.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Each change is written to the audit log. A new variable is read by the correlation sweep once it has values, and can then be shocked in a scenario set.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No variable is set up yet. Open Variables and use Add variable.</b> (Dashboard) and <b>No variable yet. Use Add variable at the top right.</b> (Variables).</li>'
                . '<li><b>Variable ... added. Enter its values on Data Entry, or import them.</b>, <b>Variable ... updated.</b>, <b>Variable ... deleted.</b></li>'
                . '<li>Refused: <b>The code is capital letters, digits and underscores, starting with a letter (for example MAIZE_PRICE).</b>; <b>... holds ... observations: delete them on Data Entry first, or keep the variable.</b>; <b>... cannot be deleted: a forward-looking model version uses it as its driver.</b> (or <b>a scenario set shocks it</b>); <b>The code of ... cannot change: ... Change its name or description instead.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Information, Macro Statistics, Dashboard, to see every variable and its latest load.',
                'Open Variables and press Add variable for a new series.',
                'Fill in the code, name, unit, frequency, source and source codes, and press Add the variable.',
                'Use the pencil to change a name or description; the bin only removes a variable nothing uses.',
                'Load the values on Data Entry or Import / Export.',
            ],
            'images' => [
                'macro-dashboard' => 'Macro Statistics, Dashboard: one card per variable with its values and latest load',
                'macro-variables' => 'Macro Statistics, Variables, with Add variable at the top right and the row icons',
            ],
            'routes' => ['macro-statistics.index', 'macro-statistics.variables.store', 'macro-statistics.variables.update', 'macro-statistics.variables.destroy'],
        ],

        'Macro statistics: data entry' => [
            'body' => '<p>The <b>Data Entry</b> tab of Macro Statistics lists every value of one variable and is where a single value is added or corrected, always with a reason. Open <b>Forward-Looking Information</b>, <b>Macro Statistics</b>, <b>Data Entry</b>, or click a card on the Dashboard. Reading needs <b>macro.view</b>; adding, correcting and deleting need <b>macro.manage</b> (or <b>eir.govern</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Variable</b> selector (code and name) with the frequency and unit, and the reminder that a value typed or corrected needs a reason and the old value is kept in the audit log.</li>'
                . '<li>The values, newest first, 15 to a page: <b>Period</b>, <b>Value</b>, <b>Actual or forecast</b> (<b>Actual</b> green or <b>Forecast</b> gold), <b>Published</b> (the date the publisher released it), <b>Source</b> (hover for the notes, including any correction reason) and <b>Batch</b> (the load it came in and its date).</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Add value</b> (top right, <b>macro.manage</b>): opens <b>Add a value to</b> the variable: <b>Period</b> (a date; for a yearly value, the year\'s last day), <b>Value</b>, <b>This is a forecast</b>, <b>Published on</b> and <b>Reason</b> (required, for example the RBM report it comes from); then <b>Cancel</b> or <b>Save</b>.</li>'
                . '<li>Pencil icon (<b>Correct this value</b>): the same window titled <b>Correct the value of</b> the period, with the period locked. Change the value, the forecast tick or the release date and give the reason.</li>'
                . '<li>Bin icon (<b>Delete this value</b>): asks <b>Delete this value?</b> and <b>Why it is deleted</b>; the old value stays in the audit log.</li>'
                . '</ul>'
                . '<h4>Why the release date matters</h4>'
                . '<p>A value is read for a month only if it was knowable by the end of that month: an actual dated on or before it, a forecast published on or before it. Where <b>Published</b> is blank the governed publication lag of the series is used. A correction replaces the stored value; the earlier value is kept only in the audit log, so give the release date of the new figure.</p>'
                . '<h4>To add or correct one value</h4>'
                . '<ol>'
                . '<li>Choose the variable.</li>'
                . '<li>Press <b>Add value</b>, or the pencil on the row to correct.</li>'
                . '<li>Enter the period, the value, whether it is a forecast and the date it was published.</li>'
                . '<li>Type the reason and press <b>Save</b>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>An added value is recorded as a manual batch whose source reads <b>Manual entry:</b> and your reason; it appears in the import history. The model reads new and corrected values at the next correlation sweep.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No values for this variable yet. Use Add value, or import a file on Import / Export.</b></li>'
                . '<li><b>Manual entry recorded as batch ... with its reason.</b>; <b>Observation corrected; the old value is kept in the audit log. The model reads it at the next correlation sweep.</b>; <b>Observation deleted; the reason and the old value are in the audit log.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Macro Statistics, Data Entry, and choose the variable.',
                'Press Add value, or the pencil on a row to correct it.',
                'Enter the period, the value, the forecast tick and the published date.',
                'Type the reason and press Save.',
                'Delete a wrong row with the bin and a reason; never delete and retype a correction.',
            ],
            'images' => ['macro-entry' => 'Macro Statistics, Data Entry: the values of one variable with the correct and delete icons'],
            'routes' => ['macro-statistics.manual', 'macro-statistics.observations.update', 'macro-statistics.observations.destroy'],
        ],

        'Scenario assumptions: weights and shocks' => [
            'body' => '<p>The <b>Scenario Assumptions</b> tab of Macro Statistics is where the weights of a month\'s scenario set and the shocks of each scenario are set. Open <b>Forward-Looking Information</b>, <b>Macro Statistics</b>, <b>Scenario Assumptions</b>. Reading needs <b>macro.view</b>; saving weights and shocks and starting a new version need <b>macro.manage</b> (or <b>eir.govern</b>). A set is proposed and approved on Scenario Sets, not here.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Month</b> and <b>Scenario set</b> selectors (each set as name, version and status) and a status badge: <b>draft</b> or <b>proposed</b> (gold, editable) or <b>approved</b>, <b>locked</b> or <b>superseded</b> (green, read-only). The page opens on the latest loan-book month and on its draft or proposed set first.</li>'
                . '<li><b>Scenario weights</b>: one box per scenario (a read-only figure on an approved set) and the <b>Total</b>, green at 100 and red otherwise.</li>'
                . '<li><b>... at a glance</b> (the first forecast year): each variable a scenario moves, its <b>Base</b> value and its value in each other scenario, with the weight beside the scenario\'s name. Values a scenario moves are in bold.</li>'
                . '<li>One tab per scenario with its weight: the weight and what the scenario is anchored to, then its shocks: <b>Variable</b>, <b>Year</b> (the forecast year and the two after it), <b>How it moves</b> (<b>add points</b>, <b>percent change</b> or <b>replace with</b>), <b>By</b>, <b>Base</b> (hover for where the base value comes from), <b>Becomes</b> and <b>Why</b> (optional).</li>'
                . '</ul>'
                . '<p>The base path is each variable\'s own values as known at the month-end. Where no forecast is knowable the last actual is carried forward.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Discard this draft</b> (bin icon, on a draft that was never proposed): asks for confirmation and removes the draft, its scenarios and their shocks; the audit log keeps what was there. The server needs <b>eir.govern</b> for this.</li>'
                . '<li><b>Propose or approve</b>: opens Scenario Sets for the month.</li>'
                . '<li><b>Save weights</b> (draft or proposed set): saves every weight at once and says whether the set can be proposed yet.</li>'
                . '<li><b>New version to change it</b> (approved or locked set): asks <b>Change an approved set?</b> and <b>Why it changes</b>, then <b>Create the draft</b> makes a draft copy as a new version. The approved set stays in force until the new one is approved.</li>'
                . '<li><b>Add a shock</b> and <b>Save</b> with the scenario\'s name (on an editable set): add a row and save that scenario\'s weight and shocks. The bin icon on a row (<b>Remove this shock</b>) removes it before you save.</li>'
                . '</ul>'
                . '<h4>Who may change a set</h4>'
                . '<p>A draft can be changed by anyone with the permission; a proposed set only by the person who proposed it. An approved, locked or superseded set cannot be changed: make a new version. A proposed set must still pass its rules after a change, or the change is refused and nothing is written.</p>'
                . '<h4>To set the weights and shocks of a month</h4>'
                . '<ol>'
                . '<li>Choose the month and the set.</li>'
                . '<li>Type the weights so that the total is 100 and press <b>Save weights</b>.</li>'
                . '<li>Open each scenario tab, use <b>Add a shock</b>, choose the variable, the year, how it moves and by how much, and press <b>Save</b>.</li>'
                . '<li>Read the at-a-glance table.</li>'
                . '<li>Press <b>Propose or approve</b> and propose the set; check it on Impact Analysis before a second person approves it.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No scenario set for this month. Start one from a profile on Scenario Profiles, then set its assumptions here.</b></li>'
                . '<li><b>No scenario moves any variable yet: every scenario follows the base.</b>; <b>The base follows the values of each variable as known at the month-end; it needs no shock.</b>; <b>No shock: this scenario follows the base path.</b></li>'
                . '<li><b>Weights saved.</b> or <b>Assumptions saved.</b>, followed by <b>The set cannot be proposed yet:</b> and the rules it breaks (for example weights that do not sum to 100).</li>'
                . '<li><b>Draft version ... created from the approved set: change its weights and shocks, then propose it on Scenario Sets.</b>; refused: <b>Version ... of ... is already open (...): change that one, or discard it first.</b>, <b>Only the proposer may change a proposed set</b>, or <b>... cannot be changed; a change after approval is a new version with a reason.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Macro Statistics, Scenario Assumptions, and choose the month and the set.',
                'Set the weights so the total is 100 and press Save weights.',
                'On each scenario tab add or change its shocks and press Save.',
                'For an approved set press New version to change it, with the reason.',
                'Press Propose or approve and propose the set on Scenario Sets.',
            ],
            'images' => ['macro-scenarios' => 'Macro Statistics, Scenario Assumptions: the weights, the year at a glance and one scenario\'s shocks'],
            'routes' => ['macro-statistics.assumptions.save', 'macro-statistics.assumptions.weights', 'macro-statistics.assumptions.version', 'scenario-sets.discard'],
        ],

        'Macro statistics: import and export' => [
            'body' => '<p>The <b>Import / Export</b> tab of Macro Statistics keeps the history of every load, and the <b>Import</b> button opens the window that loads values from a file or a source. Open <b>Forward-Looking Information</b>, <b>Macro Statistics</b>, <b>Import / Export</b>. Previewing and exporting need <b>macro.view</b>; importing (committing) needs <b>macro.manage</b> (or <b>eir.govern</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Import history: committed batches</b>, 15 to a page: <b>Batch</b>, <b>Source</b>, <b>Address</b> (the file name or the web address), <b>Fetched</b>, <b>Committed by</b> (<b>system</b> for an automatic load), <b>Rows</b> and <b>Variables</b>.</li>'
                . '<li>The <b>Import macro data</b> window has four source cards: <b>Your own file (any variable)</b>, <b>World Bank (live)</b> (choose a variable that has a World Bank code, the <b>Country</b>, <b>From</b> and <b>To</b> years; no file needed), <b>IMF World Economic Outlook</b> (a variable with an IMF code and the tab-delimited WEO file) and <b>Reserve Bank policy rate</b> (a CSV of two columns, date and rate).</li>'
                . '<li>Under the cards, a switch between <b>Your own file</b>, <b>IMF WEO file</b> and <b>Reserve Bank file</b> shows that layout in the <b>How to import</b> panel: <b>Show the ... columns (... required)</b> opens the column table (<b>Column</b>, <b>Type</b>, <b>Required</b>, <b>Format and allowed values</b>, <b>Description</b>, <b>Example</b>).</li>'
                . '<li>On the right, <b>How to load macro data</b>: the short rules for each source.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Import</b> (upload icon, top right, <b>macro.manage</b>): opens the window. The cross icon (<b>Close</b>) or a click outside closes it.</li>'
                . '<li><b>Export CSV</b> (download icon): downloads every value of every variable as <b>macro statistics</b> and the date, with the columns series_code, period, value, is_forecast, source and published_at, the same layout <b>Your own file</b> reads, so you can export, edit and import back.</li>'
                . '<li><b>Preview</b> on each card: reads the file or fetches the series and shows the rows; nothing is saved. A file is checked against its column layout first.</li>'
                . '<li><b>Sample CSV</b> or <b>Sample file</b> on each card, and <b>Download sample file</b> and <b>Download column guide</b> in the How to import panel: the sample in exactly the format the import reads (illustrative rows: identifiers start with 8 and names with Sample) and the same column guide as an Excel workbook.</li>'
                . '<li><b>Import ... rows</b> (after a preview of your own file) and <b>Commit ... rows as a batch</b> (after a World Bank, IMF or Reserve Bank preview), <b>macro.manage</b>: save the rows you have seen as one batch per variable.</li>'
                . '</ul>'
                . '<h4>To import your own file</h4>'
                . '<ol>'
                . '<li>Press <b>Export CSV</b> or <b>Sample CSV</b> for the layout: one row per value with series_code, period (yyyy-mm, yyyy-mm-dd or yyyy), value, is_forecast (1 or 0), source and published_at.</li>'
                . '<li>Add the variable on Variables first if it is new.</li>'
                . '<li>Press <b>Import</b>, choose the file on <b>Your own file</b> and press <b>Preview</b>.</li>'
                . '<li>Read the rows and the lines skipped.</li>'
                . '<li>Press <b>Import ... rows</b>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Each commit is a batch with its source, address and person, listed in the history. A value for a period already held is updated in place (the batch counts new, updated and unchanged rows); the release date of each row is kept. The model reads the values at the next correlation sweep.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No batch has been committed yet.</b></li>'
                . '<li>A red box <b>The preview could not be made</b> with the reason, for example <b>The file needs a column named series_code. Download the sample for the layout.</b> or <b>Choose a series.</b>; a file that breaks its layout lists the problems by row and column.</li>'
                . '<li>Lines set aside in your own file: <b>Line ...: ... is not a variable; add it on Variables first.</b> and <b>Line ...: the period or the value cannot be read.</b></li>'
                . '<li>Success: <b>Imported: ... batch ... (... new, ... updated).</b> or <b>Batch ...: ... rows, ... new, ... updated, ... unchanged.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Macro Statistics and press Import at the top right.',
                'Choose the source card: your own file, World Bank, IMF WEO or Reserve Bank.',
                'Choose the variable or the file and press Preview.',
                'Read the rows, then press Import or Commit.',
                'Check the new batch in Import history.',
                'Use Export CSV to take every value out in the same layout.',
            ],
            'images' => ['macro-import' => 'Macro Statistics, Import / Export: the import history and the Import macro data window with its four sources'],
            'routes' => ['macro-statistics.preview', 'macro-statistics.commit', 'macro-statistics.csv-preview', 'macro-statistics.csv-commit', 'macro-statistics.export'],
        ],

        'Credit loss data' => [
            'body' => '<p><b>Credit Loss Data</b> is the credit-loss history the forward-looking model is calibrated on: the measures derived from MAIIC\'s own staged loan books, and external series such as the Reserve Bank\'s industry NPL ratio, which give the model a longer history than MAIIC\'s books alone. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Credit Loss Data</b>. Any signed-in user can open it and, at present, add, edit, import and delete records (the screen has no permission check of its own).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>Four tiles: <b>Months derived from the books</b> (and the latest), <b>External series</b>, <b>Records entered here</b> and <b>Measures defined</b>.</li>'
                . '<li><b>Derived from MAIIC\'s staged loan books</b>, 15 months to a page: <b>Month</b>, <b>NPL ratio (Stage 3 carrying amount)</b>, <b>Stage 3 share of accounts</b> and <b>12-month default rate</b>. They are book level, refreshed by the correlation sweep each month-end and never typed in.</li>'
                . '<li><b>External series the model can use</b> (when any are held): <b>Code</b> (EXT_ and the measure code, with _P and the portfolio number for a portfolio series), <b>Series</b>, <b>Months</b>, <b>From</b>, <b>To</b> and <b>Latest release</b> (or <b>end of each month</b> when no release date was given).</li>'
                . '<li><b>Records entered or imported</b>, 15 to a page: <b>Month</b>, <b>Measure</b> (name and code), <b>Portfolio</b> (or <b>External or industry</b>), <b>Value</b>, <b>Published</b>, <b>Source</b> and <b>Entered by</b>.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<p>Above the records: <b>Month</b>, <b>Portfolio</b> (<b>All</b>, <b>External or industry</b> or one portfolio) and <b>Measure</b>; <b>Filter</b> applies them and <b>Reset</b> clears them.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Measure</b> (top right): opens <b>Create Credit Loss Definition</b>: <b>Metric Code</b>, <b>Metric Name</b> and <b>Description</b>; a click on a tile under <b>Common Credit Loss Definitions</b> fills the form; <b>Cancel</b> or <b>Create Definition</b>.</li>'
                . '<li><b>Import CSV</b> (top right): opens <b>Import credit loss data</b>: the <b>How to import</b> panel with <b>Check file</b> (tests the chosen file without saving it), <b>Download sample file</b> and <b>Download column guide</b>; <b>Portfolio</b> (required); the file box (CSV, TXT or XLSX, up to 2 MB); <b>Columns in your file</b> showing how each column is <b>Read as</b> (or <b>not read</b>) with the first rows; <b>Back to credit loss data</b>, <b>Cancel</b> and <b>Start import</b>.</li>'
                . '<li><b>Add record</b> (top right): opens <b>Add Credit Loss Data</b>: <b>Portfolio</b> (leave it on <b>External or industry series (no MAIIC portfolio)</b> for an industry figure), <b>Reporting Period</b>, <b>Metric Type</b>, the value (with a hint for the measure: PD and LGD are fractions between 0 and 1), <b>Published on</b> (the date the publisher released it), <b>Data Source</b> (Manual Entry, System Generated, External Data, Regulatory Report or Internal Model) and <b>Notes</b>; <b>Cancel</b> or <b>Create Record</b>. A record for the same portfolio, measure and month replaces the one held.</li>'
                . '<li><b>Refresh from the Overview</b>: opens the Overview, where step 3 runs the sweep that refreshes the derived measures.</li>'
                . '<li>Pencil icon (<b>Edit</b>): the same form with <b>Update Record</b>; the value, source, notes and release date can change.</li>'
                . '<li>Bin icon (<b>Delete</b>): asks <b>Delete this record?</b>; the model stops reading it at the next correlation sweep.</li>'
                . '</ul>'
                . '<h4>To add an industry series</h4>'
                . '<ol>'
                . '<li>Press <b>Measure</b> and create the measure if it is new (for example RBM_NPL, Industry NPL ratio).</li>'
                . '<li>Press <b>Add record</b>, leave the portfolio on External or industry series, choose the month and the measure.</li>'
                . '<li>Enter the value and the date it was published, and press <b>Create Record</b>. Repeat for each month.</li>'
                . '<li>Run the correlation sweep (Overview, step 3) so the model can use it.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>At each correlation sweep the records become the external series EXT_ and the measure code; a series is not read for any month before its release date. The derived measures are rebuilt from the staged books at the same time.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>Nothing derived yet: run the correlation sweep (Overview, step 3).</b></li>'
                . '<li><b>No record. Add one, or import a CSV of an external series (a period column, a value column named after the measure, and optionally published_at).</b></li>'
                . '<li>On the import screen: <b>The file has no period column...</b> or <b>None of the columns is a credit loss measure the import reads. Rename them as in the sample CSV.</b>; a file that breaks its layout is refused with the problems by row and column and nothing is imported.</li>'
                . '<li><b>Credit loss data added successfully!</b>, <b>Credit loss data updated successfully!</b>, <b>Credit loss data deleted.</b>, <b>Credit Loss Data imported successfully.</b>, <b>Credit loss definition created successfully!</b>; <b>Import failed:</b> and the reason.</li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Model, Credit Loss Data, and read the derived measures.',
                'Use Measure to define a new external measure if needed (for example RBM_NPL).',
                'Use Add record (portfolio left on External or industry series) with the publication date, or Import CSV for one portfolio.',
                'Filter the records by month, portfolio or measure to check them.',
                'Run the correlation sweep (Overview, step 3) so the model can use them.',
            ],
            'images' => [
                'credit-loss-data' => 'Credit Loss Data: the derived measures, the external series and the records entered',
                'credit-loss-data-create' => 'Add Credit Loss Data, with the portfolio left on External or industry series',
                'credit-loss-data-import' => 'Import credit loss data with the How to import panel and Columns in your file',
            ],
            'routes' => ['credit-loss-data.index', 'credit-loss-data.create', 'credit-loss-data.store', 'credit-loss-data.edit', 'credit-loss-data.update', 'credit-loss-data.destroy', 'credit-loss-data.importView', 'credit-loss-data.import', 'credit-loss-data.definition', 'credit-loss-data.definition.store'],
        ],

        'Correlation finder' => [
            'body' => '<p>The <b>Correlation Finder</b> tests every economic variable against every credit-loss measure, at several lags, using only data known at the month-end, and ranks which driver best explains the losses. It also fits the candidates under the guardrail; a fit that passes is proposed and approved on the Regression tab. Nothing here touches a PD. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Correlation Finder</b>. Any signed-in user can read it; <b>Run the finder</b> needs <b>eir.run</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>Four tiles: <b>Latest sweep</b> (the run number and time, hover for its inputs fingerprint, or <b>None yet</b>), <b>Drivers by loss measures</b> (for example 12 by 6), <b>Suggestions</b> (how many are recommended, with caveat and rejected) and <b>Fits of the period</b> (applied and declined).</li>'
                . '<li><b>Ranked suggestions</b>, strongest first, 15 to a page: <b>#</b>, <b>Driver and credit-loss measure</b> (hover the names for the codes and the measure\'s definition), <b>Lag</b> (months between the driver and the loss measure), <b>Score</b>, <b>R-squared</b> (written R with a small 2), <b>Verdict</b> (<b>Recommended</b> green, <b>Usable with caveat</b> gold, <b>Rejected</b> red; a rejected row is red) and <b>What it shows</b> (the finding in plain words; hover for the technical reason).</li>'
                . '<li><b>Regression fits for</b> the period (folded; <b>Show</b> and <b>Hide</b>): <b>Fit</b>, <b>Driver and credit-loss measure</b>, <b>Lag</b>, <b>Expected sign</b> (or <b>not stated</b>), <b>Slope</b>, <b>R-squared</b>, <b>p</b> (the p-value), <b>n</b> (observations), <b>Verdict</b> (<b>Applied</b> or <b>Declined</b> with the reason) and <b>Approval</b> (<b>Approved</b>, <b>Proposed</b> or <b>Not proposed</b>).</li>'
                . '<li><b>Sweeps</b> (folded): every sweep with <b>Run</b>, <b>Period</b> (a link to that period), <b>Run at</b>, <b>By</b> (<b>console</b> when run outside the screens), <b>Status</b>, <b>Suggestions</b> and <b>Inputs hash</b>; and the <b>Output of the last run</b> for the period. A later sweep is a new run, never an edit.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li>Period selector and <b>View</b>: show another period.</li>'
                . '<li><b>Run the finder for</b> the period (<b>eir.run</b>): runs the sweep while you wait (<b>Running...</b>) and keeps its output on the audit log. The Overview\'s step 3 runs the same sweep in the background.</li>'
                . '<li>Arrow icon at the end of a suggestion (<b>Show the technical detail</b> or <b>Hide the technical detail</b>): opens a line with the codes, the measure\'s definition and the technical reason.</li>'
                . '</ul>'
                . '<h4>To find and fit the candidates for a month</h4>'
                . '<ol>'
                . '<li>Choose the period and press <b>View</b>.</li>'
                . '<li>Press <b>Run the finder for</b> the period.</li>'
                . '<li>Read the ranked suggestions and their verdicts.</li>'
                . '<li>Open <b>Regression fits</b> to see which fits were applied.</li>'
                . '<li>Go to the Regression tab to propose the best applied fit.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No sweep for this period yet. Use Run the finder at the top right.</b>; <b>No fits for this period.</b>; <b>No sweep has run.</b></li>'
                . '<li><b>The finder ran for ...</b> and the first line of its output; <b>The finder stopped for ...</b> or <b>The finder failed:</b> with the reason.</li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Model, Correlation Finder.',
                'Choose the period and press View.',
                'Press Run the finder for the period (needs eir.run).',
                'Read the ranked suggestions; open a row for the technical detail.',
                'Open Regression fits to see what was applied, then propose one on Regression.',
            ],
            'images' => ['fli-correlation' => 'Correlation Finder: the tiles and the ranked suggestions with their verdicts'],
            'routes' => ['fli-correlation.index', 'fli-correlation.run'],
        ],

        'Regression analysis' => [
            'body' => '<p>The <b>Regression</b> tab (page title <b>Regression (FLI Adjustments)</b>) lists the fitted macro relationships of a period, shows the model version in force, and is where a reviewer proposes a fit and a second person approves it. Approval publishes the fit as a <b>model version</b> in force from that month until a later one. The model is recalibrated once a year or after an event; a month-end does not need a new fit. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Regression</b>. Any signed-in user can read it; proposing, approving and applying need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>Three tiles: <b>Method in force</b> (how the adjustment reaches the PD), <b>Loans with a post-FLI PD</b> (and how many were adjusted) and <b>Average PD, before and after</b>.</li>'
                . '<li>The pinned card of the version in force: <b>Model version ... in force</b>, the fit (or <b>External model</b>) in words, for example the driver <b>drives the</b> measure nine months later, <b>since</b> its month (and <b>this month applies it without a new fit</b> when it is older), a link <b>all versions</b>, then <b>Slope</b>, <b>Intercept</b>, <b>R-squared</b>, <b>p</b>, <b>Months of data</b>, <b>Expected</b> (moves with or against the driver), <b>Proposed by</b> and <b>Approved by</b> with the date. Without one, an amber card says <b>No model version is in force for ...: the PD holds</b>.</li>'
                . '<li><b>Fits for</b> the period, applied first then declined, strongest first, 15 to a page: <b>Fit</b>, <b>Driver and credit-loss measure</b>, <b>Lag</b>, <b>Slope</b> (hover for the intercept), <b>R-squared</b>, <b>p</b>, <b>n</b>, <b>Verdict</b> (<b>Applied</b> or <b>Declined</b> with the reason) and <b>Approval</b> (<b>Approved</b>, <b>Proposed</b> or <b>Not proposed</b>, with the approver). The approved row is highlighted. When a fit cannot be proposed or approved, amber text says why: <b>Cannot be proposed:</b> or <b>Cannot be approved:</b> and the reason.</li>'
                . '<li><b>How the adjustment reaches the PD</b> (folded): one card per method with <b>In force</b>, <b>Seeded</b> or <b>Preconditions not met</b> badges, what it does, its formula and symbols, what it implies, each precondition with a green tick or a red cross and the figure checked, and a worked example on a real loan.</li>'
                . '<li><b>Last run for</b> the period (folded, once the route has run): the loans, adjusted and held, the route, method, weighting and fit, any note, and the adjustment per scenario.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li>Month box and <b>View</b>: show another period.</li>'
                . '<li>Arrow icon (<b>Propose this fit for use</b>, <b>eir.govern</b>): on an applied fit that is not proposed. Disabled, with the reason, when the fit cannot be proposed.</li>'
                . '<li>Tick icon (<b>Approve this fit</b>, <b>eir.govern</b>): on a proposed fit. The proposer cannot approve it; the fit is checked again at approval because the data may have moved.</li>'
                . '<li><b>Apply the route to</b> the month (<b>eir.govern</b>): writes the post-FLI PD of every loan under the version and the approved set. The Overview\'s step 7 does the same.</li>'
                . '</ul>'
                . '<h4>To publish a new model version</h4>'
                . '<ol>'
                . '<li>Run the correlation sweep for the month (Overview step 3, Correlation Finder, or Maintenance, Recalibrate).</li>'
                . '<li>Open Regression for the month and read the applied fits.</li>'
                . '<li>Press the arrow icon on the best applied fit.</li>'
                . '<li>A second person presses the tick icon to approve it.</li>'
                . '<li>Check Model Versions, then apply the route and calculate the ECL from the Overview.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Approval publishes the fit as the model version in force from its month; an earlier approved fit of the same month is set aside with the note superseded by the new fit. The route then moves every loan\'s PD; the ECL must be calculated again (Overview step 9). If the route is applied while no version is in force, the PD holds and the success message says so.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No fits for this period yet. Run the correlation on the Correlation Finder tab, then come back to propose one.</b></li>'
                . '<li><b>Fit ... proposed for the route; a second person approves it.</b>; <b>Fit ... approved for the route.</b>; <b>Route applied for ...: ... loans adjusted, ... held</b> with any note.</li>'
                . '<li>Refused: <b>Fit ... was declined by the guardrail (...); it cannot be proposed.</b>; <b>Fit ... is already approved; it cannot be proposed again.</b>; <b>Fit ... is not proposed.</b>; <b>Maker-checker: the approver must be a different person from the proposer, and both must be named.</b></li>'
                . '</ul>',
            'steps' => [
                'Run the correlation sweep for the month (Overview step 3 or Correlation Finder).',
                'Open Forward-Looking Model, Regression, choose the month and press View.',
                'Press the arrow icon on the best applied fit to propose it.',
                'A second person presses the tick icon to approve it; it becomes the model version in force.',
                'Check Model Versions, then apply the route from the Overview.',
            ],
            'images' => ['fli-regression' => 'Regression (FLI Adjustments): the version in force and the fits with the propose and approve icons'],
            'routes' => ['fli-adjustments.index', 'fli-adjustments.propose', 'fli-adjustments.approve', 'fli-adjustments.apply'],
        ],

        'Model versions' => [
            'body' => '<p><b>Model Versions</b> lists every version of the forward-looking model: the one in force for the chosen month, when each took effect, who approved it and the months it governed. A version is never edited: a recalibration publishes a new one from its month forward. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Model Versions</b>. Any signed-in user can read it; retiring a version and approving or rejecting an external model need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Month</b> selector (top right).</li>'
                . '<li>The green card <b>In force for</b> the month: the version, its driver and measure and lag, <b>In force since</b>, <b>calibrated on data to</b>, the slope, intercept and R-squared, and <b>Cadence in force</b> (the Governance Centre setting). Without one, an amber card: <b>No version is in force for ...: the PD holds.</b></li>'
                . '<li><b>All versions</b>, 15 to a page: <b>Version</b>, <b>Driver and credit-loss measure</b>, <b>Lag</b>, <b>Slope</b> (hover for the intercept), <b>R-squared</b>, <b>Effective from</b>, <b>Source</b> (<b>Fit</b> and its number, or <b>External</b>), <b>Status</b> (<b>Published</b> green, <b>Proposed</b> gold, <b>Superseded</b> grey, <b>Retired</b> or <b>Rejected</b> red, with the retirement reason), <b>Approved by</b> (with the date) and <b>Months governed</b>. The version in force is highlighted.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Fit a new version</b>: opens Regression for the month.</li>'
                . '<li><b>Enter an external model</b>: opens External Calculations.</li>'
                . '<li>Ban icon (<b>Retire this version</b>, on a published version, <b>eir.govern</b>): asks <b>Retire version ...?</b> and <b>Why it is retired</b>. The months after it fall back to the version before it, or to none; run the route again for an open month.</li>'
                . '<li>Tick icon (<b>Approve this external model</b>, on a proposed external version, <b>eir.govern</b>): hidden from the person who proposed it. The confirmation says it governs every month from its effective month until a later version.</li>'
                . '<li>Cross icon (<b>Reject</b>, on a proposed external version, <b>eir.govern</b>): asks for the reason.</li>'
                . '</ul>'
                . '<h4>To retire a version</h4>'
                . '<ol>'
                . '<li>Choose a month the version governs.</li>'
                . '<li>Press the ban icon on the version and give the reason.</li>'
                . '<li>Read which version is now in force for the month.</li>'
                . '<li>Apply the route again from the Overview for any month that is still open, then calculate the ECL and record the month.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No version yet. Approving a fit on Regression publishes the first.</b></li>'
                . '<li><b>The version is retired; the months after it fall back to the version before it.</b>; <b>The external model is published from its effective month.</b>; <b>The external model is rejected.</b></li>'
                . '<li>Refused: <b>Version ... is ...; only a published version can be retired.</b>; <b>Version ... is ..., not proposed.</b>; <b>Maker-checker: the approver must be a different person from the proposer.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Model, Model Versions, and choose the month.',
                'Read the version in force and its cadence.',
                'Read All versions for when each took effect and the months it governed.',
                'A second person approves a proposed external model with the tick icon, or rejects it with a reason.',
                'Retire a version with the ban icon and a reason, then apply the route again for open months.',
            ],
            'images' => ['fli-models' => 'Model Versions: the version in force and every version with its status and months governed'],
            'routes' => ['fli-models.index', 'fli-models.retire', 'fli-models.approve-external', 'fli-models.reject-external'],
        ],

        'Adjusted forecast' => [
            'body' => '<p><b>Adjusted Forecast</b> shows what the model version in force forecasts under each scenario of the approved set, year by year: the credit-loss measure and the adjustment to the PD (year 1 is the one the route books), and each portfolio\'s PD term structure. After the governed forecast horizon the adjustment falls to nil in equal steps. The booked Stage 2 ECL is still measured by the ECL engine; this page is the evidence year by year beside it. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Adjusted Forecast</b>. Any signed-in user can read it; projecting needs <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>Four tiles: <b>Model in force</b> (the version, driver, measure and lag), <b>Scenario set</b> (the approved set or <b>None approved</b>), <b>Forecast horizon</b> (for example 3 + 2 years: the path, then the reversion) and <b>Projected</b> (when, or <b>Not yet</b>; red when out of date).</li>'
                . '<li><b>The credit-loss measure, forecast by scenario</b>: one row per scenario, one column per year; each cell gives the adjustment to the PD and the predicted measure (hover for the source and the driver value). The horizon is named under the table.</li>'
                . '<li><b>PD term structure by portfolio</b>: choose the portfolio; a line gives its performing loans, Stage 2 loans, exposure, PD before FLI and the years projected; one table per scenario with its <b>lifetime PD</b> and the columns <b>Year</b>, <b>Conditional</b>, <b>Survival</b>, <b>Marginal</b> and <b>Cumulative</b>. A note compares the engine\'s annualised lifetime PD over the same years.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Month</b> selector (top right).</li>'
                . '<li><b>Project the scenarios</b> (or <b>Project again</b>, refresh icon, <b>eir.govern</b>): runs the projection for the month and stores it. Disabled until a model version is in force and a set is approved. The Overview\'s step 8 runs the same projection.</li>'
                . '<li>The portfolio selector changes the term-structure tables.</li>'
                . '</ul>'
                . '<h4>To project a month</h4>'
                . '<ol>'
                . '<li>Choose the month.</li>'
                . '<li>Press <b>Project the scenarios</b>.</li>'
                . '<li>Read the adjustment of each scenario year by year.</li>'
                . '<li>Choose a portfolio and read its term structure under each scenario.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>The projection needs a model version in force and an approved scenario set for ...</b> (or one of the two).</li>'
                . '<li><b>Not projected yet for .... Use Project the scenarios at the top right.</b></li>'
                . '<li><b>The route ran after this projection: project again so it reflects the PDs the ECL now uses.</b></li>'
                . '<li><b>Projected ... portfolios under ... scenarios.</b>; refused, for example <b>No forecast horizon is in force</b>, <b>No performing loan of ... carries a PD before FLI: run the PD engine first.</b>, or a scenario with no driver value for a forecast year.</li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Model, Adjusted Forecast, and choose the month.',
                'Press Project the scenarios (needs eir.govern, a model version and an approved set).',
                'Read the adjustment and the predicted measure of each scenario year by year.',
                'Choose a portfolio to read its PD term structure under each scenario.',
            ],
            'images' => ['adjusted-forecast' => 'Adjusted Forecast: the measure by scenario year by year and the PD term structure of a portfolio'],
            'routes' => ['forecasting.manual', 'forecasting.manual.process'],
        ],

        'Weighted forecast' => [
            'body' => '<p><b>Weighted Forecast</b> shows, for a scenario set, the probability-weighted path of every driver year by year, beside each scenario\'s own path and the spread between them. IFRS 9 weights the loss, not the economy: the booked ECL is the weighted ECL of the scenarios (Impact Analysis). The weighted path is for reading the set, and is the input of the <b>Weight the macro path, one ECL</b> method the Governance Centre keeps for reproducing earlier months. Open <b>Forward-Looking Information</b>, <b>Forward-Looking Model</b>, <b>Weighted Forecast</b>. Any signed-in user can read it; storing and removing need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>A note with the date the values are as known at, and either <b>Stored:</b> with the revision, the number of values and when, or <b>Not stored yet for this set.</b></li>'
                . '<li>The table, 15 drivers to a page: <b>Driver</b> (name and code; <b>no scenario moves it</b> when every scenario is on the base), then for each forecast year one column per scenario with its weight, <b>Weighted</b> (bold) and <b>Spread</b> (the weighted standard deviation of the scenarios around it). A scenario value that differs from the base is highlighted; hover a value for where the base comes from.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Month</b> and scenario-set selectors (top right). The page opens on the approved set of the month.</li>'
                . '<li><b>Calculate and store</b> (or <b>Recalculate and store</b>, refresh icon, <b>eir.govern</b>): keeps the path against the set as a new revision; the previous revision is kept, not current.</li>'
                . '<li>Bin icon (<b>Remove the stored path of this set</b>, <b>eir.govern</b>): asks for confirmation and removes the stored path; the set itself is unchanged.</li>'
                . '</ul>'
                . '<h4>To store the weighted path of a set</h4>'
                . '<ol><li>Choose the month and the set.</li><li>Read the weighted value and the spread of each driver.</li><li>Press <b>Calculate and store</b>.</li></ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No scenario set for .... Start one from Scenario Profiles or on Scenario Sets.</b>; <b>The set has no paths.</b></li>'
                . '<li><b>Weighted path stored for set ... (revision ...).</b>; <b>The stored weighted path of the set is removed; the set itself is unchanged.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Forward-Looking Model, Weighted Forecast.',
                'Choose the month and the scenario set at the top right.',
                'Read the weighted value and the spread of each driver.',
                'Press Calculate and store to keep the path with the set.',
            ],
            'images' => ['fli' => 'Weighted Forecast for a scenario set: each scenario, the weighted value and the spread by year'],
            'routes' => ['macro-forecast-weighted.index', 'macro-forecast-weighted.calculate', 'macro-forecast-weighted.destroy'],
        ],

        'Scenario profiles' => [
            'body' => '<p>A <b>scenario profile</b> is a reusable template of economic scenarios: their names, weights, narratives, anchors and shocks. The month\'s scenario set is started from a profile and then proposed and approved on Scenario Sets. Open <b>Forward-Looking Information</b>, <b>Scenarios &amp; Overlays</b>, <b>Scenario Profiles</b>. Any signed-in user can read it and add or edit profiles and scenarios; adding the standard profile and starting a set need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Profiles</b>: the count, the reminder that a profile\'s weights must total 100 before a set started from it can be proposed, and the <b>Start a set for</b> month selector (the loan-book months and the month after the latest, each with the number of sets it already has).</li>'
                . '<li>The table, 15 to a page: <b>Code</b>, <b>Profile</b> (name and description), <b>Scenarios and weights</b> (each scenario with its weight, <b>(base)</b> and the number of shocks, or <b>No scenario yet</b>), <b>Total</b> (green at 100, gold otherwise), <b>Sets started</b> and <b>Actions</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Add the standard profile</b> (top right, <b>eir.govern</b>, shown until it exists): adds <b>MAIIC four scenarios</b>: Base 50, Upside 15, Downside 25 and Severe 10, each anchored to a Malawi episode (the 2021 recovery, the 2023 devaluation, the 2016 drought), with their shocks.</li>'
                . '<li><b>Scenario</b> (top right): opens <b>Add Scenario</b>: <b>Profile</b>, <b>Name</b>, <b>Description</b>, <b>Probability (%)</b>, <b>Is Base Case</b> and <b>Tags (comma-separated)</b>; <b>Cancel</b> or <b>Save</b>.</li>'
                . '<li><b>Profile</b> (top right): opens <b>Add Profile</b>: <b>Profile Code</b>, <b>Name</b> and <b>Description</b>; <b>Cancel</b> or <b>Save</b>.</li>'
                . '<li><b>Start a set</b> (<b>eir.govern</b>, disabled for a profile without scenarios): asks <b>Start a scenario set for ...?</b> and creates a <b>draft</b> set for the chosen month from the profile, then opens Scenario Sets.</li>'
                . '<li>Eye icon (<b>Open the profile\'s scenarios</b>): the <b>Scenarios</b> page of the profile with its <b>Profile Code</b> and <b>Profile Name</b>, a <b>Back</b> button, and the columns <b>Name</b>, <b>Probability</b>, <b>Type</b> (<b>Base Case</b> or <b>Alternative</b>) and <b>Tags</b>, with an edit icon, an eye icon (also opens the edit form) and a bin icon that deletes the scenario and the macro values tied to it after a confirmation.</li>'
                . '<li>Pencil icon (<b>Edit the profile</b>): opens <b>Edit Profile</b>.</li>'
                . '</ul>'
                . '<h4>To start the month\'s scenario set</h4>'
                . '<ol>'
                . '<li>Choose the month in <b>Start a set for</b>.</li>'
                . '<li>Press <b>Start a set</b> on the profile and confirm.</li>'
                . '<li>Set the weights and shocks on Macro Statistics, Scenario Assumptions (or with the editor on Scenario Sets).</li>'
                . '<li>Propose the set on Scenario Sets; a second person approves it.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The draft set reaches no loan until it is proposed and approved. Its source reads from profile and the profile code, which is how <b>Sets started</b> counts it.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No profile yet. Add the standard profile (four scenarios anchored to Malawi episodes) or create your own.</b></li>'
                . '<li><b>Draft set ... started for ... from the profile; review it, then propose it.</b>, or a warning ending <b>It cannot be proposed yet:</b> and the rules it breaks.</li>'
                . '<li><b>The profile has no active scenario; add its scenarios first.</b>; <b>Standard profile added: four scenarios with their shocks and anchors.</b>; <b>The standard profile is already held.</b>; <b>Profile created</b>, <b>Profile updated</b>, <b>Scenario created</b>, <b>Scenario updated</b>, <b>Scenario deleted</b>.</li>'
                . '</ul>',
            'steps' => [
                'Open Scenarios & Overlays, Scenario Profiles.',
                'Press Add the standard profile once, or create your own with Profile and Scenario.',
                'Choose the month in Start a set for.',
                'Press Start a set on the profile you want and confirm.',
                'Adjust the weights and shocks, then propose the set on Scenario Sets.',
            ],
            'images' => ['scenario-profiles' => 'Scenario Profiles with the Start a set for month and the Start a set button'],
            'routes' => ['scenarios.profiles', 'scenarios.profiles.seed', 'scenarios.profiles.start', 'scenarios.profiles.store', 'scenarios.profiles.update', 'scenarios.index', 'scenarios.store', 'scenarios.update', 'scenarios.destroy'],
        ],

        'Scenario sets' => [
            'body' => '<p><b>Scenario Sets</b> holds one versioned set of economic scenarios and weights per month: proposed by one person, approved by another and locked with the period. It shows each set\'s scenarios and shocks, its paths, and after approval its sensitivity and back-test. Open <b>Forward-Looking Information</b>, <b>Scenarios &amp; Overlays</b>, <b>Scenario Sets</b>. Any signed-in user can read it; every button needs <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Rules in force (Governance Centre)</b>: the minimum number of scenarios, the floor on the base weight, the ceiling on any weight, whether a calibration note is required on every downside, and the weighting method.</li>'
                . '<li><b>Overlays against</b> the month (when any): each overlay with its scope, adjustment and status, and the link <b>open the register</b>.</li>'
                . '<li>One panel per set, newest version first: the name, <b>v</b> and the version, a status badge (<b>DRAFT</b> grey, <b>PROPOSED</b> gold, <b>APPROVED</b> green, <b>LOCKED</b> solid green, <b>SUPERSEDED</b> grey), the narrative, a line with <b>Sources</b>, who proposed and approved it and when, when it was locked and which set it supersedes and why; in red the rules it breaks; in amber any overlay still proposed (<b>the set cannot lock until each is approved or rejected</b>); and <b>Overlays in force at approval</b>.</li>'
                . '<li><b>Scenarios and weights</b>: a card per scenario with its weight, <b>PD multiplier</b>, <b>Anchored to</b>, <b>Calibration</b> and its <b>Shocks on the base path</b> (for example +2 points, +44%, set to 25, times 1.1, and the year when it is not the first), or <b>No shocks: the base path as the sources stand.</b> for the base.</li>'
                . '<li><b>The paths, first forecast year</b>: <b>Series</b>, <b>Base</b> and a column per other scenario with its weight.</li>'
                . '<li>After approval: <b>Sensitivity</b> (the number of loans; <b>ECL under</b> each scenario <b>at 100 percent</b>, <b>Weighted</b>, <b>Ten points from the base to the downside</b> and <b>Ten points from the base to the upside</b>, with the basis) and <b>Back-test</b> (a note, red when the weights should be reviewed; <b>Series</b>, <b>Predicted base</b>, <b>Actual</b>, <b>Miss</b> and <b>Within range</b>).</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Period</b> box and <b>View period</b> (top right): show another month.</li>'
                . '<li><b>Propose the first set</b> (only when the month has no set): creates and proposes the standard four scenarios for the month, for the CFO to set the weights.</li>'
                . '<li><b>Edit weights, scenarios and shocks</b>: on a draft, or on a proposed set to the person who proposed it. The editor shows <b>Weights sum to</b> (green at 100), <b>Name</b>, <b>Source vintage</b>, <b>Narrative</b>, and per scenario <b>Scenario</b>, <b>Weight %</b>, <b>PD multiplier</b>, <b>the base</b> (one scenario), <b>Anchored to</b>, <b>Calibration note (required on every downside)</b> and <b>Remove scenario</b>; under <b>Shocks on the base path</b> the series code, the kind (<b>percent change</b>, <b>absolute change</b>, <b>replacement value</b> or <b>multiplier</b>), the value, the year offset (0 to 5), a note, <b>Remove shock</b> and <b>Add a shock</b>; then <b>Add a scenario</b>, <b>Save and re-check the rules</b> and <b>Cancel</b>.</li>'
                . '<li><b>Propose</b> (draft): checks the rules and marks the set proposed in your name.</li>'
                . '<li><b>Approve (second person)</b> (proposed): the proposer is refused. Approval checks the rules again, supersedes the set approved earlier for the month, records the overlays in force and stores the back-test and the sensitivity.</li>'
                . '<li><b>Lock with the period</b> (approved): refused while any overlay of the month is still proposed.</li>'
                . '<li><b>New version</b> (approved or locked): asks <b>Start a new version?</b> and <b>Why a new version?</b> and creates a draft copy; the set in force stays until the new version is approved.</li>'
                . '</ul>'
                . '<h4>The rules a set must pass</h4>'
                . '<p>At least the minimum number of scenarios; weights that sum to 100; one base at or above the floor; no weight above the ceiling; a calibration note on every downside when the rule requires it; and a shock on every scenario other than the base (otherwise it would be the base under another name).</p>'
                . '<h4>To propose and approve the month\'s set</h4>'
                . '<ol>'
                . '<li>Choose the month and press <b>View period</b>.</li>'
                . '<li>Open the editor on the draft, set the weights and shocks, and press <b>Save and re-check the rules</b>.</li>'
                . '<li>Press <b>Propose</b>.</li>'
                . '<li>The second person checks it on Impact Analysis and presses <b>Approve (second person)</b>.</li>'
                . '<li>When the month\'s overlays are decided, press <b>Lock with the period</b>.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No scenario set for ....</b> with a pointer to <b>Propose the first set</b>; <b>No base path for this period yet: load the macro statistics first.</b></li>'
                . '<li><b>Set ... proposed.</b>; <b>Set ... approved; the back-test and sensitivity are stored with it.</b>; <b>Set ... locked.</b>; <b>A new version of set ... was created as a draft.</b>; <b>Set ... saved; it passes its rules.</b> or <b>... saved as a draft that does not yet pass its rules:</b> and the problems.</li>'
                . '<li>Refused: <b>The set cannot be proposed:</b> and the problems; <b>Only a proposed set can be approved.</b>; <b>Maker-checker: the approver must be a different person from the proposer.</b>; <b>The set no longer passes its rules:</b>; <b>Only an approved set can be locked.</b>; <b>Set ... cannot be locked: overlay ... still proposed; approve or reject it first.</b>; <b>The base scenario cannot be removed: every other scenario is a shock on it.</b>; <b>Two shocks on ... for year offset ...: one series, one offset, one shock.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Scenarios & Overlays, Scenario Sets, choose the month and press View period.',
                'Edit the draft\'s weights, scenarios and shocks and press Save and re-check the rules.',
                'Press Propose.',
                'A second person reads Impact Analysis and presses Approve (second person).',
                'Lock the set with the period once no overlay of the month is still proposed.',
                'For a later change press New version and give the reason.',
            ],
            'images' => ['scenario-sets' => 'Scenario Sets: the rules in force, a set with its scenarios, shocks, paths, sensitivity and back-test'],
            'routes' => ['scenario-sets.index', 'scenario-sets.seed', 'scenario-sets.propose', 'scenario-sets.approve', 'scenario-sets.lock', 'scenario-sets.version', 'scenario-sets.update', 'scenario-sets.add-scenario', 'scenario-sets.remove-scenario'],
        ],

        'Economic scenarios (management overlays)' => [
            'body' => '<p><b>Economic Scenarios</b> is the scenario workbench: for a set, each driver\'s recent actuals and each scenario\'s path for the forecast years, as a small chart and a table, with the weights and the narrative, against the previous version of the month or the last set of the month before. It is for reading and comparing a set; the set is changed on Scenario Assumptions or Scenario Sets and approved on Scenario Sets. Management overlays have their own tab and article, <b>Manual overlays</b>. Open <b>Forward-Looking Information</b>, <b>Scenarios &amp; Overlays</b>, <b>Economic Scenarios</b>. Any signed-in user can read it.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>The scenarios</b>: the date the values are as known at and the set compared with. Columns <b>Scenario</b> (with its colour: Base green, Upside blue, Downside amber, Severe red), <b>Weight</b>, <b>Before</b> (the weight in the compared set, amber when it changed), <b>Anchored to</b> and <b>Calibration note</b>.</li>'
                . '<li>One card per driver, nine to a page, the shocked drivers first: the name and code (<b>every scenario on the base path</b> when nothing moves it); a chart with the actuals of the four years before in grey, a dashed line where the forecast starts and a coloured line per scenario; and a table with each scenario\'s value for the year and the two after. A value that changed against the compared set is amber and bold; hover it for <b>Before:</b> and the earlier value.</li>'
                . '<li><b>Named sets from the earlier screens</b> (folded, when any exist): <b>Name</b>, <b>Description</b>, <b>Active</b> and <b>Created</b>, with a pencil icon (<b>Edit</b>) that opens the earlier editor (<b>Scenario Set Name</b>, <b>Description</b>, <b>Active Status</b>, and each scenario\'s name and <b>Probability (%)</b>; saving needs the probabilities to total 100). These sets do not reach the ECL.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Month</b> and scenario-set selectors (top right): the page opens on the approved set of the latest month with a set.</li>'
                . '<li><b>Edit on Scenario Sets</b> (pencil icon): opens Scenario Sets for the month.</li>'
                . '<li>Page numbers under the cards.</li>'
                . '</ul>'
                . '<h4>To compare a new version with the one before</h4>'
                . '<ol><li>Choose the month and the new version.</li><li>Read the weights against <b>Before</b>.</li><li>Look for amber values in the driver cards and hover them for the earlier value.</li><li>Press <b>Edit on Scenario Sets</b> to change or approve the set.</li></ol>'
                . '<h4>Empty states</h4>'
                . '<p><b>No scenario set for .... Start one from Scenario Profiles.</b></p>',
            'steps' => [
                'Open Scenarios & Overlays, Economic Scenarios.',
                'Choose the month and the set at the top right.',
                'Read the weights against Before and each driver card against the compared set.',
                'Press Edit on Scenario Sets to change or approve it.',
            ],
            'images' => ['economic-scenarios' => 'Economic Scenarios, the scenario workbench: the scenarios and one card per driver'],
            'routes' => ['fli.scenarios.index', 'fli.scenarios.edit', 'fli.scenarios.update'],
        ],

        'Manual overlays' => [
            'body' => '<p><b>Manual Overlays</b> is the register of management judgement added on top of the forward-looking adjustment: a percentage on the PD of the whole book, a product group or one contract, each with a reason, evidence, an owner and an expiry month, approved by a second person and shown as its own line in the ECL. The register is read when the Governance Centre setting <b>How the forward-looking adjustment is produced</b> says <b>Manual overlay</b> or <b>Regression plus overlay</b>. Open <b>Forward-Looking Information</b>, <b>Scenarios &amp; Overlays</b>, <b>Manual Overlays</b>. Any signed-in user can read it; proposing, approving, rejecting and expiring need <b>eir.govern</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li>Four tiles: <b>In force for</b> the month; <b>Awaiting a second person</b> (with a note that the scenario set cannot lock until these are approved or rejected); <b>ECL added by overlays</b> (over the loans with a PD and an ECL); <b>Scenario set</b> (the approved set, or <b>None approved for ...</b> with a red note when the rule requires an approved set first).</li>'
                . '<li><b>Overlay register for</b> the month: every overlay whose life covers it, newest first. Columns <b>#</b>, <b>Scope</b> (<b>The whole book</b>, <b>Product group ...</b> or <b>Contract ...</b>), <b>PD adjustment</b> (for example +15.00%), <b>Reason and evidence</b> (with <b>Covers</b> and the driver it covers, red when it is the model\'s own driver without a review, and <b>Rejected:</b> with the reason), <b>Owner</b>, <b>Period to expiry</b>, <b>Status</b> (<b>Proposed</b>, <b>Approved</b>, <b>Rejected</b>, <b>Expired</b>, with who proposed, approved or rejected it), <b>Loans in scope</b> and <b>ECL line</b> (what it adds to the booked ECL of its loans; Stage 3 adds nothing). Overlays in force are in bold.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Period</b> box and <b>View period</b> (top right).</li>'
                . '<li><b>Propose an overlay</b> (or <b>Close the form</b>, <b>eir.govern</b>): opens the form: <b>Reporting period</b>, <b>Scope</b> (<b>The whole book</b>, <b>One product group</b> or <b>One contract</b>), the product group or <b>Contract id</b>, <b>Adjustment to the PD, percent</b> (+15 multiplies every PD in scope by 1.15; -10 by 0.90), <b>In force until (expiry period)</b>, <b>Owner</b>, <b>Reason: the event the statistics cannot see</b>, <b>Evidence: a reference or the text</b>, <b>Risk it covers (a driver, if any)</b> (the model\'s own driver is marked <b>(the model already uses it)</b>) and, when you choose that driver, a required box to say why the overlay is not counting it twice; then <b>Propose</b> or <b>Cancel</b>.</li>'
                . '<li>Tick icon (<b>Approve (second person)</b>) on a proposed overlay: the proposer is refused, and so is an overlay on the model\'s own driver without a double-count review.</li>'
                . '<li>Circled cross icon (<b>Reject</b>) on a proposed overlay: asks <b>Why is it rejected?</b>; the reason goes to the register and the audit log.</li>'
                . '<li>Calendar icon (<b>Expire now</b>) on an approved overlay: asks <b>Expire this overlay now?</b> with an optional reason; it is withdrawn before its expiry period.</li>'
                . '</ul>'
                . '<h4>To propose an overlay</h4>'
                . '<ol>'
                . '<li>Make sure the month\'s scenario set is approved.</li>'
                . '<li>Press <b>Propose an overlay</b>.</li>'
                . '<li>Choose the scope, type the adjustment, the expiry month and the owner.</li>'
                . '<li>Write the reason and the evidence, and the risk it covers if it is a driver.</li>'
                . '<li>Press <b>Propose</b>. A second person approves it with the tick icon.</li>'
                . '<li>Apply the route again and calculate the ECL from the Overview.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No overlay covers ....</b> with a pointer to <b>Propose an overlay</b> and to the Governance Centre setting.</li>'
                . '<li><b>Overlay ... proposed; a second person approves it before it reaches any loan.</b>; <b>Overlay ... approved: it is in force until its expiry period.</b>; <b>Overlay ... rejected.</b>; <b>Overlay ... expired: it no longer reaches any loan.</b></li>'
                . '<li>Refused: <b>An overlay needs an approved scenario set for ...; approve the set first.</b>; <b>An overlay on a ... must name which one.</b>; <b>An adjustment at or below minus one would take the PD to zero or below.</b>; <b>The expiry period must be a month no earlier than the reporting period.</b>; <b>Maker-checker: the approver must be a different person from the proposer.</b>; <b>Overlay ... covers ..., which is already the driver of model version ... in force for ...: approving it would count that risk twice. Record on the overlay why the model does not capture it, or withdraw it.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Scenarios & Overlays, Manual Overlays, and choose the month.',
                'Press Propose an overlay and choose the scope, the adjustment and the expiry.',
                'Write the reason, the evidence and the risk it covers, and press Propose.',
                'A second person approves it with the tick icon, or rejects it with a reason.',
                'Apply the route and calculate the ECL from the Overview.',
                'Expire an overlay early with the calendar icon.',
            ],
            'images' => ['fli-overlays' => 'Manual Overlays: the tiles, the propose form and the overlay register with the approve, reject and expire icons'],
            'routes' => ['fli-overlays.index', 'fli-overlays.propose', 'fli-overlays.approve', 'fli-overlays.reject', 'fli-overlays.expire'],
        ],

        'External calculations and calculation history' => [
            'body' => '<p>Two tabs of <b>Scenarios &amp; Overlays</b> that open with <b>reports.ifrs9</b>. <b>External Calculations</b> is where a forward-looking model calibrated outside the system (by a consultant, or in a validation workbook) is entered with its evidence, proposed by one person and approved by another; it then governs its months like any other model version. The page also shows what the route would do to the month under the version in force, before anyone applies it. <b>Calculation History</b> lists every recorded month and every approval and run behind it. Open <b>Forward-Looking Information</b>, <b>Scenarios &amp; Overlays</b>, then <b>External Calculations</b> or <b>Calculation History</b>.</p>'
                . '<h4>External Calculations: what you see</h4>'
                . '<ul>'
                . '<li><b>What the route would do to</b> the month: the route, method and weighting in force; <b>Model in force</b> (or <b>None: the PD holds.</b>) and <b>Scenario set</b> (or <b>No approved set for the month.</b>); any problem in red; a table <b>Scenario</b>, <b>Weight</b>, <b>Driver</b>, <b>Driver month</b>, <b>Predicted measure</b> and <b>Adjustment to the PD</b>; and today\'s base value of the driver with its predicted measure. Nothing is written.</li>'
                . '<li><b>External models</b>, 15 to a page: <b>Version</b>, <b>Driver and measure</b>, <b>Lag</b>, <b>Slope</b>, <b>Intercept</b>, <b>Governs from</b>, <b>Status</b> (PROPOSED, PUBLISHED, REJECTED, RETIRED), <b>Proposed</b>, <b>Approved</b> and <b>Evidence</b> (hover for the full text). Approved ones also appear on Model Versions.</li>'
                . '</ul>'
                . '<h4>External Calculations: buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Month</b> selector (top right).</li>'
                . '<li><b>Enter an external model</b> (plus icon, <b>eir.govern</b>): opens the form <b>An externally calibrated model</b> (proxy(t) = slope x driver(t - lag) + intercept): <b>Driver</b> and <b>Credit-loss measure</b> (both must be series the system holds), <b>Lag in months</b> (0 to 36), <b>Slope</b>, <b>Intercept</b>, <b>R-squared (optional)</b>, <b>Observations (optional)</b>, <b>Calibrated on data to</b>, <b>Governs from</b> and <b>Evidence</b> (the workbook or report, who calibrated it, the data and period, the tests it passed); <b>Cancel</b> or <b>Propose</b>.</li>'
                . '<li><b>Apply it from the Overview</b>: opens the Overview for the month.</li>'
                . '<li>Tick icon (<b>Approve</b>, <b>eir.govern</b>, hidden from the proposer) and cross icon (<b>Reject</b>, <b>eir.govern</b>, asks for a reason) on a proposed model.</li>'
                . '</ul>'
                . '<h4>Calculation History: what you see</h4>'
                . '<ul>'
                . '<li>Four tiles over the current records: <b>Months recorded</b>, <b>Signed off</b>, <b>Waiting for sign-off</b> and <b>Not reconciling</b>.</li>'
                . '<li><b>Month by month</b>, 15 to a page: <b>Month</b> (a link to Impact Analysis), <b>Model</b> (version, driver to measure, lag), <b>Scenario set</b>, <b>Loans</b>, <b>ECL before FLI</b>, <b>FLI effect</b>, <b>ECL booked</b>, <b>Weighted ties</b> (<b>Yes</b> or <b>No</b>), <b>Movement</b> (red when it needs a commentary; hover for the commentary), <b>Status</b> (<b>Recorded</b>, <b>Signed off</b> or <b>Superseded</b>, a superseded row in grey) and <b>Signed off</b> (who and when). The figures are on the engine measure (undiscounted).</li>'
                . '<li><b>Approvals and runs</b>: the latest 300 forward-looking and scenario-set entries of the audit log: <b>When</b>, <b>What</b>, <b>Month</b>, <b>Record</b> and <b>By</b>. The <b>Every action</b> selector filters by one action.</li>'
                . '</ul>'
                . '<h4>To enter an external model</h4>'
                . '<ol>'
                . '<li>Open External Calculations and press <b>Enter an external model</b>.</li>'
                . '<li>Choose the driver and the measure, and type the lag, slope and intercept.</li>'
                . '<li>Give the month it was calibrated on and the month it governs from (not earlier).</li>'
                . '<li>Write the evidence and press <b>Propose</b>.</li>'
                . '<li>A second person approves it here, on Model Versions or on the Overview.</li>'
                . '</ol>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><b>No external model has been entered. Use Enter an external model at the top right.</b>; <b>No month has been recorded yet. Record one on the Overview.</b>; <b>Nothing logged.</b></li>'
                . '<li><b>External model proposed (version record ...); a second person approves it before it governs any month.</b></li>'
                . '<li>Refused: <b>A model cannot govern a month before the data it was calibrated on (IFRS 9 B5.5.49).</b>; <b>Say where the calibration comes from: the workbook, the consultant, the data and the period it was fitted on.</b>; <b>The driver ... is not a series the system holds, so the route could not read it.</b>; <b>The credit-loss measure ... is not held, so the route could not read it.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Scenarios & Overlays, External Calculations, and choose the month.',
                'Read what the route would do under the model in force.',
                'Press Enter an external model, fill in the coefficients, months and evidence, and press Propose.',
                'A second person approves it here, on Model Versions or on the Overview.',
                'Open Calculation History to read every recorded month and filter the approvals and runs.',
            ],
            'images' => [
                'external-calculations' => 'External Calculations: what the route would do to the month and the external models',
                'calculation-history' => 'Calculation History: month by month, and the approvals and runs',
            ],
            'routes' => ['fli.external.index', 'fli.external.propose', 'fli.external.list'],
        ],

        'Earlier forward-looking screens kept off the menu' => [
            'body' => '<p>A few screens of the earlier forward-looking module are still reachable by their web address but are no longer on the menu. They do not feed the governed route or the booked ECL; use the tabs described in the articles above instead. They are listed here so that the help button on each of them finds an answer.</p>'
                . '<ul>'
                . '<li><b>The earlier regression chain</b> (Regression list, train a model, a model and its prediction): kept for the models trained before the governed route, behind the <b>reports.ifrs9</b> permission. Fit and approve the model on <b>Correlation Finder</b> and <b>Regression</b> instead.</li>'
                . '<li><b>The earlier macro statistics list and its values page</b>: replaced by the five Macro Statistics tabs.</li>'
                . '<li><b>Credit loss data by period</b>: replaced by the filters on Credit Loss Data.</li>'
                . '<li><b>Create a named scenario set</b>: the earlier named sets, listed folded on Economic Scenarios. A governed set is started from Scenario Profiles or Scenario Sets.</li>'
                . '</ul>'
                . '<p>The buttons of these screens are not described in this manual.</p>',
            'steps' => [
                'Use Macro Statistics instead of the earlier macro list.',
                'Use Correlation Finder and Regression instead of the earlier regression chain.',
                'Start scenario sets from Scenario Profiles or Scenario Sets.',
                'Filter Credit Loss Data by month instead of the period page.',
            ],
            'images' => ['regression' => 'The earlier regression list, kept off the menu'],
            'routes' => ['regression.index', 'regression.create', 'regression.view', 'regression.predict', 'macro-statistics.legacy', 'macro-values.index', 'credit-loss-data.period', 'fli.scenarios.create', 'regression.forecast.data'],
        ],
    ],
];
