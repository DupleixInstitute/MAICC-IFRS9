<?php

/*
|--------------------------------------------------------------------------
| User Manual chapter: Collateral Management
|--------------------------------------------------------------------------
| Loaded by HelpContentSeeder. Chapter title => [article title => spec].
| Spec keys: body (HTML), steps (array), images (key => caption), routes.
*/

return [
    'Collateral Management' => [

        'Collateral register' => [
            'body' => '<p>The collateral register is the list of security items (property, vehicles, cash cover, guarantees and so on) held against customers, one row per item per reporting period. It is loaded from the core banking collateral file each month and is the source for collateral allocation, which in turn lowers the loss given default (LGD) of covered loans. Credit administration staff use this page to confirm the monthly register loaded correctly and to look up what a customer has pledged. To open it, open the sidebar, expand <b>Collateral Management</b> and choose <b>Collateral Register</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Collateral / Register</b> (the green <b>Collateral</b> link goes to the allocations page), with the subtitle <code>List of Collateral Register by Date</code>.</li>'
                . '<li><b>Import Register</b> button (dark, upload icon, top right): opens the collateral import page.</li>'
                . '<li><b>Filter bar</b>: <b>From Date</b> and <b>To Date</b> (month pickers on the register period; choose only From Date to see a single month), <b>Collateral Type</b> (text box, placeholder <code>e.g. Code: 103</code>; enter the type code exactly), <b>Customer ID</b> (placeholder <code>ID</code>) and <b>Customer Name</b> (placeholder <code>Name</code>, partial match).</li>'
                . '<li><b>Apply Filters</b> (green) runs the search; <b>Reset</b> (dark) clears every box and reloads the full list.</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b>, <b>Customer Name</b>, <b>Type Code</b>, <b>Reporting Period</b> (shown as month and year), <b>Nominal Value</b>, <b>Market Value</b>, <b>Execution Value</b>. Amounts are shown with thousands separators and two decimals; the newest period is listed first, ten rows per page.</li>'
                . '<li><b>Pagination</b>: <b>Previous</b>, page numbers and <b>Next</b> under the table.</li>'
                . '</ul>'
                . '<h4>Understanding the value columns</h4>'
                . '<ul>'
                . '<li><b>Nominal Value</b>: the face or book value of the security as recorded by the bank.</li>'
                . '<li><b>Market Value</b>: the most recent valuation at open-market prices.</li>'
                . '<li><b>Execution Value</b>: the forced-sale or realisable value. This is the figure the platform uses when allocating collateral to loans, before the type haircut and time discount are applied.</li>'
                . '<li><b>Type Code</b>: must match a code on the <b>Collateral Types</b> page; the type supplies the haircut and realisation period used at allocation.</li>'
                . '</ul>'
                . '<h4>To find a customer\'s collateral</h4>'
                . '<ol>'
                . '<li>Type the customer number in <b>Customer ID</b>, or part of the name in <b>Customer Name</b>.</li>'
                . '<li>Optionally set <b>From Date</b> to the reporting month you are checking.</li>'
                . '<li>Click <b>Apply Filters</b>.</li>'
                . '<li>Click <b>Reset</b> when finished to clear the filters.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Nothing is edited on this page; the register is maintained by import. Once the month\'s register is on file, run <b>Collateral Allocation</b> for the same reporting month to spread each customer\'s execution value across their loans and update the loan LGD. The <b>Reporting Period</b> here must match the loan book month you allocate against.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The list is empty after an import: check the <b>Imports</b> page; if the row shows <code>failed</code> or a high <b>Exception Records</b> count, download the exceptions file. If the import completed, clear the filters with <b>Reset</b>.</li>'
                . '<li>Filtering by <b>Collateral Type</b> returns nothing: the box needs the exact type code (for example <code>103</code>), not the type name.</li>'
                . '<li>Execution Value shows 0.00: the file column was blank or contained only a dash. The item will contribute nothing at allocation until it is corrected and re-imported.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Collateral Management and choose Collateral Register.',
                'Set From Date (and To Date for a range) to the reporting month you want to see.',
                'Optionally enter a Collateral Type code, Customer ID or Customer Name.',
                'Click Apply Filters and review the Nominal, Market and Execution Value columns.',
                'Click Import Register if the month has not been loaded yet.',
            ],
            'images' => [
                'collateral-register' => 'The Collateral Register with the From Date, To Date, Collateral Type, Customer ID and Customer Name filters and the Import Register button',
            ],
            'routes' => ['collateral.register.index'],
        ],

        'Importing collateral' => [
            'body' => '<p>The collateral import loads one reporting month of the collateral register from a CSV file. It is done once a month by credit administration, after the collateral types have been set up and usually alongside the loan book import for the same month. To open the page, open the sidebar, expand <b>Collateral Management</b>, choose <b>Collateral Register</b> and click <b>Import Register</b>; the header reads <b>Collateral / Import</b> with the subtitle <code>Select the file with collaterals (registry of all the collateral)</code>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Download Sample File</b> button (dark, top right): downloads <code>collateral_registry_sample.csv</code>, a header-only file with the expected column names.</li>'
                . '<li><b>Select Import Type</b>, two cards: <b>Legacy Format</b> (<code>Use traditional format with fields in sample file</code>, selected by default) and <b>Custom Mapping</b> (<code>Map your CSV columns to any database fields</code>).</li>'
                . '<li><b>Period</b>: a month picker with the hint <code>Select the month and year for the collateral register</code>. Required; every row in the file is stamped with this period.</li>'
                . '<li><b>Upload File</b>: a dashed box reading <code>Select a CSV file</code>; accepts .csv and .txt. The file name replaces the text once chosen.</li>'
                . '<li><b>Map CSV Columns</b> (Custom Mapping only, after a file is chosen): <b>File Preview (first 3 rows)</b> and one drop-down per column (<code>-- Ignore this column --</code> or a register field). Headings such as <code>Customer ID</code>, <code>Collateral Type</code>, <code>Registration Date</code>, <code>Market Value</code> and <code>Forced Sale Value</code> are matched automatically and marked <code>Mapped</code>; others show <code>Not mapped</code>.</li>'
                . '<li><b>Download Legacy Template</b> button: a sample file with three dummy rows in the legacy layout.</li>'
                . '<li><b>Cancel</b> returns to the allocations page; <b>Start Import</b> submits the file.</li>'
                . '<li><b>Processing overlay</b>: while uploading you see <code>Uploading file...</code> with a progress bar, then <code>Processing file...</code> and the note <code>Please wait while we process your file. This may take a few minutes for large files.</code></li>'
                . '</ul>'
                . '<h4>File format</h4>'
                . '<p>Columns, with the legacy template as the reference: <code>customer_id</code> (required; the customer number, for example <code>CUST001</code>), <code>customer_name</code>, <code>collateral_type</code> (required; the type code from the Collateral Types page, for example <code>103</code>), <code>property_use</code> (for example <code>Residential</code>), <code>description</code>, <code>location</code>, <code>registration_date</code>, <code>expiry_date</code>, <code>valuation_date</code>, <code>nominal_value</code>, <code>market_value</code>, <code>execution_value</code> and <code>status</code> (defaults to <code>ACTIVE</code>). Dates may be <code>dd/mm/yyyy</code>, <code>mm/dd/yyyy</code> or <code>yyyy-mm-dd</code>; amounts may contain commas and a lone dash is read as zero. A blank registration date is set to the first day of the chosen <b>Period</b>. Example row: <code>CUST001,John Doe,103,Residential,Residential House,123 Main St,01/01/2023,31/12/2033,01/01/2023,500000.00,550000.00,450000.00,ACTIVE</code>.</p>'
                . '<h4>What happens next</h4>'
                . '<p>You return to the register with <code>Collateral register import has been queued for processing.</code> The file is processed in chunks in the background and appears on the <b>Imports</b> page under its file name. A row with the same customer, collateral type, period and registration date as an existing record updates that record; otherwise a new record is inserted, so re-importing a corrected file is safe. Rejected rows are written to an exceptions file, downloadable from the Imports page. When the import shows <code>completed</code>, the month is ready for <b>Collateral Allocation</b>.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Missing essential fields: customer_id and/or collateral_type</code> in the exceptions file: the row has a blank customer number or type code. In Custom Mapping make sure a column is mapped to each of <code>customer_id</code> and <code>collateral_type</code>.</li>'
                . '<li><code>Import failed: ...</code> shown at the top of the page: the file could not be accepted (wrong file type, or <b>Period</b> missing). The period must be a month and year.</li>'
                . '<li>Allocation later reports no collateral for customers you know are covered: the type code in the file does not exist on <b>Collateral Types</b>, or the <b>Period</b> chosen here differs from the loan book month.</li>'
                . '<li>The import completes but values are zero: the amount columns were not mapped, or contain text rather than numbers.</li>'
                . '</ul>',
            'steps' => [
                'Open Collateral Management, Collateral Register and click Import Register.',
                'Click Download Sample File or Download Legacy Template and lay your data out in those columns.',
                'Leave Legacy Format selected, or choose Custom Mapping if your headings differ.',
                'Set Period to the reporting month the register represents.',
                'Click the dashed Upload File box and choose the CSV file; for Custom Mapping check the column drop-downs.',
                'Click Start Import and wait for the confirmation message.',
                'Open Customer & Loan Data, Imports to confirm the status is completed and check Exception Records.',
            ],
            'images' => [
                'collateral-import' => 'Collateral / Import with the Legacy Format and Custom Mapping cards, the Period picker, the upload box and Download Legacy Template',
            ],
            'routes' => ['collateral.register.import', 'collateral.register.import.store', 'collateral.register.sample'],
        ],

        'Collateral types' => [
            'body' => '<p>Collateral types are the categories of security the bank accepts (bank guarantee, bill of sale over motor vehicles, cash cover, charge over landed property and so on). Each type carries a code, a haircut and a realisation period, and these two numbers decide how much of a pledged item counts towards covering a loan when collateral is allocated. The types must exist, with the same codes used in the collateral file, before the register is imported. Risk administrators maintain this page. To open it, open the sidebar, expand <b>Collateral Management</b> and choose <b>Collateral Types</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Collateral Types, with the subtitle <code>Enter the collateral types that are to be used in allocation</code>.</li>'
                . '<li><b>+ Add Type</b> button (green, top right): opens the add window.</li>'
                . '<li><b>Table columns</b> in order: <b>ID</b> (internal number), <b>Code</b>, <b>Name</b>, <b>Haircut (%)</b>, <b>Realisation Period (Months)</b>, <b>Actions</b>. Ten types per page.</li>'
                . '<li><b>Row actions</b>: a green pencil-and-square icon (labelled <code>Edit Type</code>) opens the edit window; a red bin deletes the type after a confirmation.</li>'
                . '<li><b>Empty state</b>: <code>No collateral types available.</code></li>'
                . '<li><b>Add / Edit window</b>: titled <b>Add Collateral Type</b> or <b>Edit Collateral Type</b>, with the fields below and a full-width green button reading <b>Submit</b> (add) or <b>Update</b> (edit). Click the grey background to close without saving.</li>'
                . '<li><b>Pagination</b>: <b>Previous</b>, page numbers and <b>Next</b>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Type Code</b>: the code that appears in the collateral file, for example <code>109</code>. Required and must be unique; a duplicate shows <code>The type code has already been taken.</code></li>'
                . '<li><b>Type Name</b>: the description, for example <code>Charge over landed property</code>. Required, up to 255 characters.</li>'
                . '<li><b>Standard Haircut (%)</b>: the forced-sale discount factor. Required, a number between 0 and 100. At allocation the platform multiplies the collateral allocated to a loan by this number, so the existing entries are stored as fractions: <code>1.00</code> means the full value counts (cash), <code>0.70</code> means 70 percent counts (landed property), <code>0.50</code> means half counts (motor vehicles) and <code>0.00</code> means the type gives no cover. Enter new types on the same basis so that they are consistent with the rest of the table.</li>'
                . '<li><b>Realisation Period (Months)</b>: how many months it typically takes to sell the security. Required, a whole number of at least 1. The allocated value is discounted over this period at the loan\'s interest rate, so a longer period gives less cover.</li>'
                . '<li><b>Description</b>: optional notes.</li>'
                . '</ul>'
                . '<h4>To edit a type</h4>'
                . '<ol>'
                . '<li>Click the green edit icon on the row. The window opens as <b>Edit Collateral Type</b> with the current values.</li>'
                . '<li>Change the haircut, realisation period or name as needed.</li>'
                . '<li>Click <b>Update</b>. The list refreshes with <code>Collateral type updated successfully.</code></li>'
                . '</ol>'
                . '<h4>To delete a type</h4>'
                . '<ol>'
                . '<li>Click the red bin on the row.</li>'
                . '<li>Answer <b>OK</b> to <code>Are you sure you want to delete this collateral type?</code></li>'
                . '<li>The list shows <code>Collateral type deleted successfully.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Changes take effect the next time collateral is allocated; allocations already made keep the values they were calculated with. Re-run <b>Collateral Allocation</b> for the period if you change a haircut or realisation period and want the loan LGDs to reflect it. Register rows whose type code has no matching type still import, but contribute a haircut of zero at allocation.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The type code has already been taken.</code>: another type already uses that code; edit the existing one instead.</li>'
                . '<li><code>The standard haircut field must be between 0 and 100.</code> or <code>The realisation period field must be at least 1.</code>: correct the number.</li>'
                . '<li>Allocated cover is far lower than the execution value: check the haircut is a fraction such as <code>0.70</code>, not <code>70</code>, and that the realisation period is realistic.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Collateral Management and choose Collateral Types.',
                'Click + Add Type at the top right.',
                'Enter the Type Code used in your collateral file and the Type Name.',
                'Enter the Standard Haircut (%) on the same basis as the existing rows (for example 0.70 for 70 percent of value) and the Realisation Period (Months).',
                'Click Submit. The new type appears in the list with Collateral type added successfully.',
            ],
            'images' => [
                'collateral-types' => 'The Collateral Types list with Code, Name, Haircut (%), Realisation Period (Months) and the green edit and red delete icons',
            ],
            'routes' => ['collateral.types.index', 'collateral.types.store', 'collateral.types.update', 'collateral.types.delete'],
        ],

        'Allocating collateral to exposures' => [
            'body' => '<p>Collateral allocation spreads each customer\'s pledged security across that customer\'s loans for a reporting month, discounts it for the type haircut and the time to realise it, and writes the resulting coverage into the loan book so that the loss given default (LGD) of a covered loan is reduced. It is run once per month after both the loan book and the collateral register for that month are on file, by the risk or credit administrator. Two pages are involved: the <b>Collateral Allocations</b> list and the <b>Auto Allocate Collateral</b> form. To open the list, open the sidebar, expand <b>Collateral Management</b> and choose <b>Collateral Allocation</b>.</p>'
                . '<h4>What you see on the allocations page</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Collateral Allocations, subtitle <code>List of allocations by Customer ID</code>.</li>'
                . '<li><b>Header buttons</b>: <b>View Register</b> (green, eye icon) opens the collateral register; <b>Download Report</b> (grey, file icon) opens the report window; <b>+ Allocate</b> (green) opens the auto allocation form.</li>'
                . '<li><b>Summary</b> heading with a <b>Hide Summary</b> / <b>Show Summary</b> toggle, and four tiles: <b>Total Allocations</b>, <b>Total Exposure (MKW)</b>, <b>Total Discounted Allocated (MKW)</b> and <b>Coverage Ratio (%)</b> (average coverage, green at 80 percent or more, amber from 50 percent, red below; a small arrow beside it shows the change).</li>'
                . '<li><b>Filter bar</b>: <b>Reporting Period</b> (month picker), <b>Collateral Type</b> (<code>All Types</code> or a type code), <b>Customer ID</b> and <b>Customer Name</b> (partial match), with <b>Apply</b> (green) and <b>Reset</b> (dark).</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b>, <b>Customer Name</b>, <b>Reporting Period</b>, <b>Basis</b> (the allocation method, for example <code>PROPORTIONAL</code>), <b>Exposure (MKW)</b> (the loan carrying amount), <b>Discounted Allocated (MKW)</b>, <b>Coverage Ratio (%)</b> (coloured green, amber or red as above). One row per loan contract, newest period first, ten per page.</li>'
                . '<li><b>Download Collateral Allocation Report</b> window: a <b>Reporting Period</b> month picker with <b>Cancel</b> and <b>Download</b> (reads <code>Preparing…</code> briefly). The CSV columns are Customer ID, Customer Name, Contract ID, Total Customer Exposure, Allocated Collateral, Allocation Percentage, Discounted Collateral, Coverage Ratio, Allocation Basis and Allocation Notes.</li>'
                . '</ul>'
                . '<h4>What you see on the Auto Allocate Collateral page</h4>'
                . '<ul>'
                . '<li><b>Header</b>: <b>Collateral / Auto Allocate Collateral</b>, subtitle <code>Select Allocation Basis, Reporting Period, and Collateral Reporting Period</code>.</li>'
                . '<li><b>Allocation Basis</b>: <code>Proportional</code> (each loan gets collateral in proportion to its share of the customer\'s total exposure; the default), <code>Descending Exposure</code> (largest loan first until the collateral runs out), <code>Ascending Exposure</code> (smallest loan first) and <code>Equal Distribution</code> (the remaining collateral split evenly across the remaining loans).</li>'
                . '<li><b>Loan Book Reporting Year</b> (number, 2000 to the current year) and <b>Loan Book Reporting Month</b> (<code>Select Month</code>, January to December): the loan book month to allocate against.</li>'
                . '<li><b>Collateral Reporting Date</b> (<code>Select Reporting Date</code>): the register dates on file. The allocation reads the register rows whose period equals the loan book month chosen above, so import the register for the same month.</li>'
                . '<li><b>Reset</b> (grey) restores the defaults; <b>Allocate</b> (green, reads <code>Processing...</code> while running) starts the allocation.</li>'
                . '</ul>'
                . '<h4>How the cover is calculated</h4>'
                . '<p>For every customer who has at least one loan with a carrying amount above zero in the chosen month and at least one register item for that period, the platform adds up the execution values of the items, works out each loan\'s share under the chosen basis, multiplies the share by the average haircut of the customer\'s collateral types, then divides by (1 + the loan interest rate) raised to the realisation period in years. The result is the <b>Discounted Allocated</b> value; the <b>Coverage Ratio</b> is that value divided by the loan carrying amount, capped at 100 percent. On the loan book row the platform then sets the customer LGD to 1 minus the coverage (so a fully covered loan has an LGD of zero) and stores the gross and discounted allocated values. The ECL calculation picks up this LGD when it runs.</p>'
                . '<h4>What happens next</h4>'
                . '<p>The browser shows <code>Collateral allocated successfully.</code> and you return to the allocations list with <code>Collateral auto-allocated per customer successfully.</code> Running the allocation again for the same month overwrites the earlier figures, so you can safely re-run after correcting the register or a collateral type. Use <b>Download Report</b> to keep a CSV of the month\'s allocations for the audit file.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>No clients with loans and collaterals found.</code>: there is no loan book for the chosen year and month, or all carrying amounts are zero. Check the Loan Book page first.</li>'
                . '<li><code>There was an error allocating collateral.</code> with a red message under a field: the year is outside 2000 to the current year, or no month was chosen.</li>'
                . '<li>The list stays empty after a successful run: no customer had both loans and register items for the same month. Confirm the register <b>Period</b> matches the loan book month and that customer IDs are identical in both files.</li>'
                . '<li>Coverage is zero although collateral exists: the collateral type has a haircut of 0, or the type code in the register does not exist on the Collateral Types page.</li>'
                . '<li>The Coverage Ratio tile shows <code>NaN%</code> beside the arrow: there are no allocations to compare yet; it clears once allocations exist.</li>'
                . '</ul>',
            'steps' => [
                'Confirm the loan book and the collateral register for the month are both imported and completed on the Imports page.',
                'Open the sidebar, expand Collateral Management and choose Collateral Allocation.',
                'Click + Allocate at the top right.',
                'Choose the Allocation Basis (Proportional is the default), the Loan Book Reporting Year and Month, and the Collateral Reporting Date.',
                'Click Allocate and wait for Collateral allocated successfully.',
                'Back on Collateral Allocations, set the Reporting Period filter and click Apply to review each contract\'s Discounted Allocated value and Coverage Ratio.',
                'Click Download Report, choose the month and click Download to keep a CSV copy.',
            ],
            'images' => [
                'collateral' => 'Collateral Allocations with the summary tiles, filters, the View Register, Download Report and + Allocate buttons and the allocation columns',
            ],
            'routes' => ['collateral.allocations.index', 'collateral.allocate', 'collateral.allocate.auto', 'collateral.allocations.download-report'],
        ],

    ],
];
