"""Pack 10 run sheet, per-query files, PDF, zip and an unsent .eml draft (nothing is sent)."""
import os, io, zipfile
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

BASE = r"C:\Users\DELL\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
PACK4 = os.path.join(BASE, "Received 12 October 2026", "Pack 4")
PACK10 = os.path.join(PACK4, "Pack 10")
REPO_QUERIES = r"C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap\queries"
DATE = "14 Oct 2026"
LONGDATE = DATE.replace('Oct', 'October')

SESSION = """ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';"""

SCHEMES = "84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145"

OD_COLS = """o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date"""

def od_daily(start, end):
    upper = f"\nAND    o.ovd_calc_date <  DATE '{end}'" if end else ""
    return f"""SELECT {OD_COLS}
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN ({SCHEMES}))
AND    o.ovd_calc_date >= DATE '{start}'{upper}
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;"""

# the tables the packs read (the annex of the read-only access request), for DD_17 and DD_18
VGCBS_TABLES = ["ACMASTER", "ACLOANMASTER", "CUSTOMER_MAST", "SCHEME_MASTER", "GLMASTER", "CUMVOUCH",
                "CUMOVUCH_OLD", "ACCOUNT_BALANCE", "LOAN_BOOK_DETAILS_ALL", "FIXLOAN_INST_CHART",
                "MULTI_INST_DETAILS", "LOAN_DISBURSMENT_SCHEDULE", "ACCOUNT_STATUS_HISTORY", "OVERDUE_DETAILS",
                "INTEREST_SLAB_MASTER", "INTEREST_SLAB_DETAILS", "INTEREST_SUMMARY", "INTEREST_PRODUCT",
                "DAILY_INTEREST_ACCRUAL", "FORGIVE_INTEREST_DETAILS", "PLR_MASTER", "LOS_DISB_CHARGES_POST",
                "LOS_REQUEST_DETAILS", "CUST_SECURITY_DETAILS", "GUARANTER_MASTER", "LOAN_RESCHEDULE_DETAILS",
                "LOAN_WRITEOFF_MASTER", "MODIFICATION_LOG", "ACCOUNT_CLOSER_HISTORY", "GL_OPENING_BALANCE",
                "GL_OPENING_BALANCE_BK240824", "TRIAL_BALANCE_HISTORY", "OPENING_BAL", "LOAN_WRITEOFF_TRANS"]
ALL_TABLES = [("VGCBS", t) for t in VGCBS_TABLES] + [("MAIIC_ORA_LIVE", "CRM_ACMASTER_05082024")]

def in_list(names, indent):
    out, line = [], indent
    for i, n in enumerate(names):
        piece = f"'{n}'" + (", " if i < len(names) - 1 else "")
        if len(line) + len(piece) > 100:
            out.append(line.rstrip()); line = indent
        line += piece
    out.append(line.rstrip())
    return "\n".join(out).lstrip()

NAMES = in_list(sorted({t for _, t in ALL_TABLES}), " " * 26)

DD17 = f"""SELECT 'LOGIN'                                  AS item,
       USER                                     AS name,
       SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') AS points_to_owner,
       CAST(NULL AS VARCHAR2(128))              AS points_to_table
FROM   dual
UNION ALL
SELECT 'SYNONYM OWNED BY ' || s.owner, s.synonym_name, s.table_owner, s.table_name
FROM   all_synonyms s
WHERE  s.synonym_name IN ({NAMES})
UNION ALL
SELECT 'PROCEDURE', o.object_name, o.owner, CAST(NULL AS VARCHAR2(128))
FROM   all_objects o
WHERE  o.object_type = 'PROCEDURE'
AND    o.object_name IN ('PROC_BUILD_MAIIC_FACILITY_REGISTER', 'PROC_BUILD_MAIIC_CASHFLOW_REGISTER',
                         'PROC_BUILD_MAIIC_GL_INTEREST_RECON')
ORDER  BY 1, 2;"""

def dd18():
    lines = []
    for i, (owner, t) in enumerate(ALL_TABLES):
        q = f"{owner}.{t}"
        src = f"{owner.lower()}.{t.lower()}"
        if i == 0:
            lines.append(f"SELECT '{q}' AS table_name, COUNT(*) AS rows_read FROM {src} WHERE ROWNUM <= 1")
        else:
            lines.append(f"UNION ALL SELECT '{q}', COUNT(*) FROM {src} WHERE ROWNUM <= 1")
    return "\n".join(lines) + ";"

