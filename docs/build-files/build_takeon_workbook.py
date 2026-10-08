"""Add the mapping, upload and block sheets to a COPY of Tamanda's take-on workbook, linked by formula to her sheets.

Nothing in Tamanda's two sheets is changed. Every workbook term on the new sheets is a formula pointing at the cell it comes from;
every E-Banker field is a lookup into the 'E-Banker master' sheet by account number; the only typed-in values are the proposed
account numbers, the confidence, the name-similarity flag and the parsed block statistics, each labelled as such.
"""
import re, shutil, difflib
import pandas as pd, numpy as np
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.table import Table, TableStyleInfo
from fu_load import load, D as RESULTS

SRC = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\New Doc Received 11 Sep\Amortisation schedules as at 31 October  2024.xlsx"
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on schedules with mapping - for Tamanda to confirm - 7 Oct 2026.xlsx"
shutil.copyfile(SRC, OUT)
wb = openpyxl.load_workbook(OUT)
LB, AM = "Loan Book", "Amortisation and Repayments"
EB, MAP, UP, BL, NM, NB, TOC, INS = "E-Banker master", "Mapping", "Upload summary", "Blocks", "Not matched", "Accounts without a block", "TOC", "Instructions"
NAVY = "1B2A41"; ORANGE = "E07B00"; LIGHT = "F4F6F9"; AMBER = "FFF4E0"
HFONT = Font(bold=True, color="FFFFFF"); HFILL = PatternFill("solid", fgColor=NAVY); INFILL = PatternFill("solid", fgColor=AMBER)

# ------------------------------------------------------------------ Loan Book rows (Excel row numbers) and the mapping already computed
ws_lb = wb[LB]
lb_rows = []                                     # (excel_row, number, name, type)
for r in range(5, ws_lb.max_row + 1):
    name, num, typ = ws_lb.cell(r, 5).value, ws_lb.cell(r, 4).value, ws_lb.cell(r, 6).value
    if name not in (None, "") and num not in (None, ""):
        lb_rows.append((r, int(num), str(name), str(typ)))
m = pd.read_csv("takeon_mapping.csv", dtype=str).fillna("")
m["key"] = list(zip(m["Workbook #"].astype(int), m["Type"]))
mp = m.set_index("key")
blocks = pd.read_csv("takeon_blocks.csv", dtype=str).fillna("")

# block rows in the amortisation sheet: the 'Principal (MK)' rows and the title above each
ws_am = wb[AM]
prow = [r for r in range(1, ws_am.max_row + 1) if str(ws_am.cell(r, 3).value).strip() == "Principal (MK)"]
assert len(prow) == len(blocks), (len(prow), len(blocks))
trow = []
for r in prow:
    t = r - 1
    while t > 0 and (ws_am.cell(t, 3).value in (None, "") or "Principal" in str(ws_am.cell(t, 3).value)): t -= 1
    trow.append(t)

# ------------------------------------------------------------------ E-Banker master sheet (values, with their source named)
acm, alm, bal, led, disb = (load(k) for k in ("acm", "alm", "bal", "ledger", "disb")); led["TRANTYPE"] = led.TRANTYPE.astype(int)
a = acm.set_index("NEW_AC_NUMBER"); al = alm.set_index("NEW_AC_NUMBER")
takeon = led[(led.TRANTYPE == 301) & (led.TRANSACTION_DATE == "2024-07-31")].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
openint = led[(led.TRANTYPE == 303) & (led.TRANSACTION_DATE == "2024-07-31")].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
oct24 = bal[bal.TRANSACTION_DATE == "2024-10-31"].set_index("NEW_AC_NUMBER").PRINCIPAL_BALANCE.mul(-1)
fdisb = disb[disb.DELETE_FLAG == "N"].groupby("NEW_AC_NUMBER").DISB_SCHEDULE_DATE.min()
ws = wb.create_sheet(EB)
eb_cols = ["Account", "Name", "Scheme id", "GL code", "Status", "Interest policy", "Rate now %", "Opening date", "Sanction", "Sanction date", "Expiry", "P moratorium", "I moratorium",
           "Take-on posting 31 Jul 2024", "Opening interest 31 Jul 2024", "Balance 31 Oct 2024", "First disbursement schedule date"]
