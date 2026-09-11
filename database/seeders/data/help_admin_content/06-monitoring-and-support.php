<?php

/**
 * Administrator Manual chapter 6 (Ticket #011 depth rewrite).
 */
return [

    'Monitoring and Support' => [

        'Audit trail' => [
            'body' => '<p><b>Administration</b>, then <b>Audit Trail</b>, is one timeline over both audit stores: the activity log written when records change, and the module audit log written by imports, settings changes and the EIR actions. They are presented together through a normalised view, so you do not have to know which store an entry came from to find it.</p><h4>What you see on the screen</h4><ul><li>A count at the top: how many activity entries and how many module audit entries exist.</li><li>Filters: a search box with the placeholder <b>Action or entity...</b>, an <b>All sources</b> selector, an <b>All users</b> selector, and <b>From</b> and <b>To</b> date boxes.</li><li>The table with the columns <b>When</b>, <b>User</b>, <b>Action</b>, <b>Entity</b>, <b>Source</b> and <b>Details</b>.</li><li>The <b>Source</b> column carries a badge naming the store the entry came from.</li><li><b>View</b> in the Details column opens the full record, including the JSON of what changed.</li></ul><h4>What is recorded</h4><ul><li>Record changes: creations, edits and deletions across the modules.</li><li>Imports: who ran them and what they produced.</li><li>Settings changes.</li><li>EIR governance: rules created, updated and approved; fees classified and reviewed, with a flag when a maker-and-checker override was used; schedules approved; rates approved, locked and reopened with the reason given.</li><li>Workspace checklist changes and ticket activity.</li></ul><h4>How to use it</h4><ul><li><b>Who changed this setting?</b> Filter by source and search the setting name.</li><li><b>Was the control observed?</b> Find the approval entry and check the approver is not the preparer. This is the evidence an auditor asks for.</li><li><b>What happened in this period?</b> Filter by the date range of the close.</li></ul><h4>What happens next</h4><p>Entries cannot be edited or deleted from the screen, which is the point. Retention is a database matter covered in the Installation Guide.</p><h4>Common problems</h4><ul><li><b>An action you expected is not there.</b> Not every read is logged; the trail records changes and governance actions, not page views.</li><li><b>The list is very long.</b> Use the date range. The trail holds thousands of entries on a live installation.</li></ul>',
            'steps' => [
                'Open Administration, then Audit Trail.',
                'Set the From and To dates to the period you are investigating.',
                'Filter by user or source, or search the action or entity name.',
                'Press View on an entry to see exactly what changed.',
            ],
            'images' => [
                'audit-trail' => 'The unified audit trail with its filters and the detail viewer',
            ],
            'routes' => ['audit-trail.index'],
        ],

        'Notifications' => [
            'body' => '<p>The bell in the header carries database notifications. It shows an unread count and animates while unread items exist.</p><h4>What raises a notification today</h4><ul><li>A support ticket created, updated or commented on, sent to the assignee and the creator.</li><li>A workspace checklist step ticked, sent to the other administrators.</li></ul><h4>Using the bell</h4><ul><li>Open it to fetch the recent list; it loads on demand rather than on every page.</li><li>Mark a single item read, or mark all read.</li><li>The unread count is shared on every page load, so it is current wherever you are.</li></ul><h4>What is not wired yet</h4><p>Notifications for import completion, ECL run completion and EIR approvals are planned under a separate change request. Until then, the Imports page and the Coverage and Blockers page are where you check those.</p><h4>Common problems</h4><ul><li><b>The badge shows unread items but the list is empty.</b> Reload the page; the count and the list are fetched separately.</li><li><b>An administrator is not being notified of checklist changes.</b> Notifications go to other administrators, not to the person who ticked the step.</li></ul>',
            'images' => [
                'workspace' => 'Ticking a manual checklist step notifies the other administrators',
            ],
            'routes' => ['workspace.index'],
        ],

        'Support tickets' => [
            'body' => '<p><b>Administration</b>, then <b>Support Tickets</b>, is the agreed record between MAIIC and Dupleix of enhancements, issues and changes. It is not an internal to-do list: it is the document both sides refer to, and the change-control mechanism in the implementation agreement points at it.</p><h4>What you see on the screen</h4><ul><li>Status counts across the top: all, open, in progress, resolved, closed.</li><li>Filters for search, status and category.</li><li>The table listing each ticket with its reference, title, status, priority and assignee.</li><li>Row actions as coloured icons: a green eye to view, a gold pencil to edit (which opens the detail page in edit mode) and a red bin to delete, with a confirmation.</li><li>A button to create a new ticket.</li></ul><h4>Field by field on a ticket</h4><ul><li><b>Reference</b>. Assigned automatically in sequence, three digits, for example 012. You cannot set it.</li><li><b>Title</b>. One line that identifies the request.</li><li><b>Description</b>. What is wanted or what went wrong. Include the screen, the period, the steps and any error text.</li><li><b>Category</b> and <b>Priority</b>.</li><li><b>Status</b>: open, in progress, resolved, closed.</li><li><b>Requested by</b> and <b>Source</b>. Who asked and how, for example email or meeting.</li><li><b>Assignee</b>, the responsible person.</li><li><b>Resolution</b>. Completed when the work is done, describing what was delivered.</li></ul><h4>The activity trail</h4><p>The detail page carries a chronological trail. Status changes are written automatically, describing the move from one status to another. You add progress notes by hand. This is what lets either side reconstruct what happened and when.</p><h4>What happens next</h4><p>Creating or updating a ticket notifies the assignee and the creator through the bell, and is written to the audit trail.</p><h4>Common problems</h4><ul><li><b>You cannot see the ticket module.</b> It is permission-gated; internal staff roles are granted the ticket permissions by a seeder.</li><li><b>A ticket has no resolution but is marked resolved.</b> Add it. The resolution is the part MAIIC reads.</li></ul>',
            'steps' => [
                'Open Administration, then Support Tickets, and press the create button.',
                'Write a title and a description naming the screen, the period and the exact message.',
                'Choose the category and priority, and assign the responsible person.',
                'Add progress notes to the trail as the work proceeds, changing the status when it moves.',
                'Record the resolution and set the status to resolved; close it once MAIIC confirms.',
            ],
            'images' => [
                'tickets' => 'Support tickets with status counts and the row action icons',
                'tickets-create' => 'Creating a ticket',
            ],
            'routes' => ['tickets.index', 'tickets.create'],
        ],

        'Health checks on the dashboard' => [
            'body' => '<p>For an administrator the dashboard is a health check rather than a management report. Three questions, answered in under a minute.</p><h4>1. Does the latest period have calculated results?</h4><p>If the dashboard says no data is available, no period carries a calculated ECL. Either the month has not been run or the run failed.</p><h4>2. Do the change chips look plausible?</h4><p>Each chip compares against the compare-to period. An early period holds a partial book, so growth against it is naturally large. An implausible figure is usually a compare-to problem, not a calculation problem.</p><h4>3. Does the coverage trend have every month it should?</h4><p>The trend plots only periods that exist; nothing is filled in. A gap in the line means an import or a calculation was skipped for that month, which is worth chasing before someone notices it in a board pack.</p><h4>What to do with what you find</h4><ul><li>A missing month: check Imports for that period, then run the ECL calculation.</li><li>A sudden coverage move: open the ECL results and look at the stage split before questioning the model. One large exposure moving to Stage 3 moves coverage more than a model change does.</li><li>A figure labelled in the wrong currency: Settings, then System.</li></ul>',
            'images' => [
                'dashboard' => 'The dashboard filter bar, KPI tiles and the coverage trend',
            ],
            'routes' => ['dashboard'],
        ],

    ],

];
