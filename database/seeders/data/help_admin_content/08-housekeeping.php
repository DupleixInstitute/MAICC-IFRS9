<?php

/**
 * Administrator Manual chapter 8 (Ticket #011 depth rewrite).
 */
return [

    'Housekeeping' => [

        'Backups and restore' => [
            'body' => '<p>Backups are MAIIC\'s responsibility under the implementation agreement: automated daily database and file backups, retained for at least thirty days. The technical commands are in the <b>Installation and Configuration Guide</b>; this article is what an administrator needs to know to be sure they are actually happening.</p><h4>What must be backed up</h4><ul><li><b>The database.</b> Everything: the loan book, the models, the ECL results, the EIR tables, the audit trail, the tickets and both manuals.</li><li><b>The environment file.</b> It holds the application encryption key. Without it, encrypted values such as two-factor secrets cannot be read even from a good database backup.</li><li><b>Uploaded files.</b> Manual figures and the failed-rows files from imports.</li><li><b>The captured screenshots</b>, if they have been refreshed on the server.</li></ul><p>The application code itself is in version control and is restored by cloning, so it does not need backing up.</p><h4>What to verify, and how often</h4><ul><li><b>Weekly.</b> That last night\'s backup file exists and is not zero length, and that it is copied somewhere other than the same disk. A backup on the same disk as the database is not a backup.</li><li><b>Before any large import or upgrade.</b> That a current backup exists. Take one if not.</li><li><b>Twice a year.</b> A real restore onto a clean machine, ending with signing in and exporting a report. An untested backup is an assumption.</li></ul><h4>Restoring</h4><p>Restoring is a developer or ICT task. Two details matter and are in the Installation Guide: the import needs foreign key checks disabled because legacy data contains a few orphan rows, and the database packet size must be raised first. After restoring, migrations and the content seeders are run so that anything newer than the backup is applied.</p><h4>Common problems</h4><ul><li><b>The backup job stopped silently.</b> This is why the weekly check exists. Look at the file date, not at whether the job is configured.</li><li><b>A restore came back without manual figures.</b> The uploaded files were not in the backup set. Add them.</li></ul>',
            'steps' => [
                'Each week, ask ICT to confirm the newest database backup file and its size and date.',
                'Confirm backups are copied off the server to MAIIC\'s backup system.',
                'Take a backup before any large import or upgrade.',
                'Twice a year, arrange a full restore test onto a clean machine and record the result in a ticket.',
            ],
        ],

        'Routine checks' => [
            'body' => '<p>A short list, done regularly, catches nearly everything before a user reports it.</p><h4>Weekly</h4><ul><li><b>The queue worker is running.</b> The symptom when it is not is an import sitting at <b>pending</b>. Ask ICT to confirm the service is up.</li><li><b>Failed jobs.</b> ICT can list them. A handful after a bad file is normal; a growing list is not.</li><li><b>Disk space and log size.</b> Both grow quietly.</li><li><b>Certificate expiry.</b> The TLS certificate should have more than thirty days left. An expired certificate makes the platform unreachable, and renewal takes time to arrange.</li><li><b>The backup file</b>, as above.</li></ul><h4>Monthly</h4><ul><li><b>Reconcile the loan book period totals</b> with the core banking control totals.</li><li><b>Read the audit trail</b> for settings or permission changes you did not expect.</li><li><b>Review open tickets</b> and chase anything stale.</li><li><b>Check user accounts</b> against the staff list, deactivating leavers.</li></ul><h4>After every release</h4><ul><li>Open the dashboard, one report PDF and one Excel export. If those three work, the release is basically sound.</li><li>Read the release note and check whether anything in the manuals needs updating.</li></ul><h4>Common problems</h4><ul><li><b>Everything is slow.</b> Usually the database cache settings or the code accelerator on the server. The Installation Guide covers both; they have caused a real slowdown here before.</li><li><b>An import never finishes.</b> The worker died mid-job. ICT restarts it and the job resumes.</li></ul>',
            'steps' => [
                'Weekly: confirm the queue worker, failed jobs, disk, logs, certificate expiry and the backup file.',
                'Monthly: reconcile period totals, review the audit trail, chase open tickets, and deactivate leavers.',
                'After a release: open the dashboard, download a report PDF and an Excel export.',
            ],
            'images' => [
                'imports' => 'An import stuck at pending is the usual sign the queue worker is not running',
            ],
            'routes' => ['imports.index'],
        ],

        'Getting help from Dupleix' => [
            'body' => '<p>Raise everything through <b>Support Tickets</b>, then tell Dupleix. The ticket is the record both sides work from and the mechanism the agreement points at for change control.</p><h4>What to include</h4><ul><li>The screen and the exact steps that produced the problem.</li><li>The reporting period and the portfolio in view.</li><li>The exact message, copied rather than paraphrased.</li><li>The time it happened, so the audit trail and the server log can be searched.</li><li>A screenshot if the problem is visual.</li></ul><h4>What you are entitled to</h4><ul><li>During the ninety-day warranty after go-live, correction of reproducible defects is included.</li><li>After the warranty, support is charged by the hour unless a separate support arrangement is signed.</li><li>Response targets: one business day for a critical issue (the system is down or a regulatory report is blocked), two business days for a high-impact issue with a workaround, and five business days for medium issues and general queries.</li></ul><h4>What counts as a change rather than a defect</h4><p>New features, additional modules, data cleansing beyond what was agreed, new core banking interfaces, custom reports beyond the delivered set, additional training and historical recalculations are changes. They are quoted and agreed on a change request form before work starts. Stress testing, the early warning tiles, the executive commentary and sensitivity analysis were delivered as extras beyond the contracted scope.</p><h4>Escalation</h4><p>If a critical issue has no response within the target, escalate through the project leads named in the agreement rather than re-raising the ticket.</p><h4>Common problems</h4><ul><li><b>The ticket comes back asking for more detail.</b> Almost always the missing message text or the period. Include both first time.</li><li><b>A request is quoted rather than fixed.</b> It was a change, not a defect. The ticket will say which.</li></ul>',
            'steps' => [
                'Open Administration, then Support Tickets, and create a ticket.',
                'Include the screen, the steps, the period, the exact message and the time.',
                'Set the priority honestly: critical means down or blocking a regulatory report.',
                'Notify Dupleix that the ticket exists.',
                'Add the outcome to the trail and close the ticket once MAIIC is satisfied.',
            ],
            'images' => [
                'tickets' => 'The support ticket record shared with Dupleix',
            ],
            'routes' => ['tickets.index'],
        ],

    ],

];