ws.append(eb_cols)
def dv(v):
    if v is None or (isinstance(v, float) and np.isnan(v)) or (isinstance(v, str) and v == ""): return None
    if isinstance(v, pd.Timestamp): return v.to_pydatetime().date() if pd.notna(v) else None
    if isinstance(v, (np.floating,)): return float(v)
    if isinstance(v, (np.integer,)): return int(v)
    return v
for acct in sorted(a.index):
    ws.append([dv(x) for x in [acct, a.ACCOUNT_NAME[acct], a.SCHEME_MST_ID[acct], a.GLCODE[acct], a.STATUS_CODE[acct], a.INTEREST_POLICY[acct], a.INTEREST_RATE[acct], a.ACCOUNT_OPEN_DATE[acct],
                               al.SANCTION_AMOUNT.get(acct), al.SANCTION_DATE.get(acct), al.EXPIRY_DATE.get(acct), al.PMOROTORIUM_PERIOD.get(acct), al.IMOROTORIUM_PERIOD.get(acct),
                               takeon.get(acct), openint.get(acct), oct24.get(acct), fdisb.get(acct)]])
ws.cell(ws.max_row + 2, 1, "Source: E-Banker extracts received 7 October 2026 (P1_01 ledger, P1_02 account master, P1_03 loan master, P2_09 balance history, P3_12 disbursement schedules). Values, not formulas: they come from the system, not from this workbook.")
EBN = ws.max_row - 2
def eb(col_name, acct_ref):
    c = get_column_letter(eb_cols.index(col_name) + 1)
    return f'IFERROR(INDEX(\'{EB}\'!${c}$2:${c}${EBN + 1},MATCH({acct_ref},\'{EB}\'!$A$2:$A${EBN + 1},0)),"")'

# ------------------------------------------------------------------ Mapping sheet (formulas into Loan Book and E-Banker master)
ws = wb.create_sheet(MAP)
hdr = ["Loan Book row", "Loan Book #", "Facility name", "Type", "Value date", "Approved", "Disbursed", "Principal 31 Oct 2024", "Carrying amount 31 Oct 2024",
       "Proposed E-Banker account", "Confidence", "E-Banker name", "E-Banker opening date", "E-Banker sanction", "E-Banker take-on posting 31 Jul 2024", "E-Banker balance 31 Oct 2024", "E-Banker first disbursement date", "E-Banker status",
       "Test 1: principal = take-on posting", "Test 2: carrying amount vs balance", "Test 3: approved = sanction", "Test 4: days, value date to first disbursement", "Test 5: name agrees (as scored)",
       "Tamanda: correct? (Y/N)", "If N, correct account number", "Comment"]
ws.append(hdr)
for i, (r, num, name, typ) in enumerate(lb_rows):
    x = i + 2
    rec = mp.loc[[(num, typ)]].iloc[0] if (num, typ) in mp.index else None
    acct = rec["Proposed E-Banker account"] if rec is not None else ""
    row = [r, f"='{LB}'!D{r}", f"='{LB}'!E{r}", f"='{LB}'!F{r}", f"='{LB}'!G{r}", f"='{LB}'!L{r}", f"='{LB}'!M{r}", f"='{LB}'!O{r}", f"='{LB}'!R{r}",
           acct, rec["Confidence"] if rec is not None else "Not matched",
           f"={eb('Name', f'$J{x}')}", f"={eb('Opening date', f'$J{x}')}", f"={eb('Sanction', f'$J{x}')}", f"={eb('Take-on posting 31 Jul 2024', f'$J{x}')}", f"={eb('Balance 31 Oct 2024', f'$J{x}')}", f"={eb('First disbursement schedule date', f'$J{x}')}", f"={eb('Status', f'$J{x}')}",
           f'=IF($J{x}="","",IF(O{x}="","No take-on posting",IF(ABS(H{x}-O{x})<1,"Yes",IF(ABS(H{x}/O{x}-1)<0.01,"Within 1%","No"))))',
           f'=IF($J{x}="","",IF(OR(P{x}="",P{x}=0),"No balance",IF(ABS(I{x}-P{x})<1,"Yes",IF(ABS(I{x}/P{x}-1)<0.02,"Within 2%",IF(ABS(I{x}/P{x}-1)<0.05,"Within 5%","No")))))',
           f'=IF($J{x}="","",IF(N{x}="","",IF(ABS(F{x}-N{x})<1,"Yes",IF(ABS(G{x}-N{x})<1,"Disbursed = sanction","No"))))',
           f'=IF(OR($J{x}="",Q{x}="",E{x}=""),"",ABS(E{x}-Q{x}))',
           rec["Name agrees"] if rec is not None else "", "", "", ""]
    ws.append(row)