REFI_ACCOUNTS = ["000104460000109", "000104460000120", "000104420000043", "000104450000068",
                 "000106050000003", "000104430000072", "000106050000006"]
REFI = in_list(REFI_ACCOUNTS, " " * 35)

# (run id, file name, title, reason, sql)
RUNS = [
    ("RUN 1", "OD_00_overdue_profile", "the overdue run, rows per calculation month",
     """Pack 9's RUN 8a, which hadn't been run. A few dozen rows: rows, accounts
-- and calculation days per month, so we can tell whether each daily file (the
-- three received and the three below) holds every day it should. Run first.""",
     f"""SELECT TO_CHAR(TRUNC(o.ovd_calc_date, 'MM'), 'YYYY-MM')  AS calc_month,
       COUNT(*)                                            AS rows_in_month,
       COUNT(DISTINCT o.new_ac_number)                     AS accounts,
       COUNT(DISTINCT TRUNC(o.ovd_calc_date))              AS calc_days,
       MIN(o.ovd_calc_date)                                AS first_calc_date,
       MAX(o.ovd_calc_date)                                AS last_calc_date,
       SUM(CASE WHEN o.reschedulement_date IS NOT NULL THEN 1 ELSE 0 END) AS rows_with_reschedule_date
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN ({SCHEMES}))
GROUP  BY TRUNC(o.ovd_calc_date, 'MM')
ORDER  BY 1;"""),

    ("RUN 2a", "OD_02_overdue_daily_2026Q2", "the overdue run, daily rows, April to June 2026",
     """The rest of pack 9's RUN 8c: E-Banker's own day-by-day arrears count per
-- account, the independent check on the ageing the system stages loans on.
-- Same 34 columns as the pack 9 daily files; about 11,000 rows (3.5 MB).
-- Run and export the three quarters one at a time.""",
     od_daily('2026-04-01', '2026-07-01')),

    ("RUN 2b", "OD_02_overdue_daily_2026Q3", "the overdue run, daily rows, July to September 2026",
     "As RUN 2a. About 10,000 rows.",
     od_daily('2026-07-01', '2026-10-01')),

    ("RUN 2c", "OD_02_overdue_daily_2026Q4", "the overdue run, daily rows, 1 October 2026 to today",
     """As RUN 2a, from 1 October to the day you run it: about 1,500 rows. The
-- rest of the quarter will come with a later pack.""",
     od_daily('2026-10-01', None)),

    ("RUN 3", "CV_15_crm_acmaster_05082024", "the account master copy of 5 August 2024",
     """Pack 9's RUN 5d found none of its 102 rows on the loan GLs or the old
-- 00010075 block. All 102 rows, the identifying columns only (no contact
-- details): it shows what this copy holds and whether its OLD_AC_NUMBER gives
-- the numbers loans had before the migration.""",
     """SELECT a.account_mst_id, a.glcode, SUBSTR(a.new_ac_number, 1, 8) AS account_block,
       a.new_ac_number, a.sub_ac_number, a.old_ac_number, a.customer_id, a.account_name,
       a.scheme_mst_id, a.status_code, a.account_open_date, a.transaction_date,
       a.operation_date, a.active_flag, a.delete_flag, a.pass_flag
FROM   maiic_ora_live.crm_acmaster_05082024 a
ORDER  BY a.glcode, a.new_ac_number, a.account_mst_id;"""),

    ("RUN 4", "RF_01_refinance_batch_legs", "every leg of the batches that paid off a loan from a new one",
     """JVD Agro (104460000109 paid off on 31 March 2026, 104460000120 drawn the
-- same day), The Food Empire (104420000043 closed into 104450000068 on 30
-- December 2025, that one into 106050000003 on 1 May 2026) and, it seems,
-- Malawi Police SACCO (104430000072 into 106050000006). Every leg of each
-- batch that drew down or paid off one of these seven accounts, 10 million and
-- above: it shows how each payoff was funded. A few hundred rows.""",
     f"""SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype,
       cv.batch_type, cv.batch_number, cv.vscrno, cv.particulars, cv.trf_glcode,
       cv.trf_new_ac_number, cv.pass_flag, cv.delete_flag, cv.modify_delete_flag,
       cv.back_dated_date, cv.operation_date
FROM   vgcbs.cumvouch cv
WHERE  (cv.cb_branch_mst_id, cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.cb_branch_mst_id, x.transaction_date, x.batch_type, x.batch_number
         FROM   vgcbs.cumvouch x
         WHERE  x.new_ac_number IN ({REFI})
         AND    x.trantype IN (301, 305, 306)
         AND    ABS(x.transamt) >= 10000000)
ORDER  BY cv.transaction_date, cv.batch_type, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;"""),

    ("RUN 5", "DD_20_link_columns_probe", "any column that may link a loan to the loan that replaced it",
     """Names and statistics only, no data. REC_FM_NEWACNO in ACMASTER and
-- ACLOANMASTER is the account repayments are recovered from (both JVD loans
-- point to the same current account, 000105910000476), so it isn't the link.
-- This lists any other column whose name suggests one: transfer, refinance,
-- top-up, renewal, closure, takeover or restructure. A few hundred rows.""",
     """SELECT c.owner, c.table_name, c.column_name, c.data_type,
       t.num_rows, c.num_distinct, c.num_nulls
FROM   all_tab_columns c
JOIN   all_tables t ON t.owner = c.owner AND t.table_name = c.table_name
WHERE  c.owner IN ('VGCBS', 'MAIIC_ORA_LIVE')
AND    (t.num_rows IS NULL OR t.num_rows > 0)
AND   (c.column_name LIKE '%TRF%AC%'
OR     c.column_name LIKE '%TRANSFER%AC%'
OR     c.column_name LIKE '%REFIN%'
OR     c.column_name LIKE '%TOP%UP%'
OR     c.column_name LIKE '%RENEW%AC%'
OR     c.column_name LIKE '%PARENT%AC%'
OR     c.column_name LIKE '%LINK%AC%'
OR     c.column_name LIKE '%PREV%LOAN%'
OR     c.column_name LIKE '%OLD%LOAN%'
OR     c.column_name LIKE '%NEW%LOAN%'
OR     c.column_name LIKE '%CLOSE%AC%'
OR     c.column_name LIKE '%TAKE%OVER%'
OR     c.column_name LIKE '%SETTLE%AC%'
OR     c.column_name LIKE '%RESTRUC%'
OR     c.column_name LIKE '%RESCH%')
ORDER  BY c.owner, c.table_name, c.column_name;"""),

    ("RUN 6", "TB_06_stored_tb_balance_check", "a check on the stored trial balance since 1 May 2026",
     """One row per day: the stored trial balance summed over the posting GLs
-- (it should be 0.00) and GL 6730 Fines Expenses. Pack 6 found it 88,000 out
-- from 15 May 2026: one voucher (Lloyd Banda, batch TRF 4, voucher 21) never
-- reached the stored history. If the vendor has rebuilt it, every day shows
-- 0.00. About 165 rows.""",
     """SELECT t.transaction_date,
       COUNT(*)                                                         AS gl_rows,
       SUM(NVL(t.closing_cr_bal, 0) + NVL(t.closing_dr_bal, 0))         AS net_closing_all_gls,
       SUM(CASE WHEN t.glcode = '6730'
                THEN NVL(t.closing_cr_bal, 0) + NVL(t.closing_dr_bal, 0)
                ELSE 0 END)                                             AS gl_6730_closing
FROM   vgcbs.trial_balance_history t
WHERE  t.parent_flag = 'N'
AND    t.transaction_date >= DATE '2026-05-01'
GROUP  BY t.transaction_date
ORDER  BY t.transaction_date;"""),

    ("RUN 7", "ZF_04_zaithwa_balance_rows_recheck", "a check on the Zaithwa Farms balance rows",
     """The month-end balance rows of Zaithwa Farms (000104450000015) from April
-- 2025. Since May 2025 they have been 1,827,633.62 away from the ledger (our
-- note of 7 October). If the vendor has rebuilt them, they now agree. About
-- 20 rows.""",
     """SELECT b.*
FROM   vgcbs.account_balance b
WHERE  b.new_ac_number = '000104450000015'
AND    b.transaction_date >= DATE '2025-04-01'
ORDER  BY b.transaction_date, b.account_bal_mst_id;"""),

    ("RUN 8a", "DD_17_login_and_synonyms", "who is logged in, and where the table names point",
     """From the data dictionary set of 5 October, not yet run. Catalogue only:
-- the login, the schema its table names point to, any synonyms for the tables
-- the packs read, and who owns the three extract procedures. A few rows.""",
     DD17),

    ("RUN 8b", "DD_18_read_access", "can this login read each table directly",
     """Also from 5 October. One row per table the packs read; each should show
-- 1. If a line errors with "table or view does not exist", delete that line,
-- run the rest, and tell us which one it was. 35 rows.""",
     dd18()),
]

