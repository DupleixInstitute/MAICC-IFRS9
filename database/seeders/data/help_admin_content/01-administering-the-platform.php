<?php

/**
 * Administrator Manual chapter 1 (Ticket #011 depth rewrite).
 * Loaded by HelpAdminContentSeeder from database/seeders/data/help_admin_content.
 */
return [

    'Administering the Platform' => [

        'Role of the administrator' => [
            'body' => '<p>The administrator owns everything that is not a calculation. Analysts run the IFRS 9 workflow described in the User Manual; you keep the environment in which they do it correct, controlled and auditable. That covers six things: who may sign in and what they may do, the organisation settings and reference data every screen reads, the periods that scope the figures, the governance of imports and locked results, the monitoring trail, and the documentation.</p><h4>What you are accountable for</h4><ul><li><b>Access.</b> Every account, every role and every permission. If someone can see a figure they should not, or cannot do work they should, that is an access question.</li><li><b>Configuration.</b> The organisation name, logo and currency that appear on every report and every manual cover; the reference data (currencies, chart of accounts, branches, sector types) that the reports group by.</li><li><b>Periods.</b> Creating the financial year, closing it after sign-off, and running the period close checklist in the Workspace.</li><li><b>Data governance.</b> Approving the things the platform deliberately refuses to decide for itself: fee rules, fee classifications, original schedules and effective interest rates. Each is a two-person control.</li><li><b>Evidence.</b> The audit trail, the support ticket record and the documentation, which together are what an auditor examines when they ask how a figure was controlled.</li></ul><h4>What you are not accountable for</h4><p>The platform is a calculation and reporting tool. MAIIC owns its accounting policies, IFRS 9 judgements, model methodology, assumptions, scenarios and overlays, and the figures it adopts in its financial statements and regulatory returns. Approving a rule in this platform records a decision; it does not make the decision for you.</p><h4>Where the work happens</h4><p>Most of it is under the <b>Administration</b> group in the sidebar, with the <b>Settings</b> hub inside it. The governance approvals sit on the working screens themselves: the EIR pages under <b>EIR and Revenue Recognition</b>, and the lock actions on the transition matrix and loss given default screens. Server-side work, backups, the queue worker, TLS certificates and upgrades, is in the <b>Installation and Configuration Guide</b>. How the engines calculate is in the <b>Technical Manual</b>.</p><h4>A month in the life</h4><ol><li>Confirm the loan book for the month is imported and the import shows no unexplained failures.</li><li>Watch the Workspace checklist fill as the analysts work through staging, PD, LGD, the forward-looking adjustment and the ECL run.</li><li>Approve what needs a second pair of eyes: fee classifications, schedules, effective interest rates.</li><li>Review the audit trail for anything unexpected.</li><li>When the reports are reviewed and signed off, tick the manual steps in the Workspace and close the financial period.</li></ol>',
            'steps' => [
                'Each month, open the Workspace and select the reporting period being closed.',
                'Check Imports for the period: records loaded, failures explained.',
                'Work through the approvals waiting for you on the EIR screens.',
                'Review the Audit Trail for settings or permission changes you did not expect.',
                'Tick the manual checklist steps once the reports have been reviewed, then close the financial period.',
            ],
            'images' => [
                'dashboard' => 'The dashboard, where an administrator confirms the latest period has calculated results',
                'workspace' => 'The period close checklist, which shows how far the month has progressed',
            ],
            'routes' => ['dashboard', 'workspace.index'],
        ],

        'Administration menu map' => [
            'body' => '<p>The <b>Administration</b> group holds six items. Each opens a list page using the standard controls: a filter bar, a table, and row actions shown as a green eye to view, a gold pencil to edit and a red bin to delete, with amber chips for state changes such as Close.</p><ul><li><b>User Management</b>. Accounts, their roles and their active status. Columns: Name, Email, Mobile, Gender, Role, Actions.</li><li><b>Roles and Permissions</b>. The roles and the permission matrix behind each. Columns: Name, System, Group Email, Send Group Email To All In Role?, Actions.</li><li><b>Financial Periods</b>. Accounting periods and their open or closed state. Columns: ID, Name, Date Created, Date Closed, Active, Actions.</li><li><b>Audit Trail</b>. The unified timeline over both audit stores, with filters for search, source, user and date range.</li><li><b>Support Tickets</b>. The agreed record of enhancements, issues and changes between MAIIC and Dupleix.</li><li><b>Settings</b>. The configuration hub, described in its own chapter.</li></ul><h4>Above Administration</h4><p><b>System Documentation</b> holds the four contractual documents: the <b>User Manual</b> and this <b>Administrator Manual</b>, which are content in the database and editable in the application, and the <b>Technical Manual</b> and <b>Installation Guide</b>, which are versioned with the source code and maintained by developers.</p><h4>Approvals that live outside Administration</h4><ul><li><b>EIR and Revenue Recognition</b>: Accounting Rules (approve a rule), Fee Classification (review a classification), EIR Data then Schedules (approve a schedule), EIR Calculations (approve and lock a rate, or reopen one).</li><li><b>IFRS 9 Model Setup</b>: locking a transition matrix or a loss given default run, and applying a matrix to the loan book.</li><li><b>Workspace</b>: ticking the manual close steps.</li></ul><h4>Common problems</h4><ul><li><b>A menu item is missing for you.</b> Administration items are permission-gated like everything else. Check your own role carries the permission.</li><li><b>A permission-does-not-exist error on a typed URL.</b> Some routes left over from the credit-scoring era reference permissions that were deleted. They are not reachable from the menu and their removal is tracked as a ticket.</li></ul>',
            'images' => [
                'users' => 'The Administration group expanded in the sidebar, with User Management open',
            ],
            'routes' => ['users.index', 'users.roles.index', 'accounting.financial_periods.index', 'audit-trail.index', 'tickets.index', 'settings.index'],
        ],

    ],

];