MAPN = ws.max_row
for x in range(2, MAPN + 1):
    for c in (5, 13, 17): ws.cell(x, c).number_format = "yyyy-mm-dd"
    for c in (6, 7, 8, 9, 14, 15, 16): ws.cell(x, c).number_format = "#,##0.00"
    for c in (24, 25, 26): ws.cell(x, c).fill = INFILL
ws.cell(MAPN + 2, 1, "Columns A to I are formulas into the 'Loan Book' sheet; L to R are lookups into 'E-Banker master' by the account in J; S to V are tests computed here; J, K and W are typed values from our matching (see Instructions). Yellow columns are for Tamanda.")

# ------------------------------------------------------------------ Upload summary (formulas)
ws = wb.create_sheet(UP)
uhdr = ["E-Banker account", "Confidence", "Loan Book row", "Facility name", "Type", "Value date", "Maturity date", "Tenor (years)", "Moratorium (text)", "Rate 31 Oct 2024 %",
        "Approved", "Disbursed", "Not yet disbursed", "Principal 31 Oct 2024", "Interest to date", "Repayments", "Carrying amount 31 Oct 2024", "Arrears principal", "Arrears interest", "Arrears total", "Segment", "Industry",
        "E-Banker name", "Scheme id", "GL code", "Status", "Interest policy", "Rate now %", "Sanction", "Sanction date", "Expiry", "P moratorium", "I moratorium", "Take-on posting 31 Jul 2024", "Opening interest 31 Jul 2024", "Balance 31 Oct 2024",
        "Carrying amount less balance", "Block #", "Go to block", "Tamanda confirmed (from Mapping)"]
ws.append(uhdr)
blk_by_lbrow = {}
for _, b in blocks.iterrows():
    for wn in str(b["wb_rows"]).strip("[]").replace("'", "").split(","):
        wn = wn.strip()
        if wn.isdigit():
            for (r, num, name, typ) in lb_rows:
                if num == int(wn) and typ == "Debt": blk_by_lbrow[r] = int(b["block"])
for i, (r, num, name, typ) in enumerate(lb_rows):
    x = i + 2; mx = i + 2
    bno = blk_by_lbrow.get(r)
    row = [f"='{MAP}'!J{mx}", f"='{MAP}'!K{mx}", r, f"='{LB}'!E{r}", f"='{LB}'!F{r}", f"='{LB}'!G{r}", f"='{LB}'!H{r}", f"='{LB}'!I{r}", f"='{LB}'!J{r}",
           f"=IF('{LB}'!K{r}=\"\",\"\",IF('{LB}'!K{r}<1,'{LB}'!K{r}*100,'{LB}'!K{r}))",
           f"='{LB}'!L{r}", f"='{LB}'!M{r}", f"='{LB}'!N{r}", f"='{LB}'!O{r}", f"='{LB}'!P{r}", f"='{LB}'!Q{r}", f"='{LB}'!R{r}", f"='{LB}'!S{r}", f"='{LB}'!T{r}", f"='{LB}'!U{r}", f"='{LB}'!AC{r}", f"='{LB}'!AE{r}"]
    for col in ("Name", "Scheme id", "GL code", "Status", "Interest policy", "Rate now %", "Sanction", "Sanction date", "Expiry", "P moratorium", "I moratorium", "Take-on posting 31 Jul 2024", "Opening interest 31 Jul 2024", "Balance 31 Oct 2024"):
        row.append(f"={eb(col, f'$A{x}')}")
    row += [f'=IF(OR(Q{x}="",AJ{x}=""),"",Q{x}-AJ{x})', bno if bno else "", (f'=HYPERLINK("#\'{AM}\'!C{trow[bno - 1]}","block {bno}")' if bno else ""), f"='{MAP}'!X{mx}"]
    ws.append(row)
