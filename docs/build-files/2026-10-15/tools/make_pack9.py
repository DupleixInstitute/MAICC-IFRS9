"""Pack 9 run sheet, per-query files, PDF, zip and an unsent .eml draft (nothing is sent)."""
import os, io, zipfile
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

BASE = r"C:\Users\DELL\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
PACK4 = os.path.join(BASE, "Received 12 October 2026", "Pack 4")
PACK9 = os.path.join(PACK4, "Pack 9")
DATE = "13 Oct 2026"

SESSION = """ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';"""

EXCL = "('SYS', 'SYSTEM', 'XDB', 'MDSYS', 'CTXSYS', 'ORDSYS', 'OLAPSYS', 'WMSYS', 'APEX_040200', 'DVSYS', 'LBACSYS', 'AUDSYS', 'GSMADMIN_INTERNAL', 'OJVMSYS', 'DBSNMP', 'OUTLN')"

# (run id, file name, title, reason, sql)
RUNS = [
    ("RUN 1", "CV_08_table_counts", "row counts of the tables in this pack",
     """Counts only, no data. Run this first: it tells us which of the later
-- runs can be exported in one grid. If a line errors because the table is
-- not there or not visible to you, delete that line and run the rest.""",
     """SELECT 'VGCBS.ACCOUNT_NO_MAPP' AS table_name, COUNT(*) AS row_count FROM vgcbs.account_no_mapp
UNION ALL SELECT 'VGCBS.NEW_ACMAP', COUNT(*) FROM vgcbs.new_acmap
UNION ALL SELECT 'VGCBS.OPENING_BAL', COUNT(*) FROM vgcbs.opening_bal
UNION ALL SELECT 'VGCBS.T_ACCOUNT', COUNT(*) FROM vgcbs.t_account
UNION ALL SELECT 'VGCBS.T_ACCOUNTBALANCE', COUNT(*) FROM vgcbs.t_accountbalance
UNION ALL SELECT 'VGCBS.TEMP_MASTER', COUNT(*) FROM vgcbs.temp_master
UNION ALL SELECT 'VGCBS.G_ACMASTER', COUNT(*) FROM vgcbs.g_acmaster
UNION ALL SELECT 'VGCBS.ACMASTER_MW', COUNT(*) FROM vgcbs.acmaster_mw
UNION ALL SELECT 'VGCBS.ACMASTER_NF', COUNT(*) FROM vgcbs.acmaster_nf
UNION ALL SELECT 'VGCBS.CUMOVUCH_OLD', COUNT(*) FROM vgcbs.cumovuch_old
UNION ALL SELECT 'VGCBS.GL_OPENING_BALANCE_BK240824', COUNT(*) FROM vgcbs.gl_opening_balance_bk240824
UNION ALL SELECT 'MAIIC_ORA_LIVE.CRM_ACMASTER_05082024', COUNT(*) FROM maiic_ora_live.crm_acmaster_05082024;"""),

    ("RUN 2a", "CV_09a_account_no_mapp", "the old-to-new account map",
     """ACCOUNT_NO_MAPP holds OLD_AC_NUMBER and NEW_OLDACNO: the link from an
-- account number before the migration to its number today. In full.""",
     "SELECT m.* FROM vgcbs.account_no_mapp m ORDER BY 1;"),

    ("RUN 2b", "CV_09b_new_acmap", "the E-Banker account map",
     """NEW_ACMAP holds EB_OLDACNO, the old account number against the new one. In full.""",
     "SELECT m.* FROM vgcbs.new_acmap m ORDER BY 1;"),

    ("RUN 3", "CV_10_opening_bal", "the opening balances taken on",
     """OPENING_BAL (about 95 rows): the balances taken on at the migration. We
-- expect the 78 term loans of 31 July 2024 among them; this should show how
-- the three opening postings per loan were prepared, in particular the
-- opening interest, which differs from the old ledger on 74 of the 78 loans.""",
     "SELECT o.* FROM vgcbs.opening_bal o ORDER BY 1;"),

    ("RUN 4a", "CV_11a_t_account", "the conversion's account table",
     """T_ACCOUNT holds OLDACCOUNTID: the accounts as loaded from the old system.
-- In full if RUN 1 shows 50,000 rows or fewer; if more, export only the
-- first 100 rows (add  FETCH FIRST 100 ROWS ONLY  before the semicolon) and tell us.""",
     "SELECT t.* FROM vgcbs.t_account t ORDER BY 1;"),

    ("RUN 4b", "CV_11b_t_accountbalance", "the conversion's balance table",
     """T_ACCOUNTBALANCE holds OLDACCOUNTID and the balances converted. Same rule
-- as RUN 4a for the size.""",
     "SELECT t.* FROM vgcbs.t_accountbalance t ORDER BY 1;"),

    ("RUN 5a", "CV_12a_old_block_g_acmaster", "the old loan accounts in G_ACMASTER",
     """The older ledger copy (CUMVOUCH_BAK) keeps its term loans on accounts
-- 000100750000002 to 000100750000084, which ACMASTER no longer holds (pack 8).
-- These three copies of the account master may still hold them.""",
     "SELECT a.* FROM vgcbs.g_acmaster a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;"),

    ("RUN 5b", "CV_12b_old_block_acmaster_mw", "the old loan accounts in ACMASTER_MW", "As RUN 5a.",
     "SELECT a.* FROM vgcbs.acmaster_mw a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;"),

    ("RUN 5c", "CV_12c_old_block_acmaster_nf", "the old loan accounts in ACMASTER_NF", "As RUN 5a.",
     "SELECT a.* FROM vgcbs.acmaster_nf a WHERE a.new_ac_number LIKE '00010075%' ORDER BY a.new_ac_number;"),

    ("RUN 5d", "CV_12d_acmaster_05082024_loans", "the account master as at 5 August 2024",
     """CRM_ACMASTER_05082024 is a copy of the account master taken days after the
-- migration: the loan accounts and the old block, as they stood then.""",
     """SELECT a.* FROM maiic_ora_live.crm_acmaster_05082024 a
WHERE  a.new_ac_number LIKE '00010075%'
OR     a.glcode IN ('1050', '1050101', '1050102', '1050103', '1050201', '1050202', '1050203', '1050401')
ORDER  BY a.new_ac_number;"""),

    ("RUN 5e", "CV_12e_temp_master", "the staging master",
     """TEMP_MASTER holds OLD_AC_NUMBER. In full if RUN 1 shows 20,000 rows or
-- fewer; if more, the first 100 rows only, as in RUN 4a.""",
     "SELECT t.* FROM vgcbs.temp_master t ORDER BY 1;"),

    ("RUN 6", "CV_13_cumovuch_old_profile", "a profile of the third old ledger copy",
     """CUMOVUCH_OLD (about 13,359 rows; the name is spelt this way in the
-- database) is a ledger copy we have not seen. Counts by year and account
-- block only, so we can see what it covers before asking for it.""",
     """SELECT EXTRACT(YEAR FROM c.transaction_date) AS yr,
       SUBSTR(c.new_ac_number, 1, 8)           AS account_block,
       COUNT(*)                                 AS row_count,
       MIN(c.transaction_date)                  AS first_date,
       MAX(c.transaction_date)                  AS last_date,
       SUM(c.transamt)                          AS net_amount
FROM   vgcbs.cumovuch_old c
GROUP  BY EXTRACT(YEAR FROM c.transaction_date), SUBSTR(c.new_ac_number, 1, 8)
ORDER  BY 1, 2;"""),

    ("RUN 7", "CV_14_gl_opening_balance_bk240824", "the GL opening balances at the August 2024 backup",
     """GL_OPENING_BALANCE_BK240824 (about 331 rows): the GL balances kept with the
-- 24 August 2024 backup, to tie the loans taken on to the GL at the migration.""",
     "SELECT g.* FROM vgcbs.gl_opening_balance_bk240824 g ORDER BY 1;"),
]

