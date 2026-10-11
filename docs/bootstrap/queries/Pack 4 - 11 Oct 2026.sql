--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 4  |  Dupleix Institute  |  11 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- What pack 3 (9 October) settled, so it is not asked again:
--   M09_03, MF_02, MF_04, MF_06, MF_07, MF_08, SM_01 and CT_01 are complete.
--   CH_01a/b, RS_01 and WO_01 are rightly empty (CT_01 shows those tables empty).
--
-- What is still open, and the only things in this pack:
--   RUN 1   MF_01_account_master.csv      not received (the run 3 file held MF_02 only)
--   RUN 2   P2_13_los_requests_text.csv   the 9 Oct re-run stopped with ORA-02392
--   RUN 3   MF_03 ledger, in 7 parts       the 9 Oct file stops after 107 of ~3,685 accounts
--           (RUN 3 count first, then 3a to 3g, and 3h only if the count says so)
--   RUN 4   CH_02a, CH_02b                the term-loan fees (schemes 140 to 145)
--   RUN 5   P2_07_disbursement_charges.csv  whole table, replaces the 7 Oct file
--   RUN 6   GL_04a, GL_04b                the fee income GL legs: new, and from now on monthly
--   RUN 7   GL_05_writeoff_batch_legs.csv the write-off batches (GL 1064 and 1876): new
--
-- Every run in this pack is a normal grid export. No SPOOL is needed: the
-- ledger is cut into seven account ranges of roughly 60,000 to 70,000 rows
-- each, with only the 28 columns we use, so each part is small.
--==============================================================================


-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The 9 October files still came out with dates as m/d/yyyy, so these two
-- lines did not take. They must be run in the same worksheet (same connection
-- tab) as the queries; each tab is its own session.
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
-- RUN 1  |  save as MF_01_account_master.csv
-- The Mega Farm account master (schemes 96 to 103, about 6,974 accounts). It
-- is the spine of the Mega Farm module: it says which scheme, GL, customer and
-- status each account carries. Run it ON ITS OWN and export its grid.
SELECT a.*
FROM   acmaster a
WHERE  a.scheme_mst_id IN (96,97,98,99,100,101,102,103)
ORDER  BY a.scheme_mst_id, a.new_ac_number;


--==============================================================================
-- RUN 2  |  save as P2_13_los_requests_text.csv
-- The 9 October re-run stopped with "ORA-02392: exceeded session limit on CPU
-- usage" because it searched the text of every request for every account
-- number. We already hold the 200 request ids (P2_13 of 8 October), so this
-- version fetches those 200 rows by id: a key lookup, a few seconds at most.
SELECT r.los_req_id, r.transaction_date, r.los_keyword, r.unique_ref_no,
       DBMS_LOB.GETLENGTH(r.request_string)                              AS request_length,
       CAST(DBMS_LOB.SUBSTR(r.request_string,  4000, 1)    AS VARCHAR2(4000)) AS request_text_1,
       CAST(DBMS_LOB.SUBSTR(r.request_string,  4000, 4001) AS VARCHAR2(4000)) AS request_text_2,
       DBMS_LOB.GETLENGTH(r.responce_string)                             AS response_length,
       CAST(DBMS_LOB.SUBSTR(r.responce_string, 4000, 1)    AS VARCHAR2(4000)) AS response_text_1,
       CAST(DBMS_LOB.SUBSTR(r.responce_string, 4000, 4001) AS VARCHAR2(4000)) AS response_text_2
FROM   los_request_details r
WHERE  r.los_req_id IN (
  25077,29067,34247,34251,34394,35911,35919,36189,36958,39136,41945,41958,44634,44883,44884,44885,44886,44945,44947,44948,
  45170,45203,45304,45530,45571,45710,46851,46852,56055,56119,57140,66686,66687,96666,97759,99819,99820,116122,117503,117504,
  141763,145021,145955,163927,184367,187607,188183,188698,188752,189127,189430,189983,190242,190243,191001,191427,192537,193995,194094,195477,
  195651,196090,196091,196092,196093,196433,196496,196783,196926,197009,197226,197483,198901,199432,200343,200583,201078,201559,201688,201690,
  201692,201705,201718,201720,201734,201736,201738,201741,201743,201744,201745,201746,201747,201749,201751,201753,201755,201757,201760,201762,
  201764,201766,201768,201770,201772,201773,201774,201775,201777,201778,201779,201781,201782,201783,201784,201786,201787,201788,201790,201791,
  201792,201794,201795,201796,201797,201798,201800,201802,201803,201805,201806,201807,201808,201809,201810,201811,201812,201813,201815,201816,
  201817,201922,201936,201937,201938,202182,202183,202184,202187,202188,202189,202192,202195,202217,202220,202222,202223,202224,202225,202226,
  202229,202230,202231,202234,202235,202236,202247,202282,202283,203032,203324,203329,203330,203331,203344,203345,203346,203347,203348,203392,
  203394,203397,203398,203399,204918,205236,205571,205620,205642,205643,205924,205926,205948,205951,205952,205953,205954,205955,205956,205957)
