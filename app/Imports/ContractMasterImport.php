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
            'PORTFOLIO' => 'portfolio',
            'FUNDING_SOURCE' => 'portfolio',

            'LOAN_START_DATE' => 'origination_date',
            'LOAN START DATE' => 'origination_date',
            'DISBURSEMENT_DATE' => 'origination_date',
            'VALUE_DATE' => 'origination_date',
            'FIRST_REPAYMENT_DATE' => 'first_repayment_date',
            'FIRST REPAYMENT DATE' => 'first_repayment_date',
            'MATURITY_DATE' => 'maturity_date',
            'MATURITY DATE' => 'maturity_date',
            'CONTRACTUAL_MATURITY_DATE' => 'maturity_date',
            'EXPIRY_DATE' => 'maturity_date',
            'CLOSURE_DATE' => 'closure_date',
            'CLOSURE DATE' => 'closure_date',
            'ACTUAL_CLOSURE_DATE' => 'closure_date',
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
            // The delivered RATE_BASIS column carries Fixed/Variable — it is
            // the rate type, not a day-count or accrual basis. Mapping it to
            // the free-text rate_basis column would leave rate_type on its
            // FIXED default for the 204 variable-rate facilities in the book.
            'RATE_BASIS' => 'rate_type',
            'RATE BASIS' => 'rate_type',
            'RATE_TYPE' => 'rate_type',
            'RATE TYPE' => 'rate_type',
            'INTEREST_BASIS' => 'rate_basis',
            'ACCRUAL_BASIS' => 'rate_basis',
            'DAY_COUNT_BASIS' => 'source_day_count_basis',
            'DAY COUNT BASIS' => 'source_day_count_basis',
            'DAYCOUNT' => 'source_day_count_basis',
            'COMPOUNDING' => 'source_compounding',
            'DISBURSEMENT_TRANCHES' => 'disbursement_tranches',
            'DISBURSEMENT TRANCHES' => 'disbursement_tranches',
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
            // The file carries principal and interest grace separately and
            // they differ per facility. Only the principal grace is aliased:
            // it is what shapes the schedule the solver discounts. A full
            // moratorium (interest deferred too) is a different cash-flow
            // shape and an accounting judgement, so INTEREST_GRACE_PERIOD is
            // deliberately left for the operator to map.
            'PRINCIPAL_GRACE_PERIOD' => 'moratorium_months',

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
     * Durations in the delivered file are not numbers. Tenor arrives as
     * "2y 0m 0d" or "0y 60m 0d" — the same 24- and 60-month terms written two
     * different ways — and grace periods as "3 M", "0 M", "0 Y" or a bare "0".
     *
     * Reading these with a plain numeric cast yields null (or, worse, 2 for a
     * 24-month tenor), so they are parsed by unit. Days are ignored: no MAIIC
     * facility expresses a tenor or grace period in days, and rounding a
     * stray day count into months would invent precision.
     *
     * @return int|null months, or null when there is no usable duration
     */
    public static function monthsFromDuration($value): ?int
    {
        if ($value === null || is_bool($value)) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value);
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }
        // A bare number is already months.
        if (is_numeric($text)) {
            return (int) round((float) $text);
        }

        $months = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*y/i', $text, $m)) {
            $months = (int) round(((float) $m[1]) * 12);
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*m/i', $text, $m)) {
            $months = (int) ($months ?? 0) + (int) round((float) $m[1]);
        }

        return $months;
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
