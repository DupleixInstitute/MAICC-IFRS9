--------------------------------------------------------------------------------
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, 6 October 2026
-- Prepared by Dupleix Institute from the data dictionary results received today.
--
-- Every query is a SELECT. Nothing is created, changed or deleted.
--
-- Scope = every account on a MAIIC or FInES scheme (scheme ids from DD_09).
-- Run the queries in the order numbered below. Export each result as CSV under
-- the file name shown: the prefix gives the priority and the run number, so
-- P2_07 is priority 2, run 7. Do not open the CSV files in Excel. Account
-- numbers must stay as text with their leading zeros.
--
-- FILES TO SEND BACK (18 in all)
--
--   PRIORITY 1  (replaces Extracts A, B and C)
--     P1_01_ledger_all.csv                  every posting, all GL codes, signed, with narration
--     P1_02_account_master_all.csv          account master, every column, 181 accounts
--     P1_03_loan_master_all.csv             loan master, every column (includes MARGIN_PERCENT)
--     P1_04_rate_setup_per_account.csv      rate set-up per account: policy, PLR, spread, effective date
--     P1_05_interest_posted_by_account.csv  the calculation behind each interest posting
--
--   PRIORITY 2  (rates, fees and the loan book history)
--     P2_06_plr_master.csv                  the PLR rate history, 46 rows
--     P2_07_disbursement_charges.csv        charges posted at disbursement, with income GL
--     P2_08_loan_book_history.csv           the stored loan book report, every as-on date
--                                           (or the month-end-only version if too large)
--     P2_09_balance_history.csv             balance history, month-end rows
--     P2_10_instalment_chart_current.csv    current instalment chart with amount recovered
--
--   PRIORITY 3  (when convenient)
--     P3_11_instalment_plans.csv
--     P3_12_disbursement_schedules.csv
--     P3_13_status_history.csv              every status change with its reason (explains H and F)
--     P3_14_daily_interest_accrual.csv
--     P3_15_slab_master.csv                 scheme-level rate slabs, 94 rows
--     P3_16_forgiven_interest.csv           2 rows
--     P3_17_auto_charges.csv                1 row
--     P3_18_date_time_check.csv             whether dates carry a time of day
--------------------------------------------------------------------------------


--==============================================================================
-- RUN 0   |   SESSION SETTINGS: run these two lines once, after logging in.
--==============================================================================
-- What they do: they tell THIS login session to display dates as 2026-10-06
-- 14:30:00 and numbers with a dot for the decimal point. Without them the
-- exports come out as 7/15/2025, which we then have to guess at.
--
-- What they do NOT do: they change nothing in the database. No table, no
-- setting and no other user is affected. They last only as long as this
-- connection: when you disconnect or close SQL Developer they are gone, and the
-- next login starts with the normal defaults. There is nothing to put back.
--
-- Run once, then run the queries below in the same connection. If you reconnect
-- or open a new connection, run these two lines again first.
--
-- To see what the session is using at any time:
--   SELECT parameter, value FROM nls_session_parameters
--   WHERE  parameter IN ('NLS_DATE_FORMAT', 'NLS_NUMERIC_CHARACTERS');

ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';


--==============================================================================
-- PRIORITY 1: the five that replace Extracts A, B and C
--==============================================================================

-- RUN 1 of 18   |   PRIORITY 1   |   save as P1_01_ledger_all.csv
-- Every posting on every in-scope account, all GL codes, all dates, signed,
-- with the narration. About 3,700 rows. This replaces Extract B.
SELECT cv.*
FROM   cumvouch cv
WHERE  cv.delete_flag = 'N'
AND    cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY cv.new_ac_number, cv.sub_account_no, cv.transaction_date, cv.cumvouch_det_id;


-- RUN 2 of 18   |   PRIORITY 1   |   save as P1_02_account_master_all.csv
-- Every column of the account master for the in-scope accounts (181 rows,
-- 141 columns). Includes INTEREST_POLICY, FLOATING_FLAG and INTEREST_RATE at
-- account level. This replaces most of Extract A.
SELECT a.*
FROM   acmaster a
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
ORDER  BY a.new_ac_number, a.sub_ac_number;


-- RUN 3 of 18   |   PRIORITY 1   |   save as P1_03_loan_master_all.csv
-- Every column of the loan master for the same accounts (80 columns).
-- Includes MARGIN_PERCENT, the sanction date and the moratorium fields.
SELECT al.*
FROM   acloanmaster al
WHERE  al.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY al.new_ac_number, al.sub_account_no;


-- RUN 4 of 18   |   PRIORITY 1   |   save as P1_04_rate_setup_per_account.csv
-- The interest rate set-up held against each account, with its effective date,
-- the PLR rate, the variation (spread) and the policy. This is the rate history
-- per loan.
SELECT d.*
FROM   interest_slab_details d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY d.new_ac_number, d.applicable_from_date, d.interest_rate_det_id;


-- RUN 5 of 18   |   PRIORITY 1   |   save as P1_05_interest_posted_by_account.csv
-- The interest calculation behind each posting: rate, from and to dates,
-- principal product, days, overdue and penal interest. This replaces Extract C
-- and gives the "how" behind every interest figure.
SELECT s.*
FROM   interest_summary s
WHERE  s.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY s.new_ac_number, s.interest_from_date, s.interest_mst_id;


