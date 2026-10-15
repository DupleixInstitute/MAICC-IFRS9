--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 2  |  Dupleix Institute  |  8 October 2026
--==============================================================================
-- Eleven read-only queries, numbered in run order, each with the file name to
-- save it under. Nothing here writes to the database. Run RUN 0 once at the
-- start of the session; it lasts for the session only.
--
-- The files to send back:
--
--   SEPTEMBER MONTH-END (the first monthly pack; three files plus the TB)
--     M09_01_ledger_since_august.csv        ledger rows since our last load, August re-pulled
--     M09_02_balance_history_aug_sep.csv    month-end balance rows for 31 Aug and 30 Sep 2026
--     M09_03_loan_book_runs_sep.csv         the Loan Book Report run(s) for 30 Sep 2026
--     (from Finance, not a query)           Trial Balance_30 September 2026.xls
--
--   THE INTEREST ARITHMETIC AND THE COLLATERAL
--     P1_05b_interest_product.csv           the balance-times-days behind every posting, if history is kept
--     P2_11_security_details.csv            collateral per customer
--     P2_12_guarantors.csv                  guarantors per account
--     P2_13_los_requests.csv                origination requests mentioning our accounts (fees and rates at approval)
--
--   THE YEAR-END ADJUSTMENTS (sent on 8 Oct as its own note; repeated here)
--     GL_03_diffint_batch_legs.csv          both legs of the interest batches of 31 Dec 2025
--
--   THE MEGA FARM SCHEMES (96 to 103; about 7,000 accounts; same shape as the 7 Oct pack)
--     MF_01_account_master.csv
--     MF_02_loan_master.csv
--     MF_03_ledger_all.csv                  the largest file; expect several times the MAIIC ledger
--     MF_04_balance_history.csv
--     MF_05_loan_book_history.csv
--     MF_06_status_history.csv
--     MF_07_rate_setup.csv
--     MF_08_charges.csv
--==============================================================================

-- RUN 0  |  session settings  |  run once, first
-- Dates come out as 2026-09-30 00:00:00 and decimals with a point, so no file
-- can be misread. They apply to this SQL Developer session only and change
-- nothing in the database; closing the session puts the defaults back.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';