QUESTIONS = [
    ("The Loan Book Report before December 2024",
     "The stored runs start at 31 December 2024. Can E-Banker produce the report as at the month-ends from 31 July to 30 November 2024? If it can, please tell us before running anything and we'll agree the dates with you. Without it, the arrears for those months can't be measured."),
    ("Re-run reports and deleted instalment definitions",
     "The as-at reports re-run in September 2026 for earlier month-ends age instalments from definitions that had already been deleted (Namalowe Eco Farm, Mchinji Civil Servants SACCO, Microloan Foundation, Ifracon). Why does a re-run read a deleted definition?"),
    ("Instalments all on one date",
     "Udon Business's instalments 35 to 73 are all dated 1 December 2025, and BenCal Technologies has 36 instalments on one date. Did the migration load them that way, and are the right dates held anywhere?"),
    ("Status codes D and F",
     "What do they mean? D is on 46 accounts in September 2026 (1,117.8 million), including Malawi Police SACCO 104430000072 with 2.2 million left; F is on The Food Empire's older accounts."),
    ("Refinanced loans",
     "When a loan is paid off from a new loan, does E-Banker record the link anywhere? RUN 4 and RUN 5 look for it."),
    ("Ecogen 104460000111",
     "The rate is 0 in the account master, the rate set-up and the Loan Book Report, and no interest has been charged since the 105.06 million drawdown in May 2025, although the 3.65 million monthly repayments look like a loan that carries interest. Was the rate left out at set-up?"),
    ("Renumbered loans",
     "Please confirm that Ifracon's 000104460000101 (closed), 000104430000059 (closed) and 000104430000062 (live) are one loan, and that Tapempha Medical Care's 000104460000058 became 000104430000061."),
    ("Happie Foods 104420000062",
     "E-Banker holds two schedules: one with value date 24 March 2023 (6.9 million a month to January 2027) and one with value date 14 September 2022 (13.5 million a month to September 2026). Which one is the loan's schedule?"),
]

