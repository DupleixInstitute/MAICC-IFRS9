--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 5  |  Dupleix Institute  |  12 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 4 is complete: all 42 files are in and every count control ties (the
-- review is in the Pack 4 folder, Export Results\Results review). This pack is
-- small. It closes the three points pack 4 left open:
--
--   RUN 1   P2_13b    the rest of the 64 long loan requests (pieces 3 and 4)
--   RUN 2   GL 6730   the 88,000 between the July closing and the August opening
--   RUN 3   fees      how a fee received in cash or through GL 2010 is tied to
--                     its loan account
--   RUN 4   probe     whether any copy of the ledger covers January 2023 to
--                     June 2024
--
-- Every run is a normal grid export and every file is small (the largest,
-- RUN 3b, has a count first so we both know what to expect).
-- Please save the results in the Pack 4 folder on OneDrive, in the subfolder
-- Pack 5\Export Results.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 4 files still came out with dates as m/d/yyyy. These two lines
-- must be run in the same worksheet (same connection tab) as the queries;
-- each tab is its own session.
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
-- RUN 1  |  save as P2_13b_los_requests_text_part2.csv
-- P2_13 in pack 4 brought the first 8,000 characters of each loan request.
-- 64 of the 200 requests are longer (the longest is 14,622 characters), and
-- their fee lines sit in the part that was cut off. This fetches characters
-- 8,001 to 16,000 of those 64, by id, so it is a key lookup and quick.
SELECT r.los_req_id,
       DBMS_LOB.GETLENGTH(r.request_string)                               AS request_length,
       CAST(DBMS_LOB.SUBSTR(r.request_string, 4000, 8001)  AS VARCHAR2(4000)) AS request_text_3,
       CAST(DBMS_LOB.SUBSTR(r.request_string, 4000, 12001) AS VARCHAR2(4000)) AS request_text_4
FROM   los_request_details r
WHERE  r.los_req_id IN (
  25077,29067,44883,44884,44945,44947,44948,45304,45530,45571,45710,46851,46852,56055,57140,66686,
  66687,96666,97759,99819,99820,116122,117503,117504,141763,145021,145955,163927,184367,187607,188183,188698,
  188752,189127,189430,189983,190242,190243,191001,191427,192537,193995,194094,195651,196090,196091,196092,196433,
  196496,196783,196926,197009,197226,197483,198901,199432,200343,200583,201078,201559,203032,204918,205236,205924)
ORDER  BY r.los_req_id;
-- Expect 64 rows.


--==============================================================================
-- RUN 2  |  GL 6730 Fines Expenses: the 88,000
-- E-Banker's trial balance history (TB_03 in pack 4) agrees with the 31 July
-- 2026 trial balance Finance sent us on every GL except 6730: the stored
-- 1 August opening on 6730 is 24,056,422.17, while the 31 July closing was
-- 24,144,422.17, so 88,000 is missing. Because of it the stored trial balance
-- has been out of balance by 88,000 every day since 1 August (December 2025
-- and January 2026 balance to zero). It looks like a July entry on 6730 was
-- changed, deleted or back-dated after July was rolled forward. These four
-- small queries should show which entry and when.

-- RUN 2a  |  save as GL_06a_6730_postings_jun_sep.csv
-- Every posting to 6730 from 1 June to 30 September 2026, including deleted,
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
AND    cv.transaction_date >= DATE '2026-06-01'
AND    cv.transaction_date <  DATE '2026-10-01'
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;
-- Expect a few dozen rows.

-- RUN 2b  |  save as GL_06b_88000_postings.csv
-- Any posting of exactly 88,000 (debit or credit, any GL) from 1 July to
-- 31 August 2026, in case the entry moved to another GL rather than vanished.
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.transaction_date, cv.back_dated_date, cv.operation_date, cv.transamt, cv.original_transamt,
       cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number, cv.vscrno, cv.particulars,
       cv.enter_user_id, cv.pass_flag, cv.delete_flag, cv.delete_user_id, cv.delete_desc,
       cv.modify_flag, cv.modify_user_id, cv.modify_desc, cv.modify_delete_flag
FROM   cumvouch cv
WHERE  (ABS(cv.transamt) = 88000 OR ABS(cv.original_transamt) = 88000)
AND    cv.transaction_date >= DATE '2026-07-01'
AND    cv.transaction_date <  DATE '2026-09-01'
ORDER  BY cv.transaction_date, cv.batch_number, cv.cumvouch_det_id;

