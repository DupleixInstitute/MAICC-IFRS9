<?php

/**
 * Administrator Manual chapter 3 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * the one-page tabbed Settings screen, the Governance Centre of 13 October and the staging basis).
 */
return [

    'Organisation Settings' => [

        'The settings hub' => [
            'body' => '<p><b>Settings</b> is one page with five tabs. It holds only what the IFRS 9 and EIR system uses: the organisation details, the reporting currency, outgoing email, the governed staging basis, and links to further administration screens. Open <b>Administration &amp; support</b>, then <b>Access &amp; Setup</b>, then <b>Settings</b>. The page and every save on it need the <code>settings</code> permission. The open tab is kept in the address (for example <code>?tab=email</code>), so a refresh or a shared link opens the same tab.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>Three figures across the top: <b>Organisation</b> (the saved name), <b>Reporting currency</b> and <b>Days past due counted from</b> (the governed days-past-due basis in force). Each reads <b>Not set</b> when empty.</li>'
                . '<li>The tab bar, each tab with a count: <b>Organisation</b> (the fields the form holds), <b>Reporting</b>, <b>Email</b>, <b>Staging basis</b> (the governed settings and threshold rows shown) and <b>More settings</b> (the screens linked that you may open).</li>'
                . '<li>A one-line description of the open tab under the tab bar.</li>'
                . '<li>The round green <b>Help for this screen</b> button at the bottom right.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Governance Centre</b> (top right, gavel icon). Shown only with <code>eir.govern</code>. Opens the Governance Centre.</li>'
                . '<li>Each form tab has its own green save button with a tick: <b>Save organisation details</b>, <b>Save reporting currency</b>, <b>Save email settings</b>.</li>'
                . '</ul>'
                . '<h4>The More settings tab</h4><p>A grid of cards, each with an icon, a name and one line. A card is shown only when you hold its permission:</p><ul>'
                . '<li><b>LGD payment tracking</b> (<code>settings</code>). Payment tracking runs used to build the loss given default.</li>'
                . '<li><b>Currencies</b> (<code>currencies.index</code>). The currency list the reporting currency is chosen from.</li>'
                . '<li><b>Financial periods</b>. The financial year and the reporting periods.</li>'
                . '<li><b>Page help text</b> (<code>settings</code>). The legacy per-screen help rows.</li>'
                . '<li><b>Licence</b> (<code>settings</code>). The licence key for this installation.</li>'
                . '<li><b>Governance Centre</b> (<code>eir.govern</code>).</li>'
                . '</ul>'
                . '<h4>What is no longer shown</h4><p>The settings inherited from the platform this system was cloned from are not on the page because nothing in the IFRS 9 or EIR engines reads them: the SMS gateway, loan application score bands, approval stages, invoice fields, licence type, self registration, timezone and the site-online switch. Their old addresses still answer: <b>General</b> and <b>Organisation</b> open the Organisation tab, <b>System</b> opens the Reporting tab, <b>Email</b> the Email tab and <b>Other</b> the More settings tab.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>You do not have access to any of these screens. Ask an administrator.</b> On More settings, when no card is permitted.</li>'
                . '<li><b>Organisation details saved.</b>, <b>Reporting currency saved.</b>, <b>Email settings saved.</b> after a save.</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Settings is not in the menu.</b> Your role lacks <code>settings</code>.</li>'
                . '<li><b>The Governance Centre button is missing.</b> Your role lacks <code>eir.govern</code>.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Access & Setup, then Settings.',
                'Read the three figures at the top: organisation, reporting currency and the days-past-due basis.',
                'Open the tab you need: Organisation, Reporting, Email, Staging basis or More settings.',
                'Change the fields and press the tab\'s own save button.',
            ],
            'images' => [
                'settings' => 'Settings with its three figures and five tabs',
            ],
            'routes' => ['settings.index'],
        ],

        'Organisation identity and branding' => [
            'body' => '<p>The <b>Organisation</b> tab of Settings holds the organisation identity. These fields are not decoration: they appear in the top bar and the sidebar, on report headers and exports, and on the cover of all four System Documentation documents.</p>'
                . '<h4>Field by field</h4><ul>'
                . '<li><b>Organisation name</b>. Required. Shown in the top bar and on every report and export. Set it to MAIIC.</li>'
                . '<li><b>Organisation email</b>. The general contact address printed on reports.</li>'
                . '<li><b>Telephone</b>. Main office number, printed on reports.</li>'
                . '<li><b>Mobile</b>. Optional second contact number.</li>'
                . '<li><b>Website</b>. The public website address.</li>'
                . '<li><b>Postal address</b>. Printed in the header of reports and exports.</li>'
                . '<li><b>Logo</b>. Shown in the sidebar and on reports; PNG or JPG up to 2 MB. The current logo shows beside the file chooser. Leave the chooser empty to keep it.</li>'
                . '<li><b>Small logo</b>. The square version used when the sidebar is collapsed to its icon rail. Leave empty to keep the current one.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Save organisation details</b>. Saves the fields the form sends and any new logo. Needs <code>settings</code>.</li></ul>'
                . '<h4>What happens next</h4><p>The new name and logos show on the next page you open. Open a report and download its PDF, and open a manual, to confirm the branding.</p>'
                . '<h4>Empty states and messages</h4><ul><li><b>Organisation details saved.</b></li><li>A field error under the field, for example when the organisation name is blank or the logo is not an image.</li></ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The logo is stretched or cropped on the PDF.</b> Supply a PNG with a transparent background at a sensible aspect ratio.</li>'
                . '<li><b>The collapsed sidebar shows the wide logo squeezed.</b> No small logo is saved. Upload a square one.</li>'
                . '</ul>',
            'steps' => [
                'Open Settings and the Organisation tab.',
                'Set Organisation name to MAIIC and fill the contact fields.',
                'Choose the Logo and the Small logo files if they need replacing.',
                'Press Save organisation details, then download a report PDF to confirm the branding.',
            ],
            'images' => [
                'settings-organisation' => 'The Organisation tab with the contact fields and the two logos',
            ],
            'routes' => ['settings.general', 'settings.organisation'],
        ],

        'System preferences' => [
            'body' => '<p>The <b>Reporting</b> tab of Settings holds the one platform-wide preference the IFRS 9 and EIR system reads: the reporting currency. The old System page, with its timezone, site-online switch, licence type and self-registration settings, opens this tab instead; those values are left as they are and nothing reads them.</p>'
                . '<h4>Field by field</h4><ul>'
                . '<li><b>Reporting currency</b>. Required. Chosen from the Currencies list, active currencies first; an inactive one carries <b>- not active</b>. It is the currency code shown beside amounts on the dashboard and in the accounting reports. It labels figures; it does not convert them. For MAIIC it is the Malawi Kwacha.</li>'
                . '<li><b>Financial year and periods</b>. A link, <b>Open Financial Periods</b>, to the screen where the periods are kept.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Save reporting currency</b>. Needs <code>settings</code>.</li></ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>The chosen currency is not marked active: check it is the currency the loan book is held in, or add the right one under Currencies.</b></li>'
                . '<li><b>Reporting currency saved.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Figures show the wrong currency label.</b> Check the reporting currency here, and the currency record under Currencies.</li>'
                . '<li><b>Audit timestamps look hours out.</b> The timezone is now a server setting, not a screen setting. Ask ICT; the Installation Guide covers it.</li>'
                . '</ul>',
            'steps' => [
                'Open Settings and the Reporting tab.',
                'Choose the reporting currency, the Malawi Kwacha for MAIIC.',
                'Press Save reporting currency.',
                'Open the dashboard and check the figures carry the right currency label.',
            ],
            'images' => [
                'settings-system' => 'The Reporting tab with the reporting currency',
            ],
            'routes' => ['settings.system'],
        ],

        'Email' => [
            'body' => '<p>The <b>Email</b> tab of Settings configures outgoing mail. The platform sends password resets and new-user sign-in details; it does not send bulk mail.</p>'
                . '<h4>Field by field</h4><ul>'
                . '<li><b>Sending method</b>. <b>Mail server (SMTP)</b> or <b>This server\'s sendmail</b>. The server fields below show only for SMTP.</li>'
                . '<li><b>Mail server</b>. The SMTP server name MAIIC ICT gives you. Required for SMTP.</li>'
                . '<li><b>Port</b>. Usually 587 with TLS, 465 with SSL, or 25 with no encryption. Required for SMTP.</li>'
                . '<li><b>Username</b>. The mailbox account used to sign in to the mail server.</li>'
                . '<li><b>Password</b>. Never shown once saved; the box then reads <b>Saved - leave blank to keep it</b>. Leave it blank to keep the saved password.</li>'
                . '<li><b>Encryption</b>. <b>None</b>, <b>TLS</b> or <b>SSL</b>.</li>'
                . '<li><b>Sender address</b>. The from address users see. Use a monitored MAIIC address so a password-reset mail is not mistaken for phishing.</li>'
                . '<li><b>Sender name</b>. The name shown beside it, for example MAIIC IFRS 9.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul><li><b>Save email settings</b>. Needs <code>settings</code>.</li></ul>'
                . '<h4>What happens next</h4><p>Test at once with <b>Forgot password?</b> on the login page for your own account. If nothing arrives, the relay is usually blocking the server rather than the settings being wrong; the Installation Guide has the command-line test.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Nothing sends and nothing errors.</b> The mail is queued and the queue worker is not running. Ask ICT.</li>'
                . '<li><b>Mail lands in junk.</b> The sender domain does not match the sending server. That is a mail-infrastructure fix.</li>'
                . '</ul>',
            'steps' => [
                'Get the relay host, port, credentials and encryption from MAIIC ICT.',
                'Open Settings and the Email tab, and choose Mail server (SMTP).',
                'Enter the mail server, port, username, password and encryption.',
                'Set the sender address to a monitored MAIIC address and the sender name to MAIIC IFRS 9.',
                'Press Save email settings, then use Forgot password? on the login page to test.',
            ],
            'images' => [
                'settings-email' => 'The Email tab with the mail server and sender fields',
            ],
            'routes' => ['settings.email'],
        ],

        'Reference data' => [
            'body' => '<p>Reference data must be complete <b>before</b> loan book months are built, because the build attaches each loan to a portfolio and a sector, and the reports group by them. Fixing it afterwards means rebuilding.</p>'
                . '<h4>The lists and where they are</h4><ul>'
                . '<li><b>Currencies</b>. Settings, More settings, Currencies (<code>currencies.index</code>). Columns <b>Name</b> (with the code, and a star on the default currency), <b>Symbol</b>, <b>Exchange Rate</b>, <b>Decimals</b>, <b>Active</b> and <b>Actions</b>; <b>Create Currency</b> at the top. The reporting currency is chosen from this list.</li>'
                . '<li><b>Loan Portfolios</b>, <b>Product Groups</b> and <b>Sector Types</b>. Data foundation, Portfolio Setup. The segments MAIIC reports on, the product families, and the economic sectors behind the sector reports. These are described in the User Manual.</li>'
                . '<li><b>Chart of Accounts</b>, <b>Branches</b>, <b>Legal Types</b> and <b>Banks</b>. Lists kept from the earlier platform. They are no longer linked from the menu or from Settings, because no IFRS 9 or EIR figure reads them; their screens still open from their old addresses for anyone holding their permissions.</li>'
                . '</ul>'
                . '<h4>Each list behaves the same way</h4><p>A table with a search filter, a create button, and green eye, gold pencil and red bin row actions. Deleting asks for confirmation. A record already used by loans cannot be removed cleanly, so rename rather than delete when a segment is reorganised.</p>'
                . '<h4>What happens next</h4><p>Changing a portfolio name changes it everywhere it is reported, including months already locked. Say so when circulating a report that reads differently from last month\'s.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Loans arrive with no portfolio.</b> The portfolio did not exist when the month was built. Create it and rebuild the month.</li>'
                . '<li><b>A sector report has an unmapped row.</b> Loans carry a sector code with no matching sector type. Add the missing type.</li>'
                . '</ul>',
            'steps' => [
                'Open Settings, More settings, Currencies, and confirm the Malawi Kwacha is present and active.',
                'Open Data foundation, Portfolio Setup, and confirm the portfolios MAIIC reports on all exist.',
                'Check Sector Types against the sector disclosure in the latest annual report.',
                'Only once all three are right, build the loan book months.',
            ],
            'images' => [
                'currencies' => 'Currencies, with the default currency starred',
                'chart-of-accounts' => 'The chart of accounts, a list kept from the earlier platform',
                'branches' => 'Branches, a list kept from the earlier platform',
                'legal-types' => 'Legal types, a list kept from the earlier platform',
                'banks' => 'Banks, a list kept from the earlier platform',
            ],
            'routes' => ['currencies.index', 'chart_of_accounts.index', 'branches.index', 'legal_types.index', 'banks.index'],
        ],

        'SMS and other settings' => [
            'body' => '<p>Three screens survive from the platform this system was cloned from. None is linked from Settings any more, and none changes an IFRS 9 or EIR figure. Know what they are so nobody mistakes them for live controls.</p>'
                . '<h4>The SMS page</h4><p>Reached only by its old address. It shows <b>SMS Enabled</b> (<b>Yes</b> or <b>No</b>), a <b>Default SMS Gateway</b> list that appears when SMS is enabled, and <b>Save</b>. It stores the two values; nothing in the system sends a text message. Notifications go to the bell in the top bar.</p>'
                . '<h4>Loan application score bands</h4><p>Reached only by its old address. Bands are defined by name with a minimum and a maximum, and overlapping bands are refused with <b>Bands must not overlap</b>. It belongs to the legacy credit-scoring feature, outside the contracted IFRS 9 and EIR scope.</p>'
                . '<h4>Page help text</h4><p>Settings, More settings, <b>Page help text</b> (<code>settings</code>). A list of short per-screen help rows with the columns <b>Title</b>, <b>Route</b> and <b>Actions</b> (view, edit and delete icons). Its description says the rows are shown by the question-mark button; in this version the question-mark button opens the manual article mapped to the screen instead, so maintain the manual rather than these rows. An empty list reads <b>No screen has its own help text yet: the ? button on every screen opens the matching manual article.</b></p>'
                . '<h4>The old Other page</h4><p>Its address now opens the <b>More settings</b> tab of Settings.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>SMS is switched on but no messages arrive.</b> Expected. Only the setting is stored.</li>'
                . '<li><b>A page help row was edited and nothing changed.</b> Expected; edit the manual article instead (System Documentation, then the manual, then Edit manual).</li>'
                . '</ul>',
            'steps' => [
                'Leave the SMS page set to No; nothing sends text messages.',
                'Do not configure score bands; the feature is outside the contracted scope.',
                'Maintain help in the manuals with Edit manual, not in Page help text.',
            ],
            'images' => [
                'settings-sms' => 'The legacy SMS page, reached only by its old address',
            ],
            'routes' => ['settings.sms', 'settings.other', 'settings.loan_bands', 'manuals.index'],
        ],

        'Licence' => [
            'body' => '<p><b>Licence</b> holds the licence key for this installation and what it covers. Open <b>Settings</b>, <b>More settings</b>, then <b>Licence</b>. It needs the <code>settings</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>When a key is held, a table: <b>Licensed To</b>, <b>Product</b>, <b>Package</b>, <b>Start Date</b> and <b>Expires</b>, with an <b>Active</b> or <b>Expired</b> marker beside the expiry date.</li>'
                . '<li>A <b>License Key</b> box and a <b>Save</b> button to enter or replace the key.</li>'
                . '</ul>'
                . '<h4>What it is and is not</h4><p>Under the implementation agreement MAIIC owns the installed system, its source code and its data in perpetuity. The licence record identifies the installation for support. It is not a switch that turns the platform off, and an expired record does not stop anyone working.</p>'
                . '<h4>Empty states and messages</h4><ul><li><b>No licence key is held for this installation. Enter the key you were given.</b></li></ul>'
                . '<h4>Common problems</h4><ul><li><b>The details are wrong or missing.</b> Raise a ticket with Dupleix; the key is issued at handover.</li></ul>',
            'steps' => [
                'Open Settings, More settings, then Licence.',
                'Read Licensed To, Product, Package and the dates.',
                'To enter a new key, paste it into License Key and press Save.',
            ],
            'images' => [
                'license' => 'The licence record for this installation',
            ],
            'routes' => ['license.index'],
        ],

        'The Governance Centre' => [
            'body' => '<p>The <b>Governance Centre</b> holds every calculation setting the engines use: how rates and resets feed the effective interest rate, which cash flows count, where the data comes from, the reconciliation and control conventions, the staging basis, the PD and LGD segments, the forward-looking adjustment and the scenarios, and the Mega Farm programme. For each it shows the value in force, the options, who proposed and who approved it, and from when. A change is proposed by one person and applies from its effective date only once a second person approves it. A month already run keeps the settings it ran with, and a setting with no approved value stops the calculation that needs it rather than guessing.</p>'
                . '<p>Open <b>Administration &amp; support</b>, then <b>Governance</b>, then <b>Governance Centre</b>, or the gavel button on Settings. Seeing the page, proposing and approving all need the <code>eir.govern</code> permission.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li>The header chips: <b>N settings</b>; <b>N in force on</b> today\'s date; <b>N awaiting approval</b> (amber when any); <b>N approved from a later date</b> (only when any); <b>N with no approved value</b> (red, only when any).</li>'
                . '<li>Two view buttons, each with a count: <b>Settings by area</b> and <b>Needs attention</b> (the count turns amber when something waits).</li>'
                . '<li>A search box, <b>Search a setting, its key, its value or what it does</b>, which filters both views as you type.</li>'
                . '<li>In <b>Settings by area</b>: the area chips, <b>All areas</b> then one chip per area with its count: <b>Interest &amp; rates</b>, <b>Cash flows</b>, <b>Data sources</b>, <b>Accounting &amp; controls</b>, <b>Staging</b>, <b>PD &amp; LGD segments</b>, <b>Forward-looking</b> and <b>Mega Farm</b>. A setting not yet placed in an area is listed under <b>Other</b>. Each area is a coloured band with its description and a grid of setting cards.</li>'
                . '<li>Each card shows the setting\'s name, its key in small type, the value in force (or <b>No approved value: the calculation stops</b> in red), two lines of its description, when the value has applied from, and <b>Open</b>.</li>'
                . '<li>Each card carries one badge: <b>recorded, not yet applied</b> (grey: the value is approved and kept but no engine reads it yet; hover for what the engine does instead), <b>no value</b> (red), <b>awaiting approval</b> (amber), <b>recommended</b> (green: the value in force is the Dupleix recommended default) or <b>changed</b> (blue: a value other than the recommended one is in force).</li>'
                . '<li>In <b>Needs attention</b>: a card for every setting with no approved value or with a change waiting, showing its area, the pending rows (<b>Awaiting approval</b> or <b>Approved, applies from</b> a date), who proposed and approved it and the reason.</li>'
                . '</ul>'
                . '<h4>One setting in full</h4><p>Click a card to open it. The window shows the area, the name and key, the full description, and, for a recorded-but-not-applied setting, a grey note: <b>Recorded, not yet applied. No engine reads this setting yet: changing it changes no figure.</b> followed by what the engine does instead. Below that:</p><ul>'
                . '<li><b>Value in force on</b> today: the value, since when, and who approved it (or <b>the Dupleix recommended default</b>).</li>'
                . '<li><b>Options</b>: every option, the one in force in bold and the recommended one marked <b>(recommended)</b>. For <b>How the adjustment reaches the PD</b> each option carries a badge, <b>preconditions met</b> or <b>not yet available</b>, and a <b>method card</b> link that opens what the method does, its formula and symbols, what it implies, each precondition marked <b>met</b> or <b>not met</b> with its live figure, and a worked example on a real contract.</li>'
                . '<li><b>Proposed and upcoming</b>: each waiting change with its value, effective date, proposer, approver and reason, and an <b>Approve</b> button on a proposal.</li>'
                . '<li><b>Every value of</b> the setting: a table with <b>State</b> (<b>In force</b>, <b>Past</b>, <b>Upcoming</b>, <b>Awaiting approval</b>), <b>Value</b>, <b>Effective from</b>, <b>Proposed by</b> (<b>Dupleix (seeded)</b> for the shipped default), <b>Approved by</b> (<b>Seeded default</b> or <b>Not yet</b>) and <b>Reason</b>.</li>'
                . '<li><b>Superseded values (audit copies)</b>: <b>Value</b>, <b>Was effective from</b>, <b>Superseded on</b>, <b>By</b>, <b>Replaced by row</b>; or <b>No value has been superseded yet.</b></li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><ul>'
                . '<li><b>Propose a change</b> (pen icon, in the setting window). Opens the proposal form. Any holder of <code>eir.govern</code>.</li>'
                . '<li><b>Close</b>. Closes the setting window.</li>'
                . '<li><b>Approve</b>. Approves a proposal. It is greyed out for the person who proposed it, with the tooltip <b>The person who proposed a change cannot approve it</b>. The only exception is a user with the admin role while the governed setting <b>Administrator override of maker-checker</b> is <b>Allowed for administrators</b>; its recommended value is <b>Not allowed</b>.</li>'
                . '<li>In the proposal form: <b>New value</b> (the option in force is greyed out and marked <b>(in force now)</b>), <b>Effective from</b> (defaults to today; must be later than the last approved change; applies from that date forward only), <b>Reason</b> (at least 10 characters, written to the audit log with the old and new value), then <b>Cancel</b> and <b>Propose change</b> (enabled once all three are filled).</li>'
                . '</ul>'
                . '<h4>To change a setting</h4><ol>'
                . '<li>Find the setting with the search box or the area chips, and open its card.</li>'
                . '<li>Read the options, and the method card where there is one.</li>'
                . '<li>Press <b>Propose a change</b>, choose the new value, set the effective date and write the reason.</li>'
                . '<li>Press <b>Propose change</b>. The message reads that the change takes effect from the date once a second person approves it.</li>'
                . '<li>A second holder of <code>eir.govern</code> opens <b>Needs attention</b>, opens the setting and presses <b>Approve</b>.</li>'
                . '</ol>'
                . '<h4>What happens next</h4><ul>'
                . '<li>The proposal and the approval are written to the Audit Trail as module actions (<b>EIR Governance Setting Proposed</b>, <b>EIR Governance Setting Approved</b>) with the old and new value, the proposer, the approver, whether the override was used and the first month the change applies to.</li>'
                . '<li>On approval the value previously in force is copied to the superseded history; it stays in force for every date before the new effective date.</li>'
                . '<li>Every engine reads the value in force at the month-end it calculates, so rerunning an earlier month uses that month\'s values. Locking a reporting month keeps a snapshot of every value in force at its month-end.</li>'
                . '<li>The Workspace tab <b>Awaiting approval</b> counts the proposals under <b>Governance setting changes proposed</b>.</li>'
                . '</ul>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No setting matches the search.</b></li>'
                . '<li><b>Nothing needs attention: every setting has an approved value and no change is waiting.</b></li>'
                . '<li>A refused proposal or approval shows <b>The change was not saved</b> with the reason, for example: <b>The effective date must be later than</b> a date <b>, the date of the last approved change</b>; <b>A change to</b> a setting <b>effective</b> a date <b>already exists; choose another date.</b>; the setting <b>is already</b> that value <b>; there is nothing to change.</b>; <b>A change needs a specific reason of at least 10 characters.</b>; <b>The person who proposed a change cannot approve it.</b>; <b>Only a proposed change can be approved.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A month-end step stops naming a governance setting.</b> The setting has no approved value. Open Needs attention.</li>'
                . '<li><b>A proposal was made in error.</b> There is no reject or withdraw button for a governance proposal. Leave it unapproved and raise a ticket; while it waits, no other change for that setting can use the same effective date.</li>'
                . '<li><b>A setting was changed and no figure moved.</b> Check its badge: a <b>recorded, not yet applied</b> setting is read by no engine yet.</li>'
                . '<li><b>A method card says not yet available.</b> The card states that the method cannot be selected until every precondition is met; the proposal form itself does not check the preconditions, so read the card before proposing.</li>'
                . '</ul>',
            'steps' => [
                'Open Administration & support, then Governance, then Governance Centre.',
                'Find the setting by search or area chip and open its card.',
                'Press Propose a change, choose the new value, the effective date and a reason of at least 10 characters.',
                'Press Propose change; the setting now shows awaiting approval.',
                'A second person with eir.govern opens Needs attention, opens the setting and presses Approve.',
                'Confirm the new row reads In force or Upcoming in Every value of the setting.',
            ],
            'images' => [
                'eir-governance' => 'The Governance Centre: header chips, the two views, the area chips and the setting cards',
            ],
            'routes' => ['eir-governance.index'],
        ],

        'Staging basis and days-past-due thresholds' => [
            'body' => '<p>The <b>Staging basis</b> tab of Settings shows how days past due are counted and the thresholds that set each stage, under the Reserve Bank of Malawi directive for development finance institutions. The four governed staging settings are shown read only here and change only in the Governance Centre; the day thresholds per facility class change on this tab, under maker-checker. Open <b>Settings</b> and choose <b>Staging basis</b>.</p>'
                . '<h4>What you see on the screen</h4><ul>'
                . '<li><b>How this works</b>, a fold-out note: each month-end, days past due are counted on the basis shown and compared with the thresholds for the loan\'s facility class; the missed-instalment trigger and the cure period then apply. A month is always staged on the values in force at its month-end.</li>'
                . '<li><b>Governed staging settings in force today</b>: <b>Setting</b> (with a one-line help), <b>Value in force</b> (green badge, or red <b>Not approved yet</b>), <b>In force since</b> (<b>From installation</b> for the shipped value), <b>Approved by</b> and <b>Pending changes</b> (<b>N awaiting approval or a later date</b>, or <b>None</b>). The four settings are the days-past-due basis, the missed-instalment trigger for Stage 3, the months a loan stays in its stage after it cures, and the rule for rebutting the 30-day Stage 2 presumption.</li>'
                . '<li><b>Days-past-due thresholds by facility class</b>: <b>Facility class</b> (<b>All facilities</b>, <b>Long term</b>, <b>Mega Farm</b>, <b>Short term</b>), <b>Applies from tenor (months)</b>, <b>Stage 2 from (days)</b>, <b>Stage 3 from (days)</b>, <b>Effective from</b>, <b>Status</b>, <b>Proposed by</b> (<b>Loaded with the system</b> for the shipped rows) and <b>Approved by</b>.</li>'
                . '<li>The <b>Status</b> badges: <b>Proposed, awaiting a second person</b>, <b>Rejected</b>, <b>In force</b>, and <b>Approved, from its date</b> for an approved row whose date has not come.</li>'
                . '<li>A note under the table: a loan uses the most specific approved row it qualifies for, its own facility class before All facilities, then the longest tenor it meets.</li>'
                . '</ul>'
                . '<h4>Buttons and icons</h4><p>All of these need <code>eir.govern</code>; without it the tab is read only and says <b>You need the governance right to propose a change. Ask an administrator.</b></p><ul>'
                . '<li><b>Approve</b> on a proposed row. Asks for confirmation, naming the class, tenor, both thresholds and the date. The proposer sees <b>Yours: a second person approves</b> instead.</li>'
                . '<li><b>Reject</b> on a proposed row. Asks for a reason of at least 10 characters.</li>'
                . '<li><b>Propose a threshold change</b>, a form: <b>Facility class</b> (a code such as DEFAULT, LONG_TERM, MEGA_FARM), <b>Applies from tenor (months)</b>, <b>Effective from</b>, <b>Stage 2 from (days)</b>, <b>Stage 3 from (days)</b>, <b>Reason</b>, <b>Rebuttal basis</b> (needed when Stage 2 starts later than day 31), and <b>Propose</b>.</li>'
                . '<li><b>Change in the Governance Centre</b>. Opens the Governance Centre to change the four governed settings.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>An approved threshold is read by staging from its effective date forward only. A rejected row stays on the list, greyed, with its reason as a tooltip.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No governed staging settings were found. Open the Governance Centre to set them.</b></li>'
                . '<li><b>No thresholds are loaded. Staging stops and names the missing rule until one is approved.</b></li>'
                . '<li><b>Threshold proposed; a second person approves it before staging reads it.</b>, <b>Threshold approved; staging reads it from its effective date.</b>, <b>Threshold proposal rejected.</b></li>'
                . '<li>Refusals: <b>Stage 2 starts at one day or more and Stage 3 starts later than Stage 2.</b>; <b>Stage 2 from later than 31 days past due rebuts the IFRS 9 presumption (B5.5.37): state the evidence in the rebuttal basis.</b>; <b>Maker-checker: the approver must be a different person from the proposer, and both must be named.</b>; the effective date must be later than the last approved threshold for that class and tenor; and a reporting period that is locked refuses a threshold dated inside it.</li>'
                . '</ul>',
            'steps' => [
                'Open Settings and the Staging basis tab.',
                'Read the governed settings in force and the thresholds by facility class.',
                'To change a threshold, fill Propose a threshold change with a reason, and a rebuttal basis if Stage 2 starts after day 31.',
                'Press Propose; a second person with eir.govern presses Approve on the row.',
                'To change the days-past-due basis, the missed-instalment trigger, the cure period or the rebuttal rule, use Change in the Governance Centre.',
            ],
            'images' => [
                'settings-staging' => 'The Staging basis tab with the governed settings and the thresholds by facility class',
            ],
            'routes' => [],
        ],

    ],

];
