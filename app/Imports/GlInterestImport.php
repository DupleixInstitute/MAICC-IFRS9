<?php

namespace App\Imports;

/**
 * Canonical header aliases for the GL interest postings file (Extract C).
 *
 * One row per loan per period: the netted interest MAIIC's GL posted
 * (TRANTYPE 303/120/308/309/310/311 per the vendor's script). Keys are
 * headings that may appear in a source file; values are the internal field
 * names consumed by GlInterestImportService.
 *
 * Open item 9 in the spec: the fee/commission GL lines were flagged as absent
 * from Extract C during the 22–23 Jul review and accepted as workable. Confirm
 * against the delivered file before the reconciliation is presented as
 * complete — a missing fee line reads as an EIR-vs-GL difference.
 */
class GlInterestImport
{
    public static function aliases(): array
    {
        return [
            'RUN_ID' => 'run_id',
            'RUN ID' => 'run_id',
            'LOAN_ACCOUNT_NUMBER' => 'contract_id',
            'LOAN ACCOUNT NUMBER' => 'contract_id',
            'ACCOUNT_NUMBER' => 'contract_id',
            'CONTRACT_ID' => 'contract_id',
            'GL_ACCOUNT_CODE' => 'gl_account_code',
            'GL ACCOUNT CODE' => 'gl_account_code',
            'GL_ACCOUNT' => 'gl_account_code',
            'PERIOD_TYPE' => 'period_type',
            'PERIOD TYPE' => 'period_type',
            'PERIOD_YEAR' => 'period_year',
            'PERIOD YEAR' => 'period_year',
            'YEAR' => 'period_year',
            'PERIOD_MONTH' => 'period_month',
            'PERIOD MONTH' => 'period_month',
            'MONTH' => 'period_month',
            'INTEREST_INCOME_POSTED' => 'interest_income_posted',
            'INTEREST INCOME POSTED' => 'interest_income_posted',
            'INTEREST_INCOME' => 'interest_income_posted',
            'TRANSACTION_COUNT' => 'transaction_count',
            'TRANSACTION COUNT' => 'transaction_count',
            'POSTING_REFERENCES' => 'posting_references',
            'POSTING REFERENCES' => 'posting_references',
            'ROW_NOTE' => 'row_note',
            'ROW NOTE' => 'row_note',
            'NOTES' => 'row_note',
            'GENERATED_ON' => 'generated_on',
            'GENERATED ON' => 'generated_on',
        ];
    }
}
