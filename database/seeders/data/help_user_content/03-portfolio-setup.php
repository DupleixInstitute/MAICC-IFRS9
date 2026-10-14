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
            'body' => '<p>A loan portfolio is the top-level basket every loan in the platform belongs to (for example Agricultural Loans or Industrial Loans). Every loan book you import is imported into a portfolio, so at least one portfolio must exist before any loan data can be loaded. Portfolios are set up by the risk or finance administrator and rarely change afterwards. To open the page, find the <b>Data foundation</b> block in the sidebar and choose <b>Portfolio Setup</b>; the page opens on the <b>Loan Portfolios</b> tab (breadcrumbs: <b>Data foundation &gt; Portfolio Setup &gt; Loan Portfolios</b>). The other two tabs of the section are <b>Product Groups</b> and <b>Sector Types</b>; each tab carries a small grey chip with the number of records it lists.</p>'
                . '<p>Portfolios are used in the <b>Portfolio</b> box of the loan book import, in the <b>Portfolio</b> box of the <b>Export summary</b> and <b>Disbursements</b> windows on the Loan Book, and in the <b>Loan portfolios</b> card on the Product Groups tab. A portfolio set to Inactive is kept for history.</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Loan Portfolios</b>, with the line <code>The portfolios a loan book is imported into and the ECL is calculated for</code>.</li>'
                . '<li><b>Table columns</b> in order: <b>Name</b> (bold), <b>Description</b> (a dash when blank), <b>Status</b>, <b>Created by</b> (the user who added it), <b>Created</b> (date and time), <b>Actions</b>. The newest portfolio is listed first, fifteen to a page.</li>'
                . '<li><b>Status badge</b>: green <code>Active</code> means the portfolio can be used; grey <code>Inactive</code> means it is retired.</li>'
                . '<li><b>Pagination</b>: page links appear under the table when there are more than fifteen portfolios.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Search portfolios</b> box (in the button row above the table): type any part of a name; the list refreshes as you type.</li>'
                . '<li><b>Status</b> drop-down: <code>All statuses</code>, <code>Active</code> or <code>Inactive</code>.</li>'
                . '<li><b>New portfolio</b> (green): opens the <b>New Portfolio</b> form.</li>'
                . '<li><b>Gold pencil</b> (tooltip <code>Edit</code>): opens the <b>Edit Portfolio</b> form for that row.</li>'
                . '<li><b>Red bin</b> (tooltip <code>Delete</code>): asks <b>Delete the (name) portfolio?</b> with the note <code>A portfolio that holds loan books cannot be deleted; mark it inactive instead.</code> Press <b>Delete</b> to confirm or <b>Cancel</b> to keep it.</li>'
                . '<li><b>Green round help button</b> (bottom right, tooltip <code>Help for this screen</code>): opens this article.</li>'
                . '<li>Who may press them: the page, the form and the delete are open to every signed-in user; no permission or second approval is asked. Keep portfolio changes to the administrator by agreement.</li>'
                . '</ul>'
                . '<h4>The New Portfolio and Edit Portfolio forms</h4>'
                . '<ul>'
                . '<li><b>Name</b>: required, up to 255 characters. Use the name your reports should show.</li>'
                . '<li><b>Description</b>: optional free text on what belongs in the portfolio.</li>'
                . '<li><b>Active</b> tick box: ticked by default. Untick it to retire a portfolio without deleting it.</li>'
                . '<li><b>Back to portfolios</b> (above the form) and <b>Cancel</b> return to the list without saving; <b>Save portfolio</b> saves (it reads <code>Saving...</code> while it works).</li>'
                . '</ul>'
                . '<h4>To add a portfolio</h4>'
                . '<ol>'
                . '<li>Press <b>New portfolio</b>.</li>'
                . '<li>Type the <b>Name</b> and, if useful, a <b>Description</b>. Leave <b>Active</b> ticked.</li>'
                . '<li>Press <b>Save portfolio</b>. You return to the list with <code>Portfolio created successfully.</code></li>'
                . '</ol>'
                . '<h4>To edit or retire a portfolio</h4>'
                . '<ol>'
                . '<li>Find the portfolio (use <b>Search portfolios</b> or the <b>Status</b> drop-down).</li>'
                . '<li>Press the gold pencil. The page title reads <b>Edit Portfolio</b>.</li>'
                . '<li>Change the <b>Name</b> or <b>Description</b>, or untick <b>Active</b> to retire it.</li>'
                . '<li>Press <b>Save portfolio</b>. You see <code>Portfolio updated successfully.</code></li>'
                . '</ol>'
                . '<h4>To delete a portfolio</h4>'
                . '<ol>'
                . '<li>Press the red bin on the row.</li>'
                . '<li>Press <b>Delete</b> in the confirmation window.</li>'
                . '<li>The list shows <code>Portfolio deleted successfully.</code> A portfolio that holds loan books is refused (see below).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>A new portfolio appears at once in the <b>Portfolio</b> box of the loan book import. Loans carry the portfolio\'s identifier, so renaming a portfolio later renames it everywhere without touching the loans.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No portfolio yet. Use New portfolio (top right) to add the first one.</code>: nothing has been set up.</li>'
                . '<li><code>No portfolio matches these filters.</code>: clear the search box or set the status to <code>All statuses</code>.</li>'
                . '<li><code>This portfolio holds loan books, so it cannot be deleted. Mark it inactive instead.</code>: loans have been imported under it.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><code>The name field is required.</code>: enter a name before saving.</li>'
                . '<li>The portfolio you expect is missing from the import page: check it has not been deleted, and set its <b>Active</b> tick box if it was retired.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block of the sidebar choose Portfolio Setup; the Loan Portfolios tab opens.',
                'Press New portfolio in the button row above the table.',
                'Type the Name (required) and an optional Description; leave Active ticked.',
                'Press Save portfolio. You return to the list with Portfolio created successfully.',
                'To retire a portfolio later, press its gold pencil, untick Active and save.',
            ],
            'images' => [
                'portfolios' => 'The Loan Portfolios tab with Search portfolios, the Status filter, New portfolio, the Active badge and the gold pencil and red bin',
                'portfolios-create' => 'The New Portfolio form: Name, Description, the Active tick box, Cancel and Save portfolio',
            ],
            'routes' => ['portfolios.index', 'portfolios.create', 'portfolios.store', 'portfolios.edit', 'portfolios.update', 'portfolios.destroy'],
        ],

        'Sector types' => [
            'body' => '<p>Sector types are the economic-sector classification used for sector concentration and sector ECL reporting. Each sector has a <b>Sector code</b> and a <b>Name</b>. The loan book carries a sector code for every contract (the <code>industry_code</code> column of the standard loan book file), and the code must match a code on this page for the sector name to show. The Loan Book shows the sector under <b>Product group / sector</b> and filters by it, and the client page shows it as <b>Sector</b>. Set the sectors up before the first loan book is loaded. To open the page, choose <b>Portfolio Setup</b> in the <b>Data foundation</b> block and click the <b>Sector Types</b> tab (breadcrumbs: <b>Data foundation &gt; Portfolio Setup &gt; Sector Types</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Sector Types</b>, with the line <code>The economic sectors loans are classified under, matched to the loan book by sector code</code>.</li>'
                . '<li><b>Table columns</b> in order: <b>Code</b>, <b>Name</b> (bold), <b>Description</b> (a dash when blank), <b>Actions</b>. Rows are sorted by code in number order, fifteen to a page, with page links under the table.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Search code or name</b> box: type part of a code or name; the list refreshes shortly after you stop typing.</li>'
                . '<li><b>New sector type</b> (green): opens the <b>New Sector Type</b> form. Shown only with the permission <code>industry_types.create</code>.</li>'
                . '<li><b>Gold pencil</b> (tooltip <code>Edit</code>): opens <b>Edit Sector Type</b>. Shown only with <code>industry_types.update</code>.</li>'
                . '<li><b>Red bin</b> (tooltip <code>Delete</code>): asks <b>Delete the (name) sector type?</b> with <code>The sector type is removed from the list. This cannot be undone.</code> Press <b>Delete</b> or <b>Cancel</b>. Shown only with <code>industry_types.destroy</code>.</li>'
                . '<li>The page itself needs <code>industry_types.index</code>; a user without it is refused. No second approval is asked for any change.</li>'
                . '</ul>'
                . '<h4>The New Sector Type and Edit Sector Type forms</h4>'
                . '<ul>'
                . '<li><b>Sector code</b>: the code as it appears in the loan book, with the hint <code>As it appears in the loan book\'s industry_code column.</code> The form does not insist on it, but a sector without a code matches no loan. Keep codes unique.</li>'
                . '<li><b>Name</b>: required; the sector name shown on reports.</li>'
                . '<li><b>Description</b>: optional notes.</li>'
                . '<li><b>Back to sector types</b> and <b>Cancel</b> leave without saving; <b>Save sector type</b> saves (it reads <code>Saving...</code> while it works).</li>'
                . '</ul>'
                . '<h4>To add a sector</h4>'
                . '<ol>'
                . '<li>Press <b>New sector type</b>.</li>'
                . '<li>Enter the <b>Sector code</b> exactly as the loan book file carries it, then the <b>Name</b> and an optional <b>Description</b>.</li>'
                . '<li>Press <b>Save sector type</b>. You return to the list with <code>Industry Type created successfully.</code></li>'
                . '</ol>'
                . '<h4>To edit or delete a sector</h4>'
                . '<ol>'
                . '<li>Press the gold pencil, change the code, name or description, and press <b>Save sector type</b> (<code>Industry Type updated successfully.</code>).</li>'
                . '<li>Or press the red bin and confirm with <b>Delete</b> (<code>Industry Type deleted successfully.</code>).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Every create, update and delete is written to the activity log. Loans imported with a sector code are grouped under that sector in the sector reports. Deleting a sector does not change loans already imported; they keep the code but show no sector name until a sector with that code exists again.</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No sector type yet. Use New sector type (top right) to add one.</code></li>'
                . '<li><code>No sector type matches the search.</code>: clear the search box.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>Sector reports show blank or unknown sectors: the codes in the loan book do not match the <b>Code</b> column here. Add the missing codes.</li>'
                . '<li>You cannot see <b>New sector type</b> or the row icons: your role lacks the create, update or destroy permission for sector types; ask your administrator.</li>'
                . '<li>Two sectors with the same code: the platform does not stop you, but reports will merge them.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block choose Portfolio Setup, then the Sector Types tab.',
                'Press New sector type.',
                'Enter the Sector code exactly as the loan book carries it in industry_code.',
                'Enter the Name and, if helpful, a Description.',
                'Press Save sector type. You return to the list with Industry Type created successfully.',
                'Repeat until every sector code used in the loan book exists.',
            ],
            'images' => [
                'sector-types' => 'The Sector Types tab with Search code or name, New sector type and the Code, Name and Description columns',
                'sector-types-create' => 'The New Sector Type form with Sector code, Name, Description and Save sector type',
            ],
            'routes' => ['industry_types.index', 'industry_types.create', 'industry_types.store', 'industry_types.show', 'industry_types.edit', 'industry_types.update', 'industry_types.destroy'],
        ],

        'Product groups' => [
            'body' => '<p>Product groups are the lending products loans are reported and staged under (for example a term loan or working capital). They sit below portfolios: the portfolio is chosen at import time, while the product group is read from the loan data itself. In an E-Banker loan book report each <code>Loan Type :</code> row sets the product group of the loans beneath it; the standard loan book file carries it in the <code>type</code> column. This tab lists the groups, shows how the latest loan book uses them, and adds any group the loan book uses that is not yet listed. To open it, choose <b>Portfolio Setup</b> in the <b>Data foundation</b> block and click the <b>Product Groups</b> tab (breadcrumbs: <b>Data foundation &gt; Portfolio Setup &gt; Product Groups</b>).</p>'
                . '<h4>What you see on the screen</h4>'
                . '<ul>'
                . '<li><b>Page title</b>: <b>Product Groups</b>, with the line <code>The loan product groups that loans are reported and staged under</code>.</li>'
                . '<li><b>Four cards</b> (live counts): <b>Product groups</b> (the number listed, with <code>N with no loans in</code> the latest month, or <code>No loan book loaded</code>); <b>Loan portfolios</b> (the number of portfolios, <code>The portfolios loans are grouped into</code>); <b>Loan book groups listed</b> (for example <code>5 of 6</code>: how many of the groups used by the latest loan book are listed here, amber when some are missing, with <code>N used in (month) but not listed</code> or <code>Every group used in (month) is listed</code>); and <b>Facilities in (month)</b> (the number of loans in the latest loan book, with the gross carrying amount in short form; hover for the full figure).</li>'
                . '<li><b>Amber notice</b> (only when needed): <code>The loan book of (month) uses N product group(s) not listed here:</code> followed by the names.</li>'
                . '<li><b>Table columns</b> in order: <b>Name</b> (bold), <b>Description</b>, <b>Loans in (month)</b> (loans of the latest loan book in the group), <b>Gross carrying amount</b> (their total, with the currency code; a dash when the group has no loans), <b>Actions</b>.</li>'
                . '<li><b>Sorting</b>: click any column heading to sort by it; click again to reverse. The small arrow icon beside the heading shows the order (faint when the column is not the one sorted). Name and Description sort A to Z first, the two number columns largest first.</li>'
                . '<li><b>Pager</b> (bottom right): fifteen rows a page with <b>Previous</b>, page numbers, <b>Next</b> and <code>X to Y of Z</code>.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4>'
                . '<ul>'
                . '<li><b>Search product groups</b> box: filters by name or description as you type.</li>'
                . '<li><b>Add the N from the loan book</b>: appears only when the loan book uses groups not listed here. It adds every missing group, named as in the loan book, with the description <code>From the loan book (E-Banker product group)</code>. Shown only with the permission <code>loans.products.create</code>.</li>'
                . '<li><b>New product group</b> (green): opens the <b>New Product Group</b> form. Shown only with <code>loans.products.create</code>.</li>'
                . '<li><b>Gold pencil</b> (tooltip <code>Edit</code>): opens <b>Edit Product Group</b>.</li>'
                . '<li><b>Red bin</b> (tooltip <code>Delete</code>): asks <b>Delete the (name) product group?</b> with <code>The product group is removed from the list. This cannot be undone.</code> Press <b>Delete</b> or <b>Cancel</b>.</li>'
                . '<li>The pencil and the bin are shown to every user who can open the tab; no second approval is asked.</li>'
                . '</ul>'
                . '<h4>The New Product Group and Edit Product Group forms</h4>'
                . '<ul>'
                . '<li><b>Name</b>: required. Use the product name exactly as the loan book carries it; names are matched ignoring capitals and spaces at either end.</li>'
                . '<li><b>Description</b>: optional.</li>'
                . '<li><b>Back to product groups</b> and <b>Cancel</b> leave without saving; <b>Save product group</b> saves (<code>Saving...</code> while it works).</li>'
                . '</ul>'
                . '<h4>To list every group the loan book uses</h4>'
                . '<ol>'
                . '<li>Read the amber notice and the <b>Loan book groups listed</b> card.</li>'
                . '<li>Press <b>Add the N from the loan book</b>.</li>'
                . '<li>The list shows <code>N product group(s) added from the loan book.</code> Edit any description you want to improve.</li>'
                . '</ol>'
                . '<h4>To add, edit or delete a group by hand</h4>'
                . '<ol>'
                . '<li>Press <b>New product group</b>, type the <b>Name</b> and an optional <b>Description</b>, and press <b>Save product group</b> (<code>Product group created.</code>).</li>'
                . '<li>To change one, press its gold pencil and save (<code>Product group updated.</code>).</li>'
                . '<li>To remove one, press its red bin and confirm (<code>Product group deleted.</code>).</li>'
                . '</ol>'
                . '<h4>What happens next</h4>'
                . '<p>Each change is written to the activity log. Loans keep the product group text they were imported with; the list here describes those names and drives the counts. A loan whose group is not listed still imports and reports under its own text (or <code>Unknown</code> for an E-Banker report with no Loan Type rows).</p>'
                . '<h4>Empty states and messages</h4>'
                . '<ul>'
                . '<li><code>No product group yet. Use New product group to add one.</code></li>'
                . '<li><code>No product group matches the search.</code></li>'
                . '<li><code>Every product group in the loan book is already listed.</code>: the sync found nothing to add.</li>'
                . '</ul>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li>The cards read <code>No loan book loaded</code> and dashes: no loan book has been imported or built yet.</li>'
                . '<li>A group shows 0 loans: the name here differs from the loan book text. Edit the name to match.</li>'
                . '<li><code>The name field is required.</code>: enter a name before saving.</li>'
                . '</ul>',
            'steps' => [
                'In the Data foundation block choose Portfolio Setup, then the Product Groups tab.',
                'Read the four cards and any amber notice of groups the loan book uses that are not listed.',
                'Press Add the N from the loan book to list every missing group in one go.',
                'Or press New product group, type the Name as the loan book carries it, add a Description and press Save product group.',
                'Click a column heading to sort, for example by Gross carrying amount.',
            ],
            'images' => [
                'product-groups' => 'The Product Groups tab with the four cards, the sortable Name, Description, Loans and Gross carrying amount columns, and New product group',
            ],
            'routes' => ['groups.index', 'groups.create', 'groups.store', 'groups.sync', 'groups.edit', 'groups.update', 'groups.destroy'],
        ],

    ],
];
