<?php

/**
 * Administrator Manual chapter 1 (Ticket #011 depth rewrite; documentation audit 14 October 2026).
 * Loaded by HelpAdminContentSeeder from database/seeders/data/help_admin_content.
 */
return [

    'Administering the Platform' => [

        'Role of the administrator' => [
            'body' => '<p>The administrator owns everything that is not a calculation. Analysts run the IFRS 9 and EIR workflow described in the User Manual; you keep the environment in which they do it correct, controlled and auditable. Almost all of your screens sit in the last block of the sidebar, <b>Administration &amp; support</b>, in three groups: <b>Governance</b>, <b>Access &amp; Setup</b> and <b>Support &amp; Maintenance</b>.</p>'
                . '<h4>What you are accountable for</h4><ul>'
                . '<li><b>Access.</b> Every account, every role and every permission (<b>Access &amp; Setup</b>, then <b>Users &amp; Roles</b>). If someone can see a figure they should not, or cannot do work they should, that is an access question.</li>'
                . '<li><b>Governed settings.</b> Every calculation convention the engines use (the EIR conventions, the staging basis, the PD and LGD segments, the forward-looking settings, the Mega Farm programme) is a governed setting in the <b>Governance Centre</b>. One person proposes a change and a second approves it, from an effective date.</li>'
                . '<li><b>Configuration.</b> The organisation name, logos, reporting currency and outgoing email on <b>Settings</b>, which appear on every report and every manual cover.</li>'
                . '<li><b>Periods.</b> The financial period shown in the top bar, the month-end close checklist in the <b>Workspace</b>, and the lock that stops a signed-off reporting month from being restated.</li>'
                . '<li><b>Data governance.</b> The approvals the platform deliberately refuses to make on its own: loan book builds, fee rules and classifications, schedules, effective interest rates, write-offs, restructures and the forward-looking month. Each is a two-person control.</li>'
                . '<li><b>Running the system.</b> The month-end jobs, the data refresh, the health check and the release update on the <b>Maintenance</b> console.</li>'
                . '<li><b>Evidence.</b> The <b>Audit Trail</b>, the per-contract <b>Audit Trace</b>, the <b>Compliance Audits</b> register, the support tickets and the documentation, which together are what an auditor examines when they ask how a figure was controlled.</li>'
                . '</ul>'
                . '<h4>What you are not accountable for</h4><p>The platform is a calculation and reporting tool. MAIIC owns its accounting policies, IFRS 9 judgements, model methodology, assumptions, scenarios and overlays, and the figures it adopts in its financial statements and regulatory returns. Approving a setting or a rule in this platform records a decision; it does not make the decision for you.</p>'
                . '<h4>Where the work happens</h4><ul>'
                . '<li><b>Administration &amp; support</b>, then <b>Governance</b>: the Governance Centre and the Audit section (Audit Trail, Audit Trace, Compliance Audits).</li>'
                . '<li><b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>: Users &amp; Roles, Financial Periods and Settings.</li>'
                . '<li><b>Administration &amp; support</b>, then <b>Support &amp; Maintenance</b>: Support Tickets, Maintenance and System Documentation.</li>'
                . '<li>The approvals of the working screens (builds, schedules, fees, EIR locks, write-offs, restructures, scenario sets, overlays) stay on those screens; the Workspace tab <b>Awaiting approval</b> lists every one of them with a count and a link.</li>'
                . '<li>Server work (backups, the queue worker, the scheduler, TLS certificates, upgrades) is in the <b>Installation Guide</b>. How the engines calculate is in the <b>Technical Manual</b>.</li>'
                . '</ul>'
                . '<h4>A month in the life</h4><ol>'
                . '<li>Confirm the month\'s E-Banker pack has landed and its loan book build is proposed and approved (Imports &amp; Feeds).</li>'
                . '<li>Run the month-end calculations from Maintenance, or watch the analysts run them.</li>'
                . '<li>Open the Workspace, choose the month, and clear what waits on the <b>Awaiting approval</b> tab, as the second person where you are not the proposer.</li>'
                . '<li>Read the Audit Trail for governed-setting and account changes you did not expect.</li>'
                . '<li>When the reports are reviewed and signed off, tick the two manual steps in the Workspace and lock the reporting month.</li>'
                . '</ol>',
            'steps' => [
                'Each month, open the Workspace and select the reporting period being closed.',
                'Check Imports & Feeds: the pack landed, the loan book build approved.',
                'Run the month-end calculations from Maintenance, or confirm the analysts have.',
                'Clear the Awaiting approval tab as the second person, never on your own proposals.',
                'Review the Audit Trail for governed-setting and account changes you did not expect.',
                'Tick Review and Sign-off in the Workspace, then lock the reporting month.',
            ],
            'images' => [
                'dashboard' => 'The dashboard, where an administrator confirms the latest period has calculated results',
                'workspace' => 'The Workspace close checklist, which shows how far the month has progressed',
            ],
            'routes' => [],
        ],

        'Administration menu map' => [
            'body' => '<p>The sidebar is the same for everyone; what each person sees in it depends on their permissions. An entry whose permission you do not hold is hidden, and a group with nothing left in it disappears. This article describes the sidebar as a whole and then the administrator\'s block in detail.</p>'
                . '<h4>The sidebar in blocks</h4><ul>'
                . '<li><b>Dashboard</b>, <b>Workspace</b> and <b>Reports</b> sit at the top with no heading and are always shown.</li>'
                . '<li>Four blocks follow, each under a small-capitals heading with its own icon and colour: <b>Data foundation</b>, <b>Financial modelling</b>, <b>Risk &amp; regulatory</b> and <b>Administration &amp; support</b>.</li>'
                . '<li>Each block folds from its heading: click the heading to open or close it. The block holding the page in view opens by itself. Which blocks you left open is remembered for your account in this browser.</li>'
                . '<li>An entry that opens a screen with tabs shows a small number beside its name, the count of its tabs; hover over it to read their names.</li>'
                . '<li>The entry for the page in view is marked with a solid bar and a light tint.</li>'
                . '</ul>'
                . '<h4>The collapsed rail</h4><p>At the foot of the sidebar, <b>Collapse menu</b> (a left chevron) folds the sidebar to a narrow icon rail and the page widens with it. On the rail every entry shows only its icon, with its name as a tooltip, and the blocks are drawn as thin rules. Clicking a group icon opens the full sidebar on that group. The right chevron, <b>Expand the menu</b>, restores the full sidebar. The choice is remembered in this browser. On a phone the sidebar is a drawer instead and has no rail.</p>'
                . '<h4>Breadcrumbs</h4><p>Above every page title a breadcrumb trail shows where the page sits in the menu: block, then group, then entry, then tab, for example <b>Administration &amp; support</b> &gt; <b>Governance</b> &gt; <b>Audit</b> &gt; <b>Audit Trace</b>. Every crumb but the last is a link. A detail page adds its own crumbs after the menu trail; a page the menu does not hold (a profile, the help centre) reads <b>Dashboard</b> &gt; its title. On a phone the middle crumbs fold into "...".</p>'
                . '<h4>The Administration &amp; support block</h4>'
                . '<p><b>Governance</b></p><ul>'
                . '<li><b>Governance Centre</b>. Every governed calculation setting under maker-checker. Visible only with the <code>eir.govern</code> permission.</li>'
                . '<li><b>Audit</b>, a section with three tabs: <b>Audit Trail</b> (every recorded change, module action and export; needs <code>settings</code>), <b>Audit Trace</b> (one contract\'s full history) and <b>Compliance Audits</b> (the register of compliance workbooks with reviewer sign-off).</li>'
                . '</ul>'
                . '<p><b>Access &amp; Setup</b></p><ul>'
                . '<li><b>Users &amp; Roles</b>, a section with two tabs: <b>Users</b> and <b>Roles &amp; Permissions</b>.</li>'
                . '<li><b>Financial Periods</b>. The accounting period shown in the top bar.</li>'
                . '<li><b>Settings</b>. Organisation details, reporting currency, email, the staging basis and links to further administration screens.</li>'
                . '</ul>'
                . '<p><b>Support &amp; Maintenance</b></p><ul>'
                . '<li><b>Support Tickets</b>. The agreed record of enhancements, issues and changes; needs <code>tickets.index</code>.</li>'
                . '<li><b>Maintenance</b>. The console that runs the setup, month-end, data and check jobs with live output; needs <code>settings</code> to see and the admin role to run.</li>'
                . '<li><b>System Documentation</b>, a group of four: <b>User Manual</b>, <b>Administrator Manual</b>, <b>Technical Manual</b> and <b>Installation Guide</b>.</li>'
                . '</ul>'
                . '<h4>Page furniture you will meet everywhere</h4><ul>'
                . '<li>The page title with a one-line description under it, and the page\'s own buttons at the top right.</li>'
                . '<li>Row actions as small coloured icons: a green eye to view, a gold pencil to edit, a red bin to delete (always with a confirmation dialog), and grey icons for state changes such as a padlock.</li>'
                . '<li>A round green question mark at the bottom right, <b>Help for this screen</b>, on the screens that carry it. It opens the User Manual at the article mapped to that screen, or at its contents when none is mapped.</li>'
                . '<li>The bell in the top bar, which holds your notifications.</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A menu entry is missing.</b> Your role lacks its permission (for example <code>eir.govern</code> for the Governance Centre). Check the role under Roles &amp; Permissions.</li>'
                . '<li><b>A whole block seems empty.</b> It is folded. Click its heading.</li>'
                . '<li><b>The sidebar shows only icons.</b> It is collapsed to the rail. Press the right chevron at its foot.</li>'
                . '<li><b>A typed address answers with a permission error.</b> Some routes kept from the platform\'s earlier life are not on the menu and need permissions MAIIC roles do not hold. Use the menu.</li>'
                . '</ul>',
            'images' => [
                'sidebar-blocks' => 'The sidebar in its blocks, with Administration & support open',
                'sidebar-collapsed' => 'The sidebar folded to the icon rail',
            ],
            'routes' => [],
        ],

    ],

];
