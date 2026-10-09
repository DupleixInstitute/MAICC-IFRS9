<?php

namespace App\Services\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Models\GovernanceSetting;
use App\Models\GovernanceSettingHistory;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The Governance Centre (spec v3 section 8, decision D17).
 *
 * Every convention the EIR engine uses is a setting with a fixed list of
 * options, an effective date, a proposer and an approver. The value in force
 * on a date is the latest APPROVED row whose effective_from is on or before
 * that date. A change applies forward only: it must start later than the
 * change before it, so a locked period keeps the settings it was locked
 * under. No value is ever defaulted in code; a key with no approved value
 * raises GovernanceSettingMissingException and the calculation stops.
 */
class GovernanceService
{
    /** Resolved values, keyed "key|date", so a loop over contracts asks once. */
    private array $memo = [];

    /**
     * The twelve conventions of spec v3 section 8, then the open choices of
     * section 4, in the order the screen shows them. The default is Dupleix's
     * recommendation; the seeder writes it as the first APPROVED row and the
     * engine reads only the database.
     *
     * @return array<string, array{label:string, description:string, options:list<string>, default:string}>
     */
    public static function catalogue(): array
    {
        return [
            'plr_mid_period' => [
                'label' => 'PLR change inside a month',
                'description' => 'When the Reserve Bank prime lending rate changes part-way through a month, this decides how the new rate reaches a floating loan\'s contractual interest. E-Banker\'s own ledger (narrations of 2,047 interest postings, 7 October 2026) charges the whole calendar month, 1st to month-end, at the rate on the account when the month-end runs: in 217 of the 229 months where the rate changed the full month was charged at the new rate, in 2 at the old rate, and in no month was the period split. The default reproduces that; the other two options are the alternatives the specification listed. Open choice O2.',
                'options' => ['Whole month at the month-end rate (E-Banker)', 'Pro rata from the effective date', 'From the next instalment date'],
                'default' => 'Whole month at the month-end rate (E-Banker)',
            ],
            'reset_trigger' => [
                'label' => 'When a floating loan\'s EIR is re-solved',
                'description' => 'A floating loan gets a fresh effective interest rate whenever its reference rate changes (IFRS 9 B5.4.5). This decides the date on which the engine re-solves it. Agreed as decision D8.',
                'options' => ['At the PLR effective date', 'At the next instalment date', 'At month-end'],
                'default' => 'At the PLR effective date',
            ],
            'moratorium_capitalisation' => [
                'label' => 'How a "Both" moratorium compounds',
                'description' => 'During a moratorium of type Both (interest and principal deferred) the interest is added to the balance. This decides how often. E-Banker\'s own postings show monthly compounding on every moratorium loan in the sample (decision D10).',
                'options' => ['Monthly at posting', 'At the instalment frequency'],
                'default' => 'Monthly at posting',
            ],
            'rate_source_precedence' => [
                'label' => 'Which source decides whether a loan reprices',
                'description' => 'Three sources can say whether a loan follows the prime rate: the Interest Policy code held in E-Banker, the rate movements observed in the monthly loan books, and the product family. This sets the order in which the engine trusts them. Open choice O3.',
                'options' => ['Interest Policy, then observed, then product family', 'Observed behaviour first', 'Product family only'],
                'default' => 'Interest Policy, then observed, then product family',
            ],
            'margin_basis' => [
                'label' => 'Where the spread over prime comes from',
                'description' => 'The spread added to the prime rate (margin) can be derived from the rate live at drawdown (LIVE_AT_DRAWDOWN), taken as captured in E-Banker (AS_CAPTURED), or supplied by MAIIC in the contract master (SUPPLIED). Open choice O1; it depends on the answer about the reference-rate refresh button.',
                'options' => ['LIVE_AT_DRAWDOWN', 'AS_CAPTURED', 'SUPPLIED'],
                'default' => 'LIVE_AT_DRAWDOWN',
            ],
            'stage3_interest_basis' => [
                'label' => 'Interest on Stage 3 loans',
                'description' => 'For a credit-impaired (Stage 3) loan, IFRS 9 5.4.1(b) applies the EIR to the amortised cost net of the loss allowance. The alternative accrues on the gross amount and unwinds the allowance separately. Agreed as decision D11.',
                'options' => ['Net carrying amount', 'Gross with allowance unwind'],
                'default' => 'Net carrying amount',
            ],
            'day_count' => [
                'label' => 'Day count',
                'description' => 'How a period\'s interest is measured: actual days over 365 (ACT/365, E-Banker\'s own convention, reproduced to the cent) or thirty-day months over 360 (30/360). Agreed as decision D9.',
                'options' => ['ACT/365', '30/360'],
                'default' => 'ACT/365',
            ],
            'period_rate_basis' => [
                'label' => 'Rate for a quarterly or annual period',
                'description' => 'How a schedule charges a period longer than a month. E-Banker adds each month\'s interest to the balance and charges the next month on it, so a quarter is (1 + rate/12) cubed, less one; JAT Group\'s first quarterly instalment of 28,624,982 reproduces to 23 tambala on this basis and is 341,296 short on the annual rate divided by four. Monthly loans are the same under either option.',
                'options' => ['Monthly compounded (E-Banker)', 'Annual rate divided by payments a year'],
                'default' => 'Monthly compounded (E-Banker)',
            ],
            'cash_source' => [
                'label' => 'Where actual cash received comes from',
                'description' => 'The cash a customer paid in a month can be read from the monthly Loan Book Report (the increase in the cumulative Repayments column), from the transaction ledger (Extract B), or assumed from the contractual schedule. Agreed as decision D14.',
                'options' => ['Loan book (change in Repayments)', 'Extract B (transaction ledger)', 'Contractual schedule'],
                'default' => 'Loan book (change in Repayments)',
            ],
            'manual_policy_handling' => [
                'label' => 'Loans with Interest Policy = Manual',
                'description' => 'Some accounts carry an Interest Policy of Manual, so E-Banker does not reprice them on its own. The engine can read their rate every month and treat each change as a reset, or treat them as fixed for life. Open choice O4.',
                'options' => ['Read the rate monthly, each change is a reset', 'Treat as fixed'],
                'default' => 'Read the rate monthly, each change is a reset',
            ],
            'modification_threshold' => [
                'label' => 'The derecognition test',
                'description' => 'When a loan is restructured, the present value of the new cash flows at the original EIR is compared with the old carrying amount. A change beyond this threshold means the old loan is derecognised and a new one recognised; below it, the difference is a modification gain or loss. Open choice O16.',
                'options' => ['10 percent', '5 percent', '15 percent'],
                'default' => '10 percent',
            ],
            'recon_tolerance' => [
                'label' => 'When a difference is an exception',
                'description' => 'The reconciliation compares the engine\'s interest with what the ledger posted, account by account and month by month. A difference inside this band is treated as agreeing; outside it, the row is an exception that has to be explained by a named cause. The band is a share of the amount posted with a floor in kwacha, so that near-zero postings do not raise false exceptions. Open choice O11.',
                'options' => [
                    '1 percent of the posted amount, floor MWK 1',
                    '0.5 percent of the posted amount, floor MWK 1',
                    '100 basis points on the EIR and MWK 1 per account-month',
                    'MWK 100 per account-month, whatever the amount posted',
                ],
                'default' => '1 percent of the posted amount, floor MWK 1',
            ],
            'counter_reset_handling' => [
                'label' => 'A Repayments counter that falls',
                'description' => 'The loan book\'s Repayments column is cumulative and should only rise. When it falls (a restructure, a settlement or a data reset), this decides what the engine does with that month\'s cash: treat it as a discontinuity and take cash from Extract B, treat it as a settlement, or hold the account for manual review. Open choice O5.',
                'options' => ['Discontinuity: take cash from Extract B', 'Settlement', 'Manual review'],
                'default' => 'Discontinuity: take cash from Extract B',
            ],

            // The open choices of spec v3 section 4 that were not yet settings
            // (added 7 October 2026). Each is seeded with Dupleix's
            // recommendation so that Dr Thom can confirm or change it in the
            // Governance Centre when he is ready, with the same maker-checker
            // and effective date as every other convention. An option is at
            // most 60 characters: that is the width of the value column.
            'contractual_record' => [
                'label' => 'Which record is the contractual one',
                'description' => 'The terms, rates, fees and cash flows the engine calculates from. Decided 7 October 2026 (O19): the E-Banker system record governs; the signed offer letter is evidence, and a difference between the two is reported to Credit as a control exception, not booked as a modification. Dr Thom\'s written confirmation to be filed.',
                'options' => ['E-Banker system record governs', 'Signed offer letter governs'],
                'default' => 'E-Banker system record governs',
            ],
            'partly_drawn_interest_basis' => [
                'label' => 'Interest basis on a partly drawn facility',
                'description' => 'E-Banker can charge interest on the sanctioned amount or on the drawn balance, and the instalment likewise. The flag is now supplied per account (loan master and scheme settings). Open choice O6.',
                'options' => ['Per-account flag, then the scheme, else refuse', 'Balance-wise everywhere, flag exceptions'],
                'default' => 'Per-account flag, then the scheme, else refuse',
            ],
            'expected_cashflow_basis' => [
                'label' => 'Which schedule is the expected cash flow',
                'description' => 'The origination system\'s schedule starts accrual on the 1st of the month and may carry a different rate; the core account accrues from the disbursement day at the core rate. This decides which one the engine expects cash against. Open choice O7.',
                'options' => ['Core dates and rate; LOS schedule is reference only', 'LOS schedule as issued; difference is a modification'],
                'default' => 'Core dates and rate; LOS schedule is reference only',
            ],
            'history_before_dec_2025' => [
                'label' => 'Loan books before December 2025',
                'description' => 'Where the engine takes the monthly loan book for the months before the Excel reports begin. The stored loan book history (query P2_08) holds every month-end from December 2024; the months from the July 2024 take-on to November 2024 are rebuilt from the balance history and the ledger. Open choice O8.',
                'options' => ['Stored loan book history, every month-end', 'Ledger and balance history, disclosed', 'Start at December 2025 and disclose'],
                'default' => 'Stored loan book history, every month-end',
            ],
            'ebanker_feed_route' => [
                'label' => 'How E-Banker data arrives: the feed route',
                'description' => 'Every route produces the same pack (the extract files plus a manifest) and enters the landing zone by the same door, so the route can be changed at any time and the others stay available for a missed month or a correction (spec v4 section 6.5). Route 1 is Barry running the versioned queries and uploading the files; route 2 a scheduled export at MAIIC to a folder the system polls; route 3 the same script posting the pack to the system; route 4 the system reading E-Banker directly over the VPN with a read-only account; route 5 a vendor view or API. Routes 4 and 5 need Dr Thom and ICT. Open choice O9.',
                'options' => ['Route 1: manual pack upload', 'Route 2: scheduled export to a folder', 'Route 3: push by API', 'Route 4: direct read over the VPN', 'Route 5: vendor view or API'],
                'default' => 'Route 1: manual pack upload',
            ],
            'loan_book_build_method' => [
                'label' => 'How the monthly loan book is built',
                'description' => 'All three methods read the landing zone and pass its gates, and the method used is recorded on every row (spec v4 section 6.2). Method A copies the stored Loan Book Report\'s latest run for each month as it is. Method B derives the row from the ledger and the masters, with E-Banker\'s own arrears fields, and covers every month from the take-on on one basis. Method C is the printed report uploaded as Excel through the importer MAIIC has always used, landed and date-checked. Open choice O8.',
                'options' => ['A: bootstrap of the stored report run', 'B: derived from the ledger', 'C: the printed report importer'],
                'default' => 'B: derived from the ledger',
            ],
            'takeon_history_basis' => [
                'label' => 'Pre-migration history of the take-on loans',
                'description' => 'The 109 loans taken on at 31 July 2024 have no E-Banker history before that date; the amortisation schedules and the fees in the take-on workbook are the only record. This decides whether the engine recomputes each loan\'s EIR from origination on that record, or starts it at the take-on balance with no day-one history. The seeded option uses the history wherever the block and the fees exist and is honest where they do not, and every loan carries the basis it was built on (spec v4 section 6.9).',
                'options' => ['Recompute where block and fees exist, else take-on balance', 'Recompute from origination for every take-on loan', 'Start every take-on loan at its take-on balance'],
                'default' => 'Recompute where block and fees exist, else take-on balance',
            ],
            'trueup_gl_account' => [
                'label' => 'Which GL absorbs the EIR true-up',
                'description' => 'The difference between interest at the effective rate and the contractual interest E-Banker posted has to land in a ledger account when the engine proposes its journals: a dedicated EIR adjustment income account that Finance opens, or the existing interest income account of each product. Open choice O10; needed before the first journal is proposed.',
                'options' => ['Dedicated EIR adjustment income account', 'Interest income account per product'],
                'default' => 'Dedicated EIR adjustment income account',
            ],
            'auditor_export_format' => [
                'label' => 'Shape of the auditor export',
                'description' => 'What the Deloitte download contains: the summary-tab shape agreed in August, or the full worked example with every supporting tab. Open choice O12, with Kundai and Deloitte.',
                'options' => ['Summary-tab shape', 'Full worked example, every tab'],
                'default' => 'Summary-tab shape',
            ],
            'keyman_insurance_treatment' => [
                'label' => 'Keyman insurance charged to the borrower',
                'description' => 'Whether a keyman insurance premium collected with the loan is an integral fee that enters the EIR, or a pass-through to the insurer outside it. One of the Phase 0 sign-offs (O13).',
                'options' => ['Not integral: pass-through, outside the EIR', 'Integral fee: enters the EIR'],
                'default' => 'Not integral: pass-through, outside the EIR',
            ],
            'nascomex_preference_shares' => [
                'label' => 'Nascomex preference shares',
                'description' => 'Whether the Nascomex preference shares are an equity instrument under IAS 32, outside the EIR engine, or a loan at amortised cost inside it. One of the Phase 0 sign-offs (O13).',
                'options' => ['Equity instrument (IAS 32), outside the EIR', 'Loan at amortised cost, inside the EIR'],
                'default' => 'Equity instrument (IAS 32), outside the EIR',
            ],
            'staging_rebuttal' => [
                'label' => 'Rebutting the 30-day Stage 2 presumption',
                'description' => 'IFRS 9 presumes a significant increase in credit risk at 30 days past due (B5.5.11) and default at 90 days (B5.5.37), and lets both be rebutted with reasonable and supportable evidence. The 90-day default presumption is rebutted for medium- and long-term facilities on the Reserve Bank of Malawi\'s Financial Services (Credit Risk Management for Development Finance Institutions) Directive, 2018, which classifies them non-performing from 181 days (short-term and Mega Farm facilities from 91); the governed thresholds carry that. This setting decides whether the 30-day Stage 2 presumption may also be rebutted, with a documented reason approved by a second person, or never. One of the Phase 0 sign-offs (O13).',
                'options' => ['Allowed with documented evidence, approved', 'Never: 30 days past due is Stage 2'],
                'default' => 'Allowed with documented evidence, approved',
            ],
            'rate_change_classification' => [
                'label' => 'Reset or modification',
                'description' => 'A rate move the contract already provides for (the prime rate changes) is a B5.4.5 reset; a rate cut MAIIC negotiates outside the contract for a struggling borrower can be a 5.4.3 modification with a gain or loss. This decides which changes are modifications. Open choice O17; Deloitte\'s written confirmation before the first reset is booked.',
                'options' => ['In-contract moves reset; negotiated changes modify', 'Every rate change is a reset'],
                'default' => 'In-contract moves reset; negotiated changes modify',
            ],
            'maker_checker_admin_override' => [
                'label' => 'Administrator override of maker-checker',
                'description' => 'Whether a user with the administrator role may approve a governance change, lock an EIR or review a fee classification they themselves proposed. The default refuses it: the two-person rule holds for everyone. System audit of 9 October 2026, finding H9: the override was a role check with no governance over it.',
                'options' => ['Not allowed', 'Allowed for administrators'],
                'default' => 'Not allowed',
            ],
            'schedule_approval_control' => [
                'label' => 'Approving a version 1 schedule',
                'description' => 'A generated schedule moves from draft to approved before it is used. This decides whether a second person must approve it, as for a fee classification and the EIR lock, or one person may draft and approve for the first run. Open choice O18.',
                'options' => ['Second person must approve', 'One person may draft and approve'],
                'default' => 'Second person must approve',
            ],
            'historic_materiality_assessment' => [
                'label' => 'The historic materiality assessment',
                'description' => 'MAIIC\'s past method judged the EIR effect immaterial at a threshold the auditors accepted. This decides whether the engine reproduces that assessment beside the detailed calculation (the threshold is then recorded as a governed amount once Finance supplies it) or reports the detailed figure only. Open choice O20, with Deloitte.',
                'options' => ['Reproduce it beside the detailed figure', 'Report the detailed figure only'],
                'default' => 'Reproduce it beside the detailed figure',
            ],
            'fee_reclass_journal' => [
                'label' => 'The year-end fee reclassification journal',
                'description' => 'Finance moves the EIR-related part of fee income into Interest on term loans by a manual journal after year end. This decides whether the engine\'s proposed entries replace that journal, reconciling to it for 2024 and 2025, or both run in parallel for one year. Open choice O21; follows the true-up account.',
                'options' => ['Engine journal replaces the manual reclass', 'Both run in parallel for one year'],
                'default' => 'Engine journal replaces the manual reclass',
            ],
            'mega_farms_scope' => [
                'label' => 'Mega Farms facilities',
                'description' => 'The Mega Farm programme is Government money that MAIIC runs on its behalf, at 15 percent of which 5 is MAIIC\'s and 10 the fund\'s; 95 percent of the loans are in default and the losses are the fund\'s (spec v4 section 16, decision D30). The seeded option keeps the programme outside the EIR engine, computes MAIIC\'s 5 percent share on the amount expected to be recovered, and provisions the loans fully in the ECL module against the fund. Open choice O22; Dr Thom to confirm.',
                'options' => ['Out of EIR engine; in ECL module; 5 percent share on net', 'In scope, stage and interest basis confirmed first', 'Out of scope entirely, disclosed'],
                'default' => 'Out of EIR engine; in ECL module; 5 percent share on net',
            ],
            'megafarm_pd_method' => [
                'label' => 'Probability of default for the Mega Farm schemes',
                'description' => 'The programme has two seasons of history and seven thousand accounts: breadth without depth. The seeded method takes the shape of the probability (term structure, macro sensitivity) from MAIIC\'s own agricultural transition matrix and the level from the programme\'s observed cohort default rates through a measured, governed scalar (spec v4 section 16.8). The cohort rate alone is the check; the programme\'s own matrix is declined until three seasons exist; judgement with an overlay is for a season the data cannot describe. The method used for the 2025 provision is to be confirmed by Finance.',
                'options' => ['Agricultural-sector PD scaled to the programme', 'Seasonal cohort default rate with a benchmark prior', 'Transition matrix on the programme history', 'Expert judgement with overlay'],
                'default' => 'Agricultural-sector PD scaled to the programme',
            ],
            'megafarm_scalar_ceiling' => [
                'label' => 'Ceiling on the Mega Farm programme scalar',
                'description' => 'The scalar is the measured ratio of the programme\'s default rate to the agricultural-sector probability for the same year and horizon; the 2025 cohort implies about 30. A scalar above this ceiling needs a second approval, so it cannot quietly be set to one (spec v4 section 16.8).',
                'options' => ['40 times the sector PD', '20 times the sector PD', '60 times the sector PD'],
                'default' => '40 times the sector PD',
            ],
            'macro_source_precedence' => [
                'label' => 'Which macro source wins where two overlap',
                'description' => 'Macro statistics arrive from the World Bank (actuals), the IMF World Economic Outlook (forecasts) and the Reserve Bank by file (rates), with a batch of provenance on every observation (spec v4 section 13). This decides which source wins where two carry the same series and period. A manual entry overrides any of them only with a reason, shown on the row and in the audit log.',
                'options' => ['World Bank actuals, IMF forecasts, RBM rates', 'IMF for everything it carries', 'Manual entry first, then the sources'],
                'default' => 'World Bank actuals, IMF forecasts, RBM rates',
            ],
            'fli_adjustment_route' => [
                'label' => 'How the forward-looking adjustment is produced',
                'description' => 'All three routes write the same adjustment rows, so the step that applies the adjustment to each loan\'s PD and the ECL that reads it do not change (spec v4 section 14.6). Regression is the approved model on the scenario paths. The manual overlay is a register entry per scenario and window, with scope, reason, evidence, owner, expiry and two approvals, shown as its own line. The third is the regression result with an overlay on top.',
                'options' => ['Regression', 'Manual overlay', 'Regression plus overlay'],
                'default' => 'Regression',
            ],
            'fli_transmission_method' => [
                'label' => 'How the adjustment reaches the PD',
                'description' => 'The route says where the adjustment comes from; this says how it moves a loan\'s PD once it exists (spec v4 section 14.7). The multiplicative scalar is MAIIC\'s method today: post-FLI PD = pre-FLI PD times one plus the adjustment, floored and capped. The alternatives are built and each is declined, with the reason, until the data it needs exists; every method has a card in the system that explains it, checks its preconditions live and shows a worked example on a real loan.',
                'options' => ['Multiplicative scalar on the proxy ratio', 'Segment-specific scalar', 'One of the nine reference methods', 'Logit-linear PD model', 'Vasicek single-factor Z-shift'],
                'default' => 'Multiplicative scalar on the proxy ratio',
            ],
            'fli_asset_correlation' => [
                'label' => 'Asset correlation for the Vasicek shift',
                'description' => 'Used only under the Vasicek method: the correlation of a borrower with the single systematic factor. A starting value in the range regulators use for corporate exposures; per portfolio once the method is in use (spec v4 section 14.7).',
                'options' => ['0.12', '0.08', '0.16', '0.24'],
                'default' => '0.12',
            ],
            'fli_expected_sign_test' => [
                'label' => 'Expected-sign test on a regression pair',
                'description' => 'Each pair of a macro series and a credit-loss proxy carries the sign economics expects (defaults rise as growth falls). A fitted relationship with the wrong sign is a statistical accident however high its R-squared, and the guardrail declines it (spec v4 section 14.4). This decides whether the test is required before a model may be approved.',
                'options' => ['Required', 'Advisory: shown, not enforced'],
                'default' => 'Required',
            ],
            'fli_r2_cutoff' => [
                'label' => 'R-squared cut-off for an approvable model',
                'description' => 'A fitted relationship weaker than this cannot be approved as a model (spec v4 section 14.4). Thirty percent is a common starting threshold for annual macro data; MAIIC may tighten it.',
                'options' => ['30 percent', '20 percent', '40 percent', '50 percent'],
                'default' => '30 percent',
            ],
            'fli_min_observations' => [
                'label' => 'Minimum observations for a fit',
                'description' => 'The guardrail declines a fit with fewer overlapping periods than this between the macro series and the proxy, with the reason, and the PD holds at its pre-FLI value (spec v4 section 14.4).',
                'options' => ['12', '8', '16', '24'],
                'default' => '12',
            ],
            'fli_alpha' => [
                'label' => 'Significance level for a fit',
                'description' => 'The guardrail declines a fit whose p-value is above this level (spec v4 section 14.4).',
                'options' => ['5 percent', '10 percent', '1 percent'],
                'default' => '5 percent',
            ],
            'fli_normality_limits' => [
                'label' => 'Normality limits for a series profile',
                'description' => 'The profiler stores the distribution of every macro series and proxy and gives a normality verdict against these limits on skewness and excess kurtosis; the finder reads the verdict to choose rank or linear methods (spec v4 section 14.4).',
                'options' => ['Skewness 1.0; excess kurtosis 3.0', 'Skewness 0.5; excess kurtosis 2.0', 'Skewness 2.0; excess kurtosis 6.0'],
                'default' => 'Skewness 1.0; excess kurtosis 3.0',
            ],
            'scenario_weighting_method' => [
                'label' => 'How scenarios are weighted',
                'description' => 'IFRS 9 asks for the probability-weighted loss over a range of outcomes (5.5.17(a), B5.5.42). The seeded method runs the forward-looking chain once per scenario and reports the weighted sum of the losses, which captures that a downside hurts more than an upside helps. The other method, MAIIC\'s today, weights the macro path and calculates one loss; it is kept so that past periods can be reproduced and the two reconciled when the switch is made (spec v4 section 15.5).',
                'options' => ['Weight the ECL across scenarios', 'Weight the macro path, one ECL'],
                'default' => 'Weight the ECL across scenarios',
            ],
            'scenario_minimum_count' => [
                'label' => 'Minimum scenarios in a set',
                'description' => 'A scenario set cannot be proposed with fewer scenarios than this (spec v4 section 15.6). Three is the practice: a base, an upside and a downside.',
                'options' => ['3', '2', '4'],
                'default' => '3',
            ],
            'scenario_weight_bounds' => [
                'label' => 'Floor on the base weight and ceiling on any weight',
                'description' => 'The weights of a scenario set must sum to 100 and lie within these bounds; a set outside them cannot be proposed (spec v4 section 15.6).',
                'options' => ['Base at least 40 percent; none above 60 percent', 'Base at least 50 percent; none above 70 percent', 'No bounds beyond summing to 100'],
                'default' => 'Base at least 40 percent; none above 60 percent',
            ],
            'scenario_calibration_note' => [
                'label' => 'Calibration note required on a downside',
                'description' => 'Every downside scenario is anchored to something that has actually happened (the 2023 devaluation, the 2016 drought) and says so in a calibration note; a set with a downside lacking one cannot be proposed (spec v4 section 15.6).',
                'options' => ['Required', 'Recommended, not enforced'],
                'default' => 'Required',
            ],
            'overlay_requires_approved_set' => [
                'label' => 'An overlay needs an approved scenario set',
                'description' => 'A management overlay on the forward-looking adjustment sits on an approved scenario set; a set cannot be locked with an unapproved overlay against it, and an overlay cannot be entered against a set that is not approved (spec v4 section 15.7).',
                'options' => ['Required', 'Not required'],
                'default' => 'Required',
            ],
            'dpd_basis' => [
                'label' => 'Days-past-due basis',
                'description' => 'The Reserve Bank\'s directive for development finance institutions counts days overdue from the instalment that fell due. The core system also ages the overdue amounts by bucket, and the two can differ on a partly cured account whose overdue date was never reset. The engine counts from the oldest unpaid instalment, as the directive does, and shows the bucket ageing beside it (spec v4 section 3.6, decision D31).',
                'options' => ['Oldest overdue instalment (the directive)', 'Bucket ageing of the overdue amounts'],
                'default' => 'Oldest overdue instalment (the directive)',
            ],
            'stage3_missed_instalments' => [
                'label' => 'Missed-instalment trigger for Stage 3',
                'description' => 'MAIIC\'s accounting policy (2025 statements, note 22.8.1) treats a loan as defaulted when the borrower has missed this many consecutive contractual payments, beside the day count. The trigger is read from the instalment plan and the ledger (spec v4 section 3.6, decision D31).',
                'options' => ['4 consecutive', '3 consecutive', '6 consecutive', 'Not applied'],
                'default' => '4 consecutive',
            ],
            'stage_cure_months' => [
                'label' => 'Months a loan stays in its stage after it cures',
                'description' => 'A loan whose days past due fall back below a stage threshold is held at its prior stage until it has been below that threshold for this many consecutive month-ends, the current one included; it moves down on the month that completes the count. A move up is immediate. The directive classifies by how long instalments are overdue and expects a cured facility to prove itself before it is upgraded (spec v4 section 3.6; the directive\'s section 15; system audit of 9 October 2026, finding M11).',
                'options' => ['0 months (no probation)', '3 months', '6 months', '12 months'],
                'default' => '3 months',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalogue());
    }

    /**
     * The value in force for a key on a date (today by default).
     *
     * @throws GovernanceSettingMissingException when no approved value applies
     */
    public function get(string $key, ?CarbonInterface $asOf = null): string
    {
        $date = ($asOf ?? CarbonImmutable::today())->toDateString();
        $memoKey = $key . '|' . $date;
        if (! array_key_exists($memoKey, $this->memo)) {
            $row = $this->inForce($key, $asOf);
            if ($row === null) {
                throw GovernanceSettingMissingException::forKey($key, $date);
            }
            $this->memo[$memoKey] = $row->value;
        }

        return $this->memo[$memoKey];
    }

    /** The approved row in force for a key on a date, or null when there is none. */
    /** The governed answer to "may this administrator approve their own change?" (finding H9). */
    public function adminOverrideAllowed(?object $user = null, ?CarbonInterface $asOf = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return false;
        }
        try {
            return $this->get('maker_checker_admin_override', $asOf) === 'Allowed for administrators';
        } catch (\Throwable) {
            return false;
        }
    }

