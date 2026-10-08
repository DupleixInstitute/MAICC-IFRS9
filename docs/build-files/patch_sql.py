"""Apply the review's corrections to the data dictionary SQL pack (version 2)."""
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
p = D + r"\MAIIC_EBanker_Data_Dictionary_Queries.sql"
s = open(p, encoding="utf-8").read().replace("\r\n", "\n")


def rep(old, new, n=1):
    global s
    assert s.count(old) == n, (old[:60], s.count(old))
    s = s.replace(old, new)


T13 = """('ACMASTER', 'ACLOANMASTER', 'CUSTOMER_MAST', 'SCHEME_MASTER', 'GLMASTER',
                        'CUMVOUCH', 'ACCOUNT_BALANCE', 'FIXLOAN_INST_CHART', 'MULTI_INST_DETAILS',
                        'LOAN_DISBURSMENT_SCHEDULE', 'LOAN_RESCHEDULE_DETAILS',
                        'LOAN_WRITEOFF_MASTER', 'LOAN_WRITEOFF_TRANS')"""

# ---- header
rep("-- Prepared by Dupleix Institute, 5 October 2026\n--\n-- PURPOSE",
    "-- Prepared by Dupleix Institute, 5 October 2026\n-- Version 2, revised the same day after an independent review.\n--\n-- PURPOSE")
rep("""-- SAFE TO RUN
--   Every statement is a SELECT. Nothing is created, changed or deleted.
--   Queries DD_01 to DD_08 read Oracle's own catalogue views (ALL_TABLES and so
--   on), not business data. DD_09 to DD_15 read codes and counts. DD_16 reads the
--   rows of ONE sample loan.""",
    """-- SAFE TO RUN
--   Every query is a SELECT. Nothing is created, changed or deleted. The only
--   other statements are the two session settings below, which change how THIS
--   session displays dates and numbers and nothing in the database.
--   Queries DD_01 to DD_08 and DD_17 read Oracle's own catalogue views
--   (ALL_TABLES and so on), not business data. DD_09 to DD_15, DD_18 and DD_19
--   read codes, counts and checks. DD_16 reads the rows of ONE sample loan.""")
rep("""-- HOW TO RUN
--   1. Log in as the same user that runs PROC_BUILD_MAIIC_FACILITY_REGISTER.
--   2. Run each query on its own and export the result grid as CSV (not Excel),
--      using the file name given above the query, for example DD_01_schema.csv.
--   3. Please do not open and re-save the CSV files in Excel before sending:
--      that is what scrambled the dates in the earlier extracts.
--
-- THE SCHEMA
--   The queries find the schema by looking for the table ACMASTER, so no schema
--   name has to be typed in. If DD_01 returns no rows, the login cannot see
--   ACMASTER: please tell us which schema owns it.
--------------------------------------------------------------------------------
""",
    """-- HOW TO RUN
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
""")

# ---- DD_17 straight after DD_01
rep("""ORDER  BY t.owner, t.table_name;


-- DD_02_tables.csv""",
    """ORDER  BY t.owner, t.table_name;


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
WHERE  s.synonym_name IN """ + T13 + """
UNION ALL
SELECT 'PROCEDURE', o.object_name, o.owner, CAST(NULL AS VARCHAR2(128))
FROM   all_objects o
WHERE  o.object_type = 'PROCEDURE'
AND    o.object_name IN ('PROC_BUILD_MAIIC_FACILITY_REGISTER', 'PROC_BUILD_MAIIC_CASHFLOW_REGISTER',
                         'PROC_BUILD_MAIIC_GL_INTEREST_RECON')
ORDER  BY 1, 2;


-- DD_02_tables.csv""")

# ---- carry the owner through every catalogue output
rep("SELECT col.table_name,\n       col.column_id                          AS column_no,",
    "SELECT col.owner,\n       col.table_name,\n       col.column_id                          AS column_no,")
rep("ORDER  BY col.table_name, col.column_id;", "ORDER  BY col.owner, col.table_name, col.column_id;")
rep("SELECT c.table_name,\n       c.constraint_name,", "SELECT c.owner,\n       c.table_name,\n       c.constraint_name,")
rep("ORDER  BY c.table_name, c.constraint_type, c.constraint_name, cc.position;",
    "ORDER  BY c.owner, c.table_name, c.constraint_type, c.constraint_name, cc.position;")
rep("SELECT i.table_name,\n       i.index_name,", "SELECT i.table_owner                          AS owner,\n       i.table_name,\n       i.index_name,")
rep("ORDER  BY i.table_name, i.index_name, ic.column_position;", "ORDER  BY i.table_owner, i.table_name, i.index_name, ic.column_position;")
rep("SELECT col.table_name,\n       col.column_name,\n       col.data_type,\n       col.data_length,\n       t.num_rows",
    "SELECT col.owner,\n       col.table_name,\n       col.column_name,\n       col.data_type,\n       col.data_length,\n       t.num_rows")
rep("ORDER  BY col.column_name, col.table_name;", "ORDER  BY col.column_name, col.owner, col.table_name;")
rep("SELECT t.table_name,\n       t.num_rows,\n       c.comments                             AS table_description\nFROM   all_tables t",
    "SELECT t.owner,\n       t.table_name,\n       t.num_rows,\n       c.comments                             AS table_description\nFROM   all_tables t")
rep("ORDER  BY t.table_name;\n\n\n-- DD_08", "ORDER  BY t.owner, t.table_name;\n\n\n-- DD_08")
rep("SELECT d.type                                 AS object_type,\n       d.name                                 AS object_name,",
    "SELECT d.referenced_owner                     AS owner,\n       d.owner                                AS object_owner,\n       d.type                                 AS object_type,\n       d.name                                 AS object_name,")
rep("ORDER  BY d.referenced_name, d.type, d.name;", "ORDER  BY d.referenced_owner, d.referenced_name, d.type, d.name;")

# ---- DD_18 and DD_19 after DD_15
T12 = ["ACMASTER", "ACLOANMASTER", "CUSTOMER_MAST", "SCHEME_MASTER", "GLMASTER", "CUMVOUCH", "ACCOUNT_BALANCE",
       "FIXLOAN_INST_CHART", "MULTI_INST_DETAILS", "LOAN_DISBURSMENT_SCHEDULE", "LOAN_RESCHEDULE_DETAILS",
       "LOAN_WRITEOFF_MASTER", "LOAN_WRITEOFF_TRANS"]
access = "\n".join(
    "SELECT {:<27} AS table_name, COUNT(*) AS rows_read FROM {:<25} WHERE ROWNUM <= 1;".format("'" + t + "'", t.lower()) for t in T12)
rep("""SELECT 'SCHEME_MASTER',             COUNT(*) FROM scheme_master;
""",
    """SELECT 'SCHEME_MASTER',             COUNT(*) FROM scheme_master;


-- DD_18_read_access.csv
-- Tests that this login can read each table directly. Run the lines one at a
-- time. Each should return one row. Please note any line that fails with
-- "table or view does not exist": that table cannot be read by this login, and
-- the queries that use it will fail too. (LOAN_WRITEOFF_TRANS may simply not exist.)
""" + access + """


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
""")
rep("-- End of file. Twenty-four result sets in all: DD_01 to DD_15, and DD_16a to DD_16i.",
    "-- End of file. Twenty-seven queries in all: DD_01 to DD_15, DD_16a to DD_16i, and DD_17 to DD_19.")
open(p, "w", encoding="utf-8", newline="\r\n").write(s)
print("sql pack patched; DD markers:", s.count("\n-- DD_"))
