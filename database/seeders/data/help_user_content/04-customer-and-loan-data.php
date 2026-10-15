<?php

/*
|--------------------------------------------------------------------------
| User Manual chapter: Customer and Loan Data
|--------------------------------------------------------------------------
| Loaded by HelpContentSeeder. Chapter title => [article title => spec].
| Spec keys: body (HTML), steps (array), images (key => caption), routes.
*/

return [
    'Customer and Loan Data' => [

        'Clients' => [
            'body' => '<p>The Clients page is the customer master: one client per E-Banker customer number. Every loan in the loan book is linked to its client by that number, and the client page lists the client\'s loans in the latest loan book. Credit and finance staff use it to find a borrower, check the details and correct a name. To open it, find the <b>Data foundation</b> block in the sidebar, expand <b>Customer &amp; Loan Data</b> and choose <b>Clients</b> (breadcrumbs: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Clients</b>). To load many clients from a file, see <b>Importing clients</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Clients</b>, with the line <code>Every borrower in the loan book, one client per E-Banker customer number, and those added here or imported. Each loan is linked to its client</code>.</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b> (the E-Banker customer number), <b>Name</b> (bold), <b>Phone</b> (a dash when blank), <b>Updated</b> (date and time of the last change), <b>Actions</b>. Fifteen clients to a page, with page links under the table.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Green round question-mark button</b> (tooltip <code>Help for this screen</code>): opens this article.</li>'
                . '<li><b>Search ID, name or phone</b> box: matches any part of the customer ID, the name or the phone number; the list refreshes shortly after you stop typing.</li>'
                . '<li><b>Link the loan book</b> (tooltip <code>Create a client for every loan-book customer not yet held, and link every loan to its client</code>): makes one client for every customer number in the loan book that has none (named as on its latest loan; typed Corporate when the name says Limited, SACCO, cooperative, trust and so on, otherwise Individual) and links every loan to its client. A client already held is never overwritten. It can take several minutes the first time. Permission <code>clients.create</code>.</li>'
                . '<li><b>Import clients</b>: opens the <b>Import Clients</b> page. Permission <code>clients.create</code>.</li>'
                . '<li><b>New client</b> (green): opens the <b>New Client</b> form. Permission <code>clients.create</code>.</li>'
                . '<li><b>Green eye</b> (tooltip <code>View</code>): opens the client page. Everyone who can open the list.</li>'
                . '<li><b>Gold pencil</b> (tooltip <code>Edit</code>): opens <b>Edit Client</b>. Shown only with <code>clients.update</code>.</li>'
                . '<li><b>Red bin</b> (tooltip <code>Delete</code>): asks <b>Delete (name)?</b> with <code>The client record and its notes and files are removed. Loans in the loan book are not affected.</code> Press <b>Delete</b> or <b>Cancel</b>. Shown only with <code>clients.destroy</code>.</li>'
                . '<li>The list and the client page need <code>clients.index</code>. No second approval is asked for client changes.</li>'
                . '</ul>'
                . '<h4>The New Client and Edit Client forms</h4>'
                . '<ul>'
                . '<li><b>Customer ID</b>: required and unique, with the hint <code>The ID the loan book uses for this borrower.</code></li>'
                . '<li><b>Name</b>: required.</li>'
                . '<li><b>Phone</b>: optional, free text.</li>'
                . '<li><b>Type</b>: <code>Individual</code> or <code>Corporate</code>.</li>'
                . '<li><b>Status</b>: <code>Active</code>, <code>Pending</code>, <code>Inactive</code> or <code>Archived</code>.</li>'
                . '<li><b>Back to clients</b> and <b>Cancel</b> leave without saving; <b>Save client</b> saves (<code>Saving...</code> while it works).</li>'
                . '</ul>'
                . '<h4>The client page (green eye)</h4>'
                . '<p>The page title is the client\'s name, with the line <code>Client (customer ID), (type)</code>. The buttons above the panels are <b>Files</b> (opens the client\'s files and the client menu, see <b>Client files, notes and the client menu</b>), <b>Edit</b> (<code>clients.update</code>), a red <b>Delete</b> (<code>clients.destroy</code>; asks <b>Delete (name)?</b> with <code>The client is removed from the client list.</code>) and <b>Back to clients</b>. Three panels follow:</p>'
                . '<ul>'
                . '<li><b>Client</b>: <b>Customer ID</b>, <b>Name</b>, <b>Type</b>, <b>Status</b> (badge: green active, gold pending, grey inactive or archived, red deceased), <b>Sector</b>, <b>Branch</b>; for a corporate client also <b>Trading name</b>, <b>Legal type</b>, <b>Registration number</b>, <b>Year of registration</b>, <b>Country of registration</b>; for an individual <b>ID number</b> and <b>Date of birth</b>. A blank value shows a dash.</li>'
                . '<li><b>Contact</b>: <b>Mobile</b>, <b>Telephone</b>, <b>Email</b>, <b>Address</b>, <b>Postal address</b>, <b>Country</b>.</li>'
                . '<li><b>Loans</b>, <code>in the loan book of (period)</code>: the client\'s loans in the latest loan book that holds any of them, largest first, with <b>Contract</b>, <b>Product group</b>, <b>Carrying amount</b>, <b>Days overdue</b>, <b>Stage</b> and <b>ECL</b>.</li>'
                . '</ul>'
                . '<h4>To find a client and check the loans</h4>'
                . '<ol>'
                . '<li>Type part of the customer ID, name or phone in <b>Search ID, name or phone</b>.</li>'
                . '<li>Press the green eye on the row.</li>'
                . '<li>Read the <b>Client</b> and <b>Contact</b> panels and the <b>Loans</b> table.</li>'
                . '</ol>'
                . '<h4>To add or edit a client</h4>'
                . '<ol>'
                . '<li>Press <b>New client</b>, or the gold pencil on a row (or <b>Edit</b> on the client page).</li>'
                . '<li>Fill in <b>Customer ID</b> and <b>Name</b>, and the <b>Phone</b>, <b>Type</b> and <b>Status</b> as needed.</li>'
                . '<li>Press <b>Save client</b>. You see <code>Client created successfully.</code> or <code>Client updated successfully.</code></li>'
                . '</ol>'
                . '<h4>To link the loan book to its clients</h4>'
                . '<ol>'
                . '<li>Press <b>Link the loan book</b> and wait; the Loading pill shows while it runs.</li>'
                . '<li>Read the message, for example <code>12 new client(s) from the loan book; 3,450 loan(s) linked to their client.</code> Running it again adds only new customers.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Every create, update and delete is written to the activity log. The sector shown on the client page comes from the loan book import, not from this form. Deleting a client leaves its loans in the loan book untouched.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No clients yet. Import a loan book or a client file, or add a client with New client.</code></li>'
                . '<li><code>No client matches the search.</code></li>'
                . '<li><code>This client has no loan in the loan book.</code> on the client page, and <code>none in the loan book</code> beside <b>Loans</b>.</li>'
                . '<li><code>Client deleted successfully.</code> after a delete.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The customer id has already been taken.</code>: another client already holds that number; search for it and edit that client.</li>'
                . '<li>The loans of a client do not show: the loans carry a different customer number, or the loan book has not been linked yet. Press <b>Link the loan book</b>.</li>'
                . '<li>You do not see the buttons or the pencil and bin: your role lacks <code>clients.create</code>, <code>clients.update</code> or <code>clients.destroy</code>.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block expand Customer & Loan Data and choose Clients.',
                'Type part of the customer ID, name or phone in the search box.',
                'Press the green eye to open the client page and read its loans, or the gold pencil to edit.',
                'To add one client, press New client, fill in Customer ID and Name and press Save client.',
                'After a new loan book, press Link the loan book so every loan points at its client.',
            ],
            'images' => [
                'clients' => 'The Clients list with the search box, Link the loan book, Import clients, New client and the green eye, gold pencil and red bin',
                'clients-create' => 'The New Client form with Customer ID, Name, Phone, Type, Status and Save client',
                'clients-show' => 'A client page with Files, Edit, Delete and Back to clients, the Client and Contact panels and the Loans table',
            ],
            'routes' => ['clients.index', 'clients.create', 'clients.store', 'clients.show', 'clients.edit', 'clients.update', 'clients.destroy', 'clients.search', 'clients.link-loan-book'],
        ],

        'Importing clients' => [
            'body' => '<p>The client import loads many clients at once from a file: the E-Banker customer number and the name, or any columns you match to client fields. A customer number already held is updated, not duplicated. It is rarely needed, because the loan book and <b>Link the loan book</b> create clients too; use it for a richer client list. Open <b>Clients</b> and press <b>Import clients</b>, or press <b>Import clients</b> on the Imports tab. The page title is <b>Import Clients</b> (breadcrumbs: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Clients &gt; Import Clients</b>). The permission <code>clients.create</code> is needed. The general rules of every import (sample file, column guide and Check file) are in the Getting Started article <b>Importing files: sample file, column guide and Check file</b>; this article covers this file only.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Two tabs</b>: <b>Upload</b> and <b>History</b>. The chip on History counts every import of every kind; the table on it lists the fifteen latest client imports.</li>'
                . '<li><b>How to import</b> panel (title <b>Clients</b>): the summary, four numbered steps, the note on matching columns, the column table and the buttons described below.</li>'
                . '<li><b>Column table</b>: <b>Column</b>, <b>Type</b>, <b>Required</b>, <b>Format and allowed values</b>, <b>Description</b>, <b>Example</b>. This import has two columns: <code>customer_id</code> (Text (identifier), Required; the E-Banker customer number, each once in the file, kept as text so leading zeros stay; also read under <code>Customer ID</code>) and <code>name</code> (Text, Optional; the client name).</li>'
                . '<li><b>File layout</b>: two cards, <b>Standard CSV</b> (<code>customer_id and name, as in the sample CSV.</code>, selected at first) and <b>Match columns</b> (<code>Match each column of your file to a client field.</code>).</li>'
                . '<li><b>File</b>: a dashed box reading <code>Choose a CSV or Excel file</code>; accepts .csv, .txt, .xlsx and .xls. The file name replaces the text once chosen.</li>'
                . '<li><b>Match the file\'s columns</b> (Match columns only, for a CSV file): the first three rows of the file, then one drop-down per file column (<code>Ignore this column</code> or a client field; required fields are marked <code>(required)</code>). Common headings such as <code>customer id</code>, <code>name</code>, <code>phone</code> and <code>email</code> are matched for you.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Hide the 2 columns (1 required)</b> or <b>Show the 2 columns (1 required)</b> (arrow link): folds or opens the column table.</li>'
                . '<li><b>Check file</b> (tick icon; appears once a file is chosen; tooltip <code>Read the file and list every problem by row. Nothing is saved.</code>): checks the file, through your matching if you chose Match columns.</li>'
                . '<li><b>Download sample file</b> (download icon): <code>clients_sample.csv</code>, the two headings and two illustrative rows (customer numbers starting with 8, names starting with Sample).</li>'
                . '<li><b>Download column guide</b> (page icon): <code>clients_column_guide.xlsx</code>, the column table as a workbook.</li>'
                . '<li><b>Back to clients</b> and <b>Cancel</b>: return to the list.</li>'
                . '<li><b>Start import</b> (green; reads <code>Uploading...</code>): checks the whole file and, if it is clean, queues the import.</li>'
                . '<li><b>Download icon</b> on a History row (tooltip <code>Download the failed rows</code>) and the <b>Open the full import history</b> link: see <b>Import history</b>.</li>'
                . '</ul>'
                . '<h4>What Check file and Start import verify for this file</h4>'
                . '<p>Before anything is saved: a <code>customer_id</code> column is present (or matched), no row has a blank customer number, the number is not written in scientific notation, and no customer number appears twice. If any row fails, nothing is imported; the result box lists each problem with <b>Row</b>, <b>Column</b>, <b>Problem</b> (marked Error or Warning) and <b>How to fix it</b>.</p>'
                . '<h4>To import clients</h4>'
                . '<ol>'
                . '<li>Press <b>Download sample file</b> and fill one row per client under the two headings.</li>'
                . '<li>Leave <b>Standard CSV</b> selected, or choose <b>Match columns</b> if your headings differ.</li>'
                . '<li>Click the dashed box and choose the file. For Match columns, check each drop-down; one column must be matched to <code>customer_id</code>.</li>'
                . '<li>Press <b>Check file</b> and fix every row listed until the box reads <code>The file passed the check</code>.</li>'
                . '<li>Press <b>Start import</b>. You return to Clients with <code>Import started successfully!</code></li>'
                . '<li>Follow the job on the History tab or on Imports.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The file is processed in the background. A new customer number creates a client; a known one has its name updated. Rows the importer could not use are written to a failed-rows file counted under <b>Failed rows</b> on the Imports tab.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>(file) was not imported: N problems found (first: row 4, customer_id: ...). Nothing was saved. Fix the rows listed and choose the file again.</code>: the check refused the file; the rows are listed in the How to import panel.</li>'
                . '<li><code>The customer_id field must be mapped.</code>: with Match columns, no column was matched to customer_id.</li>'
                . '<li><code>No file has been imported yet. Use the Upload tab to import the first one.</code>: the History tab when no client file has been loaded. Under the table, <code>The N most recent uploads of every kind.</code> and the link <b>Open the full import history</b>.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The required column customer_id is missing.</code>: the heading is spelt differently; rename it or use Match columns.</li>'
                . '<li><code>Repeats row N</code>: the same customer number is in the file twice; keep one row.</li>'
                . '<li>The matching drop-downs do not appear for an Excel file: matching reads CSV headings only. Save the file as CSV, or use the standard headings.</li>'
                . '</ul>',
            'steps' => [
                'Open Clients and press Import clients.',
                'Press Download sample file and fill customer_id and name, one row per client.',
                'Choose Standard CSV (or Match columns) and click the dashed box to choose the file.',
                'Press Check file and fix every row listed.',
                'Press Start import and follow the job on the History tab or on Imports.',
            ],
            'images' => [
                'clients-import' => 'The Import Clients page: the How to import panel for the client file, the file box and the History tab',
                'import-modal' => 'An import screen: the How to import panel with the column table, Check file, Download sample file and Download column guide',
            ],
            'routes' => ['clients.import.create', 'clients.import.store'],
        ],

        'Client files, notes and the client menu' => [
            'body' => '<p>Each client has a set of supporting pages kept from the credit origination screens: files, notes, shareholders, financial statements, ratio and Porter analyses, loan applications and portal logins. They are not used by the IFRS 9 calculations. Reach them from the client page: open <b>Clients</b>, press the green eye on a client, then press <b>Files</b>. The Files page shows the <b>client menu</b> on the left, from which the other pages open. Each entry, and each button on those pages, is shown only if your role holds its permission.</p>'
                . '<h4>The client menu (left panel)</h4>'
                . '<ul>'
                . '<li>The client\'s name, type, mobile and email, and two green icon buttons, <b>SMS</b> (speech icon) and <b>Email</b> (envelope), that start a message campaign to the client (permission <code>communication.campaigns.create</code>).</li>'
                . '<li>Menu entries: <b>Basic Profile</b> (the client page), <b>Shareholders</b> (<code>clients.shareholders.index</code>), <b>Balance Sheet</b> (<code>clients.balance_sheet.index</code>), <b>Income Statement</b> (<code>clients.income_statement.index</code>), <b>Ratio Analysis</b> (<code>clients.ratio_analysis.index</code>), <b>Porter\'s Five Forces Analysis</b> (<code>clients.poters_five_forces_analysis.index</code>), <b>Loan Applications</b> (<code>loans.applications.index</code>), <b>Notes</b> (<code>clients.notes</code>), <b>Files</b> (<code>clients.files.index</code>) and <b>Login Details</b> (<code>clients.create</code>). The open entry is shaded grey.</li>'
                . '<li><b>Edit</b> (<code>clients.update</code>) and a red <b>Delete</b> (<code>clients.destroy</code>) at the foot. Delete opens <b>Delete Record</b> with the buttons <b>Nevermind</b> and <b>Delete Record</b>.</li>'
                . '</ul>'
                . '<h4>Files</h4>'
                . '<ul>'
                . '<li>Header <b>Clients / (name)</b>; heading <b>Files</b> with a blue <b>Create File</b> button (<code>clients.files.create</code>).</li>'
                . '<li>Columns: <b>Name</b> (a link that downloads the file), <b>Size</b>, <b>Description</b>, <b>Date</b> (how long ago), <b>Action</b>.</li>'
                . '<li>Action: the download arrow (tooltip <code>Click to download</code>), <b>Edit</b> (<code>clients.files.update</code>) and a red <b>Delete</b> (<code>clients.files.destroy</code>), which asks <b>Delete Record</b>, <code>Are you sure you want to delete this record?</code>, with <b>Nevermind</b> and <b>Delete</b>.</li>'
                . '<li>The <b>Create File</b> form has <b>Name</b>, <b>File</b> and <b>Description</b> and a <b>Save</b> button; you return with <code>Client File created successfully.</code> An edit shows <code>Updated File successfully.</code>, a delete <code>File deleted successfully.</code></li>'
                . '<li>Empty state: <code>No Files Yet</code>.</li>'
                . '</ul>'
                . '<h4>The other pages</h4>'
                . '<ul>'
                . '<li><b>Notes</b>: columns <b>Note</b>, <b>Created By</b>, <b>Date</b>, <b>Action</b> (<b>Edit</b>, <b>Delete</b>); <b>Create</b> opens <b>Create Note</b> with <b>Description</b>, a tick box for whether the client may see it, and <b>Save</b>. Empty: <code>No Notes Yet</code>.</li>'
                . '<li><b>Shareholders</b>: <b>Name</b>, <b>Gender</b>, <b>Date of Birth</b>, <b>Shares</b>, <b>ITC Ref No</b>, <b>ITC Date</b>, credit bureau counts (<b>No of Paid Debts &gt; Defaults</b>, <b>No of Defaults</b>, <b>No of Judgements</b>, <b>No of Trace Alerts</b>), <b>Blacklisted?</b>, <b>Fraudalert?</b>, <b>Action</b>; <b>Create</b>, <b>Edit</b>, <b>Delete</b>. Empty: <code>No Shareholders Yet</code>.</li>'
                . '<li><b>Balance Sheets</b>: <b>Year</b>, <b>Total Assets</b>, <b>Total Liabilities</b>, <b>Working Capital</b>, <b>Action</b>; a green tick (tooltip <code>Balanced</code>) or red cross (<code>Not balanced</code>) beside a sheet; <b>View Summary</b>, <b>Create</b>, <b>Edit</b>, <b>Delete</b>. Empty: <code>No Balance Sheets Yet</code>.</li>'
                . '<li><b>Income Statements</b>: <b>Year</b>, <b>Reporting Month</b>, <b>No of Months In Year</b>, <b>Audit Status</b>, <b>Annual Inflation Rate - Real</b>, <b>Sales</b>, <b>Costs Of Good Sold</b>, <b>Gross Profit</b>, <b>Operating Expenses</b>, <b>Operating Income</b>, <b>Other Expenses</b>, <b>Other Income</b>, <b>Income Before Tax</b>, <b>Income Tax</b>, <b>Net Profit</b>, <b>Action</b>. Empty: <code>No Income Statements Yet</code>.</li>'
                . '<li><b>Ratio Analysis</b>: one row per ratio with its <b>Symbol</b>, a column per year and a <b>Comment</b> box, saved with <b>Save</b>.</li>'
                . '<li><b>Porter\'s Five Forces Analysis</b>: <b>Input</b>, <b>Score</b> and <b>Comment</b> per force, saved with <b>Save</b>.</li>'
                . '<li><b>Loans</b> (Loan Applications): <b>ID</b>, <b>Product</b>, <b>Amount</b>, <b>Score</b>, <b>Status</b>, <b>Date</b>, <b>Action</b> with a magnifier (<code>View</code>), pencil (<code>Edit</code>, <code>loans.applications.update</code>), bin (<code>Delete</code>, <code>loans.applications.destroy</code>) and clock (<code>Log History</code>).</li>'
                . '<li><b>Login Details</b>: portal users of the client, <b>Name</b>, <b>Email</b>, <b>Action</b>; <b>Add</b> creates a login. Empty: <code>No Users Yet</code>.</li>'
                . '</ul>'
                . '<h4>To attach a document to a client</h4>'
                . '<ol>'
                . '<li>Open the client page and press <b>Files</b>.</li>'
                . '<li>Press <b>Create File</b>, give it a <b>Name</b>, choose the <b>File</b> and add a <b>Description</b>.</li>'
                . '<li>Press <b>Save</b>; the file appears in the list and downloads from its name.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>These records are kept with the client and listed in the activity log. They do not feed the loan book, staging, PD, LGD or ECL.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>A menu entry is missing: your role lacks its permission.</li>'
                . '<li>The page shows <b>Clients / (name)</b> without the modern header and breadcrumbs: these older pages keep their original layout.</li>'
                . '</ul>',
            'steps' => [
                'Open Clients and press the green eye on the client.',
                'Press Files to open the client files and the client menu.',
                'Press Create File, enter the Name, choose the File, add a Description and press Save.',
            ],
            'images' => [
                'client-files' => 'The Files page of a client with the client menu on the left and Create File',
            ],
            'routes' => [
                'clients.files.index', 'clients.files.create', 'clients.files.store', 'clients.files.show', 'clients.files.edit', 'clients.files.update', 'clients.files.destroy',
                'clients.notes.index', 'clients.notes.create', 'clients.notes.store', 'clients.notes.show', 'clients.notes.edit', 'clients.notes.update', 'clients.notes.destroy',
                'clients.shareholders.index', 'clients.shareholders.create', 'clients.shareholders.store', 'clients.shareholders.show', 'clients.shareholders.edit', 'clients.shareholders.update', 'clients.shareholders.destroy',
                'clients.balance_sheets.index', 'clients.balance_sheets.summary', 'clients.balance_sheets.create', 'clients.balance_sheets.store', 'clients.balance_sheets.show', 'clients.balance_sheets.edit', 'clients.balance_sheets.update', 'clients.balance_sheets.destroy',
                'clients.income_statements.index', 'clients.income_statements.create', 'clients.income_statements.store', 'clients.income_statements.show', 'clients.income_statements.edit', 'clients.income_statements.update', 'clients.income_statements.destroy',
                'clients.ratio_analysis.index', 'clients.ratio_analysis.create', 'clients.ratio_analysis.store', 'clients.ratio_analysis.show', 'clients.ratio_analysis.edit', 'clients.ratio_analysis.update', 'clients.ratio_analysis.destroy',
                'clients.poter.index', 'clients.poter.create', 'clients.poter.store', 'clients.poter.show', 'clients.poter.edit', 'clients.poter.update', 'clients.poter.destroy',
                'clients.loan_applications.index', 'clients.login_details.index', 'clients.login_details.create', 'clients.login_details.store', 'clients.login_details.destroy',
            ],
        ],

        'Loan Book' => [
            'body' => '<p>The Loan Book is the contract-level view of one month-end: each loan\'s exposure, IFRS 9 stage, arrears, locked EIR and ECL, and, when you open a row, how its ECL was built. Finance and risk staff use it to check what was loaded or built, confirm staging and review the ECL. It opens on the latest month that holds loans. To open it, find the <b>Data foundation</b> block, expand <b>Customer &amp; Loan Data</b> and choose <b>Loan Book</b> (breadcrumbs: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Loan Book</b>). Any signed-in user may open it.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Loan Book</b>, with the line <code>Each loan\'s exposure, stage, arrears, locked EIR and ECL. Open a row to see how its ECL was built.</code></li>'
                . '<li><b>Six cards</b> (they follow the filters): <b>Loans</b> (number of contracts), <b>Carrying (MWK)</b>, <b>EAD (MWK)</b> (exposure at default: carrying amount plus undrawn commitments times utilisation), <b>Stage 3 EAD (MWK)</b>, <b>ECL (MWK)</b> and <b>Coverage (ECL / EAD)</b>. Large amounts are shortened to m or bn; hover a card for the full figure (the Stage 3 card also gives the number of Stage 3 loans).</li>'
                . '<li><b>Filter bar</b>: <b>Reporting period</b> (the month-ends that hold loans, newest first), <b>Stage</b> (<code>All stages</code>, <code>Stage 1</code>, <code>Stage 2</code>, <code>Stage 3</code>), <b>Product group</b> (<code>All product groups</code> or a group in that month), <b>Sector</b> (<code>All sectors</code> or a sector in that month) and <b>Search contract or customer</b> (contract ID, customer number, customer name or client name). The note at the right reads <code>EAD = carrying amount + undrawn x utilisation. Coverage = ECL / EAD.</code> The table and cards refresh as soon as you change a filter.</li>'
                . '<li><b>Table columns</b>: the open arrow, <b>Contract</b> (contract ID with the customer name beneath), <b>Product group / sector</b>, <b>Stage / RBM</b>, <b>DPD</b> (days past due), <b>Carrying (MWK)</b> (with <code>+ (undrawn)</code> beneath when there is an undrawn commitment), <b>EAD (MWK)</b>, <b>EIR</b>, <b>ECL (MWK)</b> and <b>Coverage</b>. Money is in accounting format (negatives in brackets). Fifteen loans a page, with page links under the table.</li>'
                . '<li><b>Stage badge</b>: green <code>Stage 1</code>, gold <code>Stage 2</code>, red <code>Stage 3</code> (the final stage: post-qualitative if set, otherwise calculated, otherwise imported). Beneath it the Reserve Bank of Malawi class in colour: green <code>Pass</code>, amber <code>Special mention</code>, red for the non-performing classes.</li>'
                . '<li><b>DPD colour</b>: green at 0, amber up to 30 days, red and bold above 30.</li>'
                . '<li><b>EIR</b>: the locked original effective interest rate as a percentage (hover for fixed or floating). A loan with no locked EIR shows a grey badge with its status instead, for example <code>No EIR record</code> or <code>Pending</code>; a rate is never shown until it is locked.</li>'
                . '<li><b>ECL</b> and <b>Coverage</b>: a dash until the ECL has been calculated for the loan.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Open arrow</b> (chevron at the left of a row, tooltip <code>Show how the ECL was built</code>; clicking anywhere on the row does the same): opens four boxes under the row. <b>Probability of default</b>: <b>12-month PD before FLI</b>, <b>FLI adjustment</b> (<code>Not applied (defaulted)</code> for Stage 3), <b>12-month PD after FLI</b>, <b>PD used</b> for the horizon, the PD by scenario, and an amber note when the loan has no remaining term. <b>Loss given default</b>: <b>LGD used</b> and its <b>Source</b>. <b>Exposure at default (MWK)</b>: <b>Carrying amount</b>, <b>Undrawn commitment</b>, <b>x utilisation rate</b> (<code>(not recorded)</code> when none) and <b>EAD</b>. <b>Expected credit loss (MWK)</b>: <b>ECL before FLI</b>, <b>Forward-looking effect</b>, <b>ECL booked</b>, and the check <code>EAD x PD used x LGD = ...</code> saying whether it <code>matches the booked ECL</code>. Beneath: <b>Ran under</b> (the FLI route, the regression fit and the scenario set with their status badges, and the EIR) and the link <b>Open in Audit Trace</b>.</li>'
                . '<li><b>Export summary</b> (tooltip <code>Balances by stage for a range of periods</code>): opens <b>Export the loan book summary</b> with <b>Portfolio</b> (<code>All portfolios</code> or one), <b>From</b> and <b>To</b> months, <b>Cancel</b> and <b>Export</b> (<code>Exporting...</code>). The file opens in a new tab.</li>'
                . '<li><b>Disbursements</b> (tooltip <code>Loans disbursed within a range of periods</code>): opens <b>Export the disbursement report</b> with <b>Portfolio</b>, <b>From</b>, <b>To</b>, <b>Content</b> (<code>Summary only</code> or <code>Summary and each contract</code>), <b>Cancel</b> and <b>Export</b>.</li>'
                . '<li><b>Import loan book</b> (green): opens <b>Import Loan Book</b> (next article).</li>'
                . '<li><b>Green round help button</b> (bottom right): opens this article.</li>'
                . '</ul>'
                . '<h4>To review a month</h4>'
                . '<ol>'
                . '<li>Choose the <b>Reporting period</b>.</li>'
                . '<li>Narrow with <b>Stage</b>, <b>Product group</b> or <b>Sector</b>, or search a contract or customer.</li>'
                . '<li>Read the cards, then the stage badge, DPD, EAD, EIR and ECL of each loan.</li>'
                . '<li>Open a row to see how its ECL was built; use <b>Open in Audit Trace</b> for its full history.</li>'
                . '</ol>'
                . '<h4>To export the summary or the disbursements</h4>'
                . '<ol>'
                . '<li>Press <b>Export summary</b> or <b>Disbursements</b>.</li>'
                . '<li>Choose the <b>Portfolio</b> if needed and the <b>From</b> and <b>To</b> months (and the <b>Content</b> for disbursements).</li>'
                . '<li>Press <b>Export</b>; the file opens in a new tab.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The figures are written by later steps of the month-end: staging sets the stage, the PD and LGD models fill the PD and LGD, collateral allocation lowers the LGD of covered loans, EIR Calculations lock the EIR and the ECL run fills ECL and coverage. A freshly imported or built month shows stages but dashes for ECL until those steps have run.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No loan book has been imported yet. Use Import loan book (top right).</code></li>'
                . '<li><code>No loan matches these filters.</code></li>'
                . '<li>In the export windows: <code>Choose both the first and the last period.</code>, <code>The first period cannot be after the last.</code> and <code>The export could not start. Please try again.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The month you want is not in <b>Reporting period</b>: no loan book has been imported or built for it. Check Imports and the Loan Book Builds tab.</li>'
                . '<li>ECL shows a dash: the ECL has not been run for the month yet.</li>'
                . '<li>The check under ECL says <code>does not match the booked ECL, run the ECL again</code>: an input changed after the ECL ran; run it again.</li>'
                . '<li>A customer shows as a number: the loan has no name and no linked client. Use <b>Link the loan book</b> on Clients.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block expand Customer & Loan Data and choose Loan Book.',
                'Pick the Reporting period; the cards and the table refresh.',
                'Narrow the list with Stage, Product group or Sector, or search a contract or customer.',
                'Read the stage badge, DPD, EAD, EIR, ECL and Coverage of each loan.',
                'Click a row to see how its ECL was built, PD, LGD, EAD and ECL.',
                'Press Export summary or Disbursements, choose the months and press Export to download.',
            ],
            'images' => [
                'loanbook' => 'The Loan Book with the six cards, the period, stage, product group and sector filters, the stage and RBM badges and an opened row',
            ],
            'routes' => ['loan_applications.loan-book', 'loan_applications.loan-book.summary'],
        ],

        'Importing a loan book' => [
            'body' => '<p>The loan book import loads one month-end of loans into a portfolio from a file: the standard CSV, the E-Banker loan book report as exported, or any CSV whose columns you match. Where the loan book is built from the E-Banker packs (see <b>E-Banker feed: loads, builds, periods and queries</b>), this import is used for corrections and other portfolios. The portfolio must exist first. Open the Loan Book and press <b>Import loan book</b>, or press <b>Import loan book</b> on the Imports tab. The page title is <b>Import Loan Book</b> (breadcrumbs: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Loan Book &gt; Import Loan Book</b>). Any signed-in user may use it; no second approval is asked. The general rules of every import are in the Getting Started article <b>Importing files: sample file, column guide and Check file</b>; this article covers the loan book files.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Two tabs</b>: <b>Upload</b> and <b>History</b> (the chip counts every import of every kind; the table lists the fifteen latest loan book imports).</li>'
                . '<li><b>How to import</b> panel: titled <b>Loan book (standard layout)</b>, or <b>Loan book (E-Banker report)</b> when that layout is chosen, with the summary, the numbered steps, the notes and the column table.</li>'
                . '<li><b>Portfolio</b>: a searchable drop-down (<code>Choose the portfolio</code>). Required.</li>'
                . '<li><b>Reporting period</b>: a month picker with the hint <code>The month-end the file reports.</code> Required.</li>'
                . '<li><b>File layout</b>: three cards, <b>Standard CSV</b> (<code>The columns of the sample CSV.</code>, selected at first), <b>E-Banker report</b> (<code>The E-Banker loan book report as exported.</code>) and <b>Match columns</b> (<code>Match each column of your file to a loan book field.</code>).</li>'
                . '<li><b>File</b>: a dashed box reading <code>Choose a CSV file</code> (.csv or .txt).</li>'
                . '<li><b>Match the file\'s columns</b> (Match columns only): the first three rows, then one drop-down per column (<code>Ignore this column</code> or a loan book field). Headings such as <code>Customer ID</code>, <code>Contract ID</code>, <code>Value Date</code>, <code>Maturity Date</code>, <code>Principal</code>, <code>Carrying Amount</code>, <code>Sector Code</code>, <code>Internal Grade</code> and the four arrears bands are matched for you.</li>'
                . '</ul>'
                . '<h4>The standard file (Standard CSV)</h4>'
                . '<p>Twenty columns, in any order; other columns are ignored. Required: <code>customer_id</code> (E-Banker customer number; the client is created if new), <code>contract_id</code> (loan account number; each customer and contract pair once in the file), <code>value_date</code> (disbursement date) and <code>maturity_date</code>. Optional: <code>name</code> (blank loads as Unknown), <code>type</code> (product group), <code>industry_code</code> (sector code, as on Sector Types), <code>industry_type</code>, <code>internal_grade</code>, <code>tenor</code> (months), <code>interest_rate</code> (a percentage, 0 to 100, for example 28.5), <code>approved_amount</code>, <code>disbursed</code>, <code>principal</code>, <code>carrying_amount</code>, <code>repayments</code>, and the arrears bands <code>1-30 Days</code>, <code>31-90 Days</code>, <code>91-180 Days</code>, <code>181-270 Days</code>. Dates are best as YYYY-MM-DD (DD/MM/YYYY and MM/DD/YYYY are read too); amounts may carry thousands commas. If principal is 0 or blank and the carrying amount is above 0, the carrying amount is used as the principal. <b>Download sample file</b> gives <code>loan_book_sample.csv</code> with two illustrative loans (identifiers starting with 8, names starting with Sample).</p>'
                . '<h4>The E-Banker report</h4>'
                . '<p>The report as E-Banker exports it, saved as CSV, read by column position (the column table shows a <b>Position</b> column): A <code>Sr.</code> serial number, B <code>Customer ID</code>, D <code>Loan account</code>, E <code>Name</code>, F <code>Value date</code>, G <code>Maturity</code> (DD/MM/YYYY), J <code>Interest</code>, N <code>Principal</code>, T <code>Carrying</code>, Y <code>1 - 30 Days</code>, Z <code>31 - 90 Days</code>, AA <code>91 - 180 Days</code>, AC <code>181 - 270 Days</code>. When the report leaves column A blank, every field is one column to the right; both shapes are read. Each <code>Loan Type : 01 - TERM LOAN</code> row sets the product group of the loans below it. A row that does not start with a serial number is skipped. Do not add, remove or move columns. <b>Download sample file</b> gives <code>loan_book_ebanker_sample.csv</code>: one Loan Type row, the header row and an illustrative row.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Hide</b> or <b>Show the N columns (M required)</b>: folds or opens the column table.</li>'
                . '<li><b>Check file</b> (tick icon; once a file is chosen): checks the file in the chosen layout without saving; a matched file is checked through its matching.</li>'
                . '<li><b>Download sample file</b> and <b>Download column guide</b> (<code>loan_book_column_guide.xlsx</code> or <code>loan_book_ebanker_column_guide.xlsx</code>), for the layout chosen.</li>'
                . '<li><b>Back to Loan Book</b> and <b>Cancel</b>: leave without importing.</li>'
                . '<li><b>Start import</b> (green; <code>Uploading...</code>): checks the whole file and, if clean, imports it.</li>'
                . '</ul>'
                . '<h4>What Check file and Start import verify</h4>'
                . '<p>Standard and matched files: every required column present (or matched), no blank customer, contract, value or maturity date, dates and numbers readable, the rate between 0 and 100, and no customer and contract pair repeated. E-Banker report: at least one row starting with a serial number, each loan row long enough to reach the arrears columns, dates and amounts readable, no <code>%</code> sign in the rate, and no loan account repeated; a report with no <code>Loan Type :</code> rows passes with a warning (every loan would load as product group Unknown). If any error is found nothing is imported and the problems are listed by <b>Row</b>, <b>Column</b>, <b>Problem</b> and <b>How to fix it</b>.</p>'
                . '<h4>To import a month</h4>'
                . '<ol>'
                . '<li>Choose the <b>Portfolio</b> and the <b>Reporting period</b>.</li>'
                . '<li>Choose the <b>File layout</b>; download the sample file if you need the layout.</li>'
                . '<li>Click the dashed box and choose the file; for Match columns, check the drop-downs.</li>'
                . '<li>Press <b>Check file</b> and fix every row listed.</li>'
                . '<li>Press <b>Start import</b>, then follow the job on the History tab or on Imports.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Standard and matched files return you to the Loan Book with <code>Import started! You will be notified once it completes.</code>; the E-Banker report with <code>Import job queued successfully. File: (name)</code>. A loan with the same customer, portfolio, period and contract replaces the earlier values, so re-importing a corrected file is safe. Every row gets a starting stage: standard and matched rows by the staging thresholds in force at the month-end (the import stops if no approved rule is in force), E-Banker rows by the highest arrears band holding an amount (181-270 days Stage 3; 31-90 or 91-180 days Stage 2; otherwise Stage 1). Rows the importer could not use go to a failed-rows file on the Imports tab.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>(file) was not imported: N problems found (first: ...). Nothing was saved. Fix the rows listed and choose the file again.</code>: the check refused the file.</li>'
                . '<li><code>Failed to import E-Banker file: ... Please ensure your file matches the E-Banker format template.</code>: the report could not be queued.</li>'
                . '<li><code>No loan rows were found: no row starts with a serial number.</code>: the file is not the E-Banker report; choose Standard CSV or export the report again.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The row has N columns; the report has at least 29</code>: columns were deleted from the report. Export it again unedited.</li>'
                . '<li><code>Repeats row N</code>: the same contract is in the file twice; keep one row.</li>'
                . '<li>Loans appear under the wrong month: the <b>Reporting period</b> was wrong; import again with the right month.</li>'
                . '<li>Customer numbers lose their leading zeros: the file was saved from Excel as numbers; format the column as text before saving.</li>'
                . '</ul>',
            'steps' => [
                'Open the Loan Book and press Import loan book.',
                'Choose the Portfolio and set the Reporting period to the month-end of the file.',
                'Choose the File layout: Standard CSV, E-Banker report or Match columns.',
                'Click the dashed box and choose the CSV file; for Match columns check each drop-down.',
                'Press Check file and fix every row listed.',
                'Press Start import and confirm the job completes on the History tab or on Imports.',
            ],
            'images' => [
                'loanbook-import' => 'Import Loan Book with the How to import panel, Portfolio, Reporting period, the three File layout cards and the dashed file box',
                'import-modal' => 'The How to import panel open: the column table, Check file, Download sample file and Download column guide',
            ],
            'routes' => ['loan_applications.loan-book.import.create', 'loan_applications.loan-book.import.store', 'loan_applications.loan-book.import.group', 'loan_applications.loan-book.download-ebanker-template', 'loan_applications.loan-book.sample', 'loan_applications.loan-book.save-loan-book'],
        ],

        'Import history' => [
            'body' => '<p>The Imports tab is the log of every file upload: loan books, clients, collateral registers, credit loss data and EIR data. Use it after any import to confirm the job finished, see how many records were inserted and download the rows that failed. It also has shortcuts to the three main import screens. To open it, find the <b>Data foundation</b> block, expand <b>Customer &amp; Loan Data</b>, choose <b>Imports &amp; Feeds</b> and stay on the <b>Imports</b> tab (breadcrumbs: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Imports &amp; Feeds &gt; Imports</b>). Any signed-in user may open it. The other tabs of the section are covered in <b>E-Banker feed: loads, builds, periods and queries</b> and <b>Take-on workbook: blocks and population</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Imports</b>, with the line <code>Every file uploaded (loan book, clients, collateral, credit loss data and EIR data), with its progress, record counts and the rows that failed</code>.</li>'
                . '<li><b>Filter bar</b>: <b>File name</b> (<code>Search by file name</code>), <b>Status</b> (<code>All statuses</code>, <code>Pending</code>, <code>Processing</code>, <code>Completed</code>, <code>Failed</code>) and <b>Clear</b>.</li>'
                . '<li><b>Table columns</b> in order: <b>File name</b>, <b>Kind</b> (<code>Loan book</code>, <code>Clients</code>, <code>Collateral</code>, <code>Credit loss</code>, <code>EIR intake</code>, or a dash), <b>Status</b>, <b>Uploaded</b>, <b>Records inserted</b>, <b>Failed rows</b> (red and bold when above zero), <b>Ran</b> (when the job started, with <code>took 2m 15s</code> beneath once it finished) and <b>Actions</b>. Newest first, fifteen a page.</li>'
                . '<li><b>Status badge</b>: grey <code>pending</code> (queued), gold <code>processing</code> (running), green <code>completed</code>, red <code>failed</code>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Green round question-mark button</b> (tooltip <code>Help for this screen</code>): opens this article.</li>'
                . '<li><b>Import loan book</b> (green), <b>Import clients</b> and <b>Import collateral</b>: open those import screens.</li>'
                . '<li><b>Download icon</b> in <b>Actions</b> (tooltip <code>Download the failed rows</code>): shown when rows failed and a failed-rows file was kept; it opens the file in a new tab.</li>'
                . '<li><b>Clear</b>: empties the search and the status filter.</li>'
                . '</ul>'
                . '<h4>Reading the failed-rows file</h4>'
                . '<p>For loan book, client and collateral imports it is a CSV with two columns: <b>Row Data</b> (the original row) and <b>Reason</b> (why it was not used). EIR intake files have <b>Contract / Scope</b>, <b>Status</b> and <b>Reason</b>. Fix the rows in the source file and import the whole file again; rows already accepted are simply updated.</p>'
                . '<h4>To check an import</h4>'
                . '<ol>'
                . '<li>Open <b>Imports</b>. Your file is the top row.</li>'
                . '<li>If it reads <code>pending</code> or <code>processing</code>, refresh the page after a moment.</li>'
                . '<li>When it reads <code>completed</code>, compare <b>Records inserted</b> with the rows in your file.</li>'
                . '<li>If <b>Failed rows</b> is above zero, press the download icon and read the <b>Reason</b> column.</li>'
                . '</ol>'
                . '<h4>General import templates</h4>'
                . '<p>Administrators also have a <b>General Imports</b> page (address <code>/imports</code>) listing <b>Import Templates</b>: <b>Template Name</b>, <b>Description</b>, <b>Source Table</b>, <b>Import Count</b>, <b>Status</b> (green Active, red Inactive, grey Deleted) and <b>Actions</b> with <b>Sample</b> (downloads an Excel sample) and an amber <b>Activate</b> or <b>Deactivate</b>. <b>Create Template</b> defines a reusable column layout. It is not needed for the standard loan book, client and collateral imports.</p>'
                . '<h4>What happens next</h4>'
                . '<p>A completed loan book import makes the month available on the Loan Book; a completed collateral import makes the period available on the Collateral Register; a completed client import updates the Clients list.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No file has been imported yet. Use Import loan book (top right) to upload the first one.</code></li>'
                . '<li><code>No import matches these filters.</code></li>'
                . '<li><code>No failed-rows file was kept for this import (it predates the file being recorded). Import the file again to get one.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The status stays <code>pending</code>: the background queue worker is not running. Ask the system administrator.</li>'
                . '<li><code>failed</code> with no records inserted: the file could not be read; check it with <b>Check file</b> on its import screen and load it again.</li>'
                . '<li>A file refused by the check before saving is not listed here: nothing was saved, so no job was created. The problems are shown on the import screen.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block expand Customer & Loan Data, choose Imports & Feeds and stay on the Imports tab.',
                'Find your file (newest first) or search by file name; filter by Status if needed.',
                'Read the Status badge: pending, processing, completed or failed.',
                'Compare Records inserted with the rows in your file.',
                'If Failed rows is above zero, press the download icon and read the Reason column.',
                'Correct the source file and import it again.',
            ],
            'images' => [
                'imports' => 'The Imports tab with the file name and status filters, the Kind column, the status badges, the counts and the Ran column',
            ],
            'routes' => ['imports.index', 'imports.failed-download', 'custom_imports.index', 'custom_imports.create', 'custom_imports.store', 'custom_imports.show', 'custom_imports.edit', 'custom_imports.update', 'custom_imports.destroy', 'custom_imports.sample'],
        ],

        'E-Banker feed: loads, builds, periods and queries' => [
            'body' => '<p>The E-Banker feed brings the core banking data into the platform. The versioned E-Banker queries are run, their results are zipped with a manifest into a <b>pack</b>, and the pack is loaded here; every file is checked before any row is kept. A <b>build</b> then writes the month-end loan book from the packs, proposed by one person and approved by a second. Once a month-end is signed off, its period is <b>locked</b> so nothing restates it. To open it, find the <b>Data foundation</b> block, expand <b>Customer &amp; Loan Data</b>, choose <b>Imports &amp; Feeds</b> and click one of four tabs: <b>E-Banker Loads</b>, <b>Loan Book Builds</b>, <b>Periods Built</b> or <b>Query Register</b> (breadcrumbs, for example: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Imports &amp; Feeds &gt; E-Banker Loads</b>). Each tab carries a chip with its count: all loads, all builds, the period and method rows, and the registered queries.</p>'
                . '<p><b>Who may do what</b>: the four tabs need the permission <code>eir.view</code> (without it they are hidden from the menu). Loading a pack, proposing or approving a build, and locking or unlocking a period need <code>eir.govern</code>; without it those buttons do not appear. A build is approved by a different person from the one who proposed it, and a locked period is never restated.</p>'
                . '<h4>What you see on every tab</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>E-Banker Feed</b>, with the line <code>Packs loaded from E-Banker, the checks each pack passed, and the approved builds of the loan book</code>.</li>'
                . '<li><b>Route in force</b> card: the approved value of the Governance Centre setting <b>How E-Banker data arrives: the feed route</b> (for example <code>Route 1: manual pack upload</code>), or <code>Not set</code>; hover for the full text.</li>'
                . '<li><b>Build method in force</b> card: the approved value of <b>How the monthly loan book is built</b> (for example <code>B: derived from the ledger</code>), or <code>Not set</code>.</li>'
                . '<li><b>Last posting loaded</b> card: the latest ledger date in the loaded packs (tooltip <code>A view or build after this date is refused, never estimated</code>); a dash when nothing is loaded.</li>'
                . '<li><b>Locked periods</b> card: how many periods are locked; hover to list them (<code>No period is locked</code> when none).</li>'
                . '</ul>'
                . '<h4>E-Banker Loads tab</h4>'
                . '<p>The line above the table reads <code>Every pack that came in, by any route. A pack that fails a check is quarantined, with the file and row named. Use Load a pack for the steps.</code> The table lists the latest fifty loads (fifteen a page, with <b>Previous</b>, page numbers, <b>Next</b> and <code>Showing X to Y of Z</code>).</p>'
                . '<ul>'
                . '<li><b>Load</b>: the load number; hover for the first twelve characters of the pack fingerprint.</li>'
                . '<li><b>Pack</b>: the pack name (hover for the full name).</li>'
                . '<li><b>Route</b>: how it came in, in words: <code>Uploaded by hand</code> (route 1), <code>From the feed folder</code> (route 2); other loads, such as the take-on workbook, show their route code as words.</li>'
                . '<li><b>Period</b>: the month the pack covers, or <code>History</code>.</li>'
                . '<li><b>Status</b>: green <code>LANDED</code> (kept and readable), red <code>QUARANTINED</code> (a check failed; kept against the load but nothing reads it), grey for any other state such as <code>PENDING</code>.</li>'
                . '<li><b>Files</b>: the number of files, with <code>(N failed)</code> in red when files failed.</li>'
                . '<li><b>Checks</b>: badges for the pack checks, green <code>N passed</code>, gold <code>N warning</code>, red <code>N failed</code> (failed checks plus failed files) and gold <code>N accepted exceptions</code>; grey <code>None recorded</code> when there are none.</li>'
                . '<li><b>Loaded</b>: date and time, with the user (or <code>system</code>) beneath.</li>'
                . '<li><b>View</b>: the eye button (tooltip <code>Show the checks and watermarks</code> or <code>Hide the checks and watermarks</code>) opens a panel under the row. <b>Checks</b> lists each named pack check with its result (<code>PASS</code>, <code>WARN</code>, <code>FAIL</code> or <code>SKIPPED</code>), its detail and failures: <code>accounts_in_master</code> (every account in the extracts is in the account master), <code>balance_history_ties</code> (the balance history ties to the ledger), <code>month_end_rows</code> (every in-scope account has its month-end row), <code>fee_gl</code> (fee postings agree with the fee GL) and <code>dates_iso</code> (dates are written year first). Failed files follow in red and accepted exceptions in amber (<code>Accepted exception: ...</code>). <b>Watermarks (last key loaded per extract)</b> lists <code>query = last key</code> pairs, or <code>None recorded.</code></li>'
                . '</ul>'
                . '<h4>Loan Book Builds tab</h4>'
                . '<p>The line reads <code>One person proposes a build of the loan book and a second approves it. The differences from the previous build are shown first; a locked period is never restated. Use Propose a build for the steps.</code> The latest twenty builds are listed.</p>'
                . '<ul>'
                . '<li><b>Build</b>: the build number. <b>Method</b>: <code>A</code>, <code>B</code>, <code>C</code>, or <code>In force</code> when left to the governed method of each month-end. <b>Periods</b>: <code>2025-11 to 2025-12</code> for example.</li>'
                . '<li><b>Status</b>: green <code>Built</code>, gold <code>Proposed</code>, grey <code>Rejected</code>, or grey <code>Approved, superseded</code> (tooltip <code>Approved: the build was written as a new build above</code>): an approved proposal is closed this way and the build it produced is listed as its own row.</li>'
                . '<li><b>Proposed by</b>: the name (or <code>system</code>) and the date. <b>Approved by</b>: the approver, or a dash.</li>'
                . '<li><b>Result</b>: per month, <code>2025-12: 4,210 rows, 35 changed, 2 flagged</code> for the first four months, then <code>and N more periods</code> (hover to list them).</li>'
                . '<li><b>Action</b> (only with <code>eir.govern</code>): <b>Approve and build</b> on a proposed build you did not propose, or the grey text <code>Waiting for a second person</code> (tooltip <code>The person who proposed a build cannot approve it</code>) on your own.</li>'
                . '</ul>'
                . '<h4>Periods Built tab</h4>'
                . '<p>The line reads <code>The loan book rows built for each period, by method. A flag names a difference from the source, it never hides one. Lock a period once its month-end is signed off: nothing then restates it.</code> Columns: <b>Period</b>, <b>Method</b>, <b>Rows</b>, <b>Flagged</b> (rows carrying a build flag), <b>Locked</b> (grey <code>Locked</code> badge or the word <code>Open</code>) and, with <code>eir.govern</code>, <b>Action</b>: a closed padlock (tooltip <code>Lock 2025-12</code>) or an open padlock (<code>Unlock 2025-12</code>). A month built by two methods has two rows.</p>'
                . '<h4>Query Register tab</h4>'
                . '<p>The line reads <code>Each E-Banker extract: its source table, its key, and whether it is pulled incrementally from the last key loaded.</code> A <b>Search query, title or table</b> box filters the list. Columns: <b>Query</b> (the id and version, for example <code>P1_01 v2</code>), <b>Title</b>, <b>Source table</b>, <b>Key</b> (<code>row</code> when the extract has no key), <b>Date column</b>, <b>Incremental</b> (green <code>Yes</code> or grey <code>No</code>), <b>Rows loaded</b> (current rows from landed loads) and <b>Last date</b>.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Download the queries</b> (download icon; on E-Banker Loads and Query Register; tooltip <code>The extract queries, as one file to run on E-Banker</code>): downloads <code>MAIIC E-Banker queries (date).sql</code>. Needs <code>eir.view</code>.</li>'
                . '<li><b>Load a pack</b> (upload icon; E-Banker Loads; <code>eir.govern</code>): opens <b>Load an E-Banker pack</b>.</li>'
                . '<li><b>Propose a build</b> (plus icon; Loan Book Builds; <code>eir.govern</code>): opens <b>Propose a build of the loan book</b>.</li>'
                . '<li><b>Approve and build</b> (Loan Book Builds; <code>eir.govern</code>, not the proposer): asks <b>Approve build N?</b> with <code>The build is written to the loan book. The differences from the previous build are kept on the build.</code> Press <b>Approve and build</b> or <b>Cancel</b>.</li>'
                . '<li><b>Padlock</b> (Periods Built; <code>eir.govern</code>): opens <b>Lock (period)</b> or <b>Unlock (period)</b>.</li>'
                . '<li><b>Eye</b> (E-Banker Loads): shows or hides the checks and watermarks of a load.</li>'
                . '</ul>'
                . '<h4>The Load an E-Banker pack window</h4>'
                . '<ul>'
                . '<li>The <b>How to import</b> panel for the <b>E-Banker pack</b>, folded at first: five steps, two notes, <b>Show the N files and sheets read</b> (a table of <b>File or sheet</b>, <b>Required</b> and <b>What it holds</b>: <code>manifest.json</code> is required, each registered query file reads <code>When delivered</code>), <b>Download sample manifest</b> (<code>manifest_sample.json</code>, listing every registered query) and <b>Download column guide</b> (<code>ebanker_pack_column_guide.xlsx</code>). There is no blank data file: the extracts come from E-Banker.</li>'
                . '<li><b>What a pack is</b> and <b>How to make one</b>: run the queries in SQL Developer starting with RUN 0 (ISO dates and a point for decimals), export each result as CSV with its query id in the file name (for example <code>P1_01_ledger_all.csv</code>), write the manifest listing each file, its query id and row count, and zip them together.</li>'
                . '<li><b>What happens when you load it</b>: every file is checked against the manifest first; a failing pack is quarantined and nothing reads it; the same pack twice changes nothing and a changed row is kept as a new version; loading never changes the loan book, a build does.</li>'
                . '<li><b>Pack (zip)</b>: the file chooser (.zip only).</li>'
                . '<li><b>Check only: run the checks and keep nothing</b> tick box.</li>'
                . '<li><b>Cancel</b>, and <b>Load the pack</b> (reads <b>Check the pack</b> when the tick box is ticked, <code>Checking...</code> while working; disabled until a zip is chosen).</li>'
                . '</ul>'
                . '<p><b>What the check verifies</b>: for each file, that it exists, its SHA-256 matches the manifest, its query id is in the register and its query version is the one the register holds, its row count matches the manifest, every amount parses as a number once commas are removed, its dates are readable in the manifest\'s <code>date_format</code> (YYYY-MM-DD when none is given), its key column is present and no key repeats in the file. Across the pack: the five named checks above. A failed file or a failed error-level check (accounts in the master, fee GL) quarantines the pack; a warning-level check (balance history, month-end rows, ISO dates) is recorded but does not. With <b>Check only</b> the window stays open and the result is shown in the How to import panel by file (<b>Column</b> holds the file name, or <code>Pack: accounts in master</code> and so on) with <b>Problem</b> and <b>How to fix it</b>.</p>'
                . '<h4>To load a pack</h4>'
                . '<ol>'
                . '<li>On <b>E-Banker Loads</b>, press <b>Download the queries</b> and have them run on E-Banker; save each result as CSV and write the manifest.</li>'
                . '<li>Zip the CSV files and <code>manifest.json</code> together.</li>'
                . '<li>Press <b>Load a pack</b>, choose the zip under <b>Pack (zip)</b>, tick <b>Check only</b> and press <b>Check the pack</b>.</li>'
                . '<li>Fix anything listed, zip again and check again until every check passes.</li>'
                . '<li>Untick <b>Check only</b> and press <b>Load the pack</b>.</li>'
                . '<li>Read the new row: <code>LANDED</code> with its checks, or <code>QUARANTINED</code>; press the eye to see why.</li>'
                . '</ol>'
                . '<h4>To propose and approve a build</h4>'
                . '<ol>'
                . '<li>On <b>Loan Book Builds</b>, press <b>Propose a build</b>. The window explains: a build writes the month-end loan book for each month in the range and is only a proposal until a second person approves it; <b>A: from the stored run</b> takes E-Banker\'s own Loan Book Report, <b>B: derived from the ledger</b> rebuilds each balance from the postings; a locked month is never restated; staging, PD, LGD, ECL and EIR already run for a month are not re-run by the build.</li>'
                . '<li>Choose <b>From</b> and <b>To</b> months and the <b>Method</b> (<code>In force on each period end</code>, <code>A: from the stored run</code> or <code>B: derived from the ledger</code>; leave it on the method in force unless you have a reason).</li>'
                . '<li>Tick <b>Retire rows from older imports that this build does not produce</b> only if the month still holds rows from old file imports that should go (Mega Farm and out-of-scope rows are never touched).</li>'
                . '<li>Press <b>Propose the build</b> (<code>Proposing...</code>). The message gives the build number and how many rows the preview would change or add.</li>'
                . '<li>A second person with <code>eir.govern</code> opens the tab, reads the <b>Result</b> and presses <b>Approve and build</b>, then confirms.</li>'
                . '<li>Run staging, PD, LGD, collateral allocation, EIR and ECL again for the months built.</li>'
                . '</ol>'
                . '<h4>To lock or unlock a period</h4>'
                . '<ol>'
                . '<li>On <b>Periods Built</b>, press the closed padlock on the month (or the open padlock to unlock).</li>'
                . '<li>Read the window. Lock: <code>A locked period is never restated: the loan book build, staging, the PD, the LGD, the forward-looking route, the ECL, the revenue roll-forward and write-offs all refuse to write it. The governance values in force are kept with the lock.</code> Unlock: <code>Unlocking lets the period be restated. Give the approval for the restatement as the reason.</code></li>'
                . '<li>Type the <b>Reason (written to the audit log)</b>, 10 to 500 characters.</li>'
                . '<li>Press <b>Lock the period</b> or <b>Unlock the period</b> (disabled until the reason has 10 characters).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>A landed pack is read by the builds and by the EIR screens; a quarantined pack is read by nothing. An approved build writes the loan book rows of each month with the method recorded on every row and the differences from the previous build kept on the build; the months then appear on Periods Built and on the Loan Book. A lock or unlock is written to the audit log with its reason, and every engine that writes a month refuses a locked one.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li>E-Banker Loads: <code>No pack has been loaded yet. Download the queries, run them on E-Banker and load the zip here.</code></li>'
                . '<li>Loan Book Builds: <code>No build yet. Use Propose a build to choose the months.</code></li>'
                . '<li>Periods Built: <code>Nothing has been built from the loaded packs yet. Use the Loan Book Builds tab to propose one.</code></li>'
                . '<li>Query Register: <code>The query register is empty.</code> or <code>No query matches the search.</code></li>'
                . '<li>After a load: <code>Pack LANDED (load N).</code>, <code>Pack QUARANTINED (load N).</code> (red), or <code>Pack ALREADY_LANDED (load N).</code> when the same pack was landed before and nothing changed.</li>'
                . '<li>After a check: <code>Check only, nothing kept: every check passed; load the pack to keep it.</code> or <code>Check only, nothing kept: N file(s) and M pack check(s) failed: ...</code></li>'
                . '<li>After a proposal: <code>Build N proposed for 2025-11 to 2025-12; a second person approves it. X changed, Y new rows in the preview.</code> After an approval: <code>Build N approved and built: R rows over P periods.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The file is not a readable zip.</code> or <code>The zip holds no manifest.json.</code>: zip the CSV files and the manifest again; the manifest may sit at the top of the zip or in one folder.</li>'
                . '<li><code>manifest.json is not a pack manifest: it needs a "files" list.</code>: start from <b>Download sample manifest</b>.</li>'
                . '<li><code>N entry name(s) were refused and left out</code>: a file name in the zip tried to leave the pack folder; rename it.</li>'
                . '<li><code>row count 1200 in the manifest, 1199 in the file</code> or <code>SHA-256 differs from the manifest</code>: the file changed after the manifest was written; write the manifest again.</li>'
                . '<li><code>Locked period(s) are never restated: 2025-12</code>: the range includes a locked month; shorten the range, or unlock it with an approved reason.</li>'
                . '<li><code>The landing zone ends at (date); a build for (month-end) is refused, never estimated.</code>: load the pack that covers the month first.</li>'
                . '<li><code>Maker-checker: the approver must be a different person from the requester.</code>: ask a colleague with <code>eir.govern</code> to approve.</li>'
                . '<li><code>Period 2025-12 has no loan book; there is nothing to lock.</code>: build the month before locking it.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block expand Customer & Loan Data, choose Imports & Feeds and click E-Banker Loads.',
                'Press Download the queries, have them run on E-Banker and zip the CSV results with manifest.json.',
                'Press Load a pack, choose the zip, tick Check only and press Check the pack; fix and repeat until clean.',
                'Untick Check only and press Load the pack; the load should read LANDED.',
                'On Loan Book Builds press Propose a build, choose From, To and the Method, and press Propose the build.',
                'A second person with eir.govern presses Approve and build on that row.',
                'After sign-off, on Periods Built press the padlock, give the reason and press Lock the period.',
            ],
            'images' => [
                'eir-feed-loads' => 'The E-Banker Loads tab with the four cards, the load rows, the status and check badges and an opened row of checks and watermarks',
                'eir-feed-builds' => 'The Loan Book Builds tab with Propose a build, the status badges, the Result per month and Approve and build',
                'eir-feed-periods' => 'The Periods Built tab with rows and flagged counts by method, the Locked badge and the padlock buttons',
                'eir-feed-queries' => 'The Query Register tab with the search box, the source table, key, date column and Incremental badge of each extract',
            ],
            'routes' => ['eir-feed.index', 'eir-feed.queries', 'eir-feed.land', 'eir-feed.build', 'eir-feed.approve', 'eir-feed.lock-period'],
        ],

        'Take-on workbook: blocks and population' => [
            'body' => '<p>The 109 loans E-Banker took on at 31 July 2024 have no E-Banker history before that date; Finance\'s take-on workbook (one amortisation schedule block per facility) and the mapping workbook returned with the E-Banker account, the confirmation and the fees are their only record. This screen loads the two workbooks, checks every block, lets Finance confirm a mapping or enter fees on the screen, and builds the <b>take-on population</b>: each migrated account with the basis its EIR is built on. The build is proposed by one person and approved by a second. To open it, find the <b>Data foundation</b> block, expand <b>Customer &amp; Loan Data</b>, choose <b>Imports &amp; Feeds</b> and click <b>Take-on Blocks</b> or <b>Take-on Population</b> (breadcrumbs, for example: <b>Data foundation &gt; Customer &amp; Loan Data &gt; Imports &amp; Feeds &gt; Take-on Blocks</b>). The chips count the blocks of the latest workbook and the accounts in the population.</p>'
                . '<p><b>Who may do what</b>: both tabs need <code>eir.view</code>. Loading the workbooks, confirming a mapping, entering fees and proposing or approving the build need <code>eir.govern</code>. The person who proposes the build cannot approve it.</p>'
                . '<h4>What you see on both tabs</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Take-on Schedules</b>, with the line <code>The take-on workbook as loaded: each block with its account mapping, fees and checks, and the take-on population built from it</code>.</li>'
                . '<li><b>Basis in force</b> line (click to open): the approved value of the Governance Centre setting <b>Pre-migration history of the take-on loans</b>, for example <code>Recompute where block and fees exist, else take-on balance</code>, or <code>Not set: no approved value in the Governance Centre</code>. Opened, it explains: where a facility has a mapped block and its fees, the EIR is solved from origination and rolled to 31 July 2024; otherwise the loan starts at its take-on balance and is flagged; a blank fee means no such fee, a zero means known to be nil; every figure keeps the sheet and cell it came from.</li>'
                . '<li><b>Six cards</b> (once a workbook is loaded): <b>Blocks</b>, <b>Mapped</b> (blocks with an account in the master, including flagged ones), <b>Flagged</b>, <b>Not matched</b> (no account mapped), <b>Refused</b> and <b>No fee row</b>.</li>'
                . '</ul>'
                . '<h4>Take-on Blocks tab</h4>'
                . '<p>The line above the table names the workbook loaded, its status and when (or <code>No workbook has been loaded yet.</code>). The <b>Search block, facility or account</b> box filters by block number, title, facility, account or status. Fifteen blocks a page.</p>'
                . '<ul>'
                . '<li><b>#</b>: the block number, with <code>row N</code> (its row on the schedule sheet).</li>'
                . '<li><b>Block</b>: the block title, a gold <code>restructured</code> badge when the Blocks sheet says so, and <code>N lines</code> (schedule lines read).</li>'
                . '<li><b>Facility</b>: the facility name, with its value date to maturity date.</li>'
                . '<li><b>Principal</b> and <b>Rate %</b>.</li>'
                . '<li><b>Account</b>: the E-Banker account (a dash when none), <code>was (account)</code> in amber when Finance corrected it, and the proposer\'s <code>(High/Medium/Low) confidence</code> with <code>, confirmed Y</code> or <code>N</code>.</li>'
                . '<li><b>Fees</b>: the total integral fees, or amber <code>blank</code> when the block has no fee row.</li>'
                . '<li><b>Status</b>: green <code>MAPPED</code>, gold <code>FLAGGED</code> (mapped, with something to review), red <code>REFUSED</code> (the build leaves it out), grey <code>NOT_MATCHED</code> (no account).</li>'
                . '<li><b>Checks</b>: red <code>N refused</code>, gold <code>N flags</code>, or <code>None</code>.</li>'
                . '<li><b>Actions</b>: the eye (tooltip <code>Show the checks</code>) opens <b>Checks on block N</b> with each <code>Refused: ...</code> and <code>Flag: ...</code> line, or <code>No check raised anything on this block.</code>, and any <code>Comment</code>; the pencil (tooltip <code>Confirm the mapping or enter the fees</code>; <code>eir.govern</code>) opens the edit window.</li>'
                . '</ul>'
                . '<p><b>The checks on a block</b>: refused when the mapped account is not in the account master, the account already has another block, the mapping is marked N with no corrected account, or schedule lines have unreadable dates. Flagged when no account is mapped (status NOT_MATCHED), there is no take-on posting on 31 Jul 2024 for the account, the block principal differs from the take-on posting, there is no fee row (<code>no fee row: not assumed fee-free</code>), or schedule lines were out of due-date order (they are sorted). Each message names the sheet cell.</p>'
                . '<h4>The block edit window</h4>'
                . '<ul>'
                . '<li>Title <b>Block N: (title)</b> and the note <code>What you enter here replaces the workbook\'s entry and is written to the audit log.</code></li>'
                . '<li><b>Mapping correct?</b>: <code>Not yet</code>, <code>Y: (proposed account)</code> or <code>N: another account</code>; with N, <b>Correct account</b> appears. <b>Comment</b> is free text. <b>Save the mapping</b> (enabled once Y or N is chosen) saves these three.</li>'
                . '<li>Fees: <b>Arrangement fee</b>, <b>Legal fees</b>, <b>Other integral fees</b>, <b>Date charged</b>, <b>Deducted from payout?</b> (<code>-</code>, <code>Y</code>, <code>N</code>) and <b>Source</b> (<code>Offer letter ref or receipt</code>). Leave a fee blank if there is no such fee; enter 0 if it is known to be nil. <b>Save the fees</b> saves them and totals them.</li>'
                . '<li><b>Close</b>, or click the grey background, leaves without saving.</li>'
                . '</ul>'
                . '<h4>Take-on Population tab</h4>'
                . '<p>The line reads <code>The accounts E-Banker migrated on 31 July 2024 (the 301, 303 and 305 legs), each with the basis it was built on.</code> The <b>Search account or basis</b> box filters the list. Columns:</p>'
                . '<ul>'
                . '<li><b>Account</b>; <b>Basis</b>: green <code>RECOMPUTED</code> (EIR solved from origination with the fees), grey <code>TAKEON_BALANCE</code> (starts at its take-on balance), red <code>REFUSED</code> (under the strict basis, no evidence).</li>'
                . '<li><b>Origination</b>, <b>Original principal</b>, <b>Rate %</b> and <b>Fees</b>: from the block.</li>'
                . '<li><b>Take-on principal</b> and <b>Opening recovery</b>: E-Banker\'s opening postings of 31 July 2024.</li>'
                . '<li><b>Schedule balance 31 Jul 2024</b> and <b>Difference</b> (take-on principal less opening recovery less the schedule balance; red when positive, teal when negative).</li>'
                . '<li><b>EIR from origination</b>; <b>EIR with no fee</b> (tooltip <code>The rate the workbook schedule itself carries, with no fee</code>).</li>'
                . '<li><b>EIR check</b> (tooltip <code>The governed take-on solve check (take-on EIR plausibility)</code>): green <code>Passed</code>, red <code>Refused</code> with the reason beneath (for example the schedule does not retire the loan, or with no fee it solves outside the rates its lines charge by more than the governed margin), or <code>Not checked</code>. A refused check leaves the loan on its take-on balance until Finance corrects the block.</li>'
                . '<li><b>Amortised cost 31 Jul 2024</b> (the recomputed figure), <b>Take-on balance</b> (principal plus opening interest less recovery) and <b>Difference</b> (take-on balance less amortised cost).</li>'
                . '<li><b>Schedule lines</b> written, and <b>Flags</b> in amber, for example <code>no block mapped to this account</code>, <code>fees not supplied (blank fee row)</code> or <code>mapping not yet confirmed by Finance</code>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Load take-on workbook</b> (upload icon; Take-on Blocks; <code>eir.govern</code>): opens <b>Load the take-on workbook</b>.</li>'
                . '<li><b>Propose the take-on build</b> (both tabs; <code>eir.govern</code>; shown when a workbook is loaded and no proposal is open): asks <b>Propose the take-on build?</b> with <code>A second person approves it on this screen; only the approval builds.</code> Press <b>Propose</b> or <b>Cancel</b>.</li>'
                . '<li><b>Approve and build</b> (green; <code>eir.govern</code>; shown when a proposal is open and you did not make it; tooltip <code>Proposed by (name); approving builds the population and replaces the previous build</code>): asks <b>Approve and build the take-on population?</b> Press <b>Approve and build</b> or <b>Cancel</b>.</li>'
                . '<li>Gold badge <code>Build proposed by you: a second person approves</code>: shown to the proposer instead of the approve button.</li>'
                . '<li><b>Eye</b> and <b>pencil</b> on a block: see above.</li>'
                . '</ul>'
                . '<h4>The Load the take-on workbook window</h4>'
                . '<ul>'
                . '<li>The <b>How to import</b> panel for the <b>Take-on workbook</b>: four steps, the note that there is no blank template, <b>Hide the 4 files and sheets read</b> (<code>Original workbook</code>: sheets <code>Loan Book</code> and <code>Amortisation and Repayments</code>; mapping sheet <code>Mapping</code>: A Loan Book row, J proposed account, K confidence, X confirmed, Y corrected account, Z comment; <code>Upload summary</code>: C Loan Book row, AO arrangement fee, AP legal fees, AQ other fees, AR fee date, AS fee deducted, AT fee source; <code>Blocks</code>: A block number, E row of the block, G Loan Book row, J restructured) and <b>Download column guide</b> (<code>takeon_column_guide.xlsx</code>).</li>'
                . '<li><b>What happens</b>: each block is checked; loading does not change any EIR, the approved take-on build does; a later corrected workbook is loaded the same way and the earlier load is kept.</li>'
                . '<li><b>Original workbook (as received)</b> and <b>Mapping workbook with fees</b>: two .xlsx choosers.</li>'
                . '<li><b>Cancel</b>; <b>Check only</b> (tooltip <code>Read both workbooks and gate every block; nothing is kept</code>; <code>Checking...</code>); <b>Load the workbook</b> (<code>Loading...</code>). Both need the two files chosen. Check only keeps the window open and lists each refusal (Error) and flag (Warning) with <b>Column</b> <code>Block N</code> and how to fix it.</li>'
                . '</ul>'
                . '<h4>To load the workbooks and build the population</h4>'
                . '<ol>'
                . '<li>On <b>Take-on Blocks</b>, press <b>Load take-on workbook</b>.</li>'
                . '<li>Choose the original workbook as received (saved in Excel so formulas carry values) and the mapping workbook.</li>'
                . '<li>Press <b>Check only</b> and read the result; ask Finance to correct cells named in refusals.</li>'
                . '<li>Press <b>Load the workbook</b>. The cards and the blocks fill in.</li>'
                . '<li>For each flagged block, press the eye, then the pencil to confirm the mapping (Y, or N with the correct account) and enter the fees from the offer letter; save each part.</li>'
                . '<li>Press <b>Propose the take-on build</b> and confirm.</li>'
                . '<li>A second person with <code>eir.govern</code> presses <b>Approve and build</b> and confirms; then review the <b>Take-on Population</b> tab.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The approved build replaces the previous population. For each migrated account it records the basis, the recomputed EIR and amortised cost where the block and fees allow, the EIR check and the flags; the EIR engine starts each take-on loan from that record. Mapping confirmations and fee entries are written to the audit log (<code>Take-on Mapping Confirmed</code>, <code>Take-on Fees Entered</code>) and count over the workbook\'s values at the next build.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li>Blocks: <code>No blocks loaded. Open Load a take-on workbook above.</code> or <code>No block matches the search.</code></li>'
                . '<li>Population: <code>Not built yet. Load the workbook, then propose the take-on build (top right); a second person approves it.</code> or <code>No account matches the search.</code></li>'
                . '<li>After a load: <code>Take-on workbook LANDED: N blocks, L schedule lines.</code>; <code>QUARANTINED</code> (red) when no block could be landed.</li>'
                . '<li>After a check: <code>Check only, nothing kept: N blocks, L schedule lines; the workbook can be landed.</code> (with the number of block refusals that would land as refused), <code>...; nothing in it can be landed.</code>, or <code>Check only: these two workbooks are already landed (load N); loading them again changes nothing.</code></li>'
                . '<li>Edits: <code>Block N: mapping confirmed.</code>, <code>Block N: mapping corrected to (account).</code>, <code>Block N: fees recorded (total X).</code> or <code>(no fee row)</code>.</li>'
                . '<li>Build: <code>Build proposed. A second person approves it on this screen; nothing is built until then.</code> and <code>Take-on population built on approval: N accounts, R recomputed from origination, T at the take-on balance, F refused.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The original has no Loan Book sheet.</code>, <code>The mapping workbook has no Mapping sheet.</code> (or Upload summary, Blocks): the wrong file was chosen in one box, or a sheet was renamed.</li>'
                . '<li><code>Marking a mapping N needs the correct account number.</code>: fill <b>Correct account</b>.</li>'
                . '<li><code>Maker-checker: the take-on build is approved by a different person from the one who proposed it, and both must be named.</code>: ask a colleague to approve.</li>'
                . '<li><code>A later take-on workbook has been landed since the build was proposed; propose the build again.</code></li>'
                . '<li><code>No take-on workbook has been landed.</code>: load the workbooks before proposing.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block expand Customer & Loan Data, choose Imports & Feeds and click Take-on Blocks.',
                'Press Load take-on workbook, choose the original and the mapping workbooks and press Check only.',
                'Press Load the workbook and review the cards and the Status and Checks of each block.',
                'Use the pencil on a block to confirm its mapping or enter its fees, and save.',
                'Press Propose the take-on build; a second person with eir.govern presses Approve and build.',
                'Open Take-on Population and review the Basis, EIR check and Flags of each account.',
            ],
            'images' => [
                'eir-takeon-blocks' => 'The Take-on Blocks tab with the Basis in force line, the six cards and the blocks with their Status and Checks badges',
                'eir-takeon-population' => 'The Take-on Population tab with the Basis badges, the EIR from origination, the EIR check and the flags of each account',
            ],
            'routes' => ['eir-takeon.index', 'eir-takeon.upload', 'eir-takeon.build', 'eir-takeon.confirm', 'eir-takeon.fees'],
        ],

    ],
];