HEAD = f"""--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 9  |  Dupleix Institute  |  {DATE.replace('Oct', 'October')}
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 8 showed that the loan accounts behind the older ledger copy
-- (CUMVOUCH_BAK, accounts 000100750000002 to 000100750000084) are no longer in
-- ACMASTER or ACLOANMASTER, and that OLD_AC_NUMBER in today's account master
-- is a serial number within the GL, not the number a loan had before. It also
-- found the tables where the link and the migration working should be:
--
--   RUN 1       row counts of all the tables below (run first)
--   RUN 2a, 2b  the two account maps, ACCOUNT_NO_MAPP and NEW_ACMAP
--   RUN 3       OPENING_BAL, the balances taken on at the migration
--   RUN 4a, 4b  T_ACCOUNT and T_ACCOUNTBALANCE, the conversion tables
--   RUN 5a-5e   the old loan accounts in the other copies of the account master
--   RUN 6       a profile of CUMOVUCH_OLD, a third old ledger copy
--   RUN 7       GL_OPENING_BALANCE_BK240824, the GL at the migration
--
-- If a query errors because a table or column is not there, skip it and tell
-- us; the rest still help. Please save the results in the Pack 4 folder on
-- OneDrive, in the subfolder Pack 9\\Export Results.
--
-- Note on pack 8: the CV_04 files in Pack 8\\Export Results are copies of the
-- pack 7 files and were not needed. Two of them (CV_04b1 and CV_04b2) are
-- shorter than the pack 7 ones (5,980 and 4,465 rows against 7,019 and
-- 5,639), so they look like exports that stopped early; we use the pack 7
-- files, and nothing needs re-running.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 8 files still came out with dates as m/d/yyyy. These two lines
-- must be run in the same worksheet (same connection tab) as the queries;
-- each tab is its own session.
{SESSION}

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-14 09:15:00 and the number 1234567.89.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/14/2026, the export is
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
-- End of pack 9. Thank you, Barry.
--==============================================================================
"""

