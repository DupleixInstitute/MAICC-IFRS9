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
            'body' => '<p>The Clients page is the customer master: one record per borrower, keyed by the customer identifier (CIF) from the core banking system. Every loan book row, collateral record and allocation is tied to a client by that identifier, and loan book imports create or update clients automatically. Use it to look up a borrower, check the profile and correct a name. To open it, open the sidebar, expand <b>Customer &amp; Loan Data</b> and choose <b>Clients</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Clients.</li>'
                . '<li><b>Filter</b> button: opens a small panel with a <b>Status</b> drop-down whose options are blank (live clients only), <code>With Trashed</code> (include deleted clients) and <code>Only Trashed</code> (deleted clients only).</li>'
                . '<li><b>Search box</b> (placeholder <code>Search...</code>): matches the customer ID, the name or the phone number. A <b>Reset</b> link appears beside it once you have typed something.</li>'
                . '<li><b>Import Names File</b> button (dark) opens the client import page; <b>Create Client</b> (green, plus sign) opens the new client form. Both appear only if your role may create clients.</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b>, <b>Name</b>, <b>Phone</b>, <b>Updated</b>, <b>Actions</b>. Ten clients are shown per page.</li>'
                . '<li><b>Row actions</b>: the green eye (tooltip <code>View</code>) opens the client profile; the gold pencil (tooltip <code>Edit</code>) opens the edit form and is shown only if your role may update clients.</li>'
                . '<li><b>Empty state</b>: <code>No clients found</code> with the hint <code>Get started by creating a new client.</code></li>'
                . '<li><b>Pagination</b>: <b>Previous</b>, page numbers and <b>Next</b> under the table.</li>'
                . '</ul>'
                . '<h4>Field by field (Create Client)</h4>'
                . '<ul>'
                . '<li><b>Customer ID (CIF)</b>: the customer number from the core banking system, for example <code>1044</code>. Required.</li>'
                . '<li><b>Name</b>: the customer or company name. Required.</li>'
                . '<li><b>Phone Number</b>: mobile number in the format shown under the box, <code>Format: 07XXXXXXXX (10 digits)</code>. If the number does not match, the form shows <code>Phone number must be 10 digits starting with 07</code>.</li>'
                . '<li><b>Cancel</b> returns to the list; <b>Save</b> stores the client and shows <code>Client created successfully.</code></li>'
                . '</ul>'
                . '<h4>The client profile (green eye)</h4>'
                . '<p>The header reads <b>Clients / (name)</b>. The left panel shows the name, type, mobile and email, SMS and Email buttons (if messaging is enabled for your role) and a menu shown according to your permissions: <b>Basic Profile</b>, <b>Shareholders</b>, <b>Balance Sheet</b>, <b>Income Statement</b>, <b>Ratio Analysis</b>, <b>Porter\'s Five Forces Analysis</b>, <b>Loan Applications</b>, <b>Notes</b>, <b>Files</b> and <b>Login Details</b>, with <b>Edit</b> and <b>Delete</b> buttons at the bottom. The right panel is a two-column table: <b>Type</b>, <b>Branch</b>, <b>Status</b> (amber <code>Pending</code> or <code>Inactive</code>, green <code>Active</code> or <code>Archived</code>, red <code>Deceased</code>), <b>CIF</b>, then for corporate clients <b>Trading Name</b>, <b>Customer Legal Type</b>, <b>Certificate Of Registration No</b>, <b>Year Of Registration</b>, <b>Years In Business</b>, <b>Country Of Registration</b>, <b>Audit Status</b>, <b>Annual Inflation Rate - Real</b>, <b>Annual Inflation Rate - Norminal</b>, <b>Industrial Sector</b>, <b>Years At Present Address</b>, <b>Main Bank</b>, <b>Second Bank</b>, <b>Third Bank</b>; then <b>Mobile</b>, <b>Tel</b>, <b>Date of Birth</b> (individuals), <b>Email</b>, <b>Marital Status</b> (individuals), <b>ID Number</b>, <b>Zip</b>, <b>Country</b>, <b>Region</b>, <b>Inkhundla</b>, <b>Address</b> and <b>Postal Address</b>.</p>'
                . '<h4>To edit a client</h4>'
                . '<ol>'
                . '<li>Click the gold pencil on the row, or <b>Edit</b> on the profile page. The header reads <b>Clients / Edit</b>.</li>'
                . '<li>Change <b>CIF</b>, <b>Name</b>, <b>Mobile</b>, <b>Type</b> (<code>Individual</code> or <code>Corporate</code>) or <b>Status</b> (<code>Pending</code>, <code>Inactive</code>, <code>Active</code>, <code>Archived</code>).</li>'
                . '<li>Click <b>Save</b>. You see <code>Client updated successfully.</code></li>'
                . '</ol>'
                . '<h4>To delete a client</h4>'
                . '<ol>'
                . '<li>Open the profile and click the red <b>Delete</b> button at the foot of the left panel.</li>'
                . '<li>In the <b>Delete Record</b> window click <b>Delete Record</b>, or <b>Nevermind</b> to keep the client.</li>'
                . '<li>The list shows <code>Client deleted successfully.</code> Deleted clients can still be listed with <b>Only Trashed</b>.</li>'
                . '</ol>'
                . '<h4>To import a names file</h4>'
                . '<ol>'
                . '<li>Click <b>Import Names File</b>. The header reads <b>Clients / Import</b>.</li>'
                . '<li>Under <b>Select Import Type</b> choose <b>Legacy Format</b> (a two-column file: <code>customer_id</code> and <code>public_name</code> in the form <code>PHONE-NAME</code>, for example <code>1001,0774892762-John Doe</code>) or <b>Custom Mapping</b> (any columns, which you match to client fields).</li>'
                . '<li>Under <b>Upload CSV File</b> click the dashed box (<code>Select a CSV file</code>) and pick a .csv, .txt, .xlsx or .xls file.</li>'
                . '<li>Legacy: the <b>Legacy Import Setup</b> box confirms the two-column rule and <code>Auto-mapping applied</code>. Custom: <b>Map CSV Columns to Database Fields</b> shows <b>File Preview (first 3 rows)</b> and one drop-down per column (<code>-- Ignore this column --</code> or a field; <code>customer_id</code> is marked <code>* (required)</code>), each row reading <code>Mapped</code> or <code>Not mapped</code>, with a <b>Field Descriptions</b> box beneath.</li>'
                . '<li>Use <b>Download Legacy Template</b> or <b>Download Custom Template</b> for a starting file.</li>'
                . '<li>Click <b>Start Import</b>. You return to the list with <code>Import started successfully!</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Imported rows create a client where the customer ID is new and update the name where it exists. The number inserted and any rejected rows are shown on the <b>Imports</b> page. Loan book and collateral imports also create missing clients, so a names file is only needed for richer client data.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The customer_id field must be mapped.</code>: in Custom Mapping, set one column drop-down to <code>customer_id</code>.</li>'
                . '<li><code>customer_id is required but missing</code> in the rejected-rows file: the row had a blank customer ID; fill it in and re-import.</li>'
                . '<li><code>Phone number must be 10 digits starting with 07</code>: correct the phone number on the create form.</li>'
                . '<li>A client is missing: it may be deleted; choose <b>With Trashed</b> in the Filter panel.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Customer & Loan Data and choose Clients.',
                'Type part of the customer ID, name or phone in the Search box to find a client.',
                'Click the green eye to open the profile, or the gold pencil to edit.',
                'To add a client manually, click Create Client, fill in Customer ID (CIF), Name and Phone Number, then click Save.',
                'To load many clients at once, click Import Names File, choose the format, pick the file, map the columns if needed and click Start Import.',
                'Check the Imports page for the result and any rejected rows.',
            ],
            'images' => [
                'clients' => 'The Clients list with Filter, Search, Import Names File and Create Client, and the green eye and gold pencil actions',
                'clients-create' => 'The Clients / Create form with Customer ID (CIF), Name and Phone Number',
            ],
            'routes' => ['clients.index', 'clients.create', 'clients.store', 'clients.show', 'clients.edit', 'clients.update', 'clients.destroy', 'clients.import.create', 'clients.import.store', 'clients.search'],
        ],

        'Loan Book' => [
            'body' => '<p>The Loan Book is the contract-level view of everything imported for a reporting month, with the IFRS 9 stage and the calculated risk figures for each contract. It is the page finance and risk staff use to check what was loaded, confirm staging and review the expected credit loss (ECL) result. When you open it without choosing a month it shows the latest reporting period on file. To open it, open the sidebar, expand <b>Customer &amp; Loan Data</b> and choose <b>Loan Book</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Loan Book Management.</li>'
                . '<li><b>Header buttons</b>: <b>Export Summary</b> (green, download icon) opens the summary export window; <b>Get Disbursements</b> (dark) opens the disbursement report window; <b>Import Loan Book</b> (green, upload icon) opens the import page (see the next article).</li>'
                . '<li><b>KPI tiles</b> (they follow the filters): <b>Total Loans</b> (number of contracts), <b>Total EAD (MWK)</b> (sum of carrying amounts; EAD means exposure at default, the balance at risk), <b>Stage 3 Exposure (MWK)</b> with the count of Stage 3 loans beneath it, <b>ECL Coverage</b> (total ECL divided by total EAD, as a percentage) and <b>Total ECL (MWK)</b>.</li>'
                . '<li><b>Filter bar</b>: <b>Year</b> (2022 to 2030), <b>Month</b> (January to December), <b>IFRS 9 Stage</b> (<code>All stages</code>, <code>Stage 1</code>, <code>Stage 2</code>, <code>Stage 3</code>) and <b>Search</b> (placeholder <code>Search by Contract ID or Customer...</code>, matching the contract ID, the customer ID or the client name). The table and tiles refresh as soon as you change a filter.</li>'
                . '<li><b>Table columns</b> in order: <b>Contract ID</b>, <b>Customer</b>, <b>Stage</b>, <b>EAD (MWK)</b>, <b>PD</b>, <b>LGD</b>, <b>ECL (MWK)</b>, <b>Coverage</b>, <b>Overdue Days</b>. Money is shown in accounting format (thousands separators, two decimals, negatives in brackets); PD and LGD are shown as percentages.</li>'
                . '<li><b>Stage badge</b>: green <code>Stage 1</code> (performing), gold <code>Stage 2</code> (significant increase in credit risk), red <code>Stage 3</code> (credit-impaired). The badge shows the final stage: the post-qualitative stage if set, otherwise the calculated stage, otherwise the stage from the import.</li>'
                . '<li><b>Coverage</b> column: the contract\'s ECL divided by its carrying amount, as a percentage.</li>'
                . '<li><b>Overdue Days</b>: green when 0, amber up to 30 days, red above 30.</li>'
                . '<li><b>Pagination</b>: ten contracts per page with <b>Previous</b>, page numbers and <b>Next</b>.</li>'
                . '</ul>'
                . '<h4>To export the summary</h4>'
                . '<ol>'
                . '<li>Click <b>Export Summary</b>. The window <b>Export Loan Book Summary Report</b> opens.</li>'
                . '<li>Optionally pick a portfolio in <b>Select Portfolio (Optional)</b> (default <code>All Portfolios</code>).</li>'
                . '<li>Choose <b>Start Period</b> and <b>End Period</b> (month and year). The <b>Export Mode</b> box is fixed at <code>Summary (Aggregated by period and stage)</code>.</li>'
                . '<li>Click <b>Export</b> (it reads <code>Exporting...</code> while working) or <b>Cancel</b>. The file opens in a new tab and downloads.</li>'
                . '</ol>'
                . '<h4>To get the disbursement report</h4>'
                . '<ol>'
                . '<li>Click <b>Get Disbursements</b>. The window <b>Export Disbursement Report</b> opens.</li>'
                . '<li>Pick a portfolio if needed, then <b>Start Period</b> and <b>End Period</b>.</li>'
                . '<li>Under <b>Export Mode</b> choose <code>Summary Only (Aggregated data)</code> or <code>Summary + Detailed (Individual contracts)</code>.</li>'
                . '<li>Click <b>Export Disbursements</b>. The report lists the total amount disbursed, the contract count and, in detailed mode, every loan originated in the date range.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The figures on this page are written by later steps of the month-end close: staging rules and SICR triggers update the stage, the PD and LGD models fill in the PD and LGD columns, and the ECL calculation fills in ECL and coverage. A freshly imported month therefore shows stages but zero PD, LGD and ECL until those steps have run. The same period, portfolio and stage filters are used by the dashboard and the IFRS 9 reports.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The table is empty for the month you chose: no loan book has been imported for that year and month. Check the <b>Imports</b> page.</li>'
                . '<li>PD, LGD and ECL all read 0.00: the ECL calculation has not been run for this period yet. Follow the period workspace checklist.</li>'
                . '<li><code>Please select both start and end periods</code> or <code>Start period cannot be later than end period</code>: correct the dates in the export window.</li>'
                . '<li>A customer shows as a number instead of a name: the client record has no name; edit it on the Clients page or re-import the names file.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Customer & Loan Data and choose Loan Book.',
                'Pick the Year and Month you want to review; the tiles and table refresh.',
                'Narrow the list with the IFRS 9 Stage drop-down or type a contract ID or customer name in Search.',
                'Read the Stage badge (green Stage 1, gold Stage 2, red Stage 3) and the EAD, PD, LGD, ECL and Coverage columns for each contract.',
                'Click Export Summary, choose the period range and click Export to download the stage summary.',
            ],
            'images' => [
                'loanbook' => 'Loan Book Management with the KPI tiles, year, month, stage and search filters and the stage badges',
            ],
            'routes' => ['loan_applications.loan-book', 'loan_applications.loan-book.summary'],
        ],

        'Importing a loan book' => [
            'body' => '<p>The loan book import loads one month of contracts into a portfolio. It is the first step of every month-end close, done once per portfolio per month. Before you start, the portfolio must exist under <b>Portfolio Setup</b> and you should know the reporting month the file represents. To open the page, open the Loan Book and click <b>Import Loan Book</b>; the header reads <b>Loan Books / Import</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Portfolio Group</b>: a searchable drop-down of your loan portfolios. Required.</li>'
                . '<li><b>Reporting Period</b>: a month picker with the hint <code>Select the month and year for this loan book data</code>. Required.</li>'
                . '<li><b>Select Import Type</b>, three cards: <b>Legacy Format</b> (<code>Use the traditional format with fields in the sample file</code>), <b>E-Banker Format</b> (<code>Use the E-Banker format</code>) and <b>Custom Mapping</b> (<code>Map your CSV columns to any database fields</code>, selected by default). Click a card to select it.</li>'
                . '<li><b>E-Banker Format Requirements</b> box (shown for E-Banker): the file should have at least 15 to 20 columns, data rows start with serial numbers, it may include <code>Loan Type :</code> context rows and must include contract, customer, dates and balance columns.</li>'
                . '<li><b>Upload File</b>: a dashed box reading <code>Select a CSV file</code>; click it and choose a .csv or .txt file. The file name replaces the text once chosen.</li>'
                . '<li><b>Map CSV Columns</b> (Custom Mapping only, after a file is chosen): <b>File Preview (first 3 rows)</b> followed by one drop-down per file column (<code>-- Ignore this column --</code> or a loan book field). Common headings such as <code>Customer ID</code>, <code>Contract ID</code>, <code>Value Date</code>, <code>Maturity Date</code>, <code>Principal</code>, <code>Carrying Amount</code>, <code>Sector Code</code> and <code>Internal Grade</code> are matched automatically; each row shows <code>Mapped</code> or <code>Not mapped</code>.</li>'
                . '<li><b>Download Legacy Template</b> and <b>Download E-Banker Template</b> buttons: sample files with dummy rows.</li>'
                . '<li><b>Cancel</b> returns to the Loan Book; <b>Start Import</b> submits the file.</li>'
                . '<li><b>E-Banker Format Error</b> panel (red, top of page): appears if an E-Banker file is rejected, with the reason and a <b>Download E-Banker Template</b> button.</li>'
                . '</ul>'
                . '<h4>File format: Legacy and Custom</h4>'
                . '<p>The legacy template has these columns, and the importer also reads them directly if a custom mapping leaves the essential fields unmapped: <code>customer_id</code> (required; the client is created or updated from it), <code>name</code>, <code>contract_id</code> (required), <code>value_date</code> (disbursement or value date, required), <code>maturity_date</code> (required), <code>tenor</code>, <code>interest_rate</code>, <code>principal</code>, <code>disbursed</code>, <code>carrying_amount</code>, and the arrears buckets <code>1-30 Days</code>, <code>31-90 Days</code>, <code>91-180 Days</code>, <code>181-270 Days</code>. Optional extra columns are <code>type</code> (product group), <code>industry_code</code>, <code>industry_type</code> and <code>internal_grade</code>. Dates may be <code>dd/mm/yyyy</code>, <code>mm/dd/yyyy</code> or <code>yyyy-mm-dd</code>; amounts may contain commas and a lone <code>-</code> is read as zero. If principal is zero the carrying amount is used. Example row: <code>1,ABC Hotel,1,01/01/2020,31/12/2025,5,15.00,1000000.00,950000.00,1000000.00,50000.00,20000.00,10000.00,5000.00</code>.</p>'
                . '<h4>File format: E-Banker</h4>'
                . '<p>Use the core banking export as it comes. Rows containing <code>Loan Type : code-name</code> set the product group and code for the rows beneath; header rows are skipped; each data row starts with a serial number. The importer reads the customer ID, contract number, name, dates, interest rate, principal, carrying amount and the four arrears buckets from fixed column positions, so do not add or remove columns.</p>'
                . '<h4>Staging on import</h4>'
                . '<p>Every row receives a pre-qualitative and post-qualitative IFRS 9 stage from its arrears buckets. Legacy and custom rows take the days past due as the lower bound of the oldest non-empty bucket and compare it with the quantitative thresholds (default: Stage 2 from 31 days, Stage 3 from 181 days; configurable under <b>IFRS 9 Model Setup</b>, <b>Staging &amp; SICR Rules</b>, <b>Quantitative Thresholds</b>). E-Banker rows use the bucket rule directly: any 181-270 balance is Stage 3, any 31-90 or 91-180 balance is Stage 2, otherwise Stage 1.</p>'
                . '<h4>What happens next</h4>'
                . '<p>For Legacy and Custom you return to the Loan Book with <code>Import started! You will be notified once it completes.</code>; for E-Banker the message is <code>Import job queued successfully. File: (name)</code>. The file is processed in the background. Rows for the same customer, portfolio, period and contract replace earlier values, so re-importing a corrected file for the same month is safe. Rejected rows go to an exceptions file counted on the <b>Imports</b> page. A remaining tenor in years is calculated from the maturity date and the reporting month end.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>customer_id missing in data array</code> in the exceptions file: the row had no customer ID, or the mapping did not point a column at <code>customer_id</code>.</li>'
                . '<li><code>Missing/invalid value_date or maturity_date</code>: a date is blank or in an unrecognised format.</li>'
                . '<li><code>Failed to import E-Banker file: ... Please ensure your file matches the E-Banker format template.</code>: the file is not the core banking layout; download the template and compare.</li>'
                . '<li>The import completes with zero inserted rows: usually the header row was not on line 1 or the file is not comma-separated.</li>'
                . '<li>Loans appear under the wrong month: the <b>Reporting Period</b> was wrong; re-import with the correct month.</li>'
                . '</ul>',
            'steps' => [
                'Open the Loan Book and click Import Loan Book.',
                'Choose the Portfolio Group and set the Reporting Period to the month the file represents.',
                'Under Select Import Type click Legacy Format, E-Banker Format or Custom Mapping.',
                'Click the dashed Upload File box and choose your CSV file.',
                'For Custom Mapping, check each column drop-down under Map CSV Columns; customer_id and contract_id must be mapped.',
                'Click Start Import and wait for the confirmation message.',
                'Open Customer & Loan Data, Imports to confirm the status is completed and check the Exception Records count.',
            ],
            'images' => [
                'loanbook-import' => 'Loan Books / Import with Portfolio Group, Reporting Period, the three import type cards, the upload box and the template buttons',
            ],
            'routes' => ['loan_applications.loan-book.import.create', 'loan_applications.loan-book.import.store', 'loan_applications.loan-book.import.group', 'loan_applications.loan-book.download-ebanker-template', 'loan_applications.loan-book.save-loan-book'],
        ],

        'Import history' => [
            'body' => '<p>The Imports page is the log of every background file load: client names files, loan books and collateral registers. Use it after any import to confirm the job finished, see how many rows were inserted and download the rows that were rejected. To open it, open the sidebar, expand <b>Customer &amp; Loan Data</b> and choose <b>Imports</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Imports.</li>'
                . '<li><b>Filter</b> button (empty panel), <b>Search</b> box (placeholder <code>Search…</code>) matching the file name, and a <b>Reset</b> link.</li>'
                . '<li><b>Table columns</b> in order: <b>Name</b> (the uploaded file name), <b>Status</b>, <b>Date</b> (when the file was submitted), <b>Inserted</b> (rows written), <b>Exception Records</b> (rows rejected), <b>Start</b>, <b>Completed</b> and <b>Duration</b> (shown as hours, minutes and seconds when both start and completion times exist). Newest first, with pagination.</li>'
                . '<li><b>Status chips</b>: amber <code>pending</code> (queued, not yet started), green <code>processing</code> (running now), green <code>completed</code> (finished) and red <code>failed</code> (stopped with an error).</li>'
                . '<li><b>Download icon</b> beside the Exception Records count: appears when rejected rows exist and an exceptions file was saved. Clicking it opens the file in a new tab.</li>'
                . '<li><b>Empty state</b>: <code>No records found.</code></li>'
                . '</ul>'
                . '<h4>Reading the exceptions file</h4>'
                . '<p>The file is a CSV with two columns: <b>Row Data</b> (the original row) and <b>Reason</b> (why it was rejected, for example <code>customer_id missing in data array</code>, <code>Missing/invalid value_date or maturity_date</code> or <code>Missing essential fields: customer_id and/or collateral_type</code>). Fix the rows in your source file and re-import the whole file; rows already accepted are simply updated.</p>'
                . '<h4>To check an import</h4>'
                . '<ol>'
                . '<li>Open <b>Imports</b>. Your file is the top row.</li>'
                . '<li>If the status is <code>pending</code> or <code>processing</code>, refresh the page after a moment.</li>'
                . '<li>When it reads <code>completed</code>, compare <b>Inserted</b> with the number of rows in your file.</li>'
                . '<li>If <b>Exception Records</b> is above zero, click the download icon and read the <b>Reason</b> column.</li>'
                . '</ol>'
                . '<h4>General import templates</h4>'
                . '<p>Administrators also have a <b>General Imports</b> page (address <code>/imports</code>) titled <b>Import Templates</b>, used for defining reusable column layouts for other tables. It lists <b>Template Name</b>, <b>Description</b>, <b>Source Table</b>, <b>Import Count</b>, <b>Status</b> (green <code>Active</code>, red <code>Inactive</code>, grey <code>Deleted</code>) and <b>Actions</b> with a <b>Sample</b> link (downloads an Excel sample) and an amber <b>Activate</b> or <b>Deactivate</b> link. <b>Create Template</b> opens a form with <b>Template Name</b>, <b>Description</b>, <b>Source Table</b> and a <b>Column Configurations</b> list (<b>Position</b>, <b>Description</b>, <b>Data Type</b>, <b>Min Value</b>, <b>Max Value</b>, <b>Is Reporting Period</b>, <b>Is Portfolio Group</b>, <b>Remove</b>) with <b>Add Column</b>, <b>Cancel</b> and <b>Create Template</b>. This is an advanced tool and is not needed for the standard loan book, client and collateral imports.</p>'
                . '<h4>What happens next</h4>'
                . '<p>A completed loan book import makes the month available on the Loan Book page and in the period workspace checklist; a completed collateral import makes the period available on the Collateral Register; a completed names file import updates the Clients list. Nothing further is needed on this page.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The status stays <code>pending</code> for a long time: the background queue worker is not running. Ask the system administrator to check it.</li>'
                . '<li>Status <code>failed</code> with no inserted rows: the file could not be read at all (wrong format, empty, or not comma-separated). Re-check the file and import again.</li>'
                . '<li>Inserted is far lower than expected and Exception Records is high: open the exceptions file; the Reason column will usually point to one column that is blank or badly formatted.</li>'
                . '<li>The same file appears twice: it was submitted twice. This is harmless because matching rows are updated rather than duplicated.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Customer & Loan Data and choose Imports.',
                'Find your file by name (newest first) or type part of the name in Search.',
                'Read the Status chip: pending, processing, completed or failed.',
                'Compare Inserted with the rows in your file.',
                'If Exception Records is above zero, click the download icon next to it and read the Reason column.',
                'Correct the source file and re-import it.',
            ],
            'images' => [
                'imports' => 'The Imports history with status chips, inserted and exception counts, start, completion and duration',
            ],
            'routes' => ['imports.index', 'imports.failed-download', 'custom_imports.index', 'custom_imports.create', 'custom_imports.store', 'custom_imports.sample'],
        ],

    ],
];
