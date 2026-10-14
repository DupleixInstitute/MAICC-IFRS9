--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 9  |  Dupleix Institute  |  13 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 8 showed that the loan accounts behind the older ledger copy
-- (CUMVOUCH_BAK, accounts 000100750000002 to 000100750000084) are no longer in
-- ACMASTER or ACLOANMASTER, and that OLD_AC_NUMBER in today's account master
-- is a serial number within the GL, not the number a loan had before. It also
-- found the tables where the link and the migration working should be:
--
--   RUN 1       row counts of all the tables below (run first)
--   RUN 2a, 2b  the two account maps, ACCOUNT_NO_MAPP and NEW_ACMAP
--   RUN 3       OPENING_BAL, the balances taken on at the migration
--   RUN 4a, 4b  T_ACCOUNT and T_ACCOUNTBALANCE, the conversion tables
--   RUN 5a-5e   the old loan accounts in the other copies of the account master
--   RUN 6       a profile of CUMOVUCH_OLD, a third old ledger copy
--   RUN 7       GL_OPENING_BALANCE_BK240824, the GL at the migration
--   RUN 8a      OVERDUE_DETAILS, a profile by month (added 14 October)
--   RUN 8b      OVERDUE_DETAILS, the month-end rows, all 78 columns
--   RUN 8c-1..6 OVERDUE_DETAILS, the daily rows since July 2025, one quarter per file
--
-- If a query errors because a table or column is not there, skip it and tell
-- us; the rest still help. Please save the results in the Pack 4 folder on
-- OneDrive, in the subfolder Pack 9\Export Results.
--
-- Note on pack 8: the CV_04 files in Pack 8\Export Results are copies of the
-- pack 7 files and were not needed. Two of them (CV_04b1 and CV_04b2) are
-- shorter than the pack 7 ones (5,980 and 4,465 rows against 7,019 and
-- 5,639), so they look like exports that stopped early; we use the pack 7
-- files, and nothing needs re-running.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 8 files still came out with dates as m/d/yyyy. These two lines
-- must be run in the same worksheet (same connection tab) as the queries;
-- each tab is its own session.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-14 09:15:00 and the number 1234567.89.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/14/2026, the export is
-- re-formatting it: Tools > Preferences > Database > NLS > Date Format =
-- YYYY-MM-DD HH24:MI:SS (type NLS in the search box at the top left of the
-- Preferences window if Database is not visible). Set once, it sticks.


--==============================================================================
-- RUN 1  |  row counts of the tables in this pack  |  save as CV_08_table_counts.csv
-- Counts only, no data. Run this first: it tells us which of the later
-- runs can be exported in one grid. If a line errors because the table is
-- not there or not visible to you, delete that line and run the rest.
SELECT 'VGCBS.ACCOUNT_NO_MAPP' AS table_name, COUNT(*) AS row_count FROM vgcbs.account_no_mapp
UNION ALL SELECT 'VGCBS.NEW_ACMAP', COUNT(*) FROM vgcbs.new_acmap
UNION ALL SELECT 'VGCBS.OPENING_BAL', COUNT(*) FROM vgcbs.opening_bal
UNION ALL SELECT 'VGCBS.T_ACCOUNT', COUNT(*) FROM vgcbs.t_account
UNION ALL SELECT 'VGCBS.T_ACCOUNTBALANCE', COUNT(*) FROM vgcbs.t_accountbalance
UNION ALL SELECT 'VGCBS.TEMP_MASTER', COUNT(*) FROM vgcbs.temp_master
UNION ALL SELECT 'VGCBS.G_ACMASTER', COUNT(*) FROM vgcbs.g_acmaster
UNION ALL SELECT 'VGCBS.ACMASTER_MW', COUNT(*) FROM vgcbs.acmaster_mw
UNION ALL SELECT 'VGCBS.ACMASTER_NF', COUNT(*) FROM vgcbs.acmaster_nf
UNION ALL SELECT 'VGCBS.CUMOVUCH_OLD', COUNT(*) FROM vgcbs.cumovuch_old
UNION ALL SELECT 'VGCBS.GL_OPENING_BALANCE_BK240824', COUNT(*) FROM vgcbs.gl_opening_balance_bk240824
UNION ALL SELECT 'MAIIC_ORA_LIVE.CRM_ACMASTER_05082024', COUNT(*) FROM maiic_ora_live.crm_acmaster_05082024;