def per_query(r):
    rid, name, title, why, sql = r
    return f"""-- MAIIC E-Banker: pack 9 ({DATE.replace('Oct', 'October')}) | Dupleix Institute
-- Read-only. Run in one SQL Developer worksheet, export the grid as CSV
-- (header row, comma, strings in double quotes, UTF-8), named as below.
-- Session settings first (RUN 0), in the same worksheet:
{SESSION}

-- {rid}  |  {title}  |  save as {name}.csv
-- {why}
{sql}
"""

README = f"""PACK 9 QUERIES  |  Dupleix Institute  |  {DATE.replace('Oct', 'October')}

One file per query, in run order. Each opens with the RUN 0 session settings,
so it can be pasted into a fresh worksheet on its own. Export each grid as CSV
(header row, comma, strings in double quotes, UTF-8) under the name in the file,
and save it in the Export Results folder next to this file. Run RUN 1 first.
The full run sheet, with the reasons for each run, is the '(all runs)' file.

Files:
  RUN 0 - session settings and check.sql
""" + "".join(f"  {r[0]} - {r[1]}.sql\n" for r in RUNS)

RUN0 = f"""-- MAIIC E-Banker: pack 9 | RUN 0 session settings and check
{SESSION}
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;
-- It must show a date like 2026-10-14 09:15:00 and the number 1234567.89.
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

os.makedirs(os.path.join(PACK9, "Export Results"), exist_ok=True)
stem = f"FOLLOW-UP extracts for Barry - pack 9 - {DATE}"
for ext in ("txt", "sql"):
    open(os.path.join(BASE, f"{stem}.{ext}"), "w", encoding="utf-8", newline="\r\n").write(FULL)
pdf = pdf_bytes(FULL, f"{stem}")
open(os.path.join(BASE, f"{stem}.pdf"), "wb").write(pdf)

files = {"README.txt": README, "RUN 0 - session settings and check.sql": RUN0}
for r in RUNS:
    files[f"{r[0]} - {r[1]}.sql"] = per_query(r)
files[f"{stem} (all runs).sql"] = FULL
for n, t in files.items():
    open(os.path.join(PACK9, n), "w", encoding="utf-8", newline="\r\n").write(t)
open(os.path.join(PACK9, f"{stem} (all runs).pdf"), "wb").write(pdf)

zbuf = io.BytesIO()
with zipfile.ZipFile(zbuf, "w", zipfile.ZIP_DEFLATED) as z:
    for n, t in files.items():
        z.writestr(n, t.replace("\n", "\r\n"))
zip_name = f"Pack 9 - one file per query - {DATE}.zip"
open(os.path.join(BASE, zip_name), "wb").write(zbuf.getvalue())

BODY = """Dear Barry,