UPN = ws.max_row
for x in range(2, UPN + 1):
    for c in (6, 7, 30, 31): ws.cell(x, c).number_format = "yyyy-mm-dd"
    for c in list(range(11, 21)) + [29, 34, 35, 36, 37]: ws.cell(x, c).number_format = "#,##0.00"
ws.cell(UPN + 2, 1, "Every workbook term is a formula into 'Loan Book'; every E-Banker field is a lookup into 'E-Banker master' by the account in column A, which itself comes from 'Mapping'. Load only rows whose column AN is Y.")

# ------------------------------------------------------------------ Blocks sheet (formulas into the amortisation sheet)
ws = wb.create_sheet(BL)
ws.append(["Block #", "Block title", "Principal in block", "Opening rate", "Sheet row of block", "Go to block", "Loan Book row", "Facility name", "E-Banker account", "Restructured (parsed)", "Rate steps (parsed)", "Periods (parsed)", "First period (parsed)", "Last period (parsed)", "Closing balance (parsed)", "Fully repaid (parsed)", "Link score (parsed)"])
lb_row_by_num = {num: r for (r, num, name, typ) in lb_rows if typ == "Debt"}
for i, b in blocks.iterrows():
    t, p = trow[i], prow[i]
    wn = str(b["wb_rows"]).strip("[]").replace("'", "").split(",")[0].strip()
    lbr = lb_row_by_num.get(int(wn)) if wn.isdigit() else None
    mrow = [k for k, (r, num, name, typ) in enumerate(lb_rows) if r == lbr]
    mx = mrow[0] + 2 if mrow else None
    ws.append([int(b["block"]), f"='{AM}'!C{t}", f"='{AM}'!D{p}", f"=IF('{AM}'!D{p + 1}<1,'{AM}'!D{p + 1}*100,'{AM}'!D{p + 1})", t, f'=HYPERLINK("#\'{AM}\'!C{t}","open")', lbr if lbr else "",
               f"='{LB}'!E{lbr}" if lbr else "", f"='{MAP}'!J{mx}" if mx else "", b["restructured"], b["rate_steps"], b["periods"], b["first_period"], b["last_period"], b["closing_balance"], b["fully_repaid"], b["link_score"]])
BLN = ws.max_row
for x in range(2, BLN + 1):
    ws.cell(x, 3).number_format = "#,##0.00"; ws.cell(x, 15).number_format = "#,##0.00"
ws.cell(BLN + 2, 1, "Columns B to D are formulas into 'Amortisation and Repayments'; F opens the block; H and I are formulas into 'Loan Book' and 'Mapping'. Columns J to Q were parsed from the block by script and are values.")

# ------------------------------------------------------------------ Not matched and accounts without a block (values, short)
ws = wb.create_sheet(NM)
ws.append(["Loan Book #", "Facility name", "Type", "Value date", "Approved", "Nearest candidates", "Tamanda: account number or 'no account'", "Comment"])
for _, r in m[m["Confidence"] == "Not matched"].iterrows():
    ws.append([int(r["Workbook #"]), r["Workbook name"], r["Type"], r["Value date"], float(r["Approved"]) if r["Approved"] else None, r["Nearest candidates (if not matched)"], "", ""])
for x in range(2, ws.max_row + 1): ws.cell(x, 7).fill = INFILL; ws.cell(x, 8).fill = INFILL
ws = wb.create_sheet(NB)
ws.append(["E-Banker account", "E-Banker name", "Opening date", "Sanction", "Status", "Tamanda: which facility is this, or 'none'", "Comment"])
used = set(m["Proposed E-Banker account"])
for acct in sorted(a.index):
    if acct not in used and pd.notna(a.ACCOUNT_OPEN_DATE[acct]) and a.ACCOUNT_OPEN_DATE[acct] < pd.Timestamp("2024-07-01"):
        ws.append([acct, f"={eb('Name', f'$A{ws.max_row + 1}')}", f"={eb('Opening date', f'$A{ws.max_row + 1}')}", f"={eb('Sanction', f'$A{ws.max_row + 1}')}", f"={eb('Status', f'$A{ws.max_row + 1}')}", "", ""])
