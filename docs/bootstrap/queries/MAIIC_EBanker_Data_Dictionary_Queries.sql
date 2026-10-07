--------------------------------------------------------------------------------
-- MAIIC E-Banker: data dictionary queries
-- Prepared by Dupleix Institute, 5 October 2026
-- Version 2, revised the same day after an independent review.
--
-- PURPOSE
--   To give us the structure of the E-Banker tables behind Extracts A, B and C
--   (the table and column names, data types, keys and code values), so that the
--   amended extract queries can be written against the real structure.
--
-- SAFE TO RUN
--   Every query is a SELECT. Nothing is created, changed or deleted. The only
--   other statements are the two session settings below, which change how THIS
--   session displays dates and numbers and nothing in the database.
--   Queries DD_01 to DD_08 and DD_17 read Oracle's own catalogue views
--   (ALL_TABLES and so on), not business data. DD_09 to DD_15, DD_18 and DD_19
--   read codes, counts and checks. DD_16 reads the rows of ONE sample loan.
--
-- HOW TO RUN
--   1. Log in as the same user that runs PROC_BUILD_MAIIC_FACILITY_REGISTER.
--   2. Run the two session settings below first.
--   3. Run each query on its own and export the result grid as CSV, using the
--      file name given above the query, for example DD_01_schema.csv.
--
-- HOW THE FILES SHOULD COME BACK (the export contract)
--   - CSV, comma separated, UTF-8, one header row.
--   - Dates as YYYY-MM-DD; dates with a time as YYYY-MM-DD HH:MM:SS (24 hour).
--     The session settings below produce this.
--   - Account numbers exactly as stored, as text, with their leading zeros.
--   - Amounts as plain numbers: a dot for the decimal point, no thousands
--     separators, and the minus sign kept.
--   - Please do not open and re-save the CSV files in Excel before sending.
--     Excel is what swapped days and months in the earlier extracts, and it
--     also drops the leading zeros from account numbers.
--
-- THE SCHEMA, AND WHAT THE LOGIN CAN READ
--   The catalogue queries find the schema by looking for the table ACMASTER, so
--   no schema name has to be typed in. Every catalogue result carries the OWNER,
--   because more than one schema can hold a table called ACMASTER (a test or
--   archive copy, for example).
--   - If DD_01 returns no rows, the login cannot see ACMASTER: please tell us
--     which schema owns it.
--   - If DD_01 lists more than one owner, please tell us which one is the live
--     schema before going further.
--   - DD_17 shows which schema the login's table names point to, and who owns
--     the three extract procedures. DD_18 tests that the login can read each
--     table directly. Being able to run a procedure does not prove that.
--------------------------------------------------------------------------------

-- SESSION SETTINGS: run these two lines first.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';


--==============================================================================
-- PART 1. STRUCTURE (Oracle catalogue only, no business data)
--==============================================================================

-- DD_01_schema.csv
-- Which schema owns the tables our three extract procedures read.
SELECT t.owner,
       t.table_name,
       t.num_rows,
       TO_CHAR(t.last_analyzed, 'YYYY-MM-DD') AS stats_date
FROM   all_tables t
WHERE  t.table_name IN ('ACMASTER', 'ACLOANMASTER', 'CUSTOMER_MAST', 'SCHEME_MASTER', 'GLMASTER',
                        'CUMVOUCH', 'ACCOUNT_BALANCE', 'FIXLOAN_INST_CHART', 'MULTI_INST_DETAILS',
                        'LOAN_DISBURSMENT_SCHEDULE', 'LOAN_RESCHEDULE_DETAILS',
                        'LOAN_WRITEOFF_MASTER', 'LOAN_WRITEOFF_TRANS')
ORDER  BY t.owner, t.table_name;


-- DD_17_login_and_synonyms.csv
-- Who is logged in, which schema that login's table names point to, any synonyms
-- standing in for the thirteen tables, and who owns the three extract procedures.
-- Table names inside a procedure are read from the procedure owner's schema.
SELECT 'LOGIN'                                AS item,
       USER                                   AS name,
       SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') AS points_to_owner,
       CAST(NULL AS VARCHAR2(128))            AS points_to_table