--==============================================================================
-- RUN 2a  |  the old-to-new account map  |  save as CV_09a_account_no_mapp.csv
-- ACCOUNT_NO_MAPP holds OLD_AC_NUMBER and NEW_OLDACNO: the link from an
-- account number before the migration to its number today. In full.
SELECT m.* FROM vgcbs.account_no_mapp m ORDER BY 1;


--==============================================================================
-- RUN 2b  |  the E-Banker account map  |  save as CV_09b_new_acmap.csv
-- NEW_ACMAP holds EB_OLDACNO, the old account number against the new one. In full.
SELECT m.* FROM vgcbs.new_acmap m ORDER BY 1;


--==============================================================================
-- RUN 3  |  the opening balances taken on  |  save as CV_10_opening_bal.csv
-- OPENING_BAL (about 95 rows): the balances taken on at the migration. We
-- expect the 78 term loans of 31 July 2024 among them; this should show how
-- the three opening postings per loan were prepared, in particular the
-- opening interest, which differs from the old ledger on 74 of the 78 loans.
SELECT o.* FROM vgcbs.opening_bal o ORDER BY 1;


--==============================================================================
-- RUN 4a  |  the conversion's account table  |  save as CV_11a_t_account.csv
-- T_ACCOUNT holds OLDACCOUNTID: the accounts as loaded from the old system.
-- In full if RUN 1 shows 50,000 rows or fewer; if more, export only the
-- first 100 rows (add  FETCH FIRST 100 ROWS ONLY  before the semicolon) and tell us.
SELECT t.* FROM vgcbs.t_account t ORDER BY 1;


--==============================================================================
-- RUN 4b  |  the conversion's balance table  |  save as CV_11b_t_accountbalance.csv
-- T_ACCOUNTBALANCE holds OLDACCOUNTID and the balances converted. Same rule
-- as RUN 4a for the size.
SELECT t.* FROM vgcbs.t_accountbalance t ORDER BY 1;


--==============================================================================
-- RUN 5a  |  the old loan accounts in G_ACMASTER  |  save as CV_12a_old_block_g_acmaster.csv
-- The older ledger copy (CUMVOUCH_BAK) keeps its term loans on accounts
-- 000100750000002 to 000100750000084, which ACMASTER no longer holds (pack 8).
-- These three copies of the account master may still hold them.
SELECT a.* FROM vgcbs.g_acmaster a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;


--==============================================================================
-- RUN 5b  |  the old loan accounts in ACMASTER_MW  |  save as CV_12b_old_block_acmaster_mw.csv
-- As RUN 5a.
SELECT a.* FROM vgcbs.acmaster_mw a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;


--==============================================================================
-- RUN 5c  |  the old loan accounts in ACMASTER_NF  |  save as CV_12c_old_block_acmaster_nf.csv
-- As RUN 5a.
SELECT a.* FROM vgcbs.acmaster_nf a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;


--==============================================================================
-- RUN 5d  |  the account master as at 5 August 2024  |  save as CV_12d_acmaster_05082024_loans.csv
-- CRM_ACMASTER_05082024 is a copy of the account master taken days after the
-- migration: the loan accounts and the old block, as they stood then.
SELECT a.* FROM maiic_ora_live.crm_acmaster_05082024 a
WHERE  a.new_ac_number LIKE '00010075%'
OR     a.glcode IN ('1050', '1050101', '1050102', '1050103', '1050201', '1050202', '1050203', '1050401')
ORDER  BY a.new_ac_number;


--==============================================================================
-- RUN 5e  |  the staging master  |  save as CV_12e_temp_master.csv
-- TEMP_MASTER holds OLD_AC_NUMBER. In full if RUN 1 shows 20,000 rows or
-- fewer; if more, the first 100 rows only, as in RUN 4a.
SELECT t.* FROM vgcbs.temp_master t ORDER BY 1;


