<?php

namespace Database\Seeders;

use App\Models\EirAccountingRule;
use Illuminate\Database\Seeder;

/**
 * The standing fee-treatment rulebook.
 *
 * IFRS 9 Appendix A defines the effective interest rate as the rate that
 * exactly discounts estimated future cash payments or receipts through the
 * expected life of the instrument to the gross carrying amount. B5.4.1 brings
 * into that calculation all fees and points paid or received that are an
 * integral part of the effective interest rate, together with transaction
 * costs. B5.4.2 then narrows "transaction cost" to an *incremental* cost
 * directly attributable to the acquisition or issue — a cost that would not
 * have arisen had the entity not made the loan — and expressly excludes
 * internal administrative and holding costs. B5.4.3 lists the fees that are
 * integral, chiefly origination fees on creating the asset and commitment fees
 * where drawdown is probable.
 *
 * The dividing line every rule below applies is the one banks use in practice:
 *
 *   Was the amount an unavoidable cost or a price of PUTTING THE LOAN ON THE
 *   BOOK?  ->  integral, spread through the yield.
 *
 *   Is it the price of a SERVICE rendered over time, or a charge CONTINGENT on
 *   something that had not happened at origination?  ->  period income under
 *   IFRS 15, outside the EIR.
 *
 * PRIORITY BANDS. FeeRuleMatcher takes the first match in ascending priority,
 * so the exclusions sit ABOVE the inclusions deliberately. A general
 * "all legal costs are integral" rule placed first would swallow recovery
 * litigation costs, which are impairment items and must never reach the EIR.
 *
 *   10-19  hard exclusions that must beat every later rule
 *   20-39  integral: the cost of originating the facility
 *   40-59  period income or expense: services and contingent charges
 *   90-99  type-level catch-alls
 *
 * Deliberately NOT covered: a bare `legal` or `other` line carrying no
 * distinguishing keyword matches nothing and stays unclassified, because
 * whether it is origination or servicing cannot be read off the record. An
 * unmatched fee is a question for a human, and silence is the correct output.
 *
 * EVERY RULE IS SEEDED UNAPPROVED. FeeRuleMatcher only considers rules that
 * are active AND carry an approved_at, so nothing here changes a single number
 * until an accounting owner approves it. Seeding them approved would put an
 * accounting policy into production by running a database command.
 */
class EirAccountingRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rules() as $rule) {
            EirAccountingRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule + [
                    'gl_account_ref' => null,
                    'cashflow_direction' => null,
                    'active' => true,
                    'approved_by' => null,
                    'approved_at' => null,
                ]
            );
        }
    }

    private function rules(): array
    {
        return [
            // ---------------------------------------------------------------
            // 10-19  HARD EXCLUSIONS — these must outrank every inclusion.
            // ---------------------------------------------------------------
            [
                'name' => 'Internal administrative and staff cost',
                'fee_type' => 'other',
                'description_contains' => 'internal',
                'proposed_integral' => false,
                'priority' => 10,
                'rationale' => 'IFRS 9 B5.4.2 expressly excludes internal administrative costs and holding costs from transaction costs. Staff time, overheads and internal processing are incurred whether or not any individual facility is written, so they are not incremental and never enter the EIR.',
            ],
            [
                'name' => 'Staff, salary or overhead recharge',
                'fee_type' => 'other',
                'description_contains' => 'salary',
                'proposed_integral' => false,
                'priority' => 11,
                'rationale' => 'IFRS 9 B5.4.2: internal costs are not incremental to a specific facility. Recharging them internally does not convert them into a transaction cost.',
            ],
            [
                'name' => 'Recovery, enforcement or litigation legal cost',
                'fee_type' => 'legal',
                'description_contains' => 'recovery',
                'proposed_integral' => false,
                'priority' => 12,
                'rationale' => 'Costs of pursuing a borrower arise after default, not on origination, so they fail the "directly attributable to acquisition" test in IFRS 9 B5.4.2. They belong to the impairment assessment as recovery costs within LGD, not to the EIR. Ranked above the origination legal rule so it can never capture them.',
            ],
            [
                'name' => 'Enforcement or collection legal cost',
                'fee_type' => 'legal',
                'description_contains' => 'enforcement',
                'proposed_integral' => false,
                'priority' => 13,
                'rationale' => 'As for recovery costs: incurred in enforcing security after a credit event, therefore an impairment cost and not part of the original effective yield.',
            ],
            [
                'name' => 'General legal expenditure not tied to a facility',
                'fee_type' => 'legal',
                'description_contains' => 'general',
                'proposed_integral' => false,
                'priority' => 14,
                'rationale' => 'Retainers and general counsel costs are not incremental to any one loan (IFRS 9 B5.4.2) and cannot be spread through the yield of a facility they do not belong to.',
            ],
            [
                'name' => 'Penalty, default or late payment charge',
                'fee_type' => 'default',
                'description_contains' => null,
                'proposed_integral' => false,
                'priority' => 16,
                'rationale' => 'A default charge is contingent on borrower behaviour that had not occurred at initial recognition, so it is not among the estimated cash flows used to set the original EIR. Recognise it in profit or loss when the entitlement arises. Note: penal interest already collected does form part of actual cash received in the amortisation roll-forward; it simply does not change the rate.',
            ],

            // ---------------------------------------------------------------
            // 20-39  INTEGRAL — the cost of putting the loan on the book.
            // ---------------------------------------------------------------
            [
                'name' => 'MAIIC GL 4873 arrangement fee received',
                'fee_type' => 'arrangement',
                'description_contains' => null,
                'gl_account_ref' => '4873',
                'cashflow_direction' => 'RECEIVED',
                'proposed_integral' => true,
                'priority' => 20,
                'rationale' => 'MAIIC GL 4873 is the governed arrangement-fee income account. A fee received for originating or arranging the loan is integral under IFRS 9 B5.4.3(a) and is deferred through the effective yield. This rule is a proposal only: the imported line must identify the contract and retain its posting evidence, and an independent reviewer must approve the classification before it reaches the EIR calculation.',
            ],
            [
                'name' => 'MAIIC GL 4871 legal fee received on loan origination',
                'fee_type' => 'legal',
                'description_contains' => null,
                'gl_account_ref' => '4871',
                'cashflow_direction' => 'RECEIVED',
                'proposed_integral' => true,
                'priority' => 21,
                'rationale' => 'MAIIC GL 4871 records legal fees charged in connection with loan drawdowns. The proposed integral treatment applies only where the charge is directly attributable to originating the identified facility. It remains subject to line-level classification and independent review so that general legal services, recoveries and post-default enforcement costs cannot enter the original EIR.',
            ],
            [
                'name' => 'Arrangement, facility or front-end fee',
                'fee_type' => 'arrangement',
                'description_contains' => null,
                'proposed_integral' => true,
                // Ranked below the structuring rule: a keyword-less rule on the
                // same fee type would otherwise swallow every specialisation of
                // it before the more precise rule was ever reached.
                'priority' => 22,
                'rationale' => 'IFRS 9 B5.4.3(a): origination fees received relating to the creation of a financial asset are an integral part of the effective interest rate. This is the principal reason the EIR differs from the contractual rate, and it is the fee MAIIC applies at 0.25%-2.5% across its own EIR assessment. Deferred at drawdown and released through interest income over the expected life.',
            ],
            [
                'name' => 'Structuring or underwriting fee',
                'fee_type' => 'arrangement',
                'description_contains' => 'structuring',
                'proposed_integral' => true,
                'priority' => 20,
                'rationale' => 'Compensation for arranging and underwriting the facility itself rather than for a separable service; integral under IFRS 9 B5.4.3(a).',
            ],
            [
                'name' => 'Legal cost directly attributable to origination',
                'fee_type' => 'legal',
                'description_contains' => 'origination',
                'proposed_integral' => true,
                'priority' => 24,
                'rationale' => 'An incremental, directly attributable cost of creating the asset under IFRS 9 B5.4.2 — it would not have been incurred had the facility not been written. Increases the initial net investment and therefore reduces the EIR.',
            ],
            [
                'name' => 'Security perfection, registration or mortgage cost',
                'fee_type' => 'legal',
                'description_contains' => 'perfection',
                'proposed_integral' => true,
                'priority' => 25,
                'rationale' => 'Registering a debenture or mortgage is a precondition of advancing the funds and would not arise otherwise, so it is incremental and directly attributable (IFRS 9 B5.4.2).',
            ],
            [
                'name' => 'Stamp duty or registration levy on security',
                'fee_type' => 'levy',
                'description_contains' => 'registration',
                'proposed_integral' => true,
                'priority' => 26,
                'rationale' => 'A statutory charge triggered by perfecting security for this facility. Incremental and directly attributable, therefore a transaction cost under IFRS 9 B5.4.2.',
            ],
            [
                'name' => 'Valuation or appraisal at origination',
                'fee_type' => 'appraisal',
                'description_contains' => null,
                'proposed_integral' => true,
                'priority' => 28,
                'rationale' => 'A third-party valuation obtained to support the credit decision and secure the facility is incremental and directly attributable (IFRS 9 B5.4.2). A revaluation obtained later for monitoring is not, and should be excluded by description.',
            ],
            [
                'name' => 'Broker, agent or introducer commission paid',
                'fee_type' => 'other',
                'description_contains' => 'commission',
                'proposed_integral' => true,
                'priority' => 30,
                'rationale' => 'Commission payable only because the loan was written is the textbook incremental transaction cost in IFRS 9 B5.4.2. Paid, so it increases the initial net investment and reduces the EIR.',
            ],
            [
                'name' => 'Origination fee rebate, refund or netting credit',
                'fee_type' => 'legal',
                'description_contains' => 'rebate',
                'proposed_integral' => true,
                'priority' => 32,
                'rationale' => 'A credit that reduces an amount already treated as integral must follow the same treatment, or the net initial investment is overstated. Amounts are signed, so the negative line reduces the integral total rather than being discarded.',
            ],
            [
                'name' => 'Commitment fee where drawdown is probable',
                'fee_type' => 'other',
                'description_contains' => 'commitment',
                'proposed_integral' => true,
                'priority' => 34,
                'rationale' => 'IFRS 9 B5.4.3(a): a commitment fee is integral where it is probable the entity will enter into a specific lending arrangement, deferred and treated as an adjustment to the EIR on drawdown. JUDGEMENT REQUIRED: where drawdown is not probable, or the commitment expires undrawn, the fee is revenue under IFRS 15 recognised over the commitment period. Confirm the drawdown assessment before approving this rule.',
            ],

            // ---------------------------------------------------------------
            // 40-59  PERIOD INCOME OR EXPENSE — services and contingent items.
            // ---------------------------------------------------------------
            [
                'name' => 'Monitoring or supervision fee',
                'fee_type' => 'other',
                'description_contains' => 'monitoring',
                'proposed_integral' => false,
                'priority' => 40,
                'rationale' => 'Payment for an ongoing service performed after origination. Revenue under IFRS 15 recognised as the service is delivered, not a component of the original yield.',
            ],
            [
                'name' => 'Anniversary or annual review fee',
                'fee_type' => 'other',
                'description_contains' => 'anniversary',
                'proposed_integral' => false,
                'priority' => 41,
                'rationale' => 'A recurring charge for periodic review is consideration for a distinct service; it was not a term of putting the loan on the book. IFRS 15 revenue.',
            ],
            [
                'name' => 'Administration, service or ledger fee',
                'fee_type' => 'other',
                'description_contains' => 'administration',
                'proposed_integral' => false,
                'priority' => 44,
                'rationale' => 'Account servicing is a distinct performance obligation satisfied over time, outside the EIR (IFRS 15).',
            ],
            [
                'name' => 'Prepayment or early settlement fee',
                'fee_type' => 'other',
                'description_contains' => 'prepayment',
                'proposed_integral' => false,
                'priority' => 46,
                'rationale' => 'Contingent on a borrower decision not assumed at initial recognition, so it is not in the original cash-flow estimate. Recognise when the settlement occurs. Where prepayment becomes expected across a portfolio, the answer is to revise the estimated cash flows under IFRS 9 B5.4.6, not to reclassify the fee.',
            ],
            [
                'name' => 'Restructuring, modification or amendment fee',
                'fee_type' => 'other',
                'description_contains' => 'restructur',
                'proposed_integral' => false,
                'priority' => 48,
                'rationale' => 'Not integral to the ORIGINAL EIR, which is the rate this engine solves. Under IFRS 9 5.4.3 a non-substantial modification adjusts the gross carrying amount, discounted at the original EIR, with the fee included in that recalculation; a substantial modification triggers derecognition and a new instrument with its own EIR. Either way it is modification accounting, not an input to the original rate.',
            ],
            [
                'name' => 'Syndication or participation fee with no exposure retained',
                'fee_type' => 'other',
                'description_contains' => 'syndication',
                'proposed_integral' => false,
                'priority' => 50,
                'rationale' => 'IFRS 9 B5.4.3(c): a syndication fee earned for arranging a facility in which the entity retains no part, or retains a share on the same effective yield as other participants, is payment for a service. IFRS 15 revenue.',
            ],
            [
                'name' => 'Insurance premium recharged to the borrower',
                'fee_type' => 'levy',
                'description_contains' => 'insurance',
                'proposed_integral' => false,
                // Ranked below keyman: "keyman insurance premium" contains the
                // word insurance, so the general rule would otherwise answer
                // for a case that is explicitly still undecided.
                'priority' => 52,
                'rationale' => 'Where the lender collects a premium and remits it to an insurer it acts as agent, and the pass-through is neither income nor a transaction cost. Integral treatment applies only where the lender is principal and the cover is a condition of lending it bears the cost of.',
            ],
            [
                'name' => 'Keyman insurance premium — AWAITING RULING',
                'fee_type' => 'levy',
                'description_contains' => 'keyman',
                'proposed_integral' => false,
                'priority' => 51,
                'rationale' => 'OPEN ITEM 4, NOT YET DECIDED — do not approve this rule until Dr Thom rules. If the policy is a condition of the facility and the cost is borne by MAIIC, it is arguably an incremental cost of lending and integral. If it is recharged to the borrower or is a general credit-protection arrangement, it is not. The default here is the conservative reading (not integral); the ruling may reverse it.',
            ],
            [
                'name' => 'Government tax, VAT or withholding on fees',
                'fee_type' => 'levy',
                'description_contains' => 'tax',
                'proposed_integral' => false,
                'priority' => 56,
                'rationale' => 'A tax settled on behalf of, or borne by, the counterparty is not a cost of acquiring the asset. Only levies that are unavoidable and incremental to originating this facility qualify under IFRS 9 B5.4.2.',
            ],

            // ---------------------------------------------------------------
            // 90-99  TYPE-LEVEL CATCH-ALLS. Nothing catches a bare `legal` or
            // `other` line: those genuinely need a human to say which side of
            // the origination/servicing line they fall on.
            // ---------------------------------------------------------------
            [
                'name' => 'Any other levy not otherwise classified',
                'fee_type' => 'levy',
                'description_contains' => null,
                'proposed_integral' => false,
                'priority' => 96,
                'rationale' => 'Backstop. A levy is only a transaction cost where it is unavoidable and incremental to this facility (IFRS 9 B5.4.2); absent evidence of that, the conservative treatment is period expense. Override per line where origination evidence exists.',
            ],
        ];
    }
}