FROM   dual
UNION ALL
SELECT 'SYNONYM OWNED BY ' || s.owner, s.synonym_name, s.table_owner, s.table_name
FROM   all_synonyms s
WHERE  s.synonym_name IN ('ACMASTER', 'ACLOANMASTER', 'CUSTOMER_MAST', 'SCHEME_MASTER', 'GLMASTER',
                        'CUMVOUCH', 'ACCOUNT_BALANCE', 'FIXLOAN_INST_CHART', 'MULTI_INST_DETAILS',
                        'LOAN_DISBURSMENT_SCHEDULE', 'LOAN_RESCHEDULE_DETAILS',
                        'LOAN_WRITEOFF_MASTER', 'LOAN_WRITEOFF_TRANS')
UNION ALL
SELECT 'PROCEDURE', o.object_name, o.owner, CAST(NULL AS VARCHAR2(128))
FROM   all_objects o
WHERE  o.object_type = 'PROCEDURE'
AND    o.object_name IN ('PROC_BUILD_MAIIC_FACILITY_REGISTER', 'PROC_BUILD_MAIIC_CASHFLOW_REGISTER',
                         'PROC_BUILD_MAIIC_GL_INTEREST_RECON')
ORDER  BY 1, 2;


-- DD_02_tables.csv
-- Every table in the schema, with its approximate row count and any description.
SELECT t.owner,
       t.table_name,
       t.num_rows,
       TO_CHAR(t.last_analyzed, 'YYYY-MM-DD') AS stats_date,
       c.comments                             AS table_description
FROM   all_tables t
LEFT   JOIN all_tab_comments c
       ON c.owner = t.owner AND c.table_name = t.table_name
WHERE  t.owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
ORDER  BY t.table_name;


-- DD_03_columns.csv
-- Every column of every table in the schema: the data dictionary itself.
-- NUM_DISTINCT and NUM_NULLS come from optimiser statistics and show which
-- columns are actually populated.
SELECT col.owner,
       col.table_name,
       col.column_id                          AS column_no,
       col.column_name,
       col.data_type,
       col.data_length,
       col.data_precision,
       col.data_scale,
       col.nullable,
       col.num_distinct,
       col.num_nulls,
       cc.comments                            AS column_description
FROM   all_tab_columns col
JOIN   all_tables t
       ON t.owner = col.owner AND t.table_name = col.table_name
LEFT   JOIN all_col_comments cc
       ON cc.owner = col.owner AND cc.table_name = col.table_name AND cc.column_name = col.column_name
WHERE  col.owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
ORDER  BY col.owner, col.table_name, col.column_id;


-- DD_04_keys.csv
-- Primary keys (P), unique keys (U) and foreign keys (R), with the table each
-- foreign key points to. This is how the tables join.
SELECT c.owner,
       c.table_name,
       c.constraint_name,
       c.constraint_type,
       cc.position                            AS column_position,
       cc.column_name,
       r.table_name                           AS references_table,
       rc.column_name                         AS references_column,
       c.status
FROM   all_constraints c
JOIN   all_cons_columns cc
       ON cc.owner = c.owner AND cc.constraint_name = c.constraint_name
LEFT   JOIN all_constraints r
       ON r.owner = c.r_owner AND r.constraint_name = c.r_constraint_name
LEFT   JOIN all_cons_columns rc
       ON rc.owner = r.owner AND rc.constraint_name = r.constraint_name AND rc.position = cc.position
WHERE  c.owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
AND    c.constraint_type IN ('P', 'U', 'R')
ORDER  BY c.owner, c.table_name, c.constraint_type, c.constraint_name, cc.position;


-- DD_05_indexes.csv
-- Indexes and their columns. Where no keys are declared (DD_04 comes back short),
-- the unique indexes show what identifies a row.
SELECT i.table_owner                          AS owner,
       i.table_name,
       i.index_name,
       i.uniqueness,
       ic.column_position,
       ic.column_name
FROM   all_indexes i
JOIN   all_ind_columns ic
       ON ic.index_owner = i.owner AND ic.index_name = i.index_name
WHERE  i.table_owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
ORDER  BY i.table_owner, i.table_name, i.index_name, ic.column_position;


-- DD_06_account_key_tables.csv
-- Every table that carries the loan account key or one of the other keys we join
-- on. This finds the tables we have not been told about (fees, charges, rate
-- history, restructures, the link to the loan origination system).
SELECT col.owner,
       col.table_name,
       col.column_name,
       col.data_type,
       col.data_length,
       t.num_rows
