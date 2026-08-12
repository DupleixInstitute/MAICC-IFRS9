<?php

namespace App\Imports;

/**
 * Canonical header aliases for the contract master file (Extract A).
 *
 * Keys are headings that may appear in a source file; values are the stable
 * internal field names consumed by ContractMasterImportService. Saved
 * mappings from the intake screen override these defaults — the aliases only
 * spare the operator the first mapping pass on the VGCBS-shaped file.
 *
 * Open item 10 in the spec: only ~13 Extract A columns were sampled, so the
 * origination-fee and date aliases below are the requested field names (22 Jul
 * request) and must be confirmed against the delivered file before an operator
 * relies on the defaults rather than mapping by hand.
 */
class ContractMasterImport
{
    public static function aliases(): array
    {
        return [
            'RUN_ID' => 'run_id',
            'RUN ID' => 'run_id',
            'CUSTOMER_ID' => 'customer_id',
            'CUSTOMER ID' => 'customer_id',
            'CLIENT_ID' => 'customer_id',
            'LOAN_ACCOUNT_NUMBER' => 'contract_id',
            'LOAN ACCOUNT NUMBER' => 'contract_id',
            'ACCOUNT_NUMBER' => 'contract_id',
            'CONTRACT_ID' => 'contract_id',
            'SUB_ACCOUNT_NO' => 'sub_account_no',
            'SUB ACCOUNT NO' => 'sub_account_no',
            'GL_ACCOUNT_CODE' => 'gl_account_code',
            'GL ACCOUNT CODE' => 'gl_account_code',
            'CURRENCY' => 'currency',
            'PRODUCT_TYPE' => 'product_type',
            'PRODUCT TYPE' => 'product_type',

            'LOAN_START_DATE' => 'origination_date',
            'LOAN START DATE' => 'origination_date',
            'DISBURSEMENT_DATE' => 'origination_date',
            'VALUE_DATE' => 'origination_date',
            'FIRST_REPAYMENT_DATE' => 'first_repayment_date',
            'FIRST REPAYMENT DATE' => 'first_repayment_date',
            'MATURITY_DATE' => 'maturity_date',
            'MATURITY DATE' => 'maturity_date',
            'EXPIRY_DATE' => 'maturity_date',
            'CLOSURE_DATE' => 'closure_date',
            'CLOSURE DATE' => 'closure_date',
            'RESTRUCTURE_DATE' => 'last_restructure_date',
            'RESTRUCTURE DATE' => 'last_restructure_date',

            'SANCTIONED_AMOUNT' => 'approved_amount',
            'SANCTIONED AMOUNT' => 'approved_amount',
            'APPROVED_AMOUNT' => 'approved_amount',
            'PRINCIPAL_DISBURSED' => 'drawn_amount',
            'PRINCIPAL DISBURSED' => 'drawn_amount',
            'DISBURSED_AMOUNT' => 'drawn_amount',

            'INTEREST_RATE' => 'contractual_rate',
            'INTEREST RATE' => 'contractual_rate',
            'CONTRACTUAL_RATE' => 'contractual_rate',
            'RATE_BASIS' => 'rate_basis',
            'RATE BASIS' => 'rate_basis',
            'INTEREST_BASIS' => 'rate_basis',
            'RATE_TYPE' => 'rate_type',
            'RATE TYPE' => 'rate_type',
            'REFERENCE_RATE' => 'reference_rate_at_origination',
            'MARKUP' => 'markup',
            'MARGIN' => 'markup',

            'REPAYMENT_FREQUENCY' => 'repayment_frequency',
            'REPAYMENT FREQUENCY' => 'repayment_frequency',
            'PAYMENT_FREQUENCY' => 'repayment_frequency',
            'PAYMENTS_PER_YEAR' => 'payments_per_year',
            'TENOR_MONTHS' => 'tenor_months',
            'TENOR' => 'tenor_months',
            'GRACE_PERIOD_MONTHS' => 'moratorium_months',
            'GRACE PERIOD' => 'moratorium_months',
            'MORATORIUM_MONTHS' => 'moratorium_months',

            'ARRANGEMENT_FEE' => 'arrangement_fee',
            'ARRANGEMENT FEE' => 'arrangement_fee',
            'LEGAL_FEES' => 'legal_fees',
            'LEGAL FEES' => 'legal_fees',
            'LEGAL_FEE' => 'legal_fees',

            'OPENING_AMORTISED_COST' => 'opening_amortised_cost',
            'OPENING AMORTISED COST' => 'opening_amortised_cost',
            'OPENING_AMORTIZED_COST' => 'opening_amortised_cost',
            'OPENING_BALANCE_DATE' => 'opening_amortised_cost_date',
        ];
    }

    /**
     * Repayment frequency as MAIIC's files spell it → payments per year.
     * Anything unrecognised returns null and is reported, never guessed: a
     * wrong frequency silently changes the solved periodic rate.
     */
    public static function paymentsPerYear(?string $frequency): ?int
    {
        return match (strtoupper(trim((string) $frequency))) {
            'MONTHLY', 'MONTH', 'M' => 12,
            'QUARTERLY', 'QUARTER', 'Q' => 4,
            'SEMI-ANNUAL', 'SEMI ANNUAL', 'SEMIANNUAL', 'HALF-YEARLY', 'BI-ANNUAL' => 2,
            'ANNUAL', 'ANNUALLY', 'YEARLY', 'Y' => 1,
            'WEEKLY' => 52,
            'FORTNIGHTLY', 'BI-WEEKLY' => 26,
            default => null,
        };
    }
}
