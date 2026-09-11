<?php

/**
 * Administrator Manual chapter 4 (Ticket #011 depth rewrite).
 */
return [

    'Periods and Close' => [

        'Financial periods' => [
            'body' => '<p><b>Administration</b>, then <b>Financial Periods</b>, holds the accounting periods the organisation reports on.</p><h4>What you see on the screen</h4><ul><li>A search filter and a <b>Create Period</b> button.</li><li>The table with the columns <b>ID</b>, <b>Name</b>, <b>Date Created</b>, <b>Date Closed</b>, <b>Active</b> and <b>Actions</b>.</li><li>Row actions: green eye to view, gold pencil to edit, red bin to delete, and a <b>Close</b> action shown as an amber chip beside them.</li><li>An empty state reading <b>No Financial Periods found.</b> on a fresh installation.</li></ul><h4>Two kinds of period, and why the difference matters</h4><ul><li>A <b>financial period</b> is the accounting year or period the organisation reports on. You create and close these.</li><li>A <b>reporting period</b> is the month-end loan book snapshot, written as a year and month such as 2025-11. Every screen in the platform is scoped to one. These are not created here; they appear when a loan book for that month is imported, and they become available to the dashboard and the reports hub once an ECL calculation exists for them.</li></ul><h4>Closing a period</h4><p>Closing locks the period so figures attributed to it are not changed after sign-off. Close only after the reports have been reviewed, the workspace checklist is complete and the numbers have been signed off. The action asks for confirmation, records the closing date and writes the change to the audit trail.</p><h4>What happens next</h4><p>Once closed, work that would post into the period is refused. If an analyst reports that they cannot recalculate into a month, check here first.</p><h4>Common problems</h4><ul><li><b>The menu item returns a permission error for everyone.</b> The financial period permissions were missing on some installations and are added by a seeder. Raise a ticket if it persists.</li><li><b>A period was closed too early.</b> Reopening is a deliberate act with a trail. Do it, note why in a support ticket, and re-close after the correction.</li></ul>',
            'steps' => [
                'Open Administration, then Financial Periods.',
                'Press Create Period, enter the name and the start and end dates, and save.',
                'Leave it active while the year is being reported.',
                'After the reports are reviewed and signed off, press Close on the row and confirm.',
            ],
            'images' => [
                'financial-periods' => 'Financial periods with their created and closed dates and the Close action',
                'financial-periods-create' => 'Creating a financial period',
            ],
            'routes' => ['accounting.financial_periods.index', 'accounting.financial_periods.create'],
        ],

        'Reporting periods and how figures are scoped' => [
            'body' => '<p>Almost every question about a figure in this platform turns out to be a question about which period it belongs to, so it is worth understanding the rules.</p><h4>How a reporting period comes into being</h4><ol><li>A loan book snapshot for the month is imported. Every row carries the reporting period, written as a year and month.</li><li>Models are calculated and applied for that period.</li><li>An ECL calculation runs for the period. That is what marks it as calculated.</li><li>Only then does the period appear in the dashboard and the IFRS 9 Reports hub period lists.</li></ol><h4>Which screens offer which periods</h4><ul><li>The <b>dashboard</b> and the <b>IFRS 9 Reports hub</b> offer only periods with a calculated ECL, because a report on an uncalculated period would be empty.</li><li>The <b>loan book</b>, <b>stress testing</b> and the reconciliation reports offer every period that has loan book data.</li><li>Most list screens open on the latest period that has data, rather than the oldest, so the first page shows live figures.</li></ul><h4>The compare-to rule</h4><p>The dashboard measures every change chip against a compare-to period, which defaults to the closest earlier calculated period. Early periods hold partial books, so growth measured against them is naturally large. When someone reports an implausible percentage, check the compare-to period before anything else.</p><h4>Common problems</h4><ul><li><b>A month is missing from the dashboard list.</b> No ECL calculation exists for it. Either it was skipped or the run failed.</li><li><b>Two reports disagree.</b> Confirm both are on the same period and the same scope. A portfolio-level run and a total-level run cover different populations.</li><li><b>A period shows a loan book but no ECL.</b> That is the normal state between import and calculation.</li></ul>',
            'images' => [
                'ecl' => 'The ECL results screen, where a calculated period first becomes visible',
            ],
            'routes' => ['expected-credit-loss.index'],
        ],

        'The period close workspace' => [
            'body' => '<p>The <b>Workspace</b> is the month-end checklist. It shows how far the close has progressed, what is outstanding and who is doing it. Open it from the sidebar, directly under Dashboard.</p><h4>What you see on the screen</h4><ul><li>A <b>Reporting Period</b> pill in the header with a calendar icon, listing the periods available.</li><li>A card showing who is signed in, with a badge reading either <b>Administrator: can tick manual steps</b> or <b>Read-only view</b>.</li><li>A progress ring showing the percentage complete, and a banner listing the outstanding steps.</li><li>Two tabs with counts: <b>IFRS 9 Close Checklist</b> and <b>Team Messages</b>.</li></ul><h4>Two kinds of step</h4><ul><li><b>System-verified steps</b> are checked live against the database and cannot be ticked by hand: the loan book import, segmentation, staging, PD, LGD, the forward-looking adjustment, the ECL run and the stress run. They tick themselves when the underlying work is done, which means the checklist cannot be flattered.</li><li><b>Manual judgement steps</b> are report review and sign-off. Only an administrator can tick these, and ticking one notifies the other administrators through the bell.</li></ul><h4>Team messages</h4><p>The second tab is a per-period message thread. Use it to record decisions taken during the close, for example why a particular exposure was overridden. It is not a chat replacement; it is the place a reviewer looks to understand what happened in that month.</p><h4>What happens next</h4><p>When every step is complete, the close is done and the financial period can be closed. The checklist state is stored against the period, so it is still there when someone asks in six months what was done.</p><h4>Common problems</h4><ul><li><b>A system-verified step will not tick.</b> The underlying work is not finished, or it was done for a different period or scope. Check the period pill.</li><li><b>You cannot tick a manual step.</b> Your account is not an administrator, so the badge on the signed-in card reads Read-only view.</li></ul>',
            'steps' => [
                'Open Workspace and select the reporting period being closed.',
                'Watch the system-verified steps tick as the analysts complete the work.',
                'Chase the outstanding steps shown in the banner.',
                'When the reports have been reviewed, tick Report review, then Sign-off after sign-off.',
                'Record any decisions in Team Messages, then close the financial period.',
            ],
            'images' => [
                'workspace' => 'The period close workspace with the progress ring and the two tabs',
            ],
            'routes' => ['workspace.index'],
        ],

    ],

];