FROM   all_tab_columns col
JOIN   all_tables t
       ON t.owner = col.owner AND t.table_name = col.table_name
WHERE  col.owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
AND    col.column_name IN ('NEW_AC_NUMBER', 'SUB_AC_NUMBER', 'SUB_ACCOUNT_NO', 'SCHEME_MST_ID',
                           'CUSTOMER_ID', 'TRANTYPE', 'GLCODE', 'AC_GLCODE', 'CBS_GLCODE',
                           'STATUS_CODE', 'FLOATING_FLAG')
ORDER  BY col.column_name, col.owner, col.table_name;


-- DD_07_tables_by_subject.csv
-- Tables whose names suggest the subjects we still need: interest policy and
-- rates, fees and charges, instalments, disbursements, restructures, write-offs,
-- status and transaction-type code lists, audit trail, loan origination, IFRS 9.
SELECT t.owner,
       t.table_name,
       t.num_rows,
       c.comments                             AS table_description
FROM   all_tables t
LEFT   JOIN all_tab_comments c
       ON c.owner = t.owner AND c.table_name = t.table_name
WHERE  t.owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
AND   (   t.table_name LIKE '%LOAN%'    OR t.table_name LIKE '%SCHEME%'
       OR t.table_name LIKE '%INST%'    OR t.table_name LIKE '%EMI%'
       OR t.table_name LIKE '%INT%'     OR t.table_name LIKE '%RATE%'
       OR t.table_name LIKE '%PLR%'     OR t.table_name LIKE '%SLAB%'
       OR t.table_name LIKE '%POLICY%'  OR t.table_name LIKE '%CHARGE%'
       OR t.table_name LIKE '%FEE%'     OR t.table_name LIKE '%DISB%'
       OR t.table_name LIKE '%RESCH%'   OR t.table_name LIKE '%RESTRUCT%'
       OR t.table_name LIKE '%WRITEOFF%' OR t.table_name LIKE '%MORAT%'
       OR t.table_name LIKE '%TRAN%TYPE%' OR t.table_name LIKE '%STATUS%'
       OR t.table_name LIKE '%AUDIT%'   OR t.table_name LIKE '%APPLICATION%'
       OR t.table_name LIKE '%PROPOSAL%' OR t.table_name LIKE '%SANCTION%'
       OR t.table_name LIKE '%NPA%'     OR t.table_name LIKE '%IFRS%'
       OR t.table_name LIKE '%VOUCH%'   OR t.table_name LIKE '%BALANCE%')
ORDER  BY t.owner, t.table_name;


-- DD_08_stored_code.csv
-- Views, procedures, functions and packages that read the tables our extracts
-- use. Names only, no source code. This shows which existing reports already sit
-- on the same data (for example the Loan Book and IFRS9 Detail reports).
SELECT d.referenced_owner                     AS owner,
       d.owner                                AS object_owner,
       d.type                                 AS object_type,
       d.name                                 AS object_name,
       d.referenced_name                      AS reads_table
FROM   all_dependencies d
WHERE  d.referenced_owner IN (SELECT owner FROM all_tables WHERE table_name = 'ACMASTER')
AND    d.referenced_type = 'TABLE'
AND    d.referenced_name IN ('ACMASTER', 'ACLOANMASTER', 'SCHEME_MASTER', 'CUMVOUCH', 'ACCOUNT_BALANCE',
                             'FIXLOAN_INST_CHART', 'MULTI_INST_DETAILS', 'LOAN_DISBURSMENT_SCHEDULE',
                             'LOAN_RESCHEDULE_DETAILS', 'LOAN_WRITEOFF_MASTER', 'LOAN_WRITEOFF_TRANS')
AND    d.type IN ('VIEW', 'PROCEDURE', 'FUNCTION', 'PACKAGE', 'PACKAGE BODY', 'TRIGGER')
ORDER  BY d.referenced_owner, d.referenced_name, d.type, d.name;


--==============================================================================
-- PART 2. CODES AND COUNTS (what the values in the columns mean)
--   From here on the queries use the table names directly, as the extract
--   procedures do, so they must be run as the same user as the procedures.
--==============================================================================