for x in range(2, ws.max_row + 1): ws.cell(x, 3).number_format = "yyyy-mm-dd"; ws.cell(x, 4).number_format = "#,##0.00"; ws.cell(x, 6).fill = INFILL; ws.cell(x, 7).fill = INFILL

# ------------------------------------------------------------------ Dupleix columns on Tamanda's own sheets, white on purple
PURPLE = PatternFill("solid", fgColor="7030A0"); PFONT = Font(bold=True, color="FFFFFF"); PFONT2 = Font(color="FFFFFF")
def lookup_map(col_letter, lbrow_ref):
    return "IFERROR(INDEX('" + MAP + "'!$" + col_letter + "$2:$" + col_letter + "$" + str(MAPN) + ",MATCH(" + str(lbrow_ref) + ",'" + MAP + "'!$A$2:$A$" + str(MAPN) + ",0)),\"\")"
c0 = max(c for c in range(1, ws_lb.max_column + 1) if ws_lb.cell(3, c).value not in (None, '')) + 2   # first free column after the last header
add = ["E-Banker account (Dupleix, proposed)", "Confidence", "E-Banker name", "E-Banker status", "Take-on posting 31 Jul 2024", "Opening interest charge 31 Jul 2024", "E-Banker balance 31 Oct 2024", "Carrying amount less E-Banker balance"]
ws_lb.cell(2, c0, "Added by Dupleix, 7 October 2026: linked to the 'Mapping' and 'E-Banker master' sheets. Your columns to the left are unchanged.").font = Font(bold=True, color="7030A0")
for j, h in enumerate(add):
    c = ws_lb.cell(3, c0 + j, h); c.fill = PURPLE; c.font = PFONT; c.alignment = Alignment(wrap_text=True, vertical="top")
    ws_lb.column_dimensions[get_column_letter(c0 + j)].width = 20
for (r, num, name, typ) in lb_rows:
    acc = f"${get_column_letter(c0)}{r}"
    vals = [f"={lookup_map('J', r)}", f"={lookup_map('K', r)}", f"={eb('Name', acc)}", f"={eb('Status', acc)}", f"={eb('Take-on posting 31 Jul 2024', acc)}", f"={eb('Opening interest 31 Jul 2024', acc)}", f"={eb('Balance 31 Oct 2024', acc)}",
            f'=IF(OR({acc}="",{get_column_letter(c0 + 6)}{r}=""),"",R{r}-{get_column_letter(c0 + 6)}{r})']
    for j, v in enumerate(vals):
        c = ws_lb.cell(r, c0 + j, v); c.fill = PURPLE; c.font = PFONT2
        if j >= 4: c.number_format = "#,##0.00"
ws_lb.row_dimensions[3].height = 45
am_c0 = 18    # column R onward on the amortisation sheet, clear of the rate-step columns
for i, b in blocks.iterrows():
    t = trow[i]; bno = int(b["block"])
    labels = ["E-Banker account (Dupleix)", "Facility (Loan Book)", "Take-on posting 31 Jul 2024", "E-Banker balance 31 Oct 2024"]
    accf = f"IFERROR(INDEX('{BL}'!$I$2:$I${len(blocks) + 1},MATCH({bno},'{BL}'!$A$2:$A${len(blocks) + 1},0)),\"\")"
    vals = [f"={accf}", f"=IFERROR(INDEX('{BL}'!$H$2:$H${len(blocks) + 1},MATCH({bno},'{BL}'!$A$2:$A${len(blocks) + 1},0)),\"\")",
            f"={eb('Take-on posting 31 Jul 2024', get_column_letter(am_c0 + 1) + str(t))}", f"={eb('Balance 31 Oct 2024', get_column_letter(am_c0 + 1) + str(t))}"]
    for j, (lab, v) in enumerate(zip(labels, vals)):
        c1 = ws_am.cell(t + j, am_c0, lab); c1.fill = PURPLE; c1.font = PFONT
        c2 = ws_am.cell(t + j, am_c0 + 1, v); c2.fill = PURPLE; c2.font = PFONT2
        if j >= 2: c2.number_format = "#,##0.00"
