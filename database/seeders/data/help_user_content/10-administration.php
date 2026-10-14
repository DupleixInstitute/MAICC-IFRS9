<?php

/**
 * User Manual chapter: Administration overview for users (Ticket #011).
 * Procedures live in the Administrator Manual; these articles say what each
 * screen is and when a business user needs an administrator.
 */
return [

    'Administration (overview for users)' => [

        'Administration and support at a glance' => [
            'body' => '<p>The last block of the sidebar, <b>Administration &amp; support</b> (violet heading, crossed-tools icon), holds the screens that govern and look after the platform rather than measure the book. Most business users open only a few of them: the Governance Centre to propose or approve a setting, the Audit tabs to evidence a control, and Support Tickets. This chapter says what each screen is and when you need an administrator; the procedures are in the <b>Administrator Manual</b>.</p>'
                . '<h4>What you see on the screen</h4><p>The block has three groups, each folding like any other group:</p><ul>'
                . '<li><b>Governance</b> (shield icon on the icon rail): <b>Governance Centre</b>, the maker-checker register of every governed calculation setting (shown only to users with the <code>eir.govern</code> permission), and <b>Audit</b>, one entry with three tabs: <b>Audit Trail</b>, <b>Audit Trace</b> and <b>Compliance Audits</b>.</li>'
                . '<li><b>Access &amp; Setup</b> (user-with-padlock icon): <b>Users &amp; Roles</b> with the tabs <b>Users</b> and <b>Roles &amp; Permissions</b>; <b>Financial Periods</b>; <b>Settings</b>.</li>'
                . '<li><b>Support &amp; Maintenance</b> (wrench icon): <b>Support Tickets</b> (needs <code>tickets.index</code>), <b>Maintenance</b> (needs <code>settings</code>) and <b>System Documentation</b> with the <b>User Manual</b>, <b>Administrator Manual</b>, <b>Technical Manual</b> and <b>Installation Guide</b>.</li></ul>'
                . '<h4>Each screen in one line</h4><ul>'
                . '<li><b>Governance Centre</b>: every calculation setting the engines use, with its value in force, its options, who proposed and who approved it and from when. Badges at the top count the settings, those in force, those <b>awaiting approval</b>, those <b>approved from a later date</b> and those <b>with no approved value</b>. Two views: <b>Settings by area</b> and <b>Needs attention</b>. Open a setting and press <b>Propose a change</b>; a different person presses <b>Approve</b> (greyed for the proposer, hover text <b>The person who proposed a change cannot approve it</b>). A setting marked <b>recorded, not yet applied</b> holds a value the engines do not read yet. Described in full in the EIR chapter.</li>'
                . '<li><b>Audit Trail</b>, <b>Audit Trace</b>, <b>Compliance Audits</b>: see their articles below.</li>'
                . '<li><b>Users &amp; Roles</b>: who can sign in, and what each role may see and do.</li>'
                . '<li><b>Financial Periods</b>: the accounting periods; one is open at a time and closing it opens the next.</li>'
                . '<li><b>Settings</b>: organisation details and logos, the reporting currency, the system email and the staging basis.</li>'
                . '<li><b>Support Tickets</b>: requests and issues shared with the developer (see the Support tickets article in chapter 2).</li>'
                . '<li><b>Maintenance</b>: runs the month-end, data and check jobs from the browser with live output.</li>'
                . '<li><b>System Documentation</b>: the four manuals (see Getting help).</li></ul>'
                . '<h4>Locking a month</h4><p>A reporting period is locked, and unlocked with a reason, on <b>Imports &amp; Feeds</b>, <b>Periods Built</b> (padlock button, <code>eir.govern</code> permission), not on Financial Periods. A locked month is never restated: the loan book build, staging, the PD, the LGD, the forward-looking route, the ECL, the revenue roll-forward and write-offs all refuse to write it.</p>'
                . '<h4>Common problems</h4><ul><li><b>A screen in this block answers No access rights.</b> Audit Trail and Settings need the <code>settings</code> permission even though their entries are shown to everyone; ask an administrator.</li><li><b>I cannot see the Governance Centre.</b> It needs the <code>eir.govern</code> permission.</li></ul>',
            'steps' => [
                'Unfold Administration & support in the sidebar.',
                'Open Governance for the Governance Centre and the three Audit tabs.',
                'Open Access & Setup for Users & Roles, Financial Periods and Settings.',
                'Open Support & Maintenance for Support Tickets, Maintenance and the manuals.',
                'For any procedure on these screens, read the Administrator Manual.',
            ],
            'images' => [
                'sidebar-admin' => 'The Administration & support block unfolded: Governance, Access & Setup and Support & Maintenance with their entries',
            ],
        ],

        'Users and roles' => [
            'body' => '<p><b>Users &amp; Roles</b> (Administration &amp; support, Access &amp; Setup) controls who can sign in and what each person may do. It has two tabs, <b>Users</b> and <b>Roles &amp; Permissions</b>. Most users never change anything here; this article explains what the screens hold so you know what to ask for.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li><b>Users</b> (needs <code>users.index</code>): <b>Who can sign in to the system, and the role that sets what each person can do</b>. A search box <b>Search name or email</b>, a drop-down <b>All roles</b>, <b>Clear</b>, and a count such as <b>12 users</b>. Columns: <b>Name</b> (with the photo), <b>Email</b>, <b>Mobile</b>, <b>Role</b> (a green badge per role, or grey <b>No role</b>) and <b>Actions</b>. <b>Add user</b> (green, plus icon) sits above the table. Empty: <b>No users match. Clear the search, or add a user.</b></li>'
                . '<li><b>Roles &amp; Permissions</b> (needs <code>users.roles.index</code>): <b>Each role is a set of permissions; give a user a role to decide what they can see and do</b>. A search box <b>Search role</b>, <b>Clear</b> and a count. Columns: <b>Role</b> (display name, with its system name in grey), <b>Type</b> (gold <b>Built in</b> or grey <b>Custom</b>), <b>Permissions</b> (how many), <b>Users</b> (how many), <b>Group email</b>, <b>Email everyone in the role</b> (<b>Yes</b> or <b>No</b>) and <b>Actions</b>. <b>Add role</b> sits above the table. Opening a role shows its permissions grouped by module, each named like <code>tickets.update</code>.</li></ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Green eye</b>: view the user or role. <b>Gold pencil</b>: edit (needs <code>users.update</code> or <code>users.roles.update</code>). <b>Red bin</b>: remove (needs <code>users.destroy</code> or <code>users.roles.destroy</code>; a built-in role has no bin). A confirmation dialogue always comes first.</li></ul>'
                . '<h4>What this means for you</h4><ul><li>If a sidebar entry or a button is missing, your role does not carry that permission. Ask an administrator rather than assuming the feature is absent.</li><li>A permission granted to a role applies to everyone holding the role from their next page.</li><li>Two people are needed for many changes regardless of role: whoever proposes a governed change, a fee classification, an EIR approval, a write-off, a scenario set, an overlay or a loan book build cannot approve it.</li><li>An account an administrator has marked inactive can still type its password, but every page then answers <b>Your account is inactive, contact the administrator</b>. Deleting an account removes it outright, so administrators withdraw access by marking the account inactive rather than deleting it.</li></ul>'
                . '<p>Creating users, building roles and granting permissions are described in the <b>Administrator Manual</b> under Users and Access.</p>',
            'steps' => [
                'Open Administration & support, Access & Setup, Users & Roles.',
                'On the Users tab, search for a person to see their roles.',
                'On the Roles & Permissions tab, open a role to see what it may do.',
                'Ask an administrator to change a role or grant a permission.',
            ],
            'images' => [
                'users' => 'The Users tab: search, role filter, and each user with their roles',
                'roles' => 'The Roles & Permissions tab: each role with its type, permission and user counts',
            ],
            'routes' => ['users.index', 'users.roles.index'],
        ],

        'Financial periods' => [
            'body' => '<p><b>Financial Periods</b> (Administration &amp; support, Access &amp; Setup) holds the accounting periods the organisation posts into: <b>The accounting periods the system posts into; one is open at a time and closing it opens the next</b>. Viewing needs <code>accounting.financial_periods.index</code>.</p>'
                . '<h4>What you see on the screen</h4><ul><li>A search box <b>Search period</b>, <b>Clear</b> and a count such as <b>3 periods</b>; <b>Add period</b> (green, plus icon; needs <code>accounting.financial_periods.create</code>) above the table.</li><li>Columns: <b>Name</b> (with any description), <b>Start date</b>, <b>End date</b>, <b>Status</b> (green <b>Open</b> or grey <b>Closed</b>), <b>Closed by</b> and <b>Actions</b>.</li><li>Empty: <b>No financial periods yet</b> with <b>Add the first period with Add period; from then on, closing a period opens the next one.</b></li></ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Gold pencil</b>: edit the period (needs <code>accounting.financial_periods.update</code>).</li><li><b>Grey padlock</b> (<b>Close this period and open the next</b>, open periods only): asks <b>Close this period?</b> with <b>The period is closed with today as its end date, and a new period opens from today.</b>; press <b>Close period</b>. There is no reopen button: a closed financial period stays closed.</li><li><b>Red bin</b>: <b>Delete this financial period?</b> with <b>The period record is removed. This cannot be undone.</b> (needs <code>accounting.financial_periods.destroy</code>).</li></ul>'
                . '<h4>Why it matters to you</h4><ul><li>A financial period is an accounting year or period. It is not a <b>reporting period</b>, the month-end loan book every screen is scoped to (for example <code>2025-11</code>).</li><li>Locking a reporting period, so nothing restates it, is done on <b>Imports &amp; Feeds</b>, <b>Periods Built</b>, with a reason; unlocking also needs a reason. If an engine refuses to write a month with <b>Reporting period 2025-11 is locked</b>, that is the lock to look at.</li></ul>'
                . '<p>Creating and closing financial periods, and locking reporting periods, are described in the <b>Administrator Manual</b> under Periods and Close.</p>',
            'images' => [
                'financial-periods' => 'Financial Periods: each period with its dates, status and who closed it',
            ],
            'routes' => ['accounting.financial_periods.index'],
        ],

        'Audit trail' => [
            'body' => '<p>The <b>Audit Trail</b> tab (Administration &amp; support, Governance, Audit) is one timeline of everything people did in the platform: <b>Every recorded change, module action and export in the system, newest first, with who made it</b>. It merges the record-change log and the module log (imports, settings, the EIR, FLI and governance actions, exports). Viewing needs the <code>settings</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul><li>A filter row: <b>Search action or record</b>, <b>All sources</b> (<b>Record changes</b> or <b>Module actions and exports</b>), <b>All users</b>, <b>From</b> and <b>To</b> dates, <b>Clear</b>, and on the right the counts, for example <b>12,345 record changes</b> and <b>6,789 module actions</b>.</li><li><b>Export CSV</b> (download icon) above the table: the entries matching the filters as a CSV file.</li><li>Columns: <b>When</b>, <b>User</b>, <b>Action</b>, <b>Record</b> (the kind of record and its number), <b>Source</b> (a badge: green for a record change, gold for a module action, grey for an export) and <b>Details</b>.</li><li>Empty: <b>No audit entries match these filters. Widen the dates or clear the filters.</b></li></ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Green eye</b> in Details (<b>Show the details</b>): opens a panel under the row with every field the entry recorded (old and new values, period, reason). It becomes a minus sign (<b>Hide the details</b>).</li><li>Pages: <b>Previous</b>, the page numbers and <b>Next</b>, then for example <b>1 to 15 of 2,400</b>.</li></ul>'
                . '<h4>When you would use it</h4><ul><li>To answer who changed a setting, who approved a build or an overlay, when an import ran and what it produced.</li><li>To evidence a control to an auditor, for example that the person who approved an EIR was not the person who calculated it.</li></ul>'
                . '<p>Entries cannot be edited or deleted. Filtering and interpretation are covered in the <b>Administrator Manual</b> under Monitoring and Support.</p>',
            'images' => [
                'audit-trail' => 'The Audit Trail: filters, the two log counts, Export CSV and the entries with their source badges',
            ],
            'routes' => ['audit-trail.index'],
        ],

        'Audit trace' => [
            'body' => '<p>The <b>Audit Trace</b> tab (Administration &amp; support, Governance, Audit) follows one contract from start to finish: <b>Pick a contract to see its full history, oldest to newest, and the settings each of its months was calculated under</b>. Any signed-in user can open it.</p>'
                . '<h4>What you see on the screen</h4><ul><li>A search panel: <b>Contract number</b> (prompt <b>Type or pick a contract number, e.g. 104420000005</b>; it suggests contracts and customer names as you type), the green <b>Trace</b> button and a count of the contracts on file.</li><li>Before a trace: <b>Trace one contract from start to finish</b>, explaining the three parts you will see: <b>Contract details</b>, <b>Timeline of events</b> and <b>Month by month</b>.</li><li>After a trace: the heading <b>Contract</b> and its number; the contract details (customer, product, GL account, origination date, EIR and approval status); the timeline of every schedule version, rate reset, fee, EIR calculation, monthly roll-forward and logged user action, oldest first; and each month with the settings it ran under and whether the month is locked. <b>New trace</b> above the page starts again.</li><li>If nothing is held: <b>Nothing recorded for</b> and the contract number.</li></ul>'
                . '<h4>To trace a contract</h4><ol><li>Open the <b>Audit Trace</b> tab.</li><li>Type or pick the contract number.</li><li>Press <b>Trace</b>.</li></ol>'
                . '<p>The trace only reads; it changes nothing.</p>',
            'images' => [
                'audit-trace' => 'The Audit Trace of one contract: details, timeline of events and month by month',
            ],
            'routes' => ['audit-trace.index'],
        ],

        'Compliance audits' => [
            'body' => '<p>The <b>Compliance Audits</b> tab (Administration &amp; support, Governance, Audit) is the register of compliance workbooks: <b>One workbook per standard or directive, each section checked against what the system does, with reviewer sign-off</b>. Any signed-in user can read it; reloading, signing and approving need the <code>eir.govern</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul><li>Tiles: <b>Workbooks</b>, <b>Sections checked</b>, <b>Done</b>, <b>Outstanding</b> and <b>Open findings</b>.</li><li>Columns: <b>Workbook</b> (a link with its full title), <b>Reviewer</b>, <b>Loaded</b>, <b>Sections</b>, <b>Status</b> (a badge per status with its count), <b>Open findings</b> (gold when above zero), <b>Downloads</b> (a grey button per file type, for example <b>xlsx</b>, titled <b>Download the XLSX file</b>) and <b>Open</b> (green eye, <b>Open the workbook</b>).</li><li><b>Reload from the workbooks</b> (circular arrow; <code>eir.govern</code>) above the table reads the workbooks again.</li><li>Empty: <b>No compliance workbooks loaded yet</b>.</li></ul>'
                . '<p>Opening a workbook lists each section of the standard, what the system does about it, where to see it and who signed it off; signing and approving a section are two separate people\'s steps. Procedures are in the <b>Administrator Manual</b>.</p>',
            'images' => [
                'compliance-audits' => 'Compliance Audits: the workbooks with their status counts, open findings and downloads',
            ],
            'routes' => ['compliance-audits.index'],
        ],

        'Settings and reference data' => [
            'body' => '<p><b>Settings</b> (Administration &amp; support, Access &amp; Setup) holds the organisation-level configuration: <b>Organisation details, the reporting currency, system email and the staging basis the IFRS 9 engine uses.</b> It needs the <code>settings</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul><li>Three tiles: <b>Organisation</b>, <b>Reporting currency</b> and <b>Days past due counted from</b>.</li><li><b>Governance Centre</b> (gavel icon) above the tabs, for users with <code>eir.govern</code>.</li><li>Tabs: <b>Organisation</b> (name, contact details and logos shown in the app and on reports), <b>Reporting</b> (the currency amounts are labelled in, and where the financial periods are kept), <b>Email</b> (the mail server and sender used for password resets and new-user login details), <b>Staging basis</b> (how days past due are counted and the thresholds that set each stage; thresholds change here under maker-checker) and <b>More settings</b> (links to LGD payment tracking, Currencies, Financial periods, Page help text, Licence and the Governance Centre).</li></ul>'
                . '<h4>What it means for you</h4><ul><li>The organisation name and logo on the sidebar, the reports and the manual covers come from <b>Organisation</b>. If a report is branded wrongly, that is where it is fixed.</li><li>The reporting currency, for example MWK, labels every amount on the Dashboard and in the reports.</li><li>A staging threshold change is proposed by one person and approved by another; the Workspace step Stage allocation opens this tab.</li></ul>'
                . '<p>Every change is written to the audit trail. Procedures are in the <b>Administrator Manual</b> under Organisation Settings.</p>',
            'images' => [
                'settings' => 'Settings: the three tiles and the Organisation, Reporting, Email, Staging basis and More settings tabs',
            ],
            'routes' => ['settings.index', 'currencies.index', 'chart_of_accounts.index', 'branches.index'],
        ],

        'Maintenance' => [
            'body' => '<p><b>Maintenance</b> (Administration &amp; support, Support &amp; Maintenance) runs the platform\'s jobs from the browser: <b>Run the system\'s setup, month-end, import and check commands from here, with live output. Every run is audit logged.</b> Viewing needs the <code>settings</code> permission; running a job needs the administrator role.</p>'
                . '<h4>What you see on the screen</h4><ul><li>Tiles: <b>Database</b>, <b>Loan book months</b> (with the latest month), <b>Months with ECL</b> and <b>Now running</b> (red when a job runs, otherwise <b>Nothing</b>).</li><li>Tabs: <b>Month-end</b> (<b>Build the loan book</b>, <b>Run the month-end calculations</b>, <b>Recalibrate the forward-looking model</b>), <b>Data and checks</b> (<b>Fetch new data</b>, <b>Check the system</b>), <b>Install and update</b> (<b>Bootstrap the system (first install)</b>, <b>Update the system after a new release</b>) and <b>Run history</b>.</li><li>A <b>Month</b> drop-down for the month-end jobs, and a card per job with its description and the command it runs.</li></ul>'
                . '<h4>Buttons and icons</h4><ul><li>Each job\'s run button asks for confirmation first. <b>Run the month-end calculations</b> runs, in order and stopping at the first failure: clients for new loan-book customers, scheme settings, programme portfolios, restructure candidates and rate moves (detection only), staging, PD and LGD by segment, the forward-looking route with the ECL and the month record, ledger cash, GL fees, EIR revenue and the Mega Farm programme.</li><li>The bootstrap job wipes the database: it asks you to type <b>BOOTSTRAP</b> to enable it and then confirms with <b>Wipe and rebuild this database?</b>.</li><li><b>Run history</b> lists past runs; the green eye (<b>View the output</b>) shows a run\'s output.</li></ul>'
                . '<p>The Workspace checklist turns green by itself as the month-end job saves its results. Procedures are in the <b>Administrator Manual</b>.</p>',
            'images' => [
                'maintenance' => 'Maintenance: status tiles, the Month-end jobs and the run history',
            ],
            'routes' => ['maintenance.index'],
        ],

        'Licence' => [
            'body' => '<p><b>Licence</b> (Settings, More settings, Licence) holds the licence record for this installation: <b>The licence key for this installation and what it covers</b>. It needs the <code>settings</code> permission. The page shows <b>Licensed To</b>, <b>Product</b>, <b>Package</b>, <b>Start Date</b> and <b>Expires</b> with a green <b>Active</b> or red <b>Expired</b> tag, and beside them a <b>License Key</b> box with a <b>Save</b> button. With no key held it reads <b>No licence key is held for this installation. Enter the key you were given.</b></p><p>Under the implementation agreement MAIIC owns the installed system, its source code and its data in perpetuity. The licence record identifies the installation for support purposes; it is not a switch that turns the platform off, and an expired record does not stop you working.</p>'
                . '<h4>What the fields mean</h4><ul><li><b>Licensed To</b>: the organisation the installation belongs to, which should read MAIIC.</li><li><b>Product</b> and <b>Package</b>: which Dupleix platform and which commercial option is in force.</li><li><b>Start Date</b> and <b>Expires</b>: the support window.</li></ul>'
                . '<h4>When you would look at it</h4><ul><li>Before raising a support request, so you can quote the installation details.</li><li>When an auditor asks what the organisation is entitled to run and who supports it.</li></ul><p>If the details are wrong or the record is missing, raise a support ticket. Maintaining it is an administrator task, described in the <b>Administrator Manual</b>.</p>',
            'images' => [
                'license' => 'The licence record',
            ],
            'routes' => ['license.index'],
        ],

    ],

];
