--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 6  |  Dupleix Institute  |  12 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 5 is complete: all nine files are in and every check passed (the
-- review is in the Pack 4 folder, Pack 5\Export Results\Results review).
-- This pack has three small queries for the two points pack 5 left open:
--
--   RUN 1a, 1b  GL 6730   find the day the 88,000 dropped out of the stored
--                         trial balance history (January to June 2026)
--   RUN 2       ledger    a count by year for each copy of the ledger, to see
--                         whether any covers January 2023 to June 2024
--
-- Every run is a normal grid export and every file is small.
-- Please save the results in the Pack 4 folder on OneDrive, in the subfolder
-- Pack 6\Export Results.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 4 and pack 5 files still came out with dates as m/d/yyyy. These two
-- lines must be run in the same worksheet (same connection tab) as the
-- queries; each tab is its own session.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-12 09:15:00 and the number 1234567.89.
-- If it shows 12-OCT-26 or 10/12/2026, run the two lines above again here.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/12/2026, the export is
-- re-formatting it: Tools > Preferences > Database > NLS > Date Format =
-- YYYY-MM-DD HH24:MI:SS (type NLS in the search box at the top left of the
-- Preferences window if Database is not visible). Set once, it sticks.


--==============================================================================
-- RUN 1  |  GL 6730 Fines Expenses: where the 88,000 left the stored history
-- Pack 5 showed that the ledger postings on 6730 add up to Finance's monthly
-- trial balance reports (for example 22,200,188.24 at 30 June 2026), but the
-- stored trial balance history is 88,000 lower from at least 25 June. Nothing
-- on 6730 was deleted, changed or back-dated from June to September, and the
-- stored history agreed at 2 January 2026. So the 88,000 dropped out between
-- January and June 2026. These two queries find the day and the posting.

-- RUN 1a  |  save as GL_08a_6730_postings_jan_jun.csv
-- Every posting to 6730 from 1 January to 25 June 2026, including deleted,
-- modified and unposted ones, with who entered, passed, changed or deleted it.
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.transaction_date, cv.cb_effective_date, cv.transaction_time,
       cv.back_dated_date, cv.operation_id, cv.operation_date, cv.transamt, cv.original_transamt,
       cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number, cv.vscrno, cv.particulars,
       cv.enter_user_id, cv.enter_desc, cv.pass_flag, cv.pass_user_id, cv.pass_desc,
       cv.delete_flag, cv.delete_user_id, cv.delete_desc,
       cv.modify_flag, cv.modify_user_id, cv.modify_desc, cv.modify_delete_flag
FROM   cumvouch cv
WHERE  (cv.cb_glcode = '6730' OR cv.ac_glcode = '6730')
AND    cv.transaction_date >= DATE '2026-01-01'
AND    cv.transaction_date <  DATE '2026-06-26'
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;
-- Expect a few dozen rows.

-- RUN 1b  |  save as TB_05_6730_history_jan_jun.csv
-- The stored trial balance rows for 6730 from 1 January to 25 June 2026, one
-- per day (about 175 rows), to see on which day the stored balance stops
-- matching the postings.
SELECT t.*
FROM   trial_balance_history t
WHERE  t.glcode = '6730'
AND    t.transaction_date >= DATE '2026-01-01'
AND    t.transaction_date <  DATE '2026-06-26'
ORDER  BY t.transaction_date;


--==============================================================================
-- RUN 2  |  save as CV_03_ledger_copies_by_year.csv
-- CUMOVUCH_OLD stops in December 2022 and the live ledger starts in practice
-- at the July 2024 migration. Pack 5 (RUN 4) listed seven other copies of the
-- ledger. This counts the postings in each by year, with the first and last
-- date, so we can see whether any of them covers January 2023 to June 2024.
-- The live ledger, CUMVOUCH, is included for comparison.
-- If one table stops the query with ORA-00904 (a column it does not have) or
-- ORA-00942 (no access), delete that table's four lines and run it again, and
-- tell us which one it was.
SELECT 'CUMVOUCH' AS table_name, TO_CHAR(c.transaction_date, 'YYYY') AS year, COUNT(*) AS postings,
       COUNT(DISTINCT c.new_ac_number) AS accounts, MIN(c.transaction_date) AS first_date, MAX(c.transaction_date) AS last_date
FROM   cumvouch c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_CHET13062025', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_chet13062025 c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_BAK', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_bak c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_BK240824', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_bk240824 c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_NF', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_nf c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_3110', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_3110 c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_310724', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_310724 c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
UNION ALL
SELECT 'CUMVOUCH_MW', TO_CHAR(c.transaction_date, 'YYYY'), COUNT(*),
       COUNT(DISTINCT c.new_ac_number), MIN(c.transaction_date), MAX(c.transaction_date)
FROM   cumvouch_mw c GROUP BY TO_CHAR(c.transaction_date, 'YYYY')
ORDER  BY 1, 2;
-- Expect a few dozen rows: one per table per year.

--==============================================================================
-- End of pack 6. Thank you, Barry.
--==============================================================================