--==============================================================================
-- PRIORITY 2: rates, fees and the loan book history
--==============================================================================

-- RUN 6 of 18   |   PRIORITY 2   |   save as P2_06_plr_master.csv
-- The prime lending rate history (46 rows). The source of the rate change file.
SELECT * FROM plr_master ORDER BY appli_from_date, plr_mst_id;


-- RUN 7 of 18   |   PRIORITY 2   |   save as P2_07_disbursement_charges.csv
-- Charges posted at disbursement: name, amount, income GL. This is the fee
-- table we have been asking for. Whole table (about 10,000 rows, all products)
-- so that nothing is filtered out by a wrong assumption.
SELECT * FROM los_disb_charges_post ORDER BY new_ac_number, lo_disb_charg_det_id;


-- RUN 8 of 18   |   PRIORITY 2   |   save as P2_08_loan_book_history.csv
-- The stored loan book report, every as-on date, for the in-scope accounts.
-- This should fill the months we do not have (November 2024 to November 2025).
SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY l.asondate, l.new_ac_number;

-- If RUN 8 is too large, run this month-end-only version instead and save it
-- under the same file name, P2_08_loan_book_history.csv:
-- SELECT l.* FROM loan_book_details_all l
-- WHERE  l.asondate = LAST_DAY(l.asondate)
-- AND    l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
--                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
-- ORDER  BY l.asondate, l.new_ac_number;


-- RUN 9 of 18   |   PRIORITY 2   |   save as P2_09_balance_history.csv
-- The balance history table for the in-scope accounts (month-end rows with
-- principal, receipts, payments and interest fields).
SELECT b.*
FROM   account_balance b
WHERE  b.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY b.new_ac_number, b.transaction_date, b.account_bal_mst_id;


-- RUN 10 of 18   |   PRIORITY 2   |   save as P2_10_instalment_chart_current.csv
-- The current instalment chart for every in-scope account, with the amount
-- recovered against each instalment. Current version only (DELETE_FLAG = 'N').
SELECT f.*
FROM   fixloan_inst_chart f
WHERE  f.delete_flag = 'N'
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.installment_date, f.inst_chart_det_id;


--==============================================================================
-- PRIORITY 3: the rest, when convenient
--==============================================================================

-- RUN 11 of 18   |   PRIORITY 3   |   save as P3_11_instalment_plans.csv
SELECT m.*
FROM   multi_inst_details m
WHERE  m.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY m.new_ac_number, m.inst_det_id;


-- RUN 12 of 18   |   PRIORITY 3   |   save as P3_12_disbursement_schedules.csv
SELECT d.*
FROM   loan_disbursment_schedule d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY d.new_ac_number, d.disb_schedule_date, d.loan_disb_det_id;


-- RUN 13 of 18   |   PRIORITY 3   |   save as P3_13_status_history.csv
-- Every status change on the in-scope accounts, with the reason. Should explain
-- the H and F codes.
SELECT h.*
FROM   account_status_history h
WHERE  h.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY h.new_ac_number, h.status_eff_date, h.ac_status_mst_id;


-- RUN 14 of 18   |   PRIORITY 3   |   save as P3_14_daily_interest_accrual.csv
-- The daily accrual log for the in-scope accounts.
SELECT d.*
FROM   daily_interest_accrual d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY d.new_ac_number, d.transaction_date, d.int_accru_det_id;


-- RUN 15 of 18   |   PRIORITY 3   |   save as P3_15_slab_master.csv
-- The scheme-level rate slabs (94 rows). Whole table.
SELECT * FROM interest_slab_master ORDER BY scheme_mst_id, applicable_from_date, interest_rate_slab_id;


-- RUN 16 of 18   |   PRIORITY 3   |   save as P3_16_forgiven_interest.csv  (2 rows)
SELECT * FROM forgive_interest_details ORDER BY transaction_date;


-- RUN 17 of 18   |   PRIORITY 3   |   save as P3_17_auto_charges.csv  (1 row)
SELECT * FROM auto_charges_master;


-- RUN 18 of 18   |   PRIORITY 3   |   save as P3_18_date_time_check.csv
-- Whether the dates the extracts filter on carry a time of day.
SELECT 'CUMVOUCH.TRANSACTION_DATE'           AS date_column,
       COUNT(*)                              AS rows_checked,
       SUM(CASE WHEN transaction_date <> TRUNC(transaction_date) THEN 1 ELSE 0 END) AS rows_with_time_of_day
FROM   cumvouch
UNION ALL
SELECT 'ACCOUNT_BALANCE.TRANSACTION_DATE', COUNT(*),
       SUM(CASE WHEN transaction_date <> TRUNC(transaction_date) THEN 1 ELSE 0 END)
FROM   account_balance
UNION ALL
SELECT 'FIXLOAN_INST_CHART.INSTALLMENT_DATE', COUNT(*),
       SUM(CASE WHEN installment_date <> TRUNC(installment_date) THEN 1 ELSE 0 END)
FROM   fixloan_inst_chart;

--------------------------------------------------------------------------------
-- End of file. Eighteen result sets, P1_01 to P3_18.
--------------------------------------------------------------------------------