ORDER  BY r.los_req_id;
-- Expect 200 rows. If request_length or response_length is above 8000 on any
-- row, tell us and we will send a third piece.


--==============================================================================
-- RUN 3  |  the Mega Farm ledger (MF_03) in seven parts, all grid exports
-- Why the change: the 9 October MF_03 stopped after 107 of about 3,685
-- accounts (13,610 rows), and SPOOL has not written a file on your side.
-- So the ledger now comes out as seven ordinary grid exports, each one an
-- account range of roughly 60,000 to 70,000 rows, with only the 28 columns
-- we use (the 9 October file had 84). There is no ORDER BY, which saves the
-- database a large sort and keeps each query well inside the CPU limit; we
-- sort on our side.
--
-- Run RUN 3 (the count) first and keep its grid: save it as
-- CHECK_MF_03_row_counts.csv (a check file; it is not loaded). Then run 3a to 3g one at a time, each on its own,
-- exporting its grid before running the next. Each file's row count must
-- match its line in CHECK_MF_03_row_counts.csv.

-- RUN 3  |  save as CHECK_MF_03_row_counts.csv
SELECT part, COUNT(*) AS rows_expected
FROM  (SELECT CASE
              WHEN cv.new_ac_number BETWEEN '000104480000000' AND '000104480002153' THEN 'RUN 3a'
              WHEN cv.new_ac_number BETWEEN '000104480002154' AND '000104480002699' THEN 'RUN 3b'
              WHEN cv.new_ac_number BETWEEN '000104480002700' AND '000104489999999' THEN 'RUN 3c'
              WHEN cv.new_ac_number BETWEEN '000104490000000' AND '000104490001276' THEN 'RUN 3d'
              WHEN cv.new_ac_number BETWEEN '000104490001277' AND '000104490001697' THEN 'RUN 3e'
              WHEN cv.new_ac_number BETWEEN '000104490001698' AND '000104499999999' THEN 'RUN 3f'
              ELSE 'RUN 3g'
              END AS part
       FROM   cumvouch cv
       WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103)))
GROUP  BY part
UNION ALL
SELECT 'MF_05 loan book', COUNT(*)
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY 1;
-- If this count itself stops with ORA-02392, skip it and go straight to 3a;
-- we will check the files against the month-end balances (MF_04) instead.

-- RUN 3a  |  save as MF_03_ledger_a.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104480000000' AND '000104480002153';

-- RUN 3b  |  save as MF_03_ledger_b.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104480002154' AND '000104480002699';

-- RUN 3c  |  save as MF_03_ledger_c.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104480002700' AND '000104489999999';

-- RUN 3d  |  save as MF_03_ledger_d.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104490000000' AND '000104490001276';

-- RUN 3e  |  save as MF_03_ledger_e.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104490001277' AND '000104490001697';

-- RUN 3f  |  save as MF_03_ledger_f.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number BETWEEN '000104490001698' AND '000104499999999';

-- RUN 3g  |  save as MF_03_ledger_g.csv
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.cb_gltype, cv.cb_alnx_type, cv.transaction_date, cv.cb_effective_date,
       cv.transaction_time, cv.transamt, cv.interest_amount, cv.vscrno, cv.batch_type, cv.batch_number,
       cv.dbcr, cv.trantype, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_delete_flag, cv.back_dated_date, cv.txn_number,
       cv.operation_date
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    cv.new_ac_number NOT LIKE '00010448%' AND cv.new_ac_number NOT LIKE '00010449%';

-- RUN 3h  |  save as MF_05_loan_book_month_ends.csv
-- ONLY IF the 'MF_05 loan book' line of CHECK_MF_03_row_counts.csv is more than
-- 34,540 (the rows we received on 9 October). If it equals 34,540, the
-- 9 October file was already whole and this run is not needed.
SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    l.asondate = LAST_DAY(l.asondate);


--==============================================================================
-- RUN 4  |  the term-loan fees (schemes 140 to 145)
-- CT_01 showed the two charges-history tables empty, so CH_01a/b were rightly
-- empty. The 54 LOS charge rows for schemes 140 to 145 are in
-- LOS_DISB_CHARGES_POST, and the ledger carries the fees as DISB debits.

-- RUN 4a  |  save as CH_02a_los_disb_charges_140_145.csv
SELECT c.*
FROM   los_disb_charges_post c
WHERE  c.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (140,141,142,143,144,145))
ORDER  BY c.new_ac_number, c.lo_disb_charg_det_id;

-- RUN 4b  |  save as CH_02b_disb_batch_legs_140_145.csv
-- Every leg of every DISB batch that touched a scheme 140 to 145 account, so
-- we can see which income GL took the fee credit.
SELECT cv.cumvouch_det_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number, cv.sub_account_no,
       cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars
FROM   cumvouch cv
WHERE  cv.batch_type = 'DISB'
AND    (cv.transaction_date, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_number
         FROM   cumvouch x
         WHERE  x.batch_type = 'DISB'
         AND    x.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                                    WHERE a.scheme_mst_id IN (140,141,142,143,144,145)))
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;


--==============================================================================
-- RUN 5  |  save as P2_07_disbursement_charges.csv  (replaces the 7 Oct file)
-- The 7 October file holds 3,651 rows; CT_01 counts 10,064 in the table.
-- Whole table, no filter, so nothing is lost by a wrong assumption on our side.
SELECT * FROM los_disb_charges_post ORDER BY new_ac_number, lo_disb_charg_det_id;


--==============================================================================
-- RUN 6  |  the fee income GL legs  (new; from the October month-end it is part
--          of every monthly pack, like M09_01 to M09_03)
-- The fee GLs agree to the trial balance every month, but about 44 percent of
-- 2025 fee income, and nearly all of it from March 2026, never touches the
-- loan account: it is credited to 4871 or 4873 in the drawdown batch while the
-- loan account carries the gross drawdown. So the fees are read from the GL
-- side, and these two queries are the fee control.

-- RUN 6a  |  save as GL_04a_fee_gl_legs.csv
-- Every posting to the four fee income GLs since 1 January 2024.
SELECT cv.cumvouch_det_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number, cv.sub_account_no,
       cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars
FROM   cumvouch cv
WHERE  (cv.cb_glcode IN ('4871','4873','4874','4875') OR cv.ac_glcode IN ('4871','4873','4874','4875'))
AND    cv.transaction_date >= DATE '2024-01-01'
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;
-- Expect a few hundred rows. The 2025 credits less debits should come to
-- 171,128,135.00 on 4871 and 311,342,792.00 on 4873 before the closing voucher.

-- RUN 6b  |  save as GL_04b_fee_batch_legs.csv
-- Every other leg of the same batches: the loan account or bank side of each fee.
SELECT cv.cumvouch_det_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number, cv.sub_account_no,
       cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars
FROM   cumvouch cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number
         FROM   cumvouch x
         WHERE  (x.cb_glcode IN ('4871','4873','4874','4875') OR x.ac_glcode IN ('4871','4873','4874','4875'))
         AND    x.transaction_date >= DATE '2024-01-01')
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;


--==============================================================================
-- RUN 7  |  save as GL_05_writeoff_batch_legs.csv   (new)
-- Every leg of every batch that touched GL 1064 Term Loans Write Offs or 1876
-- ECL Write Offs since 1 January 2024. It covers three entries we can see in
-- the trial balance but not fully in the ledger: the two write-offs of August
-- 2024; 138,135,680.80 debited to 1064 in January 2026 against 1876, where no
-- loan account moved; and batch TRF 3 of 29 June 2026, which credited
-- 38,492,032.54 to 20 loan accounts as "ACCOUNT CLOSURE" (type 305) while 1064
-- rose by the same amount. Same shape as GL_03; expect a few hundred rows.
SELECT cv.cumvouch_det_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number, cv.sub_account_no,
       cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars
FROM   cumvouch cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number
         FROM   cumvouch x
         WHERE  (x.cb_glcode IN ('1064','1876') OR x.ac_glcode IN ('1064','1876'))
         AND    x.transaction_date >= DATE '2024-01-01')
ORDER  BY cv.transaction_date, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;
-- If the January 2026 entry does not appear, it may have been keyed outside
-- CUMVOUCH: tell us where GL journals are held and we will adjust.

--==============================================================================
-- From Finance (not a query): Trial Balance_30 September 2026, from Tamanda.
--==============================================================================
-- NOTE ON SPOOL, for later (nothing in this pack needs it)
-- SPOOL writes to the computer SQL Developer is running on, never to the
-- database server. When it writes nothing, it is usually one of these:
--   1. The block was run with Run Statement (Ctrl+Enter). SPOOL only works
--      with Run Script (F5).
--   2. The folder does not exist on that computer, or Windows does not allow
--      writing there. If SQL Developer runs on a MAIIC server over remote
--      desktop, C:\Users\vmagaleta\Downloads is a folder on that server (it
--      may not exist there); try C:\Temp after creating it.
--   3. SET TERMOUT OFF hid the error message (SP2-0606 "cannot create SPOOL
--      file"). Run it with TERMOUT ON to see the message.
-- This shows which computer and Windows user SQL Developer is running as:
--   SELECT SYS_CONTEXT('USERENV','HOST')    AS client_computer,
--          SYS_CONTEXT('USERENV','OS_USER') AS windows_user
--   FROM   dual;
--==============================================================================
-- End of pack 4. Thank you, Barry.
--==============================================================================