def wrap(text, width, first, rest):
    words, lines, line = text.split(), [], first
    for w in words:
        if len(line) + len(w) + 1 > width and line.strip():
            lines.append(line.rstrip()); line = rest
        line += w + " "
    lines.append(line.rstrip())
    return lines

Q_SQL = []
for i, (t, q) in enumerate(QUESTIONS, 1):
    Q_SQL += wrap(f"{i}. {t}. {q}", 79, "--   ", "--      ")
Q_SQL = "\n".join(Q_SQL)

HEAD = f"""--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 10  |  Dupleix Institute  |  {LONGDATE}
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 9 came back complete, and the split overdue run worked: the month-end
-- rows (OD_01, 31 December 2024 to 30 September 2026) and the daily rows for
-- July 2025 to March 2026 (OD_02, three files) are in. Pack 10 asks for what
-- is still open:
--
--   RUN 1       the overdue run, a profile by month (pack 9's RUN 8a, run first)
--   RUN 2a-2c   the overdue run, daily rows from April 2026 to today, one quarter per file
--   RUN 3       the account master copy of 5 August 2024 (CRM_ACMASTER_05082024)
--   RUN 4       every leg of the batches that paid off a loan from a new loan
--   RUN 5       a names-only probe for a column that links a loan to its replacement
--   RUN 6       a check on the stored trial balance (the 88,000 on GL 6730)
--   RUN 7       a check on the Zaithwa Farms balance rows (the 1,827,633.62)
--   RUN 8a, 8b  DD_17 and DD_18 from the data dictionary set: what this login reads
--
-- Every table carries its owner (VGCBS or MAIIC_ORA_LIVE). If a query errors
-- because a table or column is not there, skip it and tell us; the rest still
-- help. Please save the results in the Pack 4 folder on OneDrive, in the
-- subfolder Pack 10\\Export Results.
--
-- QUESTIONS THAT NEED NO QUERY (a short reply by email is fine):
{Q_SQL}
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 9 files still came out with dates as m/d/yyyy. They load as they
-- are, but please put the cursor on each ALTER SESSION line and press
-- Ctrl+Enter, in the same worksheet (same connection tab) as the queries;
-- each tab is its own session.
{SESSION}

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-15 09:15:00 and the number 1234567.89.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/15/2026, the export is
-- re-formatting it: Tools > Preferences > Database > NLS > Date Format =
-- YYYY-MM-DD HH24:MI:SS (type NLS in the search box at the top left of the
-- Preferences window if Database is not visible). Set once, it sticks.
"""

