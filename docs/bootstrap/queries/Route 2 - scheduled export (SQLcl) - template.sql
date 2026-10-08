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

-- the masters come whole each time (P1_02, P1_03, P1_04, P2_06, P2_07, P3_12, P3_13): copy the SELECTs of the
-- versioned queries in this folder here, one SPOOL each, unchanged.

-- manifest.json: one entry per file with the query id, the row count (lines less the header) and the SHA-256.
-- Write it with the PowerShell step of the scheduled task after the spools:
--   Get-ChildItem *.csv | ForEach-Object { [pscustomobject]@{ file=$_.Name; query_id=($_.BaseName -split '_')[0..1] -join '_';
--     rows=((Get-Content $_).Count - 1); sha256=(Get-FileHash $_ -Algorithm SHA256).Hash.ToLower() } } |
--     ConvertTo-Json | Set-Content manifest.json  (wrap as {"pack": "...", "period": "&period", "source": "dates exported as yyyy-mm-dd", "files": [...]})
EXIT
