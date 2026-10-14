-- MAIIC E-Banker: pack 9 (13 October 2026) | Dupleix Institute | added 14 October
-- Read-only. Run in one SQL Developer worksheet, export the grid as CSV
-- (header row, comma, strings in double quotes, UTF-8), named as below.
-- Session settings first (RUN 0), in the same worksheet:
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 8  |  E-Banker's own overdue computation per account  |  save as OD_01_overdue_details.csv
-- Added 14 October. Why this one:
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
--      owed, and the directive's section 15 and the 10 percent modification
--      test (spec 3.3.2) both wait on restructure history. If these columns
--      carry it, that request to the vendor can be dropped.
--   3. The report's buckets are 1-30, 31-91, 91-180, 181-270, 271-360 (note
--      the overlap at 91 and the ceiling at 360); the directive's bands run
--      to 365 and 746 by term. A per-account day count from this table lets
--      the RBM return be checked without the buckets.
-- GOAL, and what to do with the file once it is back (for whoever lands it):
--   a. Land it as query id OD_01 (registered in EbankerQuerySeeder, key
--      OVERDUE_DET_ID, account NEW_AC_NUMBER, date OVD_CALC_DATE, incremental)
--      through the feed like any extract: eir:land-pack, or the Feed screen.
--   b. Per account and month-end, compare the days past due the overdue run
--      implies (its calculation date less the oldest unpaid instalment date,
--      or its own overdue-days column if it has one) with loan_books.overdue_days
--      from the Loan Book Report. Report the count that agree and list the
--      rest. A systematic difference means one of the two bases is wrong.
--   c. Compare EXPECTED_NO_INST against the instalments actually received
--      (ACTUAL_RECOVERY_AMT, PRINCIPLE/INTEREST_RECOVERY_AMT) with the
--      four-missed-instalments trigger StagingService rebuilds from P2_10 and
--      the ledger: the loans the trigger lifts to Stage 3 should be the loans
--      this table shows four or more instalments behind.
--   d. If RESCHEDULEMENT_DATE / RESCHEDULEMENT_AMOUNT are populated for any of
--      our accounts, that is the restructure history: list the accounts with
--      the dates and amounts, and raise it as the input for the restructured
--      loan register (directive s.15) and the 10 percent modification test
--      (spec 3.3.2); then tell Barry the vendor's Reschedule Report is no
--      longer needed. If they are empty on every row, say so and the
--      vendor request stands.
--   e. Record the outcome in the pack review note (as Pack 7 results review
--      does): rows, accounts, date range, the tie counts of b and c, the
--      answer to d.
-- The 18 MAIIC schemes, every calculation date, oldest first. If the table
-- is large, SPOOL it (see the BIG FILES note in the pack 3 folder) rather
-- than export the grid.
SELECT o.*
FROM   overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;

-- If the schema prefix is needed, as in RUN 1: vgcbs.overdue_details and vgcbs.acmaster.
-- If OVD_CALC_DATE does not exist under that name, order by TRANSACTION_DATE instead.