Thank you for pack 8, which came through complete.

It showed that the loan accounts behind the older ledger copy (CUMVOUCH_BAK, accounts 000100750000002 to 000100750000084) are no longer in ACMASTER or ACLOANMASTER: only 000100750000001, the "Term Loans" control account, is still there. The OLD_AC_NUMBER column in today's account master is a serial number within each GL, so it doesn't give the old number either. Your two probes found where the link and the migration working should be kept: ACCOUNT_NO_MAPP and NEW_ACMAP (the account maps), T_ACCOUNT and T_ACCOUNTBALANCE (the conversion tables), and OPENING_BAL (the balances taken on).

Pack 9 reads those tables, and a few related ones: thirteen grid exports, most of them small. RUN 1 gives the row counts of all of them, so please run it first; the run sheet says what to do if a table turns out large or isn't visible to you.

Two small things:
1. The CV_04 files in Pack 8\\Export Results were not needed (they're the pack 7 files). Two of them, CV_04b1 and CV_04b2, are shorter than the pack 7 copies, as if the export stopped early. We use the pack 7 files, so nothing needs re-running.
2. The dates still come out as m/d/yyyy. They load fine, but the run sheet repeats the Preferences setting that fixes it for good.

Where to save the results: the Pack 4 folder on OneDrive, in the new subfolder Pack 9\\Export Results. The queries are in Pack 9 too, one file per query, and the pack 8 review is in Pack 8\\Export Results\\Results review.

With thanks,

Edward Mazibuko
Director, Data Analytics
Dupleix Institute
"""

msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = "Barry Makumba <bmakumba@maiic.mw>"
msg["Cc"] = ("Thomson Kumwenda <tkumwenda@maiic.mw>, Tamanda Sitimawina <tsitimawina@maiic.mw>, "
             "Vanessa Magaleta <vmagaleta@maiic.mw>, Kundai Muriwo <kundai@dupleixinstitute.com>, "
             "Wadzanai Rombe <wadzanai@dupleixinstitute.com>")
msg["Subject"] = "MAIIC EIR: extracts pack 9 - the account maps and the migration working"
msg["Date"] = formatdate(localtime=True)
msg["Message-ID"] = make_msgid(domain="dupleixinstitute.com")
msg["X-Unsent"] = "1"
msg.set_content(BODY)
msg.add_attachment(pdf, maintype="application", subtype="pdf", filename=f"{stem}.pdf")
msg.add_attachment(zbuf.getvalue(), maintype="application", subtype="zip", filename=zip_name)
eml = os.path.join(BASE, f"EMAIL - extracts pack 9 - {DATE} (open in Outlook and send).eml")
open(eml, "wb").write(bytes(msg))

print("written:", PACK9, "and", eml)
print("runs:", len(RUNS), "exports")
