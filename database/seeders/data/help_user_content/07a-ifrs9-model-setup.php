<?php

/*
|--------------------------------------------------------------------------
| User Manual chapter: Financial Modelling (part one)
| (the suite's group name; it was 'IFRS 9 Model Setup' until the system
| audit of 9 October 2026, finding M16)
|--------------------------------------------------------------------------
| Loaded by HelpContentSeeder. Chapter title => [article title => spec].
| Spec keys: body (HTML), steps (array), images (key => caption), routes.
| Part two of this chapter (forward-looking model and overlays) lives in
| 07b-ifrs9-model-setup.php; the loader merges both under one chapter.
| Documentation audit of 14 October 2026: every article re-read against
| the screens (labels, columns, buttons, permissions, maker-checker, locks).
*/

return [
    'Financial Modelling' => [

        'Staging and SICR: quantitative thresholds' => [
            'body' => '<p>IFRS 9 places every loan in one of three stages. Stage 1 is a performing loan that carries a 12-month expected credit loss (ECL). Stage 2 is a loan with a significant increase in credit risk (SICR) since it was granted and carries a lifetime ECL. Stage 3 is a credit-impaired loan, also on lifetime ECL. The quantitative rule that separates the stages is days past due (DPD): how many days the oldest unpaid instalment is overdue. The DPD thresholds are governed. One person proposes a change, a second person approves it, and staging reads only approved rows, from their effective date. The thresholds live on the <b>Staging basis</b> tab of Settings. To reach it, open the sidebar and choose <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Settings</b>, then the <b>Staging basis</b> tab (breadcrumbs: Administration &amp; support, Access &amp; Setup, Settings). Opening the page needs the <b>settings</b> permission; proposing, approving and rejecting a threshold need the <b>eir.govern</b> permission.</p>'
                . '<p>The older Quantitative Thresholds screen was retired on 9 October 2026 because no engine read the table it edited. Its address (<code>/stageing-rules</code>) now opens Settings on the Staging basis tab, and the <b>Staging &amp; SICR Rules</b> entry under IFRS 9 Model Setup holds only the three SICR tabs.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Settings, with the line <code>Organisation details, the reporting currency, system email and the staging basis the IFRS 9 engine uses.</code> Users with eir.govern also see a <b>Governance Centre</b> button (gavel icon) at the top right.</li>'
                . '<li><b>Three figure cards</b>: <b>Organisation</b> (the company name, or <code>Not set</code>), <b>Reporting currency</b> and <b>Days past due counted from</b> (the governed DPD basis in force, for example <code>Oldest overdue instalment</code>).</li>'
                . '<li><b>Tabs</b>: <b>Organisation</b>, <b>Reporting</b>, <b>Email</b>, <b>Staging basis</b> and <b>More settings</b>, each with a count. The Staging basis count is the number of governed staging settings plus the number of threshold rows shown.</li>'
                . '<li><b>How this works</b>: a folded note. Click it to read that each month-end the DPD is counted on the basis below and compared with the thresholds for the loan facility class, under the Reserve Bank of Malawi directive for development finance institutions, then the missed-instalment trigger and the cure period apply; and that a month is always staged on the values in force at its month-end.</li>'
                . '<li><b>Governed staging settings in force today</b> table with the columns <b>Setting</b> (name and a one-line help), <b>Value in force</b> (green badge, or red <code>Not approved yet</code>), <b>In force since</b> (a date, or <code>From installation</code> for the value the system was installed with), <b>Approved by</b> and <b>Pending changes</b> (gold <code>N awaiting approval or a later date</code>, or grey <code>None</code>). The four rows are <b>Days-past-due basis</b>, <b>Missed-instalment trigger for Stage 3</b>, <b>Months a loan stays in its stage after it cures</b> and <b>Rebutting the 30-day Stage 2 presumption</b>. These four are read only here; they change in the Governance Centre.</li>'
                . '<li><b>Days-past-due thresholds by facility class</b> table with the columns <b>Facility class</b> (shown as <code>All facilities</code>, <code>Long term</code>, <code>Short term</code> or <code>Mega Farm</code>), <b>Applies from tenor (months)</b>, <b>Stage 2 from (days)</b>, <b>Stage 3 from (days)</b>, <b>Effective from</b>, <b>Status</b>, <b>Proposed by</b> (a name, or <code>Loaded with the system</code> for a shipped row), <b>Approved by</b>, and for users with eir.govern an unlabelled action column.</li>'
                . '<li><b>Status</b> badges: gold <code>Proposed, awaiting a second person</code>; grey <code>Rejected</code>; green <code>In force</code>; gold <code>Approved, from its date</code> (approved, effective date still ahead). Hover over a proposed or rejected badge to read the reason.</li>'
                . '<li>The note under the table: <code>A loan uses the most specific approved row it qualifies for: its own facility class before "All facilities", then the longest tenor it meets. A change is proposed by one person and approved by another before staging reads it; it applies forward only, from its effective date.</code></li>'
                . '<li><b>Propose a threshold change</b> form (eir.govern only), described field by field below, with the <b>Propose</b> button.</li>'
                . '<li>At the bottom right, <b>Change in the Governance Centre</b> (eir.govern), or for other users the line <code>You need the governance right to propose a change. Ask an administrator.</code></li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Governance Centre</b> (header) and <b>Change in the Governance Centre</b> (bottom): open the Governance Centre, where the four governed staging settings are proposed and approved. eir.govern only.</li>'
                . '<li><b>Approve</b> (text link in the action column, on a proposed row): asks <code>Approve All facilities from 0 months: Stage 2 from 31 days, Stage 3 from 181, effective (date)?</code> (with the row values). The person who proposed the row does not see Approve; the column reads <code>Yours: a second person approves</code>, and the server refuses a self-approval with <code>Maker-checker: the approver must be a different person from the proposer, and both must be named.</code></li>'
                . '<li><b>Reject</b> (red text link, on a proposed row): asks <code>Why is this proposal rejected? (at least 10 characters)</code>. Any eir.govern user may reject, including the proposer, which is how a proposer withdraws a row.</li>'
                . '<li><b>Propose</b>: saves the form as a proposed row. eir.govern only.</li>'
                . '<li><b>How this works</b>: opens and closes the explanation. Clicking a tab name switches tab and keeps the tab in the address, so a refresh or a shared link opens the same tab.</li>'
                . '</ul>'
                . '<h4>Field by field (Propose a threshold change)</h4>'
                . '<ul>'
                . '<li><b>Facility class</b>: a code of capital letters, digits and underscores, placeholder <code>DEFAULT, LONG_TERM, MEGA_FARM</code>. DEFAULT is shown as All facilities.</li>'
                . '<li><b>Applies from tenor (months)</b>: zero or more. A row applies to loans whose tenor is at least this many months.</li>'
                . '<li><b>Effective from</b>: a date. It must be later than the last approved row for the same class and tenor, and in a month after the last locked reporting period, so no governed or locked month is restated.</li>'
                . '<li><b>Stage 2 from (days)</b>: 1 or more. <b>Stage 3 from (days)</b>: larger than the Stage 2 value.</li>'
                . '<li><b>Reason</b>: at least 10 characters, placeholder <code>Why the change, at least 10 characters</code>.</li>'
                . '<li><b>Rebuttal basis (needed when Stage 2 starts later than day 31)</b>: IFRS 9 presumes a significant increase in credit risk at more than 30 days past due; a later Stage 2 trigger rebuts that and must state its evidence here.</li>'
                . '</ul>'
                . '<h4>How a loan is staged each month-end</h4>'
                . '<ul>'
                . '<li>The staging run counts the DPD on the governed basis. The ageing E-Banker reports is read against E-Banker\'s instalment chart in force at that month-end (the version of the schedule that existed on that date, not today\'s live chart), so a month is never staged on a schedule that did not yet exist.</li>'
                . '<li>The DPD is compared with the approved threshold row for the loan facility class and tenor. As shipped, Stage 2 starts at 31 days in every class and Stage 3 at 181 days for medium and long term facilities and 91 days for short-term and Mega Farm facilities, following the directive; the table shows the rows actually in force.</li>'
                . '<li>The missed-instalment trigger then applies (read from the chart in force against the receipts in the ledger), then the approved SICR triggers (see SICR trigger alerts), then the cure period. The DPD stage is kept as the pre-qualitative stage and the final stage as the post-qualitative stage, the one the PD, LGD, ECL and reports use.</li>'
                . '<li>If no approved row covers a loan, staging stops and names the missing rule; it never assumes one.</li>'
                . '</ul>'
                . '<h4>To propose a change</h4>'
                . '<ol>'
                . '<li>Open Settings and click the <b>Staging basis</b> tab.</li>'
                . '<li>In <b>Propose a threshold change</b>, enter the facility class, the tenor, the effective date, the two day counts and the reason (and the rebuttal basis if Stage 2 starts after day 31).</li>'
                . '<li>Click <b>Propose</b>. The banner reads <code>Threshold proposed; a second person approves it before staging reads it.</code> and the row appears as <code>Proposed, awaiting a second person</code>.</li>'
                . '</ol>'
                . '<h4>To approve or reject a proposal (second person)</h4>'
                . '<ol>'
                . '<li>Open the Staging basis tab and find the gold proposed row.</li>'
                . '<li>Click <b>Approve</b> and confirm. The banner reads <code>Threshold approved; staging reads it from its effective date.</code></li>'
                . '<li>Or click <b>Reject</b>, type the reason (10 characters or more) and confirm. The banner reads <code>Threshold proposal rejected.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Approved rows take effect at the first month-end on or after their effective date, the next time the month is staged; an earlier month keeps the rule it was staged on. The PD, LGD and ECL then read the stage it wrote. Every proposal, approval and rejection is written to the audit trail (<code>Staging Threshold Proposed</code>, <code>Staging Threshold Approved</code>, <code>Staging Threshold Rejected</code>) with both names.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No governed staging settings were found. Open the Governance Centre to set them.</code></li>'
                . '<li><code>No thresholds are loaded. Staging stops and names the missing rule until one is approved.</code></li>'
                . '<li><code>Stage 2 from later than 31 days past due rebuts the IFRS 9 presumption (B5.5.37): state the evidence in the rebuttal basis.</code></li>'
                . '<li><code>The effective date must be later than (date), the last approved threshold for (class) from (tenor) months. A change never restates a governed month.</code></li>'
                . '<li><code>Reporting period (yyyy-mm) is locked; a threshold change takes effect after it.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>No Propose form and no Approve or Reject links</b>: your role lacks eir.govern. Ask an administrator.</li>'
                . '<li><b>Approve is missing on a row you can see</b>: you proposed it. A second person approves.</li>'
                . '<li><b>A loan is in a different stage from what the thresholds suggest</b>: the missed-instalment trigger, an approved SICR trigger or the cure period may have moved or held it. The loan book shows both the pre- and post-qualitative stage.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Administration & support, then Access & Setup, then Settings, and click the Staging basis tab.',
                'Read the Governed staging settings in force today and the Days-past-due thresholds by facility class.',
                'To change a threshold, fill in Propose a threshold change: facility class, tenor, effective date, Stage 2 and Stage 3 days and a reason.',
                'Click Propose; the row shows Proposed, awaiting a second person.',
                'A second person with the eir.govern right clicks Approve (or Reject with a reason).',
                'Stage the month again for the approved row to reach the loans from its effective date.',
            ],
            'images' => [
                'staging' => 'Settings, Staging basis tab: the governed staging settings in force and the days-past-due thresholds by facility class, with the Propose a threshold change form',
            ],
            'routes' => ['stageing-rules.index', 'stageing-rules.store', 'settings.staging-thresholds.propose', 'settings.staging-thresholds.approve', 'settings.staging-thresholds.reject'],
        ],

        'SICR groups' => [
            'body' => '<p>Besides days past due, a loan can show a significant increase in credit risk (SICR) through a qualitative warning sign: forbearance, a sector shock, a watch-list decision and so on. SICR groups are the families those warning signs are organised under; each group holds SICR items, and a credit officer raises a trigger on a loan from an item. This page is maintained by the risk administrator and needs the <b>settings</b> permission (a user without it who opens the tab sees the <code>No access rights</code> message). To reach it, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, then the <b>SICR Groups</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> SICR Groups, with the line <code>The families of qualitative warning signs used to judge a significant increase in credit risk, and how many items in each point to Stage 2 or Stage 3</code>. The tab bar above it shows the three SICR tabs.</li>'
                . '<li><b>Four figure cards</b>: <b>Groups</b>, <b>Items</b>, <b>Items pointing to Stage 2</b> (hint <code>Significant increase in credit risk</code>) and <b>Items pointing to Stage 3</b> (hint <code>Evidence the loan is credit-impaired</code>).</li>'
                . '<li><b>How qualitative SICR works</b>: a folded panel shared by the three SICR tabs. Click it to open three cards: <b>1. Stage by days past due</b> (the staging run first sets the pre-qualitative stage from days past due and missed instalments), <b>2. Add the judgement</b> (an officer raises a trigger from an item, with reason, evidence and dates; a second person approves it; each item points to Stage 2 or Stage 3) and <b>3. The stage the ECL uses</b> (the run takes the higher of the two, so an approved trigger can move a loan up, never down; a closed trigger releases the loan after the governed cure period; a change takes effect when the month is staged again and the ECL re-run).</li>'
                . '<li><b>Groups table</b> with the columns <b>Order</b> (the order on screen, or a dash), <b>Group</b>, <b>What it covers</b>, <b>Items</b> (with <code>(N off)</code> when some items are switched off), <b>Stage 2</b> (gold count), <b>Stage 3</b> (red count) and <b>Actions</b>. Groups are listed by order, then name, fifteen per page with page links underneath.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Import CSV</b> (top right of the table): opens <b>Import SICR groups from a CSV file</b>.</li>'
                . '<li><b>Add group</b>: opens <b>Add a SICR group</b>.</li>'
                . '<li>Eye icon (<code>See the items in this group</code>): opens SICR Alert Items filtered to the group.</li>'
                . '<li>Pen icon (<code>Edit group</code>): opens <b>Edit SICR group</b>.</li>'
                . '<li>Bin icon (<code>Delete group</code>): asks <code>Delete this group?</code> with <code>The group "(name)" is removed. This cannot be undone.</code> and the buttons Cancel and Delete. It is greyed out, with the tooltip <code>A group with items cannot be deleted</code>, while the group holds items.</li>'
                . '<li>In the add or edit window: <b>Cancel</b>, and <b>Add group</b> or <b>Save changes</b> (<code>Saving...</code> while it runs; greyed out until the name is filled in).</li>'
                . '<li>In the import window: the <b>How to import</b> panel with <b>Check file</b> (reads the file and lists every problem by row, nothing is saved), <b>Download sample file</b>, <b>Download column guide</b> and a <code>Show the N columns (N required)</code> link; a dashed <code>Choose a CSV file</code> box; <b>Cancel</b> and <b>Start import</b> (<code>Importing...</code>).</li>'
                . '</ul>'
                . '<p>Every action on this page needs the <b>settings</b> permission. There is no maker-checker on groups: they describe the list, and no loan moves stage until a trigger raised on one of their items is approved by a second person.</p>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Group name</b>: required, up to 199 characters, for example <code>Forbearance</code>.</li>'
                . '<li><b>Order on screen</b>: 0 to 999; lower numbers list first.</li>'
                . '<li><b>What it covers</b>: optional description, for example <code>Restructures and concessions granted because the borrower is in difficulty.</code></li>'
                . '</ul>'
                . '<h4>To import groups from a CSV file</h4>'
                . '<ol>'
                . '<li>Click <b>Import CSV</b>, then <b>Download sample file</b> and fill in one group per line under the headings <code>name</code> and <code>description</code> (a file up to 10 MB).</li>'
                . '<li>Choose the file in the dashed box, click <b>Check file</b> to see any problems, then <b>Start import</b>.</li>'
                . '<li>A group whose name already exists is left as it is. The banner reads <code>Imported N groups.</code> If any row breaks a rule, nothing is imported and the problems are listed by row and column.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>New groups appear in the group filter of SICR Alert Items, in the Group box of the item form and as headings in the Warning sign (item) list of the trigger form. They change no loan on their own.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li>Empty table: <code>No SICR groups yet. Use Add group at the top right, or import a CSV.</code></li>'
                . '<li><code>Group created.</code>, <code>Group updated.</code>, <code>Group deleted.</code></li>'
                . '<li><code>Cannot delete group with items.</code> and <code>Missing headers: name, description</code>.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The bin is greyed out</b>: the group still holds items. Delete or move them on SICR Alert Items, or keep the group.</li>'
                . '<li><b>No access rights</b> when opening the tab: your role lacks the settings permission.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then Staging & SICR Rules, and click the SICR Groups tab.',
                'Click Add group.',
                'Type the Group name, the Order on screen and What it covers.',
                'Click Add group; the banner Group created. confirms it.',
                'Click the eye icon on the row to add items to the group on SICR Alert Items.',
            ],
            'images' => [
                'sicr-groups' => 'SICR Groups: the four figure cards, the How qualitative SICR works panel and the groups table with the eye, pen and bin icons',
            ],
            'routes' => ['sicr-groups.index', 'sicr-groups.store', 'sicr-groups.import', 'sicr-groups.update', 'sicr-groups.destroy'],
        ],

        'SICR alert items' => [
            'body' => '<p>A SICR item is one warning sign a credit officer can raise on a loan, for example <code>Restructured because of financial difficulty</code>. Each item belongs to a group, says which stage it moves the loan to (Stage 2 for a significant increase in credit risk, Stage 3 for evidence the loan is credit-impaired), whether evidence must be recorded, and the IFRS 9 paragraph it rests on. The list should read like the checklist the credit committee accepts. The page needs the <b>settings</b> permission. To reach it, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, then the <b>SICR Alert Items</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> SICR Alert Items, with the line <code>The warning signs a credit officer can raise on a loan, and the stage each one moves the loan to</code>.</li>'
                . '<li><b>Four figure cards</b>: <b>Items</b>, <b>Stage 2: significant increase</b>, <b>Stage 3: credit-impaired</b> and <b>Switched off</b> (hint <code>No new trigger can be raised on an item that is off</code>).</li>'
                . '<li><b>How qualitative SICR works</b>: the folded three-card panel explained in the SICR groups article.</li>'
                . '<li><b>Items table</b> with the columns <b>Group</b>, <b>Item and what it means</b> (the name, with the description under it), <b>Moves the loan to</b> (gold <code>Stage 2, SICR</code> or red <code>Stage 3, credit-impaired</code>), <b>Evidence</b> (<code>Required</code> or <code>Not required</code>), <b>IFRS 9 basis</b>, <b>Status</b> and <b>Actions</b>. Items are listed by group, stage and name, fifteen per page.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<ul>'
                . '<li><b>Filter by group</b> drop-down (<code>All groups</code> or one group) and <b>Filter by the stage implied</b> drop-down (<code>Stage 2 and Stage 3</code>, <code>Stage 2 items only</code>, <code>Stage 3 items only</code>). Each reloads the table at once; the eye icon on SICR Groups opens this page already filtered.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Import CSV</b>: opens <b>Import SICR items from a CSV file</b> (the same How to import panel, Check file, sample and column guide, dashed file box, Cancel and Start import as the groups import).</li>'
                . '<li><b>Add item</b>: opens <b>Add a SICR item</b>, preset to the group you are filtering by.</li>'
                . '<li><b>Status</b> badge: green <code>On</code> or grey <code>Off</code>. Click it to switch the item (tooltip <code>Switch off: no new triggers on this item</code> or <code>Switch on</code>). The banner reads <code>Item status updated.</code></li>'
                . '<li>Pen icon (<code>Edit item</code>): opens <b>Edit SICR item</b>.</li>'
                . '<li>Bin icon (<code>Delete item</code>): asks <code>Delete this item?</code> with <code>"(name)" is removed from the list. An item with triggers raised on it cannot be deleted; switch it off instead.</code></li>'
                . '<li>In the form: <b>Cancel</b>, and <b>Add item</b> or <b>Save changes</b> (greyed out until a group and a name are given).</li>'
                . '</ul>'
                . '<p>Every action needs the <b>settings</b> permission. Changing an item stage applies to triggers raised from then on; triggers already raised keep the stage they were approved with.</p>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Group</b>: required, chosen from the groups (<code>Choose a group</code>).</li>'
                . '<li><b>Moves the loan to</b>: <code>Stage 2: significant increase in credit risk</code> (default) or <code>Stage 3: credit-impaired</code>.</li>'
                . '<li><b>Item name</b>: required, up to 199 characters, written as an officer will read it.</li>'
                . '<li><b>What it means, in plain words</b>: optional, up to 2,000 characters; shown under the item in the trigger form.</li>'
                . '<li><b>IFRS 9 basis (optional)</b>: up to 120 characters, placeholder <code>e.g. IFRS 9 B5.5.17(g)</code>.</li>'
                . '<li><b>Evidence must be recorded when it is raised</b> (ticked by default) and <b>On (can be raised)</b> (ticked by default).</li>'
                . '</ul>'
                . '<h4>To import items from a CSV file</h4>'
                . '<ol>'
                . '<li>Download the sample file. The required headings are <code>group</code>, <code>name</code> and <code>active</code>; <code>stage</code> (2 or 3, blank means 2), <code>description</code>, <code>evidence_required</code> and <code>standard_reference</code> are optional. Active and evidence_required are yes or no.</li>'
                . '<li>Choose the file, click <b>Check file</b>, then <b>Start import</b>.</li>'
                . '<li>A group named in the file that does not exist is created. An item that already exists in its group is updated, not duplicated. The banner reads <code>Imported N items.</code>, or <code>Imported N items. Skipped row N (stage must be 2 or 3).</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Items that are On are offered in the <b>Warning sign (item)</b> list of the Raise a SICR trigger form, under their group, with their stage. An item that is Off stays on every trigger already raised on it but cannot be raised again.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No SICR items yet. Use Add item at the top right, or import a CSV.</code>, or with a filter on, <code>No items match the filter.</code></li>'
                . '<li><code>Item created.</code>, <code>Item updated.</code>, <code>Item deleted.</code></li>'
                . '<li><code>This item has triggers raised on it, so it cannot be deleted. Switch it off instead: no new triggers can then be raised on it.</code></li>'
                . '<li><code>Missing headers: group, name, active</code>.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The list looks short</b>: a group or stage filter is on. Choose <code>All groups</code> and <code>Stage 2 and Stage 3</code>.</li>'
                . '<li><b>An item is missing from the trigger form</b>: it is switched Off.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then Staging & SICR Rules, and click the SICR Alert Items tab.',
                'Click Add item.',
                'Choose the Group and whether the item Moves the loan to Stage 2 or Stage 3.',
                'Type the Item name, what it means and the IFRS 9 basis; decide whether evidence is required.',
                'Click Add item; the banner Item created. confirms it.',
                'Click the On or Off badge to switch an item that should no longer be raised.',
            ],
            'images' => [
                'sicr-items' => 'SICR Alert Items: the group and stage filters, the four figure cards and the items table with the stage, evidence, IFRS 9 basis and On or Off columns',
            ],
            'routes' => ['sicr-items.index', 'sicr-items.store', 'sicr-items.import', 'sicr-items.update', 'sicr-items.toggle', 'sicr-items.destroy'],
        ],

        'SICR trigger alerts' => [
            'body' => '<p>The SICR trigger register records the qualitative judgements that move a loan to a higher stage regardless of days past due. A credit officer raises a trigger on one loan, or on every loan of a customer, from the SICR item list, with the reason, the evidence and the dates it applies. A second person approves or rejects it. When the condition passes, the trigger is closed with a date and a reason. Nothing on this page writes to the loan book: the staging engine reads the approved triggers in force at each month-end the next time the month is staged, and the PD and ECL runs then read the stage it wrote. The page needs the <b>settings</b> permission. To reach it, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, then the <b>SICR Trigger Alerts</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> SICR Trigger Alerts, with the line <code>Qualitative warning signs raised on loans, approved by a second person, and the stage each one moves the loan to at the month-end</code>.</li>'
                . '<li><b>Five figure cards</b>: <b>In force today</b>, <b>Pointing to Stage 2</b>, <b>Pointing to Stage 3</b> (the triggers in force today by the stage they imply), <b>Awaiting approval</b> and <b>Closed or rejected</b>.</li>'
                . '<li><b>How qualitative SICR works</b>: the folded three-card panel explained in the SICR groups article.</li>'
                . '<li><b>Register table</b> with the columns <b>Loan</b> (the contract number and customer, or <code>All loans of (customer)</code> for a customer-wide trigger, with <code>Trigger N</code> under it), <b>Item and stage it points to</b> (the item, a gold <code>Stage 2</code> or red <code>Stage 3</code> badge and the group), <b>Reason and evidence</b> (the reason, <code>Evidence: ...</code>, and when present <code>Rejected: ...</code> or <code>Closed: ...</code>; a grey <code>Demo trigger, not a MAIIC assessment</code> badge marks a seeded example), <b>Dates and people</b> (<code>From (date) until closed</code> or <code>to (date)</code>, <code>Raised by</code>, <code>Approved by</code>, <code>Rejected by</code>, <code>Closed by</code>), <b>Status, stage at (latest month)</b> and <b>Actions</b>. Awaiting approval rows list first, fifteen per page.</li>'
                . '<li><b>Status</b> badges: <code>Awaiting approval</code> (gold), <code>Approved, not started</code> (green, start date ahead), <code>In force</code> (solid green), <code>Ended</code> (end date passed) and <code>Closed</code> (grey), and <code>Rejected</code> (red). Under the badge each loan shows <code>Days past due: Stage N</code>, then <code>After triggers: Stage N</code> in amber when the trigger changed it (or <code>Used: Stage N</code>) and <code>ECL</code> for the latest staged month; <code>Not in the (month) book</code> when the loan is not in it.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<ul>'
                . '<li><b>Which triggers to list</b>: <code>Current and waiting</code> (default), <code>Awaiting approval</code>, <code>Closed, ended or rejected</code> or <code>All triggers</code>.</li>'
                . '<li>Search box <code>Contract, customer or name</code> and the <b>Search</b> button.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Raise a trigger</b>: opens <b>Raise a SICR trigger</b>.</li>'
                . '<li>Tick icon (<code>Approve (second person)</code>), on a trigger awaiting approval: asks <code>Approve trigger N?</code> and states which loans move to at least which stage from which date. It is greyed out for the person who raised it (tooltip <code>You raised it: a second person approves</code>), and the server refuses a self-approval with <code>Maker-checker: the person who raised a trigger cannot approve it. A second person approves.</code> (An administrator may approve their own only where the Governance Centre allows the administrator override.)</li>'
                . '<li>Cross icon (<code>Reject</code>), on a trigger awaiting approval: asks <code>Reject trigger N?</code> and <code>Why is it rejected?</code>; the reason is written to the register and the audit log.</li>'
                . '<li>Calendar icon (<code>Close: the condition has passed</code>), on an approved trigger: opens <b>Close trigger N?</b> with <b>Closed on</b> (not before the start date) and <b>What changed</b>, and the buttons <b>Cancel</b> and <b>Close trigger</b>.</li>'
                . '<li>A dash means no action is left (rejected, closed or ended).</li>'
                . '</ul>'
                . '<p>Raising, approving, rejecting and closing all need the <b>settings</b> permission; approving needs a second person.</p>'
                . '<h4>Field by field (Raise a SICR trigger)</h4>'
                . '<ul>'
                . '<li><b>Warning sign (item)</b>: required; the items that are On, under their group, each with its stage. The item description and <code>Moves the loan to Stage N.</code> appear under the box.</li>'
                . '<li><b>One loan</b> or <b>Every loan of a customer</b>.</li>'
                . '<li><b>Loan ((month) book)</b>: type a contract number or customer name and pick from the list of loans in the latest staged month. Or <b>Customer</b>: choose the customer, listed with the number of loans.</li>'
                . '<li><b>Applies from</b>: required date, today by default. <b>Applies until (optional)</b>: <code>Leave blank to keep it until it is closed.</code></li>'
                . '<li><b>Reason: what happened to the borrower</b>: required.</li>'
                . '<li><b>Evidence: the document, its date and where it is filed</b>: required when the item needs evidence, otherwise marked <code>(optional)</code>.</li>'
                . '<li>Buttons <b>Cancel</b> and <b>Raise trigger</b> (<code>Saving...</code>).</li>'
                . '</ul>'
                . '<h4>To raise and approve a trigger</h4>'
                . '<ol>'
                . '<li>Click <b>Raise a trigger</b>, choose the item, the loan or customer, the dates, the reason and the evidence, and click <b>Raise trigger</b>. The banner reads <code>Trigger N raised. A second person approves it; it reaches the loan\'s stage when the month is next staged.</code></li>'
                . '<li>A second person filters <code>Awaiting approval</code>, reads the reason and evidence and clicks the tick. The banner reads <code>Trigger N approved. Stage the month again (and re-run PD and ECL) for it to change the loan\'s stage and ECL.</code></li>'
                . '<li>When the condition has passed, click the calendar icon, give the date and what changed, and click <b>Close trigger</b>. The banner reads <code>Trigger N closed. Month-ends after the closing date no longer see it; the cure period decides when the loan moves down.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>At each month-end the staging run takes the higher of the days-past-due stage and the stage of any approved trigger in force on that date. A trigger can move a loan up, never down. After a trigger is closed the loan stays at its stage for the governed cure period. A change reaches the loan when the month is staged again and the PD and ECL re-run; a locked month is never restaged. Every raise, approval, rejection and closure is written to the audit trail.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No triggers in this list.</code> (or <code>No trigger matches the search.</code>) <code>Use Raise a trigger at the top right when a borrower shows a warning sign from the SICR item list.</code></li>'
                . '<li>In the form: <code>The item "(name)" is switched off; switch it on before raising it.</code>, <code>The item "(name)" needs evidence: name the document, its date and where it is filed.</code>, <code>No loan with contract id "(id)" is in any month\'s loan book.</code>, <code>The end date cannot be before the date the trigger applies from.</code></li>'
                . '<li><code>Trigger N is approved, not waiting for approval.</code> and <code>The closing date cannot be before the trigger\'s start date (date).</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The trigger is approved but the loan has not moved</b>: the month has not been staged again since approval. Stage it, then re-run PD and ECL.</li>'
                . '<li><b>The tick is greyed out</b>: you raised the trigger. Ask a colleague to approve it.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then Staging & SICR Rules, and click the SICR Trigger Alerts tab.',
                'Click Raise a trigger and choose the Warning sign (item).',
                'Choose One loan or Every loan of a customer and pick the loan or customer.',
                'Set Applies from (and Applies until if known), write the reason and the evidence, and click Raise trigger.',
                'A second person filters Awaiting approval and clicks the tick to approve, or the cross to reject with a reason.',
                'Stage the month again and re-run PD and ECL; close the trigger with the calendar icon when the condition passes.',
            ],
            'images' => [
                'sicr-triggers' => 'SICR Trigger Alerts: the five figure cards, the list and search filters, and the register with the loan, item, reason, dates, status and approve, reject and close icons',
            ],
            'routes' => ['sicr-triggers.index', 'sicr-triggers.store', 'sicr-triggers.approve', 'sicr-triggers.reject', 'sicr-triggers.close'],
        ],

        'Transition profiles' => [
            'body' => '<p>A transition matrix measures how loans move between grades (here, stages) from one month to another. A transition profile tells the platform where to look: which table and column hold the grade at the start and at the end, which column identifies the same loan in both months, whether the matrix is weighted by balance or by count, and which categories the matrix rows and columns use. Profiles are set up once by the risk administrator; monthly users only pick a profile when they create a matrix. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>PD Model</b>, then the <b>Transition Profiles</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Transition Profiles, with the line <code>Where the grades come from when the monthly transition matrices are built</code>.</li>'
                . '<li><b>Transition profiles</b> panel, with <code>N profile(s). A profile says which table and column hold the grade at the start and end of each period.</code></li>'
                . '<li><b>Table</b> with the columns <b>Profile code</b>, <b>Short name</b> (with the description under it), <b>Start of period</b> and <b>End of period</b> (table.column, with the value type <code>text</code> or <code>number</code> under it), <b>Customer key</b> (the start client column), <b>Weighted by</b> (<code>Balance</code> or <code>Count</code>), <b>Created</b> and <b>Actions</b>. Fifteen per page.</li>'
                . '<li><b>Create page</b>, titled <code>Create transition profile</code> (the same title when editing), with the form described below and the <b>Save Transition Profile</b> button. An empty <b>Transition profiles</b> panel also appears under the form; it has no use there.</li>'
                . '<li><b>Configuration page</b> (from the cog), titled <code>Configuration</code>: a <b>Transition Profile Details</b> card (Profile Code, Short Name, Mapped Tables) with the <b>Re-Order Categories</b> button; then <b>Start Table Configuration</b> and <b>End Table Configuration</b>, each with a <code>Select a category</code> box, a <b>+ Add Category</b> button (it reads <code>Cancel</code> while the form is open) and a table with the columns <b>ID</b>, <b>Ordering Index</b>, <b>Matrix Category Name</b>, <b>Min Value</b>, <b>Max Value</b>, <b>Text Value</b>, <b>Default Flag</b> (<code>Yes</code> or <code>No</code>), <b>Created</b> and <b>Actions</b>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Create profile</b>: opens the create page. <b>Back</b> returns to the list.</li>'
                . '<li>Pen icon (<code>Edit profile</code>): opens the profile in the same form.</li>'
                . '<li>Cog icon (<code>Configure the grade mapping</code>): opens the configuration page.</li>'
                . '<li>Bin icon (<code>Delete profile</code>): asks <code>Delete profile (code)?</code> with <code>The transition profile is removed. Matrices already built from it are kept. This cannot be undone.</code></li>'
                . '<li>On the configuration page: <b>Edit</b> turns a row into boxes, then <b>Save</b> or <b>Cancel</b>; <b>Delete</b> removes a category at once, without a confirmation; <b>Save Category</b> saves the add form; <b>Re-Order Categories</b> opens <b>Reorder Categories</b> with two drag lists, <b>Start Categories</b> and <b>End Categories</b>, and <b>Cancel</b> and <b>Save</b> (<code>Saving...</code>).</li>'
                . '</ul>'
                . '<p>Who may act: any signed-in user can open and change profiles; the list of tables and columns on the create page is read only for users with the <b>reports.ifrs9</b> permission, so without it the table boxes stay empty. There is no maker-checker on profiles; the control sits on the matrices built from them, which are locked before use.</p>'
                . '<h4>Field by field (create page)</h4>'
                . '<ul>'
                . '<li><b>Profile Code</b>: required, unique, up to 15 characters, for example <code>M101</code>.</li>'
                . '<li><b>Short Name</b>: required, up to 60 characters; shown with the code in the matrix forms, for example <code>M101 - MAIIC stages</code>.</li>'
                . '<li><b>Description</b>: optional.</li>'
                . '<li><b>Mapped Start Table</b> and <b>Mapped End Table</b>: required; for the loan book both are <code>loan_books</code>. Choosing a table loads its columns into the boxes below.</li>'
                . '<li><b>Start Client Column</b> and <b>End Client ID Column</b>: required; the column that identifies the same loan in both months, normally <code>contract_id</code>.</li>'
                . '<li><b>Start Grading Column</b> and <b>End Grading Column</b>: required; the column holding the stage.</li>'
                . '<li><b>Value Type</b> and <b>End Value Type</b>: <code>Text</code> when the grade is a label such as 1, 2, 3; <code>Range</code> when categories are numeric bands.</li>'
                . '<li><b>Aggregation Criteria</b>: <code>Balance</code> (weighted by carrying amount, the MAIIC standard) or <code>Count</code>.</li>'
                . '</ul>'
                . '<h4>To configure the categories</h4>'
                . '<ol>'
                . '<li>Click the cog on the profile row.</li>'
                . '<li>Under <b>Start Table Configuration</b> click <b>+ Add Category</b>, set <b>Configure Period</b> to <code>Start</code>, type the <b>Category Name</b> (for example <code>1</code>), <b>Min Value</b> and <b>Max Value</b> (the same number for a text grade), a <b>Text Value</b> (for example <code>Performing</code>) and click <b>Save Category</b>. Repeat for 2 and 3.</li>'
                . '<li>Under <b>End Table Configuration</b> add the same categories with <b>Configure Period</b> set to <code>End</code>, tick <b>Default Flag</b> on the category that means default (3), and add <code>Paid</code> if settled loans should show as a column.</li>'
                . '<li>Click <b>Re-Order Categories</b>, drag the names into display order in both lists and click <b>Save</b>. The banner reads <code>Categories sorted successfully</code>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The profile appears in the <b>Transition Profile</b> box of the monthly and cumulative matrix forms. Every matrix built from it reads the mapped columns and lays out its grid in the saved order. The Default Flag decides which end column the PD is measured to, so set it before any matrix is calculated.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No transition profiles yet. Use Create profile at the top right to add one.</code></li>'
                . '<li><code>Profile created successfully.</code>, <code>Profile updated successfully.</code>, <code>The profile code has already been taken.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The table and column boxes are empty</b>: choose the table first; if the table list itself is empty, your role lacks reports.ifrs9.</li>'
                . '<li><b>The matrix shows no PD</b>: no end category has the Default Flag ticked.</li>'
                . '<li><b>A category will not save</b>: Min Value, Max Value and Text Value are all required.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then PD Model, and click the Transition Profiles tab.',
                'Click Create profile.',
                'Enter the Profile Code, Short Name and Description.',
                'Choose the mapped start and end tables, the client columns and the grading columns.',
                'Choose the value types and the Aggregation Criteria, then click Save Transition Profile.',
                'Click the cog on the new row, add the start and end categories, tick Default Flag on the default end category, and use Re-Order Categories.',
            ],
            'images' => [
                'tprofiles' => 'Transition Profiles: the profile table with the start and end table and column, customer key, weighting and the pen, cog and bin icons',
                'tprofiles-create' => 'Create transition profile: Profile Code, Short Name, Description, mapped tables, client and grading columns, value types and Aggregation Criteria',
            ],
            'routes' => ['transition-profiles.index', 'transition-profiles.create', 'transition-profiles.store', 'transition-profiles.edit', 'transition-profiles.update', 'transition-profiles.config', 'transition-profile.categories'],
        ],

        'Monthly transition matrix (PD)' => [
            'body' => '<p>The probability of default (PD) is the chance that a loan defaults within a horizon. A monthly transition matrix takes every loan of a segment at a start month, finds the same loans at an end month, and adds their balances into a grid of start stage by end stage. The share of each start stage that ended in the default category is that stage\'s PD. Risk analysts create, check and lock matrices; a locked matrix is then applied to the loan book. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>PD Model</b>, then the <b>Monthly Probability</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Monthly Probability, with the line <code>Monthly transition matrices: how loans move between stages, and the probability of default they give</code>.</li>'
                . '<li><b>Transition matrices</b> panel: <code>N matrix run(s), newest first. A closed matrix is locked for use in the ECL.</code></li>'
                . '<li><b>Table</b> with the columns <b>Matrix</b> (<code>#id</code>, with <code>Profile N, run N</code>), <b>Segment</b> (an icon chip, the portfolio or sector name and its level), <b>Source</b> (<code>System</code> or <code>Manual</code>, with <code>Payments: 1</code> when settled accounts are included or <code>Payments: 0</code> when excluded), <b>Periods</b> (start to end, with <code>N reporting period(s)</code>), <b>Years</b>, <b>Records</b> (loans transitioned, with <code>N updated</code>), <b>Transition balance</b> (with <code>N updated</code>), <b>Status</b>, <b>Last calculated</b> (date and time) and <b>Actions</b>. Fifteen per page.</li>'
                . '<li><b>Status</b>: green <code>Closed</code> (locked, the only state the loan book update is offered on) or gold <code>Draft</code>. Hover to read the comments.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<ul>'
                . '<li><b>Search</b> (placeholder <code>Segment, period or comment</code>; in this build it matches the matrix status and description), <b>From</b> and <b>To</b> dates (matrices whose start is on or after From and whose end is on or before To). The list reloads as you type or pick.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Get report</b>: opens <b>Export Transition Matrix Report</b>. <b>Create matrix</b>: opens the create page.</li>'
                . '<li>Table icon (<code>View the matrix</code>): opens the matrix read only.</li>'
                . '<li>Pen icon (<code>Edit the matrix</code>), draft only: opens the matrix with a box in every cell and <b>Save updates</b>; the notice reads <code>Matrix saved</code>.</li>'
                . '<li>Calculator icon (<code>Re-run the calculation</code>), draft only: asks <code>Re-run this calculation?</code> (<code>The draft matrix is recalculated from the loan books.</code>); the notice reads <code>Matrix re-run</code>.</li>'
                . '<li>On a manual matrix: the document icon (<code>No supporting document attached yet</code>) or green tick (<code>Download the supporting document</code>), and the paperclip (<code>Attach a supporting document</code>), which opens <b>Attach Supporting Document</b> (<code>Click to choose a file</code>, <code>Allowed Formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</code>, <code>Max Size: 50 MB</code>, <b>Cancel</b> and <b>Upload</b>).</li>'
                . '<li>Padlock (<code>Lock this PD</code>) on a draft, or open padlock (<code>Unlock this PD</code>) on a closed matrix: asks <code>Change the lock on this PD?</code> (<code>A closed (locked) matrix is the one the ECL uses; a draft can still be edited.</code>). Anyone may lock; only a user with the <b>admin</b> role may unlock (<code>Only an Administrator can unlock a closed PD record</code>). A second closed matrix for the same segment and window is refused (<code>A closed record already exists for the same reporting period.</code>).</li>'
                . '<li>Book icon (<code>Update the loan book</code>), closed only: opens <b>Update Loan Book Period</b> with a <b>Reporting Period</b> month, <b>Cancel</b> and <b>Update</b>.</li>'
                . '</ul>'
                . '<p>There is no delete icon. Apart from the unlock (admin role), the page checks no permission: any signed-in user can create, lock and apply a matrix.</p>'
                . '<h4>The matrix window</h4>'
                . '<ul>'
                . '<li>Header: <code>Transition matrix #id</code>, the window and segment, and badges for the horizon (for example <code>12-month (annual)</code>), the weighting (<code>Amount-weighted (balance)</code> or <code>Count of loans</code>), <code>PD: observed default rate</code>, the profile and <code>Read only</code> or <code>Editing</code>. <b>Download CSV</b> saves the grid.</li>'
                . '<li>Cards: <b>Starting balance</b> (or loans) and one <b>PD from (stage)</b> card per start stage.</li>'
                . '<li>Grid: one row per start stage (<code>From (stage)</code>), one column per end stage (the default column is tagged <code>default</code>), then <b>Row total</b> and <b>PD to (default)</b>; the footer row <b>Total at end</b>. Each cell shows the amount and its share of the row. The formula line under the grid works one row through, and the colour key reads <code>stayed or improved</code>, <code>moved to a worse stage</code>, <code>moved into or stayed in default</code>, <code>repaid or settled</code>.</li>'
                . '<li>Buttons <b>Close</b> and, when editing, <b>Save updates</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Create monthly transition matrix)</h4>'
                . '<ul>'
                . '<li><b>Transition Profile</b>: required, listed as code and short name.</li>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months, for example <code>2024-12</code> and <code>2025-12</code>. The years are worked out from the gap.</li>'
                . '<li><b>PD Start Stage Total Type</b>: <code>Include Settled Accounts</code> (default) or <code>Exclude Settled Accounts</code>.</li>'
                . '<li><b>PD Calculation Level</b>: <code>Portfolio</code> or <code>Sector</code>, then the portfolio (<code>Select Portfolio</code>) or sector (<code>Select Sector</code>).</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> (default, calculated from the loan book) or <code>Manual</code> (you type the cells and attach evidence).</li>'
                . '<li><b>Proceed to Matrix Entry</b> saves and calculates; <b>Back</b> returns to the list.</li>'
                . '</ul>'
                . '<h4>To lock and apply a matrix</h4>'
                . '<ol>'
                . '<li>Click the table icon and check each row total and <b>PD to (default)</b>.</li>'
                . '<li>Click the padlock and confirm. The banner reads <code>Probability Of Default (PD) record locked.</code></li>'
                . '<li>Click the book icon, choose the <b>Reporting Period</b> (for example <code>2025-12</code>) and click <b>Update</b>.</li>'
                . '<li>For every loan of the segment in that month, by its measured (post-qualitative) stage, the PD before FLI becomes the matrix probability of moving from that stage to Stage 3; Stage 3 loans get 100 percent; a lifetime PD is derived over the remaining term. The banner reads <code>Loan book PD updated successfully using backend scope logic</code>, the audit entry <code>PD Monthly Loan Book Update</code> is written and the month records this matrix as its PD source.</li>'
                . '</ol>'
                . '<h4>Filters and exports</h4>'
                . '<p><b>Get report</b> opens <b>Export Transition Matrix Report</b>: <b>Start Period</b>, <b>End Period</b>, <b>Export Format</b> (<code>CSV</code> or <code>Excel</code>), <b>Export Type</b> (<code>Summary (Current)</code>, one line per matrix, or <code>Matrix Format</code>, each grid in full), <b>Include Headers</b>, <b>Compress as ZIP</b>, and <b>Cancel</b> and <b>Export</b> (<code>Exporting...</code>). Only closed matrices overlapping the range are written, to <code>Transition_Matrix_Report_(start)_to_(end)</code>.</p>'
                . '<h4>What happens next</h4>'
                . '<p>Closed monthly matrices feed the cumulative matrix. The PD before FLI written to the loan book is the input of the forward-looking adjustment and of the ECL run.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No transition matrices yet. Use Create matrix at the top right to build the first one.</code></li>'
                . '<li><code>Update failed: No PD data with end stage 3 found for this transition matrix</code>.</li>'
                . '<li><code>Update failed: Reporting period (yyyy-mm) is locked: the transition-matrix PD cannot be written. ...</code> A locked month is never restated.</li>'
                . '<li><code>No locked periods found for the selected date range</code> (export).</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The book icon is missing</b>: the matrix is still a draft. Lock it first.</li>'
                . '<li><b>The download icon gives an error</b>: in this build the supporting-document download fails; keep a copy of the evidence until it is corrected.</li>'
                . '<li><b>PD is zero on every row</b>: no end category of the profile has the Default Flag ticked.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then PD Model, and click the Monthly Probability tab.',
                'Click Create matrix.',
                'Choose the Transition Profile, the Start Period and the End Period.',
                'Choose the PD Calculation Level and the portfolio or sector, leave Calculation Source as System, and click Proceed to Matrix Entry.',
                'Click the table icon to check the row totals and PD to default.',
                'Click the padlock and confirm to lock the matrix.',
                'Click the book icon, choose the Reporting Period and click Update.',
            ],
            'images' => [
                'tmatrix' => 'Monthly Probability: the transition matrices table with the search and date filters, Get report, Create matrix and the view, edit, re-run, lock and update icons',
                'tmatrix-create' => 'Create monthly transition matrix: Transition Profile, Start and End Period, PD Start Stage Total Type, PD Calculation Level and Calculation Source',
                'tmatrix-report' => 'The Export Transition Matrix Report window: periods, export format and type, Include Headers and Compress as ZIP',
            ],
            'routes' => [
                'transition-matrices.index', 'transition-matrices.create', 'transition-matrices.store', 'transition-matrices.show',
                'transition-matrices.update-loan-book', 'transition-matrices.entries.index', 'transition-matrices.entries.update',
                'transition-matrices.save-entries.update', 'transition-matrices.view', 'transition-matrices.edit',
                'transition-matrices.matrix.loanbook-update', 'transition-matrices.lock', 'transition-matrices.attach-file',
                'transition-matrices.download-file', 'transition-matrices.report-by-period', 'transition-matrices.delete',
            ],
        ],

        'Cumulative transition matrix' => [
            'body' => '<p>One monthly matrix looks at one observation window. The cumulative matrix adds together, cell by cell, the closed monthly matrices of a profile and segment across a range of months and works out the PD from the pooled totals, which is steadier. Risk analysts build and lock it after the monthly matrices for the range are closed. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>PD Model</b>, then the <b>Cumulative Probability</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Cumulative Probability, with the line <code>Monthly matrices combined over many periods into the cumulative probability of default</code>.</li>'
                . '<li><b>Cumulative matrices</b> panel: <code>N matrix run(s). A closed matrix is locked for use in the ECL.</code></li>'
                . '<li><b>Table</b> with the columns <b>Matrix</b> (<code>#id</code>, profile and run), <b>Segment</b>, <b>Source</b>, <b>Periods</b> (start to end, with <code>N transition period(s)</code>), <b>Records</b>, <b>Transition balance</b>, <b>Last period</b>, <b>Status</b> (green <code>Closed</code> or gold <code>Draft</code>) and <b>Actions</b>.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<p><b>Search</b> (placeholder <code>Segment or period</code>; in this build it matches the status), <b>From</b> and <b>To</b> dates.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Get report</b>: opens <b>Export Cumulative Transition Matrix Report</b> (the same fields as the monthly export); closed records overlapping the range are written to <code>Transition_Matrix_Cumulative_Report_(start)_to_(end)</code>. <b>Create matrix</b>: opens the create page.</li>'
                . '<li>Table icon (<code>View the matrix</code>): the matrix window, as on the monthly page, with <code>Cumulative transition matrix</code> and <code>PD: pooled default rate</code>.</li>'
                . '<li>Calendar icon (<code>Show the periods combined</code>): opens <b>Periods List</b>, a <b>Close</b> button and a table of the <b>Start</b> and <b>End</b> month of each monthly matrix pooled. <code>No periods recorded</code> shows when none are stored.</li>'
                . '<li>Pen (<code>Edit the matrix</code>) and calculator (<code>Re-run the calculation</code>, <code>The draft matrix is recalculated from the monthly matrices.</code>), draft only.</li>'
                . '<li>Padlock or open padlock: <code>Change the lock on this PD?</code>. Banner <code>Record locked.</code> Only the <b>admin</b> role may unlock (<code>Only an Administrator can unlock a closed record.</code>).</li>'
                . '<li>Book icon (<code>Update the loan book</code>), closed only: <b>Update Loan Book Period</b> with <b>Reporting Period</b>, <b>Cancel</b> and <b>Update</b>.</li>'
                . '</ul>'
                . '<p>No other permission is checked; any signed-in user can build and lock.</p>'
                . '<h4>Field by field (Create cumulative transition matrix)</h4>'
                . '<ul>'
                . '<li><b>Transition Profile</b>, <b>Start Period</b> and <b>End Period</b>: required. Every closed monthly matrix of the profile and segment overlapping the range is pooled.</li>'
                . '<li><b>PD Calculation Level</b> and <b>PD Element</b> (the portfolio or sector).</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> or <code>Manual</code>.</li>'
                . '<li><b>Proceed to Matrix Entry</b> builds the record; <b>Back</b> returns to the list.</li>'
                . '</ul>'
                . '<h4>To lock and apply the cumulative PD</h4>'
                . '<ol>'
                . '<li>Click the calendar icon to confirm which windows were pooled, then the table icon to check the PDs.</li>'
                . '<li>Click the padlock and confirm.</li>'
                . '<li>Click the book icon, choose the <b>Reporting Period</b> and click <b>Update</b>. The banner reads <code>Loan book PD updated successfully</code>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>In this build the update writes the pooled probability to the loan book PD value column of every loan of that month, by its calculated stage, whatever the segment (Stage 3 gets 100 percent), and records the matrix as the month\'s PD source. The ECL Calculation reads the PD before FLI or after FLI, which the monthly matrix and the internal grades write, not this column; use the monthly matrix (or internal grades) to set the PD the ECL uses. A locked reporting period refuses the update.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No cumulative matrices yet. Use Create matrix at the top right to build one from the monthly matrices.</code></li>'
                . '<li><code>Failed to create cumulative record. Please check logs.</code>: no closed monthly matrix exists for the profile and segment in the range.</li>'
                . '<li><code>Update failed: No PD data with end stage 3 found for this transition matrix</code>, and the locked-period message.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>Proceed to Matrix Entry does nothing</b>: a known defect in this build. The form sends the portfolio under a different name from the one the server requires, and the refusal (<code>The portfolio group field is required.</code>) is not shown under any box. Report it to the administrator.</li>'
                . '<li><b>Only an Administrator can unlock</b>: ask a user with the admin role.</li>'
                . '</ul>',
            'steps' => [
                'Lock every monthly matrix for the range first.',
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then PD Model, and click the Cumulative Probability tab.',
                'Click Create matrix, choose the Transition Profile, the Start and End Period, the level and the PD Element, and click Proceed to Matrix Entry.',
                'Click the calendar icon to see the periods combined and the table icon to check the PDs.',
                'Click the padlock and confirm to lock it.',
                'Click the book icon, choose the Reporting Period and click Update.',
            ],
            'images' => [
                'tmatrix-cumulative' => 'Cumulative Probability: the cumulative matrices table with the view, periods, edit, re-run, lock and update icons',
                'tmatrix-cumulative-create' => 'Create cumulative transition matrix: Transition Profile, Start and End Period, PD Calculation Level, PD Element and Calculation Source',
            ],
            'routes' => [
                'transition-matrix-cummulative.index', 'transition-matrix-cummulative.create', 'transition-matrix-cummulative.store',
                'transition-matrix-cummulative.rerun', 'transition-matrix-cummulative.update-loan-book',
                'transition-matrix-cumulative.lock', 'transition-matrix-cummulative.attach-file', 'transition-matrix-cummulative.download-file',
                'transition-matrix-cummulative.report-by-period',
            ],
        ],

        'Internal grades' => [
            'body' => '<p>An internal grading profile maps each credit grade to a PD term structure: the cumulative probability that a borrower of the grade defaults within 1, 2, ... years. MAIIC\'s grading comes from its ECL model workbook, which ties each of ten internal grades to an S&amp;P rating and S&amp;P\'s published cumulative default rates. Once a profile is active it can write PDs to the loan book by stage and remaining term, as an alternative to the transition matrix. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>PD Model</b>, then the <b>Internal Grades</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Internal Grades, with the line <code>Internal grading profiles: the grades and the PD term structure for each</code>.</li>'
                . '<li><b>Profiles table</b> with the columns <b>Profile</b> (name and description), <b>Grades</b>, <b>Max tenor</b> (<code>N years</code>), <b>Status</b> (green <code>Active</code> or amber <code>Draft</code>) and <b>Actions</b>. Clicking a row opens its PD matrix. Only one profile can be active at a time.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Import S&amp;P table</b> (upload icon): opens <b>Import the S&amp;P grading of an ECL model</b>.</li>'
                . '<li><b>New profile</b>: opens <b>Create Grading Profile</b>.</li>'
                . '<li>Table icon (<code>View the PD matrix</code>): opens <b>Cumulative PD by grade and year</b>.</li>'
                . '<li>List icon (<code>Manage grades</code>), pen (<code>Edit profile</code>) and bin (<code>Delete profile</code>), draft profiles only. Delete asks <code>Delete the profile (name) and its N grade(s)? This cannot be undone.</code> and is written to the audit trail.</li>'
                . '<li>Lock icon: red closed padlock (<code>Activate profile</code>) on a draft, green open padlock (<code>Deactivate profile</code>) on the active one. One click, no confirmation; activating one profile deactivates the others.</li>'
                . '<li><b>Update loan book</b> (active profile only, tooltip <code>Update the loan book with this profile\'s PDs</code>): opens <b>Update Loan Book (PD Assignment)</b>.</li>'
                . '</ul>'
                . '<p>Who may act: any signed-in user can import, create, activate and apply a profile; there is no maker-checker on activation. An active profile is locked against editing: its grades cannot be added, changed or deleted until it is deactivated (<code>The profile is active. Deactivate it before editing.</code>).</p>'
                . '<h4>The S&amp;P import</h4>'
                . '<ul>'
                . '<li>The window shows the <b>How to import</b> panel (the two sheets read and their columns, with <b>Download column guide</b>; there is no blank template because the file is the ECL model workbook itself), <b>What to upload</b> (the workbook as Finance keeps it; the sheets <b>ASS1-Internal Grades</b> and <b>PDs-S&amp;P</b> are read and nothing else) and <b>What happens</b>.</li>'
                . '<li><b>ECL model workbook (.xlsx)</b> chooser and <b>Profile name</b> (preset to <code>S&amp;P Emerging Markets (ECL model, December 2025)</code>).</li>'
                . '<li><b>Preview</b> (<code>Reading...</code>) shows the <b>Source</b> line and a table with <b>Grade</b>, <b>S&amp;P</b>, <b>Broad grade</b>, <b>PD range</b> and <b>Year 1</b> to <b>Year N</b>, and lists in amber any grade left out (a grade with no row of PDs is left out, not given a curve).</li>'
                . '<li><b>Create draft profile</b> saves it as a draft (<code>Draft profile "(name)" created with N grades. It changes no PD until it is activated.</code>). <b>Cancel</b> closes the window.</li>'
                . '</ul>'
                . '<h4>The PD matrix window (Cumulative PD by grade and year)</h4>'
                . '<ul>'
                . '<li>Header: the profile name with its Active or Draft chip and <code>The probability that a borrower of the grade defaults within 1, 2, ... N years.</code> Close it with the x or by clicking outside.</li>'
                . '<li>Columns: <b>Grade</b> (a round badge with the grade number, the description and <code>S&amp;P (rating)</code>), <b>Broad grade</b>, <b>PD range</b> (lower to upper) and, under <b>Cumulative PD within</b>, one column per year (<code>1 yr</code> to <code>N yr</code>); a dash means no value.</li>'
                . '<li>Colour key: <code>under 0.5%</code>, <code>0.5% to 2%</code>, <code>2% to 10%</code>, <code>10% to 30%</code>, <code>30% to under 100%</code>, <code>100% (default)</code>.</li>'
                . '<li>For an imported profile: <b>Source</b>, an <b>S&amp;P table</b> link that opens the S&amp;P page named in the workbook (it may ask for an S&amp;P login), and a <b>Left out of this profile</b> box. <code>No grades configured for this profile.</code> when empty.</li>'
                . '</ul>'
                . '<h4>The grades page</h4>'
                . '<ul>'
                . '<li>Title <code>(profile name) - Internal Grades</code>, the <b>+ Add Grade</b> button (draft only) and an info line <b>Profile Name</b>, <b>Max Tenor</b> and <b>Total Grades</b>.</li>'
                . '<li>One card per grade, <code>(code) - (name)</code> and <code>Click to view PD curve</code>, with the pen (edit), bin (delete, asks <code>Delete grade (code) and its PD curve? This cannot be undone.</code>) on drafts, and a chevron that opens the curve as <code>Year 1</code>, <code>Year 2</code> and so on. The curve is shown as a decimal fraction (0.015 is 1.5 percent). <code>No grades defined</code> when empty.</li>'
                . '<li><b>Add Internal Grade</b> or <b>Edit Internal Grade</b> window: <b>Grade Code *</b>, <b>Grade Name *</b>, <b>Probabilities per Year</b> (one box per year, a percentage 0 to 100), and <b>Reset</b> and <b>Save Grade</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Create Grading Profile)</h4>'
                . '<ul>'
                . '<li><b>Profile name</b>: required, placeholder <code>e.g., MAIIC corporate grades</code>. <b>Description</b>: optional.</li>'
                . '<li><b>Max Tenor Years</b>: 1 to 30, note <code>Maximum number of years for PD curves. All grades in this profile will follow this limit.</code></li>'
                . '</ul>'
                . '<h4>To write PDs to the loan book</h4>'
                . '<ol>'
                . '<li>Activate the profile with the padlock, then click <b>Update loan book</b>.</li>'
                . '<li>Choose the <b>Reporting Period</b>, the <b>Update Level</b> (<code>Portfolio</code> or <code>Sector</code>) and the portfolio or sector, and click <b>Update</b>.</li>'
                . '<li>Each loan is matched on the grade code it carries in the loan book. By its days-past-due stage (the stage set from E-Banker\'s instalment chart in force at that month-end), a Stage 1 loan gets the Year 1 PD, a Stage 2 loan the PD for the whole years covering its remaining term, and a Stage 3 loan 100 percent. The result is the PD before FLI.</li>'
                . '<li>A Stage 1 or 2 loan with no grade, no remaining term or no PD for its year is left unchanged and named. The banner reads <code>Loan books updated successfully in N minutes. Rows updated: N</code>, followed when needed by <code>Left unchanged, with no grade mapping, remaining tenor or PD for the tenor: N (contracts)</code>. The audit entry <code>Internal Grade Loan Book Update</code> is written.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The PDs land in the same PD before FLI column the monthly transition matrix writes, so use one method per period. The forward-looking adjustment and the ECL read them.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No grading profiles yet</code> and <code>Use New profile at the top right to create the first one.</code></li>'
                . '<li><code>Profile must be active before updating loan books.</code>; the locked-period message (<code>Reporting period (yyyy-mm) is locked: the internal-grade PD cannot be written. ...</code>).</li>'
                . '<li>Import: <code>A grading profile named "(name)" exists already; choose another name.</code>, <code>The workbook needs the sheets "ASS1-Internal Grades" and "PDs-S&amp;P", as the ECL model has them.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>Manage grades, the pen and the bin are missing</b>: the profile is active. Deactivate it to change it.</li>'
                . '<li><b>Many loans left unchanged</b>: the loans carry no grade code that the profile holds. Check the grade codes in the loan book.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then PD Model, and click the Internal Grades tab.',
                'Click Import S&P table, choose the ECL model workbook, check the Profile name and click Preview.',
                'Read the grades and PDs, then click Create draft profile.',
                'Click the profile row to check the Cumulative PD by grade and year.',
                'Click the red padlock to activate the profile.',
                'Click Update loan book, choose the Reporting Period, the Update Level and the portfolio or sector, and click Update.',
            ],
            'images' => [
                'internal-grades' => 'Internal Grades: the profiles table with Import S&P table, New profile and the matrix, grades, edit, delete, lock and Update loan book controls',
                'internal-grades-profile' => 'The grades page of the S&P profile: one card per grade with its PD curve',
                'internal-grades-matrix' => 'Cumulative PD by grade and year for the S&P profile, with the broad grade, PD range, the colour key and the S&P source',
            ],
            'routes' => [
                'internal-grading.profiles', 'internal-grading.profile.store', 'internal-grading.import-sp.preview', 'internal-grading.import-sp',
                'internal-grading.grades', 'internal-grading.grade.store', 'internal-grading.grade.update', 'internal-grading.grade.destroy',
                'internal-grading.profile.update', 'internal-grading.profile.destroy', 'internal-grading.matrix.view', 'internal-grading.profile.toggle',
                'internal-grading.loanbook.updateWithPD',
            ],
        ],

        'Monthly loss given default' => [
            'body' => '<p>Loss given default (LGD) is the share of a defaulted balance the lender expects to lose after cures and recoveries. A monthly LGD run takes every Stage 3 loan of a portfolio at a start month, follows it to an end month, and records what cured (back to Stage 1 or 2), what was repaid in part or in full, and what was disbursed. Cure rate is the cured balance over the opening Stage 3 balance; recovery rate is net recoveries over the same balance; LGD is (1 minus cure rate) times (1 minus recovery rate), between 0 and 1. Recoveries may be discounted. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>LGD Model</b>, then the <b>Monthly LGD</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Monthly LGD, with the line <code>Loss given default from cures and recoveries on Stage 3 loans, one run per window</code>.</li>'
                . '<li><b>LGD runs</b> panel: <code>N run(s). A closed run is locked for use in the ECL; amounts in MWK.</code></li>'
                . '<li><b>Table</b> with the columns <b>Window</b> (start to end month, with <code>Run (date and time)</code>; hover for who created it), <b>Segment</b> (the portfolio, or <code>Whole loan book</code>), <b>LGD %</b> (amber at 50 percent or more), <b>Cure rate %</b>, <b>Recovery rate %</b>, <b>Recovered</b>, <b>Stage 3 balance, start / end</b> (start above, end below), <b>Source</b> (<code>System</code> or <code>Manual</code>, with <code>Discounted</code> or <code>Not discounted</code>), <b>Status</b> (green <code>Closed</code> or gold <code>Active</code>) and <b>Actions</b>. Fifteen per page with a pager below.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Get report</b>: opens <b>Download LGD Monthly Report</b>. <b>Calculate LGD</b>: opens the create page.</li>'
                . '<li>Eye icon (<code>View the discounted payments</code>), discounted runs only: opens the Discounted Payments page described below.</li>'
                . '<li>Pen icon (<code>Edit this LGD</code>), manual runs only: reopens the form with the figures. A closed run refuses (<code>This LGD is closed and cannot be edited.</code>).</li>'
                . '<li>Padlock (<code>Lock this LGD</code>) or open padlock (<code>Unlock this LGD</code>): asks <code>Change the lock on this LGD?</code> (<code>A closed (locked) LGD is the one the ECL uses; an active run can still change.</code>). Banner <code>LGD record status updated.</code> Only the <b>admin</b> role may unlock (<code>Only an Administrator can unlock a closed LGD record</code>).</li>'
                . '<li>Book icon (<code>Update the loan book</code>), closed runs only: <b>Update Loan Book Period</b> with <b>Reporting Period</b>, <b>Cancel</b> and <b>Update</b> (<code>Update..</code>).</li>'
                . '<li>Bin icon (<code>Delete this LGD run</code>), active runs only: asks <code>Delete this LGD run?</code> (<code>The run and its figures are removed. This cannot be undone.</code>).</li>'
                . '</ul>'
                . '<p>Apart from the unlock (admin role), no permission is checked on this page.</p>'
                . '<h4>Field by field (Calculate monthly LGD)</h4>'
                . '<ul>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months, start not after end.</li>'
                . '<li><b>Portfolio Group</b>: required (<code>Select Portfolio</code>).</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> measures from the loan book; <code>Manual</code> opens <b>Manual LGD Calculation</b> when you click <b>Calculate</b>.</li>'
                . '<li><b>Enable Discounting</b>: shows <b>Discounting Configuration</b> with <b>Interest Rate Source</b>: <code>Original EIR (IFRS 9)</code> (only contracts with an approved and locked EIR are discounted), <code>Manual Rate</code> (adds <b>Interest Rate (%)</b>, placeholder <code>e.g., 10.5</code>) or <code>From Loan Book</code> (each contract\'s own rate).</li>'
                . '<li><b>Manual LGD Calculation</b> window: <b>Calculation Mode</b> <code>By Amount</code> (start and end Stage 3 totals, cured amounts, partly and fully recovered, disbursed) or <code>By Percentage</code> (cure and recovery rates); <b>Submit</b> and <b>Close</b>.</li>'
                . '</ul>'
                . '<h4>The Discounted Payments page</h4>'
                . '<p>Header <code>Monthly Loss Given Default / Discounted Payments - (portfolio)</code>: an <b>LGD Summary</b> card (Period, Discount Rate Source, Interest Rate, Average Days to Repayment), <b>Discounting Results</b> (Total Payments, Total Discounted, Discount Loss, Loss Rate) and a <b>Discounted Payment Details</b> table with <b>Export CSV</b>, a <b>Search Contract</b> box and the columns Contract ID, Reporting Period, Payment Period, Payment Amount, Interest Rate, Days, Discounted Amount, Discount Loss and Rate Source.</p>'
                . '<h4>To lock and apply an LGD</h4>'
                . '<ol>'
                . '<li>Click the padlock and confirm.</li>'
                . '<li>Click the book icon, choose the <b>Reporting Period</b> and click <b>Update</b>.</li>'
                . '<li>In this build the update writes this LGD to the LGD value of every loan of that month, in every portfolio, and records the run as the month\'s LGD source. It does not check the reporting-period lock. The ECL Calculation reads the Collection LGD (written by the cumulative LGD) or the Customer LGD (from collateral), not this column, so apply the cumulative LGD before the ECL run.</li>'
                . '</ol>'
                . '<h4>Filters and exports</h4>'
                . '<p><b>Get report</b>: <b>Start Period</b>, <b>End Period</b>, <b>Cancel</b> and <b>Download</b> (<code>Preparing...</code>). A zip named <code>LGD_Monthly_Report_(start)_to_(end)</code> with one CSV line per run is downloaded.</p>'
                . '<h4>What happens next</h4>'
                . '<p>Closed monthly runs are pooled by the cumulative LGD, which is what reaches the ECL as the Collection LGD.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No LGD runs yet. Use Calculate LGD at the top right to run the first one.</code></li>'
                . '<li><code>Loss Given Default record created successfully.</code>; <code>Start balance is zero ... check if data matches your filters (reporting period, stage, portfolio).</code></li>'
                . '<li><code>Cannot update loan books for an active LGD record.</code>; <code>No LGD records valid for selected period</code> (report).</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>Start balance is zero</b>: no loan of the portfolio had a calculated stage of 3 in the start month.</li>'
                . '<li><b>Discounting enabled but recoveries are zero</b>: run LGD payment tracking for the portfolio and window first (see the next article), and with Original EIR make sure the contracts have a locked EIR.</li>'
                . '<li><b>The banner says seconds</b>: the figure in <code>Loan books updated in N seconds.</code> is in minutes in this build.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then LGD Model, and click the Monthly LGD tab.',
                'Click Calculate LGD.',
                'Choose the Start Period, End Period and Portfolio Group.',
                'Set Calculation Source to System; tick Enable Discounting and choose the Interest Rate Source if recoveries are to be discounted.',
                'Click Calculate; the run appears as Active.',
                'Click the padlock to lock it, ready for the cumulative LGD.',
            ],
            'images' => [
                'lgd' => 'Monthly LGD: the LGD runs table with the window, segment, rates, balances, source and status, and the eye, pen, lock, update and delete icons',
                'lgd-create' => 'Calculate monthly LGD: Start Period, End Period, Portfolio Group, Calculation Source and Enable Discounting',
                'lgd-report' => 'The Download LGD Monthly Report window with Start Period and End Period',
            ],
            'routes' => [
                'loss-given-default.index', 'loss-given-default.create', 'loss-given-default.systemCalculation', 'loss-given-default.updateManual',
                'loss-given-default.storeManual', 'loss-given-default.delete', 'loss-given-default.update-loan-book', 'loss-given-default.editManual',
                'loss-given-default.lock', 'loss-given-default.attach-file', 'loss-given-default.download-file', 'loss-given-default.report-by-period',
                'loss-given-default.discounted-payments', 'loss-given-default.trigger-discounting',
            ],
        ],

        'Cumulative loss given default' => [
            'body' => '<p>The cumulative LGD pools the closed monthly LGD runs of a portfolio or sector across a range of months, adds their opening balances, cures and recoveries, and works out cure rate, recovery rate and LGD from the pooled totals. It is the LGD normally applied to the loan book for the ECL: the update writes it as each loan\'s Collection LGD. It can also be entered manually with a supporting document. To reach the page, open the sidebar and choose <b>Financial modelling</b>, then <b>IFRS 9 Model Setup</b>, then <b>LGD Model</b>, then the <b>Cumulative LGD</b> tab.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> Cumulative LGD, with the line <code>Monthly LGD runs combined over many windows, by portfolio or sector</code>.</li>'
                . '<li><b>Cumulative LGD runs</b> panel: <code>N run(s). A closed run is locked for use in the ECL; amounts in MWK.</code></li>'
                . '<li><b>Table</b> with the columns <b>Window</b> (hover for who created it), <b>Segment</b>, <b>LGD %</b>, <b>Cure rate %</b>, <b>Recovery rate %</b>, <b>Stage 3 balance, start / end</b>, <b>Source</b>, <b>Status</b> (green <code>Closed</code> or gold <code>Active</code>) and <b>Actions</b>.</li>'
                . '</ul>'
                . '<h4>Filters</h4>'
                . '<p><b>Level</b> (<code>All</code>, <code>Portfolio</code>, <code>Sector</code>), <b>From</b> and <b>To</b> months, and the <b>Apply</b> and <b>Reset</b> buttons.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Get report</b> and <b>Calculate LGD</b>.</li>'
                . '<li>Calendar icon (<code>Show the periods combined</code>), system runs: <b>Periods List</b> with <b>Close</b> and the <b>Start</b> and <b>End</b> of each pooled window.</li>'
                . '<li>Paperclip (<code>Attach a supporting document</code>) and document icon (<code>No supporting document attached yet</code>, or a green tick <code>Download the supporting document</code>), manual runs: the <b>Attach Supporting Document</b> window takes PDF, DOC, DOCX, XLS, XLSX, JPG or PNG up to 50 MB. A closed run refuses (<code>Cannot attach file to a closed LGD record.</code>).</li>'
                . '<li>Padlock or open padlock: <code>Change the lock on this LGD?</code>; only the <b>admin</b> role may unlock.</li>'
                . '<li>Book icon (<code>Update the loan book</code>), closed runs: <b>Update Loan Book Period</b> with <b>Reporting Period</b>, an <b>Include Customer LGD in Update</b> tick box, <b>Cancel</b> and <b>Update</b> (<code>Updating...</code>).</li>'
                . '<li>Bin icon (<code>Delete this LGD run</code>), active runs: <code>Delete this LGD run?</code>.</li>'
                . '</ul>'
                . '<p>Apart from the unlock (admin role), no permission is checked.</p>'
                . '<h4>Field by field (Calculate cumulative LGD)</h4>'
                . '<ul>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months, start not after end.</li>'
                . '<li><b>LGD Level</b>: <code>Portfolio</code> or <code>Sector</code>; then <b>Portfolio Group</b> or <b>Sector Code</b>.</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> pools closed monthly runs; <code>Manual</code> opens <b>Manual cumulative LGD calculation</b> (<b>Calculation Mode</b> by amount or by percentage, and <b>Periods Count</b>), with <b>Submit</b> and <b>Close</b>.</li>'
                . '</ul>'
                . '<h4>To lock and apply the cumulative LGD</h4>'
                . '<ol>'
                . '<li>Click the calendar icon to check the windows pooled, then the padlock to lock the run.</li>'
                . '<li>Click the book icon and choose the <b>Reporting Period</b>.</li>'
                . '<li>Leave <b>Include Customer LGD in Update</b> unticked to write this LGD as both the Collection LGD and the LGD of every loan of the segment that month. Tick it to write the Collection LGD and set the LGD to each loan\'s Customer LGD (from collateral) times this LGD.</li>'
                . '<li>Click <b>Update</b>. The banner reads <code>Loan books updated successfully in N minutes. Rows updated: N</code>. A locked reporting period refuses with the locked-period message.</li>'
                . '</ol>'
                . '<h4>Filters and exports</h4>'
                . '<p><b>Get report</b> opens a window titled <b>Download LGD Monthly Report</b> (the title is shared with the monthly page) with <b>Start Period</b>, <b>End Period</b>, <b>Calculation Level (Optional)</b> (<code>All Levels</code>, <code>Portfolio</code>, <code>Sector</code>, <code>Customer</code>), <b>Cancel</b> and <b>Download</b>. It downloads a zip named <code>LGD_Cummulative_Report_(start)_to_(end)</code> with one CSV line per cumulative run.</p>'
                . '<h4>What happens next</h4>'
                . '<p>The Collection LGD written here is the LGD the ECL Calculation uses when <b>LGD to use</b> is Collection LGD (the default) or Both.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No cumulative LGD runs yet. Use Calculate LGD at the top right to build one from the monthly runs.</code></li>'
                . '<li><code>Cumulative LGD calculated and saved successfully.</code>; <code>No closed LGD records found in the selected period.</code>; <code>Cannot update loan books for an active LGD record.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>No closed LGD records found</b>: lock the monthly runs for the range, at the same level as chosen here.</li>'
                . '<li><b>Please fill all amount fields.</b> (manual): every box of the chosen mode, and Periods Count, is required.</li>'
                . '</ul>',
            'steps' => [
                'Lock the monthly LGD runs for the range.',
                'Open the sidebar, choose Financial modelling, then IFRS 9 Model Setup, then LGD Model, and click the Cumulative LGD tab.',
                'Click Calculate LGD, choose the periods, the LGD Level and the portfolio or sector, set Calculation Source to System and click Calculate.',
                'Click the calendar icon to check the windows pooled, then the padlock to lock the run.',
                'Click the book icon, choose the Reporting Period, decide on Include Customer LGD in Update, and click Update.',
            ],
            'images' => [
                'lgd-cumulative' => 'Cumulative LGD: the Level, From and To filters and the runs table with the periods, attach, download, lock, update and delete icons',
                'lgd-cumulative-create' => 'Calculate cumulative LGD: periods, LGD Level, Portfolio Group or Sector Code and Calculation Source',
            ],
            'routes' => [
                'lgd-cummulative.index', 'lgd-cummulative.create', 'lgd-cummulative.system', 'lgd-cummulative.manual', 'lgd-cummulative.update-loanbook',
                'lgd-cummulative.delete', 'lgd-cummulative.lock', 'lgd-cummulative.attach-file', 'lgd-cummulative.download-file', 'lgd-cummulative.report-by-period',
            ],
        ],

        'LGD calculation logs and payment tracking' => [
            'body' => '<p>Behind a discounted monthly LGD sits a payment tracking table: for every Stage 3 contract of a portfolio it records, month by month, the opening and closing balance, the payment detected (a fall in balance), any disbursement, whether the contract cured within twelve months of default, and gaps in the extracts. The <b>LGD payment tracking</b> page builds that table, keeps a log of every run, and exports the detail. It is not on the sidebar. To reach it, open <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Settings</b>, click the <b>More settings</b> tab and choose <b>LGD payment tracking</b> (the link shows for users with the <b>settings</b> permission). The page itself checks no permission beyond being signed in.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b> LGD payment tracking, with the line <code>Runs that follow Stage 3 loans and their payments month by month: the cures and recoveries the loss given default is built from</code>.</li>'
                . '<li><b>Table</b> with the columns <b>ID</b>, <b>Portfolio</b>, <b>Period</b>, <b>Status</b>, <b>Duration</b>, <b>Contracts</b>, <b>Records</b>, <b>Payments</b>, <b>Cured</b>, <b>Source</b> (<code>manual</code>, <code>scheduled</code> or <code>api</code>), <b>Created</b> and <b>Actions</b>, with page links. Status is <code>Pending</code>, <code>Processing</code>, <code>Completed</code> or <code>Failed</code> (cancelled runs too).</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li>Round <b>?</b> (<code>Help for this screen</code>), <b>Refresh</b> (reloads the list while a run is in progress), <b>Filters</b> (opens the filter panel) and <b>New calculation</b>.</li>'
                . '<li>Eye (<code>View Details</code>): the <b>Calculation Details #N</b> page.</li>'
                . '<li>Redo arrow (<code>Recalculate</code>), completed runs: asks <code>Create a new recalculation based on this one?</code>.</li>'
                . '<li>Cross (<code>Cancel</code>), pending or processing runs: asks <code>Are you sure you want to cancel this calculation?</code>; the run is marked Failed.</li>'
                . '<li>Download arrow (<code>Export</code>), completed runs: a CSV <code>repayment_export_(id)_(date).csv</code>.</li>'
                . '<li>Bin (<code>Delete</code>), any run not processing: asks <code>Are you sure you want to delete this calculation and all its records?</code>.</li>'
                . '</ul>'
                . '<h4>Filters and exports</h4>'
                . '<p>The filter panel has <b>Portfolio</b> (<code>All Portfolios</code>), <b>Status</b> (<code>All Status</code>, <code>Completed</code>, <code>Processing</code>, <code>Pending</code>, <code>Failed</code>), <b>From Date</b>, <b>To Date</b>, <b>Reset</b> and <b>Apply</b>. On the details page, <b>Export Report</b> opens <b>Generate LGD Payment Report</b> (Portfolio, Start and End Period, Format CSV or Excel, an advanced <b>Exclude periods with zero payments</b> option, Cancel and Download).</p>'
                . '<h4>The New LGD Payment Tracking Calculation page</h4>'
                . '<p><b>Portfolio *</b>, <b>Start Period *</b>, <b>End Period *</b>, a <b>Recalculate even if calculation already exists</b> tick box, the <b>Processing Information</b> note, <b>Cancel</b> and <b>Start Calculation</b> (<code>Processing...</code>). Small windows finish at once; larger ones are queued (<code>Calculation queued for processing. You will be notified when complete.</code>) and run in the background.</p>'
                . '<h4>The Calculation Details page</h4>'
                . '<p><b>Back</b> and, when completed, <b>Export Report</b>; tiles Status, Duration, Period, Portfolio, Contracts Processed, Records Generated, Total Payments, Cured Contracts, Defaulted Amount, <b>Payment-Based Cure Rate (Recommended)</b>, Balance-Based Cure Rate, Recovered Amount Rate and Stage-Based Cure Rate (Contract Count); and the tabs <b>Recent Payments</b> (the ten largest payments), <b>Contracts</b> (tiles, Search Contract, Filter by Stage and Filter by Status; the contract list shows <code>No contracts found</code> in this version, use Export for the detail) and <b>Metadata</b> (Calculation ID, Parent Calculation, Triggered By, Trigger Source, Start Time, End Time, and any recalculation reason or error).</p>'
                . '<h4>What happens next</h4>'
                . '<p>A completed run fills the payment tracking table the monthly LGD reads when <b>Enable Discounting</b> is ticked, so run it for the same portfolio and window first.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No calculations found</code>.</li>'
                . '<li><code>A calculation already exists for this period. Use "Recalculate" if you want to run it again.</code></li>'
                . '<li><code>Calculation cancelled successfully.</code>, <code>Calculation deleted successfully.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>Status stays Pending</b>: the background queue worker is not running; ask the administrator.</li>'
                . '<li><b>Contracts is zero</b>: no contract of the portfolio had a calculated stage of 3 in the start month.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Access & Setup, then Settings, click More settings and choose LGD payment tracking.',
                'Click New calculation.',
                'Choose the Portfolio, Start Period and End Period matching the LGD window you will discount, and click Start Calculation.',
                'Click Refresh until the status reads Completed, then the eye to read the cure rates.',
                'Click the download arrow to export the tracking detail, then run the monthly LGD with Enable Discounting ticked.',
            ],
            'images' => [
                'lgd-calculations' => 'LGD payment tracking: the runs table with Refresh, Filters, New calculation and the view, recalculate, cancel, export and delete icons',
                'lgd-payment-report' => 'The LGD payment tracking list as opened from the payment report address',
            ],
            'routes' => [
                'lgd-calculations.index', 'lgd-calculations.create', 'lgd-calculations.compare', 'lgd-calculations.store', 'lgd-calculations.show',
                'lgd-calculations.recalculate', 'lgd-calculations.cancel', 'lgd-calculations.export', 'lgd-calculations.destroy',
                'lgd-payment-report.index', 'lgd-payment-report.generate', 'lgd-payment-report.monthly-summary', 'lgd-payment-report.contract-details',
                'lgd-payment-report.download-comparison',
            ],
        ],

    ],
];
