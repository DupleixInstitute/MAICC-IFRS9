--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 8  |  Dupleix Institute  |  13 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 7 is complete (the review is in the Pack 4 folder, Pack 7\Export
-- Results\Results review). It showed that CUMVOUCH_BK240824 gives the full
-- history before the migration for 41 of the 78 term loans, and that Ifracon
-- and Tapempha were renumbered at the migration. Two things are left that the
-- database itself can answer:
--
--   RUN 1a, 1b  the older ledger copy (CUMVOUCH_BAK) keeps its loans on
--               accounts 000100750000001 to 000100750000084 plus
--               000101270000001 and 000101350000001, which are not in the
--               account extracts we hold; these two queries bring their
--               account and loan master rows, so they can be linked to
--               today's loans
--   RUN 2, 3    names only (no data): any table that may hold the migration
--               working (take-on, opening balances, conversion), and any
--               column that links an old account number to a new one
--
-- Four small grid exports. Please save the results in the Pack 4 folder on
-- OneDrive, in the subfolder Pack 8\Export Results.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 4 to 7 files still came out with dates as m/d/yyyy. These two
-- lines must be run in the same worksheet (same connection tab) as the
-- queries; each tab is its own session.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-13 09:15:00 and the number 1234567.89.
-- If it shows 13-OCT-26 or 10/13/2026, run the two lines above again here.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/13/2026, the export is
-- re-formatting it: Tools > Preferences > Database > NLS > Date Format =
-- YYYY-MM-DD HH24:MI:SS (type NLS in the search box at the top left of the
-- Preferences window if Database is not visible). Set once, it sticks.


--==============================================================================
-- RUN 1  |  the accounts behind the older ledger copy

-- RUN 1a  |  save as CV_05a_old_accounts_master.csv
-- The account master rows for the 75 loan accounts in CUMVOUCH_BAK (and any
-- other account in the 00010075 block): scheme,
-- GL, customer, name, open date, status, and the old account number.
SELECT a.*
FROM   acmaster a
WHERE  a.new_ac_number LIKE '00010075%'
OR     a.new_ac_number IN ('000101270000001', '000101350000001')
ORDER  BY a.new_ac_number;
-- Expect 75 to 86 rows. If none come back, the block is not in ACMASTER:
-- please tell us, and where those accounts are kept.

-- RUN 1b  |  save as CV_05b_old_accounts_loan_master.csv
-- The loan master rows for the same accounts: sanctioned amount, dates,
-- tenor and rate, so each old loan can be matched to its loan today.
SELECT lm.*
FROM   acloanmaster lm
WHERE  lm.new_ac_number LIKE '00010075%'
OR     lm.new_ac_number IN ('000101270000001', '000101350000001')
ORDER  BY lm.new_ac_number;


--==============================================================================
-- RUN 2  |  save as CV_06_migration_tables_probe.csv
-- Names and row counts only, no data: tables that may hold the take-on
-- working of July 2024 (the three opening postings per loan: amount
-- disbursed, interest charged, amount recovered). The opening interest
-- differs from the old ledger on 74 of 78 loans; the working would explain it.
SELECT t.owner, t.table_name, t.num_rows, t.last_analyzed
FROM   all_tables t
WHERE  t.owner NOT IN ('SYS', 'SYSTEM', 'XDB', 'MDSYS', 'CTXSYS', 'ORDSYS', 'OLAPSYS', 'WMSYS', 'APEX_040200', 'DVSYS', 'LBACSYS', 'AUDSYS', 'GSMADMIN_INTERNAL', 'OJVMSYS', 'DBSNMP', 'OUTLN')
AND   (t.table_name LIKE '%TAKE%'
OR     t.table_name LIKE '%OPEN%'
OR     t.table_name LIKE '%CONV%'
OR     t.table_name LIKE '%LEGACY%'
OR     t.table_name LIKE '%MIGR%'
OR     t.table_name LIKE '%UPLOAD%'
OR     t.table_name LIKE '%IMPORT%'
OR     t.table_name LIKE '%OLD%')
ORDER  BY t.owner, t.table_name;


--==============================================================================
-- RUN 3  |  save as CV_07_account_mapping_columns_probe.csv
-- Names only, no data: any column in any table that may link an old account
-- number to a new one (Ifracon moved from 000104430000059 to
-- 000104430000062 and Tapempha from 000104460000058 to 000104430000061 at
-- the migration; there may be others).
SELECT c.owner, c.table_name, c.column_name, c.data_type
FROM   all_tab_columns c
WHERE  c.owner NOT IN ('SYS', 'SYSTEM', 'XDB', 'MDSYS', 'CTXSYS', 'ORDSYS', 'OLAPSYS', 'WMSYS', 'APEX_040200', 'DVSYS', 'LBACSYS', 'AUDSYS', 'GSMADMIN_INTERNAL', 'OJVMSYS', 'DBSNMP', 'OUTLN')
AND   (c.column_name LIKE '%OLD%AC%'
OR     c.column_name LIKE '%PREV%AC%'
OR     c.column_name LIKE '%LEGACY%'
OR     c.column_name LIKE '%MIGR%'
OR     c.column_name LIKE '%TAKE%ON%'
OR     c.column_name LIKE '%CONV%AC%')
ORDER  BY c.owner, c.table_name, c.column_name;

--==============================================================================
-- End of pack 8. Thank you, Barry.
--==============================================================================
