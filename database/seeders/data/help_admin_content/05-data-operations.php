<?php

/**
 * Administrator Manual chapter 5 (Ticket #011 depth rewrite).
 */
return [

    'Data Operations' => [

        'Import governance' => [
            'body' => '<p>Every figure the platform reports rests on imported data, so the import record is the first thing an auditor asks about. <b>Customer and Loan Data</b>, then <b>Imports</b>, is the history.</p><h4>What you see on the screen</h4><ul><li>A search filter.</li><li>The table listing each import with its name, status, the number of records loaded, the number that failed, and a download for the failed-rows file where one exists.</li><li>Statuses: <b>pending</b> (queued, not started), <b>processing</b> (running), <b>completed</b>, <b>failed</b>.</li></ul><h4>What to check after every import</h4><ol><li>The record count against the source file. Contract Schedule 3 requires record counts to reconcile fully, with every source record either imported or listed on an agreed exceptions report.</li><li>The failed-rows file, if any. Each rejected row carries its reason, usually a missing column or a value that is not a number.</li><li>The aggregate value against the source control total. The contract tolerance is 0.1 per cent.</li></ol><p>Keep the exceptions file. Agreeing it with the data owner and filing it is what turns a partial import into an auditable one.</p><h4>Governance points</h4><ul><li>Imports run on a queue, so a job sitting at <b>pending</b> means the worker is not running. That is an ICT issue, not a data one, and nothing is lost.</li><li>EIR extract imports do not duplicate on re-run, because each row carries a source identifier.</li><li>Every import is written to the audit trail with who ran it and what it produced.</li></ul><h4>Known data hazards on the MAIIC extracts</h4><p>These are recorded in the Technical Manual and repeated here because they change what you should accept:</p><ul><li>Typed Excel dates in the contract master extract have the day and month swapped; text dates in the same columns are correct.</li><li>The extended GL interest extract stacks three generation runs. Loading it whole triple-counts a year of interest.</li><li>Trial balance profit and loss figures are cumulative year to date and reset each January. A monthly figure is the difference between consecutive months.</li></ul><h4>Common problems</h4><ul><li><b>The import completed but the loan book looks short.</b> Compare the records count with the source; the difference will be in the failed-rows file.</li><li><b>A grouped E-Banker import reports failures but offers no file.</b> That path records only a count. Reconcile against the source row count and raise a ticket if the gap is material.</li></ul>',
            'steps' => [
                'Open Customer and Loan Data, then Imports, after every load.',
                'Compare the records count with the source file row count.',
                'Download the failed-rows file and read the reason on each row.',
                'Agree the exceptions with the data owner and file the document.',
                'Check the Loan Book totals for the period against the core banking control total.',
            ],
            'images' => [
                'imports' => 'Import history with statuses, counts and the failed-rows download',
            ],
            'routes' => ['imports.index'],
        ],

        'Locks and recalculation' => [
            'body' => '<p>Results that have been reviewed are locked so they cannot drift. Knowing which locks exist, and what each one prevents, saves a great deal of confusion at month end.</p><h4>The locks</h4><ul><li><b>Transition matrix</b>, monthly and cumulative. A locked matrix is immutable. To change an assumption, create a new draft; do not try to edit the locked one.</li><li><b>Loss given default</b>, monthly and cumulative. A closed run is immutable in the same way.</li><li><b>ECL run</b>. A lock is taken per period and scope while a calculation runs, so two people cannot write the same figures at once. It clears when the run finishes.</li><li><b>Financial period</b>. Closing prevents work being attributed to the period.</li><li><b>Original effective interest rate</b>. Locked after a second person approves it. Reopening is administrator-only and described in its own article.</li></ul><h4>Applying a model result</h4><p>Locking a matrix is not the same as using it. <b>Apply</b> or <b>Book</b> writes its probabilities onto the loan book, which is what the ECL run reads. A matrix that is calculated and locked but never applied has no effect on any figure.</p><h4>Recalculation</h4><p>Analysts rerun the ECL calculation from the screen. Developers can rerun a period from the command line with the same logic, which is used when a correction has to be applied across several months. Every run is recorded in the audit trail, so a recalculated month is visible rather than silent.</p><h4>Common problems</h4><ul><li><b>An ECL calculation is already running.</b> Another run holds the lock. Wait. If a run was interrupted and the message will not clear, ask Dupleix to release the lock.</li><li><b>An analyst cannot edit a matrix.</b> It is locked. Create a new draft.</li><li><b>Figures changed after sign-off.</b> Check the audit trail for a later run, and check whether the financial period was closed. Closing the period is what prevents this.</li></ul>',
            'images' => [
                'tmatrix' => 'The transition matrix list showing draft, calculated and locked states',
                'ecl' => 'The ECL results, written by a calculation run',
            ],
            'routes' => ['transition-matrices.index', 'loss-given-default.index', 'expected-credit-loss.index'],
        ],

        'EIR data governance' => [
            'body' => '<p>The effective interest rate pipeline has four control points, and each is yours. The platform deliberately refuses to decide any of them on its own, which is what makes the result defensible.</p><h4>1. Accounting rules</h4><p><b>EIR and Revenue Recognition</b>, then <b>Accounting Rules</b>. Rules match fee lines and propose a treatment: integral to the effective interest rate, or not. Every rule is seeded unapproved, and an unapproved rule never fires. Approving one is an accounting judgement, so read the rationale and the IFRS 9 paragraph it cites before you approve. Editing a rule resets its approval, which is intentional: a changed rule is a new decision.</p><h4>2. Fee classification</h4><p><b>Fee Classification</b>. A classifier records the treatment of each fee line; a different person reviews it. Only lines that are reviewed and integral enter the calculation. The platform refuses a same-person review with <b>A classifier cannot review their own decision.</b> An administrator can override, and the override is recorded.</p><h4>3. Schedule approval</h4><p><b>EIR Data</b>, then the <b>Schedules</b> tab. The original contractual schedule is either imported or generated from the contract terms, and a generated one is a draft until approved. The platform compares it against the remaining cash flows delivered by the core banking system and reports whether they reconcile within one per cent. Where they do not, approval demands a written review note. It refuses with <b>Only a draft schedule can be approved.</b> if the schedule is already approved.</p><h4>4. Calculation, lock and reopening</h4><p><b>EIR Calculations</b>. A calculated rate is approved and locked by a second person; the refusal is <b>The calculator cannot approve and lock their own EIR.</b> A locked rate is final.</p><p><b>Reopening</b> is administrator-only and consequential. It requires a written reason of at least ten characters, archives the locked measurement to history, supersedes every amortisation row that depended on it, and marks the discounted ECL as stale. The contract must then be recalculated and independently approved again. Do not reopen to tidy something up; reopen when the locked rate is wrong.</p><h4>Where to see the state of the whole book</h4><p><b>Coverage and Blockers</b> answers the portfolio question: how much of the book carries a locked rate, and what is stopping the rest. Blockers are ranked by exposure and flagged where clearing one blocker alone would release a contract. That is your work queue.</p><h4>Common problems</h4><ul><li><b>Everything is blocked on fee classification.</b> Fee lines are waiting for a reviewer. Assign one.</li><li><b>A contract is blocked on an unapproved schedule.</b> Approve it on the Schedules tab, with a note if it does not reconcile.</li><li><b>Coverage is low and nobody knows why.</b> Read the blocker ranking rather than guessing; it names the reason for every contract.</li></ul>',
            'steps' => [
                'Open EIR and Revenue Recognition, then Accounting Rules, and approve the rules the accounting owner agrees with.',
                'Open Fee Classification and make sure a second person reviews what the classifier recorded.',
                'Open EIR Data, then Schedules, and approve each draft schedule, adding a note where it does not reconcile.',
                'Open EIR Calculations and approve and lock each calculated rate as a second person.',
                'Open Coverage and Blockers and work down the blockers ranked by exposure.',
            ],
            'images' => [
                'eir-rules' => 'Accounting rules awaiting approval',
                'eir-fees' => 'Fee classification with the maker and checker control',
                'eir-calculations' => 'EIR calculations with approve, lock and reopen',
                'eir-coverage' => 'Coverage and blockers ranked by exposure',
            ],
            'routes' => ['eir-accounting-rules.index', 'eir-fee-classification.index', 'eir-data.index', 'eir-calculations.index', 'eir-coverage.index'],
        ],

    ],

];
