<?php

/**
 * Administrator Manual chapter 5 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * fail-closed imports, the fee-only permission, the reporting period lock, the registers and the E-Banker packs).
 */
return [

    'Data Operations' => [

        'Import governance' => [
            'body' => '<p>Every figure the platform reports rests on imported data, so the import record is the first thing an auditor asks about. <b>Data foundation</b>, then <b>Customer &amp; Loan Data</b>, then <b>Imports &amp; Feeds</b>, tab <b>Imports</b>, lists every file uploaded: loan book, clients, collateral, credit loss data and EIR data. The E-Banker packs have their own tabs and their own article.</p>'
                . '<h4>What you see on the Imports tab</h4><ul>'
                . '<li>Buttons at the top right: <b>Import loan book</b>, <b>Import clients</b>, <b>Import collateral</b>.</li>'
                . '<li>Filters: <b>File name</b> (<b>Search by file name</b>), <b>Status</b> (<b>All statuses</b>, <b>Pending</b>, <b>Processing</b>, <b>Completed</b>, <b>Failed</b>) and <b>Clear</b>.</li>'
                . '<li>The table: <b>File name</b>, <b>Kind</b> (Loan book, Clients, Collateral, Credit loss, EIR intake), <b>Status</b>, <b>Uploaded</b>, <b>Records inserted</b>, <b>Failed rows</b>, <b>Ran</b>, <b>Actions</b>. The download icon, <b>Download the failed rows</b>, appears where rows were refused.</li>'
                . '</ul>'
                . '<h4>Every import is fail-closed</h4><p>Each import screen carries the same instructions panel, <b>How to import</b>: the numbered steps, the notes, and a row of buttons above the column table:</p><ul>'
                . '<li><b>Show the</b> N <b>columns (</b>N <b>required)</b>. Opens the column table: <b>Column</b>, <b>Type</b>, <b>Required</b> or <b>Optional</b>, <b>Format and allowed values</b>, <b>Description</b>, <b>Example</b>. For a machine-made pack it lists the files and sheets read instead.</li>'
                . '<li><b>Download sample file</b> (or <b>Download sample manifest</b> for a pack). A file in exactly the format the import reads.</li>'
                . '<li><b>Download column guide</b>. Every column with its type, whether it is required, its format and allowed values.</li>'
                . '<li><b>Check file</b>, once a file is chosen on an import that can be checked on its own. It reads the file and lists every problem by row; nothing is saved.</li>'
                . '</ul>'
                . '<p>The result is a table of <b>Row</b>, <b>Column</b>, <b>Problem</b> and <b>How to fix it</b>, headed <b>The file passed the check</b>, <b>The file passed the check, with warnings</b>, <b>The file has problems: fix the rows below before importing</b>, or, when an import itself was refused, <b>The file was refused: fix the rows below and choose it again</b>. A refused file writes nothing. That is the point: a partial load never reaches the figures.</p>'
                . '<h4>Fees: the fee-only permission and the fee GL check</h4><ul>'
                . '<li>Finance can load fees by loan on <b>EIR Data Intake</b> (the fees type only) with the <code>eir.fees.import</code> permission, without the broad <code>settings</code> right. The same permission lets them read Fee Classification and its fee GL check. Classifying and reviewing still need <code>settings</code>.</li>'
                . '<li>The governed setting <b>Fee imports checked against the fee GLs</b> (recommended on) sets every fee held for each year a fee file covers beside the trial balance movement of the fee income GLs, month by month and for the year, inside the reconciliation band. The result shows on the import and on Fee Classification. It reports and never blocks; when the setting is off, the screens say so.</li>'
                . '</ul>'
                . '<h4>What to check after every import</h4><ol>'
                . '<li>Records inserted against the source row count. Every source record must be imported or listed on an agreed exceptions report.</li>'
                . '<li>The failed-rows file, if any; each refused row carries its reason.</li>'
                . '<li>The aggregate value against the source control total; the contract tolerance is 0.1 per cent.</li>'
                . '</ol>'
                . '<p>Keep the exceptions file. Agreeing it with the data owner and filing it is what turns a partial import into an auditable one.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>No file has been imported yet. Use Import loan book (top right) to upload the first one.</b></li>'
                . '<li><b>No import matches these filters.</b></li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>An import sits at Pending.</b> The queue worker is not running. That is an ICT issue; nothing is lost.</li>'
                . '<li><b>Check file is not offered.</b> No file is chosen yet, or that import is checked as part of the load itself.</li>'
                . '<li><b>Fees will not classify after a fee-only import.</b> Expected: a person with <code>settings</code> classifies and a second reviews.</li>'
                . '</ul>',
            'steps' => [
                'Open Imports & Feeds and the Imports tab after every load.',
                'On any import, download the sample file and the column guide before preparing the file.',
                'Choose the file and press Check file; fix every row it lists.',
                'Import, then compare Records inserted with the source row count.',
                'Download the failed rows, agree the exceptions with the data owner and file them.',
                'For a fee file, read the fee GL check on the import and on Fee Classification.',
            ],
            'images' => [
                'imports' => 'The Imports tab with statuses, counts and the failed-rows download',
                'import-modal' => 'The instructions panel of an import: the sample file, the column guide and Check file',
            ],
            'routes' => ['imports.index'],
        ],

        'Locks and recalculation' => [
            'body' => '<p>Results that have been reviewed are locked so they cannot drift. Knowing which locks exist, and what each prevents, saves a great deal of confusion at month end.</p>'
                . '<h4>The locks</h4><ul>'
                . '<li><b>Reporting period.</b> The main one. A locked month is never restated by the build, staging, the PD, the LGD, the forward-looking route, the ECL, the revenue roll-forward, write-offs or a staging threshold change. Locked and unlocked with a reason on Imports &amp; Feeds, Periods Built (<code>eir.govern</code>).</li>'
                . '<li><b>Transition matrix</b> and <b>loss given default</b> runs, monthly and cumulative. A closed record is immutable; only a user with the admin role can unlock it (<b>Only an Administrator can unlock a closed PD record</b>, or LGD record). To change an assumption, create a new draft.</li>'
                . '<li><b>Original effective interest rate.</b> Locked when a second person approves it. Reopening needs the admin role and the governed setting <b>Administrator override of maker-checker</b> at <b>Allowed for administrators</b>; at its recommended value, Not allowed, a locked rate cannot be reopened (<b>Only an administrator can reopen a locked original EIR.</b>).</li>'
                . '<li><b>Governed settings.</b> A change applies forward only, from a date later than the last approved change; a month already run keeps its values.</li>'
                . '<li><b>Financial period.</b> A label, not a lock: closing one stops nothing.</li>'
                . '</ul>'
                . '<h4>Recalculation</h4><p>Analysts rerun a calculation from its screen; the month-end job on Maintenance reruns the whole chain for a month. A rerun into an open month replaces that month\'s figures and is recorded in the audit log; a rerun into a locked month is refused. The EIR revenue for a month already run is left alone unless rerun with <code>eir:run-revenue</code> and its <code>--recalculate</code> option, which requires a reason.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A calculation stops saying the period is locked.</b> Expected after sign-off. Unlock only with the approval for the restatement.</li>'
                . '<li><b>An analyst cannot edit a matrix.</b> It is closed. Create a new draft.</li>'
                . '<li><b>Figures changed after sign-off.</b> The month was not locked. Check the Audit Trail for the later run, and lock the month.</li>'
                . '</ul>',
            'images' => [
                'tmatrix' => 'The transition matrix list showing draft, calculated and closed states',
            ],
            'routes' => [],
        ],

        'EIR data governance' => [
            'body' => '<p>The effective interest rate rests on a chain of decisions the platform deliberately refuses to make on its own. Each is a two-person control, and the Workspace tab <b>Awaiting approval</b> counts every one that waits.</p>'
                . '<h4>1. Accounting rules</h4><p><b>EIR &amp; Revenue Recognition</b>, <b>EIR Rules</b>, <b>Accounting Rules</b> (<code>settings</code>). Rules match fee lines and propose a treatment, integral to the EIR or not. A rule never fires until approved, and editing a rule resets its approval. Approving one is an accounting judgement: read the rationale and the IFRS 9 paragraph it cites.</p>'
                . '<h4>2. Fee classification and held GL lines</h4><p><b>EIR Rules</b>, <b>Fee Classification</b>. A classifier records the treatment of each fee line and a different person reviews it (<code>settings</code>); only reviewed, integral lines enter the EIR. The refusal reads <b>A classifier cannot review their own decision.</b> The panel of held GL fee postings lists fee income postings whose batch names no single loan: each is held, never dropped and never guessed. A holder of <code>eir.govern</code> presses <b>Tie this fee line to its loan</b>, enters the <b>Loan (contract or account number)</b> and a <b>Reason (written to the audit log)</b> of at least 10 characters, and the line joins the list for classification and review. The governed setting <b>Which source a contract\'s fees come from</b> decides which source wins where a fee is held in several; the other lines are kept, marked superseded and left out of the EIR.</p>'
                . '<h4>3. Schedule approval</h4><p><b>EIR Data</b>, tab <b>Schedule Review</b>. A generated original schedule is a draft until approved. The governed setting <b>Approving a version 1 schedule</b> decides whether a second person must approve it (recommended) or one person may draft and approve. A schedule that does not reconcile within tolerance to the remaining cash flows needs a review note. Refusals include <b>Only a draft schedule can be approved.</b> and <b>Maker-checker: the person who prepared the schedule cannot approve it</b>.</p>'
                . '<h4>4. Calculation, lock and reopening</h4><p><b>EIR Calculations</b> (<code>settings</code>). A calculated rate is approved and locked by a second person; the refusal is <b>The calculator cannot approve and lock their own EIR.</b> Reopening a locked rate needs a written reason of at least ten characters, archives the locked measurement, supersedes the amortisation that depended on it and marks the discounted ECL stale; it is open only to the admin role while the override setting allows it.</p>'
                . '<h4>5. The write-off register</h4><p><b>EIR Data</b>, tab <b>Write-offs</b>. Anyone with <code>eir.view</code> reads it; proposing and approving need <code>eir.govern</code>. One write-off per contract, for an amount above nil, on a contract in the loan book. <b>The person who proposed a write-off cannot approve it.</b> A write-off dated in a locked month is refused.</p>'
                . '<h4>6. The restructure register</h4><p><b>EIR Data</b>, tab <b>Restructures &amp; Resets</b>. The month-end job finds candidates and rate moves from the E-Banker reschedule history but confirms nothing. A person classifies each (modification, revised estimate, contractual reset, correction, not a change) with a reason, and a second approves: <b>The person who proposed a classification cannot approve it.</b> Proposing and approving need <code>eir.govern</code>. This register is not yet counted on the Workspace Awaiting approval tab; check it directly.</p>'
                . '<h4>Where to see the whole book</h4><p><b>EIR Calculations</b>, tabs <b>Blockers</b>, <b>Coverage by Portfolio</b> and <b>Largest Facilities</b>, answer how much of the book carries a locked rate and what stops the rest, ranked by exposure. That is your work queue.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Everything is blocked on fee classification.</b> Fee lines wait for a reviewer, or GL lines are held. Assign a reviewer and tie the held lines.</li>'
                . '<li><b>A contract is blocked on an unapproved schedule.</b> Approve it on Schedule Review, with a note if it does not reconcile.</li>'
                . '<li><b>A locked rate is wrong and cannot be reopened.</b> The override setting is at Not allowed. Propose the change in the Governance Centre, have it approved, reopen, then propose it back.</li>'
                . '</ul>',
            'steps' => [
                'Approve the accounting rules the accounting owner agrees with, on Accounting Rules.',
                'Tie the held GL fee lines to their loans, then have fees classified by one person and reviewed by another.',
                'Approve each draft schedule on Schedule Review, as a second person, with a note where it does not reconcile.',
                'Approve and lock each calculated rate on EIR Calculations as a second person.',
                'Clear the proposed write-offs and restructure classifications as the second person.',
                'Work down Blockers, ranked by exposure.',
            ],
            'images' => [
                'eir-rules' => 'Accounting rules awaiting approval',
                'eir-fees' => 'Fee classification with the held GL fee lines and the maker and checker control',
                'eir-schedule-review' => 'Schedule Review, where a second person approves a draft schedule',
                'eir-write-offs' => 'The write-off register',
                'eir-restructures' => 'The restructure register: candidates, classifications and approvals',
            ],
            'routes' => [],
        ],

        'E-Banker packs and loan book builds' => [
            'body' => '<p>From October 2026 the system is loaded from the E-Banker tables themselves. A <b>pack</b> is a zip of CSV files, one per E-Banker query, with a <code>manifest.json</code> naming each file\'s query, row count and fingerprint. A pack only lands data; the monthly loan book is written by a <b>build</b>, which a second person approves. This article is the administrator\'s view; the full procedure is in the Installation Guide, section 9.8.</p>'
                . '<h4>Where it is</h4><p><b>Data foundation</b>, <b>Customer &amp; Loan Data</b>, <b>Imports &amp; Feeds</b>, tabs <b>E-Banker Loads</b>, <b>Loan Book Builds</b>, <b>Periods Built</b> and <b>Query Register</b> (and <b>Take-on Blocks</b>, <b>Take-on Population</b> for the take-on workbook). Reading needs <code>eir.view</code>; loading a pack, proposing and approving a build and locking a period need <code>eir.govern</code>.</p>'
                . '<h4>The three routes a pack can arrive by</h4><ul>'
                . '<li><b>Uploaded</b> on E-Banker Loads with <b>Load a pack</b>. Tick <b>Check only: run the checks and keep nothing</b> to test a pack first.</li>'
                . '<li><b>The feed folder.</b> The scheduler lands every new pack found there each day at 06:30 (<code>eir:poll-feed-folder</code>); Maintenance, <b>Fetch new data</b>, does the same on demand.</li>'
                . '<li><b>Posted by the export script</b> over HTTPS with a token; it waits in an inbox and the same daily run lands it.</li>'
                . '</ul>'
                . '<p>Whatever the route, every file is checked against the manifest before anything is kept: row counts, accounts against the account master, balances against the ledger, the month-end rows and the date format. A pack that fails a check is quarantined with the file and row named, and nothing reads it. Which route is in force is the governed setting <b>How E-Banker data arrives: the feed route</b>.</p>'
                . '<h4>Builds</h4><p>On <b>Loan Book Builds</b>, <b>Propose a build</b> chooses the months and, unless there is a reason, leaves the method on the one in force (the governed setting <b>How the monthly loan book is built</b>: <b>B: derived from the ledger</b> by default, or <b>A: from the stored run</b>). The proposal shows, month by month, what would change against the loan book held now. A second person presses <b>Approve and build</b>; the requester cannot (<b>Maker-checker: the approver must be a different person from the requester.</b>). A locked month is never restated, and a month the landing zone does not cover is refused, never estimated. Calculations already run for a month are not rerun by the build; run the month-end again after it.</p>'
                . '<h4>The commands, at a high level</h4><ul>'
                . '<li><code>php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify</code>. A clean install from the committed client inputs. Anything it approves carries the label System Bootstrap (automated data-readiness, not a MAIIC approval). On Maintenance it is <b>Bootstrap the system (first install)</b> and wipes the database.</li>'
                . '<li><code>php artisan eir:land-pack</code> a pack folder <code>--user=</code>id. Lands one pack through its gates.</li>'
                . '<li><code>php artisan eir:land-takeon --user=</code>id. Lands the take-on workbook and builds the take-on population.</li>'
                . '<li><code>php artisan eir:poll-feed-folder</code>. Lands every new pack in the feed folder.</li>'
                . '<li><code>php artisan eir:build-loan-books</code> from to. Builds the loan book months under maker-checker.</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A pack is quarantined.</b> Open the load\'s checks on E-Banker Loads; correct the named file or manifest entry and load it again.</li>'
                . '<li><b>A build is refused for the last month.</b> The landing zone does not reach that month-end yet. Land the next pack.</li>'
                . '<li><b>The build method shows no value.</b> The governed setting has no approved value; set it in the Governance Centre.</li>'
                . '</ul>',
            'steps' => [
                'Confirm the month\'s pack has landed on E-Banker Loads with every check passed.',
                'On Loan Book Builds, press Propose a build and choose the months.',
                'Read the month-by-month differences in the proposal.',
                'A second person presses Approve and build.',
                'Run the month-end calculations for the month again after any rebuild.',
            ],
            'images' => [
                'eir-feed-loads' => 'E-Banker Loads: every pack with its checks, quarantine and watermarks',
                'eir-feed-builds' => 'Loan Book Builds: proposals and approvals',
            ],
            'routes' => [],
        ],

    ],

];
