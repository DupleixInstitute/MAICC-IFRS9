<?php

namespace App\Imports;

/**
 * Canonical header aliases for Extract B.
 *
 * Keys are headings that may appear in a source file; values are the
 * stable internal field names consumed by the Extract B import service.
 * Saved mappings from the intake screen may override these defaults.
 */
class ExtractBImport
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
            'SUB_ACCOUNT_NUMBER' => 'sub_account_no',
            'GL_POSTING_REF' => 'gl_posting_ref',
            'GL POSTING REF' => 'gl_posting_ref',
            'POSTING_REFERENCE' => 'gl_posting_ref',
            'TRANSACTION_DATE' => 'transaction_date',
            'TRANSACTION DATE' => 'transaction_date',
            'VALUE_DATE' => 'transaction_date',
            'TRANSACTION_TYPE' => 'transaction_type',
            'TRANSACTION TYPE' => 'transaction_type',
            'PRINCIPAL_COMPONENT' => 'principal_component',
            'PRINCIPAL COMPONENT' => 'principal_component',
            'INTEREST_COMPONENT' => 'interest_component',
            'INTEREST COMPONENT' => 'interest_component',
            'FEE_COMPONENT' => 'fee_component',
            'FEE COMPONENT' => 'fee_component',
            'TOTAL_AMOUNT' => 'total_amount',
            'TOTAL AMOUNT' => 'total_amount',
            'SCHEDULED_ACTUAL_FLAG' => 'scheduled_actual_flag',
            'SCHEDULED ACTUAL FLAG' => 'scheduled_actual_flag',
            'ACTUAL_SCHEDULED_FLAG' => 'scheduled_actual_flag',
            'BALANCE_AFTER_TRANSACTION' => 'balance_after_transaction',
            'BALANCE AFTER TRANSACTION' => 'balance_after_transaction',
            'ROW_NOTE' => 'row_note',
            'ROW NOTE' => 'row_note',
            'NOTES' => 'row_note',
        ];
    }
}
