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
     * A definition may carry 'seed_reason': the reason the seeder records for a
     * technical default that is not Dupleix's recommendation (it says so, and
     * that it is not a MAIIC approval).
     *
     * @return array<string, array{label:string, description:string, options:list<string>, default:string, seed_reason?:string}>
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
                'description' => 'E-Banker can charge interest on the sanctioned amount or on the drawn balance, and the instalment likewise. The flag is now supplied per account (loan master and scheme settings). Open choice O6. A facility still partly drawn with no flag is refused a schedule. A facility drawn in full in tranches needs the interest basis only for the months it was partly drawn: where neither the contract nor its scheme states it, the basis is read from the interest E-Banker posted in those months (the drawn balance or the sanctioned amount, whichever the postings follow) and recorded as observed; with no postings to read it is refused (results review of 13 October 2026, E1). The second option takes the drawn balance everywhere without reading the postings.',
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
                'description' => 'Every route produces the same pack (the extract files plus a manifest) and enters the landing zone by the same door, so the route can be changed at any time and the others stay available for a missed month or a correction (spec v4 section 6.5). Route 1 is Barry running the versioned queries and uploading the files; route 2 a scheduled export at MAIIC to a folder the system polls; route 3 the same script posting the pack to the system; route 4 the system reading E-Banker directly over the VPN with a read-only account; route 5 a vendor view or API. Routes 4 and 5 need Dr Thom and ICT. The feed-folder poller lands packs only while route 2 is in force and the API intake only while route 3 is, unless the last option opens routes 1 to 3 together; the manual upload stays open for a missed month or a correction. Open choice O9.',
                // the feed-folder poller lands only under route 2 and the API intake only under route 3,
                // unless the last option opens routes 1 to 3 together (FeedRoute, 14 October 2026)
                'options' => ['Route 1: manual pack upload', 'Route 2: scheduled export to a folder', 'Route 3: push by API', 'Route 4: direct read over the VPN', 'Route 5: vendor view or API', \App\Services\Ebanker\FeedRoute::SEVERAL],
                'default' => 'Route 1: manual pack upload',
            ],
            \App\Services\Ebanker\DirectRead\DirectReadSchedule::TIMING => [
                'label' => 'When route 4 reads E-Banker each month',
                'description' => 'Route 4 reads the month just ended once a month, by the system\'s own scheduler. By default it reads at 06:00 on the first working day of the month, before the working day starts and while E-Banker is quiet (data access request of 16 October 2026, sections 5 and 6.2). The second option reads instead on the working day and at the time set in the next two settings, for example where MAIIC\'s month-end close or E-Banker\'s end-of-day runs later. Working days are Monday to Friday; public holidays are not known to the system. Read only while route 4 is the feed route in force; a month can always be read by hand (eir:direct-read). Spec v4 section 6.5.',
                'options' => [\App\Services\Ebanker\DirectRead\DirectReadSchedule::AT_SIX, \App\Services\Ebanker\DirectRead\DirectReadSchedule::AS_SET],
                'default' => \App\Services\Ebanker\DirectRead\DirectReadSchedule::AT_SIX,
            ],
            \App\Services\Ebanker\DirectRead\DirectReadSchedule::WORKING_DAY => [
                'label' => 'Route 4: the working day of the month it reads on',
                'description' => 'Which working day of the month route 4 reads on, counting Monday to Friday from the first of the month. Read only when "When route 4 reads E-Banker each month" is set to the working day and time set here. Spec v4 section 6.5.',
                'options' => \App\Services\Ebanker\DirectRead\DirectReadSchedule::WORKING_DAYS,
                'default' => \App\Services\Ebanker\DirectRead\DirectReadSchedule::WORKING_DAYS[0],
            ],
            \App\Services\Ebanker\DirectRead\DirectReadSchedule::TIME => [
                'label' => 'Route 4: the time it reads at',
                'description' => 'The time of day, on the server\'s clock, at which route 4 reads on the working day set above. Read only when "When route 4 reads E-Banker each month" is set to the working day and time set here. A read takes minutes; a time outside working hours keeps it clear of E-Banker\'s busy hours (data access request, section 4.2: ORA-01555). Spec v4 section 6.5.',
                'options' => \App\Services\Ebanker\DirectRead\DirectReadSchedule::times(),
                'default' => '06:00',
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
            'fee_gl_reconciliation' => [
                'label' => 'Fee imports checked against the fee GLs',
                'description' => 'When fees are imported by loan (EIR Data Intake, type fees), every fee held for each year the file covers is set beside the trial balance movement of 4871 Legal Fees and 4873 Arrangement Fees, month by month and for the year, inside the reconciliation band. The fee GLs agree to the trial balance every month, while about 44 percent of 2025 fee income never touched a loan account, so the GL is the control total (spec v4 sections 3.7 and 7.4). The result shows on the import and on Fee Classification; it reports and never blocks. Switching it off stops the check, and the screens then say it is off.',
                'options' => [\App\Services\Eir\FeeGlReconciliationService::ON, \App\Services\Eir\FeeGlReconciliationService::OFF],
                'default' => \App\Services\Eir\FeeGlReconciliationService::ON,
            ],
            // Added 14 October 2026 (results review E3, spec v4 sections 7.8.2 and 7.8.6).
            'fee_source_precedence' => [
                'label' => 'Which source a contract\'s fees come from',
                'description' => 'The same fee is held in several places: the GL fee postings by loan (GL_04, tied to the loan through the drawdown batch), Finance\'s fee workbook loaded through EIR Data Intake, the origination system\'s charges table (P2_07) and the contract master\'s fee columns (Extract A). The engine reads a contract\'s fees from the highest source in this order that holds any fee for it; a line from a lower source is kept, shown and marked superseded, never enters the EIR and is not counted again by the fee GL check, so no fee is counted twice. The take-on workbook\'s fees are the only record of the take-on loans and never compete with the others (spec v4 sections 7.8.2 and 7.8.6). Every line still needs a person\'s classification and review before it reaches an EIR.',
                'options' => [\App\Services\Eir\FeeSourcePrecedence::GL_FIRST, \App\Services\Eir\FeeSourcePrecedence::FINANCE_FIRST, \App\Services\Eir\FeeSourcePrecedence::LOS_FIRST],
                'default' => \App\Services\Eir\FeeSourcePrecedence::GL_FIRST,
            ],
            // Added 16 October 2026 (pack 13, question F2; spec v4 section 7.8.6).
            UnpostedFeeBasis::KEY => [
                'label' => 'LOS charges never posted to the ledger',
                'description' => 'The loan origination system (LOS_DISB_CHARGES_POST) captures the charges of a loan at application. A charge that was posted reaches the GL, and the GL line or the line of the Finance workbook is then its source of record. An LOS line that no other source holds was never posted: it has no batch and no income GL, so nothing shows the borrower was charged it. As built, such a line counts like any other line of the source of record. The second option counts it only where Finance has confirmed it was charged (an approved answer on sheet 4b, Fees never posted, of the fee reconciliation workbook on Fee Classification); a line Finance has not confirmed is left out of the EIR inputs and no longer blocks the loan. Read where the engine decides which fee lines count (EirReadinessService, EirContractInputService). A locked EIR is never reopened by a change: every locked EIR whose fee inputs would change is flagged for review, naming the lines (pack 13, question F2).',
                'options' => [UnpostedFeeBasis::AS_BUILT, UnpostedFeeBasis::CONFIRMED_ONLY],
                'default' => UnpostedFeeBasis::AS_BUILT,
                'evidence_required' => true,
                'seed_reason' => 'Seeded at the basis the system was built on (16 October 2026), so that nothing moves on deploy: an LOS charge never posted counts where no other source holds the fee. Not a MAIIC decision: the answer of Finance to pack 13 question F2 decides whether to change it, by a proposal, its supporting document and the approval of a second person.',
            ],
            // Added 14 October 2026 (results review E2, spec v4 section 6.9).
            'takeon_eir_plausibility' => [
                'label' => 'Take-on EIR: the check before it is used',
                'description' => 'A take-on loan\'s EIR is solved from origination on the take-on workbook\'s schedule. Before it is carried into the EIR engine the schedule is solved again with no fee: a schedule that is a true record of the loan gives back the rates it charges, so its no-fee rate must lie inside the lowest and highest rate the schedule\'s own lines charge, widened by this margin for the day-count and compounding differences between the workbook and the solver. A schedule outside the band (a balance the lines do not explain, instalments sharing one date, a loan it never retires) is refused with the reason and the loan stays on its take-on balance until Finance corrects the block (spec v4 section 6.9; results review of 13 October 2026, E2).',
                'options' => ['Inside the schedule\'s own rates, 0.5 point either side', 'Inside the schedule\'s own rates, 1 point either side', 'Inside the schedule\'s own rates, 0.25 point either side'],
                'default' => 'Inside the schedule\'s own rates, 0.5 point either side',
            ],
            'mega_farms_scope' => [
                'label' => 'Mega Farms facilities',
                'description' => 'The Mega Farm programme is Government money that MAIIC runs on its behalf, at 15 percent of which 5 is MAIIC\'s and 10 the fund\'s; 95 percent of the loans are in default and the losses are the fund\'s (spec v4 section 16, decision D30). The seeded option keeps the programme outside the EIR engine, computes MAIIC\'s 5 percent share on the amount expected to be recovered, and provisions the loans fully in the ECL module against the fund. Open choice O22; Dr Thom to confirm.',
                'options' => ['Out of EIR engine; in ECL module; 5 percent share on net', 'In scope, stage and interest basis confirmed first', 'Out of scope entirely, disclosed'],
                'default' => 'Out of EIR engine; in ECL module; 5 percent share on net',
            ],
            'megafarm_pd_method' => [
                'label' => 'Probability of default for the Mega Farm schemes',
                'description' => 'The seeded method measures the programme as its own PD pool, apart from MAIIC\'s book (Edward Mazibuko, 17 October 2026): the transition matrix on the programme\'s own loans, the same measure and twelve-month window as MAIIC\'s pooled book, Stage 3 in default. A Stage 1 or 2 below the governed minimum history is declined, never given MAIIC\'s PD. The alternatives: MAIIC\'s agricultural-sector PD scaled by the programme\'s measured cohort default rate under a governed ceiling (spec v4 section 16.8, the seeded method until 17 October 2026); a seasonal cohort rate once two seasons exist; judgement with an overlay for a season the data cannot describe.',
                'options' => ['Agricultural-sector PD scaled to the programme', 'Seasonal cohort default rate with a benchmark prior', 'Transition matrix on the programme history', 'Expert judgement with overlay'],
                'default' => 'Transition matrix on the programme history',
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
            // The forward-looking module (FLI module specification v1, 13 October 2026)
            'fli_model_cadence' => [
                'label' => 'How often the forward-looking model is recalibrated',
                'description' => 'The model (the driver, the lag and the coefficients) is recalibrated and approved as a new version, and each month-end applies the version in force at its date. Under the seeded option a version stays in force until the next annual review, or until an event breaks the relationship (a devaluation, a drought, a change of policy regime); the month-end does not re-fit it, and a version older than twelve months is flagged as due for review. Under the other option a version must be published for every month (FLI module decision D-F1).',
                'options' => ['Once a year, and when an event breaks the relationship', 'Every month-end'],
                'default' => 'Once a year, and when an event breaks the relationship',
            ],
            'fli_without_model' => [
                'label' => 'A month-end with no forward-looking model version in force',
                'description' => 'What the month-end does for a month that has no model version in force (no fit approved on Regression, so none is published). Under the seeded option the month-end runs every step: the route writes each scenario of the approved set at the PD before FLI, so the PD holds with no forward-looking adjustment; the projection is skipped; the ECL is calculated and the month is recorded, and the record says "no forward-looking model in force", the IFRS 9 note and the forward-looking reports disclose it, and the sign-off needs a commentary saying so. Under the other option the month-end, the bootstrap and the route stop for such a month, with the reason, until a version is in force. The Maintenance month-end, the Forward-Looking Overview and the bootstrap all read this one setting (FLI module decision D-F1, note of 14 October 2026). Seeded as a technical default from inception, the behaviour of the bootstrap until then, so the golden figures do not move; it is not a MAIIC approval and is a decision for Dr Thom.',
                'options' => [\App\Services\Fli\FliWithoutModel::PROCEED, \App\Services\Fli\FliWithoutModel::REFUSE],
                'default' => \App\Services\Fli\FliWithoutModel::PROCEED,
                'seed_reason' => 'Technical default from inception, not a MAIIC approval: the behaviour of the bootstrap until 14 October 2026, so the golden figures do not move; decision for Dr Thom (FLI module specification D-F1, note of 14 October 2026)',
            ],
            'fli_forecast_horizon' => [
                'label' => 'Reasonable and supportable forecast horizon',
                'description' => 'How far the scenario paths are used before the projection reverts to the long-run PD (IFRS 9 B5.5.50). Inside the horizon each forecast year takes the scenario\'s own path; after it the adjustment falls in equal steps to nil over the reversion years, so a long Stage 2 loan is measured on the long-run PD for the years nobody can forecast (FLI module decision D-F4).',
                'options' => ['3 years, then 2 years back to the long-run PD', '2 years, then 1 year back to the long-run PD', '5 years, then 2 years back to the long-run PD'],
                'default' => '3 years, then 2 years back to the long-run PD',
            ],
            'fli_movement_commentary' => [
                'label' => 'ECL movement that needs a commentary',
                'description' => 'When the booked ECL moves by more than this against the previous month-end, the month cannot be closed on the forward-looking Overview until a reviewer records why (FLI module section 7).',
                'options' => ['10 percent month on month', '5 percent month on month', '20 percent month on month'],
                'default' => '10 percent month on month',
            ],
            // The S&P basis: an alternative ECL on the December 2025 model's method, for comparison only (spec v4 section 17, 15 October 2026)
            'sp_basis_staging' => [
                'label' => 'S&P basis: how a loan is staged',
                'description' => 'The stage the S&P basis comparison uses. Under the seeded option it is the December 2025 model\'s: the arrears bracket of the days past due, 0 to 30 days Stage 1, 31 to 180 Stage 2, over 180 Stage 3 (PortfolioReport!AP, ECLComp!P:R). Under the other it is the system\'s own stage. The S&P basis is a comparison: it changes no booked figure.',
                'options' => [\App\Services\Ifrs9\SpBasisEclService::STAGING_MODEL, \App\Services\Ifrs9\SpBasisEclService::STAGING_SYSTEM],
                'default' => \App\Services\Ifrs9\SpBasisEclService::STAGING_MODEL,
                'seed_reason' => \App\Services\Ifrs9\SpBasisEclService::SEED_REASON,
            ],
            'sp_basis_grades' => [
                'label' => 'S&P basis: the grade for each arrears bracket',
                'description' => 'The internal grade (and so the S&P rating and its cumulative default rates) given to a loan by its days past due, as the December 2025 model maps it: 0 to 30 days grade 3 (A), 31 to 90 grade 6 (B), 91 to 180 grade 8 (CCC), over 180 grade 10 (default). The grades are read from the active internal grading profile.',
                'options' => [\App\Services\Ifrs9\SpBasisEclService::GRADES_MODEL],
                'default' => \App\Services\Ifrs9\SpBasisEclService::GRADES_MODEL,
                'seed_reason' => \App\Services\Ifrs9\SpBasisEclService::SEED_REASON,
            ],
            'sp_basis_macro_factor' => [
                'label' => 'S&P basis: the forward-looking factor on the PD',
                'description' => 'The December 2025 model multiplies every PD by one plus the forecast T-bill rate (TBRateForecasts row 20, 15.75 percent), not by the NPL-change row headed as the PD adjustment (27.2 percent); question D3 to Dr Thom. The options are those two and none. A PD is capped at 100 percent.',
                'options' => [\App\Services\Ifrs9\SpBasisEclService::MACRO_TBILL, \App\Services\Ifrs9\SpBasisEclService::MACRO_NPL, \App\Services\Ifrs9\SpBasisEclService::MACRO_NONE],
                'default' => \App\Services\Ifrs9\SpBasisEclService::MACRO_TBILL,
                'seed_reason' => \App\Services\Ifrs9\SpBasisEclService::SEED_REASON,
            ],
            'internal_grade_pd_feed' => [
                'label' => 'Internal grades: may a profile write its PDs to the loan book?',
                'description' => 'The Update loan book action on Internal Grades writes an active profile\'s PDs over the loan book\'s PD before FLI, which the ECL engine reads. Under the seeded option it is switched off: internal grades (the S&P grading of the December 2025 model) are a comparison only, run on the S&P basis screen (spec v4 section 17), and the engine\'s PD stays the one measured from MAIIC\'s defaults. Whether S&P rates should feed the PD, alone or blended, is Dr Thom\'s decision D4; the other option turns the action on.',
                'options' => [\App\Services\Ifrs9\SpBasisEclService::FEED_OFF, \App\Services\Ifrs9\SpBasisEclService::FEED_ON],
                'default' => \App\Services\Ifrs9\SpBasisEclService::FEED_OFF,
                'seed_reason' => 'Technical default, not a MAIIC approval: keeps the engine results as measured while the S&P basis is a comparison; decision D4 for Dr Thom (spec v4 section 17, 15 October 2026)',
            ],
            'sp_basis_recovery' => [
                'label' => 'S&P basis: the model\'s typed recovery deduction',
                'description' => 'The December 2025 model deducts the present value of 46.98 percent of EAD, received 360 days out and discounted at the loan\'s rate, from every loan before PD and LGD (ECLComp!CB7, BE, BB), although its LGD already counts collateral recoveries (results review section 2.3, question D3). Seeded as the model has it, so the S&P basis reproduces the model; the other option leaves it out.',
                'options' => [\App\Services\Ifrs9\SpBasisEclService::RECOVERY_MODEL, \App\Services\Ifrs9\SpBasisEclService::RECOVERY_NONE],
                'default' => \App\Services\Ifrs9\SpBasisEclService::RECOVERY_MODEL,
                'seed_reason' => \App\Services\Ifrs9\SpBasisEclService::SEED_REASON,
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
                'label' => 'Staging day-count basis',
                'description' => 'How staging counts days past due. The core system ages the overdue amounts by bucket, as MAIIC\'s staging policy as at 31 December 2025 did; the Reserve Bank\'s directive for development finance institutions counts from the oldest overdue instalment. The two differ on a partly cured account whose overdue date was never reset, and buckets cannot tell 200 days from 900. This setting governs staging only, and only under the Custom preset (under a named staging policy the policy sets it). The loan book always keeps the directive\'s count as well (overdue_days), which the RBM return and the RBM classification read whatever this setting says (Staging Module Specification v1, section 5; spec v4 section 3.6, decision D31).',
                'options' => [StagingPolicy::BASIS_OLDEST, StagingPolicy::BASIS_BUCKET],
                'default' => StagingPolicy::BASIS_BUCKET,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only by staging under the Custom preset; the RBM return always reads the directive\'s count (Staging Module Specification v1, sections 3 and 5).',
            ],
            'stage3_missed_instalments' => [
                'label' => 'Missed-instalment trigger for Stage 3',
                'description' => 'MAIIC\'s accounting policy (2025 statements, note 22.8.1) treats a loan as defaulted when the borrower has missed this many consecutive contractual payments, beside the day count. The trigger is read from the instalment plan and the ledger (spec v4 section 3.6, decision D31).',
                'options' => ['4 consecutive', '3 consecutive', '6 consecutive', 'Not applied'],
                'default' => 'Not applied',
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025), which staged on days alone. Staging reads it only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'stage_cure_months' => [
                'label' => 'Months a loan stays in its stage after it cures',
                'description' => 'A loan whose days past due fall back below a stage threshold is held at its prior stage until it has been below that threshold for this many consecutive month-ends, the current one included; it moves down on the month that completes the count. A move up is immediate. The directive classifies by how long instalments are overdue and expects a cured facility to prove itself before it is upgraded (spec v4 section 3.6; the directive\'s section 15; system audit of 9 October 2026, finding M11). Read by staging only under the Custom preset. 0 months is MAIIC\'s staging policy as at 31 December 2025.',
                'options' => ['0 months (no probation)', '3 months', '6 months', '12 months'],
                'default' => '0 months (no probation)',
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025), which had no probation. Staging reads it only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            // Staging Module Specification v1, section 3 (15 October 2026): every staging rule a governed setting
            'staging_preset' => [
                'label' => 'Staging policy in force',
                'description' => 'Which staging policy stages the book. Single-ladder staging is MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025): one ladder for every facility, 1 to 30 days Stage 1, 31 to 180 days Stage 2, 181 days and over Stage 3, on the bucket ageing with a matured loan counted from its maturity, with no missed-instalment trigger, no qualitative register and no probation. Directive-aligned staging follows the RBM directive and MAIIC\'s 2025 accounting policy: thresholds by facility term (short-term 91, medium and long-term 181), the oldest overdue instalment, the count from maturity, the schedule recount, 4 missed instalments, the qualitative register, and a cure that needs the arrears cleared and then 3 months. It is never in force until MAIIC adopts it: proposed here with the Board or Credit Committee decision and approved by a second person; nothing switches it on by date. Under a named policy the policy governs every staging rule; under Custom each staging setting below governs.',
                'options' => [StagingPolicy::PRESET_SINGLE, StagingPolicy::PRESET_DIRECTIVE, StagingPolicy::PRESET_CUSTOM],
                'default' => StagingPolicy::PRESET_SINGLE,
                'evidence_required' => true,
                'seed_reason' => 'Seeded as MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025), in force for every period until MAIIC adopts another by governed approval (Staging Module Specification v1, section 3).',
            ],
            'staging_threshold_profile' => [
                'label' => 'Stage 3 threshold profile',
                'description' => 'The days past due at which a facility enters Stage 3. One threshold for every facility is MAIIC\'s staging policy as at 31 December 2025 (181 days, whatever the term). By facility term is the directive (section 10): 91 days for a short-term facility, 181 for a medium or long-term one, read from the approved staging_thresholds rows by tenor. The IFRS 9 presumption is 91 days for every facility (IFRS 9 B5.5.37). Read only under the Custom preset.',
                'options' => [StagingPolicy::PROFILE_SINGLE, StagingPolicy::PROFILE_BY_TERM, StagingPolicy::PROFILE_IFRS9],
                'default' => StagingPolicy::PROFILE_SINGLE,
                'evidence_required' => true,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'stage2_threshold_medium_long' => [
                'label' => 'Stage 2 threshold for medium and long-term facilities',
                'description' => 'The days past due at which a facility over 12 months enters Stage 2. 31 days is the IFRS 9 presumption (5.5.11) and MAIIC\'s staging policy as at 31 December 2025. 91 days is the directive\'s first medium and long-term class; it rebuts the 30-day presumption and needs documented evidence before it is approved. Read only under the Custom preset.',
                'options' => [StagingPolicy::STAGE2_31, StagingPolicy::STAGE2_91],
                'default' => StagingPolicy::STAGE2_31,
                'evidence_required' => true,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'staging_mega_farm_term' => [
                'label' => 'Mega Farm facilities',
                'description' => 'How a Mega Farm facility is classed for staging. The same threshold as every facility is MAIIC\'s staging policy as at 31 December 2025. By tenor treats it as any facility of its term. Always short-term stages it on the 91-day MEGA_FARM threshold rows, as the current build did. Read only under the Custom preset.',
                'options' => [StagingPolicy::MF_SAME, StagingPolicy::MF_BY_TENOR, StagingPolicy::MF_SHORT],
                'default' => StagingPolicy::MF_SAME,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'dpd_after_maturity' => [
                'label' => 'Days past due after maturity',
                'description' => 'A loan past its maturity date with a balance outstanding: its last instalment, the whole balance, fell due at maturity. Count from maturity takes the days since maturity when that is longer than the core system\'s count. It is MAIIC\'s staging policy as at 31 December 2025: the ECL workbooks of 14 December 2025 staged on a loan book that showed matured loans as past due, while E-Banker\'s report leaves the arrears of many matured loans empty (the Mega Farm bullet loans read 0 days), so without this count they would read current. Core-system ageing only reads E-Banker\'s count as it stands. Read only under the Custom preset, for the staging day count (staging_dpd); the directive\'s count kept for the RBM return always counts from maturity.',
                'options' => [StagingPolicy::MATURITY_CORE, StagingPolicy::MATURITY_COUNT],
                'default' => StagingPolicy::MATURITY_COUNT,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025, whose loan book showed matured loans as past due), decided by Edward Mazibuko on 15 October 2026. Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'dpd_schedule_correction' => [
                'label' => 'Schedule correction of the day count',
                'description' => 'Whether the core system\'s day count is taken as given, as MAIIC\'s staging policy as at 31 December 2025 did, or recounted against the instalment schedule in force and the ledger, which corrects a count left stale by a reschedule. Read only under the Custom preset, for the staging day count (staging_dpd); the directive\'s count kept for the RBM return is always recounted.',
                'options' => [StagingPolicy::SCHEDULE_AS_GIVEN, StagingPolicy::SCHEDULE_RECOUNT],
                'default' => StagingPolicy::SCHEDULE_AS_GIVEN,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'sicr_qualitative_register' => [
                'label' => 'Qualitative SICR register',
                'description' => 'Whether the approved entries of the qualitative SICR register (watch list, restructure, forbearance, Stage 3 indicators) lift a loan\'s stage. Not applied is MAIIC\'s staging policy as at 31 December 2025, which staged on days alone. Read only under the Custom preset.',
                'options' => [StagingPolicy::APPLIED, StagingPolicy::NOT_APPLIED],
                'default' => StagingPolicy::NOT_APPLIED,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            'sicr_pd_change' => [
                'label' => 'SICR on the change in PD since origination',
                'description' => 'Whether a loan whose lifetime PD has at least doubled since origination moves to Stage 2 (IFRS 9 5.5.9). Not applied is MAIIC\'s staging policy as at 31 December 2025 and the directive-aligned policy. The option is recorded now and built later, once each loan\'s PD at origination is kept.',
                'options' => [StagingPolicy::NOT_APPLIED, StagingPolicy::PD_DOUBLED],
                'default' => StagingPolicy::NOT_APPLIED,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025) (Staging Module Specification v1, section 3).',
            ],
            // the PD-change test's two thresholds (PD at Origination Specification v1, decision 4, taken by Edward
            // Mazibuko on Dr Thom's behalf on 16 October 2026 for ratification); read by its shadow report only,
            // until setting 9 (sicr_pd_change) is adopted
            'sicr_pd_ratio_threshold' => [
                'label' => 'PD-change test: how many times the starting PD',
                'description' => 'The PD-change test (setting 9) treats a loan as having had a significant increase in credit risk when its 12-month PD after the forward-looking adjustment is at least this many times its PD at origination, and at least the points below higher. Read by the shadow report of the test only, until setting 9 is adopted.',
                'options' => ['1.5 times', '2 times', '2.5 times', '3 times'],
                'default' => '2 times',
                'seed_reason' => 'Decided by Edward Mazibuko on Dr Thom\'s behalf on 16 October 2026, for ratification (PD at Origination Specification v1, decision 4): doubling is the widely used relative test.',
            ],
            'sicr_pd_points_threshold' => [
                'label' => 'PD-change test: by how many percentage points',
                'description' => 'The second half of the PD-change test: the 12-month PD must also have risen by at least this many percentage points since origination. The PDs of MAIIC are high, so a relative test alone would move loans on small absolute changes. Read by the shadow report of the test only, until setting 9 is adopted.',
                'options' => ['5 percentage points', '10 percentage points', '15 percentage points', '20 percentage points'],
                'default' => '10 percentage points',
                'seed_reason' => 'Decided by Edward Mazibuko on Dr Thom\'s behalf on 16 October 2026, for ratification (PD at Origination Specification v1, decision 4).',
            ],
            'stage_cure_test' => [
                'label' => 'What a loan must do to cure',
                'description' => 'What lets a loan move down a stage. Months below the threshold holds it for the cure months only, as MAIIC\'s staging policy as at 31 December 2025 did (with 0 months, it moves down at once). All arrears cleared moves it down only when nothing is overdue (the directive, section 13(3)). Arrears cleared, then the months needs both. Read only under the Custom preset.',
                'options' => [StagingPolicy::CURE_MONTHS, StagingPolicy::CURE_CLEARED, StagingPolicy::CURE_BOTH],
                'default' => StagingPolicy::CURE_MONTHS,
                'seed_reason' => 'Seeded at the value of MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025). Read only under the Custom preset (Staging Module Specification v1, section 3).',
            ],
            // PD and LGD by segment (9 October 2026): the book split into its real programmes
            'pd_segmentation_basis' => [
                'label' => 'How the PD is segmented',
                'description' => 'The probability of default is measured on groups of loans that share their credit risk (IFRS 9 B5.5.5): a segment. By portfolio measures each lending programme (MAIIC Industrial, MAIIC Agricultural, MAIIC Term, FInES Industrial, FInES Agricultural) on its own transition matrix. Pooled book measures the whole book as one. By RBM sector measures each economic sector of the directive across the programmes. By portfolio and RBM sector measures each sector inside each programme. Every segment uses the same twelve-month window. A segment with too little history follows the thin-segment rule. The ECL is still reported by portfolio; the loan carries the segment its PD came from. The seeded basis is the pooled book (Edward Mazibuko, 17 October 2026): MAIIC\'s book is one PD pool, and the Mega Farm programme is its own (megafarm_pd_method); neither is ever measured with the other.',
                'options' => ['By portfolio', 'Pooled book', 'By RBM sector', 'By portfolio and RBM sector'],
                'default' => 'Pooled book',
            ],
            'pd_segment_min_observations' => [
                'label' => 'Minimum history for a segment\'s own PD',
                'description' => 'A segment\'s PD from Stage 1, or from Stage 2, is its own only when at least this many loans started the twelve-month window in that stage and at least this many of them were in Stage 3 at its end (or had left the book unpaid, which counts as a default). Below that the stage is thin: a rate measured on two or three loans is noise, and a stage with no default at all would give a PD of nil. A thin stage follows the thin-segment rule.',
                'options' => ['10 loans and 1 default per stage', '5 loans and 1 default per stage', '20 loans and 2 defaults per stage', '10 loans per stage, defaults not counted'],
                'default' => '10 loans and 1 default per stage',
            ],
            'pd_segment_thin_rule' => [
                'label' => 'A segment too thin for its own PD or LGD',
                'description' => 'Under the first option a thin stage takes the PD of the segment above it for that stage: a sector inside a portfolio takes its portfolio\'s, a portfolio or a sector takes the pooled book\'s. A portfolio with too small a Stage 3 cohort takes the pooled book\'s LGD. The parent must meet the minimum itself, or the run stops. Every substitution is recorded on the loan and shown in the ECL by segment report. Under the second option any thin segment stops the run, names the segment and the reason, and nothing is written.',
                'options' => ['Take the parent: portfolio, then the pooled book', 'Fail closed: stop the run and name the segment'],
                'default' => 'Take the parent: portfolio, then the pooled book',
            ],
            'pd_sector_unverified_codes' => [
                'label' => 'Industry codes held apart in a sector PD',
                'description' => 'E-Banker gives industry code 4290 (other civil engineering) to a loan whose sector was never captured: in the August 2026 book 58 of the 135 loans carry it (57 with a balance), among them an eye clinic, a lodge, transport firms and a farmers\' cooperative. Held apart, those loans form their own "unverified sector" segment in a sector PD, and the sector reports show them separately, instead of being read as construction risk. Their codes are never changed by the system; the fix is to capture the right code in E-Banker.',
                'options' => ['4290 (E-Banker default civil engineering code)', 'None held apart'],
                'default' => '4290 (E-Banker default civil engineering code)',
            ],
            'lgd_segment_min_cohort' => [
                'label' => 'Minimum Stage 3 cohort for a portfolio\'s own LGD',
                'description' => 'The LGD is measured per portfolio on the loans that were in Stage 3 twelve months before the period, followed to the period (cure and recovery). A portfolio with fewer such loans than this follows the thin-segment rule.',
                'options' => ['5 Stage 3 loans at the window start', '3 Stage 3 loans at the window start', '10 Stage 3 loans at the window start'],
                'default' => '5 Stage 3 loans at the window start',
            ],
            // IFRS 7 Disclosures Specification v1 (16 October 2026): sections (i) and (j) of the IFRS 9 note
            'ifrs7_interest_revenue_basis' => [
                'label' => 'Interest revenue (IFRS 7.20(b)): the headline figure',
                'description' => 'The financial statements\' income statement has to tie to the trial balance, so the note\'s headline interest-revenue figure is the GL-posted interest income, with the EIR-run figure and its cause-coded variance (EirGlReconciliationService) shown underneath as the explanation, not as a competing total. "Both, shown side by side" is for a period where Finance wants the comparison foregrounded, such as the first year this note is used. Open choice O23; Finance (Tamanda) and the CFO to confirm.',
                'options' => ['GL-posted interest income, with the EIR variance as supporting detail', 'EIR-run interest revenue (the locked engine figure)', 'Both, shown side by side'],
                'default' => 'GL-posted interest income, with the EIR variance as supporting detail',
            ],
            'ifrs7_interest_revenue_detail' => [
                'label' => 'Interest revenue (IFRS 7.20(b)): level of detail',
                'description' => 'IFRS 7.20(b) requires only the total; the split makes the Stage 3 net-basis treatment (IFRS 9 5.4.1(b)) auditable without a separate request. "Total only" is kept for a period where Stage 3 history is thin and the split would be noise.',
                'options' => ['Total only', 'Split by stage and interest basis (gross vs net)'],
                'default' => 'Split by stage and interest basis (gross vs net)',
            ],
            'ifrs7_sensitivity_disclosure' => [
                'label' => 'Sensitivity analysis (IFRS 7.35G): which tables are shown',
                'description' => 'Table 1 (the governed macro-economic scenarios already signed off each month) is close to free; Table 2 (an isolated PD/LGD stress) adds the traditional credit-risk stress auditors expect beside it, but needs Risk to own the shock sizes first (ifrs7_sensitivity_pd_shock, ifrs7_sensitivity_lgd_shock). "Macro scenarios only" is for a period before those sizes are agreed; "Not disclosed" is for a period before any scenario set is signed off, or while this disclosure is still under review. Open choice O24; the CFO and Risk to confirm.',
                'options' => ['Not disclosed', 'Macro scenarios only (Table 1)', 'Macro scenarios and isolated PD/LGD stress (Tables 1 and 2)'],
                'default' => 'Macro scenarios and isolated PD/LGD stress (Tables 1 and 2)',
            ],
            'ifrs7_sensitivity_pd_shock' => [
                'label' => 'Sensitivity analysis: the probability-of-default stress (Table 2)',
                'description' => 'The relative increase applied to the booked probability of default for Stage 1 and Stage 2 only (Stage 3\'s PD is 100 percent by definition and is not shocked). A judgement for Risk, kept as a discrete, governed choice rather than a free-typed number, in the same style as the staging thresholds. Open choice O25; Risk and the CFO to confirm.',
                'options' => ['+25% relative to the booked PD', '+50% relative to the booked PD', '+100% relative to the booked PD (doubling)'],
                'default' => '+50% relative to the booked PD',
            ],
            'ifrs7_sensitivity_lgd_shock' => [
                'label' => 'Sensitivity analysis: the loss-given-default stress (Table 2)',
                'description' => 'The increase applied to the booked loss given default, every stage, expressed in percentage points rather than a relative multiple because a relative shock on a low (secured) LGD would understate the stress Risk usually intends. Open choice O25; Risk and the CFO to confirm.',
                'options' => ['+10 percentage points', '+20 percentage points', '+30 percentage points'],
                'default' => '+20 percentage points',
            ],
        ];
    }

    /**
     * The settings that are recorded and approved like every other but that no
     * engine reads yet (system audit M12; spec 4.2), each with what the engine
     * does instead. The Governance Centre shows them as "Recorded, not yet
     * applied", so no setting looks as if it governs a figure it does not.
     * A key leaves this list in the same change that wires it into an engine.
     *
     * @return array<string, string>
     */
    public static function notYetApplied(): array
    {
        return [
            'reset_trigger' => 'A floating loan\'s EIR is re-estimated each month with the rate E-Banker charged (D38); no reset date is chosen from this setting.',
            'rate_source_precedence' => 'The rate type is taken from the Interest Policy alone: P floating, every other code fixed (spec 7.2).',
            'cash_source' => 'The engine reads actual cash from the E-Banker ledger (D35, eir:load-ledger-cash) and the contractual schedule only for a month the ledger does not cover; the value recorded here does not change that.',
            'manual_policy_handling' => 'The engine treats Interest Policy M as fixed (spec 7.2), so a manual loan keeps its EIR. The recorded value is not applied: wiring it waits on open choice O4 and review item R14 (a manual change is not automatically a market reset).',
            'counter_reset_handling' => 'Cash comes from the ledger posting by posting (D35), so a falling Repayments counter is not read.',
            'contractual_record' => 'The engine reads the E-Banker system record in every case (D20).',
            'expected_cashflow_basis' => 'Schedules are built from the core dates and rate; an LOS schedule is not read.',
            'history_before_dec_2025' => 'Superseded by loan_book_build_method, which governs every month (D22).',
            'trueup_gl_account' => 'No EIR true-up journal is proposed yet (O10).',
            'auditor_export_format' => 'The auditor exports have one shape; this setting does not choose it.',
            'keyman_insurance_treatment' => 'Each fee line is classified by a person on Fee Classification; this setting does not pre-classify keyman insurance.',
            'nascomex_preference_shares' => 'The Nascomex entries (type 401) are taken as a settlement in kind, not cash, by the build and the ledger cash (spec 3.3); this setting is not read.',
            'staging_rebuttal' => 'A rebuttal is a staging_thresholds row with its evidence, proposed and approved on Settings, Staging basis; this setting is not read.',
            'historic_materiality_assessment' => 'The historic materiality figure is not reproduced yet (O20).',
            'fee_reclass_journal' => 'No fee reclassification journal is proposed yet (O21).',
            'sicr_pd_change' => 'Each loan\'s PD at origination is now kept and the PD-change test runs in shadow (IFRS 9 Model Setup, PD at Origination); staging never applies it until this setting is adopted (PD at Origination Specification v1, decision 5).',
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

    /**
     * The governed answer to "may this administrator approve their own change?"
     * (finding H9): UAT mode, through the one maker-checker check (MakerChecker;
     * Staging Module Specification v1, section 8.9): a non-production server, the
     * setting allowing it, and the admin role.
     */
    public function adminOverrideAllowed(?object $user = null, ?CarbonInterface $asOf = null): bool
    {
        return \App\Support\MakerChecker::allowedFor($user ?? auth()->user());
    }

    /** The approved row in force for a key on a date, or null when there is none. */

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
        // an option recorded for the future that staging does not apply yet is refused, never approved and ignored
        if ($key === 'sicr_pd_change' && $value !== StagingPolicy::NOT_APPLIED) {
            throw new InvalidArgumentException('The change in PD since origination is not built yet (the PD of each loan at origination is not kept), so it cannot be switched on; staging would ignore it.');
        }
        $this->assertOverrideMayBeAllowed($key, $value);

        $effective = $this->date($effectiveFrom);

        return DB::transaction(function () use ($key, $value, $effective, $reason, $userId, $definition) {
            $this->assertEffectiveDateIsLater($key, $effective, null);
            // no back-dating into a locked month (Staging Module Specification v1, section 8.8, item 4)
            \App\Support\ReportingPeriodLock::assertNotBackDated($effective->toDateString(), "A change to '{$definition['label']}'");

            $inForce = $this->inForce($key, $effective);
            // under a named staging policy a staging rule's value is the policy's, not the stored row's: a proposal
            // that differs from the policy changes the rule (the policy turns to Custom), one that matches it does not
            $preset = isset(StagingPolicy::SETTINGS[$key]) ? $this->inForce(StagingPolicy::PRESET, $effective)?->value : null;
            $current = isset(StagingPolicy::PRESETS[$preset][$key]) ? StagingPolicy::PRESETS[$preset][$key] : $inForce?->value;
            if ($current !== null && $current === $value) {
                throw new LogicException("{$definition['label']} is already '{$value}' from " . ($preset !== null && isset(StagingPolicy::PRESETS[$preset][$key]) ? "the staging policy in force ({$preset})" : $inForce->effective_from->toDateString()) . '; there is nothing to change.');
            }

            $setting = GovernanceSetting::create([
                'key' => $key,
                'value' => $value,
                'options' => $definition['options'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'effective_from' => $effective->toDateString(),
                'set_by' => $userId,
                'reason' => $reason,
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
    /**
     * $approvedBeforeEvidenceRule: only the decisions pack passes it, for an approval its source database
     * made before the supporting-document rule came into force there (section 8.10); the approval is then
     * carried, labelled as made before supporting documents were required, instead of being refused.
     */
    public function approve(int $settingId, int $approverId, bool $allowMakerCheckerOverride = false, bool $approvedBeforeEvidenceRule = false): GovernanceSetting
    {
        return DB::transaction(function () use ($settingId, $approverId, $allowMakerCheckerOverride, $approvedBeforeEvidenceRule) {
            $setting = GovernanceSetting::query()->lockForUpdate()->findOrFail($settingId);
            if ($setting->status !== GovernanceSetting::STATUS_PROPOSED) {
                throw new LogicException('Only a proposed change can be approved.');
            }
            // the override never approves a change to its own setting: switching UAT mode off needs a
            // second person (Staging Module Specification v1, section 8.9, item 7; acceptance 23)
            $override = $allowMakerCheckerOverride && $setting->key !== \App\Support\MakerChecker::SETTING && \App\Support\MakerChecker::environmentAllows();
            if (! $override && $setting->set_by === null) {
                throw new LogicException('The proposal has no identifiable proposer and cannot be approved.');
            }
            if (! $override && (int) $setting->set_by === $approverId) {
                throw new LogicException($setting->key === \App\Support\MakerChecker::SETTING
                    ? 'The person who proposed a change to the maker-checker override cannot approve it; the override never approves a change to its own setting.'
                    : 'The person who proposed a change cannot approve it.');
            }
            $this->assertOverrideMayBeAllowed($setting->key, $setting->value);
            if (! $approvedBeforeEvidenceRule) {
                $this->assertEvidence($setting);
            }
            if ($override && (int) $setting->set_by === $approverId) {
                \App\Support\MakerChecker::recordUse('Governance Centre setting', GovernanceSetting::class, $setting->id, $approverId);
            }
            $allowMakerCheckerOverride = $override;

            $effective = CarbonImmutable::parse($setting->effective_from->toDateString());
            $this->assertEffectiveDateIsLater($setting->key, $effective, $setting->id);
            // checked again on approval: the month may have been locked since the proposal
            \App\Support\ReportingPeriodLock::assertNotBackDated($effective->toDateString(), "A change to '{$setting->label}'");

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
                    'evidence' => $approvedBeforeEvidenceRule ? \App\Support\EvidenceRule::LABEL : null,
                    'first_period_applied' => $effective->format('Y-m'),
                ],
            ]);
            $this->carryStagingPolicy($setting, $effective, $approverId);
            $this->logPresetApplied($setting, $superseded, $approverId);
            // a change to the basis of the LOS charges never posted flags every locked EIR whose fee inputs change; none is reopened
            if ($setting->key === UnpostedFeeBasis::KEY) {
                UnpostedFeeBasis::flagOnChange($superseded?->value, $setting->value, (int) $setting->id, $approverId);
            }

            return $setting->fresh();
        });
    }

    /**
     * A change to one staging setting while a named staging policy is in force
     * turns the policy to Custom (Staging Module Specification v1, setting 12).
     * Under Custom every staging setting governs, so each one is first carried
     * at the value the named policy gave it, from the same date, and only then
     * is the policy set to Custom: the approved change is the only rule that
     * moves. The carried rows are approved with the change they follow, name
     * it in their reason, and are audit-logged.
     */
    private function carryStagingPolicy(GovernanceSetting $setting, CarbonImmutable $effective, int $approverId): void
    {
        if (! isset(StagingPolicy::SETTINGS[$setting->key])) {
            return;
        }
        $preset = $this->inForce(StagingPolicy::PRESET, $effective)?->value;
        if (! isset(StagingPolicy::PRESETS[$preset])) {
            return;
        }
        $reason = "Carried with the approval of {$setting->key} (change #{$setting->id}): the staging policy '{$preset}' turns to Custom, and every other staging setting keeps the value that policy gave it.";
        $carried = [];
        foreach (StagingPolicy::PRESETS[$preset] as $key => $value) {
            if ($key !== $setting->key && $this->carry($key, $value, $effective, $setting, $approverId, $reason)) {
                $carried[$key] = $value;
            }
        }
        $this->carry(StagingPolicy::PRESET, StagingPolicy::PRESET_CUSTOM, $effective, $setting, $approverId, $reason);
        $this->memo = [];
        AuditLoggerService::log('Staging Policy Turned Custom', GovernanceSetting::class, $setting->id, [
            'old_values' => ['staging_preset' => $preset],
            'new_values' => ['staging_preset' => StagingPolicy::PRESET_CUSTOM, 'changed' => [$setting->key => $setting->value], 'carried' => $carried, 'effective_from' => $effective->toDateString()],
            'meta' => ['proposed_by' => $setting->set_by, 'approved_by' => $approverId],
        ]);
    }

    /** One approved value from a date, superseding the one in force; false when it is already in force. */
    private function carry(string $key, string $value, CarbonImmutable $effective, GovernanceSetting $cause, int $approverId, string $reason): bool
    {
        $current = $this->inForce($key, $effective);
        if ($current !== null && $current->value === $value) {
            return false;
        }
        $definition = self::catalogue()[$key];
        if ($current !== null) {
            GovernanceSettingHistory::create([
                'setting_id' => $current->id, 'key' => $current->key, 'value' => $current->value, 'options' => $current->options,
                'label' => $current->label, 'description' => $current->description, 'effective_from' => $current->effective_from->toDateString(),
                'set_by' => $current->set_by, 'approved_by' => $current->approved_by, 'approved_at' => $current->approved_at,
                'reason' => $current->reason, 'status' => $current->status, 'superseded_at' => now(), 'superseded_by' => $approverId,
                'superseded_by_setting_id' => $cause->id,
            ]);
        }
        $attributes = ['value' => $value, 'options' => $definition['options'], 'label' => $definition['label'], 'description' => $definition['description'],
            'set_by' => $cause->set_by, 'approved_by' => $approverId, 'approved_at' => now(), 'reason' => $reason, 'status' => GovernanceSetting::STATUS_APPROVED];
        if ($current !== null && $current->effective_from->toDateString() === $effective->toDateString()) {
            $current->update($attributes);
        } else {
            GovernanceSetting::create($attributes + ['key' => $key, 'effective_from' => $effective->toDateString()]);
        }
        $this->memo = [];

        return true;
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
        // under a named staging policy each staging rule is the policy's, whatever its own row says
        $preset = $this->inForce(StagingPolicy::PRESET, $asOf)?->value;
        foreach (self::catalogue() as $key => $definition) {
            $inForce = $this->inForce($key, $asOf);
            $overview[] = [
                'set_by_policy' => isset(StagingPolicy::SETTINGS[$key], StagingPolicy::PRESETS[$preset][$key])
                    ? ['policy' => $preset, 'value' => StagingPolicy::PRESETS[$preset][$key]] : null,
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'options' => $definition['options'],
                'default' => $definition['default'],
                // recorded and approved, but read by no engine yet: shown as such (audit M12)
                'applied' => ! isset(self::notYetApplied()[$key]),
                'not_applied_note' => self::notYetApplied()[$key] ?? null,
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
     * Read an ifrs7_sensitivity_pd_shock option into a multiplier on the booked
     * probability of default (IFRS 7 Disclosures Specification v1, section 3.4):
     * "+50% relative to the booked PD" returns 1.5, "doubling" returns 2.0.
     */
    public static function parsePdShock(string $option): float
    {
        if (preg_match('/\+(\d+(?:\.\d+)?)\s*%/', $option, $m) === 1) {
            return 1 + ((float) $m[1]) / 100;
        }

        throw new InvalidArgumentException("The PD shock option '{$option}' names no relative percentage.");
    }

    /**
     * Read an ifrs7_sensitivity_lgd_shock option into an add-on to the booked
     * loss given default, as a fraction (IFRS 7 Disclosures Specification v1,
     * section 3.4): "+20 percentage points" returns 0.20.
     */
    public static function parseLgdShock(string $option): float
    {
        if (preg_match('/\+(\d+(?:\.\d+)?)\s*percentage\s*points?/i', $option, $m) === 1) {
            return ((float) $m[1]) / 100;
        }

        throw new InvalidArgumentException("The LGD shock option '{$option}' names no percentage-point add-on.");
    }

    /**
     * A change applies forward only: its effective date must be later than
     * every approved change before it, so no governed period is restated.
     * The same (key, date) may exist once, whatever its status.
     */
    /**
     * UAT mode switched from the server console (Staging Module Specification
     * v1, section 8.9, item 3; governance:uat-mode). On an existing database the
     * override setting already exists, so a fresh install's UAT seeding does not
     * reach it: someone with server access switches it on, only on a
     * non-production server, from today, without a second person. Switching it
     * off works on any server. Either way the value in force is superseded with
     * its history, and the change is audit-logged with the person and the
     * environment.
     */
    public function switchUatMode(bool $on, ?int $userId): GovernanceSetting
    {
        $key = \App\Support\MakerChecker::SETTING;
        $value = $on ? \App\Support\MakerChecker::ALLOWED : \App\Support\MakerChecker::NOT_ALLOWED;
        $this->assertOverrideMayBeAllowed($key, $value);
        $today = CarbonImmutable::today();
        $definition = self::catalogue()[$key];
        $reason = $on
            ? 'Switched on for UAT from the server console (APP_ENV=' . app()->environment() . '): administrators may approve their own proposals, each recorded on the self-approvals report; not a MAIIC approval'
            : 'Switched off from the server console (APP_ENV=' . app()->environment() . '): every workflow needs a second person again';

        return DB::transaction(function () use ($key, $value, $today, $definition, $reason, $userId) {
            $current = $this->inForce($key, $today);
            if ($current !== null && $current->value === $value) {
                return $current;
            }
            if ($current !== null) {
                GovernanceSettingHistory::create([
                    'setting_id' => $current->id, 'key' => $current->key, 'value' => $current->value, 'options' => $current->options,
                    'label' => $current->label, 'description' => $current->description, 'effective_from' => $current->effective_from->toDateString(),
                    'set_by' => $current->set_by, 'approved_by' => $current->approved_by, 'approved_at' => $current->approved_at,
                    'reason' => $current->reason, 'status' => $current->status, 'superseded_at' => now(), 'superseded_by' => $userId,
                    'superseded_by_setting_id' => null,
                ]);
            }
            $attributes = ['value' => $value, 'options' => $definition['options'], 'label' => $definition['label'], 'description' => $definition['description'],
                'set_by' => $userId, 'approved_by' => null, 'approved_at' => now(), 'reason' => $reason, 'status' => GovernanceSetting::STATUS_APPROVED];
            if ($current !== null && $current->effective_from->toDateString() === $today->toDateString()) {
                $current->update($attributes);
                $row = $current->fresh();
            } else {
                $row = GovernanceSetting::create($attributes + ['key' => $key, 'effective_from' => $today->toDateString()]);
            }
            $this->memo = [];
            AuditLoggerService::log('UAT Mode Switched From The Console', GovernanceSetting::class, $row->id, [
                'old_values' => $current ? ['value' => $current->value] : null,
                'new_values' => ['key' => $key, 'value' => $value, 'effective_from' => $today->toDateString(), 'reason' => $reason],
                'meta' => ['user' => $userId, 'environment' => app()->environment()],
            ]);

            return $row;
        });
    }

    /**
     * Reject a proposal (Staging Module Specification v1, section 8.2): by a
     * person other than its proposer, with a reason. It stays on record as
     * REJECTED with its documents.
     */
    public function reject(int $settingId, int $userId, string $reason): GovernanceSetting
    {
        return $this->review($settingId, $userId, $reason, GovernanceSetting::STATUS_REJECTED);
    }

    /** Withdraw a proposal: by its proposer, with a reason. It stays on record as WITHDRAWN. */
    public function withdraw(int $settingId, int $userId, string $reason): GovernanceSetting
    {
        return $this->review($settingId, $userId, $reason, GovernanceSetting::STATUS_WITHDRAWN);
    }

    private function review(int $settingId, int $userId, string $reason, string $status): GovernanceSetting
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException(($status === GovernanceSetting::STATUS_REJECTED ? 'A rejection' : 'A withdrawal') . ' needs a specific reason of at least 10 characters.');
        }

        return DB::transaction(function () use ($settingId, $userId, $reason, $status) {
            $setting = GovernanceSetting::query()->lockForUpdate()->findOrFail($settingId);
            if ($setting->status !== GovernanceSetting::STATUS_PROPOSED) {
                throw new LogicException('Only a proposed change can be ' . strtolower($status) . '.');
            }
            if ($status === GovernanceSetting::STATUS_WITHDRAWN && $setting->set_by !== null && (int) $setting->set_by !== $userId) {
                throw new LogicException('Only the person who proposed the change can withdraw it; a second person rejects it instead.');
            }
            $setting->update(['status' => $status, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_reason' => $reason]);
            AuditLoggerService::log($status === GovernanceSetting::STATUS_REJECTED ? 'EIR Governance Setting Rejected' : 'EIR Governance Setting Withdrawn', GovernanceSetting::class, $setting->id, [
                'new_values' => ['key' => $setting->key, 'value' => $setting->value, 'effective_from' => $setting->effective_from->toDateString(), 'reason' => $reason],
                'meta' => ['user' => $userId, 'proposed_by' => $setting->set_by],
            ]);

            return $setting->fresh();
        });
    }

    /** The settings whose approval needs at least one supporting document (section 8.3), from the catalogue. */
    public static function evidenceRequired(string $key): bool
    {
        return (bool) (self::catalogue()[$key]['evidence_required'] ?? false);
    }

    private function assertEvidence(GovernanceSetting $setting): void
    {
        if (! self::evidenceRequired($setting->key)) {
            return;
        }
        // attached to this change or linked to it from another decision (section 8.7)
        $documents = \App\Support\SupportingDocuments::count(GovernanceSetting::class, (int) $setting->id);
        if ($documents === 0) {
            throw new LogicException("A change to '{$setting->label}' needs a supporting document before it is approved: attach the minute, decision or analysis behind it (Staging Module Specification v1, section 8.3).");
        }
    }

    /**
     * A staging policy applied (section 8.2): one entry for the policy and one
     * for each staging rule whose value it changes, all linked to the change.
     */
    private function logPresetApplied(GovernanceSetting $setting, ?GovernanceSetting $superseded, int $approverId): void
    {
        if ($setting->key !== StagingPolicy::PRESET || ! isset(StagingPolicy::PRESETS[$setting->value])) {
            return;
        }
        $before = $superseded !== null && isset(StagingPolicy::PRESETS[$superseded->value]) ? StagingPolicy::PRESETS[$superseded->value] : [];
        $after = StagingPolicy::PRESETS[$setting->value];
        AuditLoggerService::log('Staging Preset Applied', GovernanceSetting::class, $setting->id, [
            'old_values' => $superseded ? ['staging_preset' => $superseded->value] : null,
            'new_values' => ['staging_preset' => $setting->value, 'effective_from' => $setting->effective_from->toDateString(), 'rules' => $after],
            'meta' => ['user' => $approverId, 'change' => $setting->id],
        ]);
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                AuditLoggerService::log('Staging Rule Set By Preset', GovernanceSetting::class, $setting->id, [
                    'old_values' => isset($before[$key]) ? [$key => $before[$key]] : null,
                    'new_values' => [$key => $value, 'by_preset' => $setting->value, 'effective_from' => $setting->effective_from->toDateString()],
                    'meta' => ['user' => $approverId, 'change' => $setting->id],
                ]);
            }
        }
    }

    /** The production guard (section 8.9, item 2): the override cannot be allowed on a production server. */
    private function assertOverrideMayBeAllowed(string $key, string $value): void
    {
        if ($key === \App\Support\MakerChecker::SETTING && $value === \App\Support\MakerChecker::ALLOWED && ! \App\Support\MakerChecker::environmentAllows()) {
            throw new LogicException('The maker-checker override cannot be allowed on a production server (APP_ENV=production). UAT mode is for a UAT server only (decision S11).');
        }
    }

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
            $row->status === GovernanceSetting::STATUS_REJECTED => 'REJECTED',
            $row->status === GovernanceSetting::STATUS_WITHDRAWN => 'WITHDRAWN',
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