    public function inForce(string $key, ?CarbonInterface $asOf = null): ?GovernanceSetting
    {
        $date = ($asOf ?? CarbonImmutable::today())->toDateString();

        return GovernanceSetting::query()
            ->where('key', $key)
            ->where('status', GovernanceSetting::STATUS_APPROVED)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The options a key may take, from the catalogue.
     *
     * @return list<string>
     * @throws InvalidArgumentException for a key the catalogue does not know
     */
    public function options(string $key): array
    {
        $catalogue = self::catalogue();
        if (! isset($catalogue[$key])) {
            throw new InvalidArgumentException("'{$key}' is not a governance setting the engine knows.");
        }

        return $catalogue[$key]['options'];
    }

    /**
     * Record a proposed change. The proposal takes no effect until a second
     * person approves it (see approve()). A value outside the option list, an
     * effective date that is not later than the last approved change, or a
     * duplicate (key, date) is refused with a named reason.
     */
    public function propose(string $key, string $value, CarbonInterface|string $effectiveFrom, string $reason, ?int $userId): GovernanceSetting
    {
        $options = $this->options($key);
        $definition = self::catalogue()[$key];
        $value = trim($value);
        if (! in_array($value, $options, true)) {
            throw new InvalidArgumentException("'{$value}' is not one of the options for {$definition['label']}: " . implode(' / ', $options) . '.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A change needs a specific reason of at least 10 characters.');
        }

        $effective = $this->date($effectiveFrom);

        return DB::transaction(function () use ($key, $value, $effective, $reason, $userId, $definition) {
            $this->assertEffectiveDateIsLater($key, $effective, null);

            $inForce = $this->inForce($key, $effective);
            if ($inForce !== null && $inForce->value === $value) {
                throw new LogicException("{$definition['label']} is already '{$value}' from {$inForce->effective_from->toDateString()}; there is nothing to change.");
            }

            $setting = GovernanceSetting::create([
                'key' => $key,
                'value' => $value,
                'options' => $definition['options'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'effective_from' => $effective->toDateString(),
                'set_by' => $userId,
                'reason' => mb_substr($reason, 0, 500),
                'status' => GovernanceSetting::STATUS_PROPOSED,
            ]);

            AuditLoggerService::log('EIR Governance Setting Proposed', GovernanceSetting::class, $setting->id, [
                'old_values' => $inForce ? ['value' => $inForce->value, 'effective_from' => $inForce->effective_from->toDateString()] : null,
                'new_values' => ['key' => $key, 'value' => $value, 'effective_from' => $effective->toDateString(), 'reason' => $reason],
                'meta' => ['proposed_by' => $userId, 'first_period_applied' => $effective->format('Y-m')],
            ]);

            return $setting;
        });
    }

    /**
     * Approve a proposal. The approver must be a different person from the
     * proposer unless an administrator overrides, the same rule the EIR lock
     * applies. The row previously in force is copied to the history table at
     * this moment, because this is when its replacement was decided; it stays
     * in the live table so that dates before the change still resolve to it.
     */
    public function approve(int $settingId, int $approverId, bool $allowMakerCheckerOverride = false): GovernanceSetting
    {
        return DB::transaction(function () use ($settingId, $approverId, $allowMakerCheckerOverride) {
            $setting = GovernanceSetting::query()->lockForUpdate()->findOrFail($settingId);
            if ($setting->status !== GovernanceSetting::STATUS_PROPOSED) {
                throw new LogicException('Only a proposed change can be approved.');
            }
            if (! $allowMakerCheckerOverride && $setting->set_by === null) {
                throw new LogicException('The proposal has no identifiable proposer and cannot be approved.');
            }
            if (! $allowMakerCheckerOverride && (int) $setting->set_by === $approverId) {
                throw new LogicException('The person who proposed a change cannot approve it.');
            }

            $effective = CarbonImmutable::parse($setting->effective_from->toDateString());
            $this->assertEffectiveDateIsLater($setting->key, $effective, $setting->id);

            $superseded = $this->inForce($setting->key, $effective);
            if ($superseded !== null) {
                GovernanceSettingHistory::create([
                    'setting_id' => $superseded->id,
                    'key' => $superseded->key,
                    'value' => $superseded->value,
                    'options' => $superseded->options,
                    'label' => $superseded->label,
                    'description' => $superseded->description,
                    'effective_from' => $superseded->effective_from->toDateString(),
                    'set_by' => $superseded->set_by,
                    'approved_by' => $superseded->approved_by,
                    'approved_at' => $superseded->approved_at,
                    'reason' => $superseded->reason,
                    'status' => $superseded->status,
                    'superseded_at' => now(),
                    'superseded_by' => $approverId,
                    'superseded_by_setting_id' => $setting->id,
                ]);
            }

            $setting->update([
                'status' => GovernanceSetting::STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);
            $this->memo = [];

            AuditLoggerService::log('EIR Governance Setting Approved', GovernanceSetting::class, $setting->id, [
                'old_values' => $superseded ? ['value' => $superseded->value, 'effective_from' => $superseded->effective_from->toDateString()] : null,
                'new_values' => ['key' => $setting->key, 'value' => $setting->value, 'effective_from' => $effective->toDateString(), 'reason' => $setting->reason],
                'meta' => [
                    'proposed_by' => $setting->set_by,
                    'approved_by' => $approverId,
                    'admin_override' => $allowMakerCheckerOverride,
                    'first_period_applied' => $effective->format('Y-m'),
                ],
            ]);

            return $setting->fresh();
        });
    }

    /**
     * Everything the Governance Centre screen shows: each catalogue setting
     * with its value in force on the date, every row (past, in force, upcoming,
     * proposed) and the history of superseded rows.
     *
     * @return list<array>
     */
    public function overview(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ?? CarbonImmutable::today();
        $rows = GovernanceSetting::with(['proposer:id,name', 'approver:id,name'])
            ->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('key');
        $history = GovernanceSettingHistory::with(['proposer:id,name', 'approver:id,name', 'superseder:id,name'])
            ->orderByDesc('superseded_at')->orderByDesc('id')->get()->groupBy('key');

        $overview = [];
        foreach (self::catalogue() as $key => $definition) {
            $inForce = $this->inForce($key, $asOf);
            $overview[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'options' => $definition['options'],
                'default' => $definition['default'],
                'in_force' => $inForce ? $this->presentRow($inForce, $inForce, $asOf) : null,
                'rows' => $rows->get($key, collect())->map(fn ($row) => $this->presentRow($row, $inForce, $asOf))->values()->all(),
                'history' => $history->get($key, collect())->map(fn ($row) => [
                    'id' => $row->id,
                    'setting_id' => $row->setting_id,
                    'value' => $row->value,
                    'effective_from' => $row->effective_from->toDateString(),
                    'reason' => $row->reason,
                    'proposer' => $row->proposer?->name,
                    'approver' => $row->approver?->name,
                    'approved_at' => $row->approved_at?->toDateTimeString(),
                    'superseded_at' => $row->superseded_at?->toDateTimeString(),
                    'superseded_by' => $row->superseder?->name,
                    'superseded_by_setting_id' => $row->superseded_by_setting_id,
                ])->values()->all(),
            ];
        }

        return $overview;
    }

    /**
     * The reconciliation band in force on a date, as numbers.
     *
     * @return array{percent:float, floor:float}
     */
    public function reconciliationTolerance(?CarbonInterface $asOf = null): array
    {
        return self::parseTolerance($this->get('recon_tolerance', $asOf));
    }

    /** The Stage 3 accrual basis in force on a date: NET or GROSS. */
    public function stage3InterestBasis(?CarbonInterface $asOf = null): string
    {
        return self::parseStage3Basis($this->get('stage3_interest_basis', $asOf));
    }

    /**
     * Read a recon_tolerance option into a share of the posted amount and a
     * kwacha floor. "N percent" and "N basis points" both give the share; an
     * option with no share at all is an absolute threshold (share zero).
     *
     * @return array{percent:float, floor:float}
     */
    public static function parseTolerance(string $option): array
    {
        $percent = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*percent/i', $option, $m) === 1) {
            $percent = (float) $m[1];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*basis\s*points?/i', $option, $m) === 1) {
            $percent = ((float) $m[1]) / 100;
        }

        $floor = null;
        if (preg_match('/MWK\s*([\d,]+(?:\.\d+)?)/i', $option, $m) === 1) {
            $floor = (float) str_replace(',', '', $m[1]);
        }

        if ($floor === null && $percent === null) {
            throw new InvalidArgumentException("The tolerance option '{$option}' names neither a share of the posted amount nor a kwacha floor.");
        }

        return ['percent' => $percent ?? 0.0, 'floor' => $floor ?? 0.0];
    }

    /** Read a stage3_interest_basis option into the accrual basis the roll-forward stores. */
    public static function parseStage3Basis(string $option): string
    {
        $text = strtolower(trim($option));
        if (str_starts_with($text, 'net')) {
            return 'NET';
        }
        if (str_starts_with($text, 'gross')) {
            return 'GROSS';
        }

        throw new InvalidArgumentException("The Stage 3 basis option '{$option}' is neither net nor gross.");
    }

    /**
     * A change applies forward only: its effective date must be later than
     * every approved change before it, so no governed period is restated.
     * The same (key, date) may exist once, whatever its status.
     */
    private function assertEffectiveDateIsLater(string $key, CarbonImmutable $effective, ?int $ignoreId): void
    {
        $latest = GovernanceSetting::query()->where('key', $key)
            ->where('status', GovernanceSetting::STATUS_APPROVED)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->max('effective_from');
        $latestDate = $latest === null ? null : CarbonImmutable::parse($latest)->toDateString();
        if ($latestDate !== null && $effective->toDateString() <= $latestDate) {
            throw new LogicException("The effective date must be later than {$latestDate}, the date of the last approved change for '{$key}'. A change never restates a period already governed.");
        }

        $duplicate = GovernanceSetting::query()->where('key', $key)
            ->whereDate('effective_from', $effective->toDateString())
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->exists();
        if ($duplicate) {
            throw new LogicException("A change to '{$key}' effective {$effective->toDateString()} already exists; choose another date.");
        }
    }

    private function date(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value->toDateString());
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new InvalidArgumentException('The effective date must be written as yyyy-mm-dd.');
        }

        return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
    }

    private function presentRow(GovernanceSetting $row, ?GovernanceSetting $inForce, CarbonInterface $asOf): array
    {
        $effective = $row->effective_from->toDateString();
        $state = match (true) {
            $row->status === GovernanceSetting::STATUS_PROPOSED => 'PROPOSED',
            $inForce !== null && $row->id === $inForce->id => 'IN_FORCE',
            $effective > $asOf->toDateString() => 'UPCOMING',
            default => 'PAST',
        };

        return [
            'id' => $row->id,
            'key' => $row->key,
            'value' => $row->value,
            'effective_from' => $effective,
            'status' => $row->status,
            'state' => $state,
            'reason' => $row->reason,
            'proposer' => $row->proposer?->name,
            'proposer_id' => $row->set_by,
            'approver' => $row->approver?->name,
            'approved_at' => $row->approved_at?->toDateTimeString(),
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }
}