--==============================================================================
-- SEPTEMBER 2026 MONTH-END: the first monthly pack
--==============================================================================
-- The three queries below are the monthly feed. They take only what is new
-- since the files of 7 October (by the table's own id), and re-pull August so
-- that a posting back-dated into August after 7 October is seen.

-- RUN 1 of 11  |  save as M09_01_ledger_since_august.csv
-- Our last loaded ledger id is 425764. Everything after it, plus every August
-- row, for the 18 MAIIC schemes.
SELECT cv.*
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND   (cv.cumvouch_det_id > 425764 OR cv.transaction_date >= DATE '2026-08-01')
ORDER  BY cv.new_ac_number, cv.transaction_date, cv.cumvouch_det_id;


-- RUN 2 of 11  |  save as M09_02_balance_history_aug_sep.csv
-- Month-end balance rows for 31 August and 30 September 2026 (August re-pulled).
SELECT b.*
FROM   account_balance b
WHERE  b.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    b.transaction_date IN (DATE '2026-08-31', DATE '2026-09-30')
ORDER  BY b.new_ac_number, b.transaction_date, b.account_bal_mst_id;


-- RUN 3 of 11  |  save as M09_03_loan_book_runs_sep.csv
-- Every stored run of the Loan Book Report with an as-on date of 30 September
-- 2026, and any run with an id after 808196 (our last), whatever its date.
SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND   (l.asondate = DATE '2026-09-30' OR l.loan_book_det_id_a > 808196)
ORDER  BY l.new_ac_number, l.asondate, l.loan_book_det_id_a;


--==============================================================================
-- THE INTEREST ARITHMETIC AND THE COLLATERAL
--==============================================================================

-- RUN 4 of 11  |  save as P1_05b_interest_product.csv
-- INTEREST_SUMMARY (P1_05) turned out to hold the latest month only.
-- INTEREST_PRODUCT carries the balance-times-days (PRINCIPAL_PRODUCT), the
-- days, the rate and the amount per calculation. If it keeps history, it is
-- the exact arithmetic behind every interest posting.
SELECT p.*
FROM   interest_product p
WHERE  p.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY p.new_ac_number, p.product_from_date, p.interest_mst_id;


-- RUN 5 of 11  |  save as P2_11_security_details.csv
-- Collateral held per customer: description, value, type, reference, active.
-- Joined through the customer id of our 184 accounts.
SELECT a.new_ac_number, a.customer_id, s.*
FROM   cust_security_details s
JOIN   acmaster a ON a.customer_id = s.customer_id
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
ORDER  BY a.new_ac_number, s.cust_sec_det_id;


-- RUN 6 of 11  |  save as P2_12_guarantors.csv
-- Guarantors per account.
SELECT g.*
FROM   guaranter_master g
WHERE  g.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY g.new_ac_number, g.guaranter_srno;


-- RUN 7 of 11  |  save as P2_13_los_requests.csv
-- The origination system's request and response payloads (202,184 rows in all)
-- that mention one of our account numbers. If the payloads carry the fees and
-- the rate as captured at approval, part of what Finance is typing into the
-- take-on workbook can come from here instead. The join is a text search, so
-- this query is slower than the others; a few minutes is normal.
SELECT a.new_ac_number, r.*
FROM   los_request_details r
JOIN   acmaster a ON INSTR(r.request_string, a.new_ac_number) > 0
                  OR INSTR(r.responce_string, a.new_ac_number) > 0
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
ORDER  BY a.new_ac_number, r.transaction_date, r.los_req_id;


--==============================================================================
-- THE YEAR-END ADJUSTMENTS (also sent as its own note on 8 October)
--==============================================================================

-- RUN 8 of 11  |  save as GL_03_diffint_batch_legs.csv
-- Both legs of the two interest batches of 31 December 2025, unfiltered by
-- account, so that the GL side of the 22 "Diff Int. Debit" postings is seen.
-- Rows whose cb_glcode differs from ac_glcode, or with no account number, are
-- the GL legs; their cb_glcode is the account that took the other side.
SELECT cumvouch_det_id, cb_glcode, ac_glcode, new_ac_number, sub_account_no,
       transaction_date, transamt, dbcr, trantype, batch_type, batch_number,
       vscrno, particulars
FROM   cumvouch
WHERE  transaction_date = DATE '2025-12-31'
AND    batch_type   = 'INTP'
AND    batch_number IN (41, 42)
ORDER  BY batch_number, vscrno, cumvouch_det_id;


--==============================================================================
-- THE MEGA FARM SCHEMES: 96 fertilizer, 97 irrigation, 98 seed, 99 CAPEX,
-- 100 working capital, 101 pesticides, 102 and 103 equipment
--==============================================================================
-- The same extracts as the 7 October pack, for the Mega Farm schemes, so that
-- the question of whether they are in the engine's scope can be answered on
-- the postings rather than on trial-balance totals. MF_03 is the largest file
-- in either pack; if SQL Developer struggles, export it one scheme at a time
-- (replace the IN list with a single id) and name the files MF_03_96.csv etc.

-- RUN 9 of 11  |  save as MF_01_account_master.csv and MF_02_loan_master.csv (two queries)
SELECT a.*
FROM   acmaster a
WHERE  a.scheme_mst_id IN (96,97,98,99,100,101,102,103)
ORDER  BY a.scheme_mst_id, a.new_ac_number;

SELECT lm.*
FROM   acloanmaster lm
WHERE  lm.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a WHERE a.scheme_mst_id IN (96,97,98,99,100,101,102,103))
ORDER  BY lm.new_ac_number;


-- RUN 10 of 11  |  save as MF_03_ledger_all.csv, MF_04_balance_history.csv, MF_05_loan_book_history.csv (three queries)
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


-- RUN 11 of 11  |  save as MF_06_status_history.csv, MF_07_rate_setup.csv, MF_08_charges.csv (three queries)
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
-- End of pack 2. Thank you, Barry.
--==============================================================================