def run_block(r):
    rid, name, title, why, sql = r
    return f"""

--==============================================================================
-- {rid}  |  {title}  |  save as {name}.csv
-- {why}
{sql}
"""

FULL = HEAD + "".join(run_block(r) for r in RUNS) + """
--==============================================================================
-- End of pack 10. Thank you, Barry.
--==============================================================================
"""

def per_query(r):
    rid, name, title, why, sql = r
    return f"""-- MAIIC E-Banker: pack 10 ({LONGDATE}) | Dupleix Institute
-- Read-only. Run in one SQL Developer worksheet, export the grid as CSV
-- (header row, comma, strings in double quotes, UTF-8), named as below.
-- Session settings first (RUN 0), in the same worksheet: put the cursor on
-- each ALTER SESSION line and press Ctrl+Enter, then run the SELECT.
{SESSION}

-- {rid}  |  {title}  |  save as {name}.csv
-- {why}
{sql}
"""

README = f"""PACK 10 QUERIES  |  Dupleix Institute  |  {LONGDATE}

One file per query, in run order. Each opens with the RUN 0 session settings,
so it can be pasted into a fresh worksheet on its own. Export each grid as CSV
(header row, comma, strings in double quotes, UTF-8) under the name in the file,
and save it in the Export Results folder next to this file. Run RUN 1 first.
The full run sheet, with the reasons for each run and eight questions that need
no query, is the '(all runs)' file.

Files:
  RUN 0 - session settings and check.sql
""" + "".join(f"  {r[0]} - {r[1]}.sql\n" for r in RUNS)

RUN0 = f"""-- MAIIC E-Banker: pack 10 | RUN 0 session settings and check
-- Put the cursor on each ALTER SESSION line and press Ctrl+Enter.
{SESSION}
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;
-- It must show a date like 2026-10-15 09:15:00 and the number 1234567.89.
"""

def pdf_bytes(text, title):
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=A4)
    c.setTitle(title); c.setAuthor('Dupleix Institute')
    w, h = A4
    y = h - 40
    c.setFont('Courier', 7.6)
    for line in text.splitlines():
        while len(line) > 110:
            c.drawString(36, y, line[:110]); line = '    ' + line[110:]; y -= 9.6
            if y < 40: c.showPage(); c.setFont('Courier', 7.6); y = h - 40
        c.drawString(36, y, line); y -= 9.6
        if y < 40: c.showPage(); c.setFont('Courier', 7.6); y = h - 40
    c.save()
    return buf.getvalue()

# ASCII only, no em dashes, in everything that goes to MAIIC
for label, text in [("FULL", FULL), ("README", README)] + [(r[0], per_query(r)) for r in RUNS]:
    bad = [ch for ch in text if ord(ch) > 126 and ch not in "\n"]
    assert not bad, f"non-ASCII in {label}: {set(bad)}"

os.makedirs(os.path.join(PACK10, "Export Results"), exist_ok=True)
stem = f"FOLLOW-UP extracts for Barry - pack 10 - {DATE}"
for ext in ("txt", "sql"):
    open(os.path.join(BASE, f"{stem}.{ext}"), "w", encoding="utf-8", newline="\r\n").write(FULL)
pdf = pdf_bytes(FULL, f"{stem}")
open(os.path.join(BASE, f"{stem}.pdf"), "wb").write(pdf)

files = {"README.txt": README, "RUN 0 - session settings and check.sql": RUN0}
for r in RUNS:
    files[f"{r[0]} - {r[1]}.sql"] = per_query(r)
files[f"{stem} (all runs).sql"] = FULL
for n, t in files.items():
    open(os.path.join(PACK10, n), "w", encoding="utf-8", newline="\r\n").write(t)
open(os.path.join(PACK10, f"{stem} (all runs).pdf"), "wb").write(pdf)

