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
            'body' => '<p>The collateral register lists the security items (property, vehicles, cash cover, guarantees and so on) held against customers, one row per item per month-end. It is imported from the core banking collateral file each month and is the source for collateral allocation, which lowers the loss given default (LGD) of covered loans. Credit administration staff use it to confirm the month\'s register loaded and to look up what a customer has pledged. To open it, find the <b>Data foundation</b> block in the sidebar and choose <b>Collateral Management</b>; the page opens on the <b>Collateral Register</b> tab (breadcrumbs: <b>Data foundation &gt; Collateral Management &gt; Collateral Register</b>). The other tabs are <b>Collateral Allocation</b> and <b>Collateral Types</b>. Any signed-in user may open it.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Collateral Register</b>, with the line <code>Every item of collateral imported, by month-end, with its nominal, market and execution values</code>.</li>'
                . '<li><b>Filter bar</b>: <b>From</b> and <b>To</b> (month pickers on the register month; fill only From to see one month), <b>Collateral type code</b> (the exact type code, for example <code>103</code>), <b>Customer ID</b> (matches the end of the number) and <b>Customer name</b> (any part of the name).</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b>, <b>Customer name</b> (bold), <b>Type</b> (the type code), <b>Period</b> (month and year, for example <code>Dec 2025</code>), <b>Nominal value</b>, <b>Market value</b>, <b>Execution value</b>. Amounts have thousands separators and two decimals. Newest month first, fifteen rows a page, with page links under the table.</li>'
                . '</ul>'
                . '<h4>Understanding the value columns</h4>'
                . '<ul>'
                . '<li><b>Nominal value</b>: the face or book value of the item.</li>'
                . '<li><b>Market value</b>: the latest open-market valuation.</li>'
                . '<li><b>Execution value</b>: the forced-sale value. Allocation uses this figure, before the type\'s value recognised and the time to realise it are applied.</li>'
                . '<li><b>Type</b>: must be a code on the <b>Collateral Types</b> tab, which supplies the value recognised and the realisation months.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Import register</b> (green, in the button row above the table): opens <b>Import Collateral Register</b> (next article).</li>'
                . '<li><b>Apply</b> (green): runs the filters. <b>Clear</b>: empties every filter and reloads the full list.</li>'
                . '<li>Nothing on this tab edits the register; it changes only by import. No permission beyond signing in is checked.</li>'
                . '</ul>'
                . '<h4>To find a customer\'s collateral</h4>'
                . '<ol>'
                . '<li>Type the customer number in <b>Customer ID</b>, or part of the name in <b>Customer name</b>.</li>'
                . '<li>Optionally set <b>From</b> to the month you are checking.</li>'
                . '<li>Press <b>Apply</b>.</li>'
                . '<li>Press <b>Clear</b> when finished.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Once the month\'s register is on file, run the allocation on the <b>Collateral Allocation</b> tab for the same month to spread each customer\'s execution value over their loans and update the loan LGD.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>The register is empty. Use Import register (top right) to load a month-end register.</code></li>'
                . '<li><code>No register row matches these filters.</code>: press <b>Clear</b>.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The list is empty after an import: check the Imports tab; if the job reads <code>failed</code> or has failed rows, download the failed-rows file.</li>'
                . '<li>Filtering by type returns nothing: the box needs the exact type code, not the type name.</li>'
                . '<li><b>Execution value</b> shows 0.00: the file column was blank or held only a dash; the item adds nothing at allocation until corrected and imported again.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block choose Collateral Management; the Collateral Register tab opens.',
                'Set From (and To for a range) to the month you want.',
                'Optionally enter a Collateral type code, Customer ID or Customer name.',
                'Press Apply and read the Nominal, Market and Execution value columns.',
                'Press Import register if the month has not been loaded yet.',
            ],
            'images' => [
                'collateral-register' => 'The Collateral Register tab with the From, To, type code, Customer ID and Customer name filters, Apply, Clear and Import register',
            ],
            'routes' => ['collateral.register.index'],
        ],

        'Importing collateral' => [
            'body' => '<p>The collateral import loads one month-end of the collateral register from a CSV file. Credit administration does it once a month, after the collateral types are set up and usually alongside the loan book for the same month. Open the <b>Collateral Register</b> tab and press <b>Import register</b>, or press <b>Import collateral</b> on the Imports tab. The page title is <b>Import Collateral Register</b>, with the line <code>Upload the collateral register for a month-end: one row per item of collateral with its values</code> (breadcrumbs: <b>Data foundation &gt; Collateral Management &gt; Collateral Register &gt; Import Collateral Register</b>). Any signed-in user may use it; no second approval is asked. The general rules of every import are in the Getting Started article <b>Importing files: sample file, column guide and Check file</b>; this article covers the register file.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>How to import</b> panel (title <b>Collateral register</b>): the summary, five numbered steps (the third lists the type codes held on Collateral Types), the note <code>A blank registration_date takes the first day of the chosen month. A blank status is loaded as ACTIVE.</code> and the column table.</li>'
                . '<li><b>Period</b>: a month picker with the hint <code>The month-end the register is for.</code> Required; every row is stamped with this month.</li>'
                . '<li><b>File layout</b>: <b>Standard CSV</b> (tooltip <code>The columns of the sample CSV.</code>, selected at first) or <b>Match columns</b> (<code>Match each column of your file to a register field.</code>).</li>'
                . '<li><b>File</b>: a dashed box reading <code>Choose a CSV file</code> (.csv or .txt).</li>'
                . '<li><b>Match the columns of the file</b> (Match columns only): the first three rows and one drop-down per column (<code>Ignore this column</code> or a register field). Headings such as <code>Customer ID</code>, <code>Collateral Type</code>, <code>Registration Date</code>, <code>Market Value</code> and <code>Forced Sale Value</code> are matched for you.</li>'
                . '<li><b>Recent imports</b>: the fifteen latest collateral imports with <b>File name</b>, <b>Status</b>, <b>Uploaded</b>, <b>Records inserted</b>, <b>Failed rows</b> and <b>Actions</b>, and the link <b>Open the full import history</b>.</li>'
                . '</ul>'
                . '<h4>The file</h4>'
                . '<p>Thirteen columns. Required: <code>customer_id</code> (E-Banker customer number of the owner of the loan the item secures) and <code>collateral_type</code> (one of the codes on Collateral Types; any other value is refused). Optional: <code>customer_name</code> (also read under <code>name</code>; blank loads as Unknown), <code>property_use</code> (for example Residential or Commercial), <code>description</code>, <code>location</code>, <code>registration_date</code>, <code>expiry_date</code>, <code>valuation_date</code> (dates best as YYYY-MM-DD; DD/MM/YYYY and MM/DD/YYYY are read too), <code>nominal_value</code>, <code>market_value</code>, <code>execution_value</code> (numbers of 0 or more; thousands commas allowed) and <code>status</code> (stored in capitals). An item is keyed by customer, type and registration date, so a re-load updates it.</p>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Hide</b> or <b>Show the 13 columns (2 required)</b>: folds or opens the column table.</li>'
                . '<li><b>Check file</b> (tick icon; once a file is chosen): checks the file, through your matching with Match columns, without saving.</li>'
                . '<li><b>Download sample file</b>: <code>collateral_sample.csv</code> with two illustrative items (customer numbers starting with 8, names starting with Sample, a real type code).</li>'
                . '<li><b>Download column guide</b>: <code>collateral_column_guide.xlsx</code>.</li>'
                . '<li><b>Back to the register</b> and <b>Cancel</b>: return to the register.</li>'
                . '<li><b>Start import</b> (green; <code>Uploading...</code>): checks the whole file and, if clean, imports it.</li>'
                . '<li><b>Download icon</b> on a Recent imports row (tooltip <code>Download the failed rows</code>).</li>'
                . '</ul>'
                . '<h4>What Check file and Start import verify</h4>'
                . '<p>Both required columns present (or matched), no blank customer number or type, every type code held on Collateral Types, dates readable, values numbers of at least 0, and no customer, type and registration date repeated. If anything fails nothing is imported, and the problems are listed by <b>Row</b>, <b>Column</b>, <b>Problem</b> and <b>How to fix it</b>.</p>'
                . '<h4>To import a month</h4>'
                . '<ol>'
                . '<li>Check that every type code in your file is on <b>Collateral Types</b>.</li>'
                . '<li>Set <b>Period</b> to the month-end of the register.</li>'
                . '<li>Leave <b>Standard CSV</b>, or choose <b>Match columns</b> if your headings differ.</li>'
                . '<li>Click the dashed box and choose the file; with Match columns, check each drop-down.</li>'
                . '<li>Press <b>Check file</b> and fix every row listed.</li>'
                . '<li>Press <b>Start import</b>. You return to the register with <code>Collateral register import has been queued for processing.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>The job appears on the Imports tab and under <b>Recent imports</b>. A row matching an existing item (customer, type, registration date) updates it; otherwise a new item is added, so importing a corrected file again is safe. Rows the importer could not use go to the failed-rows file. When the job reads <code>completed</code>, the month is ready for allocation.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>(file) was not imported: N problems found (first: ...). Nothing was saved. Fix the rows listed and choose the file again.</code></li>'
                . '<li><code>Import failed: ...</code>: the file could not be processed; the reason follows.</li>'
                . '<li><code>No file has been imported yet. Use the Upload tab to import the first one.</code> under Recent imports when none exists.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>A type code is refused: add the type on Collateral Types first, or correct the code in the file.</li>'
                . '<li><code>The period field is required.</code>: choose the <b>Period</b>.</li>'
                . '<li>Allocation later finds no collateral for a covered customer: the customer numbers differ between the register and the loan book, or the register month differs from the loan book month.</li>'
                . '</ul>',
            'steps' => [
                'Open the Collateral Register tab and press Import register.',
                'Press Download sample file and lay your data out under its headings.',
                'Set Period to the month-end the register is for and choose Standard CSV or Match columns.',
                'Click the dashed box and choose the CSV file.',
                'Press Check file and fix every row listed.',
                'Press Start import and confirm the job completes under Recent imports or on the Imports tab.',
            ],
            'images' => [
                'collateral-import' => 'Import Collateral Register with the How to import panel, the Period picker, the File layout choice, the file box and Recent imports',
                'import-modal' => 'The How to import panel open: the column table, Check file, Download sample file and Download column guide',
            ],
            'routes' => ['collateral.register.import', 'collateral.register.import.store', 'collateral.register.sample'],
        ],

        'Collateral types' => [
            'body' => '<p>Collateral types are the kinds of security the register may hold (bank guarantee, motor vehicle, cash cover, landed property and so on). Each type has a code, the share of the item\'s value recognised after the haircut, and the months it takes to realise it; these decide how much of a pledged item covers a loan at allocation. The types must exist, with the codes the collateral file uses, before the register is imported: the import refuses any other code. Risk administrators maintain this tab. To open it, choose <b>Collateral Management</b> in the <b>Data foundation</b> block and click the <b>Collateral Types</b> tab (breadcrumbs: <b>Data foundation &gt; Collateral Management &gt; Collateral Types</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Collateral Types</b>, with the line <code>The kinds of collateral the register may hold: the share of its value recognised after the haircut, and the months to realise it, used in the allocation</code>.</li>'
                . '<li><b>Table columns</b> in order: <b>Code</b>, <b>Name</b> (bold), <b>Description</b> (a dash when blank), <b>Value recognised</b> (as a percentage, for example <code>70%</code>; a gold <code>Not set</code> badge when it is 0, tooltip <code>No value is recognised for this type until it is set</code>), <b>Realisation (months)</b> (a dash when 0) and <b>Actions</b>. Fifteen types a page.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>New collateral type</b> (green): opens the <b>New collateral type</b> window.</li>'
                . '<li><b>Gold pencil</b> (tooltip <code>Edit</code>): opens the <b>Edit collateral type</b> window with the current values.</li>'
                . '<li><b>Red bin</b> (tooltip <code>Delete</code>): asks <b>Delete the (name) collateral type?</b> with <code>The type is removed from the list. This cannot be undone.</code> Press <b>Delete</b> or <b>Cancel</b>.</li>'
                . '<li>The tab and its buttons are open to every signed-in user; no permission or second approval is checked. Keep changes to the risk administrator by agreement.</li>'
                . '</ul>'
                . '<h4>The New and Edit collateral type window</h4>'
                . '<ul>'
                . '<li><b>Code</b>: required and unique; the code the collateral file uses.</li>'
                . '<li><b>Name</b>: required, up to 255 characters.</li>'
                . '<li><b>Value recognised (0 to 1)</b>: required, a number from 0 to 1, with the hint <code>The share of the value kept after the haircut: 0.70 for a 30 percent haircut, 1 for a guarantee counted in full.</code></li>'
                . '<li><b>Realisation (months)</b>: required, a whole number from 0 to 240.</li>'
                . '<li><b>Description</b>: optional.</li>'
                . '<li><b>Cancel</b> (or a click on the grey background) closes without saving; <b>Add type</b> (new) or <b>Save changes</b> (edit) saves, reading <code>Saving...</code> while it works.</li>'
                . '</ul>'
                . '<h4>To add a type</h4>'
                . '<ol>'
                . '<li>Press <b>New collateral type</b>.</li>'
                . '<li>Enter the <b>Code</b> used in the collateral file and the <b>Name</b>.</li>'
                . '<li>Enter <b>Value recognised (0 to 1)</b>, for example 0.70, and the <b>Realisation (months)</b>.</li>'
                . '<li>Press <b>Add type</b>. The list shows <code>Collateral type added successfully.</code></li>'
                . '</ol>'
                . '<h4>To edit or delete a type</h4>'
                . '<ol>'
                . '<li>Press the gold pencil, change the values and press <b>Save changes</b> (<code>Collateral type updated successfully.</code>).</li>'
                . '<li>Or press the red bin and confirm with <b>Delete</b> (<code>Collateral type deleted successfully.</code>).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Changes take effect the next time collateral is allocated; allocations already made keep their figures. Run the allocation again for the month if you change a value recognised or a realisation period and want the loan LGDs to follow.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No collateral types yet. Add one with New collateral type; the register import needs them.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The type code has already been taken.</code>: another type uses that code; edit it instead.</li>'
                . '<li><code>The standard haircut field must not be greater than 1.</code>: enter the share as a fraction, for example 0.70, not 70.</li>'
                . '<li><code>The realisation period field must not be greater than 240.</code>: enter months, at most 240.</li>'
                . '<li>A type shows <code>Not set</code>: its value recognised is 0, so it gives no cover until set.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block choose Collateral Management, then the Collateral Types tab.',
                'Press New collateral type.',
                'Enter the Code used in your collateral file and the Name.',
                'Enter Value recognised (0 to 1), for example 0.70, and Realisation (months).',
                'Press Add type. The new type appears with Collateral type added successfully.',
            ],
            'images' => [
                'collateral-types' => 'The Collateral Types tab with Code, Name, Description, Value recognised, Realisation (months) and the gold pencil and red bin',
            ],
            'routes' => ['collateral.types.index', 'collateral.types.store', 'collateral.types.update', 'collateral.types.delete'],
        ],

        'Allocating collateral to exposures' => [
            'body' => '<p>Collateral allocation spreads each customer\'s pledged collateral over that customer\'s loans for one loan book month, discounts it for the type\'s value recognised and the time to realise it, and writes the coverage into the loan book so that the LGD of a covered loan falls. It is run once a month, after the loan book and the collateral register for the month are on file, by the risk or credit administrator. Two screens are involved: the <b>Collateral Allocation</b> tab and the <b>Allocate Collateral</b> form. To open the tab, choose <b>Collateral Management</b> in the <b>Data foundation</b> block and click <b>Collateral Allocation</b> (breadcrumbs: <b>Data foundation &gt; Collateral Management &gt; Collateral Allocation</b>). Any signed-in user may open and run it; a locked month is refused.</p>'
                . '<h4>What you see on the Collateral Allocation tab</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Collateral Allocation</b>, with the line <code>The discounted collateral allocated to each customer\'s exposure, by reporting period</code>.</li>'
                . '<li><b>Four cards</b> (they follow the filters): <b>Allocations</b> (rows), <b>Exposure (MWK)</b>, <b>Discounted collateral (MWK)</b> and <b>Average coverage</b> (green at 80 percent or more, amber from 50 percent, red below; a dash when there are none).</li>'
                . '<li><b>Filter bar</b>: <b>Reporting period</b> (month), <b>Collateral type</b> (<code>All types</code> or a type code), <b>Customer ID</b> and <b>Customer name</b> (any part), with <b>Apply</b> and <b>Clear</b>.</li>'
                . '<li><b>Table columns</b> in order: <b>Customer ID</b>, <b>Customer name</b>, <b>Reporting period</b>, <b>Basis</b> (the basis used, in capitals, for example <code>PROPORTIONAL</code>), <b>Exposure (MWK)</b> (the loan\'s carrying amount), <b>Discounted collateral (MWK)</b> and <b>Coverage</b> (coloured as the card). One row per loan, newest month first, fifteen a page.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Download report</b>: opens <b>Download the allocation report</b>, <code>A CSV file of the allocations for one reporting period, contract by contract.</code>, with a <b>Reporting period</b> month (filled from the filter), <b>Cancel</b> and <b>Download</b> (<code>Preparing...</code> briefly). The file <code>collateral_allocation_report_(year)_(month).csv</code> has Customer ID, Customer Name, Contract ID, Total Customer Exposure, Allocated Collateral, Allocation Percentage, Discounted Collateral, Coverage Ratio, Allocation Basis and Allocation Notes.</li>'
                . '<li><b>Allocate collateral</b> (green): opens the <b>Allocate Collateral</b> form.</li>'
                . '<li><b>Apply</b> and <b>Clear</b>: run or empty the filters.</li>'
                . '</ul>'
                . '<h4>The Allocate Collateral form</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Allocate Collateral</b>, with <code>Spread each customer\'s discounted collateral over their loans for one loan book month</code>, and <b>Back to allocations</b>.</li>'
                . '<li><b>Allocation basis</b>: <code>Proportional to exposure</code> (each loan gets collateral in proportion to its share of the customer\'s exposure; the default), <code>Largest exposure first</code>, <code>Smallest exposure first</code>, <code>Equal shares</code> (what is left split evenly over the loans left).</li>'
                . '<li><b>Collateral register date</b>: <code>Choose the register date</code> or one of the registration dates on file. Leave it unchosen to use every register item of the customer; choose one to use only items with that date. If no register exists the amber note <code>No register has been imported yet. Import the collateral register first.</code> shows.</li>'
                . '<li><b>Loan book year</b> (2000 to this year) and <b>Loan book month</b>: the loan book month to allocate against; the form opens on the latest loan book month.</li>'
                . '<li><b>Clear</b> restores the defaults; <b>Allocate</b> (green; <code>Allocating...</code>) runs it.</li>'
                . '</ul>'
                . '<h4>How the cover is calculated</h4>'
                . '<p>For each customer with at least one loan above zero in the month and at least one register item, the platform adds up the items\' execution values, works out each loan\'s share under the basis (never more than is left), multiplies the share by the average value recognised of the customer\'s collateral types, and divides by (1 + the loan\'s interest rate) raised to the realisation period in years (taken from the type of the customer\'s first item). The result is the <b>Discounted collateral</b>; the <b>Coverage</b> is that divided by the loan\'s carrying amount, at most 100 percent. On the loan book row of that month only, the customer LGD becomes 1 minus the coverage (a fully covered loan has an LGD of zero), and the gross and discounted allocated values are stored. The ECL uses this LGD when it next runs.</p>'
                . '<h4>To allocate a month</h4>'
                . '<ol>'
                . '<li>Confirm the loan book and the collateral register for the month have both completed on the Imports tab.</li>'
                . '<li>On <b>Collateral Allocation</b>, press <b>Allocate collateral</b>.</li>'
                . '<li>Choose the <b>Allocation basis</b>, the <b>Loan book year</b> and <b>Loan book month</b>, and the <b>Collateral register date</b> if you want only one.</li>'
                . '<li>Press <b>Allocate</b>. You return to the tab with <code>Collateral auto-allocated per customer successfully.</code></li>'
                . '<li>Set <b>Reporting period</b> to the month and press <b>Apply</b> to review the coverage.</li>'
                . '<li>Press <b>Download report</b> and <b>Download</b> to keep a CSV for the audit file.</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Running the allocation again for the same month overwrites that month\'s figures, so you can re-run after correcting the register or a type. Run the ECL for the month afterwards so it uses the new LGDs.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No collateral has been allocated yet. Import the register, then use Allocate collateral (top right).</code></li>'
                . '<li><code>No allocation matches these filters.</code></li>'
                . '<li><code>Choose a reporting period.</code> in the report window.</li>'
                . '<li><code>No clients with loans and collaterals found.</code>: no loan above zero in the chosen month.</li>'
                . '<li><code>Reporting period 2025-12 is locked: the collateral allocation cannot be written. A locked period is never restated; unlock it with a reason on Imports &amp; Feeds, Periods Built (or eir:lock-period --unlock) if the restatement is deliberate.</code></li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The tab stays empty after a successful run: no customer had both loans in the month and register items. Check the customer numbers are the same in both files.</li>'
                . '<li>Coverage is zero although collateral exists: the type shows <code>Not set</code> on Collateral Types, or the type code is not held there.</li>'
                . '<li>A red message under <b>Loan book year</b>: the year is outside 2000 to this year.</li>'
                . '</ul>',
            'steps' => [
                'Confirm the month\'s loan book and collateral register both read completed on the Imports tab.',
                'In the Data foundation block choose Collateral Management, then the Collateral Allocation tab.',
                'Press Allocate collateral.',
                'Choose the Allocation basis, the Loan book year and month, and optionally a Collateral register date.',
                'Press Allocate and wait for Collateral auto-allocated per customer successfully.',
                'Filter by the Reporting period and press Apply to review Discounted collateral and Coverage.',
                'Press Download report, choose the month and press Download to keep a CSV copy.',
            ],
            'images' => [
                'collateral' => 'The Collateral Allocation tab with the four cards, the filters, Download report, Allocate collateral and the coverage column',
            ],
            'routes' => ['collateral.allocations.index', 'collateral.allocate', 'collateral.allocate.auto', 'collateral.allocations.download-report'],
        ],

    ],
];