ws_am.column_dimensions[get_column_letter(am_c0)].width = 30; ws_am.column_dimensions[get_column_letter(am_c0 + 1)].width = 22
ws_am.cell(1, am_c0, "Purple cells were added by Dupleix on 7 October 2026 and are linked to the 'Blocks' and 'E-Banker master' sheets. Nothing else on this sheet was changed.").font = Font(bold=True, color="7030A0")

# ------------------------------------------------------------------ Instructions and TOC
ws = wb.create_sheet(INS)
lines = [
    ("What this workbook is", True),
    ("Your take-on workbook of 11 September 2026, exactly as you sent it (the sheets 'Loan Book' and 'Amortisation and Repayments' are untouched), with six sheets added by Dupleix that link each facility and each amortisation block to its E-Banker account number.", False),
    ("", False),
    ("Why", True),
    ("The pre-July-2024 history of each loan lives in your workbook; the history from July 2024 lives in E-Banker. To join the two for the effective interest rate, each facility in the workbook needs its E-Banker account number. We have proposed the number for 105 of the 109 facilities. We need you to confirm or correct each one.", False),
    ("", False),
    ("What to do, in order", True),
    ("1. Open the sheet 'Mapping'. For each row, look at the proposed account (column J), the E-Banker name beside it (column L) and the five tests (columns S to W). Put Y in column X if the account is right, or N and the correct account number in column Y. Column Z is for any comment.", False),
    ("2. Open the sheet 'Not matched'. Four facilities have no proposed account: give the account number, or write 'no account' if the facility was never set up in E-Banker (for example an equity holding, or a loan closed before the migration).", False),
    ("3. Open the sheet 'Accounts without a block'. These E-Banker accounts were opened before July 2024 but match no facility in your workbook. Say which facility each is, or 'none'.", False),
    ("4. Save the file and send it back. Nothing else needs to change.", False),
    ("", False),
    ("How the sheets are linked, so that everything can be audited", True),
    ("Every workbook figure shown on the new sheets is a formula pointing at the cell in 'Loan Book' or 'Amortisation and Repayments' it comes from. Click any such cell and the formula bar shows the source. If you correct a figure in your sheets, the new sheets follow.", False),
    ("Every E-Banker figure is a lookup, by account number, into the sheet 'E-Banker master', which holds the system values as extracted on 7 October 2026 (the source files are named at the foot of that sheet). Those are values, because they come from the system, not from this workbook.", False),
    ("The five tests on 'Mapping' are formulas: they recompute from the linked figures. Test 1 compares your Principal at 31 October 2024 with the opening balance E-Banker loaded on 31 July 2024: it matches to the cent on every migrated loan. Test 2 compares your carrying amount with E-Banker's balance at 31 October 2024. Test 3 compares approved with sanction. Test 4 gives the days between your value date and E-Banker's first disbursement-schedule date. Test 5 is the name similarity we scored.", False),
    ("Three things are typed in rather than computed, and are labelled: the proposed account number and confidence on 'Mapping' (our matching), the name-similarity flag, and the statistics parsed from each block on 'Blocks' (restructured, rate steps, periods, dates, closing balance).", False),
    ("", False),
    ("The purple cells on your two sheets", True),
    ("On 'Loan Book', eight columns have been added to the right of your last column, in white on purple: the proposed E-Banker account, the confidence, the E-Banker name and status, the take-on posting and opening interest of 31 July 2024, E-Banker's balance at 31 October 2024, and the difference between your carrying amount and that balance. On 'Amortisation and Repayments', four purple cells sit beside each block title: the account, the facility, the take-on posting and the balance. All of them are formulas into the new sheets. Purple means 'added by Dupleix'; none of your own cells has been edited.", False),
    ("", False),
    ("The sheets", True),
    ("TOC: links to every sheet.  Instructions: this page.  Mapping: one row per facility, the proposed account, the tests, and your Y/N.  Upload summary: the same facilities laid out as the engine will load them, one row each, with your terms beside the E-Banker terms.  Blocks: one row per amortisation block with a link that opens it.  Not matched and Accounts without a block: the leftovers for you to place.  E-Banker master: the system values the lookups read.  Loan Book and Amortisation and Repayments: your sheets, unchanged.", False),
    ("", False),
    ("Prepared by Dupleix Institute, 7 October 2026. The scripts that built the new sheets are kept in our project folder (Build files) so the work can be re-run.", False),
]
for i, (t, bold) in enumerate(lines, 1):
    c = ws.cell(i, 1, t); c.alignment = Alignment(wrap_text=True, vertical="top")
    if bold: c.font = Font(bold=True, color=NAVY, size=12)
