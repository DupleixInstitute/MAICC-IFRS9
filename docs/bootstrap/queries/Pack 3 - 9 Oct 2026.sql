--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 3  |  Dupleix Institute  |  9 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database. Run RUN 0 once at the start
-- of the session, before anything else; it lasts for the session only.
--
-- Two groups:
--
--   A. STILL OPEN FROM PACK 2 (8 October)
--     M09_03_loan_book_runs_sep.csv         the Loan Book Report run(s) for 30 Sep 2026
--     P2_13_los_requests.csv                RE-RUN: the payloads rendered as text this time
--     MF_01 .. MF_08                         the eight Mega Farm files (schemes 96 to 103)
--     (from Finance, not a query)           Trial Balance_30 September 2026.xls
--
--   B. NEW THIS WEEK, FROM THE BUILD
--     SM_01_scheme_master.csv               the 26 schemes' settings (moratorium, interest basis)
--     CT_01_table_counts.csv                row counts of the reschedule, write-off and charges tables
--     CH_01_charges_term_loans.csv          charges on the term-loan schemes 140 to 145
--     RS_01_reschedules.csv  WO_01_writeoffs.csv   only if CT_01 shows rows
--==============================================================================

-- RUN 0  |  session settings  |  run once, first
-- The 7 and 8 October files came out with dates as m/d/yyyy because these two
-- lines were not run first. The system now records on every load whether its
-- dates were ISO; from this pack on it should say yes. They apply to this SQL
-- Developer session only and change nothing in the database.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- EXPORT SETTINGS (SQL Developer, Export Data): format CSV, header yes,
-- delimiter comma, string enclosure double quotes, encoding UTF-8, no
-- thousand separators (RUN 0 covers the numbers), left-aligned dates as RUN 0
-- formats them. One file per query, named as shown.


--==============================================================================
-- A. STILL OPEN FROM PACK 2
--==============================================================================

-- RUN 1  |  save as M09_03_loan_book_runs_sep.csv
-- Every stored run of the Loan Book Report with an as-on date of 30 September
-- 2026, and any run with an id after 808196 (our last), whatever its date.
-- This is the one September file we do not yet hold; without it the month
-- cannot be built by method A and compared with method B.
SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND   (l.asondate = DATE '2026-09-30' OR l.loan_book_det_id_a > 808196)
ORDER  BY l.new_ac_number, l.asondate, l.loan_book_det_id_a;


-- RUN 2  |  save as P2_13_los_requests.csv   (RE-RUN)
-- The 8 October file carried the literal text "CLOB" in REQUEST_STRING and
-- RESPONCE_STRING because SQL Developer does not export a CLOB as text by
-- default. This version renders the first 4,000 characters of each payload
-- as text (two columns, so a payload longer than 4,000 characters is still
-- seen in full). The join is a text search, so a few minutes is normal.
SELECT a.new_ac_number,
       r.los_req_id, r.transaction_date,
       DBMS_LOB.SUBSTR(r.request_string, 4000, 1)     AS request_text_1,
       DBMS_LOB.SUBSTR(r.request_string, 4000, 4001)  AS request_text_2,
       DBMS_LOB.SUBSTR(r.responce_string, 4000, 1)    AS response_text_1,
       DBMS_LOB.SUBSTR(r.responce_string, 4000, 4001) AS response_text_2
FROM   los_request_details r
JOIN   acmaster a ON INSTR(r.request_string, a.new_ac_number) > 0
                  OR INSTR(r.responce_string, a.new_ac_number) > 0
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
ORDER  BY a.new_ac_number, r.transaction_date, r.los_req_id;


-- RUN 3  |  save as MF_01_account_master.csv and MF_02_loan_master.csv (two queries)
-- The Mega Farm schemes 96 to 103, about 7,000 accounts, same shape as the
-- 7 October pack. Unchanged from pack 2.
SELECT a.*
FROM   acmaster a
WHERE  a.scheme_mst_id IN (96,97,98,99,100,101,102,103)
ORDER  BY a.scheme_mst_id, a.new_ac_number;

SELECT lm.*
FROM   acloanmaster lm
WHERE  lm.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY lm.new_ac_number;


-- RUN 4  |  save as MF_03_ledger_all.csv, MF_04_balance_history.csv, MF_05_loan_book_history.csv (three queries)
-- MF_03 is the largest file of the pack: expect several times the MAIIC ledger.
SELECT cv.*
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY cv.new_ac_number, cv.transaction_date, cv.cumvouch_det_id;

SELECT b.*
FROM   account_balance b
WHERE  b.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
AND    b.transaction_date = LAST_DAY(b.transaction_date)
ORDER  BY b.new_ac_number, b.transaction_date;

SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY l.new_ac_number, l.asondate, l.loan_book_det_id_a;