--==============================================================================
-- RUN 6  |  a profile of the third old ledger copy  |  save as CV_13_cumovuch_old_profile.csv
-- CUMOVUCH_OLD (about 13,359 rows; the name is spelt this way in the
-- database) is a ledger copy we have not seen. Counts by year and account
-- block only, so we can see what it covers before asking for it.
SELECT EXTRACT(YEAR FROM c.transaction_date) AS yr,
       SUBSTR(c.new_ac_number, 1, 8)           AS account_block,
       COUNT(*)                                 AS row_count,
       MIN(c.transaction_date)                  AS first_date,
       MAX(c.transaction_date)                  AS last_date,
       SUM(c.transamt)                          AS net_amount
FROM   vgcbs.cumovuch_old c
GROUP  BY EXTRACT(YEAR FROM c.transaction_date), SUBSTR(c.new_ac_number, 1, 8)
ORDER  BY 1, 2;


--==============================================================================
-- RUN 7  |  the GL opening balances at the August 2024 backup  |  save as CV_14_gl_opening_balance_bk240824.csv
-- GL_OPENING_BALANCE_BK240824 (about 331 rows): the GL balances kept with the
-- 24 August 2024 backup, to tie the loans taken on to the GL at the migration.
SELECT g.* FROM vgcbs.gl_opening_balance_bk240824 g ORDER BY 1;


-- RUN 8  |  E-Banker's own overdue computation per account  |  SPLIT into 8a, 8b, 8c (14 October, afternoon)
-- WHY RUN 8 WAS SPLIT (14 October, afternoon)
--   The single query stopped with "Result set exceeds the maximum size
--   (100 MB)" and the export holds 21 rows of one account. The table is big
--   because the overdue run is DAILY since 14 July 2025 (one row per account
--   per day; month-end rows only before that, back-filled on 8 to 11 August
--   2025). So it is split: a profile (8a) that sizes it, the month-end rows
--   (8b) that the system compares with, and the daily rows by quarter with
--   the columns that matter (8c), each well under the limit. The 21-row file
--   is superseded; please leave it, we will not load it.
--
-- Why this table at all (unchanged from the morning):
--   1. Today every arrears figure the system holds comes from the stored
--      Loan Book Report (P2_08): the overdue date and five day buckets, as
--      the report computed them on the day it was run. The staging engine
--      counts days past due from that overdue date (the directive's basis)
--      and only falls back to the bucket when the date is blank. There is no
--      second source to check it against. OVERDUE_DETAILS is the table the
--      overdue run itself writes: per account and calculation date, the
--      instalments expected and received, the principal and interest
--      recovery split, the balance. It is the independent check on the days
--      past due and on the four-missed-instalments trigger (spec 3.6, D31),
--      which today is rebuilt from the instalment chart and the ledger.
--   2. Its RESCHEDULEMENT_DATE and RESCHEDULEMENT_AMOUNT columns may be where
--      a restructure is actually recorded in E-Banker: LOAN_RESCHEDULE_DETAILS
--      is empty (6 October dictionary), the vendor's Reschedule Report is still
--      owed, and the directive's section 16 and the 10 percent modification
--      test (spec 3.3.2) both wait on restructure history. If these columns
--      carry it, that request to the vendor can be dropped.
--   3. The report's buckets are 1-30, 31-91, 91-180, 181-270, 271-360 (note
--      the overlap at 91 and the ceiling at 360); the directive's bands run
--      to 365 and 746 by term. A per-account day count from this table lets
--      the RBM return be checked without the buckets.
-- GOAL, and what to do with the files once they are back (for whoever lands them):
--   a. Land 8b as query id OD_01 and the 8c files as OD_02 (both registered in
--      EbankerQuerySeeder, key OVERDUE_DET_ID, account NEW_AC_NUMBER, date
--      OVD_CALC_DATE, incremental; a row in both is landed once) through the
--      feed like any extract: eir:land-pack, or the Feed screen. 8a is a
--      profile: read it, do not land it.
--   b. Per account and month-end (8b), compare the days past due the overdue
--      run implies (OVD_CALC_DATE less OVERDUE_PRINCI_DATE, and its own
--      OVERDUE_PERIOD column, which is in months: 4.97 on 31 Dec 2024 for an
--      overdue date of 1 Aug 2024) with loan_books.overdue_days from the
--      Loan Book Report. Report the count that agree and list the rest. A
--      systematic difference means one of the two bases is wrong.
--   c. Compare EXPECTED_NO_INST against the instalments actually received
--      (ACTUAL_RECOVERY_AMT, PRINCIPLE/INTEREST_RECOVERY_AMT) with the
--      four-missed-instalments trigger StagingService rebuilds from P2_10 and
--      the ledger: the loans the trigger lifts to Stage 3 should be the loans
--      this table shows four or more instalments behind.
--   d. If RESCHEDULEMENT_DATE / RESCHEDULEMENT_AMOUNT are populated for any of
--      our accounts, that is the restructure history: list the accounts with
--      the dates and amounts, and raise it as the input for the restructured
--      loan register (directive s.16(1)) and the 10 percent modification test
--      (spec 3.3.2); then tell Barry the vendor's Reschedule Report is no
--      longer needed. If they are empty on every row, say so and the
--      vendor request stands. (On the 21 rows received they are empty / 0.00.)
--   e. Record the outcome in the pack review note (as Pack 7 results review
--      does): rows, accounts, date range, the tie counts of b and c, the
--      answer to d.
-- If the schema prefix is needed, as in RUN 1: vgcbs.overdue_details and vgcbs.acmaster.


