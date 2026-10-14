<?php

/**
 * Administrator Manual chapter 4 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * financial periods as they are now, the reporting period lock, the Workspace and the month-end job).
 */
return [

    'Periods and Close' => [

        'Financial periods' => [
            'body' => '<p><b>Financial Periods</b> holds the accounting periods of the organisation: one is open at a time, and closing it opens the next. Open <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Financial Periods</b>, or <b>Settings</b>, <b>Reporting</b>, <b>Open Financial Periods</b>. The list needs <code>accounting.financial_periods.index</code>.</p>'
                . '<h4>Two kinds of period, and why the difference matters</h4><ul>'
                . '<li>A <b>financial period</b> is the accounting period kept on this screen. It is a record and a label: no IFRS 9 or EIR engine reads it, and closing one does not stop any calculation.</li>'
                . '<li>A <b>reporting period</b> is a month-end, written as a year and month such as 2025-12. Every screen is scoped to one. It comes into being when the month\'s loan book is built, and what protects a signed-off month is the <b>reporting period lock</b>, described in its own article. That lock, not the financial period, is the control that stops figures changing after sign-off.</li>'
                . '</ul>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>A search box (<b>Search period</b>) with <b>Clear</b>, and the number of periods on the right.</li>'
                . '<li>The table with the columns <b>Name</b> (or Period and its number when unnamed, with the description under it), <b>Start date</b>, <b>End date</b>, <b>Status</b> (green <b>Open</b> or grey <b>Closed</b>), <b>Closed by</b> and <b>Actions</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Add period</b> (top right). Needs <code>accounting.financial_periods.create</code>. The form warns: <b>Only one period is open at a time: when you save, any period still open is closed.</b></li>'
                . '<li>Grey padlock, <b>Close this period and open the next</b>. Shown on an open period to holders of <code>accounting.financial_periods.update</code>. Asks <b>Close this period?</b>: the period is closed with today as its end date, and a new, unnamed period opens from today.</li>'
                . '<li>Gold pencil, <b>Edit</b>. Change the name, dates or description. Needs <code>accounting.financial_periods.update</code>.</li>'
                . '<li>Red bin, <b>Delete</b>. Asks <b>Delete this financial period?</b> The record is removed; this cannot be undone. Needs <code>accounting.financial_periods.destroy</code>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4><ul>'
                . '<li><b>Name (optional)</b>, <b>Start date</b>, <b>End date (optional)</b>, <b>Description (optional)</b>. The save also requires a name and an end date after the start date, despite the optional labels.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>Creating, editing, closing and deleting a period are recorded in the Audit Trail as record changes.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No financial periods yet</b>: <b>Add the first period with Add period; from then on, closing a period opens the next one.</b></li>'
                . '<li><b>Financial Period created successfully.</b>, <b>Financial Period updated successfully.</b> (also after a close), <b>Financial Period deleted successfully.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>An analyst cannot recalculate a month.</b> That is the reporting period lock, not the financial period. See Locking and unlocking a reporting period.</li>'
                . '<li><b>A period was closed by mistake.</b> Edit the new open period\'s dates and name; closing is recorded and cannot be reversed from the screen.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Access & Setup, then Financial Periods.',
                'Press Add period, give it a name, start and end date, and save; any open period closes.',
                'At the end of the year, press the padlock on the open period and confirm Close period.',
                'Edit the new period that opens to give it its name and dates.',
                'Lock the signed-off reporting months separately on Imports & Feeds, Periods Built.',
            ],
            'images' => [
                'financial-periods' => 'Financial Periods with the open and closed badges and the padlock action',
                'financial-periods-create' => 'Adding a financial period',
            ],
            'routes' => ['accounting.financial_periods.index', 'accounting.financial_periods.create', 'accounting.financial_periods.edit'],
        ],

        'Locking and unlocking a reporting period' => [
            'body' => '<p>A locked reporting period is never restated. Once a month-end is signed off, lock it: the loan book build, staging, the PD, the LGD, the forward-looking route, the ECL, the revenue roll-forward, write-offs and staging threshold changes all refuse to write into it. The lock also keeps a snapshot of every governance value in force at that month-end, so a view as at a date inside the month, and the Audit Trace, reproduce the locked figures.</p>'
                . '<h4>Where it is</h4><p><b>Data foundation</b>, then <b>Customer &amp; Loan Data</b>, then <b>Imports &amp; Feeds</b>, tab <b>Periods Built</b>. The table lists each period with its method, rows, flagged rows and a <b>Locked</b> column (grey <b>Locked</b> badge, or <b>Open</b>). The <b>Locked periods</b> figure at the top counts them; hover for the list.</p>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li>The padlock in the <b>Action</b> column (closed padlock, <b>Lock</b> the period; open padlock, <b>Unlock</b> it). Shown only to holders of <code>eir.govern</code>.</li>'
                . '<li>The window asks for a <b>Reason (written to the audit log)</b> of 10 to 500 characters. For an unlock, give the approval for the restatement as the reason. Then <b>Lock the period</b> or <b>Unlock the period</b>, or <b>Cancel</b>.</li>'
                . '</ul>'
                . '<p>Locking and unlocking are one person\'s act, not a two-person control; the reason and the name go to the audit log, so agree the unlock with the CFO first and quote the approval.</p>'
                . '<h4>From the command line</h4><p><code>php artisan eir:lock-period 2025-12 --user=1 --reason="Audited year-end"</code> locks a month; add <code>--unlock</code> to unlock it. The reason is required.</p>'
                . '<h4>What happens next</h4><ul>'
                . '<li>The audit log records <b>Reporting Period Locked</b> or <b>Reporting Period Unlocked</b> with the period and the reason.</li>'
                . '<li>Any engine asked to write the month stops with: <b>Reporting period</b> 2025-12 <b>is locked:</b> what it tried to write <b>cannot be written.</b> The message names the screen and command to unlock it.</li>'
                . '<li>Unlocking discards the snapshot; locking again takes a new one from the values in force at that month-end.</li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li>The confirmation from the command, for example Period 2025-12 locked with N governance values snapshotted.</li>'
                . '<li><b>Period</b> 2026-09 <b>has no loan book; there is nothing to lock.</b></li>'
                . '<li>Period 2025-12 was not locked. (an unlock of an open period)</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The padlock is missing.</b> Your role lacks <code>eir.govern</code>.</li>'
                . '<li><b>A correction must go into a locked month.</b> Unlock with the approval as the reason, make the correction, rerun the month, review, and lock again.</li>'
                . '</ul>',
            'steps' => [
                'After the month\'s sign-off, open Imports & Feeds and the Periods Built tab.',
                'Press the padlock on the month.',
                'Write the reason, for example the sign-off reference, and press Lock the period.',
                'To restate a locked month, get the approval first, press the open padlock, quote the approval and press Unlock the period.',
                'Lock it again once the restatement is reviewed.',
            ],
            'images' => [
                'eir-feed-periods' => 'Periods Built, with the Locked column and the padlock action',
            ],
            'routes' => [],
        ],

        'Reporting periods and how figures are scoped' => [
            'body' => '<p>Almost every question about a figure turns out to be a question about which period it belongs to.</p><h4>How a reporting period comes into being</h4><ol><li>The month\'s E-Banker pack lands and the loan book build for the month is proposed and approved.</li><li>The month-end calculations run: staging, PD and LGD, the forward-looking route and the ECL, the EIR revenue.</li><li>The ECL run marks the month as calculated.</li><li>Only then does the month appear in the dashboard and Reports period lists.</li><li>After sign-off the month is locked and never restated.</li></ol><h4>Which screens offer which periods</h4><ul><li>The <b>dashboard</b> and the <b>Reports</b> hub offer only months with a calculated ECL.</li><li>The <b>loan book</b>, the Maintenance month picker and the reconciliations offer every month that has a loan book.</li><li>Most list screens open on the latest month with data.</li></ul><h4>The compare-to rule</h4><p>The dashboard measures every change chip against a compare-to month, by default the closest earlier calculated month. Early months hold partial books, so growth against them is naturally large. When someone reports an implausible percentage, check the compare-to month first.</p><h4>Common problems</h4><ul><li><b>A month is missing from the dashboard list.</b> No ECL calculation exists for it.</li><li><b>Two reports disagree.</b> Confirm both are on the same month and scope.</li><li><b>A month shows a loan book but no ECL.</b> The normal state between the build and the month-end run.</li></ul>',
            'images' => [
                'ecl' => 'The ECL results screen, where a calculated period first becomes visible',
            ],
            'routes' => [],
        ],

        'The period close workspace' => [
            'body' => '<p>The <b>Workspace</b> is the month-end IFRS 9 close for one reporting period: each step, who did it, what waits for a second person, and the team\'s notes. Open it from the top of the sidebar, under Dashboard. Everyone signed in can read it; ticking a manual step needs the <b>admin</b> role.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li><b>Reporting period</b> (top right): the month the page shows.</li>'
                . '<li>Four figures: <b>Close progress</b> for the month (a percentage and a bar), <b>Steps done</b> (for example 7 of 10), <b>Outstanding</b> (hover for the list), and <b>Signed in as</b> with your name and either <b>Administrator: can tick manual steps</b> or <b>Read-only view</b>.</li>'
                . '<li>Three tabs with counts: <b>IFRS 9 close checklist</b>, <b>Awaiting approval</b> and <b>Team messages</b>.</li>'
                . '</ul>'
                . '<h4>The checklist</h4><p>Ten steps in order: <b>Import the loan book for the period</b>, <b>Portfolio segmentation &amp; sector tags</b>, <b>Stage allocation (SICR rules applied)</b>, <b>PD model applied to the loan book</b>, <b>LGD model applied to the loan book</b>, <b>Forward-looking month-end (route, record, sign-off)</b>, <b>ECL calculation run</b>, <b>Review IFRS 9 reports &amp; data quality</b>, <b>Stress / sensitivity scenarios run</b> and <b>Management sign-off for the period</b>. The line above reads <b>Steps marked System are checked automatically from the data; an administrator ticks the others.</b> and then either the steps still to do or <b>All steps complete for this period.</b></p><ul>'
                . '<li>A step\'s number turns into a tick when it is done; under its name is a detail line or <b>Ticked by</b> a name and date.</li>'
                . '<li>Grey <b>System</b> badge with a padlock: verified automatically from the database; it cannot be ticked by hand.</li>'
                . '<li><b>Done</b> or <b>Pending</b> badge.</li>'
                . '<li>Arrow icon, <b>Open this screen</b>: goes to the screen where the step is done.</li>'
                . '<li>Tick icon, <b>Mark as done</b>, or circular arrow, <b>Reopen this step</b>: on the two manual steps, for the admin role only. Ticking or reopening notifies the other administrators through the bell.</li>'
                . '</ul>'
                . '<h4>Awaiting approval</h4><p><b>Everything a second person must approve, across the system (all periods). The person who proposed an item cannot approve it.</b> One line per workflow with something waiting: a gold count, the name, and an arrow (<b>Open</b>) to its screen. The lines are: loan book builds proposed; governance setting changes proposed; write-offs proposed; EIRs calculated, awaiting approval and lock; repayment schedules awaiting review; fees awaiting classification review; EIR accounting rules not yet approved; SICR trigger alerts proposed; scenario sets proposed; forward-looking regression fits proposed; external forward-looking models proposed; manual overlays proposed; forward-looking months recorded, awaiting sign-off. When nothing waits: <b>Nothing is waiting for approval.</b></p>'
                . '<h4>Team messages</h4><p><b>Team conversation</b> for the period, visible to everyone signed in. Type in <b>Message the team about this period...</b> and press Enter or <b>Send</b>. Use it to record decisions taken during the close. Empty: <b>No messages yet. Start the conversation for this period.</b></p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>Only administrators can update the workspace.</b> A non-administrator tried to tick a step.</li>'
                . '<li><b>This step is verified automatically by the system.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A System step will not tick.</b> The work is not finished for this month. Check the period selector, then open the step\'s screen.</li>'
                . '<li><b>You cannot tick a manual step.</b> Your account does not hold the admin role.</li>'
                . '</ul>',
            'steps' => [
                'Open Workspace and choose the reporting period being closed.',
                'Watch the System steps tick as the month-end jobs complete.',
                'Open the Awaiting approval tab and clear each line as the second person.',
                'When the reports are reviewed, tick Review IFRS 9 reports & data quality.',
                'After management sign-off, tick Management sign-off for the period.',
                'Record decisions in Team messages, then lock the month on Imports & Feeds, Periods Built.',
            ],
            'images' => [
                'workspace' => 'The Workspace with the close figures and the ten-step checklist',
                'workspace-approvals' => 'The Awaiting approval tab, one line per workflow with a count and a link',
            ],
            'routes' => ['workspace.index'],
        ],

        'Running the month-end close' => [
            'body' => '<p>This is the month-end as the administrator runs it, from the landed pack to the locked month. Every step that writes figures is either a job you start or an approval a second person gives; the platform never approves for you.</p>'
                . '<h4>The order</h4><ol>'
                . '<li><b>Land the pack.</b> The scheduler lands new E-Banker packs from the feed folder every day at 06:30; or upload one on Imports &amp; Feeds, E-Banker Loads; or run <b>Fetch new data</b> on Maintenance. Check the load passed its gates.</li>'
                . '<li><b>Build the loan book.</b> Maintenance, <b>Month-end</b>, step 1 <b>Build the loan book</b>, for the month; or propose the build on Imports &amp; Feeds, Loan Book Builds. A second person approves it on <b>Loan Book Builds</b>. A locked month is refused.</li>'
                . '<li><b>Run the month-end calculations.</b> Maintenance, <b>Month-end</b>, step 2. It needs an approved build, a forward-looking model version in force and an approved scenario set.</li>'
                . '<li><b>Approve.</b> Clear the Workspace tab <b>Awaiting approval</b>: restructure candidates and rate moves found by the run wait on EIR Data, Restructures &amp; Resets; fees wait on Fee Classification; EIRs wait on EIR Calculations; the forward-looking month waits for its sign-off on Forward-Looking Information, Overview.</li>'
                . '<li><b>Review and sign off.</b> Read the reports; tick the two manual Workspace steps.</li>'
                . '<li><b>Lock the month.</b> Imports &amp; Feeds, Periods Built, padlock.</li>'
                . '</ol>'
                . '<h4>What the month-end job runs</h4><p><code>php artisan maiic:task month-end 2026-08 --user=1</code> (the same job Maintenance starts) runs these steps in order, prints each one\'s output, and stops at the first that fails, naming it: <b>Stopped at step</b> N <b>(</b>name<b>). Correct what it names and run the job again; the steps before it are done.</b></p><ol>'
                . '<li><b>Clients for new loan-book customers</b> (<code>clients:link-loan-book</code>).</li>'
                . '<li><b>Scheme settings and the over-sanction flag</b> (<code>eir:load-schemes</code>).</li>'
                . '<li><b>Programme portfolios</b>, the Mega Farm schemes kept apart from the MAIIC book (<code>ifrs9:segment-portfolios</code>).</li>'
                . '<li><b>Restructure candidates and rate moves</b>, detection only: nothing is confirmed (<code>eir:restructure-candidates</code>).</li>'
                . '<li><b>Stage the book</b> (<code>eir:stage</code>).</li>'
                . '<li><b>PD and LGD by segment</b> (<code>ifrs9:segment-parameters</code>).</li>'
                . '<li><b>Forward-looking route, projection, ECL and the month record</b> (<code>fli:month-end --all</code>).</li>'
                . '<li><b>Actual cash from the E-Banker ledger</b> (<code>eir:load-ledger-cash</code>).</li>'
                . '<li><b>GL fee postings tied to their loans, and the fee GL gate</b> (<code>eir:build-gl-fees</code>).</li>'
                . '<li><b>EIR revenue</b> (<code>eir:run-revenue</code>).</li>'
                . '<li><b>Mega Farm programme</b>, only when the month has Mega Farm loans (<code>megafarm:ecl</code>); skipped with a warning otherwise.</li>'
                . '</ol>'
                . '<p>When every step is done it prints <b>All</b> N <b>steps done.</b> The Maintenance card describes the same job in a shorter list; the command is the authority.</p>'
                . '<h4>What happens next</h4><p>Each step writes to the audit log as it runs, and the Maintenance run history keeps the full output. The Workspace System steps tick as their work lands.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The job stops at staging or the PD naming a governance setting.</b> The setting has no approved value; open the Governance Centre, Needs attention.</li>'
                . '<li><b>The job stops on a locked month.</b> Expected: unlock it with the approval first, if the rerun is deliberate.</li>'
                . '<li><b>The forward-looking step stops.</b> No model version in force or no approved scenario set. Approve them on their screens.</li>'
                . '</ul>',
            'steps' => [
                'Confirm the month\'s E-Banker pack has landed with its gates passed.',
                'On Maintenance, Month-end, choose the month and run Build the loan book; have a second person approve the build.',
                'Run the month-end calculations and read the output to the end.',
                'Clear the Workspace Awaiting approval tab as the second person.',
                'Review the reports, tick the two manual Workspace steps.',
                'Lock the month on Imports & Feeds, Periods Built.',
            ],
            'images' => [
                'maintenance' => 'The Maintenance console on the Month-end tab, with the month picker',
            ],
            'routes' => [],
        ],

    ],

];