-- DD_09_schemes_all.csv
-- Every loan scheme with the number of accounts on it. Shows whether any scheme
-- falls outside the "MAIIC% or FINES%" name filter the extracts use.
SELECT s.scheme_mst_id,
       s.scheme_name,
       CASE WHEN UPPER(s.scheme_name) LIKE 'MAIIC%' OR UPPER(s.scheme_name) LIKE 'FINES%'
            THEN 'Y' ELSE 'N' END             AS in_extract_scope,
       COUNT(a.new_ac_number)                 AS accounts
FROM   scheme_master s
LEFT   JOIN acmaster a ON a.scheme_mst_id = s.scheme_mst_id
GROUP  BY s.scheme_mst_id, s.scheme_name
ORDER  BY s.scheme_name;


-- DD_10_scheme_settings.csv
-- Every setting held on the MAIIC and FInES schemes, all columns. This is where
-- Interest Policy, Floating Flag, Loan Interest Cal. Base On, Installment Based
-- On, EMI Calc Type and the moratorium limits are stored.
SELECT s.*
FROM   scheme_master s
WHERE  UPPER(s.scheme_name) LIKE 'MAIIC%' OR UPPER(s.scheme_name) LIKE 'FINES%'
ORDER  BY s.scheme_name;


-- DD_11_account_status_codes.csv
-- Account status codes in use on the MAIIC and FInES accounts (A, C, D, H, F ...).
SELECT a.status_code,
       COUNT(*)                                        AS accounts,
       TO_CHAR(MIN(a.account_status_date), 'YYYY-MM-DD') AS earliest_status_date,
       TO_CHAR(MAX(a.account_status_date), 'YYYY-MM-DD') AS latest_status_date
FROM   acmaster a
JOIN   scheme_master s ON s.scheme_mst_id = a.scheme_mst_id
WHERE  UPPER(s.scheme_name) LIKE 'MAIIC%' OR UPPER(s.scheme_name) LIKE 'FINES%'
GROUP  BY a.status_code
ORDER  BY a.status_code;


-- DD_12_transaction_types.csv
-- Transaction type codes posted on the MAIIC and FInES loan accounts, with the
-- direction of the amounts. POSITIVE_ROWS and NEGATIVE_ROWS show the sign
-- convention for each code.
SELECT cv.trantype,
       COUNT(*)                                             AS postings,
       SUM(CASE WHEN cv.transamt > 0 THEN 1 ELSE 0 END)     AS positive_rows,
       SUM(CASE WHEN cv.transamt < 0 THEN 1 ELSE 0 END)     AS negative_rows,
       SUM(cv.transamt)                                     AS net_amount,
       SUM(CASE WHEN cv.ac_glcode = a.glcode THEN 1 ELSE 0 END) AS rows_on_loan_gl,
       TO_CHAR(MIN(cv.transaction_date), 'YYYY-MM-DD')      AS first_posting,
       TO_CHAR(MAX(cv.transaction_date), 'YYYY-MM-DD')      AS last_posting
FROM   cumvouch cv
JOIN   acmaster a
       ON a.new_ac_number = cv.new_ac_number AND a.sub_ac_number = cv.sub_account_no
JOIN   scheme_master s ON s.scheme_mst_id = a.scheme_mst_id
WHERE (UPPER(s.scheme_name) LIKE 'MAIIC%' OR UPPER(s.scheme_name) LIKE 'FINES%')
AND    cv.delete_flag = 'N'
GROUP  BY cv.trantype
ORDER  BY cv.trantype;


-- DD_13_gl_codes_posted.csv
-- Every GL code the MAIIC and FInES loan accounts post to, by transaction type.
-- The extracts read only the loan's own GL code; this shows what else is posted
-- against the same accounts (fee income, penalties, interest in suspense).
SELECT cv.ac_glcode,
       gm.gltitle,
       cv.trantype,
       COUNT(*)                                        AS postings,
       SUM(cv.transamt)                                AS net_amount,
       TO_CHAR(MIN(cv.transaction_date), 'YYYY-MM-DD') AS first_posting,
       TO_CHAR(MAX(cv.transaction_date), 'YYYY-MM-DD') AS last_posting
FROM   cumvouch cv
JOIN   acmaster a
       ON a.new_ac_number = cv.new_ac_number AND a.sub_ac_number = cv.sub_account_no
