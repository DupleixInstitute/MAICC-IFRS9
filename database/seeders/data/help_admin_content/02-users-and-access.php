<?php

/**
 * Administrator Manual chapter 2 (Ticket #011 depth rewrite; documentation audit 14 October 2026).
 */
return [

    'Users and Access' => [

        'User accounts' => [
            'body' => '<p><b>Users</b> is where sign-ins are created, changed and withdrawn. Open <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Users &amp; Roles</b>; the section opens on its first tab, <b>Users</b>. The breadcrumb reads Administration &amp; support &gt; Access &amp; Setup &gt; Users &amp; Roles &gt; Users. Seeing the list needs the <code>users.index</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>A filter row: a search box (<b>Search name or email</b>), a role selector (<b>All roles</b> or one role), <b>Clear</b> once a filter is set, and the number of users on the right.</li>'
                . '<li>The table with the columns <b>Name</b> (with the profile photo where one is held; the name links to the account), <b>Email</b>, <b>Mobile</b>, <b>Role</b> and <b>Actions</b>.</li>'
                . '<li>The <b>Role</b> column shows each role as a green badge; an account with no role shows a grey <b>No role</b> badge.</li>'
                . '<li>The pager under the table when there are more users than fit one page.</li>'
                . '<li>The round green <b>Help for this screen</b> button at the bottom right.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Add user</b> (top right, with a plus). Opens the new-user form. Needs <code>users.create</code>.</li>'
                . '<li>Green eye, <b>View</b>. Opens the account page.</li>'
                . '<li>Gold pencil, <b>Edit</b>. Opens the form for the account. Needs <code>users.update</code>; hidden without it.</li>'
                . '<li>Red bin, <b>Delete</b>. Asks for confirmation, naming the person, then deletes the account. Needs <code>users.destroy</code>. You cannot delete your own account, nor the last account holding the admin role.</li>'
                . '</ul>'
                . '<h4>Field by field on the user form</h4><ul>'
                . '<li><b>Full name</b>. As it should appear in the audit trail, on approvals and on reports.</li>'
                . '<li><b>Email (used to sign in)</b>. The sign-in identifier and where password resets go. It must be unique.</li>'
                . '<li><b>Gender</b> (<b>Male</b> or <b>Female</b>), <b>Mobile</b>, <b>Telephone</b>. Contact details.</li>'
                . '<li><b>Photo (optional, up to 1 MB)</b>. Shown on the avatar and in the user list.</li>'
                . '<li><b>Roles</b>. One or more roles, chosen as tags (<b>Choose one or more roles</b>). Required. This is what decides what the person can see and do.</li>'
                . '<li><b>Can hand work to other users</b>. <b>No</b> or <b>Yes</b>; a setting kept from the earlier platform that no IFRS 9 or EIR screen reads.</li>'
                . '<li><b>Password</b> and <b>Confirm password</b>. On the edit form the first reads <b>New password (leave blank to keep)</b>.</li>'
                . '<li><b>Active (can sign in)</b>. Untick to withdraw access without deleting the account.</li>'
                . '<li><b>Email the sign-in details</b> (new accounts only). Sends the person their email and the password you typed, through the mail server set on Settings, Email.</li>'
                . '<li><b>Branch</b>, <b>Group email</b>, <b>External ID</b>. Optional grouping and reference details.</li>'
                . '</ul>'
                . '<h4>To add a user</h4><ol>'
                . '<li>Press <b>Add user</b>.</li>'
                . '<li>Enter the full name and email, choose at least one role, and set a password twice.</li>'
                . '<li>Leave <b>Active (can sign in)</b> ticked; tick <b>Email the sign-in details</b> if the mail server is set up.</li>'
                . '<li>Press <b>Add user</b> at the foot of the form.</li>'
                . '</ol>'
                . '<h4>To withdraw access</h4><p>Edit the account and untick <b>Active (can sign in)</b>. The account and every audit entry naming it are kept, so historical approvals still name a real person. Delete an account only when it was created in error.</p>'
                . '<h4>What happens next</h4><ul>'
                . '<li>A role change applies on the person\'s next page; nobody needs to sign out.</li>'
                . '<li>An inactive account can still type its password, but every page then answers <b>Your account is inactive, contact the administrator</b>.</li>'
                . '<li>Creating, changing and deleting an account are recorded in the Audit Trail as record changes, with your name.</li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No users match. Clear the search, or add a user.</b> The filters exclude everyone.</li>'
                . '<li><b>You cannot delete your own account. Ask another administrator.</b></li>'
                . '<li><b>This is the last administrator. Give another user the admin role first.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A new user sees almost nothing.</b> No role, or a role with few permissions.</li>'
                . '<li><b>The sign-in details email never arrived.</b> Settings, Email is not configured, or the queue worker is not running.</li>'
                . '<li><b>A user is refused after five quick attempts.</b> Sign-in is throttled to five attempts a minute for the same email and address. Wait a minute.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Access & Setup, then Users & Roles; the Users tab opens.',
                'Press Add user.',
                'Enter the full name and email, choose at least one role, and set the password twice.',
                'Leave Active (can sign in) ticked; tick Email the sign-in details if email is set up.',
                'Press Add user to save.',
                'To withdraw access later, edit the account and untick Active (can sign in) rather than deleting it.',
            ],
            'images' => [
                'users' => 'Users, with the role badges and the row action icons',
                'users-create' => 'The Add user form',
            ],
            'routes' => ['users.index', 'users.create', 'users.show', 'users.edit'],
        ],

        'Roles and permissions' => [
            'body' => '<p><b>Roles &amp; Permissions</b> defines what each role may do. A permission is a named right such as <code>eir.govern</code>; a role is a set of them; a user holds roles. Open <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Users &amp; Roles</b>, and choose the <b>Roles &amp; Permissions</b> tab. Seeing it needs <code>users.roles.index</code>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>A search box (<b>Search role</b>) with <b>Clear</b>.</li>'
                . '<li>The table with the columns <b>Role</b> (the display name as a link, with the system name under it), <b>Type</b>, <b>Permissions</b> (how many the role holds), <b>Users</b> (how many people hold it), <b>Group email</b>, <b>Email everyone in the role</b> (<b>Yes</b> or <b>No</b>) and <b>Actions</b>.</li>'
                . '<li><b>Type</b> is a gold <b>Built in</b> badge for a system role or a grey <b>Custom</b> badge for one created here.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Add role</b> (top right). Needs <code>users.roles.create</code>.</li>'
                . '<li>Green eye, <b>View</b>. The role with its permissions, read only.</li>'
                . '<li>Gold pencil, <b>Edit</b>. Needs <code>users.roles.update</code>. The system name of a built-in role cannot be changed.</li>'
                . '<li>Red bin, <b>Delete</b>. Shown only on custom roles and with <code>users.roles.destroy</code>.</li>'
                . '</ul>'
                . '<h4>Field by field on the role form</h4><ul>'
                . '<li><b>Display name</b>. What people see, for example Finance Officer.</li>'
                . '<li><b>System name (no spaces)</b>. The internal name, for example finance_officer.</li>'
                . '<li><b>Group email (optional)</b>, <b>Email everyone in the role</b> and <b>Members can hand work to each other</b>. Settings kept from the earlier platform; no IFRS 9 or EIR screen uses them.</li>'
                . '<li><b>Permissions</b>, with the number ticked. The areas are listed on the left, each with ticked/total; choose an area and tick its permissions on the right. <b>Tick all</b> and <b>Clear all</b> act on the area shown.</li>'
                . '</ul>'
                . '<h4>The permissions that matter most</h4><ul>'
                . '<li><code>settings</code>. The broad administration right: Settings, the Audit Trail, Maintenance (to see it), the manual editor, the licence, and on the EIR side the Accounting Rules, Fee Classification (classify and review), EIR Data Intake and EIR Calculations.</li>'
                . '<li><code>eir.view</code>. The read-only EIR and feed screens: EIR Data, Coverage, EIR as at a Date, GL Reconciliation, Imports &amp; Feeds, Write-offs, Restructures.</li>'
                . '<li><code>eir.govern</code>. The Governance Centre; landing a pack and proposing or approving a loan book build; the take-on workbook upload, build and confirmation; locking and unlocking a reporting period; write-off and restructure actions; tying a held GL fee line to its loan; the staging thresholds; signing and approving Compliance Audits rows. Give it to the people who propose and approve governed changes.</li>'
                . '<li><code>eir.run</code> and <code>eir.export</code>. Running the forward-looking correlation; building and downloading the auditor\'s pack and the reconciliation exports.</li>'
                . '<li><code>eir.fees.import</code>. The fee-only import: lets Finance load fees by loan on EIR Data Intake (the fee type only) and read Fee Classification and its fee GL check, without the broad <code>settings</code> right. Classifying and reviewing still need <code>settings</code>.</li>'
                . '<li><code>macro.view</code> and <code>macro.manage</code>. The Macro Statistics screen, and committing or entering macro data there.</li>'
                . '<li><code>tickets.index</code>, <code>tickets.create</code>, <code>tickets.update</code>, <code>tickets.destroy</code>. Support Tickets.</li>'
                . '<li><code>users.*</code>, <code>users.roles.*</code>, <code>accounting.financial_periods.*</code>, <code>currencies.*</code>. The administration lists of the same names.</li>'
                . '<li>The <b>admin</b> role itself (not a permission) is checked in four places: running a Maintenance command, ticking the manual Workspace steps, unlocking a closed PD or LGD record, and the administrator override of maker-checker when that governed setting allows it.</li>'
                . '</ul>'
                . '<h4>How to think about a role</h4><p>Grant the least a person needs. Keep maker and checker apart by people, not by roles: the platform refuses the person who proposed, classified, calculated or signed an item as its approver, so two people holding the same role still give a clean two-person control. A read-only role for auditors and board observers needs <code>eir.view</code>, <code>reports.ifrs9</code> and the view permissions of the screens they read, and nothing that writes.</p>'
                . '<h4>What happens next</h4><p>Permissions apply on the next page; nobody needs to sign out. Role and permission changes are not written to the Audit Trail in this version, so record each change, with who asked for it, in a support ticket.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No roles match. Clear the search, or add a role.</b></li>'
                . '<li><b>The role is held by N user(s). Move them to another role before deleting it.</b></li>'
                . '<li><b>Cannot delete system role.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A permission you removed came back.</b> The update job (<code>maiic:task update</code>, on Maintenance as Update the system after a new release) runs the permission seeders, and they grant the EIR, macro, financial period, currency and ticket permissions to every staff role. Review the roles after every update.</li>'
                . '<li><b>You granted a permission and the menu entry still does not show.</b> Confirm the person holds that role, and ask them to reload the page.</li>'
                . '<li><b>A checkbox shows a code rather than a label.</b> The permission has no display name. Raise a ticket.</li>'
                . '</ul>',
            'steps' => [
                'Open Users & Roles and choose the Roles & Permissions tab.',
                'Press Add role, or the gold pencil on an existing role.',
                'Enter the display name and the system name.',
                'Work through the permission areas on the left, ticking only what the role needs.',
                'Save, then give the role to people on the Users tab.',
                'After every system update, check the roles again: the update re-grants the EIR and ticket permissions.',
            ],
            'images' => [
                'roles' => 'Roles & Permissions with the type, permission and user counts',
                'roles-create' => 'The permission picker: areas on the left, permissions on the right',
            ],
            'routes' => ['users.roles.index', 'users.roles.create', 'users.roles.show', 'users.roles.edit'],
        ],

        'Login security' => [
            'body' => '<p>Three controls protect the sign-in page, and the maker-checker rules protect what people do once inside. Know how each behaves before a user reports a problem.</p>'
                . '<h4>The security check</h4><p>The login page shows a distorted code image and a box to type it into. The code is generated on this server, held in the session and never sent to the browser as text. It is single use, not case sensitive, and expires after a few minutes. The circular-arrows button beside the image issues a new one. No third-party service is involved, so it works with no internet connection.</p>'
                . '<h4>Two-factor authentication</h4><p>Users enable it themselves from <b>Profile</b>. Once on, sign-in asks for a code from their authenticator application. Recovery codes are shown once and can be regenerated. Encourage it for every account with approval rights.</p>'
                . '<h4>Sessions and lockout</h4><ul>'
                . '<li>Sessions expire after the configured lifetime; an expired session produces a plain message, not a silent failure.</li>'
                . '<li>Sign-in is throttled to five attempts a minute for the same email and address.</li>'
                . '<li>An inactive account is let past the password but refused on every page with <b>Your account is inactive, contact the administrator</b>.</li>'
                . '</ul>'
                . '<h4>Maker and checker</h4><p>The platform enforces the two-person rule itself. Among the refusals:</p><ul>'
                . '<li>Fee classification: <b>A classifier cannot review their own decision.</b></li>'
                . '<li>EIR lock: <b>The calculator cannot approve and lock their own EIR.</b></li>'
                . '<li>Governance Centre: <b>The person who proposed a change cannot approve it.</b></li>'
                . '<li>Loan book builds and staging thresholds: <b>Maker-checker: the approver must be a different person from the requester.</b> (or from the proposer).</li>'
                . '<li>Write-offs, restructures and compliance rows refuse the proposer or signer in the same way.</li>'
                . '</ul>'
                . '<p>Whether an administrator may override the rule on their own governance change, EIR lock, fee review, SICR trigger, rate move or restructure is itself a governed setting, <b>Administrator override of maker-checker</b>, in the Governance Centre. Its recommended value is <b>Not allowed</b>, so by default the rule holds for everyone, administrators included. Reopening a locked effective interest rate also needs this override: with the setting at Not allowed, nobody can reopen a locked rate. When the override is used, the audit entry carries a flag saying so.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The code image does not appear.</b> The server component that draws it is unavailable. This is an ICT issue, covered in the Installation Guide.</li>'
                . '<li><b>A user is locked out after several attempts.</b> Wait a minute. If it persists, confirm the account is active and the email is correct.</li>'
                . '<li><b>Someone lost their two-factor device.</b> They use a recovery code. If those are gone too, there is no screen to clear two-factor on another person\'s account; raise a ticket so ICT or Dupleix clears it on the server.</li>'
                . '<li><b>An approver is refused on their own item.</b> Expected. Ask a second person who holds the permission.</li>'
                . '</ul>',
            'images' => [
                'login' => 'The sign-in page with the security check and its refresh button',
            ],
            'routes' => [],
        ],

    ],

];