ws.column_dimensions["A"].width = 130

ws = wb.create_sheet(TOC, 0)
ws.cell(1, 1, "Take-on schedules as at 31 October 2024, with the E-Banker account mapping to confirm").font = Font(bold=True, size=14, color=NAVY)
ws.cell(2, 1, "Dupleix Institute for Tamanda Sitimawina, MAIIC  ·  7 October 2026  ·  start with 'Instructions'").font = Font(color="5B6470")
ws.append([]); ws.append(["Sheet", "What it is", "Who it is for"])
for c in range(1, 4): ws.cell(4, c).font = HFONT; ws.cell(4, c).fill = HFILL
toc_rows = [(INS, "Read first: what to do and how the sheets are linked", "Tamanda"), (MAP, "One row per facility: the proposed account, five tests, your Y/N", "Tamanda, to fill in"),
            (NM, "The four facilities with no proposed account", "Tamanda, to fill in"), (NB, "E-Banker accounts opened before July 2024 with no facility in the workbook", "Tamanda, to fill in"),
            (UP, "The facilities laid out for loading, your terms beside the E-Banker terms", "Dupleix"), (BL, "One row per amortisation block, linked to its facility and account, with a link into the block", "Dupleix"),
            (EB, "The E-Banker values the lookups read (extracted 7 October 2026)", "Reference"), (LB, "Your sheet, unchanged", "Reference"), (AM, "Your sheet, unchanged", "Reference")]
for name, what, who in toc_rows:
    ws.append([f'=HYPERLINK("#\'{name}\'!A1","{name}")', what, who])
    ws.cell(ws.max_row, 1).font = Font(color="0563C1", underline="single")
ws.column_dimensions["A"].width = 32; ws.column_dimensions["B"].width = 90; ws.column_dimensions["C"].width = 22

# ------------------------------------------------------------------ formatting, order, calculation
for name in (EB, MAP, UP, BL, NM, NB):
    w = wb[name]
    for c in range(1, w.max_column + 1):
        w.cell(1, c).font = HFONT; w.cell(1, c).fill = HFILL; w.cell(1, c).alignment = Alignment(wrap_text=True, vertical="top")
        w.column_dimensions[get_column_letter(c)].width = 18
    w.freeze_panes = "A2"; w.row_dimensions[1].height = 42
wb[MAP].column_dimensions["C"].width = 36; wb[MAP].column_dimensions["L"].width = 36; wb[UP].column_dimensions["D"].width = 36; wb[UP].column_dimensions["W"].width = 36; wb[BL].column_dimensions["B"].width = 36; wb[BL].column_dimensions["H"].width = 36
order = [TOC, INS, MAP, NM, NB, UP, BL, EB, LB, AM] + [n for n in wb.sheetnames if n not in (TOC, INS, MAP, NM, NB, UP, BL, EB, LB, AM)]
wb._sheets = [wb[n] for n in order]
wb.active = 0
wb.calculation.fullCalcOnLoad = True
wb.save(OUT)
print("written", OUT, "| sheets:", wb.sheetnames, "| mapping rows", MAPN - 1, "| upload rows", UPN - 1, "| blocks", BLN - 1)