JOIN   scheme_master s ON s.scheme_mst_id = a.scheme_mst_id
LEFT   JOIN glmaster gm ON gm.cbs_glcode = cv.ac_glcode
WHERE (UPPER(s.scheme_name) LIKE 'MAIIC%' OR UPPER(s.scheme_name) LIKE 'FINES%')
AND    cv.delete_flag = 'N'
GROUP  BY cv.ac_glcode, gm.gltitle, cv.trantype
ORDER  BY cv.ac_glcode, cv.trantype;


-- DD_14_other_codes.csv
-- The remaining code columns the extracts decode: repayment frequency and the
-- moratorium units.
SELECT 'MULTI_INST_DETAILS.INSTALLMENT_PAY_MODE' AS code_column,
       m.installment_pay_mode                    AS code_value,
       COUNT(*)                                  AS rows_using_it
FROM   multi_inst_details m
WHERE  m.delete_flag = 'N'
GROUP  BY m.installment_pay_mode
UNION ALL
SELECT 'ACLOANMASTER.PMOROTORIUM_UNIT', al.pmorotorium_unit, COUNT(*)
FROM   acloanmaster al
GROUP  BY al.pmorotorium_unit
UNION ALL
SELECT 'ACLOANMASTER.IMOROTORIUM_UNIT', al.imorotorium_unit, COUNT(*)
FROM   acloanmaster al
GROUP  BY al.imorotorium_unit
ORDER  BY 1, 2;


-- DD_15_row_counts.csv
-- Exact row counts for the smaller loan tables. A zero against
-- LOAN_RESCHEDULE_DETAILS would explain why RESTRUCTURE_DATE is blank on every
-- row of Extract A.
SELECT 'LOAN_RESCHEDULE_DETAILS' AS table_name, COUNT(*) AS row_count FROM loan_reschedule_details
UNION ALL
SELECT 'LOAN_WRITEOFF_MASTER',      COUNT(*) FROM loan_writeoff_master
UNION ALL
SELECT 'LOAN_WRITEOFF_TRANS',       COUNT(*) FROM loan_writeoff_trans
UNION ALL
SELECT 'LOAN_DISBURSMENT_SCHEDULE', COUNT(*) FROM loan_disbursment_schedule
UNION ALL
SELECT 'FIXLOAN_INST_CHART',        COUNT(*) FROM fixloan_inst_chart
UNION ALL
SELECT 'MULTI_INST_DETAILS',        COUNT(*) FROM multi_inst_details
UNION ALL
SELECT 'SCHEME_MASTER',             COUNT(*) FROM scheme_master;


-- DD_18_read_access.csv
-- Tests that this login can read each table directly. Run the lines one at a
-- time. Each should return one row. Please note any line that fails with
-- "table or view does not exist": that table cannot be read by this login, and
-- the queries that use it will fail too. (LOAN_WRITEOFF_TRANS may simply not exist.)
SELECT 'ACMASTER'                  AS table_name, COUNT(*) AS rows_read FROM acmaster                  WHERE ROWNUM <= 1;
SELECT 'ACLOANMASTER'              AS table_name, COUNT(*) AS rows_read FROM acloanmaster              WHERE ROWNUM <= 1;
SELECT 'CUSTOMER_MAST'             AS table_name, COUNT(*) AS rows_read FROM customer_mast             WHERE ROWNUM <= 1;
SELECT 'SCHEME_MASTER'             AS table_name, COUNT(*) AS rows_read FROM scheme_master             WHERE ROWNUM <= 1;
SELECT 'GLMASTER'                  AS table_name, COUNT(*) AS rows_read FROM glmaster                  WHERE ROWNUM <= 1;
SELECT 'CUMVOUCH'                  AS table_name, COUNT(*) AS rows_read FROM cumvouch                  WHERE ROWNUM <= 1;
SELECT 'ACCOUNT_BALANCE'           AS table_name, COUNT(*) AS rows_read FROM account_balance           WHERE ROWNUM <= 1;
SELECT 'FIXLOAN_INST_CHART'        AS table_name, COUNT(*) AS rows_read FROM fixloan_inst_chart        WHERE ROWNUM <= 1;
SELECT 'MULTI_INST_DETAILS'        AS table_name, COUNT(*) AS rows_read FROM multi_inst_details        WHERE ROWNUM <= 1;
SELECT 'LOAN_DISBURSMENT_SCHEDULE' AS table_name, COUNT(*) AS rows_read FROM loan_disbursment_schedule WHERE ROWNUM <= 1;
SELECT 'LOAN_RESCHEDULE_DETAILS'   AS table_name, COUNT(*) AS rows_read FROM loan_reschedule_details   WHERE ROWNUM <= 1;
SELECT 'LOAN_WRITEOFF_MASTER'      AS table_name, COUNT(*) AS rows_read FROM loan_writeoff_master      WHERE ROWNUM <= 1;
SELECT 'LOAN_WRITEOFF_TRANS'       AS table_name, COUNT(*) AS rows_read FROM loan_writeoff_trans       WHERE ROWNUM <= 1;


