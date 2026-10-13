<?php

/*
|--------------------------------------------------------------------------
| User Manual: Financial Modelling (second half), Forward-Looking Information
|--------------------------------------------------------------------------
| The forward-looking module as rebuilt on 13 October 2026 (FLI module
| specification v1): the month-end Overview and Impact Analysis, Macro
| Statistics with the scenario assumptions, the model and its versions, the
| scenarios and the overlays. The article titles are kept from the earlier
| chapter so the seeder refreshes them in place; the first half of the
| chapter (staging, PD and LGD) lives in a sibling file.
*/

return [
    'Financial Modelling' => [

        'Forward-looking information: how it works' => [
            'body' => '<p>IFRS 9 asks for expected credit losses that look ahead: a probability-weighted amount over a range of economic outcomes, using information available at the reporting date. In this system that is the <b>Forward-Looking Information</b> entry in the sidebar. It has four parts, used in this order.</p><ol><li><b>FLI Month-End</b>: the <b>Overview</b> walks a month through its ten steps and runs them; <b>Impact Analysis</b> shows what the forward-looking information did to the ECL.</li><li><b>Macro Statistics</b>: the economic variables and their values, and the <b>Scenario Assumptions</b> (the weight of each scenario and the shocks that move each variable).</li><li><b>Forward-Looking Model</b>: the credit-loss history, the Correlation Finder and Regression that fit the model, the <b>Model Versions</b> in force, the Adjusted Forecast (the PD year by year under each scenario) and the Weighted Forecast.</li><li><b>Scenarios &amp; Overlays</b>: Scenario Profiles (templates), Scenario Sets (propose and approve), Economic Scenarios (the workbench), Manual Overlays, External Calculations and Calculation History.</li></ol><h4>How the figure is made</h4><p>The model links one economic variable to one credit-loss measure, for example the prime lending rate nine months earlier to the share of accounts in Stage 3. For each scenario of the approved set, the model predicts the measure from that scenario\'s path and compares it with the prediction from today\'s value; the ratio, less one, moves each loan\'s PD. The ECL is measured under each scenario and weighted by the scenario weights; the PD the system books on each loan is the one whose ECL equals that weighted ECL. Stage 3 loans are not moved by the scenarios.</p><h4>What changes when</h4><ul><li><b>Every month-end</b>: the model version in force is applied; nothing is re-fitted.</li><li><b>Every quarter</b>, or when the outlook moves: a new scenario set, proposed by one person and approved by another.</li><li><b>Once a year</b>, or after an event that breaks the relationship (a devaluation, a drought): the model is recalibrated and approved as a new version.</li></ul><h4>Common problems</h4><ul><li><b>The Overview says no model version is in force.</b> Approve a fit on Regression; approval publishes it as the version in force from that month.</li><li><b>The ECL step refuses.</b> A new PD run clears the post-FLI PD; run the forward-looking route again (the Overview does it for you).</li><li><b>Weighted ECL does not tie to the booked ECL.</b> The ECL was calculated before the route last ran: calculate it again, then record the month.</li></ul>',
            'images' => ['fli' => 'The Forward-Looking Information entry in the sidebar'],
            'routes' => ['fli.overview', 'fli.impact'],
        ],

        'Macro elements' => [
            'body' => '<p><b>Macro Statistics</b> holds the economic variables the model reads. Open Forward-Looking Information, Macro Statistics. It has five tabs.</p><ul><li><b>Dashboard</b>: one card per variable with how many values it holds and where the last came from. Click a card to see its values.</li><li><b>Variables</b>: the list of variables with their unit, frequency, source and source codes (WB: the World Bank code, IMF: the WEO code). <b>Add variable</b> at the top right opens a window: a code in capitals (for example MAIZE_PRICE), a name, unit, frequency, source and the codes. The pencil edits a variable; the bin deletes one, and the system refuses while it still holds values or a model or a scenario uses it, saying which.</li><li><b>Data Entry</b>: the values of one variable. <b>Add value</b> at the top right takes the period, the value, whether it is a forecast, the date the publisher released it, and a reason. The pencil corrects a value (with a reason; the old value stays in the audit log); the bin deletes one.</li><li><b>Scenario Assumptions</b>: the weights and shocks of the scenario sets; see the article on scenario assumptions.</li><li><b>Import / Export</b>: the history of every load. <b>Import</b> at the top right opens a window with four sources: your own file (any variable, in the layout series_code, period, value, is_forecast, source, published_at), the World Bank (fetched live), the IMF World Economic Outlook file and the Reserve Bank policy-rate file. Press Preview, check the rows, then Import. <b>Export CSV</b> downloads every value in the same layout, so you can export, edit and import back.</li></ul><h4>Why the release date matters</h4><p>A figure is read for a month only if it was published by the end of that month. A forecast released in October never changes a figure for March.</p>',
            'steps' => [
                'Open Forward-Looking Information, Macro Statistics, Variables, and use Add variable for a new series.',
                'Open Data Entry, choose the variable and use Add value; or use Import at the top right for many values.',
                'Give each value the date its publisher released it.',
                'Correct a wrong value with the pencil and a reason; never delete and retype.',
            ],
            'images' => ['macro-elements' => 'Macro Statistics, Variables, with Add variable at the top right'],
            'routes' => ['macro-statistics.index', 'macro-statistics.variables.store', 'macro-statistics.variables.update', 'macro-statistics.variables.destroy', 'macro-statistics.observations.update', 'macro-statistics.observations.destroy', 'macro-statistics.manual', 'macro-statistics.csv-preview', 'macro-statistics.csv-commit', 'macro-statistics.export'],
        ],

        'Scenario profiles' => [
            'body' => '<p>A <b>scenario profile</b> is a reusable template: the scenarios (for example Base, Upside, Downside, Severe), their weights, what each is anchored to and the shocks each applies. Open Forward-Looking Information, Scenarios &amp; Overlays, Scenario Profiles.</p><ul><li><b>Add the standard profile</b> creates MAIIC four scenarios: Base 50, Upside 15, Downside 25 and Severe 10, each anchored to a Malawi episode (the 2021 recovery, the 2023 devaluation, the 2016 drought).</li><li><b>Profile</b> and <b>Scenario</b> at the top right create your own; the eye opens a profile\'s scenarios and the pencil edits it.</li><li><b>Start a set</b>, with the month chosen at the top of the list, creates a <b>draft</b> scenario set for that month from the profile. A draft reaches no loan: you set its weights and shocks on Macro Statistics, Scenario Assumptions, then propose it on Scenario Sets, and a second person approves it.</li></ul>',
            'steps' => [
                'Open Scenario Profiles and choose the month at the top of the list.',
                'Press Start a set on the profile you want.',
                'Adjust the weights and shocks on Macro Statistics, Scenario Assumptions.',
                'Propose the set on Scenario Sets; a second person approves it.',
            ],
            'images' => ['scenario-profiles' => 'Scenario Profiles with Start a set'],
            'routes' => ['scenarios.profiles', 'scenarios.profiles.seed', 'scenarios.profiles.start', 'scenarios.profiles.store', 'scenarios.profiles.update', 'scenarios.index', 'scenarios.store', 'scenarios.update', 'scenarios.destroy'],
        ],

        'Scenario assumptions: weights and shocks' => [
            'body' => '<p>Open Forward-Looking Information, Macro Statistics, Scenario Assumptions. Choose the month and the scenario set at the top.</p><ul><li><b>Scenario weights</b>: one box per scenario and the total, which must be 100. <b>Save weights</b> saves them. An approved set is locked: <b>New version to change it</b> makes a draft copy you can change, and the approved set stays in force until the new one is approved. <b>Discard this draft</b> removes a draft that was never proposed.</li><li><b>At a glance</b>: every variable a scenario moves, its base value and its value in each scenario, for the first forecast year.</li><li><b>One tab per scenario</b>: its shocks. A shock moves one variable in one forecast year: <b>add points</b> (a rate two points higher), <b>percent change</b> (the exchange rate 44 percent weaker) or <b>replace with</b> (a stated value). The table shows the base and what the value becomes. <b>Add a shock</b> and <b>Save</b> are at the top right of the tab.</li></ul><p>The base path is the variable\'s own values as known at the month-end. Where no forecast is held, the last actual is carried forward and the screen says so.</p>',
            'steps' => [
                'Choose the month and the set.',
                'Set the weights so the total is 100, and press Save weights.',
                'Open each scenario tab, add or change its shocks, and press Save.',
                'Propose the set on Scenario Sets; see its effect on Impact Analysis before a second person approves it.',
            ],
            'images' => [],
            'routes' => ['macro-statistics.assumptions.save', 'macro-statistics.assumptions.weights', 'macro-statistics.assumptions.version', 'scenario-sets.discard'],
        ],

        'Weighted forecast' => [
            'body' => '<p><b>Weighted Forecast</b> shows, for a scenario set, the probability-weighted path of every variable year by year, beside each scenario\'s own path and the spread between them. IFRS 9 weights the loss, not the economy: the booked ECL is the weighted ECL of the scenarios (Impact Analysis). The weighted path is for reading the set, and is the input of the "weight the macro path" method the Governance Centre keeps for reproducing earlier months. <b>Calculate and store</b> keeps the path against the set; the bin removes it.</p>',
            'steps' => [
                'Choose the month and the scenario set at the top right.',
                'Read the weighted value and the spread of each variable.',
                'Press Calculate and store to keep the path with the set.',
            ],
            'images' => ['fli' => 'Weighted Forecast for a scenario set'],
            'routes' => ['macro-forecast-weighted.index', 'macro-forecast-weighted.calculate', 'macro-forecast-weighted.destroy'],
        ],

        'Credit loss data' => [
            'body' => '<p><b>Credit Loss Data</b> is the credit-loss history the model is calibrated on. The first table shows the measures derived from MAIIC\'s own staged loan books each month (the NPL ratio by carrying amount, the Stage 3 share of accounts and the twelve-month default rate); they are refreshed by the correlation sweep and never typed in. Below it you enter or import <b>external series</b>, such as the Reserve Bank\'s industry NPL ratio, which give the model a longer history than MAIIC\'s own books. Leave the portfolio empty for an industry series, and give the date it was published: the model does not read it for any month before that date. <b>Add record</b>, <b>Import CSV</b> and <b>Measure</b> (a new kind of measure) are at the top right; the pencil and the bin edit and delete a record.</p>',
            'steps' => [
                'Use Measure to define a new external measure if needed (for example RBM_NPL).',
                'Use Add record or Import CSV to enter its monthly values, with the publication date.',
                'Run the correlation sweep (Overview, step 3) so the model can use it.',
            ],
            'images' => [],
            'routes' => ['credit-loss-data.index', 'credit-loss-data.create', 'credit-loss-data.store', 'credit-loss-data.edit', 'credit-loss-data.update', 'credit-loss-data.destroy', 'credit-loss-data.importView', 'credit-loss-data.import', 'credit-loss-data.definition', 'credit-loss-data.definition.store'],
        ],

        'Adjusted forecast' => [
            'body' => '<p><b>Adjusted Forecast</b> shows what the model in force forecasts under each scenario, year by year: the credit-loss measure and the adjustment to the PD (year 1 is the one booked), and each portfolio\'s PD term structure: the conditional PD of each year, the probability of surviving to it, the marginal PD and the cumulative PD. After the governed forecast horizon the adjustment returns to nil in equal steps. <b>Project the scenarios</b> at the top right runs it for the month; the Overview runs it as step 8. The booked Stage 2 ECL is still measured by the ECL engine; this is the evidence year by year beside it.</p>',
            'steps' => ['Choose the month.', 'Press Project the scenarios.', 'Choose a portfolio to read its term structure under each scenario.'],
            'images' => ['adjusted-forecast' => 'Adjusted Forecast with the PD term structure'],
            'routes' => ['forecasting.manual', 'forecasting.manual.process'],
        ],

        'Regression analysis' => [
            'body' => '<p>The model is fitted in two screens. <b>Correlation Finder</b> sweeps every economic variable against every credit-loss measure, at several lags, using only data known at the month-end. <b>Regression</b> fits the candidates under the guardrail (enough observations, a strong enough R-squared, the expected sign, a significant p-value) and lists them, applied first. A reviewer <b>proposes</b> a fit and a second person <b>approves</b> it. Approval publishes it as a <b>model version</b> in force from that month until it is replaced. <b>Model Versions</b> lists every version, which one is in force, the months each governed, and lets a version be retired (the months after it go back to the version before). The model is recalibrated once a year, or after an event; a month-end does not need a new fit.</p>',
            'steps' => [
                'Run the correlation sweep for the month (Overview, step 3, or Maintenance, Recalibrate).',
                'Open Regression, read the applied fits and propose the best.',
                'A second person approves it; it becomes the model version in force.',
                'Check Model Versions.',
            ],
            'images' => [],
            'routes' => ['fli-correlation.index', 'fli-correlation.run', 'fli-adjustments.index', 'fli-adjustments.propose', 'fli-adjustments.approve', 'fli-models.index', 'fli-models.retire'],
        ],

        'Economic scenarios (management overlays)' => [
            'body' => '<p><b>Economic Scenarios</b> is the scenario workbench: for a set, each variable\'s recent actuals and each scenario\'s path for the forecast years, as a small chart and a table, against the previous version or the previous month\'s set (a changed value is highlighted). The set itself is changed on Scenario Assumptions and approved on Scenario Sets.</p><p><b>Manual Overlays</b> are management judgements added on top of the model: a percentage on the PD of the book, a product group or a contract, with a reason, evidence, an owner and an expiry month, approved by a second person. An overlay that names, as the risk it covers, the very variable the model in force already uses is refused unless the proposer explains why it does not count that risk twice.</p>',
            'steps' => ['Open Economic Scenarios to read a set against the previous one.', 'Propose an overlay on Manual Overlays with its reason and the risk it covers; a second person approves it.'],
            'images' => ['economic-scenarios' => 'Economic Scenarios, the scenario workbench'],
            'routes' => ['fli.scenarios.index', 'fli-overlays.index', 'fli-overlays.propose', 'fli-overlays.approve', 'fli-overlays.reject', 'fli-overlays.expire'],
        ],

        'External calculations and calculation history' => [
            'body' => '<p><b>External Calculations</b> is where a model calibrated outside the system (by a consultant, or in a validation workbook) is entered: the variable, the credit-loss measure, the lag, the slope and the intercept, the month it was calibrated on, the month it governs from, and the evidence. One person proposes it, a second approves it, and it then governs its months like any other model version. The page also shows what the route would do to the month under the model in force, before anyone applies it.</p><p><b>Calculation History</b> lists every month recorded: what it ran under (model version, scenario set), the ECL before FLI, the forward-looking effect, the ECL booked, whether the weighted ECL ties, the movement and the sign-off; and below it every approval and run in the audit log.</p>',
            'steps' => ['Use Enter an external model at the top right, with its evidence.', 'A second person approves it on External Calculations, Model Versions or the Overview.', 'Read every month on Calculation History.'],
            'images' => [],
            'routes' => ['fli.external.index', 'fli.external.propose', 'fli.external.list', 'fli-models.approve-external', 'fli-models.reject-external'],
        ],

        'The forward-looking month-end' => [
            'body' => '<p>Open Forward-Looking Information, FLI Month-End, Overview, and choose the month. The ten steps are listed in order with their state: the loan book and its PD, the economic data, the credit-loss history, the model version in force, the approved scenario set, the overlays, the route applied to every loan, the scenario projection, the ECL on the post-FLI PD, and the month recorded and signed off. A step that needs a person (an import, an approval) says what to do and links to its screen; the others have a Run button and run in the background with their output on the right. <b>Run the remaining steps</b> at the top right runs every automated step that is not done and stops at the first that needs a person.</p><p><b>Waiting for a second person</b> lists the fits, scenario sets, overlays and external models proposed for the month, with an approve button; you cannot approve your own. <b>The month\'s record</b> shows the ECL before FLI, the forward-looking effect, the ECL booked, the ECL under each scenario and whether the weighted figure ties. A second person signs it off; when the ECL moved by more than the governed tolerance against the previous month-end, a commentary is needed first.</p>',
            'steps' => [
                'Choose the month on the Overview.',
                'Approve what is waiting (a second person).',
                'Press Run the remaining steps.',
                'Read Impact Analysis.',
                'A second person signs the month off, with a commentary if asked.',
            ],
            'images' => [],
            'routes' => ['fli.overview', 'fli.run', 'fli.sign-off', 'fli.comment', 'fli.impact'],
        ],
    ],
];
