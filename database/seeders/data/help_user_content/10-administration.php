<?php

/**
 * User Manual chapter: Administration overview for users (Ticket #011).
 * Procedures live in the Administrator Manual; these articles say what each
 * screen is and when a business user needs an administrator.
 */
return [

    'Administration (overview for users)' => [

        'Users and roles' => [
            'body' => '<p>Two screens under <b>Administration</b> control who can sign in and what they may do. Most users never open them; this article explains what they hold so you know what to ask for.</p><h4>User Management</h4><p>Lists every account with the columns <b>Name</b>, <b>Email</b>, <b>Mobile</b>, <b>Gender</b>, <b>Role</b> and <b>Actions</b>. An account must be active to sign in: an inactive account is refused at the login page even with the right password, which is how access is withdrawn without deleting anyone\'s history from the audit trail.</p><h4>Roles and Permissions</h4><p>Lists the roles with the columns <b>Name</b>, <b>System</b>, <b>Group Email</b>, <b>Send Group Email To All In Role?</b> and <b>Actions</b>. Opening a role shows a matrix of permissions grouped by module, each named in the form <code>module.action</code>, for example <code>tickets.update</code>. Ticking a permission grants it to everyone holding that role from their next page load.</p><h4>What this means for you</h4><ul><li>If a menu item is missing, your role does not carry that permission. Ask an administrator rather than assuming the feature is absent.</li><li>If you open a page you are not entitled to, the platform shows a plain modal explaining that you do not have rights, rather than failing silently.</li><li>Two controls apply to everyone regardless of role. A fee classification cannot be reviewed by the person who classified it, and an effective interest rate cannot be approved by the person who calculated it. Only an administrator can override those, and every override is recorded.</li></ul><p>Procedures for creating users, building roles and granting permissions are in the <b>Administrator Manual</b> under Users and Access.</p>',
            'images' => [
                'users' => 'User Management with roles and active status',
                'roles' => 'The permission matrix grouped by module',
            ],
            'routes' => ['users.index', 'users.roles.index'],
        ],

        'Financial periods' => [
            'body' => '<p><b>Administration</b>, then <b>Financial Periods</b>, holds the accounting periods the organisation reports on. The table shows <b>ID</b>, <b>Name</b>, <b>Date Created</b>, <b>Date Closed</b>, <b>Active</b> and <b>Actions</b>, with a Close action shown as an amber chip beside the row icons.</p><h4>Why it matters to you</h4><ul><li>A financial period is the accounting year or period the organisation reports on. It is not the same thing as a <b>reporting period</b>, which is the month-end loan book snapshot written as a year and month, for example 2025-11, that every screen in the platform is scoped to.</li><li>Closing a financial period locks it so that figures attributed to it are not changed after sign-off. If you find you cannot post or recalculate into a period, check whether it has been closed.</li></ul><p>Creating and closing periods is an administrator task, described in the <b>Administrator Manual</b> under Periods and Close.</p>',
            'images' => [
                'financial-periods' => 'Financial periods with their created and closed dates',
            ],
            'routes' => ['accounting.financial_periods.index'],
        ],

        'Audit trail' => [
            'body' => '<p><b>Administration</b>, then <b>Audit Trail</b>, is one timeline of everything people did in the platform. It draws on both audit stores, the activity log written when records change and the module audit log written by imports, settings changes and the EIR actions, and presents them together.</p><h4>What you see on the screen</h4><ul><li>A count at the top showing how many activity entries and how many module audit entries exist.</li><li>Filters: a search box with the placeholder <b>Action or entity...</b>, an <b>All sources</b> selector, an <b>All users</b> selector, and <b>From</b> and <b>To</b> date boxes.</li><li>A table with the columns <b>When</b>, <b>User</b>, <b>Action</b>, <b>Entity</b>, <b>Source</b> and <b>Details</b>. The Source column carries a badge saying which store the entry came from, and <b>View</b> in the Details column opens the full record of what changed.</li></ul><h4>When you would use it</h4><ul><li>To answer who changed a setting, who approved a rule, when an import ran and what it produced.</li><li>To evidence a control to an auditor, for example that the person who approved an effective interest rate was not the person who calculated it.</li></ul><p>Entries cannot be edited or deleted from the screen. Filtering and interpretation are covered in the <b>Administrator Manual</b> under Monitoring and Support.</p>',
            'images' => [
                'audit-trail' => 'The unified audit trail with its filters and the detail viewer',
            ],
            'routes' => ['audit-trail.index'],
        ],

        'Settings and reference data' => [
            'body' => '<p><b>Administration</b>, then <b>Settings</b>, is the hub for organisation-level configuration. Its sections are <b>Organisation</b> (name, logo, address and contacts, plus links to the reference data), <b>General</b> and <b>System</b> (preferences such as timezone and formatting), <b>Email</b> (the mail server used for password resets and notifications), <b>SMS</b> and <b>Loan bands</b> (left over from the loan-origination template and not used by the IFRS 9 workflow), and <b>Licence</b>.</p><h4>Reference data you will notice as a user</h4><ul><li><b>Currencies</b>. The organisation currency, MWK, is what labels every figure on the dashboard and in the reports.</li><li><b>Chart of Accounts</b> and <b>Branches</b>. Organisation structure used for grouping.</li><li><b>Sector Types</b>. The economic sectors behind the sector concentration reports.</li><li><b>Legal Types</b> and <b>Banks</b>. Supporting lists used on client records.</li></ul><p>The organisation name and logo you see on report headers and on the manual covers come from Settings, Organisation. If a report is branded wrongly, that is where it is fixed. All settings changes require the settings permission and are written to the audit trail. Procedures are in the <b>Administrator Manual</b> under Organisation Settings.</p>',
            'images' => [
                'settings' => 'The Settings hub',
            ],
            'routes' => ['settings.index', 'currencies.index', 'chart_of_accounts.index', 'branches.index', 'industry_types.index'],
        ],

        'Licence' => [
            'body' => '<p><b>Settings</b>, then <b>Licence</b>, holds the licence record for this installation. The page shows <b>Licensed To</b>, <b>Product</b>, <b>Package</b>, <b>Start Date</b> and <b>Expires</b>, with an action to verify the record.</p><p>Under the implementation agreement MAIIC owns the installed system, its source code and its data in perpetuity. The licence record identifies the installation for support purposes; it is not a switch that turns the platform off, and an expired record does not stop you working.</p><h4>What the fields mean</h4><ul><li><b>Licensed To</b>. The organisation the installation belongs to, which should read MAIIC.</li><li><b>Product</b> and <b>Package</b>. Which Dupleix platform and which commercial option is in force. MAIIC holds the outright purchase option rather than the monthly subscription.</li><li><b>Start Date</b> and <b>Expires</b>. The support window. The ninety-day warranty runs from go-live; after that, support is arranged separately.</li></ul><h4>When you would look at it</h4><ul><li>Before raising a support request, so you can quote the installation details.</li><li>When an auditor asks what the organisation is entitled to run and who supports it.</li></ul><p>If the details are wrong or the record is missing, raise a support ticket. Maintaining it is an administrator task, described in the <b>Administrator Manual</b>.</p>',
            'images' => [
                'license' => 'The licence record',
            ],
            'routes' => ['license.index'],
        ],

    ],

];