-- RUN 8a  |  profile of the overdue table: rows per calculation month  |  save as OD_00_overdue_profile.csv
-- A few dozen rows. Tells us how many rows each month holds and how many
-- accounts, so we know every later file is complete. Run first.
SELECT TO_CHAR(TRUNC(o.ovd_calc_date, 'MM'), 'YYYY-MM')  AS calc_month,
       COUNT(*)                                            AS rows_in_month,
       COUNT(DISTINCT o.new_ac_number)                     AS accounts,
       COUNT(DISTINCT TRUNC(o.ovd_calc_date))              AS calc_days,
       MIN(o.ovd_calc_date)                                AS first_calc_date,
       MAX(o.ovd_calc_date)                                AS last_calc_date,
       SUM(CASE WHEN o.rescheduLement_date IS NOT NULL THEN 1 ELSE 0 END) AS rows_with_reschedule_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
GROUP  BY TRUNC(o.ovd_calc_date, 'MM')
ORDER  BY 1;


-- RUN 8b  |  the overdue computation at each MONTH-END, every account, all dates  |  save as OD_01_overdue_details.csv
-- Only the rows whose calculation date is the last day of a month: about
-- 120 accounts x 24 months, a few thousand rows, all 78 columns. These are
-- the rows the system compares with its month-end loan book.
SELECT o.*
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    TRUNC(o.ovd_calc_date) = TRUNC(LAST_DAY(o.ovd_calc_date))
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;


-- RUN 8c  |  the DAILY overdue rows, one quarter per file, 34 columns  |  save as OD_02_overdue_daily_<quarter>.csv
-- Six queries, identical except for the two dates and the file name. Each
-- is about 120 accounts x 92 days, roughly 11,000 rows and 3 MB. Run and
-- export them one at a time. If a quarter still trips the 100 MB message,
-- SPOOL it instead (see "BIG FILES - export with SPOOL" in the pack 3 folder).

-- RUN 8c-1  |  save as OD_02_overdue_daily_2025Q3.csv  (daily rows start 14 July 2025)
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2025-07-01' AND o.ovd_calc_date < DATE '2025-10-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- RUN 8c-2  |  save as OD_02_overdue_daily_2025Q4.csv
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2025-10-01' AND o.ovd_calc_date < DATE '2026-01-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- RUN 8c-3  |  save as OD_02_overdue_daily_2026Q1.csv
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-01-01' AND o.ovd_calc_date < DATE '2026-04-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- RUN 8c-4  |  save as OD_02_overdue_daily_2026Q2.csv
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-04-01' AND o.ovd_calc_date < DATE '2026-07-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- RUN 8c-5  |  save as OD_02_overdue_daily_2026Q3.csv
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-07-01' AND o.ovd_calc_date < DATE '2026-10-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- RUN 8c-6  |  save as OD_02_overdue_daily_2026Q4.csv  (1 October 2026 to the day you run it)
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-10-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;
