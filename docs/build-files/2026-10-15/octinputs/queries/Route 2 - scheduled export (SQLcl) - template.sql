-- Route 2 of the E-Banker feed (spec v4 section 6.5): the scheduled export at MAIIC.
-- Run by Windows Task Scheduler on the first working day after month-end under a read-only Oracle
-- account, with SQLcl, writing one CSV per query into a dated folder on the shared feed folder
-- (EBANKER_FEED_FOLDER on the system's server), then manifest.json. The system polls the folder
-- (eir:poll-feed-folder) and lands the pack through the same gates as a manual pack.
--
--   sql -S readonly/*****@EBANKER @"Route 2 - scheduled export (SQLcl) - template.sql" 2026-09 425764 359237 808196
--
-- Arguments: 1 the period YYYY-MM; 2 to 4 the watermarks the system reported after the last load
-- (CUMVOUCH_DET_ID, ACCOUNT_BAL_MST_ID, LOAN_BOOK_DET_ID_A), from the E-Banker Feed screen.
-- The RUN 0 settings of the 6 October request apply: ISO dates, point decimal, no thousands separators.
SET SQLFORMAT csv
SET FEEDBACK OFF
SET TERMOUT OFF
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';
DEFINE period = &1
DEFINE wm_ledger = &2
DEFINE wm_balance = &3
DEFINE wm_loanbook = &4
HOST mkdir "\maiic-fs\ebanker-feed\pack-&period"

-- M09_01 the ledger since the watermark, plus the two-month re-pull (back-dated postings, deletion flags)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\M09_01_ledger_since_watermark.csv"
SELECT * FROM CUMVOUCH WHERE CUMVOUCH_DET_ID > &wm_ledger
   OR TRANSACTION_DATE >= ADD_MONTHS(TRUNC(TO_DATE('&period-01','YYYY-MM-DD'),'MM'), -2)
 ORDER BY CUMVOUCH_DET_ID;
SPOOL OFF

-- M09_02 the balance history since the watermark
SPOOL "\maiic-fs\ebanker-feed\pack-&period\M09_02_balance_history.csv"
SELECT * FROM ACCOUNT_BALANCE WHERE ACCOUNT_BAL_MST_ID > &wm_balance ORDER BY ACCOUNT_BAL_MST_ID;
SPOOL OFF

-- M09_03 the loan-book runs since the watermark (every run kept; the system uses the latest per account-month)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\M09_03_loan_book_runs.csv"
SELECT * FROM LOAN_BOOK_DETAILS_ALL WHERE LOAN_BOOK_DET_ID_A > &wm_loanbook ORDER BY LOAN_BOOK_DET_ID_A;
SPOOL OFF

-- GL_04a the fee income GL legs since the ledger watermark (spec v4 sections 3.7 and 6.4: the fee control)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\GL_04a_fee_gl_legs.csv"
SELECT cumvouch_det_id, cb_branch_mst_id, t_branch_mst_id, o_branch_mst_id, cb_glcode, ac_glcode, new_ac_number,
       sub_account_no, transaction_date, transamt, dbcr, trantype, batch_type, batch_number, vscrno, particulars,
       pass_flag, delete_flag, modify_delete_flag
FROM   CUMVOUCH
WHERE  (cb_glcode IN ('4871','4873','4874','4875') OR ac_glcode IN ('4871','4873','4874','4875'))
AND    (CUMVOUCH_DET_ID > &wm_ledger OR TRANSACTION_DATE >= ADD_MONTHS(TRUNC(TO_DATE('&period-01','YYYY-MM-DD'),'MM'), -2))
ORDER  BY CUMVOUCH_DET_ID;
SPOOL OFF

-- GL_04b every other leg of those fee batches (the loan account or bank side)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\GL_04b_fee_batch_legs.csv"
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.t_branch_mst_id, cv.o_branch_mst_id, cv.cb_glcode, cv.ac_glcode,
       cv.new_ac_number, cv.sub_account_no, cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type,
       cv.batch_number, cv.vscrno, cv.particulars, cv.pass_flag, cv.delete_flag, cv.modify_delete_flag
FROM   CUMVOUCH cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number FROM CUMVOUCH x
         WHERE (x.cb_glcode IN ('4871','4873','4874','4875') OR x.ac_glcode IN ('4871','4873','4874','4875'))
         AND   (x.CUMVOUCH_DET_ID > &wm_ledger OR x.TRANSACTION_DATE >= ADD_MONTHS(TRUNC(TO_DATE('&period-01','YYYY-MM-DD'),'MM'), -2)))
ORDER  BY cv.CUMVOUCH_DET_ID;
SPOOL OFF

-- The instalment charts come whole each month (added 14 October 2026). Staging reads E-Banker's chart in
-- force at each month-end (InstalmentChartHistory: the missed-instalment trigger and the cure period) from
-- the current chart (P2_10), the instalment plans (P3_11) and the superseded instalment rows (RS_02a). Without
-- them in the monthly pack the chart history stops at the last manual pack (12 October 2026) and every later
-- month-end is staged on a stale chart. The SELECTs are the registered queries, unchanged; RS_02a is one file
-- here because SQLcl has no grid limit (the manual pack split it in five by account block).

-- P2_10 the current instalment chart with the amount recovered against each instalment (live rows only)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\P2_10_instalment_chart_current.csv"
SELECT f.*
FROM   fixloan_inst_chart f
WHERE  f.delete_flag = 'N'
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.installment_date, f.inst_chart_det_id;
SPOOL OFF

-- P3_11 the instalment plans
SPOOL "\maiic-fs\ebanker-feed\pack-&period\P3_11_instalment_plans.csv"
SELECT m.*
FROM   multi_inst_details m
WHERE  m.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY m.new_ac_number, m.inst_det_id;
SPOOL OFF

-- RS_02a the superseded instalment rows (deleted or inactive): the earlier versions of each schedule
SPOOL "\maiic-fs\ebanker-feed\pack-&period\RS_02a_superseded_instalments.csv"
SELECT f.*
FROM   fixloan_inst_chart f
WHERE  (f.delete_flag = 'Y' OR f.active_flag = 'N')
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.operation_date, f.inst_sr_no;
SPOOL OFF

-- the masters come whole each time (P1_02, P1_03, P1_04, P2_06, P2_07, P3_12, P3_13): copy the SELECTs of the
-- versioned queries in this folder here, one SPOOL each, unchanged.

-- manifest.json: one entry per file with the query id, the row count (lines less the header) and the SHA-256.
-- Write it with the PowerShell step of the scheduled task after the spools:
--   Get-ChildItem *.csv | ForEach-Object { [pscustomobject]@{ file=$_.Name; query_id=($_.BaseName -split '_')[0..1] -join '_';
--     rows=((Get-Content $_).Count - 1); sha256=(Get-FileHash $_ -Algorithm SHA256).Hash.ToLower() } } |
--     ConvertTo-Json | Set-Content manifest.json  (wrap as {"pack": "...", "period": "&period", "source": "dates exported as yyyy-mm-dd", "files": [...]})
EXIT