-- RUN 5  |  save as MF_06_status_history.csv, MF_07_rate_setup.csv, MF_08_charges.csv (three queries)
SELECT h.*
FROM   account_status_history h
WHERE  h.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY h.new_ac_number, h.status_eff_date, h.ac_status_mst_id;

SELECT r.*
FROM   interest_slab_details r
WHERE  r.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY r.new_ac_number, r.applicable_from_date, r.interest_rate_det_id;

SELECT c.*
FROM   los_disb_charges_post c
WHERE  c.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY c.new_ac_number, c.lo_disb_charg_det_id;


--==============================================================================
-- B. NEW THIS WEEK, FROM THE BUILD
--==============================================================================

-- RUN 6  |  save as SM_01_scheme_master.csv
-- The scheme settings for the 18 MAIIC schemes and the 8 Mega Farm schemes:
-- the moratorium period and unit, whether interest is charged on the
-- sanctioned or the disbursed amount (LOANINT_SANCWISE_BALWISE), the interest
-- calculation and payment frequencies, the product frequency. Two things in
-- the build wait on this: eight part-drawn facilities whose interest basis
-- neither the contract nor the scheme has told us yet, and a check of our
-- reading of the loan master's moratorium fields (see the note).
SELECT s.*
FROM   scheme_master s
WHERE  s.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145,96,97,98,99,100,101,102,103)
ORDER  BY s.scheme_mst_id;


-- RUN 7  |  save as CT_01_table_counts.csv
-- Before we ask for any of these tables: how many rows each holds, in all
-- and for our schemes. The data dictionary of 6 October showed
-- LOAN_RESCHEDULE_DETAILS and LOAN_WRITEOFF_TRANS empty; if that is right the
-- restructure history is still the vendor's Reschedule Report, and the
-- write-offs are only the two type-300 postings in the ledger. If any count
-- is above zero, RUN 9 and RUN 10 extract them.
SELECT 'LOAN_RESCHEDULE_DETAILS' AS table_name, COUNT(*) AS all_rows,
       SUM(CASE WHEN new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)) THEN 1 ELSE 0 END) AS our_rows
FROM   loan_reschedule_details
UNION ALL
SELECT 'T_RESCHEDULE', COUNT(*), NULL FROM t_reschedule
UNION ALL
SELECT 'LOAN_WRITEOFF_MASTER', COUNT(*),
       SUM(CASE WHEN new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)) THEN 1 ELSE 0 END)
FROM   loan_writeoff_master
UNION ALL
SELECT 'LOAN_WRITEOFF_TRANS', COUNT(*), NULL FROM loan_writeoff_trans
UNION ALL
SELECT 'CHARGES_POSTING_HISTORY', COUNT(*), NULL FROM charges_posting_history
UNION ALL
SELECT 'CHARGES_POSTING_HISALL', COUNT(*), NULL FROM charges_posting_hisall
UNION ALL
SELECT 'DISBURSE_CHARGES_DETAILS', COUNT(*), NULL FROM disburse_charges_details
UNION ALL
SELECT 'LOS_DISB_CHARGES_POST', COUNT(*),
       SUM(CASE WHEN new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (140,141,142,143,144,145)) THEN 1 ELSE 0 END)
FROM   los_disb_charges_post;
-- If a table in this list does not exist under this name, drop that UNION
-- branch and tell us the name it goes by.


-- RUN 8  |  save as CH_01_charges_term_loans.csv
-- The charges table we hold (P2_07, LOS_DISB_CHARGES_POST) carries fees for
-- the MAIIC Industrial and FInES loans but none for the term-loan schemes
-- 140 to 145, although the signed offer letters show arrangement and legal
-- fees on every one of them (Malawi Police SACCO 90,000,000 and 48,771,287,
-- for example, deducted in January 2026). Either they are posted elsewhere
-- or through the ledger alone. This pulls every charges-type row we can find
-- for those six schemes from the three charges tables; where a table has no
-- NEW_AC_NUMBER column, tell us its key and we will adjust.
SELECT 'CHARGES_POSTING_HISTORY' AS source, c.*
FROM   charges_posting_history c
WHERE  c.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (140,141,142,143,144,145))
ORDER  BY c.new_ac_number;

SELECT 'DISBURSE_CHARGES_DETAILS' AS source, d.*
FROM   disburse_charges_details d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (140,141,142,143,144,145))
ORDER  BY d.new_ac_number;


-- RUN 9  |  save as RS_01_reschedules.csv   (only if CT_01 shows rows for our schemes)
SELECT r.*
FROM   loan_reschedule_details r
WHERE  r.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY r.new_ac_number, r.transaction_date, r.loan_resch_det_id;


-- RUN 10  |  save as WO_01_writeoffs.csv   (only if CT_01 shows rows for our schemes)
SELECT w.*
FROM   loan_writeoff_master w
WHERE  w.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY w.new_ac_number;


--==============================================================================
-- End of pack 3. Thank you, Barry.
--==============================================================================
