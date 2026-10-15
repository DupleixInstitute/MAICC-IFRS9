--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 7  |  Dupleix Institute  |  12 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 6 is complete (the review is in the Pack 4 folder, Pack 6\Export
-- Results\Results review). It showed that two older copies of the ledger hold
-- the postings from before the July 2024 migration:
--
--   CUMVOUCH_BK240824   2018 to 15 August 2024    12,464 rows
--   CUMVOUCH_BAK        2000 to 27 March 2024     19,780 rows
--
-- This pack extracts both in full. They give the loans that were on the book
-- before the migration their real history: drawdowns, fees, repayments and
-- arrears, instead of a single opening balance on 31 July 2024. We will also
-- tie their balances to the opening balances posted at the migration.
--
-- Six grid exports, split by year so each stays well under the grid limit.
-- The row count to expect is given under each one (from pack 6, RUN 2).
-- Please save the results in the Pack 4 folder on OneDrive, in the subfolder
-- Pack 7\Export Results.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 4, 5 and 6 files still came out with dates as m/d/yyyy. These two
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
-- RUN 1  |  CUMVOUCH_BK240824, the copy taken on 24 August 2024, in three parts

-- RUN 1a  |  save as CV_04a1_bk240824_2018_2022.csv
SELECT c.*
FROM   cumvouch_bk240824 c
WHERE  c.transaction_date < DATE '2023-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 3,530 rows.

-- RUN 1b  |  save as CV_04a2_bk240824_2023.csv
SELECT c.*
FROM   cumvouch_bk240824 c
WHERE  c.transaction_date >= DATE '2023-01-01'
AND    c.transaction_date <  DATE '2024-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 2,083 rows.

-- RUN 1c  |  save as CV_04a3_bk240824_2024.csv
SELECT c.*
FROM   cumvouch_bk240824 c
WHERE  c.transaction_date >= DATE '2024-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 6,851 rows.


--==============================================================================
-- RUN 2  |  CUMVOUCH_BAK, in three parts

-- RUN 2a  |  save as CV_04b1_bak_2000_2021.csv
SELECT c.*
FROM   cumvouch_bak c
WHERE  c.transaction_date < DATE '2022-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 7,019 rows.

-- RUN 2b  |  save as CV_04b2_bak_2022.csv
SELECT c.*
FROM   cumvouch_bak c
WHERE  c.transaction_date >= DATE '2022-01-01'
AND    c.transaction_date <  DATE '2023-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 5,639 rows.

-- RUN 2c  |  save as CV_04b3_bak_2023_2024.csv
SELECT c.*
FROM   cumvouch_bak c
WHERE  c.transaction_date >= DATE '2023-01-01'
ORDER  BY c.transaction_date, c.cumvouch_det_id;
-- Expect 7,122 rows.

-- If a query stops with ORA-00904 on CUMVOUCH_DET_ID (the copy may not have
-- that column), change the ORDER BY line to ORDER BY c.transaction_date and
-- run it again.

--==============================================================================
-- End of pack 7. Thank you, Barry.
--==============================================================================
