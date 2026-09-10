<?php

/**
 * Administrator Manual content (Ticket #011, contract Schedule 1 item 6).
 * Loaded by HelpAdminContentSeeder into the help centre as the "admin"
 * manual. Same spec shape as HelpContentSeeder: chapter => article =>
 * [body html, steps, images (screenshot key => caption), routes].
 */
return [

    'Administering the Platform' => [
        'Role of the administrator' => [
            'body' => '<p>The administrator owns everything that is not a calculation: who can sign in and what they may do, the organisation settings and reference data, the financial and reporting periods, the governance of imports and locked results, the audit trail, support tickets and the documentation. Analysts run the IFRS 9 workflow described in the User Manual; the administrator keeps the environment in which they do it correct and controlled.</p><p>Everything in this manual is reached from the <b>Administration</b> group in the navigation pane, the <b>Settings</b> hub inside it, and the governance actions on the EIR and ECL screens. Server-side tasks such as backups, the queue worker and upgrades are covered in the Installation Guide.</p>',
            'images' => ['dashboard' => 'The dashboard is the first screen after sign-in; administrators use it to confirm the latest period has calculated results'],
        ],
        'Administration menu map' => [
            'body' => '<p>The Administration group contains the following items. Each opens a list page with the standard row actions: a green eye to view, a gold pencil to edit and a red bin to delete where deletion is allowed.</p><ul><li><b>User Management</b>: accounts, activation and roles.</li><li><b>Roles and Permissions</b>: the permission matrix grouped by module.</li><li><b>Financial Periods</b>: accounting periods and their open or closed status.</li><li><b>Audit Trail</b>: the unified timeline of user actions.</li><li><b>Support Tickets</b>: enhancement, issue and change tracking with MAIIC and Dupleix.</li><li><b>Settings</b>: organisation details, general and system preferences, email, licence and the reference data hub.</li></ul><p><b>System Documentation</b> sits above Administration and holds the User Manual, this Administrator Manual, the Technical Manual and the Installation Guide.</p>',
            'routes' => ['users.index', 'users.roles.index', 'accounting.financial_periods.index', 'audit-trail.index', 'tickets.index', 'settings.index'],
        ],
    ],

    'Users and Access' => [
        'User accounts' => [
            'body' => '<p>User Management lists every account with its roles and active status. A user must be <b>active</b> to sign in; an inactive user is rejected at login even with the right password, so deactivation is the safe way to remove access without deleting history. Passwords must satisfy the rules enforced at registration and change: a minimum length with mixed characters. Users can enable two-factor authentication and review their own browser sessions from the profile menu.</p><p>The seeded administrator account (admin@localhost.com) must have its password changed at first sign-in and should be replaced by named MAIIC accounts.</p>',
            'steps' => [
                'Open Administration, User Management and press New User.',
                'Enter the name, email address and an initial password, and tick Active.',
                'Assign one or more roles, then save.',
                'Ask the user to sign in, change the password and enable two-factor authentication.',
                'To remove access, edit the user and untick Active rather than deleting the account.',
            ],
            'images' => ['users' => 'User Management with active status and roles'],
            'routes' => ['users.index'],
        ],
        'Roles and permissions' => [
            'body' => '<p>Roles and Permissions shows a matrix of permissions grouped by module, each named module.action (for example tickets.update). Tick the permissions a role needs and save; users with that role gain the access on their next request. The <b>admin</b> role holds every permission.</p><p>In August 2026 the fifty-six permissions left over from the credit-scoring era and the borrower client role were removed, so the matrix now lists IFRS 9 functions only. Dedicated Preparer, Reviewer, Approver and Read-only roles for the IFRS 9 close are planned under ticket #008 and are not yet seeded; create them manually from the matrix until then.</p>',
            'steps' => [
                'Open Administration, Roles and Permissions and press New Role, or edit an existing role.',
                'Give the role a name and tick the permissions by module.',
                'Save, then assign the role to users under User Management.',
                'Search the list to confirm the role appears with the expected permission count.',
            ],
            'images' => ['roles' => 'The permission matrix grouped by module'],
            'routes' => ['users.roles.index'],
        ],
        'Login security' => [
            'body' => '<p>The login page requires a security code read from an image generated on the server; no third-party service is involved and the code is single use. Sessions expire after the configured lifetime and a friendly modal explains an expired session or a permission refusal rather than failing silently. HTTPS enforcement, secure cookies and strict transport security are switched on by the server administrator once a certificate is installed, as described in the Installation Guide.</p><p>Two application-level maker and checker controls apply regardless of role: a fee classification cannot be reviewed by the person who classified it, and an original EIR cannot be approved by the person who calculated it. Only a user with the admin role can override these or reopen a locked EIR, and every override is recorded in the audit trail.</p>',
        ],
    ],

    'Organisation Settings' => [
        'Settings hub' => [
            'body' => '<p>Settings opens a hub of sections:</p><ul><li><b>Organisation</b>: company name, logo, address and contacts, and links to the reference data (currencies, chart of accounts, branches, sector types). The name and logo appear on every report and manual cover.</li><li><b>General</b> and <b>System</b>: preferences such as timezone and formatting.</li><li><b>Email</b>: outbound mail server used for password resets and notifications.</li><li><b>SMS</b> and <b>Loan bands</b>: legacy sections from the loan-origination template; they are not used by the IFRS 9 workflow and their consolidation is tracked under ticket #008.</li><li><b>Licence</b>: the licence record supplied by Dupleix.</li></ul><p>All settings updates require the settings permission and are written to the audit trail.</p>',
            'images' => [
                'settings' => 'The Settings hub',
                'settings-organisation' => 'Organisation details and reference data links',
                'settings-email' => 'Email server settings',
            ],
            'routes' => ['settings.index', 'settings.organisation', 'settings.general', 'settings.system', 'settings.email'],
        ],
        'Reference data' => [
            'body' => '<p>Reports and segmentation depend on reference data being complete before loan book snapshots are loaded.</p><ul><li><b>Currencies</b>: the organisation currency (MWK) labels every figure on the dashboard and reports.</li><li><b>Chart of Accounts</b> and <b>Branches</b>: organisation structure used for grouping.</li><li><b>Sector Types</b>: the economic sectors behind the RBM sector concentration reports.</li><li><b>Product Groups</b> and <b>Loan Portfolios</b> (under Portfolio Setup): the segments (MAIIC core, FInES, Mega Farm and derived agricultural portfolios) that every stage table and roll-forward is reported by.</li></ul>',
            'steps' => [
                'Open Settings, Organisation and follow the Currencies link; confirm MWK exists and is the organisation currency.',
                'Review Sector Types against the sectors in the annual report note on loans and advances.',
                'Under Portfolio Setup, confirm the loan portfolios match the segments MAIIC reports on.',
                'Only then start loading loan book snapshots.',
            ],
            'images' => [
                'currencies' => 'Currencies with the organisation currency',
                'sector-types' => 'Sector types used by the concentration reports',
            ],
            'routes' => ['currencies.index', 'chart_of_accounts.index', 'branches.index', 'industry_types.index', 'portfolios.index'],
        ],
        'Licence' => [
            'body' => '<p>The Licence page holds the licence record for the installation and verifies it. Enter the details supplied by Dupleix at handover. Under the agreement MAIIC owns its installed system and data in perpetuity; the licence record identifies the installation for support purposes and does not switch the application off.</p>',
            'images' => ['license' => 'Licence record'],
            'routes' => ['license.index'],
        ],
    ],

    'Periods and Close' => [
        'Financial periods' => [
            'body' => '<p>Financial periods are accounting periods with a start date, an end date and a status. Create the financial year before loading data. Closing a period locks it so results attributed to it are not changed after sign-off; the Close action is shown as an amber chip beside the row actions. Creating, updating, deleting and closing each require their own permission.</p>',
            'steps' => [
                'Open Administration, Financial Periods and press New Period.',
                'Enter the name, start date and end date, and save.',
                'When the period has been reported and signed off, press Close on its row and confirm.',
            ],
            'images' => ['financial-periods' => 'Financial periods with their status'],
            'routes' => ['accounting.financial_periods.index'],
        ],
        'Reporting periods' => [
            'body' => '<p>A reporting period is a month-end loan book snapshot identified as YYYY-MM. Every import, calculation and report is scoped to one. The dashboard and the reports hub offer only periods for which an ECL calculation exists; the loan book and stress testing list every period with data. Most screens open on the latest period that has data, and the reporting period selector carries a calendar icon so it is easy to find.</p><p>The compare-to selector on the dashboard defaults to the closest earlier period and drives every change chip and the portfolio summary table.</p>',
            'images' => ['ecl' => 'The ECL calculation page scoped to a reporting period'],
            'routes' => ['expected-credit-loss.index'],
        ],
        'Period close workspace' => [
            'body' => '<p>The Workspace shows the IFRS 9 close checklist for a reporting period and the team messages for it. System-verified steps (loan book import, segmentation, staging, PD, LGD, FLI, ECL run, stress run) are checked live against the database and cannot be ticked by hand. Manual judgement steps (report review, sign-off) can be ticked only by administrators; ticking one notifies the other administrators through the bell. The progress ring and the outstanding-steps banner show where the close stands.</p>',
            'steps' => [
                'Open Workspace and select the reporting period.',
                'Work through the system-verified steps by completing the underlying tasks; they tick themselves.',
                'When the reports have been reviewed, tick Report review, and after sign-off tick Sign-off.',
                'Use Team Messages to record decisions taken during the close.',
            ],
            'images' => ['workspace' => 'The period close workspace with the checklist and progress ring'],
            'routes' => ['workspace.index'],
        ],
    ],

    'Data Operations' => [
        'Import governance' => [
            'body' => '<p>The Imports page lists every import job with its status (pending, processing, completed, failed), the number of records loaded and failed, and a download of the failed-rows file where one exists. Loan book, collateral, client and EIR extract imports all appear here. Imports run on the queue, so a job that stays pending means the queue worker is not running; ask the server administrator to start it.</p><p>Every EIR intake writes its outcome to the audit trail, and re-running an EIR extract does not duplicate rows because each row carries a source identifier.</p>',
            'steps' => [
                'After an import completes, compare the records count with the source file row count.',
                'Download the failed-rows file, resolve each reason with the data owner and re-import the corrected rows.',
                'Check the Loan Book page totals for the period against the core banking control total.',
            ],
            'images' => ['imports' => 'Import history with statuses and failed-rows downloads'],
            'routes' => ['imports.index'],
        ],
        'Locks and recalculation' => [
            'body' => '<p>Results that have been reviewed are locked so they cannot drift. A transition matrix or LGD run that is locked or closed is immutable; create a new draft if a change is needed. The ECL calculation takes a lock per period and scope while it runs, and a second run started at the same time is refused with the message that a calculation is already running. If a run is interrupted and the message persists, ask Dupleix to clear the lock.</p><p>Developers can rerun a period from the command line (the recalculate-ecl command) with the same logic as the screen; the audit trail records every run.</p>',
        ],
        'EIR data governance' => [
            'body' => '<p>The EIR pipeline has four control points that administrators own.</p><ul><li><b>Accounting rules</b> are created as drafts and only take effect once approved; editing a rule resets its approval.</li><li><b>Fee classification</b> is maker and checker: a classifier records the treatment and a different user reviews it. Only reviewed integral fees enter the EIR.</li><li><b>Schedule approval</b>: a generated original schedule is a draft until a reviewer approves it on the EIR Data, Schedules tab; a review note is required when it does not reconcile to the remaining schedule within one percent.</li><li><b>Calculation lock</b>: a calculated EIR is approved and locked by a second user. Reopening a locked EIR is administrator-only, requires a written reason, archives the locked result and marks every dependent revenue and discounted ECL figure as stale until recalculated and re-approved.</li></ul><p>The Coverage and Blockers page names, for every contract, what is stopping its EIR, ranked by exposure.</p>',
            'images' => [
                'eir-rules' => 'Accounting rules awaiting approval',
                'eir-fees' => 'Fee classification with maker and checker',
                'eir-calculations' => 'EIR calculations with approve and lock actions',
                'eir-coverage' => 'Coverage and blockers ranked by exposure',
            ],
            'routes' => ['eir-accounting-rules.index', 'eir-fee-classification.index', 'eir-calculations.index', 'eir-coverage.index', 'eir-data.index'],
        ],
    ],

    'Monitoring and Support' => [
        'Audit trail' => [
            'body' => '<p>The Audit Trail is one timeline over both audit stores: the activity log written by model events and controller actions, and the application audit log written by imports, settings changes and EIR actions. Filter by search text, source, user and date range; open an entry to see its detail as JSON. Use it to answer who changed a setting, who approved a rule, when an import ran and what it produced.</p>',
            'images' => ['audit-trail' => 'The unified audit trail with filters'],
            'routes' => ['audit-trail.index'],
        ],
        'Notifications' => [
            'body' => '<p>The bell in the header shows unread notifications. Events that raise one today: a ticket created, updated or commented on (to the assignee and creator), and a workspace checklist step ticked (to the other administrators). Open the bell to read the list and mark one or all as read. Notifications for import completion, ECL runs and EIR approvals are planned under ticket #006.</p>',
        ],
        'Support tickets' => [
            'body' => '<p>Support Tickets is the agreed record between MAIIC and Dupleix of enhancements, issues and changes. Each ticket has a three-digit reference assigned in sequence, a category, a priority, a status (open, in progress, resolved, closed), a requester and source, a responsible person and a chronological activity trail. Status changes are recorded automatically; progress notes can be added by hand. Tickets #001 to #011 document the platform review and documentation work of August and September 2026.</p>',
            'steps' => [
                'Open Administration, Support Tickets and press New Ticket.',
                'Describe the request, choose the category and priority, and assign the responsible person.',
                'Add progress notes as work proceeds; change the status when it moves.',
                'Record the resolution and set the status to resolved; close it once MAIIC confirms.',
            ],
            'images' => ['tickets' => 'Support tickets with status counts and row actions'],
            'routes' => ['tickets.index'],
        ],
        'Dashboard health indicators' => [
            'body' => '<p>For an administrator the dashboard answers three questions quickly: does the latest reporting period have calculated results (otherwise it says no data is available), do the change chips against the compare-to period look plausible (an early period with a partial book will show large growth, which is expected), and does the coverage trend include every month that should exist. A missing month means an import or an ECL run was skipped.</p>',
            'routes' => ['dashboard'],
        ],
    ],

    'Documentation Maintenance' => [
        'Maintaining the manuals' => [
            'body' => '<p>The User Manual and this Administrator Manual are content in the database. Press <b>Edit manual</b> on either manual to open the authoring screen, then switch between the two manuals with the pills at the top. Chapters hold articles; an article has a body written in the rich text editor, numbered steps, figures uploaded from your computer, and page mappings that make the article appear behind the help button on those pages. Set an article to Draft to hide it from readers while it is being written. The PDF download is generated from the same rows, so the PDF and the screen never disagree.</p>',
            'steps' => [
                'Open System Documentation, User Manual or Administrator Manual, and press Edit manual.',
                'Choose the manual with the pills, then add a chapter or open an article.',
                'Edit the body, add or reorder steps, and upload figures with captions.',
                'Map the article to the pages it documents and save.',
                'Download the PDF to check the result.',
            ],
            'images' => ['help-manage' => 'The manual authoring screen'],
            'routes' => ['help.manage.index', 'help.index', 'help.admin'],
        ],
        'Refreshing screenshots' => [
            'body' => '<p>The figures in both manuals are captured from the running system by a command that signs in with a local account and photographs each configured page, so after an interface change the pictures can be refreshed in one run. The command needs the application running on a workstation, the screenshot credentials and the security-code bypass set in the local environment file (the bypass is ignored in production), and a Chromium browser. The captured files are committed with the code so a fresh installation ships with pictures.</p>',
            'steps' => [
                'On a workstation with the application running, set MANUAL_SHOT_EMAIL, MANUAL_SHOT_PASSWORD and MANUAL_SHOT_CAPTCHA in the local environment file.',
                'Run: php artisan manual:screenshots',
                'Review the refreshed images under public/manual/screenshots and commit them.',
            ],
        ],
        'Technical Manual and Installation Guide' => [
            'body' => '<p>The Technical Manual and the Installation Guide are written as chapter files in the application repository, so they are versioned with the code. They are rendered live under System Documentation with a cover page, document control, a contents rail and a PDF download, and the Technical Manual carries a schema appendix read from the connected database at render time. Developers update the chapter files in the same change as the code they describe; administrators do not edit them in the application.</p>',
            'images' => ['docs-technical' => 'The Technical Manual with its cover page and document control'],
            'routes' => ['docs.technical', 'docs.installation'],
        ],
    ],

    'Housekeeping' => [
        'Backups and restore' => [
            'body' => '<p>Backups are MAIIC\'s responsibility under the agreement: a nightly database dump and a copy of the environment file, uploaded files and manual figures, retained for at least thirty days and copied off the server. The Installation Guide provides the backup script, the restore procedure (including the foreign key and packet settings a restore needs) and a twice-yearly recovery test. Confirm with the server administrator that the backup job ran and that the newest file is present before any large import or upgrade.</p>',
        ],
        'Routine checks' => [
            'body' => '<p>Weekly: confirm the queue worker is running (an import that stays pending is the symptom when it is not), review failed jobs, check free disk space and the size of the log folder, and confirm the certificate expiry date is more than thirty days away. Monthly: reconcile the loan book period totals with the core banking control totals, review the audit trail for unexpected settings changes, and review open tickets. After every release: open the dashboard, one report PDF and one Excel export.</p>',
        ],
        'Getting help from Dupleix' => [
            'body' => '<p>Log the issue as a support ticket with the steps, time, affected period and any error text, and notify Dupleix. During the ninety-day warranty after go-live, defect correction is included. After the warranty, support is charged per hour under Schedule 8 of the agreement unless a support arrangement is signed. Response targets are one business day for a critical issue (system down or blocking a regulatory report), two for a high-impact issue with a workaround, and five for medium issues and general queries.</p>',
            'routes' => ['tickets.index'],
        ],
    ],

];
