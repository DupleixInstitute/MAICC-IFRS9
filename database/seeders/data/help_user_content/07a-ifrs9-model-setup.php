<?php

/*
|--------------------------------------------------------------------------
| User Manual chapter: IFRS 9 Model Setup (part one)
|--------------------------------------------------------------------------
| Loaded by HelpContentSeeder. Chapter title => [article title => spec].
| Spec keys: body (HTML), steps (array), images (key => caption), routes.
| Part two of this chapter (forward-looking model and overlays) lives in
| 07b-ifrs9-model-setup.php; the loader merges both under one chapter.
*/

return [
    'IFRS 9 Model Setup' => [

        'Staging and SICR: quantitative thresholds' => [
            'body' => '<p>IFRS 9 places every loan in one of three stages. Stage 1 is a performing loan that carries a 12-month expected credit loss (ECL, the loss the lender expects over the next twelve months). Stage 2 is a loan with a significant increase in credit risk (SICR) since it was granted and carries a lifetime ECL. Stage 3 is a credit-impaired loan, also on lifetime ECL. The quantitative rule that separates the stages is days past due (DPD, the number of days a repayment is overdue). This page holds the institution level DPD boundaries and is maintained by the risk administrator; it needs the <b>settings</b> permission. To reach it, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, and choose <b>Quantitative Thresholds</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: IFRS 9 Staging Rules - Quantitative Thresholds, with the subtitle <code>Configure days past due thresholds for automatic stage classification</code> and a green <code>IFRS 9 Compliant</code> chip on the right.</li>'
                . '<li><b>Threshold Configuration</b> panel (dark header) with the line <code>Set quantitative rules for loan staging based on delinquency periods</code>. It holds the form described below.</li>'
                . '<li><b>Institution Type</b> box with the help text <code>Identifier for this set of staging rules</code> and the placeholder <code>e.g., default, commercial, retail</code>. The page always opens on the rule set named <code>default</code>.</li>'
                . '<li><b>Stage 1 Threshold</b> card (green, numbered 1) labelled <code>12-month ECL (Performing loans)</code>, a number box in days, and the live sentence <code>Loans with &le; 30 days past due</code> that updates as you type.</li>'
                . '<li><b>Stage 3 Threshold</b> card (red, numbered 3) labelled <code>Lifetime ECL (Credit-impaired)</code>, a number box in days, and the sentence <code>Loans with &ge; 90 days past due</code>.</li>'
                . '<li><b>Stage 2 (Calculated)</b> card (gold, numbered 2) labelled <code>Lifetime ECL (Significant increase in credit risk)</code>. It has no box: it shows <code>Loans between 30+ and 89 days past due</code>, worked out from the two values above.</li>'
                . '<li><b>Save Thresholds</b> button (green, bottom right, tick icon). It stays greyed out until the form is valid. While saving it reads <code>Saving...</code>.</li>'
                . '<li><b>IFRS 9 Staging Framework</b> panel underneath with three reference cards, Stage 1, Stage 2 and Stage 3, restating the standard in plain words. Nothing on this panel is editable.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Institution Type</b>: required, up to 255 characters. Keep <code>default</code> unless the administrator has agreed a separately named rule set. Saving with a new name creates a new rule set rather than replacing the default one.</li>'
                . '<li><b>Stage 1 Threshold</b>: required, a whole number of days from 0 to 365. Loans overdue by this many days or fewer stay in Stage 1. Example: <code>30</code>.</li>'
                . '<li><b>Stage 3 Threshold</b>: required, 0 to 365 days, and it must be larger than the Stage 1 value. Loans overdue by this many days or more are Stage 3. Example: <code>90</code>.</li>'
                . '<li>Everything in between is Stage 2. With the examples above a loan 45 days overdue is Stage 2.</li>'
                . '</ul>'
                . '<h4>How the DPD ladder is applied on import</h4>'
                . '<p>Staging itself happens when a loan book file is imported. The month end extract carries arrears in five buckets: 1 to 30, 31 to 90, 91 to 180, 181 to 270 and 271 to 360 days. The importer takes the lowest day of the highest bucket that holds a positive amount as the loan DPD (a loan with money only in the 91 to 180 bucket is treated as 91 DPD; a loan with nothing overdue is 0 DPD and Stage 1). It then compares that DPD with the governing row of the staging threshold table:</p>'
                . '<ul>'
                . '<li><b>DEFAULT</b> rule: Stage 2 from 31 DPD and Stage 3 from 181 DPD, effective from 1 January 2020. This reproduces the ladder the platform has always used.</li>'
                . '<li><b>LONG_TERM</b> rule for facilities of 36 months or longer: Stage 2 from 91 DPD and Stage 3 from 181 DPD, rebutting the 30 day presumption on the basis of the Reserve Bank of Malawi classification directive. It is stored with a far future effective date and does nothing until the CFO signs the rebuttal and the administrator brings the date forward.</li>'
                . '<li>Blank cells, dashes and thousand separators in the arrears columns are cleaned before the test, so a cell showing <code>-</code> counts as nothing overdue.</li>'
                . '</ul>'
                . '<p>The result is written to the loan as its pre-qualitative stage. Qualitative overrides from SICR trigger alerts are kept in a separate post-qualitative stage so that both can be seen side by side in the loan book.</p>'
                . '<h4>To change the thresholds</h4>'
                . '<ol>'
                . '<li>Leave <b>Institution Type</b> as <code>default</code>.</li>'
                . '<li>Type the Stage 1 and Stage 3 day counts and check the Stage 2 sentence reads as you intend.</li>'
                . '<li>Click <b>Save Thresholds</b>. A green toast reads <code>Staging rules updated successfully!</code> and the page reloads with the saved values.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The saved rule set is the documented policy for the institution and is what auditors see on this page. Stage boundaries drive which loans take a 12-month PD and which take a lifetime PD when the transition matrix and internal grade pages write PDs to the loan book, and the loan book stage filter, the dashboard stage cards and the stage migration reports all read the stage set on import.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>The Save Thresholds button is greyed out</b>: the Stage 1 value must be smaller than the Stage 3 value and neither may be negative; the Institution Type must not be blank.</li>'
                . '<li><code>Please check your input and try again.</code>: the server rejected a value, usually a non-numeric entry. The exact reason is listed under the field.</li>'
                . '<li><b>A loan is in a different stage from what the page suggests</b>: the import uses the DPD ladder described above (Stage 2 from 31 DPD, Stage 3 from 181 DPD under DEFAULT), and a SICR trigger may have moved the post-qualitative stage. Check the loan in the Loan Book.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then Staging & SICR Rules, and choose Quantitative Thresholds.',
                'Keep Institution Type as default.',
                'Enter the Stage 1 Threshold in days (for example 30) and the Stage 3 Threshold in days (for example 90).',
                'Read the Stage 2 (Calculated) card to confirm the middle band, for example Loans between 30+ and 89 days past due.',
                'Click Save Thresholds and wait for the toast Staging rules updated successfully!',
            ],
            'images' => [
                'staging' => 'The Quantitative Thresholds page: Institution Type, the Stage 1 and Stage 3 day boxes, the calculated Stage 2 band and the Save Thresholds button',
            ],
            'routes' => ['stageing-rules.index', 'stageing-rules.store'],
        ],

        'SICR groups' => [
            'body' => '<p>A significant increase in credit risk (SICR) is the IFRS 9 test that moves a loan from Stage 1 to Stage 2. Besides the days past due rule, MAIIC records qualitative reasons: sector distress, restructuring, a watch list event and so on. SICR groups are the headings under which those reasons are organised (for example Financial Ratios, Sector Events or Account Conduct). Each group holds alert items, and an alert item is what a credit officer selects when raising a trigger. Groups are set up once by the risk administrator (permission <b>settings</b>). To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, and choose <b>SICR Groups Setup</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: SICR Groups, with the subtitle <code>Manage Significant Increase in Credit Risk groupings</code> and a green chip showing the count, for example <code>3 Groups</code>.</li>'
                . '<li><b>SICR Group Management</b> heading with two buttons on the right: <b>Import CSV</b> (white, upload icon) and <b>Add New Group</b> (green, plus icon).</li>'
                . '<li><b>Existing SICR Groups</b> table with the columns <b>Group Name</b> (with a round badge showing the first letter), <b>Description</b> and <b>Actions</b>. Groups are listed newest first, twenty per page, with page links underneath.</li>'
                . '<li><b>Actions</b>: two text buttons per row, <b>Edit</b> (green) and <b>Delete</b> (red).</li>'
                . '<li><b>Empty state</b>: <code>No SICR groups found</code> and <code>Get started by creating your first group above.</code></li>'
                . '<li><b>Add New SICR Group</b> modal (or <b>Edit SICR Group</b> when editing) with the fields listed below and the buttons <b>Cancel</b> and <b>Create Group</b> (or <b>Update Group</b>). While saving the button reads <code>Saving...</code>.</li>'
                . '<li><b>Bulk Import SICR Groups</b> modal with a <b>CSV Format Requirements</b> note, a <b>Select CSV File *</b> chooser, and the buttons <b>Cancel</b> and <b>Upload CSV</b> (<code>Uploading...</code> while it runs).</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Group Name *</b>: required, up to 255 characters, help text <code>Short identifier for the group</code>, placeholder <code>e.g., Financial Ratios</code>. Example: <code>Sector Events</code>.</li>'
                . '<li><b>Description *</b>: a few sentences on what belongs in the group, placeholder <code>Describe what types of risk factors this group contains...</code>. The form will not submit until both boxes have text. Example: <code>Industry wide shocks such as drought, export bans or price collapses affecting a borrower sector.</code></li>'
                . '</ul>'
                . '<h4>To edit a group</h4>'
                . '<ol>'
                . '<li>Click <b>Edit</b> on the row. The modal opens as <b>Edit SICR Group</b> with the current name and description.</li>'
                . '<li>Change the text and click <b>Update Group</b>. The toast reads <code>Group updated successfully!</code></li>'
                . '</ol>'
                . '<h4>To delete a group</h4>'
                . '<ol>'
                . '<li>Click <b>Delete</b> on the row and answer <b>OK</b> to <code>Are you sure you want to delete this group?</code></li>'
                . '<li>The toast reads <code>Group deleted successfully!</code>. A group that still has alert items cannot be deleted; move or delete its items first.</li>'
                . '</ol>'
                . '<h4>To import groups from a CSV file</h4>'
                . '<ol>'
                . '<li>Prepare a CSV file (up to 10 MB) whose first row holds the headings <code>name</code> and <code>description</code>, one group per line.</li>'
                . '<li>Click <b>Import CSV</b>, choose the file under <b>Select CSV File *</b> and click <b>Upload CSV</b>.</li>'
                . '<li>Rows whose name already exists are skipped, so re-running an import is safe. The banner reads <code>Imported 5 groups.</code> and the toast <code>CSV imported successfully!</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Groups appear immediately in the <b>Filter by Group</b> list on the SICR Alert Items page and in the <b>SICR Group *</b> box of the Trigger SICR Alert form. They do not change any loan on their own; a loan only moves stage when a trigger is raised against an item in the group.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Cannot delete group with items.</code>: the group has alert items under it. Open SICR Alert Items, filter by the group and delete or re-home the items.</li>'
                . '<li><code>Missing headers: name, description</code>: the CSV first row does not contain those two headings (case does not matter). Fix the headings and upload again.</li>'
                . '<li><code>Failed to import CSV. Please check the file format.</code>: the file was not a .csv or .txt, or exceeded 10 MB.</li>'
                . '<li><code>Please check your input and try again.</code>: the name exceeded 255 characters or was blank.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then Staging & SICR Rules, and choose SICR Groups Setup.',
                'Click Add New Group.',
                'Type the Group Name, for example Sector Events.',
                'Type a Description explaining what risk factors the group holds.',
                'Click Create Group. The toast Group created successfully! confirms it and the new group appears at the top of the table.',
            ],
            'images' => [
                'sicr-groups' => 'The SICR Groups page with the Import CSV and Add New Group buttons and the Existing SICR Groups table',
            ],
            'routes' => ['sicr-groups.index', 'sicr-groups.store', 'sicr-groups.import', 'sicr-groups.update', 'sicr-groups.destroy'],
        ],

        'SICR alert items' => [
            'body' => '<p>An alert item is one specific qualitative risk factor inside a SICR group, for example <code>Debt-to-Equity Ratio</code> under Financial Ratios or <code>Restructured in last 12 months</code> under Account Conduct. When a credit officer raises a SICR trigger alert they pick the item, so the list should read like a checklist of the reasons the credit committee accepts as a significant increase in credit risk. Items are maintained by the risk administrator (permission <b>settings</b>). To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, and choose <b>SICR Alert Items</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: SICR Items, subtitle <code>Manage individual risk factors within SICR groups</code>, a green count chip such as <code>12 Items</code> and, when a filter is on, a gold chip <code>Filtered: (group name)</code>.</li>'
                . '<li><b>Filter by Group</b> drop-down listing <code>All Groups</code> and every group. Choosing a group reloads the table; a <b>Clear</b> button appears beside it to remove the filter.</li>'
                . '<li><b>Import CSV</b> (white) and <b>Add New Item</b> (green) buttons on the right of the filter bar.</li>'
                . '<li><b>SICR Items</b> table, headed <code>SICR Items in (group)</code> when filtered, with the columns <b>Group</b> (two letter badge plus name), <b>Item Name</b>, <b>Status</b> and <b>Actions</b>. Newest first, twenty per page.</li>'
                . '<li><b>Status</b>: a clickable pill, green <code>Active</code> or red <code>Inactive</code>. Clicking it flips the state at once.</li>'
                . '<li><b>Actions</b>: text buttons <b>Edit</b> (green) and <b>Delete</b> (red).</li>'
                . '<li><b>Empty state</b>: <code>No SICR items found</code>, then either <code>No items in this group yet.</code> or <code>Get started by creating your first item above.</code>, and an <b>Add First Item</b> button.</li>'
                . '<li><b>Add New SICR Item</b> modal (or <b>Edit SICR Item</b>) with the fields below, an <b>Item Status</b> note reading <code>New items are created as Active by default. You can toggle the status later using the status button in the table.</code>, and the buttons <b>Cancel</b> and <b>Create Item</b> (or <b>Update Item</b>).</li>'
                . '<li><b>Bulk Import SICR Items</b> modal with the CSV requirements, <b>Select CSV File *</b>, <b>Cancel</b> and <b>Upload CSV</b>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>SICR Group *</b>: required. Pre-selected to the group you are filtering by, otherwise the first group. Help text <code>Select the group this item belongs to</code>.</li>'
                . '<li><b>Item Name *</b>: required, up to 255 characters, placeholder <code>e.g., Debt-to-Equity Ratio, Current Ratio</code>. Write it as the officer will read it in the trigger form. Example: <code>Drought declared in borrower district</code>.</li>'
                . '</ul>'
                . '<h4>To switch an item on or off</h4>'
                . '<ol>'
                . '<li>Click the <b>Active</b> or <b>Inactive</b> pill in the <b>Status</b> column.</li>'
                . '<li>The pill changes colour and the toast reads <code>Item status updated successfully!</code>. An inactive item stays in history but should no longer be chosen for new triggers.</li>'
                . '</ol>'
                . '<h4>To edit or delete an item</h4>'
                . '<ol>'
                . '<li>Click <b>Edit</b>, change the group or name, and click <b>Update Item</b> (toast <code>Item updated successfully!</code>).</li>'
                . '<li>Click <b>Delete</b> and answer <b>OK</b> to <code>Are you sure you want to delete this item?</code> (toast <code>Item deleted successfully!</code>).</li>'
                . '</ol>'
                . '<h4>To import items from a CSV file</h4>'
                . '<ol>'
                . '<li>Prepare a CSV (up to 10 MB) with the headings <code>group</code>, <code>name</code> and <code>active</code>. Use <code>1</code> for active and <code>0</code> for inactive.</li>'
                . '<li>Click <b>Import CSV</b>, choose the file and click <b>Upload CSV</b>.</li>'
                . '<li>A group named in the file that does not yet exist is created automatically (without a description). An item that already exists under that group has its active flag updated rather than being duplicated. The banner reads <code>Imported 8 items.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Active items are offered in the <b>SICR Item *</b> box of the Trigger SICR Alert form as soon as their group is chosen. Raising a trigger against an item sets the SICR flag on the loan and moves a Stage 1 loan to post-qualitative Stage 2; see the SICR trigger alerts article.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Missing headers: group, name, active</code>: one of the three headings is absent from the first row of the CSV.</li>'
                . '<li><b>The Create Item button is greyed out</b>: both the group and the item name must be filled in.</li>'
                . '<li><b>The item list is empty although items exist</b>: a group filter is on. Click <b>Clear</b> or choose <code>All Groups</code>.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then Staging & SICR Rules, and choose SICR Alert Items.',
                'Click Add New Item.',
                'Choose the SICR Group the factor belongs to.',
                'Type the Item Name as officers should read it in the trigger form.',
                'Click Create Item. The toast Item created successfully! confirms it and the item appears as Active.',
            ],
            'images' => [
                'sicr-items' => 'The SICR Items page with the Filter by Group box, Import CSV and Add New Item buttons and the Group, Item Name, Status and Actions columns',
            ],
            'routes' => ['sicr-items.index', 'sicr-items.store', 'sicr-items.import', 'sicr-items.update', 'sicr-items.toggle', 'sicr-items.destroy'],
        ],

        'SICR trigger alerts' => [
            'body' => '<p>A SICR trigger alert is the record a credit officer raises when a qualitative event (a restructure, a sector shock, a watch list decision) means a customer has suffered a significant increase in credit risk regardless of days past due. Raising the alert flags the loan and moves a Stage 1 loan into Stage 2 in the loan book, so lifetime ECL is provided. The alert stays on file with who raised it, why, and any supporting document, until it is removed. Officers with the <b>settings</b> permission use it. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>Staging &amp; SICR Rules</b>, and choose <b>SICR Trigger Alerts</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: SICR Trigger Alerts (warning triangle icon), subtitle <code>Report significant increases in credit risk events</code>, a gold count chip such as <code>4 Triggers</code> and a red <code>Alert System</code> chip.</li>'
                . '<li><b>SICR Alert Management</b> heading with <code>Monitor and trigger alerts for significant credit risk changes</code> and the red <b>Trigger Alert</b> button on the right.</li>'
                . '<li><b>Trigger History</b> table with the columns <b>Timestamp</b> (date and time raised), <b>SICR Details</b> (group name over item name), <b>Customer</b> (customer ID or <code>N/A</code>), <b>Account</b>, <b>Affect All</b> (<code>Yes</code> or <code>No</code>), <b>Last Update</b>, <b>Triggered By</b> (user name with initial badge), <b>Status</b> and <b>Actions</b>. Newest first, twenty per page.</li>'
                . '<li><b>Status</b>: green <code>Alert Active</code> while the alert stands; grey <code>Removed</code> once it has been withdrawn.</li>'
                . '<li><b>Actions</b> for an active alert: <b>Update Book</b> (tooltip <code>Update Loan Book for this trigger</code>) and <b>Remove</b> (tooltip <code>Remove this alert permanently</code>). A removed alert shows <code>Removed (date)</code> instead.</li>'
                . '<li><b>Empty state</b>: <code>No triggers found</code>, <code>No SICR alerts have been triggered yet.</code> and a <b>Trigger First Alert</b> button.</li>'
                . '<li><b>Trigger SICR Alert</b> modal, subtitle <code>Report a significant increase in credit risk event</code>, opening with an <b>Important Notice</b>: <code>This alert will be logged and may trigger additional review processes. Ensure all information is accurate before submitting.</code> Buttons <b>Cancel</b> and <b>Trigger Alert</b> (<code>Triggering Alert...</code> while saving).</li>'
                . '<li><b>Update Loan Book</b> modal, subtitle <code>Apply trigger changes to the loan book</code>, showing <b>Trigger Details</b> (Account, SICR Group, SICR Item), an <b>Effective Period *</b> date and the buttons <b>Cancel</b> and <b>Update Loan Book</b> (<code>Updating...</code>).</li>'
                . '</ul>'
                . '<h4>Field by field (Trigger SICR Alert)</h4>'
                . '<ul>'
                . '<li><b>SICR Group *</b>: the heading the reason falls under. Changing it refreshes the item list.</li>'
                . '<li><b>SICR Item *</b>: the specific factor. Only items of the chosen group are listed.</li>'
                . '<li><b>Customer ID</b>: optional, placeholder <code>Type to search customers...</code>. Type two or more characters and a drop-down lists matching customer identifiers from the loan book with their loan count, for example <code>TBA  5460 loan(s)</code>. Click one to select it. Only customers present in the loan book can be chosen.</li>'
                . '<li><b>Affect all accounts under customer</b> tick box: available once a customer is selected. Ticked, the trigger is applied to every loan of that customer; unticked, only to the account below.</li>'
                . '<li><b>Effective Period</b>: optional date recorded on the trigger as the date it takes effect.</li>'
                . '<li><b>Account Number *</b>: required, placeholder <code>e.g., ACC-123456789</code>. Enter the contract ID exactly as it appears in the loan book.</li>'
                . '<li><b>Reason for Alert *</b>: required free text, placeholder <code>Provide detailed information about why this SICR alert is being triggered...</code>. This is the audit narrative; be specific.</li>'
                . '<li><b>Update Loan Book Now</b> tick box: ticked, the loan book is changed as soon as you submit; unticked, the alert is recorded only and you apply it later with <b>Update Book</b>.</li>'
                . '<li><b>Supporting Documentation (Optional)</b>: one file up to 5 MB (a committee minute, a letter, a valuation).</li>'
                . '</ul>'
                . '<h4>What the loan book update does</h4>'
                . '<ul>'
                . '<li>With <b>Affect all</b> ticked, every loan whose customer identifier matches is updated; otherwise the latest loan book row whose contract ID (or customer ID) equals the Account Number.</li>'
                . '<li>Each loan gets the SICR flag set. If its pre-qualitative stage is 1, its post-qualitative stage becomes 2. Loans already in Stage 2 or 3 are left where they are.</li>'
                . '<li>The pre-qualitative stage is never overwritten, so the loan book shows the DPD stage and the judgement stage side by side.</li>'
                . '</ul>'
                . '<h4>To apply an alert to the loan book later</h4>'
                . '<ol>'
                . '<li>Click <b>Update Book</b> on the row. Check the <b>Trigger Details</b>.</li>'
                . '<li>Choose the <b>Effective Period *</b> and click <b>Update Loan Book</b>. The toast reads <code>Loan book updated successfully!</code></li>'
                . '</ol>'
                . '<h4>To remove an alert</h4>'
                . '<ol>'
                . '<li>Click <b>Remove</b> and answer <b>OK</b> to <code>Are you sure you want to remove this alert? This action cannot be undone.</code></li>'
                . '<li>The status changes to <code>Removed</code>, the removal date is stamped, and the SICR trigger flag on the affected loans is cleared. The toast reads <code>Alert removed successfully!</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Loans moved to post-qualitative Stage 2 are picked up by the next PD application (they receive a lifetime PD instead of a 12-month PD) and by the ECL run, and they appear in the SICR trigger report under Reports. The alert, its reason, the user and the timestamps remain visible in Trigger History for auditors.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Customer is not active or does not exist in the loan book.</code>: the Customer ID typed is not in the loan book. Select the customer from the drop-down rather than typing it freely, or leave the box empty and rely on the Account Number.</li>'
                . '<li><b>The Trigger Alert button is greyed out</b>: group, item, account number and reason are all required.</li>'
                . '<li><b>Nothing changed in the loan book</b>: either <b>Update Loan Book Now</b> was not ticked (use <b>Update Book</b>), or the Account Number does not match a contract ID in the loan book.</li>'
                . '<li><code>Failed to update loan book. Please try again.</code>: the effective period was missing or the customer is no longer in the loan book.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then Staging & SICR Rules, and choose SICR Trigger Alerts.',
                'Click Trigger Alert.',
                'Choose the SICR Group and the SICR Item that describe the event.',
                'Type the first letters of the customer in Customer ID and pick the customer from the drop-down; tick Affect all accounts under customer if every loan is affected.',
                'Enter the Account Number (contract ID) and write the Reason for Alert.',
                'Tick Update Loan Book Now, attach any Supporting Documentation, and click Trigger Alert. The toast SICR alert triggered successfully! confirms it.',
            ],
            'images' => [
                'sicr-triggers' => 'The SICR Trigger Alerts page with the Trigger Alert button and the Trigger History table columns',
            ],
            'routes' => ['sicr-triggers.index', 'sicr-triggers.store', 'sicr-triggers.update-loan-book', 'sicr-triggers.remove-alert', 'sicr-triggers.customers'],
        ],

        'Transition profiles' => [
            'body' => '<p>A transition matrix measures how loans move between stages from one month to the next, and a transition profile tells the platform where to look: which table and columns hold the stage at the start and at the end, how balances are aggregated, and what the stage categories are. Profiles are technical set-up done once by the risk administrator with help from Dupleix; monthly users only select a profile when they create a matrix. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>PD Model Setup</b>, and choose <b>Transition Profiles</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Transition Profile, subtitle <code>List of Profiles</code>, and the dark <b>Create Profile</b> button at the top right.</li>'
                . '<li><b>Profiles table</b> with the columns <b>Id</b>, <b>Profile Code</b>, <b>Short Name</b>, <b>Start Table</b>, <b>Start Column</b>, <b>Start Col Type</b>, <b>End Table</b>, <b>End Column</b>, <b>End Col Type</b>, <b>Created On</b> and <b>Actions</b>.</li>'
                . '<li><b>Actions</b>: three icon buttons, the green pencil (edit the profile), the green configuration cog (open the category configuration) and the red bin (delete after <code>Are you sure you want to delete this profile?</code>).</li>'
                . '<li><b>Create page</b>, header <code>Transition Profile List / Create</code>, holding the form described below and the green <b>Save Transition Profile</b> button.</li>'
                . '<li><b>Configuration page</b>, header <code>Configuration</code>, with a <b>Transition Profile Details</b> card (Profile Code, Short Name, Mapped Tables), a <b>Re-Order Categories</b> button, and two sections, <b>Start Table Configuration</b> and <b>End Table Configuration</b>, each with a <code>Select a category</code> box, a <b>+ Add Category</b> button (which reads <code>Cancel</code> while the form is open) and a table with the columns <b>ID</b>, <b>Ordering Index</b>, <b>Matrix Category Name</b>, <b>Min Value</b>, <b>Max Value</b>, <b>Text Value</b>, <b>Default Flag</b> (<code>Yes</code> or <code>No</code>), <b>Created</b> and <b>Actions</b> (<b>Edit</b> and <b>Delete</b>; while editing, <b>Save</b> and <b>Cancel</b>).</li>'
                . '<li><b>Reorder Categories</b> dialog with two drag lists, <b>Start Categories</b> and <b>End Categories</b>, and the buttons <b>Cancel</b> and <b>Save</b> (<code>Saving...</code>).</li>'
                . '</ul>'
                . '<h4>Field by field (Create Profile)</h4>'
                . '<ul>'
                . '<li><b>Profile Code</b>: required, unique, up to 15 characters. Example <code>T2025</code> for the 2025 model year, or the shipped <code>M101</code>.</li>'
                . '<li><b>Short Name</b>: required, up to 60 characters, shown next to the code in matrix forms as <code>T2025 - MAIIC 2025</code>.</li>'
                . '<li><b>Description</b>: optional narrative of what the profile measures.</li>'
                . '<li><b>Mapped Start Table</b> and <b>Mapped End Table</b>: required; the database tables holding the opening and closing month. For the loan book both are <code>loan_books</code>. Choosing a table loads its columns into the boxes below.</li>'
                . '<li><b>Start Client Column</b> and <b>End Client ID Column</b>: required; the column that identifies the same loan in both months, normally <code>contract_id</code>.</li>'
                . '<li><b>Start Grading Column</b> and <b>End Grading Column</b>: required; the column holding the stage, normally <code>ifrs9stage_pre_qualitative</code>.</li>'
                . '<li><b>Value Type</b> and <b>End Value Type</b>: <code>Text</code> when the grade is a label such as 1, 2, 3; <code>Range</code> when categories are defined by numeric bands using Min Value and Max Value.</li>'
                . '<li><b>Aggregation Criteria</b>: <code>Balance</code> (the matrix is weighted by carrying amount, the MAIIC standard) or <code>Count</code>.</li>'
                . '</ul>'
                . '<h4>To configure the categories</h4>'
                . '<ol>'
                . '<li>Click the configuration cog on the profile row.</li>'
                . '<li>Under <b>Start Table Configuration</b> click <b>+ Add Category</b>. Set <b>Configure Period</b> to <code>Start</code>, type the <b>Category Name</b> (for example <code>1</code>), give <b>Min Value</b> and <b>Max Value</b> (for a text grade enter the same number in both, for example 1 and 1), a <b>Text Value</b> (for example <code>Performing</code>) and click <b>Save Category</b>. Repeat for <code>2</code> and <code>3</code>.</li>'
                . '<li>Under <b>End Table Configuration</b> add the same categories with <b>Configure Period</b> set to <code>End</code>. Tick <b>Default Flag</b> on the category that means default (Stage 3). Loans that are absent in the end month are counted under an end category named <code>Paid</code>; add it if you want settled loans shown as a column.</li>'
                . '<li>Click <b>Re-Order Categories</b>, drag the names into the order the matrix should display (1, 2, 3, Paid) in both lists and click <b>Save</b>. The banner reads <code>Categories sorted successfully</code>.</li>'
                . '</ol>'
                . '<h4>To edit a category in place</h4>'
                . '<ol>'
                . '<li>Click <b>Edit</b> on the row; the cells become boxes.</li>'
                . '<li>Change the name, values or default flag and click <b>Save</b>, or <b>Cancel</b> to discard.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The profile appears in the <b>Transition Profile</b> box on the monthly and cumulative matrix create pages. Every matrix built from it reads the mapped tables and columns and lays its rows and columns out in the category order you saved. The Default Flag decides which end columns count as default when the PD percentage is worked out, so it must be set before any matrix is calculated.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The profile code has already been taken.</code>: codes are unique; choose another.</li>'
                . '<li><code>There was an error submitting the form. Please check your inputs.</code>: a required box is empty; the message under each box names it.</li>'
                . '<li><b>The column boxes are empty</b>: choose the start and end tables first; the columns load after the table is picked.</li>'
                . '<li><b>The matrix shows zero PD on every row</b>: no end category has the Default Flag ticked.</li>'
                . '<li><b>Category form will not save</b>: Min Value, Max Value and Text Value are all required even for text grades.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then PD Model Setup, and choose Transition Profiles.',
                'Click Create Profile.',
                'Enter the Profile Code (for example T2025), the Short Name and a Description.',
                'Choose loan_books as the Mapped Start Table and Mapped End Table, contract_id as the client columns and ifrs9stage_pre_qualitative as the grading columns.',
                'Leave both value types as Text and Aggregation Criteria as Balance, then click Save Transition Profile.',
                'Back on the list, click the configuration cog, add the start categories 1, 2 and 3 and the end categories 1, 2, 3 and Paid, tick Default Flag on end category 3, then use Re-Order Categories to set the display order.',
            ],
            'images' => [
                'tprofiles' => 'The Transition Profile list showing the shipped M101 profile mapped to loan_books and ifrs9stage_pre_qualitative',
                'tprofiles-create' => 'The Create form: Profile Code, Short Name, Description, mapped tables, client and grading columns, value types and Aggregation Criteria',
            ],
            'routes' => ['transition-profiles.index', 'transition-profiles.create', 'transition-profiles.store', 'transition-profiles.edit', 'transition-profiles.update', 'transition-profiles.config', 'transition-profile.categories'],
        ],

        'Monthly transition matrix (PD)' => [
            'body' => '<p>Probability of default (PD) is the chance that a loan defaults within a given horizon. MAIIC measures it from history: a monthly transition matrix takes every loan in a segment at a start month, finds the same loans at an end month, and sums the balances that moved from each start stage to each end stage. The share of a start stage that ended in the default category is the PD for that stage. Risk analysts create and lock matrices; the ECL team applies them to the loan book. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>PD Model Setup</b>, and choose <b>Monthly Probability</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Transition Matrix Monthly Probability, with the green <b>Get Report</b> button and the dark <b>Create New Matrix</b> button.</li>'
                . '<li><b>Filters</b>: a <code>Search matrices...</code> box (searches status and comments), and <b>Start Date</b> and <b>End Date</b> pickers that keep matrices whose start period is on or after the first date and end period on or before the second.</li>'
                . '<li><b>Table columns</b> in order: <b>ID</b>, <b>Transition Profile Id</b>, <b>PD Level</b> (<code>PORTFOLIO</code> or <code>SECTOR</code>), <b>Segmentation</b> (portfolio name or sector code and name), <b>Calculation Source</b> (<code>System</code> or <code>Manual</code>), <b>Payments Included?</b>, <b>Start Period</b>, <b>End Period</b>, <b>Transition Years</b>, <b>Records Transitioned</b>, <b>Records Updated</b>, <b>Reporting Periods</b>, <b>No of Calc Runs</b>, <b>Transition Balance</b>, <b>Updated Balance</b>, <b>Status</b>, <b>Last Calc Date</b>, <b>Comments</b> and <b>Actions</b>. Ten per page, newest first.</li>'
                . '<li><b>Status</b>: gold <code>Draft</code> (editable, not yet approved) or red <code>Closed</code> (locked; the only state that can be applied to the loan book or exported).</li>'
                . '<li><b>Actions</b> icons: the grid icon <code>View</code>; on a draft the pen <code>Edit</code> and the calculator <code>Re-run</code>; on a manual matrix the document icon (<code>Download Support Doc</code>, or <code>Attach Support Doc First</code> when none is attached) and the paperclip <code>Attach File</code>; the padlock <code>Lock PD</code> on a draft or the open padlock <code>Unlock PD</code> on a closed matrix; and, on a closed matrix only, the book icon <code>Update Loan Book</code>. There is no delete icon on this page.</li>'
                . '<li><b>View / Edit modal</b>, titled <code>View Transition Matrix (Normal)</code> or <code>Edit Transition Matrix (Normal)</code>: a grid with <b>FROM/TO</b> down the side (each start category with its range and text), one column per end category, then <b>Total Start</b> and <b>PD%</b>, and a <b>TOTAL</b> footer. Buttons <b>Close</b> and, in edit mode, <b>Save Updates</b>.</li>'
                . '<li><b>Update Loan Book Period</b> modal: a <b>Reporting Period</b> month box with <b>Cancel</b> and <b>Update</b>.</li>'
                . '<li><b>Attach Supporting Document</b> modal: <code>Click to choose a file</code>, the selected file name and size, <code>Allowed Formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</code>, <code>Max Size: 5 MB</code>, <b>Cancel</b> and <b>Upload</b>.</li>'
                . '<li><b>Export Transition Matrix Report</b> modal: <b>Start Period</b>, <b>End Period</b>, <b>Export Format</b> (<code>CSV</code> or <code>Excel</code>), <b>Export Type</b> (<code>Summary (Current)</code> or <code>Matrix Format</code>), tick boxes <b>Include Headers</b> and <b>Compress as ZIP</b>, and <b>Cancel</b> and <b>Export</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Create Transition Matrix Monthly Probability)</h4>'
                . '<ul>'
                . '<li><b>Transition Profile</b>: required; listed as code and short name, for example <code>T2025 - MAIIC 2025</code>.</li>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months. For a twelve month observation ending June 2025 choose <code>2024-06</code> and <code>2025-06</code>. Transition Years is worked out from the gap (a one year gap gives 1).</li>'
                . '<li><b>PD Start Stage Total Type</b>: <code>Include Settled Accounts</code> (default) or <code>Exclude Settled Accounts</code>, recorded on the matrix.</li>'
                . '<li><b>PD Calculation Level</b>: <code>Portfolio</code> or <code>Sector</code>; then <b>Portfolio Group</b> or <b>Sector</b> appears for the segment.</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> calculates from the loan book; <code>Manual</code> creates the matrix so you can type balances in the edit grid and attach a supporting document.</li>'
                . '<li><b>Proceed to Matrix Entry</b> saves and calculates; <b>Back</b> returns to the list.</li>'
                . '</ul>'
                . '<h4>How the system calculation works</h4>'
                . '<p>For the chosen segment, every loan present in the start month is matched by contract ID to the end month. Its carrying amount is added to the cell for its start stage and end stage; a loan missing at the end month is counted under the end category <code>Paid</code>. Each cell probability is the cell balance divided by the start stage total, and the row <b>PD%</b> shown in the grid is the share of the start total that landed in categories flagged as default. The run counter and the last calculation date are updated each time.</p>'
                . '<h4>To review, correct and lock a matrix</h4>'
                . '<ol>'
                . '<li>Click the grid icon <code>View</code> and read the balances, <b>Total Start</b> and <b>PD%</b> per row.</li>'
                . '<li>If the loan book has changed, click the calculator <code>Re-run</code>, answer <b>OK</b> to <code>Are you sure you want to re-run this calculation?</code> and wait for <code>Matrix re-run completed successfully.</code></li>'
                . '<li>To overwrite a cell (manual evidence), click the pen <code>Edit</code>, type the balances and click <b>Save Updates</b> (<code>Matrix updated successfully.</code>). For a manual matrix, attach the evidence with the paperclip; the toast reads <code>File attached successfully</code>.</li>'
                . '<li>Click the padlock <code>Lock PD</code> and answer <b>OK</b> to <code>Are you sure you want to change the lock status of this Probability Of Default?</code>. The banner reads <code>Probability Of Default (PD) record locked.</code> and the status becomes <code>Closed</code>.</li>'
                . '</ol>'
                . '<h4>To apply the PDs to the loan book</h4>'
                . '<ol>'
                . '<li>On a closed matrix click the book icon <code>Update Loan Book</code>.</li>'
                . '<li>Choose the <b>Reporting Period</b>, for example <code>2025-06</code>, and click <b>Update</b>.</li>'
                . '<li>For every loan in that period and segment, Stage 1 and Stage 2 loans receive the matrix probability of moving from their stage to Stage 3 as their 12-month PD, Stage 3 loans receive 100 percent, and a lifetime PD is derived as 1 minus (1 minus PD) to the power of the remaining tenor. The banner reads <code>Loan book PD updated successfully using backend scope logic</code>, an audit entry <code>PD Monthly  Loan Book Update</code> is written and the period is registered with this matrix as its PD source.</li>'
                . '</ol>'
                . '<h4>To export a report by period</h4>'
                . '<ol>'
                . '<li>Click <b>Get Report</b>, set <b>Start Period</b> and <b>End Period</b>, choose the format and type, and click <b>Export</b>.</li>'
                . '<li>Only closed matrices overlapping the range are included. <code>Summary (Current)</code> gives one line per matrix; <code>Matrix Format</code> writes each matrix as a stage by stage grid followed by its balances. The file is named <code>Transition_Matrix_Report_(start)_to_(end)</code> and is zipped when <b>Compress as ZIP</b> is ticked.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Closed monthly matrices are the input to the cumulative matrix, which compounds them into the term structure. The PDs written to the loan book feed the ECL calculation, the internal grade bands and the dashboard weighted PD.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The start period field is required.</code> or <code>The end period field is required.</code>: both months must be chosen before proceeding.</li>'
                . '<li><code>A closed record already exists for the same reporting period.</code>: another matrix for the same segment and periods is already locked. Unlock it first or delete the duplicate through the administrator.</li>'
                . '<li><code>Only an Administrator can unlock a closed PD record</code>: ask an administrator to unlock.</li>'
                . '<li><code>Update failed: No PD data with end stage 3 found for this transition matrix</code>: the profile has no end category named 3 with balances, so there is nothing to apply. Check the category configuration and re-run.</li>'
                . '<li><code>No locked periods found for the selected date range</code>: the export range contains no closed matrix.</li>'
                . '<li><code>Start period cannot be later than end period</code>: swap the export dates.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then PD Model Setup, and choose Monthly Probability.',
                'Click Create New Matrix.',
                'Choose the Transition Profile (for example T2025 - MAIIC 2025), the Start Period and the End Period (for example 2024-06 and 2025-06).',
                'Set PD Calculation Level to Portfolio and pick the Portfolio Group, leave Calculation Source as System, and click Proceed to Matrix Entry.',
                'On the list, click the grid icon View to check the balances and PD% per stage.',
                'Click the padlock Lock PD and confirm; the status changes to Closed.',
                'Click the book icon Update Loan Book, choose the Reporting Period (for example 2025-06) and click Update.',
            ],
            'images' => [
                'tmatrix' => 'The Transition Matrix Monthly Probability list with Get Report, Create New Matrix, the search and date filters and one matrix per observation window',
                'tmatrix-create' => 'The create form: Transition Profile, Start Period, End Period, PD Start Stage Total Type, PD Calculation Level and Calculation Source',
                'tmatrix-report' => 'The create form showing the validation messages when Start Period and End Period are left empty',
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
            'body' => '<p>A single monthly matrix looks at one observation window. The cumulative matrix stacks every closed monthly matrix for a profile and segment across a range of months, adds the balances cell by cell, and works out the PD from the pooled totals. The result is a more stable, longer horizon PD (the basis of the 12-month and lifetime term structure) that is applied to the loan book by stage. Risk analysts build and lock it after the monthly matrices for the range are closed. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>PD Model Setup</b>, and choose <b>Cumulative Probability</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Transition Matrices Cummulative Probability (the spelling is as shown on screen), with the green <b>Get Report</b> and dark <b>Create New Matrix</b> buttons.</li>'
                . '<li><b>Filters</b>: <code>Search matrices...</code>, <b>Start Date</b> and <b>End Date</b>.</li>'
                . '<li><b>Table columns</b> in order: <b>ID</b>, <b>Transition Profile Id</b>, <b>PD Level</b>, <b>Segmentation</b>, <b>Calculation Source</b>, <b>Start Period</b>, <b>End Period</b>, <b>Transition Periods</b> (how many monthly windows were pooled), <b>Records Transitioned</b>, <b>Periods Cummulated</b>, <b>Transition Balance</b>, <b>Calc Runs</b>, <b>Last Calc Date</b>, <b>Status</b> and <b>Actions</b>. Ten per page.</li>'
                . '<li><b>Periods Cummulated</b>: an eye icon (<code>Show Periods</code>). Clicking it opens the <b>Periods List</b> modal, a table of <b>Start</b> and <b>End</b> months (for example <code>January 2024</code> to <code>January 2025</code>) for each monthly matrix that was pooled, with a <b>Close</b> button.</li>'
                . '<li><b>Status</b>: gold <code>Draft</code> or red <code>Closed</code>.</li>'
                . '<li><b>Actions</b>: the grid icon <code>View</code>; on a draft the pen <code>Edit</code> and the calculator <code>Re-run</code>; the padlock <code>Lock PD</code> or open padlock <code>Unlock PD</code>; and on a closed record the book icon <code>Update Loan Book</code>.</li>'
                . '<li><b>View / Edit modal</b>, titled <code>View Transition Matrix (Cumulative)</code> or <code>Edit Transition Matrix (Cumulative)</code>, with the same FROM/TO grid, <b>Total Start</b>, <b>PD%</b> and <b>TOTAL</b> row as the monthly view, and the buttons <b>Close</b> and <b>Save Updates</b>.</li>'
                . '<li><b>Update Loan Book Period</b> modal with a <b>Reporting Period</b> month, <b>Cancel</b> and <b>Update</b>.</li>'
                . '<li><b>Export Cumulative Transition Matrix Report</b> modal with <b>Start Period</b>, <b>End Period</b>, <b>Export Format</b>, <b>Export Type</b> (<code>Summary (Current)</code> or <code>Matrix Format</code>), <b>Include Headers</b>, <b>Compress as ZIP</b>, <b>Cancel</b> and <b>Export</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Create Cummulative Transition Matrix)</h4>'
                . '<ul>'
                . '<li><b>Transition Profile</b>: required; the same profile the monthly matrices were built on.</li>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months. Every closed monthly matrix for the profile whose window overlaps this range is pooled. Example: <code>2024-01</code> to <code>2025-10</code> pools ten monthly windows.</li>'
                . '<li><b>PD Calculation Level</b>: <code>Portfolio</code> or <code>Sector</code>, then <b>PD Element</b> lists the portfolios or the sectors (code and name).</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> or <code>Manual</code>.</li>'
                . '<li><b>Proceed to Matrix Entry</b> builds the record; <b>Back</b> returns to the list.</li>'
                . '</ul>'
                . '<h4>How the pooling works</h4>'
                . '<p>The platform finds the closed monthly matrices for the profile and segment overlapping the range, adds their cell balances into one grid, keeps a list of the pooled windows (shown by the eye icon), counts the records, and sets each start stage PD as the pooled default flagged balance divided by the pooled start total. The record is created as <code>Draft</code> with run number 1; <code>Re-run</code> deletes the pooled data and rebuilds it from the current closed monthly matrices.</p>'
                . '<h4>To lock and apply the cumulative PDs</h4>'
                . '<ol>'
                . '<li>Click the grid icon <code>View</code> and check <b>PD%</b> for stages 1 and 2 against the monthly figures.</li>'
                . '<li>Click the padlock <code>Lock PD</code> and answer <b>OK</b> to <code>Are you sure you want to change the lock status?</code>. The banner reads <code>Record locked.</code></li>'
                . '<li>Click the book icon <code>Update Loan Book</code>, choose the <b>Reporting Period</b> (for example <code>2025-06</code>) and click <b>Update</b>.</li>'
                . '<li>Every loan in that reporting period receives the pooled stage-to-3 probability for its calculated stage as its PD value (Stage 3 receives 100 percent). The banner reads <code>Loan book PD updated successfully</code> and the period is registered with this record as its PD source.</li>'
                . '</ol>'
                . '<h4>To export a report by period</h4>'
                . '<ol>'
                . '<li>Click <b>Get Report</b>, set the two periods, choose the format and type and click <b>Export</b>.</li>'
                . '<li>Closed cumulative records overlapping the range are written to <code>Transition_Matrix_Cumulative_Report_(start)_to_(end)</code>, zipped when requested.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The PD value written here is the one the ECL calculation multiplies by LGD and exposure, so lock the cumulative record before the ECL run for the period and keep it closed thereafter. The Periods List is the evidence of which windows were pooled and is worth attaching to the model validation file.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The portfolio group field is required.</code> appearing after you chose a portfolio in <b>PD Element</b>: this is a known defect in the current build (the form and the server disagree on the field name). Report it to the administrator, who can create the record directly.</li>'
                . '<li><code>Failed to create cumulative record. Please check logs.</code>: no closed monthly matrix exists for the profile and segment in the range. Lock the monthly matrices first.</li>'
                . '<li><code>A closed record already exists for the same reporting period.</code>: a cumulative record with the same start and end is already locked.</li>'
                . '<li><code>Only an Administrator can unlock a closed record.</code>: ask an administrator.</li>'
                . '<li><code>Update failed: No PD data with end stage 3 found for this transition matrix</code>: the pooled monthly matrices carry no end category 3; check the profile categories.</li>'
                . '<li><code>No locked periods found for the selected date range</code>: nothing closed in the export range.</li>'
                . '</ul>',
            'steps' => [
                'Lock every monthly matrix for the range first, then open the sidebar, expand IFRS 9 Model Setup, then PD Model Setup, and choose Cumulative Probability.',
                'Click Create New Matrix.',
                'Choose the Transition Profile, the Start Period and the End Period of the range to pool.',
                'Set PD Calculation Level and choose the PD Element (portfolio or sector), leave Calculation Source as System and click Proceed to Matrix Entry.',
                'Click the eye icon under Periods Cummulated to confirm which monthly windows were pooled, then the grid icon View to check PD%.',
                'Click the padlock Lock PD and confirm, then click the book icon Update Loan Book, choose the Reporting Period and click Update.',
            ],
            'images' => [
                'tmatrix-cumulative' => 'The cumulative list: one record pooling ten monthly windows from January 2024 to October 2025',
                'tmatrix-cumulative-create' => 'The create form: Transition Profile, Start Period, End Period, PD Calculation Level, PD Element and Calculation Source',
            ],
            'routes' => [
                'transition-matrix-cummulative.index', 'transition-matrix-cummulative.create', 'transition-matrix-cummulative.store',
                'transition-matrix-cummulative.rerun', 'transition-matrix-cummulative.update-loan-book', 
                'transition-matrix-cumulative.lock', 'transition-matrix-cummulative.attach-file', 'transition-matrix-cummulative.download-file',
                'transition-matrix-cummulative.report-by-period',
            ],
        ],

        'Internal grades' => [
            'body' => '<p>An internal grade is a letter band that summarises a borrower risk, A for the best through to G for the worst, and each grade carries a PD term structure: the probability of default for year 1, year 2 and so on up to the maximum tenor. The loan book carries each loan grade in its <code>internal_grade_code</code> column (loaded from the extract, or derived from the 12-month PD: below 2 percent is A, below 5 percent B, below 10 percent C, below 20 percent D, below 40 percent E, below 100 percent F, otherwise G). A grading profile maps every grade to its PD curve, and once active it can write PDs to the loan book by stage and tenor as an alternative to the transition matrix. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>PD Model Setup</b>, and choose <b>Internal Grades</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Internal Grading Profiles, subtitle <code>Configure internal grades and PD term structures</code>, and the green <b>+ New Profile</b> button.</li>'
                . '<li><b>Profile cards</b>, one per profile, showing the name with a status chip, green <code>Active</code> or gold <code>Draft</code>; a <b>Grades</b> count; a preview of the first five grade codes (then <code>+N</code>); and a footer with either a <b>Manage Grades</b> link (draft) or an <b>Update Loan Book</b> button (active) and the profile <code>ID</code>. Clicking anywhere on a card opens its grades page.</li>'
                . '<li><b>Card icons</b> at the top right: the eye (<code>View PD Matrix</code>) and the padlock (<code>Activate profile</code> on a draft, <code>Deactivate profile</code> on the active one). Only one profile can be active at a time; activating one deactivates the others.</li>'
                . '<li><b>Empty state</b>: <code>No grading profiles yet</code>, <code>Click the button above to create your first profile</code>.</li>'
                . '<li><b>Create Grading Profile</b> modal with <b>Profile Name</b>, <b>Description</b>, <b>Max Tenor Years</b> and the buttons <b>Cancel</b> and <b>Save</b>.</li>'
                . '<li><b>Internal Grade PD Matrix</b> modal: the profile name, <code>Max tenor N years</code>, and a table with a <b>Grade</b> column and one column per year, <b>Y1</b> to <b>Yn</b>, each cell a PD percentage or a dash when no value is set. <code>No grades configured for this profile.</code> appears when empty.</li>'
                . '<li><b>Update Loan Book (PD Assignment)</b> modal with <b>Reporting Period</b> (month), <b>Update Level</b> (<code>Portfolio</code> or <code>Sector</code>), then <b>Portfolio</b> or <b>Sector</b>, and the buttons <b>Cancel</b> and <b>Update</b>.</li>'
                . '<li><b>Grades page</b> (title <code>(profile name) - Internal Grades</code>): a <b>+ Add Grade</b> button (draft profiles only), an info line <code>Profile Name</code>, <code>Max Tenor</code> and <code>Total Grades</code>, and one card per grade showing <code>(code) - (name)</code> and <code>Click to view PD curve</code>. Each card has a gold pencil (edit, draft profiles only) and a chevron that expands the read-only curve as <code>Year 1</code>, <code>Year 2</code> and so on. <code>No grades defined</code> shows when the profile is empty.</li>'
                . '<li><b>Add Internal Grade</b> (or <b>Edit Internal Grade</b>) modal with <b>Grade Code *</b>, <b>Grade Name *</b>, a <b>Probabilities per Year</b> block with one box per year, and the buttons <b>Reset</b> and <b>Save Grade</b>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Profile Name</b>: required, up to 255 characters, for example <code>MAIIC Grades 2025</code>.</li>'
                . '<li><b>Description</b>: optional.</li>'
                . '<li><b>Max Tenor Years</b>: required whole number from 1 to 30, placeholder <code>e.g., 5</code>. The note reads <code>Maximum number of years for PD curves. All grades in this profile will follow this limit.</code> Choose the longest remaining tenor in the book, for example <code>10</code>.</li>'
                . '<li><b>Grade Code *</b>: required and unique within the profile, for example <code>A</code>.</li>'
                . '<li><b>Grade Name *</b>: required, for example <code>Minimal risk</code>.</li>'
                . '<li><b>Probabilities per Year</b>: one percentage per year (0 to 100, two decimals), for example Year 1 <code>1.50</code>, Year 2 <code>2.80</code>. They are stored as fractions and shown again as percentages.</li>'
                . '</ul>'
                . '<h4>To build a profile</h4>'
                . '<ol>'
                . '<li>Click <b>+ New Profile</b>, fill in the three boxes and click <b>Save</b> (banner <code>Profile created successfully</code>).</li>'
                . '<li>Click the card, then <b>+ Add Grade</b>. Enter the code, name and the PD for each year, and click <b>Save Grade</b> (<code>Grade and PD curve saved successfully</code>). Repeat for A to G.</li>'
                . '<li>Back on the profiles page click the eye <code>View PD Matrix</code> to read the whole term structure in one grid.</li>'
                . '<li>Click the padlock <code>Activate profile</code>. The chip turns to <code>Active</code>, the grades become read-only, and the <b>Update Loan Book</b> button appears.</li>'
                . '</ol>'
                . '<h4>To write PDs to the loan book</h4>'
                . '<ol>'
                . '<li>On the active profile click <b>Update Loan Book</b>.</li>'
                . '<li>Choose the <b>Reporting Period</b> (for example <code>2025-06</code>), the <b>Update Level</b> and the portfolio or sector, then click <b>Update</b>.</li>'
                . '<li>Each loan is matched by its grade code. Stage 1 loans receive the Year 1 PD, Stage 2 loans receive the PD for their remaining tenor in years, and Stage 3 loans receive 100 percent. Loans whose grade is not in the profile receive 0. The banner reads <code>Loan books updated successfully in 0.4 minutes. Rows updated: 5460</code>, an audit entry <code>Internal Grade Loan Book Update</code> records the scope, period and rows affected, and the period is registered with the profile and the run time.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The PDs land in the same loan book column the monthly transition matrix writes, so use one method per period. The ECL run, the dashboard weighted PD and the grade distribution report read the result. Every loan book update is audit logged with the user, scope and row count.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Profile must be active before updating loan books.</code>: activate the profile with the padlock first.</li>'
                . '<li><code>The grade code has already been taken.</code>: codes are unique per profile; edit the existing grade instead.</li>'
                . '<li><b>Rows updated is smaller than the segment</b>: only loans in that reporting period and portfolio or sector are touched; loans with no grade code still count as updated but receive 0.</li>'
                . '<li><b>+ Add Grade and the pencil are missing</b>: the profile is active. Deactivate it to change grades, then activate it again.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then PD Model Setup, and choose Internal Grades.',
                'Click + New Profile, enter the Profile Name, a Description and Max Tenor Years, and click Save.',
                'Click the new profile card, then + Add Grade; enter Grade Code A, its Grade Name and the PD percentage for each year, and click Save Grade. Repeat through G.',
                'Return to Internal Grading Profiles and click the eye View PD Matrix to check the term structure.',
                'Click the padlock Activate profile.',
                'Click Update Loan Book, choose the Reporting Period, the Update Level and the portfolio or sector, and click Update.',
            ],
            'images' => [
                'internal-grades' => 'The Internal Grading Profiles page before any profile exists, with the + New Profile button',
            ],
            'routes' => [
                'internal-grading.profiles', 'internal-grading.profile.store', 'internal-grading.grades', 'internal-grading.grade.store',
                'internal-grading.grade.update', 'internal-grading.matrix.view', 'internal-grading.profile.toggle', 'internal-grading.loanbook.updateWithPD',
            ],
        ],

        'Monthly loss given default' => [
            'body' => '<p>Loss given default (LGD) is the share of a defaulted balance the lender expects to lose after cures and recoveries. MAIIC measures it from the Stage 3 book: take every Stage 3 loan in a portfolio at a start month, follow it to an end month, and record what cured (returned to Stage 1 or 2), what was repaid in part or in full, and what was disbursed. Cure rate is cured balance over the opening Stage 3 balance; recovery rate is net recoveries over the same balance; and LGD is (1 minus cure rate) times (1 minus recovery rate). Recoveries may be discounted back to the reporting date. The risk team calculates and locks LGD each month and applies it to the loan book. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>LGD Model Setup</b>, and choose <b>Monthly LGD</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Monthly Loss Given Default, with the round green help mark, and the green buttons <b>Calculate LGD</b> (calculator icon) and <b>Get Report</b> (archive icon).</li>'
                . '<li><b>Table columns</b> in order: <b>Reporting Period</b> (start month to end month), <b>Portfolio</b>, <b>LGD %</b> (gold when 50 percent or more, green below), <b>Cure Rate %</b>, <b>Recovery Rate %</b>, <b>Calculated</b> (<code>system</code> or <code>manual</code>), <b>Status</b>, <b>Total Settled (Recovered Amount)</b>, <b>MKW Balance(Start)</b>, <b>MKW Balance(End)</b>, <b>Discounting</b> (<code>Enabled</code> or <code>Not Enabled</code>), <b>Created By</b>, <b>Created On</b> and <b>Actions</b>. Empty state: <code>No Monthly Loss-Given-Default</code>.</li>'
                . '<li><b>Status</b>: green <code>Active</code> (open for editing) or red <code>Closed</code> (locked; only closed records can be applied to the loan book).</li>'
                . '<li><b>Actions</b> icons: the gold pencil (<code>Edit LGD</code>, manual records only); the eye (<code>View Discounted Payments</code>, when discounting is enabled); the padlock <code>Lock LGD</code> on an active record or the open padlock <code>Unlock LGD</code> on a closed one; the red bin (active records only, asks <code>Are you sure?</code>); and the book <code>Update Loan Book</code> (closed records only).</li>'
                . '<li><b>Update Loan Book Period</b> modal: <b>Reporting Period</b> month, <b>Cancel</b> and <b>Update</b> (<code>Update..</code> while running).</li>'
                . '<li><b>Download LGD Monthly Report</b> modal: <b>Start Period</b>, <b>End Period</b>, <b>Cancel</b> and <b>Download</b> (<code>Preparing…</code>).</li>'
                . '<li><b>Create page</b>, title <code>Loss Given Default Monthly - Essentials</code>, with the fields below and the buttons <b>Calculate</b> and <b>Back</b>.</li>'
                . '<li><b>Manual LGD Calculation</b> modal (manual source only): <b>Calculation Mode</b> (<code>By Amount</code> or <code>By Percentage</code>), the amount boxes <b>Start Total Stage 3</b>, <b>End Total Stage 3</b>, <b>Cured Amount (1)</b>, <b>Cured Amount (2)</b>, <b>Partially Recovered</b>, <b>Fully Recovered</b> and <b>Disbursed Amount</b>, or the percentage boxes <b>Cure Rate (%)</b> and <b>Recovery Rate (%)</b>; buttons <b>Submit</b> and <b>Close</b>.</li>'
                . '<li><b>Discounted Payments</b> page (from the eye icon), header <code>Monthly Loss Given Default / Discounted Payments - (portfolio)</code>: an <b>LGD Summary</b> card (Period, Discount Rate Source, Interest Rate when manual, Average Days to Repayment) and <b>Discounting Results</b> (Total Payments, Total Discounted, Discount Loss, Loss Rate); a <b>Discounted Payment Details</b> table with <b>Export CSV</b>, a <b>Search Contract</b> box and the columns <b>Contract ID</b>, <b>Reporting Period</b>, <b>Payment Period</b>, <b>Payment Amount</b>, <b>Interest Rate</b>, <b>Days</b>, <b>Discounted Amount</b>, <b>Discount Loss</b> and <b>Rate Source</b>, fifteen per page with <b>Previous</b> and <b>Next</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Loss Given Default Monthly - Essentials)</h4>'
                . '<ul>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months; the start must not be after the end. For the June 2025 close observing one year, choose <code>2024-06</code> and <code>2025-06</code>.</li>'
                . '<li><b>Portfolio Group</b>: required; the portfolio whose Stage 3 loans are followed.</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> measures from the loan book; <code>Manual</code> opens the Manual LGD Calculation modal when you click <b>Calculate</b>.</li>'
                . '<li><b>Enable Discounting</b> tick box: reveals the <b>Discounting Configuration</b> block with <b>Interest Rate Source</b>: <code>Original EIR (IFRS 9)</code> (discounts each payment at the contract original effective interest rate; only contracts with an approved and locked EIR are discounted, other payments are excluded), <code>Manual Rate</code> (adds <b>Interest Rate (%)</b>, for example <code>10.5</code>) or <code>From Loan Book</code> (each contract own rate).</li>'
                . '</ul>'
                . '<h4>How the system calculation works</h4>'
                . '<ul>'
                . '<li>Opening balance is the carrying amount of every loan in the portfolio with a calculated stage of 3 in the start month. Each loan is matched by contract ID in the end month; a missing loan has an end balance of 0 and counts as fully recovered.</li>'
                . '<li>A loan ending in Stage 1 or Stage 2 is cured for its opening balance (<code>Cured Amount (1)</code> or <code>(2)</code>). A balance that fell to zero is fully recovered; one that fell but not to zero is partly recovered; one that rose is a disbursement. Net recoveries are partial plus full recoveries minus disbursements.</li>'
                . '<li>With discounting, recoveries are instead taken from the payment tracking table (built on the Payment Tracking Calculations page) and each payment is discounted as payment divided by (1 plus rate) to the power of days over 365, days being from the payment month end to the reporting month end, capped at ten years. The discount loss is kept separately.</li>'
                . '<li>LGD is (1 minus cure rate) times (1 minus recovery rate), floored at 0 and capped at 1, and the record is created as <code>Active</code> with the banner <code>Loss Given Default record created successfully.</code></li>'
                . '</ul>'
                . '<h4>To enter a manual LGD</h4>'
                . '<ol>'
                . '<li>Set <b>Calculation Source</b> to <code>Manual</code> and click <b>Calculate</b>.</li>'
                . '<li>Choose <b>Calculation Mode</b>. <code>By Amount</code>: type the seven balances and the cure rate, recovery rate and LGD are worked out for you. <code>By Percentage</code>: type <b>Cure Rate (%)</b> and <b>Recovery Rate (%)</b> directly.</li>'
                . '<li>Click <b>Submit</b>. The record is listed as <code>manual</code> and can be corrected later with the gold pencil until it is locked.</li>'
                . '</ol>'
                . '<h4>To lock and apply an LGD</h4>'
                . '<ol>'
                . '<li>Click the padlock <code>Lock LGD</code> and answer <b>OK</b> to <code>Are you sure you want to change the lock status of this LGD?</code>. The banner reads <code>LGD record status updated.</code> and the status becomes <code>Closed</code>.</li>'
                . '<li>Click the book icon <code>Update Loan Book</code>, choose the <b>Reporting Period</b> (for example <code>2025-06</code>) and click <b>Update</b>. Every loan in that reporting period receives this LGD as its LGD value and the period is registered with the record. The banner reads <code>Loan books updated in 0.02 seconds.</code></li>'
                . '</ol>'
                . '<h4>To download the monthly LGD report</h4>'
                . '<ol>'
                . '<li>Click <b>Get Report</b>, choose <b>Start Period</b> and <b>End Period</b> and click <b>Download</b>.</li>'
                . '<li>A zip named <code>LGD_Monthly_Report_(start)_to_(end)</code> containing a CSV with one line per record (portfolio, LGD, validity, balances, cure and recovery figures, source and status) is downloaded.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Closed monthly records are the input to the cumulative LGD, which pools several months. The LGD value written to the loan book is multiplied by PD and exposure in the ECL run and drives the coverage figures on the dashboard and the LGD and Collateral report.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The start period field is required.</code> and <code>The end period field is required.</code>: both months must be chosen.</li>'
                . '<li><code>Start balance is zero</code> followed by <code>check if data matches your filters (reporting period, stage, portfolio).</code>: no loan in the portfolio had a calculated stage of 3 in the start month. Check the loan book for that month.</li>'
                . '<li><code>Cannot update loan books for an active LGD record.</code>: lock the record first.</li>'
                . '<li><code>Only an Administrator can unlock a closed LGD record</code>: ask an administrator.</li>'
                . '<li><code>This LGD is closed and cannot be edited.</code>: unlock it (administrator) before using the pencil.</li>'
                . '<li><code>No LGD records valid for selected period</code>: the report range holds no record.</li>'
                . '<li><b>Discounting enabled but recoveries are zero</b>: run a Payment Tracking Calculation for the portfolio and range first, and with <code>Original EIR (IFRS 9)</code> make sure the contracts have a locked EIR.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand IFRS 9 Model Setup, then LGD Model Setup, and choose Monthly LGD.',
                'Click Calculate LGD.',
                'Choose the Start Period and End Period (for example 2024-06 and 2025-06) and the Portfolio Group.',
                'Set Calculation Source to System; tick Enable Discounting and choose the Interest Rate Source if recoveries are to be discounted.',
                'Click Calculate. The new record appears as Active with its LGD %, Cure Rate % and Recovery Rate %.',
                'Click the padlock Lock LGD and confirm, then click the book icon Update Loan Book, choose the Reporting Period and click Update.',
            ],
            'images' => [
                'lgd' => 'The Monthly Loss Given Default list with Calculate LGD and Get Report and the reporting period, portfolio, rate and status columns',
                'lgd-create' => 'The Loss Given Default Monthly - Essentials form: Start Period, End Period, Portfolio Group, Calculation Source and Enable Discounting',
                'lgd-report' => 'The same form showing the validation messages when the periods are left empty',
            ],
            'routes' => [
                'loss-given-default.index', 'loss-given-default.create', 'loss-given-default.systemCalculation', 'loss-given-default.updateManual',
                'loss-given-default.storeManual', 'loss-given-default.delete', 'loss-given-default.update-loan-book', 'loss-given-default.editManual',
                'loss-given-default.lock', 'loss-given-default.attach-file', 'loss-given-default.download-file', 'loss-given-default.report-by-period',
                'loss-given-default.discounted-payments', 'loss-given-default.trigger-discounting',
            ],
        ],

        'Cumulative loss given default' => [
            'body' => '<p>One monthly LGD reflects one observation window. The cumulative LGD pools the closed monthly records for a portfolio or sector across a range of months, adds their opening balances, cures and recoveries, and works out cure rate, recovery rate and LGD from the pooled totals. The pooled figure is steadier and is the one normally applied to the loan book for the ECL run. It can also be entered manually with a supporting document. To reach the page, open the sidebar, expand <b>IFRS 9 Model Setup</b>, then <b>LGD Model Setup</b>, and choose <b>Cumulative LGD</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Cummulative Loss Given Default (spelling as on screen), the round green help mark, and the green buttons <b>Calculate LGD</b> and <b>Get Report</b>.</li>'
                . '<li><b>Filter bar</b>: <b>Calculation Level</b> (<code>All</code>, <code>Portfolio</code> or <code>Sector</code>), <b>Start Date</b> and <b>End Date</b> months, the green <b>Apply Filters</b> and dark <b>Reset</b> buttons.</li>'
                . '<li><b>Table columns</b> in order: <b>Reporting Period</b>, <b>LGD Level</b> (green <code>PORTFOLIO</code> or gold <code>SECTOR</code>), <b>Segmentation</b> (portfolio name or sector code and name), <b>LGD %</b>, <b>Cure Rate %</b>, <b>Recovery Rate %</b>, <b>Calculated</b>, <b>Status</b>, <b>Balance-Start</b>, <b>Balance-End</b>, <b>Created By</b>, <b>Created On</b> and <b>Actions</b>. Ten per page. Empty state: <code>No Cummulative Loss-Given-Default found.</code></li>'
                . '<li><b>Status</b>: green <code>Active</code> or red <code>Closed</code>.</li>'
                . '<li><b>Actions</b>: on a system record the eye (<code>Show Periods</code>) opening the <b>Periods List</b> modal of pooled <b>Start</b> and <b>End</b> months; on a manual record the paperclip (<code>Attach File</code>) and the document icon (<code>Download Support Doc</code>, or <code>Attach Support Doc First</code> in gold when nothing is attached); the padlock <code>Lock LGD</code> or open padlock <code>Unlock LGD</code>; on a closed record the book <code>Update Loan Book</code>; on an active record the red bin <code>Delete LGD</code> (asks <code>Are you sure?</code>).</li>'
                . '<li><b>Update Loan Book Period</b> modal: <b>Reporting Period</b> month, an <b>Include Customer LGD in Update</b> tick box, <b>Cancel</b> and <b>Update</b> (<code>Updating...</code>).</li>'
                . '<li><b>Attach Supporting Document</b> modal: <code>Click to choose a file</code>, <code>Allowed Formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</code>, <code>Max Size: 5 MB</code>, <b>Cancel</b> and <b>Upload</b>.</li>'
                . '<li><b>Download LGD Monthly Report</b> modal (the title is shared with the monthly page): <b>Start Period</b>, <b>End Period</b>, <b>Calculation Level (Optional)</b> (<code>All Levels</code>, <code>Portfolio</code>, <code>Sector</code>, <code>Customer</code>), <b>Cancel</b> and <b>Download</b>.</li>'
                . '<li><b>Create page</b>, title <code>Cummulative LGD - Essentials</code>, with the fields below and the buttons <b>Calculate</b> and <b>Back</b>.</li>'
                . '<li><b>Manual Cummulative LGD Calculation</b> modal: <b>Calculation Mode</b> (<code>By Amount</code> or <code>By Percentage</code>), the amount boxes <b>Start Total Stage 3</b>, <b>End Total Stage 3</b>, <b>Cured Amount (1)</b>, <b>Cured Amount (2)</b>, <b>Partially Recovered</b>, <b>Fully Recovered</b>, <b>Disbursed Amount</b> and <b>Periods Count</b>, or <b>Cure Rate (%)</b>, <b>Recovery Rate (%)</b> and <b>Periods Count</b>; buttons <b>Submit</b> and <b>Close</b>.</li>'
                . '</ul>'
                . '<h4>Field by field (Cummulative LGD - Essentials)</h4>'
                . '<ul>'
                . '<li><b>Start Period</b> and <b>End Period</b>: required months, start not after end. Example <code>2024-07</code> to <code>2025-06</code> to pool the twelve monthly records ending in June 2025.</li>'
                . '<li><b>LGD Level</b>: <code>Portfolio</code> or <code>Sector</code>; then <b>Portfolio Group</b> or <b>Sector Code</b> appears.</li>'
                . '<li><b>Calculation Source</b>: <code>System</code> pools closed monthly records; <code>Manual</code> opens the manual modal.</li>'
                . '<li><b>Periods Count</b> (manual only): the number of monthly windows the figures represent, up to 60.</li>'
                . '</ul>'
                . '<h4>How the system pooling works</h4>'
                . '<p>The platform collects the closed monthly LGD records for the level and segment whose end month falls in the range, sums their opening and closing Stage 3 balances, cured amounts, recoveries and disbursements, and sets cure rate as pooled cures over pooled opening balance, recovery rate as pooled recoveries over pooled opening balance, and LGD as (1 minus cure rate) times (1 minus recovery rate). The list of pooled windows is kept for the eye icon. Running it again for the same range and segment overwrites the earlier system record. The banner reads <code>Cummulative LGD calculated and saved successfully.</code></p>'
                . '<h4>To lock and apply the cumulative LGD</h4>'
                . '<ol>'
                . '<li>Click the padlock <code>Lock LGD</code> and answer <b>OK</b> to <code>Are you sure you want to change the lock status of this LGD?</code> (banner <code>LGD record status updated.</code>).</li>'
                . '<li>Click the book icon <code>Update Loan Book</code> and choose the <b>Reporting Period</b>, for example <code>2025-06</code>.</li>'
                . '<li>Leave <b>Include Customer LGD in Update</b> unticked to write this LGD to every loan of the segment in that period. Tick it to write the collection LGD and multiply it by each loan customer specific LGD where one exists.</li>'
                . '<li>Click <b>Update</b>. The banner reads <code>Loan books updated successfully in 0.05 minutes. Rows updated: 1230</code>, an audit entry <code>LGD Cummulative Loan Book Update</code> is written and the period is registered with the record.</li>'
                . '</ol>'
                . '<h4>To enter a manual cumulative LGD with evidence</h4>'
                . '<ol>'
                . '<li>Set <b>Calculation Source</b> to <code>Manual</code>, click <b>Calculate</b>, choose the mode, fill the boxes and <b>Periods Count</b>, and click <b>Submit</b>.</li>'
                . '<li>On the list click the paperclip <code>Attach File</code>, choose the model file or committee paper (up to 5 MB) and click <b>Upload</b>; the toast reads <code>File attached successfully</code>. The document icon turns green and downloads the file thereafter.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The LGD value written to the loan book feeds the ECL run for the period and the LGD and Collateral report. Lock the record before the run; a closed record can only be unlocked by an administrator.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>No closed LGD records found in the selected period.</code>: lock the monthly LGD records for the range first, and check they were created at the same level (portfolio or sector) as you chose here.</li>'
                . '<li><code>Start period must be before or equal to reporting period.</code>: swap the two months.</li>'
                . '<li><code>Please fill all amount fields.</code> or <code>Please fill cure rate, recovery rate, and periods count.</code>: every box in the manual modal is required.</li>'
                . '<li><code>Cannot update loan books for an active LGD record.</code>: lock the record first.</li>'
                . '<li><code>Cannot attach file to a closed LGD record.</code>: attach evidence before locking.</li>'
                . '<li><b>Get Report returns the monthly report</b>: in the current build the cumulative page report button downloads the monthly LGD report for the chosen range; the cumulative CSV is available from the administrator until this is corrected.</li>'
                . '</ul>',
            'steps' => [
                'Lock the monthly LGD records for the range, then open the sidebar, expand IFRS 9 Model Setup, then LGD Model Setup, and choose Cumulative LGD.',
                'Click Calculate LGD.',
                'Choose the Start Period and End Period, set LGD Level to Portfolio and pick the Portfolio Group, set Calculation Source to System and click Calculate.',
                'On the list, click the eye Show Periods to confirm which monthly windows were pooled.',
                'Click the padlock Lock LGD and confirm.',
                'Click the book icon Update Loan Book, choose the Reporting Period, decide on Include Customer LGD in Update, and click Update.',
            ],
            'images' => [
                'lgd-cumulative' => 'The Cumulative LGD list with the Calculation Level, Start Date and End Date filters and the level, segmentation, rate and balance columns',
                'lgd-cumulative-create' => 'The Cummulative LGD - Essentials form: periods, LGD Level, Portfolio Group or Sector Code and Calculation Source',
            ],
            'routes' => [
                'lgd-cummulative.index', 'lgd-cummulative.create', 'lgd-cummulative.system', 'lgd-cummulative.manual', 'lgd-cummulative.update-loanbook',
                'lgd-cummulative.delete', 'lgd-cummulative.lock', 'lgd-cummulative.attach-file', 'lgd-cummulative.download-file', 'lgd-cummulative.report-by-period',
            ],
        ],

        'LGD calculation logs and payment tracking' => [
            'body' => '<p>Behind the discounted LGD sits a payment tracking table: for every Stage 3 contract in a portfolio it records, month by month, the opening balance, the closing balance, the payment detected (a fall in balance), any disbursement (a rise), whether the contract cured within twelve months of default, and gaps in the extract. The Payment Tracking Calculations page builds that table, keeps a log of every run with its status and totals, and lets you export the detail as a report. Risk analysts run it before a discounted monthly LGD. The page is not on the sidebar: open it by typing <code>/lgd-calculations</code> after the platform address, or use the link an administrator gives you.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Payment Tracking Calculations, with the round green help mark, and the buttons <b>New Calculation</b> (green, calculator icon), <b>Filters</b> (grey, funnel icon) and <b>Refresh</b> (green, arrows icon).</li>'
                . '<li><b>Filters panel</b> (opens under the header): <b>Portfolio</b> (<code>All Portfolios</code> or one), <b>Status</b> (<code>All Status</code>, <code>Completed</code>, <code>Processing</code>, <code>Pending</code>, <code>Failed</code>), <b>From Date</b>, <b>To Date</b>, and the buttons <b>Reset</b> and <b>Apply</b>.</li>'
                . '<li><b>Table columns</b> in order: <b>ID</b> (as <code>#12</code>), <b>Portfolio</b>, <b>Period</b> (start to end month), <b>Status</b>, <b>Duration</b> (<code>2 mins 05 secs</code>), <b>Contracts</b>, <b>Records</b>, <b>Payments</b> (total detected), <b>Cured</b>, <b>Source</b> (<code>manual</code>, <code>scheduled</code> or <code>api</code>), <b>Created</b> and <b>Actions</b>. Twenty per page. Empty state: <code>No calculations found</code>.</li>'
                . '<li><b>Status</b> values: grey <code>Pending</code> (queued, not started), gold <code>Processing</code> (running), green <code>Completed</code>, red <code>Failed</code> (including cancelled runs). A completed run that was later re-run shows gold <code>Completed (Recalculated)</code>; the newer run shows <code>(Recalculation)</code> after its status.</li>'
                . '<li><b>Actions</b> icons: the eye <code>View Details</code>; on a completed run the redo arrow <code>Recalculate</code> and the download arrow <code>Export</code>; on a pending or processing run the cross <code>Cancel</code>; and the red bin <code>Delete</code> on any run that is not processing.</li>'
                . '<li><b>New LGD Payment Tracking Calculation</b> page: <b>Portfolio *</b>, <b>Start Period *</b>, <b>End Period *</b>, a tick box <b>Recalculate even if calculation already exists</b>, a <b>Processing Information</b> note (<code>Large datasets will be processed in the background</code>, <code>You will be notified when calculation completes</code>, <code>You can check status in the calculations list</code>), and the buttons <b>Cancel</b> and <b>Start Calculation</b> (<code>Processing...</code>).</li>'
                . '<li><b>Calculation Details #N</b> page: <b>Back</b> and, when completed, <b>Export Report</b>; summary tiles <b>Status</b>, <b>Duration</b>, <b>Period</b>, <b>Portfolio</b>, <b>Contracts Processed</b>, <b>Records Generated</b>, <b>Total Payments</b> and <b>Cured Contracts</b>; a second row <b>Defaulted Amount</b>, <b>Payment-Based Cure Rate (Recommended)</b> (<code>Contracts with actual payments</code>) and <b>Balance-Based Cure Rate</b> (<code>Contracts with reduced balances</code>); then <b>Recovered Amount Rate</b> (<code>Stage: 1, 2 &amp; 3</code>) and <b>Stage-Based Cure Rate (Contract Count)</b>; and three tabs, <b>Recent Payments</b>, <b>Contracts</b> and <b>Metadata</b>.</li>'
                . '<li><b>Recent Payments</b> tab: the ten largest payments with the columns <b>Contract</b>, <b>Reporting Period</b>, <b>Payment Period</b>, <b>Previous Balance</b>, <b>Current Balance</b>, <b>Payment</b>, <b>Type</b> (<code>full</code>, <code>partial</code> or <code>none</code>), <b>Stage</b>, <b>Cured</b> (tick or cross) and <b>Months</b> since default.</li>'
                . '<li><b>Contracts</b> tab: tiles <b>Total Contracts</b>, <b>Cured Contracts</b>, <b>Defaulted Contracts</b> and <b>Cure Rate</b>, a <b>Search Contract</b> box, <b>Filter by Stage</b> and <b>Filter by Status</b> (<code>Cured</code>, <code>Not Cured</code>, <code>With Payments</code>, <code>No Payments</code>), and a contract table. In this version the contract list itself is not yet populated and shows <code>No contracts found</code>; use <b>Export</b> for the contract detail.</li>'
                . '<li><b>Metadata</b> tab: <b>Calculation ID</b>, <b>Parent Calculation</b> (link to the run this one recalculated), <b>Triggered By</b>, <b>Trigger Source</b>, <b>Start Time</b>, <b>End Time</b>, and when present <b>Recalculation Reason</b> and <b>Error Message</b>.</li>'
                . '<li><b>Generate LGD Payment Report</b> modal (from <b>Export Report</b>): <b>Portfolio</b>, <b>Start Period</b>, <b>End Period</b>, <b>Format</b> (<code>CSV Download</code> or <code>Excel Download</code>), a <b>Show Advanced Options</b> link revealing <b>Exclude periods with zero payments</b>, and the buttons <b>Cancel</b> and <b>Download</b> (<code>Generating...</code>).</li>'
                . '</ul>'
                . '<h4>Field by field (New LGD Payment Tracking Calculation)</h4>'
                . '<ul>'
                . '<li><b>Portfolio *</b>: required; the portfolio whose Stage 3 contracts are followed.</li>'
                . '<li><b>Start Period *</b> and <b>End Period *</b>: required months, end not before start. Use the same window as the monthly LGD you intend to discount, for example <code>2024-06</code> to <code>2025-06</code>.</li>'
                . '<li><b>Recalculate even if calculation already exists</b>: tick to run again for a window that already has a completed or processing run. The new run is linked to the old one as a recalculation.</li>'
                . '</ul>'
                . '<h4>How the run is processed</h4>'
                . '<ul>'
                . '<li>Small windows (up to 5,000 loan book rows) run at once and you land on the details page with <code>Calculation completed successfully in 12.3 seconds</code>.</li>'
                . '<li>Larger windows are queued: the message reads <code>Calculation queued for processing. You will be notified when complete.</code> and the status shows <code>Pending</code> then <code>Processing</code>. The background job takes the Stage 3 contracts of the start month in chunks of 500, writes one row per contract per month, marks a contract cured when it leaves Stage 3 within twelve months of first default, classifies each payment as full or partial, flags gaps of more than a month between extracts, and treats a contract missing at the end month as fully recovered. The job may take up to two hours and is retried three times before it is marked <code>Failed</code>.</li>'
                . '<li>Click <b>Refresh</b> to update the list while a run is in progress.</li>'
                . '</ul>'
                . '<h4>To export the tracking detail</h4>'
                . '<ol>'
                . '<li>Click the download arrow <code>Export</code> on a completed run. A CSV named <code>repayment_export_(id)_(date).csv</code> is downloaded with the columns Contract ID, Reporting Period, Starting Balance, Ending Balance, Payment Amount, Cumulative Payments, Payment Type, IFRS9 Stage, Months Since Default, Is Cured, Cure Stage and Portfolio.</li>'
                . '<li>For a filtered report, open the details page, click <b>Export Report</b>, set the portfolio, periods and format, tick <b>Exclude periods with zero payments</b> if wanted, and click <b>Download</b>. The file is named <code>LGD_Payment_Report_(start)_to_(end)</code>.</li>'
                . '</ol>'
                . '<h4>To recalculate, cancel or delete a run</h4>'
                . '<ol>'
                . '<li><b>Recalculate</b>: click the redo arrow, answer <b>OK</b> to <code>Create a new recalculation based on this one?</code>. The original is marked <code>Completed (Recalculated)</code>.</li>'
                . '<li><b>Cancel</b>: on a pending or processing run click the cross and confirm <code>Are you sure you want to cancel this calculation?</code>; the run is marked <code>Failed</code> (<code>Calculation cancelled successfully.</code>).</li>'
                . '<li><b>Delete</b>: click the red bin and confirm <code>Are you sure you want to delete this calculation and all its records?</code>. The log and its tracking rows are removed (<code>Calculation deleted successfully.</code>).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>A completed run fills the payment tracking table the monthly LGD reads when <b>Enable Discounting</b> is ticked, so run it for the portfolio and window first. The cure rates on the details page (payment based, balance based and stage based) are management information to support the cure assumption in the LGD model; the recommended one counts only contracts that actually paid.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>A calculation already exists for this period. Use "Recalculate" if you want to run it again.</code>: tick <b>Recalculate even if calculation already exists</b> or use the redo arrow on the existing run.</li>'
                . '<li><code>A recalculation is already in progress for this calculation. Please wait for it to complete or cancel it first.</code>: wait, or cancel the pending run.</li>'
                . '<li><code>Only pending or processing calculations can be cancelled.</code>: the run has already finished.</li>'
                . '<li><b>Status stays Pending</b>: the background queue worker is not running; ask the administrator to start it.</li>'
                . '<li><b>Contracts is zero</b>: no contract in the portfolio had a calculated stage of 3 in the start month.</li>'
                . '</ul>',
            'steps' => [
                'Type /lgd-calculations after the platform address to open Payment Tracking Calculations.',
                'Click New Calculation.',
                'Choose the Portfolio, the Start Period and the End Period matching the LGD window you will discount.',
                'Click Start Calculation. Small runs finish at once; large runs are queued and show Pending then Processing.',
                'Click Refresh until the status reads Completed, then click the eye View Details to read the cure rates and recent payments.',
                'Click the download arrow Export to save the contract by contract tracking detail, then run the monthly LGD with Enable Discounting ticked.',
            ],
            'images' => [
                'lgd-calculations' => 'The Payment Tracking Calculations list with New Calculation, Filters and Refresh and the ID to Actions columns',
                'lgd-payment-report' => 'The same list as reached from the payment report address, before any run has been created',
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