-- RUN 2c  |  save as TB_04_6730_history_jul_aug.csv
-- The stored trial balance rows for 6730 from 25 June to 5 August 2026, to
-- see on which day the 88,000 dropped out.
SELECT t.*
FROM   trial_balance_history t
WHERE  t.glcode = '6730'
AND    t.transaction_date >= DATE '2026-06-25'
AND    t.transaction_date <  DATE '2026-08-06'
ORDER  BY t.transaction_date;

-- RUN 2d  |  save as GL_06d_6730_opening_balances.csv
-- The year opening balances held for 6730 (the modification log shows edits
-- to GL_OPENING_BALANCE, so we check it is not the source).
SELECT o.*
FROM   gl_opening_balance o
WHERE  o.glcode = '6730'
ORDER  BY o.finyearstartdate, o.gl_opening_bal_det_id;


--==============================================================================
-- RUN 3  |  the fee link: which loan account each fee belongs to
-- Pack 4 tied the fee GLs (4871, 4873) to the trial balance to the cent. But
-- of 163 fee lines since 2024, 107 sit in batches with no loan-account leg.
-- In 94 of them the other side is the bank (GL 1001), in 10 another bank GL
-- (1017 or 1046), and in 3 GL 2010 ("loan draw down"); the borrower is named
-- only in the narration. The system needs each fee on its loan account, and
-- we would rather read the link from E-Banker than guess it from names.

-- RUN 3a  |  save as GL_04c_fee_gl_legs_all_columns.csv
-- The same 205 fee legs as GL_04a, but with every column, so we can see
-- whether a reference field (TRF_NEW_AC_NUMBER, ADVICE_NUMBER, TOKEN_NUMBER,
-- TXN_NUMBER, VARCHAR_1 and the like) carries the loan account.
SELECT cv.*
FROM   cumvouch cv
WHERE  (cv.cb_glcode IN ('4871','4873','4874','4875') OR cv.ac_glcode IN ('4871','4873','4874','4875'))
AND    cv.transaction_date >= DATE '2024-01-01'
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;
-- Expect about 205 rows.

-- RUN 3b0  |  save as CHECK_GL_07_row_counts.csv   (a check file; it is not loaded)
-- The size of RUN 3b, so we both know the file is whole. If ROWS_EXPECTED is
-- above 300,000, stop and tell us; we will split it.
SELECT 'RUN 3b GL 2010 batch legs' AS part, COUNT(*) AS rows_expected
FROM   cumvouch cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number
         FROM   cumvouch x
         WHERE  (x.cb_glcode = '2010' OR x.ac_glcode = '2010')
         AND    x.transaction_date >= DATE '2024-01-01');

-- RUN 3b  |  save as GL_07_gl2010_batch_legs.csv
-- Every leg of every batch that touched GL 2010 since 1 January 2024. It shows
-- how 2010 is cleared: if the loan account is debited in a later batch that
-- credits 2010, that batch gives us the fee's loan account.
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype,
       cv.batch_type, cv.batch_number, cv.vscrno, cv.particulars,
       cv.trf_glcode, cv.trf_new_ac_number, cv.trf_subacno, cv.advice_number, cv.token_number,
       cv.txn_number, cv.pass_flag, cv.delete_flag, cv.modify_delete_flag
FROM   cumvouch cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number
         FROM   cumvouch x
         WHERE  (x.cb_glcode = '2010' OR x.ac_glcode = '2010')
         AND    x.transaction_date >= DATE '2024-01-01')
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;


--==============================================================================
-- RUN 4  |  save as CV_02_ledger_tables_probe.csv
-- CUMOVUCH_OLD (pack 4, RUN 9b) holds postings from April 2018 to December
-- 2022 only. This lists every table whose name suggests a ledger or an older
-- copy of one, with its row count as last gathered, to see whether January
-- 2023 to June 2024 is held anywhere. Names and counts only, no data.
SELECT t.owner, t.table_name, t.num_rows, t.last_analyzed
FROM   all_tables t
WHERE  t.table_name LIKE '%VOUCH%'
OR     t.table_name LIKE '%CUMOV%'
OR     t.table_name LIKE '%LEDGER%'
OR     t.table_name LIKE '%MIGR%'
OR     t.table_name LIKE '%TRAN%HIST%'
OR     t.table_name LIKE '%\_OLD' ESCAPE '\'
OR     t.table_name LIKE '%\_BKP%' ESCAPE '\'
OR     t.table_name LIKE '%\_BAK%' ESCAPE '\'
ORDER  BY t.owner, t.table_name;

--==============================================================================
-- End of pack 5. Thank you, Barry.
--==============================================================================
