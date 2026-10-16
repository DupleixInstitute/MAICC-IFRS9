<?php

namespace Database\Seeders;

use App\Services\Ebanker\DirectRead\RegisteredStatements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The query register of the E-Banker feed (spec v4 section 6.4): each extract
 * the landing zone knows, its source table, the column that is its key, the
 * account and date columns, and whether it is pulled incrementally by key.
 * Idempotent on query_id. The SQL text of the manual packs lives in the
 * committed files under docs/bootstrap/queries, which the E-Banker Feed screen
 * hands out as one download (EirFeedController::queries). The sql and sha256
 * columns hold only the statements route 4 executes, one file per query id
 * under resources/ebanker-queries/route4 (RegisteredStatements); every other
 * query keeps them empty.
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
            // E-Banker's own overdue computation per account and calculation date (pack 9 RUN 8, 14 October 2026): the independent check on days past due and the instalment trigger; may hold the reschedule date and amount.
            // The run is daily since 14 July 2025 and the table exceeds SQL Developer's 100 MB grid, so it comes in two shapes: OD_01 the month-end rows (RUN 8b, all columns) and OD_02 the daily rows one quarter per file (RUN 8c, 34 columns). Same key, so a month-end row present in both lands once.
            ['OD_01',  'Overdue details, month-end rows (E-Banker overdue run)',       'OVERDUE_DETAILS', 'OVERDUE_DET_ID', 'NEW_AC_NUMBER', 'OVD_CALC_DATE', true],
            ['OD_02',  'Overdue details, daily rows by quarter (E-Banker overdue run)', 'OVERDUE_DETAILS', 'OVERDUE_DET_ID', 'NEW_AC_NUMBER', 'OVD_CALC_DATE', true],
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
            // fee income GL legs (spec v4 section 3.7): the fee control, run every month; 4b adds the other legs of those batches
            ['GL_04a', 'Fee income GL legs (4871, 4873, 4874, 4875)', 'CUMVOUCH',             'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            ['GL_04b', 'Every leg of the fee batches',          'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     true],
            // the write-off batches, GL 1064 and 1876 (spec v4 section 3.8)
            ['GL_05',  'Write-off batch legs (1064, 1876)',     'CUMVOUCH',                'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            // the reschedule history rebuilt from E-Banker, and two probes (pack 4 RUN 8 and 9, 12 October 2026)
            ['RS_02a', 'Superseded instalment rows', 'FIXLOAN_INST_CHART', 'INST_CHART_DET_ID', 'NEW_AC_NUMBER', 'INSTALLMENT_DATE', false],
            ['RS_02b', 'Instalment chart copy of 30 Aug 2024', 'FIXLOAN_INST_CHART_300824', 'INST_CHART_DET_ID', 'NEW_AC_NUMBER', 'INSTALLMENT_DATE', false],
            ['RS_02c', 'Instalment chart copy of 14 Nov 2024', 'FIXLOAN_INST_CHART_141124', 'INST_CHART_DET_ID', 'NEW_AC_NUMBER', 'INSTALLMENT_DATE', false],
            ['RS_02d', 'Instalment chart copy of 28 Sep', 'FIX_INST_CHART28SEP', 'INST_CHART_DET_ID', 'NEW_AC_NUMBER', 'INSTALLMENT_DATE', false],
            ['RS_02e', 'Instalment chart copy CHART1', 'FIXLOAN_INST_CHART1', 'INST_CHART_DET_ID', 'NEW_AC_NUMBER', 'INSTALLMENT_DATE', false],
            ['RS_02f', 'Modification log', 'MODIFICATION_LOG', 'MOD_LOG_DET_ID', null, 'TRANSACTION_DATE', false],
            ['RS_02g', 'Account closure history', 'ACCOUNT_CLOSER_HISTORY', 'ACT_CLOSE_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['TB_02', 'Trial balance history probe (counts)', 'TRIAL_BALANCE_HISTORY', 'MONTH', null, null, false],
            ['CV_01', 'Old ledger probe (counts)', 'CUMOVUCH_OLD', 'YEAR', null, null, false],
            ['TB_03', 'Trial balance rows (E-Banker)', 'TRIAL_BALANCE_HISTORY', 'GLCODE,TRANSACTION_DATE', null, 'TRANSACTION_DATE', false],
            // packs 5 to 8 (12 and 13 October 2026). Each has its own id, outside the families
            // LandingZoneReader reads for the engines (LEDGER, TRIAL_BALANCE, ACCOUNT_MASTER,
            // LOAN_MASTER, CHARGES), so landing them moves no engine figure: GL_04c, GL_06a,
            // GL_06b, GL_07 and GL_08a are slices of the live ledger already landed, TB_04 and
            // TB_05 of the stored trial balance (TB_03), and CV_04a/CV_04b are the ledger copies
            // from before the July 2024 migration, whose posting ids come from other sequences
            ['P2_13b', 'LOS request payloads, pieces 3 and 4 of the long requests', 'LOS_REQUEST_DETAILS', 'LOS_REQ_ID', null, null, false],
            ['GL_06a', 'GL 6730 postings Jun to Sep 2026, with audit columns', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['GL_06b', 'Postings of exactly 88,000, Jul and Aug 2026', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['GL_06d', 'GL 6730 year-opening balances', 'GL_OPENING_BALANCE', 'GL_OPENING_BAL_DET_ID', null, 'FINYEARSTARTDATE', false],
            ['TB_04', 'Stored trial balance, GL 6730, 25 Jun to 5 Aug 2026', 'TRIAL_BALANCE_HISTORY', 'GLCODE,TRANSACTION_DATE', null, 'TRANSACTION_DATE', false],
            ['GL_04c', 'Fee income GL legs, all columns (the GL_04a legs)', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['GL_07', 'GL 2010 batch legs since 2024 (fees cleared through 2010)', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['CV_02', 'Ledger-copy tables probe (names, statistics row counts)', 'ALL_TABLES', 'TABLE_NAME', null, null, false], // OWNER|TABLE_NAME would pass the 60 characters of source_key
            ['GL_08a', 'GL 6730 postings Jan to Jun 2026, with audit columns', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['TB_05', 'Stored trial balance, GL 6730, 2 Jan to 25 Jun 2026', 'TRIAL_BALANCE_HISTORY', 'GLCODE,TRANSACTION_DATE', null, 'TRANSACTION_DATE', false],
            ['CV_03', 'Ledger copies: postings and accounts by year', null, 'TABLE_NAME,YEAR', null, null, false],
            ['CV_04a', 'Ledger copy CUMVOUCH_BK240824 (2018 to 15 Aug 2024), pre-migration', 'CUMVOUCH_BK240824', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['CV_04b', 'Ledger copy CUMVOUCH_BAK (2000 to 27 Mar 2024, earlier chart)', 'CUMVOUCH_BAK', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['CV_05a', 'Account master rows of the old 00010075 block', 'ACMASTER', 'NEW_AC_NUMBER', 'NEW_AC_NUMBER', 'ACCOUNT_OPEN_DATE', false],
            ['CV_05b', 'Loan master rows of the old 00010075 block', 'ACLOANMASTER', 'NEW_AC_NUMBER', 'NEW_AC_NUMBER', 'SANCTION_DATE', false],
            ['CV_06', 'Migration tables probe (names, statistics row counts)', 'ALL_TABLES', 'OWNER,TABLE_NAME', null, null, false],
            ['CV_07', 'Account-mapping columns probe (names only)', 'ALL_TAB_COLUMNS', null, null, null, false], // keyed by row: OWNER|TABLE_NAME|COLUMN_NAME passes the 60 characters of source_key
            // pack 9 (14 October 2026): the migration worksheet and the GL opening balances of 2024
            ['CV_10', 'Migration worksheet (OPENING_BAL), take-on of 31 Jul 2024', 'OPENING_BAL', 'OP_BAL_DET_ID', 'ACCNO', null, false],
            ['CV_14', 'GL opening balances of 1 Jan 2024 (backup of 24 Aug 2024)', 'GL_OPENING_BALANCE_BK240824', 'GL_OPENING_BAL_DET_ID', null, 'FINYEARSTARTDATE', false],
            // pack 10 (14 October 2026). OD_00 (the overdue profile) is read, not landed, as in pack 9; OD_02 takes
            // the remaining quarters. RF_01 is a slice of the live ledger and ZF_04 of the balance history, so
            // neither joins the LEDGER or BALANCE_HISTORY families: landing them moves no engine figure
            ['CV_15', 'Account master copy of 5 Aug 2024 (CRM_ACMASTER_05082024), identifying columns', 'CRM_ACMASTER_05082024', 'ACCOUNT_MST_ID', 'NEW_AC_NUMBER', 'ACCOUNT_OPEN_DATE', false],
            ['RF_01', 'Refinance batch legs: every leg of the batches that paid off a loan from a new one', 'CUMVOUCH', 'CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['TB_06', 'Stored trial balance check: daily net of the posting GLs and GL 6730, from 1 May 2026', 'TRIAL_BALANCE_HISTORY', 'TRANSACTION_DATE', null, 'TRANSACTION_DATE', false],
            ['ZF_04', 'Zaithwa Farms balance rows from Apr 2025 (re-check of the vendor rebuild)', 'ACCOUNT_BALANCE', 'ACCOUNT_BAL_MST_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', false],
            ['DD_17', 'Data dictionary: login and synonyms', null, null, null, null, false],
            ['DD_18', 'Data dictionary: read access per table', null, 'TABLE_NAME', null, null, false],
            ['DD_20', 'Data dictionary: refinance-link columns probe (names, statistics)', 'ALL_TAB_COLUMNS', null, null, null, false], // keyed by row, as CV_07
            // packs 3 and 4 (9 and 11 October 2026)
            ['SM_01',  'Scheme master',                         'SCHEME_MASTER',           'SCHEME_MST_ID',       null,            null,                   false],
            ['CT_01',  'Row counts of the reschedule, write-off and charges tables', null,  'TABLE_NAME',          null,            null,                   false],
            ['CH_01a', 'Charges posting history, term loans',   'CHARGES_POSTING_HISTORY', null,                  'NEW_AC_NUMBER', null,                   false],
            ['CH_01b', 'Disburse charges details, term loans',  'DISBURSE_CHARGES_DETAILS', null,                 'NEW_AC_NUMBER', null,                   false],
            ['CH_02a', 'LOS charges, schemes 140 to 145',       'LOS_DISB_CHARGES_POST',   'LO_DISB_CHARG_DET_ID','NEW_AC_NUMBER', null,                   false],
            ['CH_02b', 'Drawdown batch legs, schemes 140 to 145', 'CUMVOUCH',              'CUMVOUCH_DET_ID',     'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['RS_01',  'Reschedules',                           'LOAN_RESCHEDULE_DETAILS', 'LOAN_RESCH_DET_ID',   'NEW_AC_NUMBER', 'TRANSACTION_DATE',     false],
            ['WO_01',  'Write-offs',                            'LOAN_WRITEOFF_MASTER',    'LOAN_WRITEOFF_MST_ID','NEW_AC_NUMBER', 'WRITEOFF_DATE',        false],
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
            // one row per GL line of a trial balance file, landed by TrialBalanceLandingService (system audit of 9 October 2026, finding M1)
            ['TB_01',  'Trial balance lines (Finance)',         'TRIAL_BALANCE_FILE',      'PERIOD,BASIS,GL_CODE', null,           'PERIOD',               false],
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
        $statements = RegisteredStatements::files();
        $unknown = array_diff(array_keys($statements), array_column(self::queries(), 0));
        if ($unknown !== []) {
            throw new \RuntimeException('Route 4 statement files name queries the register does not hold: ' . implode(', ', $unknown));
        }
        DB::table('ebanker_queries')->whereNotIn('query_id', array_keys($statements))->update(['sql' => null, 'sha256' => null]);
        foreach ($statements as $id => $sql) {
            DB::table('ebanker_queries')->where('query_id', $id)->update(['sql' => $sql, 'sha256' => RegisteredStatements::fingerprint($sql)]);
        }
        $this->command?->info('E-Banker query register: ' . count(self::queries()) . ' queries, ' . count($statements) . ' with a route 4 statement.');
    }
}
