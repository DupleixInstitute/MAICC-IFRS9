<?php

/**
 * Administrator Manual chapter 2 (Ticket #011 depth rewrite).
 */
return [

    'Users and Access' => [

        'User accounts' => [
            'body' => '<p><b>Administration</b>, then <b>User Management</b>, is where accounts are created, amended and withdrawn. Open it from the sidebar.</p><h4>What you see on the screen</h4><ul><li>A search filter above the table.</li><li>The table with the columns <b>Name</b>, <b>Email</b>, <b>Mobile</b>, <b>Gender</b>, <b>Role</b> and <b>Actions</b>.</li><li>Row actions: a green eye to view the account, a gold pencil to edit it and a red bin to delete it. Deleting asks for confirmation and names the account.</li><li>A button to create a new user.</li></ul><h4>Field by field on the user form</h4><ul><li><b>Name</b>. The person\'s full name as it should appear in the audit trail and on generated reports.</li><li><b>Email</b>. The sign-in identifier and where password resets go. It must be unique.</li><li><b>Password</b> and <b>Confirm Password</b>. An initial password the user changes at first sign-in.</li><li><b>Roles</b>. One or more roles. This is what decides what the person can see and do; an account with no role can sign in and see almost nothing.</li><li><b>Branch</b>. The organisational branch, used for grouping.</li><li><b>Mobile</b>, <b>Tel</b>, <b>Zip</b>, <b>Gender</b>, <b>External ID</b>. Contact and reference details.</li><li><b>Group Email</b>. Used by the group-email feature on roles.</li><li><b>Photo</b>. Optional profile picture shown on the avatar.</li></ul><h4>Active and inactive</h4><p>An account must be <b>active</b> to sign in. An inactive account is refused at the login page even with the correct password, and the person sees the ordinary failure message. This is the right way to withdraw access: the account, and every audit entry attached to it, is preserved, so historical evidence still names a real person. Delete an account only when it was created in error.</p><h4>What happens next</h4><ul><li>Role changes take effect on the user\'s next request; nobody needs to sign out.</li><li>Creating, editing and deleting an account are written to the audit trail with your name.</li></ul><h4>Common problems</h4><ul><li><b>A new user cannot sign in.</b> Check the account is active and carries at least one role, and that the email is the one they are typing.</li><li><b>A user sees nothing after signing in.</b> No role, or a role with no permissions.</li><li><b>You cannot delete a user.</b> Records they created may reference them. Deactivate instead; that is the intended path.</li></ul>',
            'steps' => [
                'Open Administration, then User Management.',
                'Press the button to create a new user.',
                'Enter the name and email, set an initial password and confirm it.',
                'Assign one or more roles and, if you use them, the branch and contact details.',
                'Tick Active and save.',
                'Ask the person to sign in, change their password and enable two-factor authentication.',
                'To withdraw access later, edit the account and untick Active rather than deleting it.',
            ],
            'images' => [
                'users' => 'User Management with the accounts, their roles and the row actions',
                'users-create' => 'The user form with name, email, password, roles and branch',
            ],
            'routes' => ['users.index', 'users.create'],
        ],

        'Roles and permissions' => [
            'body' => '<p><b>Administration</b>, then <b>Roles and Permissions</b>, defines what each role may do. A permission is a named right such as <code>tickets.update</code> or <code>expected-credit-loss.create</code>; a role is a bundle of them; a user holds roles.</p><h4>What you see on the screen</h4><ul><li>A search filter, which searches on the server so it works across every page of results.</li><li>The table with the columns <b>Name</b>, <b>System</b>, <b>Group Email</b>, <b>Send Group Email To All In Role?</b> and <b>Actions</b>.</li><li>Row actions to view and edit. A system role is marked in the System column and should not be renamed.</li><li>A button to create a new role.</li></ul><h4>Field by field on the role form</h4><ul><li><b>Name</b>. The internal name, lower case and without spaces, for example <code>preparer</code>.</li><li><b>Display Name</b>. What people see, for example <b>Preparer</b>.</li><li><b>Group Email</b> and <b>Send Group Email To All In Role?</b>. Optional, for notifying everyone holding the role.</li><li><b>The permission matrix</b>. Permissions grouped by module, each with a checkbox and a readable label. Tick what the role needs.</li></ul><h4>How to think about a role</h4><p>Grant the least a person needs to do their job. A useful starting set for MAIIC:</p><ul><li><b>Preparer</b>. Read everything, import loan books and collateral, run PD, LGD and ECL calculations, classify fees. No approval rights.</li><li><b>Reviewer</b>. Read everything, review fee classifications, approve schedules and lock effective interest rates. No import or calculation rights, which keeps maker and checker clean.</li><li><b>Read only</b>. Read the dashboard, the loan book and every report. Nothing else. This is the role for auditors and for board observers.</li><li><b>Administrator</b>. Everything, including settings, users and the overrides.</li></ul><p>The credit-scoring permissions inherited from the platform\'s earlier life were removed in August 2026, so the matrix now lists IFRS 9 functions only. Dedicated preparer, reviewer, approver and read-only roles are planned as a separate change; until then, build them from the matrix as above.</p><h4>What happens next</h4><p>Permissions take effect on the next request. Users already signed in do not need to sign out. Changes are written to the audit trail.</p><h4>Common problems</h4><ul><li><b>A checkbox has no label.</b> A permission is missing its display name. Report it; the seeders backfill these.</li><li><b>You granted a permission and the menu item still does not appear.</b> Confirm the user actually holds that role, and ask them to reload the page.</li><li><b>Everyone is an administrator.</b> Common on a new installation and worth fixing before go-live: build the narrower roles and move people onto them.</li></ul>',
            'steps' => [
                'Open Administration, then Roles and Permissions.',
                'Press the button to create a new role, or open an existing role with the gold pencil.',
                'Enter the name and display name.',
                'Work down the permission matrix module by module, ticking only what the role needs.',
                'Save, then assign the role to users under User Management.',
                'Sign in as a test user, or ask the holder to confirm they can reach what they need and nothing more.',
            ],
            'images' => [
                'roles' => 'The roles list with the system flag and group email settings',
                'roles-create' => 'The permission matrix grouped by module',
            ],
            'routes' => ['users.roles.index', 'users.roles.create'],
        ],

        'Login security' => [
            'body' => '<p>Three controls protect the sign-in page, and you should know how each behaves before a user reports a problem.</p><h4>The security check</h4><p>The login page shows a distorted code image and a box to type it into. The code is generated on this server, held in the session and never sent to the browser as text, so it cannot be read from the page source. It is single use, not case sensitive, and expires after a few minutes. The circular-arrows button beside the image issues a new one. No third-party service is involved, so it works with no internet connection.</p><h4>Two-factor authentication</h4><p>Users enable it themselves from <b>Profile</b>. Once on, sign-in asks for a code from their authenticator application. Recovery codes are shown once and can be regenerated. Encourage it for every account with approval rights.</p><h4>Sessions and lockout</h4><ul><li>Sessions expire after the configured lifetime. An expired session produces a plain modal explaining it, not a silent failure.</li><li>Repeated failed attempts are throttled for a short period.</li><li>Sessions are stored in the database, so a user can be signed out by clearing their sessions if that is ever needed.</li></ul><h4>Maker and checker controls</h4><p>Two rules apply to everyone, regardless of role, and are enforced by the platform rather than by policy:</p><ul><li>A fee classification cannot be reviewed by the person who classified it. The refusal reads <b>A classifier cannot review their own decision.</b></li><li>An effective interest rate cannot be approved and locked by the person who calculated it. The refusal reads <b>The calculator cannot approve and lock their own EIR.</b></li></ul><p>A user holding the administrator role can override both, and reopening a locked rate is administrator-only. Every override is written to the audit trail with a flag saying an override was used. Use them rarely, and expect to explain each one.</p><h4>Common problems</h4><ul><li><b>The code image does not appear.</b> The server component that draws it is unavailable. This is an ICT issue, covered in the Installation Guide.</li><li><b>A user is locked out after several attempts.</b> Wait a minute. If it persists, confirm the account is active and the email is correct.</li><li><b>Someone lost their two-factor device.</b> They use a recovery code. If those are gone too, an administrator disables two-factor on the account so they can sign in and set it up again.</li></ul>',
            'images' => [
                'login' => 'The sign-in page with the security check and its refresh button',
            ],
            'routes' => ['login'],
        ],

    ],

];
