<?php

/**
 * Administrator Manual chapter 8 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * the Maintenance jobs, the scheduler and the current menu paths).
 */
return [

    'Housekeeping' => [

        'Backups and restore' => [
            'body' => '<p>Backups are MAIIC\'s responsibility under the implementation agreement: automated daily database and file backups, retained for at least thirty days. The technical commands are in the <b>Installation Guide</b>; this article is what an administrator needs to know to be sure they are actually happening.</p><h4>What must be backed up</h4><ul><li><b>The database.</b> Everything: the landed E-Banker packs, the loan books, the models, the ECL results, the EIR tables, the governed settings and their history, the period locks, the audit trail, the tickets and both manuals.</li><li><b>The environment file.</b> It holds the application encryption key. Without it, encrypted values such as two-factor secrets cannot be read even from a good database backup.</li><li><b>Uploaded and generated files.</b> Manual figures, logos, failed-rows files, the E-Banker inbox and pack folders, and the Maintenance run output.</li></ul><p>The application code, including the compliance workbooks and the committed client inputs, is in version control and is restored by cloning.</p><h4>What to verify, and how often</h4><ul><li><b>Weekly.</b> That last night\'s backup file exists, is not zero length, and is copied somewhere other than the same disk.</li><li><b>Before any large load, rebuild or upgrade.</b> That a current backup exists. Take one if not. Never run <b>Bootstrap the system (first install)</b> on Maintenance against a database you need: it wipes it.</li><li><b>Twice a year.</b> A real restore onto a clean machine, ending with signing in, opening the dashboard and exporting a report.</li></ul><h4>Restoring</h4><p>Restoring is a developer or ICT task, described in the Installation Guide. After restoring, run <b>Update the system after a new release</b> on Maintenance (or <code>php artisan maiic:task update</code>) so anything newer than the backup is applied, then <b>Check the system</b>.</p><h4>Common problems</h4><ul><li><b>The backup job stopped silently.</b> This is why the weekly check exists. Look at the file date, not at whether the job is configured.</li><li><b>A restore came back without manual figures or logos.</b> The uploaded files were not in the backup set. Add them.</li></ul>',
            'steps' => [
                'Each week, ask ICT to confirm the newest database backup file, its size and date.',
                'Confirm backups are copied off the server to MAIIC\'s backup system.',
                'Take a backup before any large load, rebuild or upgrade.',
                'Twice a year, arrange a full restore test onto a clean machine and record the result in a ticket.',
            ],
        ],

        'Routine checks' => [
            'body' => '<p>A short list, done regularly, catches nearly everything before a user reports it.</p>'
                . '<h4>Daily, on working days</h4><ul>'
                . '<li><b>The overnight feed.</b> The scheduler lands new E-Banker packs from the feed folder at 06:30. Open Imports &amp; Feeds, E-Banker Loads: a new load should appear, and none should be quarantined without a reason you know.</li>'
                . '</ul>'
                . '<h4>Weekly</h4><ul>'
                . '<li><b>Check the system</b> on Maintenance (<code>maiic:task health-check</code>). Read the output to the end: every check reads PASS or FAIL, and the last line names any that failed.</li>'
                . '<li><b>The queue worker and the scheduler are running.</b> The symptoms when they are not: an import sitting at Pending, no new feed load. Ask ICT.</li>'
                . '<li><b>Disk space and log size.</b> Both grow quietly; the landed packs and the Maintenance output are large.</li>'
                . '<li><b>Certificate expiry.</b> The TLS certificate should have more than thirty days left.</li>'
                . '<li><b>The backup file</b>, as above.</li>'
                . '</ul>'
                . '<h4>Monthly</h4><ul>'
                . '<li><b>The month-end close</b>: the Workspace for the month complete, the Awaiting approval tab empty, the month locked.</li>'
                . '<li><b>The Governance Centre, Needs attention</b>: nothing left without an approved value, no stale proposal.</li>'
                . '<li><b>The macro data.</b> The scheduler fetches the World Bank series on the 2nd of each month at 07:00; Macro Statistics should show the new figures.</li>'
                . '<li><b>Read the Audit Trail</b> for governed-setting, period-lock and account changes you did not expect.</li>'
                . '<li><b>Review open tickets</b> and chase anything stale.</li>'
                . '<li><b>Check user accounts and roles</b> against the staff list: deactivate leavers, and confirm no role holds <code>eir.govern</code> or <code>settings</code> that should not.</li>'
                . '</ul>'
                . '<h4>After every release</h4><ul>'
                . '<li>Run <b>Update the system after a new release</b> on Maintenance, then <b>Check the system</b>.</li>'
                . '<li>Review the roles: the update re-grants the EIR, macro, financial period, currency and ticket permissions to every staff role.</li>'
                . '<li>Ship the documentation release (see Shipping a documentation release).</li>'
                . '<li>Open the dashboard, one report PDF and one Excel export.</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Everything is slow.</b> Usually the database cache settings or the code accelerator on the server. The Installation Guide covers both.</li>'
                . '<li><b>A Maintenance job has been running for hours.</b> Read its output on Run history to see which step it is on, and ask ICT before starting another.</li>'
                . '</ul>',
            'steps' => [
                'Daily: check E-Banker Loads for the overnight pack.',
                'Weekly: run Check the system on Maintenance; confirm the queue worker, scheduler, disk, logs, certificate and backup.',
                'Monthly: close and lock the month, clear Needs attention, read the Audit Trail, review tickets, users and roles.',
                'After a release: run Update the system, then Check the system, review the roles and ship the documentation.',
            ],
            'images' => [
                'maintenance' => 'The Maintenance console, where the update and the health check run',
            ],
            'routes' => [],
        ],

        'Getting help from Dupleix' => [
            'body' => '<p>Raise everything through <b>Support Tickets</b> (<b>Administration &amp; support</b>, <b>Support &amp; Maintenance</b>), then tell Dupleix. The ticket is the record both sides work from and the mechanism the agreement points at for change control.</p><h4>What to include</h4><ul><li>The screen, from its breadcrumb, and the exact steps that produced the problem.</li><li>The reporting period and the portfolio in view.</li><li>The exact message, copied rather than paraphrased.</li><li>The time it happened, so the audit trail and the server log can be searched.</li><li>For a Maintenance job, the run from Run history: its name, start time and the last lines of its output.</li><li>A screenshot if the problem is visual.</li></ul><h4>What you are entitled to</h4><ul><li>During the ninety-day warranty after go-live, correction of reproducible defects is included.</li><li>After the warranty, support is charged by the hour unless a separate support arrangement is signed.</li><li>Response targets: one business day for a critical issue (the system is down or a regulatory report is blocked), two business days for a high-impact issue with a workaround, and five business days for medium issues and general queries.</li></ul><h4>What counts as a change rather than a defect</h4><p>New features, additional modules, data cleansing beyond what was agreed, new core banking interfaces, custom reports beyond the delivered set, additional training and historical recalculations are changes. They are quoted and agreed on a change request before work starts.</p><h4>Escalation</h4><p>If a critical issue has no response within the target, escalate through the project leads named in the agreement rather than re-raising the ticket.</p><h4>Common problems</h4><ul><li><b>The ticket comes back asking for more detail.</b> Almost always the missing message text or the period.</li><li><b>A request is quoted rather than fixed.</b> It was a change, not a defect. The ticket will say which.</li></ul>',
            'steps' => [
                'Open Support & Maintenance, then Support Tickets, and press New ticket.',
                'Include the screen, the steps, the period, the exact message and the time.',
                'Set the priority honestly: Critical means down or blocking a regulatory report.',
                'Notify Dupleix that the ticket exists.',
                'Add the outcome with Add an update and close the ticket once MAIIC is satisfied.',
            ],
            'images' => [
                'tickets' => 'The support ticket record shared with Dupleix',
            ],
            'routes' => [],
        ],

    ],

];
