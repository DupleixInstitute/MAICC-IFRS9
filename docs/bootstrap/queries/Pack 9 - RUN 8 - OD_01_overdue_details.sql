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
