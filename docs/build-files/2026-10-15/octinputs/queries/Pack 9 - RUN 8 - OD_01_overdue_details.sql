-- MAIIC E-Banker: pack 9 (13 October 2026) | Dupleix Institute | RUN 8 added 14 October, SPLIT INTO 8a, 8b, 8c the same day
-- Read-only. Run in one SQL Developer worksheet, export the grid as CSV
-- (header row, comma, strings in double quotes, UTF-8), named as below.
-- Session settings first (RUN 0), in the same worksheet: put the cursor on
-- each ALTER SESSION line and press Ctrl+Enter, then run the SELECT. The
-- file received this morning has dates as 8/8/2025, so RUN 0 had not run.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

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
