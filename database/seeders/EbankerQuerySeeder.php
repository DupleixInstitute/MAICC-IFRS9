<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The query register of the E-Banker feed (spec v4 section 6.4): each extract
 * the landing zone knows, its source table, the column that is its key, the
 * account and date columns, and whether it is pulled incrementally by key.
 * Idempotent on query_id. The SQL text itself is loaded from the committed
 * query files by the bootstrap; here is the shape.
 */
class EbankerQuerySeeder extends Seeder
{
    /** @return array<int, array{0:string,1:string,2:?string,3:?string,4:?string,5:?string,6:bool}> */
    public static function queries(): array
    {
        return [
            // id,      title,                                   source table,              key column,            account column,  date column,            incremental
            ['P1_01',  'Ledger, every posting',                  'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['P1_02',  'Account master',                        'ACMASTER',                'NEW_AC_NUMBER',       'NEW_AC_NUMBER', 'ACCOUNT_OPEN_DATE',    false],
            ['P1_03',  'Loan master',                           'ACLOANMASTER',            'NEW_AC_NUMBER',       'NEW_AC_NUMBER', 'SANCTION_DATE',        false],
            ['P1_04',  'Rate set-up per account',               'INTEREST_SLAB_DETAILS',   'INTEREST_RATE_DET_ID','NEW_AC_NUMBER', 'APPLICABLE_FROM_DATE', false],
            ['P1_05',  'Interest summary (latest month)',       'INTEREST_SUMMARY',        'INTEREST_MST_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['P1_05b', 'Interest product (history)',            'INTEREST_PRODUCT',        'INTEREST_MST_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['P2_06',  'PLR master',                            'PLR_MASTER',              'PLR_MST_ID',          null,            'APPLI_FROM_DATE',      false],
            ['P2_07',  'Disbursement charges',                  'LOS_DISB_CHARGES_POST',   'LO_DISB_CHARG_DET_ID','NEW_AC_NUMBER', null,                   false],
            ['P2_08',  'Loan book report runs',                 'LOAN_BOOK_DETAILS_ALL',   'LOAN_BOOK_DET_ID_A',  'NEW_AC_NUMBER', 'ASONDATE',             true],
            ['P2_09',  'Balance history, month-ends',           'ACCOUNT_BALANCE',         'ACCOUNT_BAL_MST_ID',  'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['P2_10',  'Instalment chart, current',             'FIXLOAN_INST_CHART',      'INST_CHART_DET_ID',   'NEW_AC_NUMBER', 'INSTALLMENT_DATE',     false],
            ['P2_11',  'Security details',                      'CUST_SECURITY_DETAILS',   'CUST_SEC_DET_ID,NEW_AC_NUMBER', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false], // one row per security and account it secures
            ['P2_12',  'Guarantors',                            'GUARANTER_MASTER',        'GUARANTER_MST_ID',    'NEW_AC_NUMBER', null,                   false],
            ['P2_13',  'LOS request payloads',                  'LOS_REQUEST_DETAILS',     'LOS_REQ_ID',          'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['P3_11',  'Instalment plans',                      'MULTI_INST_DETAILS',      'INST_DET_ID',         'NEW_AC_NUMBER', 'INSTALLMENT_START_DATE', false],
            ['P3_12',  'Disbursement schedules',                'LOAN_DISBURSMENT_SCHEDULE', null,                'NEW_AC_NUMBER', null,                   false],
            ['P3_13',  'Status history',                        'ACCOUNT_STATUS_HISTORY',  'AC_STATUS_MST_ID',    'NEW_AC_NUMBER', 'STATUS_EFF_DATE',      false],
            ['P3_14',  'Daily interest accrual',                'DAILY_INTEREST_ACCRUAL',  'INT_ACCRU_DET_ID',    'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['P3_15',  'Slab master',                           'INTEREST_SLAB_MASTER',    null,                  null,            null,                   false],
            ['P3_16',  'Forgiven interest',                     'FORGIVE_INTEREST_DETAILS', null,                 'NEW_AC_NUMBER', null,                   false],
            ['P3_17',  'Auto charges',                          null,                      null,                  'NEW_AC_NUMBER', null,                   false],
            ['P3_18',  'Date-time session check',               null,                      null,                  null,            null,                   false],
            ['GL_01',  'FInES postings without an account',     'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['GL_02',  'Keyed GL opening balances',             'GL_OPENING_BALANCE',      null,                  null,            null,                   false],
            ['GL_03',  'Year-end interest batch legs',          'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['ZF_01',  'Zaithwa Farms postings',                'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['ZF_02',  'Zaithwa Farms amount search',           'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['ZF_03',  'Zaithwa Farms balance rows',            'ACCOUNT_BALANCE',         'ACCOUNT_BAL_MST_ID',  'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['M09_01', 'Monthly feed: ledger since watermark',  'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['M09_02', 'Monthly feed: balance history',         'ACCOUNT_BALANCE',         'ACCOUNT_BAL_MST_ID',  'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['M09_03', 'Monthly feed: loan book runs',          'LOAN_BOOK_DETAILS_ALL',   'LOAN_BOOK_DET_ID_A',  'NEW_AC_NUMBER', 'ASONDATE',             true],
            ['MF_01',  'Mega Farm: account master',             'ACMASTER',                'NEW_AC_NUMBER',       'NEW_AC_NUMBER', 'ACCOUNT_OPEN_DATE',    false],
            ['MF_02',  'Mega Farm: loan master',                'ACLOANMASTER',            'NEW_AC_NUMBER',       'NEW_AC_NUMBER', 'SANCTION_DATE',        false],
            ['MF_03',  'Mega Farm: ledger',                     'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['MF_04',  'Mega Farm: balance history',            'ACCOUNT_BALANCE',         'ACCOUNT_BAL_MST_ID',  'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['MF_05',  'Mega Farm: loan book runs',             'LOAN_BOOK_DETAILS_ALL',   'LOAN_BOOK_DET_ID_A',  'NEW_AC_NUMBER', 'ASONDATE',             true],
            ['MF_06',  'Mega Farm: status history',             'ACCOUNT_STATUS_HISTORY',  'AC_STATUS_MST_ID',    'NEW_AC_NUMBER', 'STATUS_EFF_DATE',      false],
            ['MF_07',  'Mega Farm: rate set-up',                'INTEREST_SLAB_DETAILS',   'INTEREST_RATE_DET_ID','NEW_AC_NUMBER', 'APPLICABLE_FROM_DATE', false],
            ['MF_08',  'Mega Farm: charges',                    'LOS_DISB_CHARGES_POST',   'LO_DISB_CHARG_DET_ID','NEW_AC_NUMBER', null,                   false],
            ['TB',     'Monthly trial balance (Finance)',       null,                      null,                  null,            null,                   false],
            ['DD_01', 'Data dictionary: schema', null, null, null, null, false],
            ['DD_02', 'Data dictionary: tables', null, null, null, null, false],
            ['DD_03', 'Data dictionary: columns', null, null, null, null, false],
            ['DD_04', 'Data dictionary: keys', null, null, null, null, false],
            ['DD_05', 'Data dictionary: indexes', null, null, null, null, false],
            ['DD_06', 'Data dictionary: account-keyed tables', null, null, null, null, false],
            ['DD_07', 'Data dictionary: tables by subject', null, null, null, null, false],
            ['DD_08', 'Data dictionary: stored code', null, null, null, null, false],
            ['DD_09', 'Data dictionary: schemes', null, null, null, null, false],
            ['DD_10', 'Data dictionary: scheme settings', null, null, null, null, false],
            ['DD_11', 'Data dictionary: account status codes', null, null, null, null, false],
            ['DD_12', 'Data dictionary: transaction types', null, null, null, null, false],
            ['DD_13', 'Data dictionary: GL codes posted', null, null, null, null, false],
            ['DD_14', 'Data dictionary: other codes', null, null, null, null, false],
            ['DD_15', 'Data dictionary: row counts', null, null, null, null, false],
            ['DD_16a', 'Data dictionary: sample account master', null, null, null, null, false],
            ['DD_16b', 'Data dictionary: sample loan master', null, null, null, null, false],
            ['DD_16c', 'Data dictionary: sample disbursement schedule', null, null, null, null, false],
            ['DD_16d', 'Data dictionary: sample instalment chart', null, null, null, null, false],
            ['DD_16e', 'Data dictionary: sample instalment plan', null, null, null, null, false],
            ['DD_16f', 'Data dictionary: sample ledger', null, null, null, null, false],
            ['DD_16g', 'Data dictionary: sample balances', null, null, null, null, false],
            ['DD_16h', 'Data dictionary: sample reschedule', null, null, null, null, false],
            ['DD_16i', 'Data dictionary: sample write-off', null, null, null, null, false],
        ];
    }

    public function run(): void
    {
        foreach (self::queries() as [$id, $title, $table, $key, $account, $date, $incremental]) {
            DB::table('ebanker_queries')->updateOrInsert(['query_id' => $id], [
                'title' => $title, 'source_table' => $table, 'key_column' => $key, 'account_column' => $account,
                'date_column' => $date, 'incremental' => $incremental, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        $this->command?->info('E-Banker query register: ' . count(self::queries()) . ' queries.');
    }
}
