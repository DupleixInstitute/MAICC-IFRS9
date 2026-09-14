<?php

/*
|--------------------------------------------------------------------------
| User Manual chapter: Portfolio Setup
|--------------------------------------------------------------------------
| Loaded by HelpContentSeeder. Chapter title => [article title => spec].
| Spec keys: body (HTML), steps (array), images (key => caption), routes.
*/

return [
    'Portfolio Setup' => [

        'Loan portfolios' => [
            'body' => '<p>A loan portfolio is the top-level basket that every loan in the platform belongs to (for example Agricultural Loans, Industrial Loans or simply Loans). Every loan book you import must be assigned to a portfolio, so at least one portfolio has to exist before any loan data can be loaded. Portfolios are set up by the risk or finance administrator and are rarely changed afterwards. To open the page, open the sidebar, expand <b>Portfolio Setup</b> and choose <b>Loan Portfolios</b>.</p>'
                . '<p>Portfolios drive the rest of the system. The <b>Portfolio Group</b> box on the loan book import page lists them, the <b>Select Portfolio (Optional)</b> box on the loan book export and disbursement report lists them, the dashboard portfolio filter uses them, and portfolio-level ECL and coverage reports are grouped by them. If a portfolio is set to Inactive it is kept for history but should not be used for new imports.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Loan Portfolios.</li>'
                . '<li><b>Search box</b> (placeholder <code>Search...</code>): type any part of a portfolio name; the list refreshes as you type.</li>'
                . '<li><b>Status drop-down</b>: <code>All Status</code>, <code>Active</code> or <code>Inactive</code>. Narrows the list to portfolios in that state.</li>'
                . '<li><b>Create Portfolio</b> button (green, top right): opens the new portfolio form.</li>'
                . '<li><b>Table columns</b> in order: <b>Name</b>, <b>Description</b>, <b>Status</b>, <b>Created By</b>, <b>Created At</b>, <b>Actions</b>. The list shows the newest portfolio first, twenty per page.</li>'
                . '<li><b>Status badge</b>: a green <code>Active</code> chip means the portfolio can be used; a red <code>Inactive</code> chip means it is retired.</li>'
                . '<li><b>Row actions</b>: the gold pencil (tooltip <code>Edit</code>) opens the edit form; the red bin (tooltip <code>Delete</code>) removes the portfolio after a confirmation.</li>'
                . '<li><b>Empty state</b>: <code>No portfolios found.</code> is shown when nothing matches the search or status filter.</li>'
                . '<li><b>Pagination</b>: page links appear under the table when there are more than twenty portfolios.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Name</b>: required, up to 255 characters. Use the name your reports should show, for example <code>Agricultural Loans</code>. If it is left blank the form shows <code>The name field is required.</code></li>'
                . '<li><b>Description</b>: optional free text describing what belongs in the portfolio, for example <code>All MAJIC agricultural facilities</code>.</li>'
                . '<li><b>Active</b> tick box: ticked by default. Untick it to retire a portfolio without deleting it.</li>'
                . '<li><b>Save Portfolio</b> button: saves the record. While saving the label changes to <code>Saving...</code>. You are returned to the list with the message <code>Portfolio created successfully.</code> or <code>Portfolio updated successfully.</code></li>'
                . '</ul>'
                . '<h4>To edit a portfolio</h4>'
                . '<ol>'
                . '<li>Find the portfolio in the list (use the search box if needed).</li>'
                . '<li>Click the gold pencil in the <b>Actions</b> column. The page header changes to <b>Portfolios / (name)</b>.</li>'
                . '<li>Change the <b>Name</b>, <b>Description</b> or <b>Active</b> tick box.</li>'
                . '<li>Click <b>Save Portfolio</b>.</li>'
                . '</ol>'
                . '<h4>To delete a portfolio</h4>'
                . '<ol>'
                . '<li>Click the red bin in the <b>Actions</b> column.</li>'
                . '<li>Answer <b>OK</b> to the browser question <code>Are you sure you want to delete this portfolio?</code></li>'
                . '<li>The list refreshes with the message <code>Portfolio deleted successfully.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>New portfolios appear immediately in the <b>Portfolio Group</b> list on the loan book import page and in the portfolio filters on the dashboard and reports. Loans imported under a portfolio carry its identifier, so changing a portfolio name later renames it everywhere without touching the loans.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>Cannot delete portfolio with associated loan books.</code> The portfolio already has loans imported under it. Set it to Inactive instead of deleting it.</li>'
                . '<li><code>The name field is required.</code> Enter a name before saving.</li>'
                . '<li>The portfolio you expect is missing from the import page: check that it is not filtered out as Inactive, and if it is, edit it and tick <b>Active</b>.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Portfolio Setup and choose Loan Portfolios.',
                'Click the green Create Portfolio button at the top right.',
                'Type the portfolio Name (required) and an optional Description.',
                'Leave the Active box ticked so the portfolio can be used for imports.',
                'Click Save Portfolio. You return to the list and see Portfolio created successfully.',
                'Repeat for each portfolio you need before importing loan books.',
            ],
            'images' => [
                'portfolios' => 'The Loan Portfolios list with search, status filter, Active badge and the gold pencil and red bin row actions',
                'portfolios-create' => 'The Create Portfolio form: Name, Description, Active tick box and Save Portfolio',
            ],
            'routes' => ['portfolios.index', 'portfolios.create', 'portfolios.store', 'portfolios.edit', 'portfolios.update', 'portfolios.destroy'],
        ],

        'Sector types' => [
            'body' => '<p>Sector types (the page itself is titled <b>Industry Types</b>) are the economic-sector classification used for sector concentration and sector ECL reporting, for example Agriculture, forestry and fishing; Mining; Manufacturing. Each sector has a short numeric <b>Code</b> and a <b>Name</b>. The loan book file carries a sector code or sector name for every contract (the importer recognises headings such as <code>Industry Code</code>, <code>Sector Code</code>, <code>Sector</code> or <code>Segmentation</code>), and the client profile shows the sector under <b>Industrial Sector</b>. The codes in your loan book must match the codes on this page, so set the sectors up before the first loan book is loaded. To open the page, open the sidebar, expand <b>Portfolio Setup</b> and choose <b>Sector Types</b>.</p>'
                . '<p>Only users whose role includes the industry types permissions see the create, edit and delete controls. If you can see the list but not the buttons, ask your administrator to extend your role.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Industry Types.</li>'
                . '<li><b>Filter</b> button with a small arrow: opens an (empty) filter panel; there are no extra filters on this page.</li>'
                . '<li><b>Search box</b> (placeholder <code>Search…</code>): type part of a code or name; the list refreshes half a second after you stop typing.</li>'
                . '<li><b>Reset</b> link: clears the search box.</li>'
                . '<li><b>Create Category</b> button (green, top right; on a narrow screen it reads just <b>Create</b>): opens the new sector form.</li>'
                . '<li><b>Table columns</b> in order: <b>Code</b>, <b>Name</b>, <b>Actions</b>.</li>'
                . '<li><b>Row actions</b>: gold pencil (tooltip <code>Edit</code>) and red bin (tooltip <code>Delete</code>). Each is shown only if your role allows it.</li>'
                . '<li><b>Empty state</b>: <code>No types found.</code></li>'
                . '<li><b>Delete confirmation window</b>: titled <b>Delete Record</b> with the text <code>Are you sure you want to delete record?</code> and the buttons <b>Nevermind</b> (cancel) and <b>Delete Record</b> (confirm).</li>'
                . '<li><b>Pagination</b>: <b>Previous</b>, page numbers and <b>Next</b> under the table.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Code</b>: the short sector code that appears in your loan book file, for example <code>1</code> for Agriculture, forestry and fishing. Required by the form. Keep it unique.</li>'
                . '<li><b>Name</b>: the sector description shown on reports, for example <code>Wholesale and Retail, Accommodation and Food Services</code>. Required; if blank the message <code>The name field is required.</code> appears.</li>'
                . '<li><b>Description</b>: optional notes (shown on the create form only).</li>'
                . '<li><b>Save</b> button: saves and returns to the list with <code>Industry Type created successfully.</code> or <code>Industry Type updated successfully.</code></li>'
                . '</ul>'
                . '<h4>To edit a sector</h4>'
                . '<ol>'
                . '<li>Click the gold pencil on the row. The header reads <b>Industry Types / Edit</b>.</li>'
                . '<li>Change the <b>Code</b> or the <b>Name</b> (on the edit form the name is a multi-line box).</li>'
                . '<li>Click <b>Save</b>.</li>'
                . '</ol>'
                . '<h4>To delete a sector</h4>'
                . '<ol>'
                . '<li>Click the red bin on the row.</li>'
                . '<li>In the <b>Delete Record</b> window click <b>Delete Record</b>, or <b>Nevermind</b> to keep it.</li>'
                . '<li>The list shows <code>Industry Type deleted successfully.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Every create, update and delete is written to the audit trail. Loan book rows imported with a sector code are grouped under that sector in the sector concentration and sector ECL reports, and a client whose sector code matches shows the sector name on the <b>Basic Profile</b> page. Deleting a sector does not change loans already imported; they keep the code but will show no name until a sector with that code exists again.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>Sector reports show blank or unknown sectors: the codes in the loan book file do not match the <b>Code</b> column here. Add the missing codes or correct the file and re-import.</li>'
                . '<li>You cannot see <b>Create Category</b> or the row icons: your role lacks the create, update or destroy permission for industry types.</li>'
                . '<li>Two sectors with the same code: the platform does not stop you, but reports will merge them. Keep codes unique.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Portfolio Setup and choose Sector Types.',
                'Click Create Category at the top right.',
                'Enter the sector Code exactly as it appears in your loan book file.',
                'Enter the sector Name and, if helpful, a Description.',
                'Click Save. You return to the list and see Industry Type created successfully.',
                'Repeat until every sector code used in the loan book exists.',
            ],
            'images' => [
                'sector-types' => 'The Industry Types list showing Code, Name and the edit and delete row actions',
                'sector-types-create' => 'The Industry Types / Create form with Code, Name and Description',
            ],
            'routes' => ['industry_types.index', 'industry_types.create', 'industry_types.store', 'industry_types.show', 'industry_types.edit', 'industry_types.update', 'industry_types.destroy'],
        ],

        'Product groups' => [
            'body' => '<p>Product groups describe the lending product a contract belongs to (for example MAJIC Agricultural Loans or MAJIC Industrial Loans). They sit one level below portfolios: a portfolio is the reporting basket you choose at import time, while the product group is read from the loan data itself. When an E-Banker loan book is imported, each <code>Loan Type :</code> heading row in the file sets the product group and product code for the contracts beneath it, and the legacy and custom formats read a <code>type</code> or <code>loan_type</code> column. The product-group ECL report then breaks expected credit loss down by these names. Set the groups up here so the names on reports match the names in your files. To open the page, open the sidebar, expand <b>Portfolio Setup</b> and choose <b>Product Groups</b>.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: Product Groups.</li>'
                . '<li><b>Filter</b> button: opens an empty filter panel; there are no extra filters on this page.</li>'
                . '<li><b>Search box</b> (placeholder <code>Search…</code>): type part of a group name; the list refreshes shortly after you stop typing.</li>'
                . '<li><b>Reset</b> link: clears the search.</li>'
                . '<li><b>Create Product Group</b> button (green, top right; <b>Create</b> on narrow screens): opens the new group form.</li>'
                . '<li><b>Table columns</b> in order: <b>Name</b>, <b>Description</b>, <b>Actions</b>.</li>'
                . '<li><b>Row actions</b>: gold pencil (tooltip <code>Edit</code>) and red bin (tooltip <code>Delete</code>).</li>'
                . '<li><b>Empty state</b>: <code>No groups found.</code></li>'
                . '<li><b>Delete confirmation window</b>: <b>Delete Record</b> with <code>Are you sure you want to delete record?</code>, buttons <b>Nevermind</b> and <b>Delete Record</b>.</li>'
                . '<li><b>Pagination</b>: <b>Previous</b>, page numbers and <b>Next</b>.</li>'
                . '</ul>'
                . '<h4>Field by field</h4>'
                . '<ul>'
                . '<li><b>Name</b>: required. Use the product name as it appears in the loan file heading, for example <code>MAJIC Agricultural Loans</code>. If left blank you see <code>The name field is required.</code></li>'
                . '<li><b>Description</b>: optional explanation of what the group covers, for example <code>Seasonal input finance for smallholder co-operatives</code>.</li>'
                . '<li><b>Save</b> button: saves and returns to the list. The confirmation message reads <code>Loan Category created successfully.</code> (the platform calls a product group a loan category internally) or <code>Loan Category updated successfully.</code></li>'
                . '</ul>'
                . '<h4>To edit a product group</h4>'
                . '<ol>'
                . '<li>Click the gold pencil on the row. The header reads <b>Product Groups / Edit</b>.</li>'
                . '<li>Change the <b>Name</b> or <b>Description</b>.</li>'
                . '<li>Click <b>Save</b>.</li>'
                . '</ol>'
                . '<h4>To delete a product group</h4>'
                . '<ol>'
                . '<li>Click the red bin on the row.</li>'
                . '<li>Click <b>Delete Record</b> in the confirmation window, or <b>Nevermind</b> to keep it.</li>'
                . '<li>The list shows <code>Loan Category deleted successfully.</code></li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Each change is recorded in the audit trail. Product group names are matched by text, so a group named here exactly as it appears in the loan file will be reported under that name in the product-group ECL and disbursement (vintage) reports. Loans whose group is not listed still import; they simply show the raw text from the file (or <code>Unknown</code> for E-Banker files without a Loan Type heading).</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The list is empty: no groups have been created yet. This is normal on a fresh installation; click <b>Create Product Group</b>.</li>'
                . '<li>A report shows a product group you did not create: the name came from the loan file. Add it here with the same spelling if you want to describe it.</li>'
                . '<li><code>The name field is required.</code>: enter a name before saving.</li>'
                . '</ul>',
            'steps' => [
                'Open the sidebar, expand Portfolio Setup and choose Product Groups.',
                'Click Create Product Group at the top right.',
                'Type the product Name exactly as it appears in your loan book file headings.',
                'Add an optional Description.',
                'Click Save. You return to the list and see Loan Category created successfully.',
            ],
            'images' => [
                'product-groups' => 'The Product Groups list with search, the Create Product Group button and the Name and Description columns',
            ],
            'routes' => ['groups.index', 'groups.create', 'groups.store', 'groups.edit', 'groups.update', 'groups.destroy'],
        ],

    ],
];
