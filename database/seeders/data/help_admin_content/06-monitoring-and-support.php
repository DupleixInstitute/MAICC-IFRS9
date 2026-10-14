<?php

/**
 * Administrator Manual chapter 6 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * the Audit section's three tabs, the Maintenance console and the current ticket screens).
 */
return [

    'Monitoring and Support' => [

        'Audit trail' => [
            'body' => '<p>The <b>Audit Trail</b> is every recorded change, module action and export in the system, newest first, with who made it. It reads two stores as one list: the record-change log written when a record is created, changed or deleted, and the module audit log written by imports, calculations, approvals, settings and exports. Open <b>Administration &amp; support</b>, then <b>Governance</b>, then <b>Audit</b>; the section opens on its first tab, <b>Audit Trail</b>. It needs the <code>settings</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>One filter row: <b>Search action or record</b>; a source selector (<b>All sources</b>, <b>Record changes</b>, <b>Module actions and exports</b>); a user selector (<b>All users</b> or one person); <b>From</b> and <b>To</b> dates; and <b>Clear</b> once a filter is set. The list refreshes as you change a filter.</li>'
                . '<li>On the right, the size of the two logs: N <b>record changes</b> | N <b>module actions</b>.</li>'
                . '<li>The table: <b>When</b>, <b>User</b>, <b>Action</b>, <b>Record</b> (the kind of record and its number), <b>Source</b> and <b>Details</b>.</li>'
                . '<li><b>Source</b> is a badge: green <b>Record change</b>, gold <b>Module action</b> (a calculation, an approval, a setting), grey <b>Export</b> (a report, download, pack, PDF, Excel or CSV).</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Export CSV</b> (top right, download icon). Downloads the whole trail as filtered on screen, not just the page shown.</li>'
                . '<li>Green eye, <b>Show the details</b>, in the Details column. Opens a panel under the row with every field recorded: old and new values, the reporting period, the reason, the proposer and approver, whether an override was used. The icon turns into a minus, <b>Hide the details</b>. A dash means the entry carries no details.</li>'
                . '<li>The pager under the table, which pages on the server.</li>'
                . '</ul>'
                . '<h4>What is recorded</h4><ul>'
                . '<li>Record changes: user accounts, financial periods, tickets, clients and the reference lists (currencies, sector types and the other lists).</li>'
                . '<li>Imports and pack loads, with who ran them and what they produced.</li>'
                . '<li>Governance: settings proposed and approved; builds, schedules, fees, EIR locks and reopenings, write-offs, restructures, SICR triggers, scenario sets, overlays; period locks and unlocks; compliance rows signed and approved.</li>'
                . '<li>Maintenance commands launched, with the command line.</li>'
                . '<li>Exports and downloads.</li>'
                . '</ul>'
                . '<p>Not recorded in this version: saves on the Settings tabs (organisation details, reporting currency, email), role and permission changes, Workspace ticks and messages, and manual edits. Keep a support ticket for each such change.</p>'
                . '<h4>How to use it</h4><ul>'
                . '<li><b>Who changed this governed setting?</b> Choose Module actions and exports and search the setting\'s name or key.</li>'
                . '<li><b>Was the control observed?</b> Open the approval entry and check the approver is not the proposer, and whether the override flag is set. This is the evidence an auditor asks for.</li>'
                . '<li><b>What happened in this close?</b> Set From and To to the close dates.</li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4><ul><li><b>No audit entries match these filters. Widen the dates or clear the filters.</b></li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>An action you expected is missing.</b> Page views are not logged; changes, governance actions and exports are.</li>'
                . '<li><b>The list is very long.</b> Use the dates and the source filter.</li>'
                . '<li><b>You need one contract\'s history.</b> Use the Audit Trace tab instead.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Governance, then Audit; the Audit Trail tab opens.',
                'Set the From and To dates to the period you are investigating.',
                'Filter by source or user, or search the action or record.',
                'Press the eye on an entry to see exactly what changed.',
                'Press Export CSV to keep the filtered trail as evidence.',
            ],
            'images' => [
                'audit-trail' => 'The Audit Trail with its filters, source badges and an entry opened',
            ],
            'routes' => ['audit-trail.index'],
        ],

        'Audit trace' => [
            'body' => '<p>The <b>Audit Trace</b> follows one contract from start to finish: every schedule version, rate reset, fee, EIR calculation, monthly roll-forward and logged action, oldest first, and each reporting month\'s stage, days past due, PD, LGD and ECL with the governed settings that month was calculated under. Where the compliance workbooks state a rule, the trace shows the rule applied to one loan. Open <b>Administration &amp; support</b>, <b>Governance</b>, <b>Audit</b>, tab <b>Audit Trace</b>. Any signed-in user can open it; it changes nothing.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The search panel: <b>Contract number</b> (<b>Type or pick a contract number</b>; as you type, a list offers contract numbers with the customer names), the green <b>Trace</b> button, and the number of contracts on file.</li>'
                . '<li>Before a trace, an empty state, <b>Trace one contract from start to finish</b>, with three cards: <b>Contract details</b>, <b>Timeline of events</b> and <b>Month by month</b>.</li>'
                . '<li>After a trace, the contract panel: <b>Contract</b> number, the customer, a <b>Schedule</b> status badge (green when approved), a grey <b>Locked</b> badge with the date when the EIR is locked, and <b>Product</b>, <b>GL account</b>, <b>Originated</b>, <b>Effective rate (annual)</b>, <b>EIR status</b> and <b>Months on file</b>.</li>'
                . '<li>A view switch with counts: <b>Timeline</b> and <b>Month by month</b>.</li>'
                . '<li><b>Timeline</b>: <b>Everything that happened to this contract, oldest first.</b> Columns <b>When</b>, <b>Event</b>, <b>Detail</b>, <b>Type</b>, <b>By</b> (<b>System</b> when no person). The <b>Type</b> badge reads <b>User action</b> (gold), <b>Schedule</b>, <b>Fee</b>, <b>EIR calculation</b> (green), <b>Rate reset</b> (red) or <b>Roll-forward</b> (grey). A <b>Type of event</b> selector filters to one type, each with its count.</li>'
                . '<li><b>Month by month</b>: <b>Period</b> (with a <b>Locked</b> badge for a locked month), <b>How the month was built</b>, <b>Stage</b> (with the stage by days past due beneath when the qualitative triggers moved it, and each SICR trigger in force at the month-end with who raised and approved it), <b>Days past due</b>, <b>Carrying amount</b>, <b>PD before FLI</b>, <b>PD after FLI</b>, <b>LGD</b>, <b>ECL</b>, <b>Forward-looking adjustment</b> and <b>Settings</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Trace</b>. Runs the trace for the number typed; disabled while the box is empty.</li>'
                . '<li><b>New trace</b> (top right, after a trace). Clears the page.</li>'
                . '<li>Green eye in the <b>Settings</b> column, <b>Show the settings this month ran under</b>. Opens the governed values for that month: the loan book build method, the days-past-due basis, Stage 3 after missed instalments, the months held after a loan cures, the Stage 3 interest basis, the FLI adjustment route and transmission method, the scenario weighting method and the prime lending rate mid-period convention. The heading says whether they come from the <b>locked snapshot</b> or are the <b>values in force on the month-end</b>.</li>'
                . '<li>The pager under each table when there are more than 15 rows.</li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>This contract number is not in the EIR contract master. Any events and months found for it are shown below.</b></li>'
                . '<li><b>Nothing recorded for</b> the number: no schedule, fee, EIR, roll-forward, loan book month or logged action names it.</li>'
                . '<li><b>No events of this type.</b> and <b>This contract has no loan book months yet.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A number with leading zeros finds nothing.</b> Leading zeros are dropped before the search; pick the number from the list.</li>'
                . '<li><b>The settings of a month look newer than the run.</b> The month is not locked, so the trace shows the values in force on its month-end today. Lock signed-off months to keep the snapshot.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Governance, then Audit, and the Audit Trace tab.',
                'Type a contract number or customer name and pick the contract from the list.',
                'Press Trace.',
                'Read the Timeline, filtering by type of event if needed.',
                'Switch to Month by month and press the eye on a month to see the settings it ran under.',
            ],
            'images' => [
                'audit-trace' => 'The Audit Trace for one contract: the contract panel, the timeline and the months',
            ],
            'routes' => ['audit-trace.index'],
        ],

        'Compliance audits' => [
            'body' => '<p><b>Compliance Audits</b> is the register of compliance workbooks: one workbook per standard or directive, each section checked against what the system does, where to see it, the setting that governs it, the test that proves it, and the reviewer\'s sign-off. Open <b>Administration &amp; support</b>, <b>Governance</b>, <b>Audit</b>, tab <b>Compliance Audits</b>. Any signed-in user can read it; reloading, signing and approving need <code>eir.govern</code>.</p>'
                . '<h4>What you see on the register</h4><ul>'
                . '<li>Five figures: <b>Workbooks</b>, <b>Sections checked</b>, <b>Done</b>, <b>Outstanding</b> and <b>Open findings</b>.</li>'
                . '<li>The table: <b>Workbook</b> (short name as a link, full title beneath), <b>Reviewer</b>, <b>Loaded</b>, <b>Sections</b>, <b>Status</b> (a badge per status with its count: green <b>Done</b>, gold <b>Partially done</b>, red <b>Outstanding</b>, grey <b>Not applicable, documented</b> and <b>Evidence needed from MAIIC</b>), <b>Open findings</b>, <b>Downloads</b> and <b>Open</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons on the register</h4><ul>'
                . '<li><b>Reload from the workbooks</b> (top right, <code>eir.govern</code>). Reads the workbook files shipped under the compliance documents folder. A row someone has signed keeps its signed state; unsigned rows take the workbook\'s status.</li>'
                . '<li><b>XLSX</b>, <b>PDF</b>, <b>MD</b> in Downloads, <b>Download the</b> format <b>file</b>: the workbook in that format, where the file exists; <b>None</b> otherwise.</li>'
                . '<li>Green eye, <b>Open the workbook</b>.</li>'
                . '</ul>'
                . '<h4>One workbook</h4><p>The page title is the workbook\'s short name with its full title beneath. At the top right: <b>All workbooks</b> and the three download buttons. Under the title, the basis and the status counts. A view switch with counts: <b>Sections</b> and <b>Findings</b>.</p><ul>'
                . '<li><b>Sections</b>, grouped by part: <b>Ref</b>, <b>Section</b>, <b>What it requires</b>, <b>Status</b> (with <b>Proposed:</b> and the proposed status in amber when a sign-off waits), <b>What the system does</b> (with the compliance comment in italics), <b>Where to see it</b> (a link to the screen where one is named), <b>Setting</b>, <b>Test</b>, <b>Sign-off</b> (<b>Signed</b> name and date, <b>Approved</b> name and date) and, for <code>eir.govern</code>, <b>Review</b>.</li>'
                . '<li><b>Findings</b>: <b>No.</b>, <b>Ref</b>, <b>Finding</b>, <b>What was found</b>, <b>Impact</b>, <b>Recommended action</b>, <b>Owner</b> and <b>Status</b> (green <b>Closed</b> or gold).</li>'
                . '</ul>'
                . '<h4>Buttons and icons on a workbook</h4><ul>'
                . '<li>In <b>Review</b>: a status selector, then the gold pen, <b>Sign this status</b>, which opens <b>Sign</b> with the reference: <b>You are signing the status</b> shown <b>. A second person then approves it.</b>, an optional <b>Note for the audit log</b>, <b>Cancel</b> and <b>Sign</b>.</li>'
                . '<li>Green tick, <b>Approve the proposed status</b>, on a row with a proposal. The signer cannot approve their own row.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>Signing records the proposed status, the signer and the note (<b>Compliance Row Signed</b> in the audit log); approving makes it the row\'s status (<b>Compliance Row Approved</b>). <code>php artisan compliance:audits --export-signed</code> writes the approved state beside each workbook for the workbook builder.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No compliance workbooks loaded yet</b>: ask the system administrator to build the workbooks, then press Reload from the workbooks.</li>'
                . '<li><b>This workbook has no sections.</b> and <b>No findings recorded for this workbook.</b></li>'
                . '<li><b>Row signed; a second person approves the status.</b> and <b>Status approved and recorded.</b></li>'
                . '<li><b>A row may be Done only when the test that proves it is named.</b></li>'
                . '<li><b>Maker-checker: the approver must be a different person from the signer.</b> and <b>Nothing proposed on this row.</b></li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Governance, then Audit, and the Compliance Audits tab.',
                'Press the eye on a workbook to open it.',
                'On a section, choose the status in Review and press the pen to sign it, adding a note.',
                'A second person with eir.govern presses the tick to approve the proposed status.',
                'Download the workbook as XLSX or PDF for the auditor.',
            ],
            'images' => [
                'compliance-audits' => 'Compliance Audits: the workbooks with their status counts and downloads',
            ],
            'routes' => ['compliance-audits.index', 'compliance-audits.show'],
        ],

        'Notifications' => [
            'body' => '<p>The bell in the top bar holds your notifications. It shows an unread count.</p><h4>What raises a notification today</h4><ul><li>A support ticket created, updated or given an update, sent to the assignee and the creator.</li><li>A manual Workspace step ticked or reopened, sent to the other administrators.</li><li>A new account with <b>Email the sign-in details</b> ticked receives its sign-in details by email.</li></ul><h4>Using the bell</h4><ul><li>Press it to open <b>Notifications</b>; the list loads when opened (<b>Loading notifications...</b>).</li><li>Mark a single item read, or all of them.</li><li>When there are none: <b>No notifications yet.</b></li></ul><h4>What is not wired yet</h4><p>Notifications for completed imports, finished month-end runs and items awaiting approval are not sent. The Workspace tab <b>Awaiting approval</b>, the Imports tab and the Maintenance run history are where you check those.</p><h4>Common problems</h4><ul><li><b>The badge shows unread items but the list is empty.</b> Reload the page.</li><li><b>An administrator is not told of their own tick.</b> Notifications go to the other administrators.</li></ul>',
            'images' => [
                'workspace' => 'Ticking a manual Workspace step notifies the other administrators',
            ],
            'routes' => [],
        ],

        'Support tickets' => [
            'body' => '<p><b>Support Tickets</b> is the agreed record between MAIIC and Dupleix of enhancement requests, issues and change requests. It is not an internal to-do list: it is the document both sides refer to, and the change-control clause of the agreement points at it. Open <b>Administration &amp; support</b>, then <b>Support &amp; Maintenance</b>, then <b>Support Tickets</b>. It needs <code>tickets.index</code>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>Status tabs with the real count in each: <b>All</b>, <b>Open</b>, <b>In progress</b>, <b>Resolved</b>, <b>Closed</b>.</li>'
                . '<li>Filters: <b>Search reference or title</b> and a category selector (<b>All categories</b>, Enhancement, Issue, Change Request, Other).</li>'
                . '<li>The table: <b>Ref</b>, <b>Title</b>, <b>Category</b>, <b>Priority</b> (Low, Medium, High, Critical), <b>Status</b> (Open, In Progress, On Hold, Resolved, Closed), <b>Assigned to</b>, <b>Updated</b> and <b>Actions</b>, each label a coloured badge.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>New ticket</b> (top right, plus). Needs <code>tickets.create</code>.</li>'
                . '<li>Green eye, <b>View</b>: the ticket page. Gold pencil, <b>Edit</b>: the ticket page with its details in edit mode (<code>tickets.update</code>). Red bin, <b>Delete</b>, with a confirmation (<code>tickets.destroy</code>).</li>'
                . '<li>On the ticket page: <b>Delete</b> and <b>Back to tickets</b> at the top right; <b>Add an update</b> with a note (<b>Progress note, comment or resolution detail</b>), <b>Change status to</b> (or keep the current status) and <b>Post update</b>; in <b>Details</b>, the pencil (<b>Edit details</b>) opens the fields and <b>Save changes</b>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4><ul>'
                . '<li><b>Title</b> and <b>Description</b>. Name the screen, the period, the steps and the exact message.</li>'
                . '<li><b>Category</b>, <b>Priority</b>, <b>Status</b>.</li>'
                . '<li><b>Requested by</b> and <b>Source</b>. Who asked and how.</li>'
                . '<li><b>Assign to</b> (<b>Unassigned</b> or a person) and <b>Due date</b>.</li>'
                . '<li><b>Resolution</b>, on the ticket page. What was delivered.</li>'
                . '</ul>'
                . '<p>The reference is assigned automatically in sequence; you cannot set it. The ticket page shows the <b>Description</b>, the <b>Resolution</b>, and the <b>Activity</b> trail, where status changes are written automatically and updates are added by hand.</p>'
                . '<h4>What happens next</h4><p>Creating or updating a ticket notifies the assignee and the creator through the bell, and is recorded in the audit trail.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No tickets here</b>: <b>Log a request or a problem with New ticket.</b>, or <b>Nothing matches these filters. Clear them to see every ticket.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>You cannot see the module.</b> Your role lacks <code>tickets.index</code>.</li>'
                . '<li><b>A ticket is resolved with no resolution.</b> Add it. The resolution is the part MAIIC reads.</li>'
                . '</ul>',
            'steps' => [
                'Open Support & Maintenance, then Support Tickets, and press New ticket.',
                'Write a title and a description naming the screen, the period and the exact message.',
                'Choose the category and priority, assign the responsible person and press Create ticket.',
                'Add progress with Add an update, changing the status when it moves.',
                'Record the resolution in Details, set the status to Resolved, and close it once MAIIC confirms.',
            ],
            'images' => [
                'tickets' => 'Support Tickets with the status tabs and the row action icons',
                'tickets-create' => 'The New ticket form',
            ],
            'routes' => ['tickets.index', 'tickets.create', 'tickets.show'],
        ],

        'Health checks on the dashboard' => [
            'body' => '<p>For an administrator the dashboard is a health check rather than a management report. Three questions, answered in under a minute.</p><h4>1. Does the latest month have calculated results?</h4><p>If the dashboard says no data is available, no month carries a calculated ECL: the month-end job has not run or stopped. Check the Maintenance run history.</p><h4>2. Do the change chips look plausible?</h4><p>Each chip compares against the compare-to month. An early month holds a partial book, so growth against it is naturally large.</p><h4>3. Does the coverage trend have every month it should?</h4><p>The trend plots only months that exist. A gap means a build or a month-end run was skipped.</p><h4>What to do with what you find</h4><ul><li>A missing month: check Imports &amp; Feeds for the pack and the build, then run the month-end.</li><li>A sudden coverage move: look at the stage split before questioning the model.</li><li>A figure labelled in the wrong currency: Settings, Reporting.</li><li>For the system as a whole, run <b>Check the system</b> on Maintenance.</li></ul>',
            'images' => [
                'dashboard' => 'The dashboard filter bar, KPI tiles and the coverage trend',
            ],
            'routes' => [],
        ],

        'The Maintenance console' => [
            'body' => '<p><b>Maintenance</b> runs the system\'s setup, month-end, data and check jobs from the browser, with live output. The browser only sends the job\'s name and, for a month-end step, a month chosen from the months the system holds; the command line is built on the server from a fixed list, so nothing typed reaches the server\'s shell. One job runs at a time, and every launch is written to the audit log. Open <b>Administration &amp; support</b>, then <b>Support &amp; Maintenance</b>, then <b>Maintenance</b>. Seeing the console needs <code>settings</code>; running a job needs the <b>admin</b> role.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>Two badges at the top right: <b>Admin only</b> and <b>Audit logged</b>.</li>'
                . '<li>Four figures: <b>Database</b> (the connected database\'s name), <b>Loan book months</b> (with the latest), <b>Months with ECL</b>, and <b>Now running</b> (the running job in red, or <b>Nothing</b>).</li>'
                . '<li>Without the admin role, an amber note: <b>You can see this console, but running a command is restricted to a system administrator.</b></li>'
                . '<li>Four tabs with counts: <b>Month-end</b>, <b>Data and checks</b>, <b>Install and update</b> and <b>Run history</b>.</li>'
                . '<li>On Month-end, a <b>Month</b> picker listing the months the system holds plus the month after the latest, so a new month can be built, and the reminder to build, have the build approved, then run the calculations.</li>'
                . '<li>Each job is a card: a step number (Month-end and Install and update), the name, a badge (green <b>Read only</b>, gold <b>Writes data</b>, red <b>Destructive</b>), a description, the exact command line, a note, and <b>Last:</b> date and status, or <b>Not run from here yet</b>.</li>'
                . '</ul>'
                . '<h4>The jobs</h4><ul>'
                . '<li><b>Month-end</b>: 1 <b>Build the loan book</b> (<code>eir:build-loan-books</code>; a second person approves the build on Loan Book Builds; a locked month is refused); 2 <b>Run the month-end calculations</b> (<code>maiic:task month-end</code>, described in Running the month-end close); 3 <b>Recalibrate the forward-looking model</b> (<code>fli:correlate</code>; once a year or after an event; writes candidate fits only, which reach the loans only after approval on Regression).</li>'
                . '<li><b>Data and checks</b>: 1 <b>Fetch new data</b> (<code>maiic:task refresh-data</code>: the World Bank macro series, then every new E-Banker pack in the feed folder); 2 <b>Check the system</b> (<code>maiic:task health-check</code>: a system snapshot, the database update status, the golden numbers and acceptance ties, then every report and its PDF, each PASS or FAIL; read only).</li>'
                . '<li><b>Install and update</b>: 1 <b>Bootstrap the system (first install)</b> (destructive: wipes the connected database and rebuilds it from the committed client inputs); 2 <b>Update the system after a new release</b> (<code>maiic:task update</code>: database updates, then the permission, ticket permission, Governance Centre default and qualitative SICR factor seeders; additive; run once after every deployment).</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Run</b> (play icon) on each card. Disabled without the admin role, while another job runs, and until the month is chosen. A <b>Writes data</b> job asks for confirmation with its note; then the output panel opens.</li>'
                . '<li>The destructive card has a box, <b>Type BOOTSTRAP to enable</b>; Run turns red and stays disabled until the phrase is typed exactly, then a second dialog, <b>Wipe and rebuild this database?</b>, names the database and warns it cannot be undone.</li>'
                . '<li>The output panel slides in from the right: the job name, the command, a status badge (<b>running</b>, <b>done</b>, <b>failed</b>) with the time taken, the close cross, and the output in a dark window that follows the newest line.</li>'
                . '<li><b>Run history</b>: <b>Started</b>, <b>Command</b> (name and command line), <b>Run by</b>, <b>Took</b>, <b>Status</b>, and the eye, <b>View the output</b>, which opens the saved output in the same panel. Fifteen runs to a page.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>The job runs in the background, so you may leave the page; its output stays in Run history. The audit log records <b>Maintenance Command Launched</b> with the command, the month and the run number. A job that stops prints the step it stopped at; correct what it names and run it again.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No command has been run from this console yet.</b></li>'
                . '<li>A job name <b>is still running. Wait for it to finish.</b></li>'
                . '<li><b>Choose a month the system holds a loan book for.</b></li>'
                . '<li><b>Type the confirmation phrase to run this command.</b></li>'
                . '<li><b>Refused: running a command is restricted to a system administrator.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Run is greyed out.</b> You are not an administrator, a job is running, or no month is chosen.</li>'
                . '<li><b>The panel shows Starting... and nothing more.</b> The background process could not start; check the server log with ICT.</li>'
                . '<li><b>Roles changed after an update.</b> The update re-grants the EIR and ticket permissions to every staff role; review the roles.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Support & Maintenance, then Maintenance.',
                'Choose the tab: Month-end, Data and checks, or Install and update.',
                'On Month-end, choose the month.',
                'Press Run on the job and confirm; for Bootstrap, type BOOTSTRAP first.',
                'Read the output to the end; done or failed shows on the badge.',
                'Find any earlier run on Run history and press the eye to read its output.',
            ],
            'images' => [
                'maintenance' => 'The Maintenance console: the figures, the tabs and the job cards',
            ],
            'routes' => ['maintenance.index'],
        ],

    ],

];