-- DD_19_date_time_check.csv
-- Whether the dates the extracts filter on carry a time of day. The extracts
-- test "between the start and end date" and "on or before the as-at date". If a
-- date carries a time, a posting made during the last day can be left out.
-- A zero in ROWS_WITH_TIME_OF_DAY means the date tests are safe for that column.
SELECT 'CUMVOUCH.TRANSACTION_DATE'           AS date_column,
       COUNT(*)                              AS rows_checked,
       SUM(CASE WHEN transaction_date <> TRUNC(transaction_date) THEN 1 ELSE 0 END) AS rows_with_time_of_day
FROM   cumvouch
UNION ALL
SELECT 'ACCOUNT_BALANCE.TRANSACTION_DATE', COUNT(*),
       SUM(CASE WHEN transaction_date <> TRUNC(transaction_date) THEN 1 ELSE 0 END)
FROM   account_balance
UNION ALL
SELECT 'FIXLOAN_INST_CHART.INSTALLMENT_DATE', COUNT(*),
       SUM(CASE WHEN installment_date <> TRUNC(installment_date) THEN 1 ELSE 0 END)
FROM   fixloan_inst_chart
UNION ALL
SELECT 'LOAN_WRITEOFF_MASTER.WRITEOFF_DATE', COUNT(*),
       SUM(CASE WHEN writeoff_date <> TRUNC(writeoff_date) THEN 1 ELSE 0 END)
FROM   loan_writeoff_master;


--==============================================================================
-- PART 3. ONE SAMPLE LOAN, EVERY COLUMN
--   The rows held for a single loan in each table, so that we can see what each
--   column actually contains. The account is Ebenezer Midian, one of the ten
--   sample loans already shared with us. Change the account number in each query
--   if another loan is preferred. Export each result as its own CSV.
--==============================================================================

-- DD_16a_sample_acmaster.csv
SELECT * FROM acmaster WHERE new_ac_number = '000104430000084';

-- DD_16b_sample_acloanmaster.csv
SELECT * FROM acloanmaster WHERE new_ac_number = '000104430000084';

-- DD_16c_sample_disbursement_schedule.csv
SELECT * FROM loan_disbursment_schedule WHERE new_ac_number = '000104430000084'
ORDER  BY disb_schedule_date;

-- DD_16d_sample_instalment_chart.csv
SELECT * FROM fixloan_inst_chart WHERE new_ac_number = '000104430000084'
ORDER  BY installment_date, inst_chart_det_id;

-- DD_16e_sample_instalment_plan.csv
SELECT * FROM multi_inst_details WHERE new_ac_number = '000104430000084'
ORDER  BY inst_det_id;

-- DD_16f_sample_ledger.csv
-- Every posting for the loan on every GL code, not only the loan's own.
SELECT * FROM cumvouch WHERE new_ac_number = '000104430000084'
ORDER  BY transaction_date, cumvouch_det_id;

-- DD_16g_sample_balances.csv
SELECT * FROM account_balance WHERE new_ac_number = '000104430000084'
ORDER  BY transaction_date;

-- DD_16h_sample_reschedule.csv
SELECT * FROM loan_reschedule_details WHERE new_ac_number = '000104430000084';

-- DD_16i_sample_writeoff.csv
SELECT * FROM loan_writeoff_master WHERE new_ac_number = '000104430000084';

--------------------------------------------------------------------------------
-- End of file. Twenty-seven queries in all: DD_01 to DD_15, DD_16a to DD_16i, and DD_17 to DD_19.
--------------------------------------------------------------------------------
