<?php

namespace App\Imports;

/**
 * Header aliases for the per-drawdown extract (spec v3 section 7.6).
 *
 * Keys are headings a source file may carry; values are the field names
 * DisbursementImportService reads. E-Banker's disbursement enquiry spools with
 * "Loan Account Number", "Disbursement Date" and "Disbursed Amount"; the loan
 * book writes the account as "Account Number". A saved mapping from the intake
 * screen still overrides these.
 */
class DisbursementImport
{
    public static function aliases(): array
    {
        return [
            'LOAN_ACCOUNT_NUMBER' => 'contract_id',
            'LOAN ACCOUNT NUMBER' => 'contract_id',
            'ACCOUNT_NUMBER' => 'contract_id',
            'ACCOUNT NUMBER' => 'contract_id',
            'ACCOUNT_NO' => 'contract_id',
            'LOAN_ACCOUNT_NO' => 'contract_id',

            'DISBURSEMENT_DATE' => 'disbursement_date',
            'DISBURSEMENT DATE' => 'disbursement_date',
            'DISBURSED_DATE' => 'disbursement_date',
            'DRAWDOWN_DATE' => 'disbursement_date',
            'VALUE_DATE' => 'disbursement_date',

            'AMOUNT' => 'amount',
            'DISBURSED_AMOUNT' => 'amount',
            'DISBURSED AMOUNT' => 'amount',
            'DISBURSEMENT_AMOUNT' => 'amount',
            'DRAWDOWN_AMOUNT' => 'amount',

            'SUB_ACCOUNT_NO' => 'sub_account_no',
            'SUB ACCOUNT NO' => 'sub_account_no',
            'SUB_ACCOUNT' => 'sub_account_no',

            'TRANCHE_NO' => 'tranche_no',
            'TRANCHE' => 'tranche_no',
            'TRANCHE_NUMBER' => 'tranche_no',

            'REFERENCE' => 'reference',
            'PAYMENT_REFERENCE' => 'reference',
            'VOUCHER_NO' => 'reference',
            'NARRATION' => 'reference',
        ];
    }
}