zbuf = io.BytesIO()
with zipfile.ZipFile(zbuf, "w", zipfile.ZIP_DEFLATED) as z:
    for n, t in files.items():
        z.writestr(n, t.replace("\n", "\r\n"))
zip_name = f"Pack 10 - one file per query - {DATE}.zip"
open(os.path.join(BASE, zip_name), "wb").write(zbuf.getvalue())

# the committed copy in the repository (LF, as packs 4 to 8 are kept)
open(os.path.join(REPO_QUERIES, f"Pack 10 - {DATE}.sql"), "w", encoding="utf-8", newline="\n").write(FULL)

Q_MAIL = "\n".join(f"{i}. {t}. {q}" for i, (t, q) in enumerate(QUESTIONS, 1))

BODY = f"""Dear Barry,

Thank you for pack 9. It came through complete, and so did the overdue files once RUN 8 was split: the month-end rows (OD_01) and the daily rows for July 2025 to March 2026 (the three OD_02 files) are all in.

Pack 9 answered a lot. OPENING_BAL turned out to be the migration worksheet, and its figures agree with the opening postings of 31 July 2024 on almost every loan. The GL opening balances kept with the August 2024 backup tie to the older ledger copy to the kwacha. And the account map tables are empty, which is an answer too.

Pack 10 is smaller: eleven grid exports, most of them a few dozen to a few hundred rows.
- RUN 1 to 2c finish the overdue run: a profile by month (pack 9's RUN 8a, which hadn't been run), then the daily rows from April 2026 to today, one quarter per file, as before. They give us E-Banker's own day-by-day arrears count, which is the independent check on the ageing we stage loans on.
- RUN 3 is the 102 rows of the account master copy of 5 August 2024 (identifying columns only, no contact details), since pack 9's RUN 5d found nothing on the loan GLs there.
- RUN 4 and 5 are about loans paid off from a new loan (JVD Agro, The Food Empire, and it seems Malawi Police SACCO). RUN 4 gives every leg of those payoff and drawdown batches; RUN 5 looks, by column name only, for anything in E-Banker that links a loan to the one that replaced it.
- RUN 6 and 7 are two quick checks on the vendor fixes: the 88,000 on GL 6730 in the stored trial balance since 15 May 2026, and the Zaithwa Farms balance rows since May 2025. Please run them either way: they'll show whether the rebuilds have been done.
- RUN 8a and 8b are DD_17 and DD_18 from the data dictionary set of 5 October, which weren't run. They show which tables your login reads directly, so we know every pack query will run as written.

A few questions that don't need a query (a short reply is fine):
{Q_MAIL}

The dates in pack 9 still came through as m/d/yyyy. They load fine; the run sheet repeats the RUN 0 lines and the Preferences setting in case it helps.

Where to save the results: the Pack 4 folder on OneDrive, in the new subfolder Pack 10\\Export Results. The queries are in Pack 10 too, one file per query, and the pack 9 review is in Pack 9\\Export Results\\Results review.

With thanks,

Edward Mazibuko
Director, Data Analytics
Dupleix Institute
"""
bad = [ch for ch in BODY if ord(ch) > 126 and ch != "\n"]
assert not bad, f"non-ASCII in the email body: {set(bad)}"

msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = "Barry Makumba <bmakumba@maiic.mw>"
msg["Cc"] = ("Thomson Kumwenda <tkumwenda@maiic.mw>, Tamanda Sitimawina <tsitimawina@maiic.mw>, "
             "Vanessa Magaleta <vmagaleta@maiic.mw>, Kundai Muriwo <kundai@dupleixinstitute.com>, "
             "Wadzanai Rombe <wadzanai@dupleixinstitute.com>")
msg["Subject"] = "MAIIC EIR: extracts pack 10 - the rest of the overdue run, refinanced loans and two checks"
msg["Date"] = formatdate(localtime=True)
msg["Message-ID"] = make_msgid(domain="dupleixinstitute.com")
msg["X-Unsent"] = "1"
msg.set_content(BODY)
msg.add_attachment(pdf, maintype="application", subtype="pdf", filename=f"{stem}.pdf")
msg.add_attachment(zbuf.getvalue(), maintype="application", subtype="zip", filename=zip_name)
eml = os.path.join(BASE, f"EMAIL - extracts pack 10 - {DATE} (open in Outlook and send).eml")
open(eml, "wb").write(bytes(msg))

print("written:", PACK10, "and", eml)
print("runs:", len(RUNS), "exports")
