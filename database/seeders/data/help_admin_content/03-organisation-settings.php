<?php

/**
 * Administrator Manual chapter 3 (Ticket #011 depth rewrite).
 */
return [

    'Organisation Settings' => [

        'The settings hub' => [
            'body' => '<p><b>Administration</b>, then <b>Settings</b>, is the configuration hub. It links to the sections below. Every update on any of them requires the settings permission and is written to the audit trail.</p><h4>The sections</h4><ul><li><b>Organisation</b>. Branding and contact details, plus links to the reference data.</li><li><b>General</b>. The organisation identity fields that appear on reports and manual covers.</li><li><b>System</b>. Currency, timezone, the online switch and the licence key.</li><li><b>Email</b>. The outbound mail server used for password resets and notifications.</li><li><b>SMS</b>. A gateway configuration inherited from the loan-origination template. It is not used by the IFRS 9 workflow.</li><li><b>Other</b> and <b>Loan bands</b>. Score bands from the same inheritance, also unused here.</li><li><b>Manuals</b>. The legacy per-page manual entries, superseded by the help centre.</li><li><b>Licence</b>. The licence record for this installation.</li><li><b>Tariffs</b> and the LGD payment calculation link. Reference data reached from the hub.</li></ul><p>Consolidating this hub and retiring the unused sections is a logged change request. Until then, leave the SMS, loan band and manual sections alone.</p><h4>Common problems</h4><ul><li><b>A settings page will not save.</b> You need the settings permission. If you have it and the save still fails, check the log with ICT.</li><li><b>A change does not appear.</b> Settings are cached for a short period for speed. Wait a minute or ask ICT to clear the cache.</li></ul>',
            'images' => [
                'settings' => 'The Settings hub with its sections',
            ],
            'routes' => ['settings.index'],
        ],

        'Organisation identity and branding' => [
            'body' => '<p><b>Settings</b>, then <b>General</b>, holds the organisation identity. These fields are not decoration: they are printed on every report header, every export and the cover of all four System Documentation documents.</p><h4>Field by field</h4><ul><li><b>Organisation Name</b>. Set this to <b>MAIIC</b>. It appears in the page header, on report headers, on PDF covers and in the confidentiality line at the foot of each document. A fresh installation carries the template name, which must be changed before anything is circulated.</li><li><b>Organisation Address</b>, <b>Organisation Email</b>, <b>Organisation Mobile</b>, <b>Organisation Tel</b>, <b>Organisation Website</b>. Contact details used on documents.</li><li><b>Logo</b>. The main logo, used on report headers and manual covers.</li><li><b>Small Logo</b>. The compact mark used in the sidebar header.</li><li><b>Letterhead</b>. An optional letterhead image for documents.</li></ul><h4>What happens next</h4><p>Save, then open any report and press <b>Download PDF</b> to confirm the name and logo are right. Because the same values feed the manual covers, check one of those too.</p><h4>Common problems</h4><ul><li><b>The old name still shows.</b> The settings cache has not expired yet; give it a minute.</li><li><b>The logo is stretched or cropped on the PDF.</b> Supply a PNG with a transparent background at a sensible aspect ratio; the template sizes it to a fixed width.</li></ul>',
            'steps' => [
                'Open Settings, then General.',
                'Set Organisation Name to MAIIC and fill the address and contact fields.',
                'Upload the Logo and the Small Logo.',
                'Save, then open a report and download its PDF to confirm the branding.',
            ],
            'images' => [
                'settings-general' => 'The organisation identity fields including the logo uploads',
                'settings-organisation' => 'The Organisation page with its links to the reference data',
            ],
            'routes' => ['settings.general', 'settings.organisation'],
        ],

        'System preferences' => [
            'body' => '<p><b>Settings</b>, then <b>System</b>, holds the platform-wide preferences.</p><h4>Field by field</h4><ul><li><b>Currency</b>. The organisation currency. For MAIIC this is the Malawi Kwacha, MWK. Every figure on the dashboard and in the reports is labelled with it, so setting it wrongly mislabels the entire platform. It is seeded to MWK on installation.</li><li><b>Timezone</b>. Used for timestamps in the audit trail and on reports. Set it to the local timezone so that "who did what when" reads correctly.</li><li><b>Site Online</b>. A switch that takes the application offline for users. Use it only when told to, for example during a data migration.</li><li><b>License Key</b> and <b>License Key Type</b>. The licence record, described in its own article.</li><li><b>Allow Member Self Registration</b>. Leave this off. Accounts are created by an administrator; self-registration would let anyone create one.</li><li><b>Webstudio</b>. A template setting not used by the IFRS 9 workflow.</li></ul><h4>What happens next</h4><p>The currency and timezone are read on every page load through the shared settings, so a change reaches every screen as soon as the short cache expires.</p><h4>Common problems</h4><ul><li><b>Figures show the wrong currency symbol.</b> Check the currency here and that MWK exists in the Currencies reference list.</li><li><b>Audit timestamps look hours out.</b> The timezone here, or the server clock. Both matter; the Installation Guide covers the server side.</li></ul>',
            'steps' => [
                'Open Settings, then System.',
                'Confirm Currency is MWK and Timezone is the local zone.',
                'Confirm Allow Member Self Registration is off and Site Online is on.',
                'Save, then check a dashboard figure carries the right currency label.',
            ],
            'images' => [
                'settings-system' => 'System preferences: currency, timezone, the online switch and the licence key',
            ],
            'routes' => ['settings.system'],
        ],

        'Email' => [
            'body' => '<p><b>Settings</b>, then <b>Email</b>, configures outbound mail. The platform sends password resets and notification mail; it does not send bulk mail to customers.</p><h4>Field by field</h4><ul><li><b>Mailer</b>. The transport, normally <code>smtp</code>.</li><li><b>Mail Host</b> and <b>Mail Port</b>. The relay MAIIC ICT gives you, commonly port 587.</li><li><b>Mail Username</b> and <b>Mail Password</b>. The relay credentials.</li><li><b>Mail Encryption</b>. Usually <code>tls</code> on port 587.</li><li><b>Mail From Address</b> and <b>Mail From Name</b>. What recipients see. Use a monitored MAIIC address and a recognisable name such as MAIIC IFRS 9, so a password-reset mail is not mistaken for phishing.</li><li><b>Sendmail</b>. The sendmail path, used only when the mailer is sendmail rather than SMTP.</li></ul><h4>What happens next</h4><p>Test immediately by using <b>Forgot password?</b> on the login page for your own account. If nothing arrives, the relay is usually blocking the server rather than the settings being wrong; the Installation Guide has the command-line test.</p><h4>Common problems</h4><ul><li><b>Nothing sends and nothing errors.</b> The mail is queued and the queue worker is not running. Ask ICT.</li><li><b>Mail lands in junk.</b> The from-address domain does not match the sending server. That is a mail-infrastructure fix, not a platform setting.</li></ul>',
            'steps' => [
                'Get the relay host, port, credentials and encryption from MAIIC ICT.',
                'Open Settings, then Email, and enter them.',
                'Set Mail From Address to a monitored MAIIC address and Mail From Name to MAIIC IFRS 9.',
                'Save, then use Forgot password? on the login page to send yourself a test.',
            ],
            'images' => [
                'settings-email' => 'The outbound mail settings',
            ],
            'routes' => ['settings.email'],
        ],

        'Reference data' => [
            'body' => '<p>Reference data must be complete <b>before</b> loan book snapshots are loaded, because the imports attach each loan to a portfolio and a sector, and the reports group by them. Fixing it afterwards means reloading data.</p><h4>The lists and what they drive</h4><ul><li><b>Currencies</b>. Holds MWK and marks it as the organisation currency. Labels every figure.</li><li><b>Loan Portfolios</b> (under Portfolio Setup). The segments MAIIC reports on: MAIIC core, FInES, Mega Farm and the derived agricultural portfolios. Every stage table, roll-forward and reconciliation is produced by portfolio, and the ECL calculation can be scoped to one.</li><li><b>Sector Types</b>. The economic sectors behind the sector concentration report, which must match the sector disclosure in the audited annual report.</li><li><b>Product Groups</b>. The lending product families behind the ECL by Product Group report.</li><li><b>Chart of Accounts</b> and <b>Branches</b>. Organisation structure used for grouping.</li><li><b>Legal Types</b> and <b>Banks</b>. Supporting lists on client records.</li></ul><h4>Each list behaves the same way</h4><p>A table with a search filter, a create button, and green eye, gold pencil and red bin row actions. Deleting asks for confirmation. A record already referenced by loans cannot be removed cleanly, so rename rather than delete when a segment is reorganised.</p><h4>What happens next</h4><p>Changing a portfolio name changes it everywhere it is reported, including in periods already closed. That is usually what you want, but say so when circulating a report that now reads differently from last month\'s.</p><h4>Common problems</h4><ul><li><b>Loans arrive with no portfolio.</b> The portfolio did not exist or the mapping did not name it. Create it and re-import.</li><li><b>A sector report has an Unmapped row.</b> Loans carry a sector value with no matching sector type. Add the missing type.</li></ul>',
            'steps' => [
                'Open Settings, then Organisation, and follow the Currencies link. Confirm MWK is present and is the organisation currency.',
                'Open Portfolio Setup, then Loan Portfolios, and confirm the segments MAIIC reports on all exist.',
                'Open Sector Types and check them against the sector disclosure in the latest annual report.',
                'Only once all three are right, load loan book snapshots.',
            ],
            'images' => [
                'currencies' => 'The currency list with the organisation currency',
                'sector-types' => 'Sector types, which drive the concentration reports',
                'chart-of-accounts' => 'The chart of accounts',
                'branches' => 'Branches, the organisation structure used for grouping',
                'legal-types' => 'Legal types, a supporting list on client records',
                'banks' => 'Banks, a supporting list on client records',
            ],
            'routes' => ['currencies.index', 'chart_of_accounts.index', 'branches.index', 'industry_types.index', 'portfolios.index', 'groups.index', 'legal_types.index', 'banks.index'],
        ],

        'SMS and other settings' => [
            'body' => '<p>Two pages sit at the foot of the settings hub. Both are short, and both are worth understanding because what they do is easy to overstate.</p>'
                . '<h4>Settings, then SMS</h4>'
                . '<ul>'
                . '<li><b>SMS Enabled</b>, a <b>Yes</b> or <b>No</b> selector.</li>'
                . '<li><b>Default SMS Gateway</b>, which appears only when SMS Enabled is set to Yes, and lists the gateways the installation knows about.</li>'
                . '<li>A <b>Save</b> button.</li>'
                . '</ul>'
                . '<p>Be clear about what this does today: it stores the two values. Nothing in the IFRS 9 workflow sends a text message. Notifications go to the bell in the header and are held in the database, as described under Notifications. Turning SMS on therefore changes a stored setting and nothing a user will see. Leave it off unless Dupleix has delivered a gateway integration for MAIIC and told you to enable it.</p>'
                . '<h4>Settings, then Other</h4>'
                . '<p>This page holds a single link, <b>Loan Application Score Bands</b>, marked with a cogs icon. It opens a small screen where scoring bands are defined by name with a minimum and a maximum, and the platform refuses bands that overlap.</p>'
                . '<p>It belongs to the legacy loan-application credit-scoring feature, which is not part of the IFRS 9 and EIR scope MAIIC contracted for. Removing that feature is logged as ticket #009. Until it is removed the page stays visible, so treat it as dormant: changing a band affects nothing in the ECL or EIR figures.</p>'
                . '<h4>Common problems</h4>'
                . '<ul>'
                . '<li><b>SMS is switched on but no messages arrive.</b> Expected. There is no sending integration; only the setting is stored.</li>'
                . '<li><b>The Other page looks empty.</b> It holds one link. That is the whole page.</li>'
                . '<li><code>Bands overlap.</code> Two score bands claim the same value. Adjust the minimum or maximum so the ranges meet without overlapping.</li>'
                . '</ul>',
            'steps' => [
                'Open Settings, then SMS, and leave SMS Enabled set to No unless Dupleix has delivered a gateway for MAIIC.',
                'Open Settings, then Other, to see the one remaining legacy link.',
                'Raise a ticket rather than configuring the score bands; the feature is outside the contracted scope and is scheduled for removal.',
            ],
            'images' => [
                'settings-sms' => 'The SMS settings page with the enable selector and the gateway list',
                'settings-other' => 'The Other settings page and its single legacy link',
            ],
            'routes' => ['settings.sms', 'settings.other'],
        ],

        'Licence' => [
            'body' => '<p><b>Settings</b>, then <b>Licence</b>, holds the licence record for this installation. The page shows <b>Licensed To</b>, <b>Product</b>, <b>Package</b>, <b>Start Date</b> and <b>Expires</b>, with an action to verify the record.</p><h4>What it is and is not</h4><p>Under the implementation agreement MAIIC owns the installed system, its source code and its data in perpetuity. The licence record identifies the installation for support purposes. It is not a switch that turns the platform off, and an expired record does not stop anyone working.</p><h4>Field by field</h4><ul><li><b>Licensed To</b>. The organisation, which should read MAIIC.</li><li><b>Product</b> and <b>Package</b>. Which Dupleix platform and which commercial option. MAIIC holds the outright purchase option, not the monthly subscription.</li><li><b>Start Date</b> and <b>Expires</b>. The support window. The ninety-day warranty runs from go-live; support after that is arranged separately.</li></ul><h4>When you would use it</h4><ul><li>Quoting the installation details when raising a support request.</li><li>Answering an auditor who asks what the organisation is entitled to run and who supports it.</li></ul><h4>Common problems</h4><ul><li><b>The page returns a permission error.</b> The licence permissions must be seeded. If nobody can open it, raise a ticket.</li><li><b>The details are wrong or missing.</b> Raise a ticket with Dupleix; the record is issued at handover.</li></ul>',
            'images' => [
                'license' => 'The licence record for this installation',
            ],
            'routes' => ['license.index'],
        ],

    ],

];
