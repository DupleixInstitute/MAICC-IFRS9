<?php

namespace Database\Seeders;

use App\Models\GlAccountScope;
use Illuminate\Database\Seeder;

/**
 * The GL codes the EIR engine reads, and what each one is (spec §3.4.3).
 *
 * Seeded rather than hardcoded because "these are the accounts in EIR scope" is
 * an accounting judgement that Dr Thom signs off, and a constant inside a
 * service class cannot be reviewed by a CFO. Rows with in_eir_scope = false and
 * a note ending in "RULING REQUIRED" are the ones still needing that sign-off.
 *
 * Titles are transcribed from the delivered trial balances rather than tidied,
 * including MAIIC's own spellings ("Legal  Fees" with two spaces, "Impairement"),
 * so a reader can grep a code here and find the same string in the source file.
 */
class GlAccountScopeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->accounts() as $account) {
            GlAccountScope::updateOrCreate(
                ['chart' => $account['chart'] ?? 'EBANKER', 'gl_code' => $account['gl_code']],
                $account + [
                    'chart' => 'EBANKER',
                    'statement' => GlAccountScope::isProfitAndLossCode($account['gl_code']) ? 'PL' : 'BS',
                    'normal_balance' => GlAccountScope::defaultNormalBalance($account['gl_code']),
                    'in_eir_scope' => false,
                    'retired' => false,
                ]
            );
        }
    }

    /** @return list<array<string,mixed>> */
    private function accounts(): array
    {
        return array_merge(
            $this->loanInterestIncome(),
            $this->feeIncome(),
            $this->outOfScopeIncome(),
            $this->impairmentAndExpense(),
            $this->awaitingRuling(),
            $this->retiredCodes(),
            $this->quickBooksMappings(),
        );
    }

    /** Door 2 — the interest the engine reproduces on an effective-interest basis. */
    private function loanInterestIncome(): array
    {
        $interest = [
            ['4215', 'Interest On MAIIC Agricultural Loans', 'MAIIC'],
            ['4216', 'Interest On MAIIC Industrial Loans', 'MAIIC'],
            ['4218', 'Interest On FInES Agricultural Loans', 'FINES'],
            ['4219', 'Interest On FInES Industrial Loans', 'FINES'],
            ['4221', 'Interest On Mega Farm Fertilizer Loans', 'MEGAFARM'],
            ['4222', 'Interest On Mega Farm Seed Loans', 'MEGAFARM'],
            ['4223', 'Interest On Mega Farm Working Capital Loans', 'MEGAFARM'],
            ['4224', 'Interest On Mega Farm Equipment Loans', 'MEGAFARM'],
            ['4260', 'Interest On Mega Farm Irrigation', 'MEGAFARM'],
            ['4262', 'Interest On Mega Farm Mega Farm Pesticides Loans', 'MEGAFARM'],
        ];

        $rows = [];
        foreach ($interest as [$code, $title, $portfolio]) {
            $rows[] = [
                'gl_code' => $code, 'gl_title' => $title, 'category' => 'INTEREST_INCOME',
                'eir_door' => 2, 'in_eir_scope' => true, 'portfolio' => $portfolio,
            ];
        }

        // Present only from January 2026 in the delivered corpus, not November
        // 2025 as §3.4.3 states. Closed item #21 confirms the term-loan PRODUCT
        // launched in November 2025 (balance-sheet code 1050401), but no
        // interest reached this income code until 2026 — a different fact, and
        // one nobody has confirmed. In scope either way; the note stands so the
        // 2025 tie-out does not silently expect a figure that is not there.
        $rows[] = [
            'gl_code' => '42019', 'gl_title' => 'Interest On MAIIC Term Loan',
            'category' => 'INTEREST_INCOME', 'eir_door' => 2, 'in_eir_scope' => true, 'portfolio' => 'MAIIC',
            'notes' => 'Appears Jan 2026 -> Jul 2026 only. Spec §3.4.3 says "from Nov 2025" and also lists a '
                . 'code 4201, which appears in none of the 19 trial balances. Confirm whether 2025 term-loan '
                . 'interest was posted elsewhere before relying on 2025 coverage for this product.',
        ];

        return $rows;
    }

    /** Integral fees — the inputs that make EIR differ from the contractual rate (B5.4.1). */
    private function feeIncome(): array
    {
        return [
            [
                'gl_code' => '4871', 'gl_title' => 'Legal  Fees', 'category' => 'FEE_INCOME',
                'in_eir_scope' => true,
                'notes' => 'Contains MK31,501,724.00 narrated as ARRANGEMENT fees (18% of the 2025 balance) and '
                    . 'the practice continues into 2026 (open item #26). No EIR impact - both types are integral '
                    . 'and the combined total is unchanged - but the legal-vs-arrangement disclosure split is '
                    . 'affected. Do not reclassify pending MAIIC.',
            ],
            ['gl_code' => '4873', 'gl_title' => 'Arrangement Fees', 'category' => 'FEE_INCOME', 'in_eir_scope' => true],
            [
                'gl_code' => '4874', 'gl_title' => 'Insurance Fees', 'category' => 'FEE_INCOME', 'in_eir_scope' => true,
                'notes' => 'Integrality is a B5.4.1 judgement per fee, not per account. Keyman insurance on EcoGen '
                    . 'is open item #4 and unresolved.',
            ],
            ['gl_code' => '4875', 'gl_title' => 'PCG Guarantee Fees', 'category' => 'FEE_INCOME', 'in_eir_scope' => true],
        ];
    }

    /**
     * Explicitly out. These exist so a control total can exclude them by rule
     * rather than by whoever writes the query remembering to (§3.4.3).
     */
    private function outOfScopeIncome(): array
    {
        return [
            ['gl_code' => '4205', 'gl_title' => 'Investment Interest Income -Tbill', 'category' => 'INVESTMENT_INCOME',
                'notes' => 'Treasury income, not lending. Outside the EIR engine.'],
            ['gl_code' => '4211', 'gl_title' => 'Investment Income USD CALL ACCOUNT', 'category' => 'INVESTMENT_INCOME',
                'notes' => 'Treasury income, not lending. Outside the EIR engine.'],
            ['gl_code' => '4213', 'gl_title' => 'Investment Income-Coupon Settle', 'category' => 'INVESTMENT_INCOME',
                'notes' => 'Treasury income, not lending. Outside the EIR engine.'],
        ];
    }

    private function impairmentAndExpense(): array
    {
        return [
            [
                'gl_code' => '6242', 'gl_title' => 'Impairement Of Financial Asset', 'category' => 'IMPAIRMENT',
                'eir_door' => 3, 'in_eir_scope' => true,
                'notes' => 'Present Jan-Nov 2025 and in the pre-closing December sheet, but in NO 2026 month through '
                    . 'July. Confirm whether no impairment has been charged in 2026 YTD or whether it posts elsewhere. '
                    . 'Carries a MK-2,174,499,665 audit adjustment in the AFS bridge (§3.4.5).',
            ],
            ['gl_code' => '6340', 'gl_title' => 'Interest Expense', 'category' => 'INTEREST_EXPENSE',
                'notes' => 'Funding cost, not asset yield. Outside the EIR engine.'],
        ];
    }

    /**
     * In the delivered corpus but absent from §3.4.3 — nobody has ruled on them.
     *
     * Left OUT of scope deliberately. An unruled account silently included
     * inflates the ledger side of a reconciliation the engine then cannot
     * explain; excluded, it shows up as an unexplained gap someone investigates.
     * Wrong in the safe direction.
     */
    private function awaitingRuling(): array
    {
        return [
            [
                'gl_code' => '4207', 'gl_title' => 'Interest On Staff Loans', 'category' => 'INTEREST_INCOME',
                'notes' => 'Staff loans are financial assets at amortised cost and technically in IFRS 9 scope, but '
                    . 'they are not the book this engagement models and are often below-market. RULING REQUIRED.',
            ],
            [
                'gl_code' => '4872', 'gl_title' => 'Consultancy Fees', 'category' => 'FEE_INCOME',
                'notes' => 'Only integral if charged as part of originating a facility. If it is standalone advisory '
                    . 'revenue it is IFRS 15, not an EIR input. RULING REQUIRED.',
            ],
            [
                'gl_code' => '4890', 'gl_title' => 'Sundry Income', 'category' => 'OTHER_INCOME',
                'notes' => 'Assumed outside the EIR. Confirm no loan-related fees are swept here. RULING REQUIRED.',
            ],
            [
                'gl_code' => '4892', 'gl_title' => 'Grants', 'category' => 'OTHER_INCOME',
                'notes' => 'Grant income, assumed outside the EIR. May interact with the FinES below-market '
                    . 'concessional analysis (day-one fair value). RULING REQUIRED.',
            ],
        ];
    }

    /**
     * Retired loan-book codes MAIIC confirmed in writing (closed item #20).
     *
     * Flagged so ingestion expects no trial-balance counterpart, instead of
     * reporting the same non-defect as a mapping error every single run.
     */
    private function retiredCodes(): array
    {
        return [
            ['gl_code' => '1050103', 'gl_title' => 'Retired loan GL code', 'category' => 'ASSET', 'retired' => true,
                'notes' => 'Retired. MAIIC confirmed 2026-08-19 the active accounts are undrawn and rebooked under '
                    . 'correct codes. Expect no TB counterpart (closed item #20).'],
            ['gl_code' => '1050203', 'gl_title' => 'Retired loan GL code', 'category' => 'ASSET', 'retired' => true,
                'notes' => 'Retired. See 1050103 (closed item #20).'],
        ];
    }

    /**
     * The QuickBooks side of §3.4.4. MAIIC keeps statutory accounts in
     * QuickBooks and the loan book in E-Banker, and the codes differ — the same
     * term-loan interest is 42019 in one ledger and 4206 in the other. Seeding
     * the pairs stops anyone assuming they are equal; the full 191-row map in
     * the AFS workbook's `TB December 2025` sheet is a separate ingestion.
     */
    private function quickBooksMappings(): array
    {
        return [
            ['chart' => 'QUICKBOOKS', 'gl_code' => '4206', 'gl_title' => 'Interest on Term Loans-Maiic',
                'category' => 'INTEREST_INCOME', 'eir_door' => 2, 'in_eir_scope' => true, 'quickbooks_code' => '4206',
                'notes' => 'AFS-side counterpart of E-Banker 42019 / 4201. MK2,128,427,032.36 in the 2025 AFS TB.'],
            ['chart' => 'QUICKBOOKS', 'gl_code' => '4870', 'gl_title' => 'Services Income', 'category' => 'FEE_INCOME',
                'notes' => 'Parent of the nested QuickBooks fee accounts (4870 Services Income:4871 Legal fees).'],
        ];
    }
}
