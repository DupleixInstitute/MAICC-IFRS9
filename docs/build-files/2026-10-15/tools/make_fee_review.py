"""MAIIC fee classification review workbook for Finance (read-only from production maiic_ifrs9)."""
import subprocess, sys
from collections import defaultdict
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.utils import get_column_letter

OUT = sys.argv[1]
M = r"C:\xampp\mysql\bin\mysql.exe"


def rows(sql):
    r = subprocess.run([M, "-uroot", "-B", "--default-character-set=utf8mb4", "maiic_ifrs9"], input=sql,
                       capture_output=True, text=True, encoding="utf-8")
    if r.returncode:
        raise SystemExit(r.stderr)
    lines = r.stdout.rstrip("\n").split("\n")
    head = lines[0].split("\t")
    out = []
    for l in lines[1:]:
        vals = [None if v == "NULL" else v.replace("\\n", " ").replace("\\t", " ") for v in l.split("\t")]
        out.append(dict(zip(head, vals)))
    return out


fees = rows("""
SELECT f.id, f.contract_id, COALESCE(NULLIF(e.customer_name, ''),
         (SELECT NULLIF(l.customer_name, '') FROM loan_books l WHERE l.contract_id = f.contract_id AND l.customer_name <> '' ORDER BY l.reporting_period DESC LIMIT 1),
         (SELECT JSON_UNQUOTE(JSON_EXTRACT(r.payload, '$.ACCOUNT_NAME')) FROM ebanker_raw_rows r WHERE r.query_id = 'P1_02'
            AND r.account IN (f.contract_id, CONCAT('000', f.contract_id)) AND r.superseded_at IS NULL ORDER BY r.id DESC LIMIT 1), '') customer, f.fee_type, f.description, f.amount, f.transaction_date,
       f.source_system, f.source_reference, f.gl_account_ref, f.cashflow_direction, f.superseded_by_source,
       f.classification_status, COALESCE(e.calculation_status, '') eir_status
FROM contract_fees f LEFT JOIN contract_eir e ON e.contract_id = f.contract_id
ORDER BY f.contract_id, f.fee_type, f.source_system, f.id""")
rules = rows("""SELECT id, name, fee_type, description_contains, gl_account_ref, cashflow_direction, proposed_integral, priority, rationale
FROM eir_accounting_rules WHERE active = 1 ORDER BY priority, id""")
prec = rows("SELECT value FROM governance_settings WHERE `key` = 'fee_source_precedence'")[0]["value"]

for f in fees:
    f["amount"] = float(f["amount"])


def match(f):
    """The app's FeeRuleMatcher, run as if every active rule were approved."""
    for r in rules:
        if r["fee_type"] and (f["fee_type"] or "").lower() != r["fee_type"].lower():
            continue
        if r["gl_account_ref"] and (f["gl_account_ref"] or "") != r["gl_account_ref"]:
            continue
        if r["cashflow_direction"] and (f["cashflow_direction"] or "").upper() != r["cashflow_direction"]:
            continue
        if r["description_contains"] and r["description_contains"].lower() not in (f["description"] or "").lower():
            continue
        return r
    return None


SRC = {"E-Banker GL fee posting": "General ledger", "E-Banker LOS_DISB_CHARGES_POST": "Loan system (at disbursement)",
       "TAKEON_WORKBOOK": "Take-on workbook"}

# GL and loan-system lines for the same loan and fee type
pair = defaultdict(lambda: {"gl": 0.0, "los": 0.0, "gl_n": 0, "los_n": 0})
for f in fees:
    k = (f["contract_id"], f["fee_type"])
    if f["source_system"] == "E-Banker GL fee posting":
        pair[k]["gl"] += f["amount"]; pair[k]["gl_n"] += 1
    elif f["source_system"] == "E-Banker LOS_DISB_CHARGES_POST":
        pair[k]["los"] += f["amount"]; pair[k]["los_n"] += 1
both = {k: v for k, v in pair.items() if v["gl_n"] and v["los_n"]}
differ = {k for k, v in both.items() if abs(v["gl"] - v["los"]) > 1}

for f in fees:
    r = match(f)
    f["rule"] = r
    flags = []
    k = (f["contract_id"], f["fee_type"])
    if f["superseded_by_source"]:
        flags.append("Not counted: the same fee is taken from the " + f["superseded_by_source"] + " (source order)")
    if k in differ:
        v = both[k]
        flags.append(f"GL and loan system disagree: GL {v['gl']:,.2f} vs loan system {v['los']:,.2f}")
    elif k in both and not f["superseded_by_source"]:
        flags.append("Also in the loan system with the same amount")
    d = (f["description"] or "").lower()
    if "insurance" in d or f["gl_account_ref"] == "4874":
        flags.append("Insurance recharge (GL 4874): usually NOT part of the EIR")
    elif d.startswith("other fee"):
        flags.append("Loan system does not say what this fee is: please name it")
    if f["source_system"] == "TAKEON_WORKBOOK":
        flags.append("Take-on loan: confirm against the offer letter")
    if r is None:
        flags.append("No rule matches: decide by hand")
    elif r["proposed_integral"] != "1" and f["gl_account_ref"] in ("4871", "4873"):
        word = (r["description_contains"] or "").strip()
        flags.append(f"Rule {r['id']} would make this NOT integral only because the description contains '{word}'"
                     f" (part of the customer's name); it is on origination fee GL {f['gl_account_ref']}, so it is likely integral")
    f["flags"] = "; ".join(flags)
    f["needs_decision"] = "No (reference only)" if f["superseded_by_source"] else "Yes"

# ---------------------------------------------------------------- styles
TEAL = "1F5C66"
HDR = Font(bold=True, color="FFFFFF")
HFILL = PatternFill("solid", fgColor=TEAL)
INFILL = PatternFill("solid", fgColor="FFF4CC")
FLAGFILL = PatternFill("solid", fgColor="FDE2E1")
GREY = PatternFill("solid", fgColor="EEEEEE")
THIN = Border(bottom=Side(style="thin", color="DDDDDD"))
WRAP = Alignment(wrap_text=True, vertical="top")
NUM = "#,##0.00"

wb = Workbook()


def table(ws, headers, data, widths, inputs=(), start=1, numcols=()):
    for c, h in enumerate(headers, 1):
        cell = ws.cell(row=start, column=c, value=h)
        cell.font = HDR; cell.alignment = Alignment(wrap_text=True, vertical="center")
        cell.fill = INFILL if h in inputs else HFILL
        if h in inputs:
            cell.font = Font(bold=True, color="000000")
    for i, row in enumerate(data, start + 1):
        for c, v in enumerate(row, 1):
            cell = ws.cell(row=i, column=c, value=v)
            cell.alignment = WRAP; cell.border = THIN
            if headers[c - 1] in numcols:
                cell.number_format = NUM
            if headers[c - 1] in inputs:
                cell.fill = INFILL
    for c, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(c)].width = w
    ws.row_dimensions[start].height = 45
    ws.freeze_panes = ws.cell(row=start + 1, column=3)
    ws.auto_filter.ref = f"A{start}:{get_column_letter(len(headers))}{start + len(data)}"


def dv(ws, col, options, first, last, prompt):
    v = DataValidation(type="list", formula1='"' + ",".join(options) + '"', allow_blank=True)
    v.promptTitle = "Choose"; v.prompt = prompt; v.showInputMessage = True
    ws.add_data_validation(v); v.add(f"{col}{first}:{col}{last}")


# ---------------------------------------------------------------- Read me
ws = wb.active; ws.title = "Read me"
decide = [f for f in fees if f["needs_decision"] == "Yes"]
blocked = len({f["contract_id"] for f in fees if f["eir_status"] == "BLOCKED"})
text = [
    ("MAIIC IFRS 9: fee classification review", "title"),
    ("Prepared by Dupleix Institute, 15 October 2026, from the IFRS 9 system's production database.", None),
    ("", None),
    ("Why this is needed", "h"),
    (f"The system will not calculate a loan's effective interest rate (EIR) while any of its fee lines has not been classified and independently reviewed. {blocked} loans are held for this reason. Their EIRs feed the discounted ECL and the IFRS 9 note.", None),
    ("", None),
    ("The question for each fee", "h"),
    ("Is the fee an integral part of the loan's effective interest rate (IFRS 9 B5.4.1 to B5.4.3)? If YES, it is spread over the life of the loan through the EIR (for example arrangement, front-end and legal fees for setting up the loan). If NO, it goes straight to profit or loss (for example insurance recharged to the borrower, monitoring or annual fees, penalties).", None),
    ("", None),
    ("What is in this workbook", "h"),
    (f"Fee lines: all {len(fees)} lines. {len(decide)} need a decision; the other {len(fees) - len(decide)} are loan-system copies of a fee the general ledger already records, so they are not counted and are shown for reference only.", None),
    ("Rules to approve: the 30 fee rules proposed in the system. None is approved yet. Approving the rules lets the system suggest the treatment for most lines at once.", None),
    (f"GL vs loan system: the {len(both)} loan and fee-type pairs recorded in both places, {len(differ)} of them with different amounts.", None),
    ("By loan: one row per loan with its fee lines and its EIR status.", None),
    ("", None),
    ("How to fill it in", "h"),
    ("1. On 'Rules to approve', mark each rule Approve, Change or Reject. The five that cover most lines are rules 1, 2, 3, 10 and 11.", None),
    ("2. On 'Fee lines', fill the yellow columns for every line marked 'Needs a decision = Yes': Integral to the EIR (Yes or No), Cash direction (Received or Paid; the system's suggestion is shown), the agreed amount where it differs, and a reason where you depart from the suggestion.", None),
    ("3. Look first at the lines with a red flag: the GL and loan system disagree, the loan system does not name the fee, an insurance recharge, or no rule matches.", None),
    ("4. 'Classified by' and 'Reviewed by' must be two different people: the system enforces this (maker-checker).", None),
    ("5. Return the workbook; the decisions are then entered in the system under EIR > Fee Classification (classify, then review). Nothing in this workbook changes the system by itself.", None),
    ("", None),
    ("Source order when a fee appears in more than one place", "h"),
    (f"The system counts one source and sets the others aside, in this order: {prec}. Please confirm this order is right.", None),
    ("", None),
    ("Summary", "h"),
]
for i, (t, kind) in enumerate(text, 1):
    c = ws.cell(row=i, column=1, value=t); c.alignment = WRAP
    if kind == "title": c.font = Font(bold=True, size=14, color=TEAL)
    elif kind == "h": c.font = Font(bold=True, color=TEAL)
ws.column_dimensions["A"].width = 120
r0 = len(text) + 1
summ = defaultdict(lambda: [0, 0.0, 0, 0.0])
for f in fees:
    s = summ[(SRC.get(f["source_system"], f["source_system"]), f["fee_type"])]
    s[0] += 1; s[1] += f["amount"]
    if f["needs_decision"] == "Yes":
        s[2] += 1; s[3] += f["amount"]
hdr = ["Source", "Fee type", "Lines", "Amount (MWK)", "Lines needing a decision", "Amount needing a decision (MWK)"]
for c, h in enumerate(hdr, 1):
    x = ws.cell(row=r0, column=c, value=h); x.font = HDR; x.fill = HFILL; x.alignment = WRAP
for i, (k, v) in enumerate(sorted(summ.items()), r0 + 1):
    for c, val in enumerate([k[0], k[1], v[0], v[1], v[2], v[3]], 1):
        x = ws.cell(row=i, column=c, value=val)
        if c in (4, 6): x.number_format = NUM
tot = r0 + len(summ) + 1
for c, val in enumerate(["Total", "", len(fees), sum(f["amount"] for f in fees), len(decide), sum(f["amount"] for f in decide)], 1):
    x = ws.cell(row=tot, column=c, value=val); x.font = Font(bold=True)
    if c in (4, 6): x.number_format = NUM
for col, w in zip("BCDEF", (14, 10, 18, 16, 22)):
    ws.column_dimensions[col].width = w

# ---------------------------------------------------------------- Fee lines
ws = wb.create_sheet("Fee lines")
H = ["Line ID", "Loan account", "Customer", "Fee type", "Description", "Amount (MWK)", "Date", "Source", "Source reference",
     "GL account", "Needs a decision", "Flags to check", "Rule that would apply", "Suggested treatment",
     "Integral to the EIR? (Yes/No)", "Cash direction (Received/Paid)", "Agreed amount if different (MWK)",
     "Reason / comment", "Classified by", "Reviewed by (a different person)"]
INPUTS = H[14:]
data = []
for f in fees:
    r = f["rule"]
    sugg = ("" if r is None else ("Integral: spread through the EIR" if r["proposed_integral"] == "1" else "Not integral: profit or loss"))
    data.append([int(f["id"]), f["contract_id"], f["customer"], f["fee_type"], f["description"], f["amount"],
                 f["transaction_date"], SRC.get(f["source_system"], f["source_system"]), f["source_reference"],
                 f["gl_account_ref"], f["needs_decision"], f["flags"], "" if r is None else f"Rule {r['id']}: {r['name']}", sugg,
                 None, (f["cashflow_direction"] or "").title() or None, None, None, None, None])
table(ws, H, data, [8, 15, 26, 11, 40, 15, 11, 18, 22, 8, 11, 42, 32, 20, 14, 14, 16, 30, 14, 16],
      inputs=INPUTS, numcols=("Amount (MWK)", "Agreed amount if different (MWK)"))
n = len(data) + 1
dv(ws, "O", ["Yes", "No"], 2, n, "Is this fee integral to the EIR?")
dv(ws, "P", ["Received", "Paid"], 2, n, "Received from the borrower, or paid by MAIIC?")
for i, f in enumerate(fees, 2):
    if f["flags"] and not f["superseded_by_source"]:
        ws.cell(row=i, column=12).fill = FLAGFILL
    if f["superseded_by_source"]:
        for c in range(1, 15):
            ws.cell(row=i, column=c).fill = GREY

# ---------------------------------------------------------------- Rules
ws = wb.create_sheet("Rules to approve")
cnt = defaultdict(lambda: [0, 0.0])
srcs = defaultdict(lambda: defaultdict(int))
false_hits = defaultdict(int)
for f in decide:
    if f["rule"]:
        cnt[f["rule"]["id"]][0] += 1; cnt[f["rule"]["id"]][1] += f["amount"]
        srcs[f["rule"]["id"]][SRC.get(f["source_system"], f["source_system"])] += 1
        if "(part of the customer's name)" in f["flags"]:
            false_hits[f["rule"]["id"]] += 1


def note(r):
    out = []
    if srcs[r["id"]]:
        out.append("Lines come from: " + ", ".join(f"{k} ({v})" for k, v in sorted(srcs[r["id"]].items())))
    if false_hits[r["id"]]:
        out.append(f"CHECK: {false_hits[r['id']]} line(s) match only because a customer's name contains "
                   f"'{r['description_contains']}'; they are origination fees. Narrow the rule (for example exclude GL 4871 and 4873) before approving")
    return ". ".join(out)


RH = ["Rule", "Name", "Matches fee type", "Description contains", "GL account", "Proposed treatment", "Why",
      "Lines it would match", "Amount it would match (MWK)", "Note from Dupleix", "Decision (Approve/Change/Reject)", "Comment"]
rdata = [[int(r["id"]), r["name"], r["fee_type"], r["description_contains"], r["gl_account_ref"],
          "Integral: spread through the EIR" if r["proposed_integral"] == "1" else "Not integral: profit or loss",
          r["rationale"], cnt[r["id"]][0], cnt[r["id"]][1], note(r), None, None] for r in rules]
table(ws, RH, rdata, [6, 40, 12, 18, 9, 22, 60, 10, 16, 40, 16, 30], inputs=RH[10:], numcols=("Amount it would match (MWK)",))
dv(ws, "K", ["Approve", "Change", "Reject"], 2, len(rdata) + 1, "Approve, change or reject this rule")
for i, r in enumerate(rules, 2):
    if false_hits[r["id"]]:
        ws.cell(row=i, column=10).fill = FLAGFILL

# ---------------------------------------------------------------- GL vs loan system
ws = wb.create_sheet("GL vs loan system")
cust = {f["contract_id"]: f["customer"] for f in fees}
GH = ["Loan account", "Customer", "Fee type", "General ledger (MWK)", "Loan system (MWK)", "Difference (MWK)",
      "Agree?", "Counted by the system", "Correct amount (MWK)", "Comment"]
gdata = []
for (cid, ft), v in sorted(both.items(), key=lambda kv: (-(abs(kv[1]["gl"] - kv[1]["los"])), kv[0])):
    gdata.append([cid, cust.get(cid, ""), ft, v["gl"], v["los"], v["gl"] - v["los"],
                  "No" if (cid, ft) in differ else "Yes", "General ledger", None, None])
table(ws, GH, gdata, [15, 28, 11, 16, 16, 16, 7, 16, 16, 34], inputs=GH[8:],
      numcols=("General ledger (MWK)", "Loan system (MWK)", "Difference (MWK)", "Correct amount (MWK)"))
for i, row in enumerate(gdata, 2):
    if row[6] == "No":
        ws.cell(row=i, column=7).fill = FLAGFILL

# ---------------------------------------------------------------- By loan
ws = wb.create_sheet("By loan")
bl = defaultdict(lambda: {"n": 0, "d": 0, "amt": 0.0, "flags": 0, "eir": "", "cust": ""})
for f in fees:
    b = bl[f["contract_id"]]
    b["n"] += 1; b["cust"] = f["customer"]; b["eir"] = f["eir_status"]
    if f["needs_decision"] == "Yes":
        b["d"] += 1; b["amt"] += f["amount"]
        if f["flags"]: b["flags"] += 1
EIRW = {"BLOCKED": "Held: fee review", "LOCKED": "Locked", "PENDING": "Waiting (not for fees)", "": "No EIR record"}
BH = ["Loan account", "Customer", "Fee lines", "Lines needing a decision", "Amount needing a decision (MWK)", "Lines with a flag", "EIR status"]
bdata = [[k, v["cust"], v["n"], v["d"], v["amt"], v["flags"], EIRW.get(v["eir"], v["eir"])] for k, v in sorted(bl.items())]
table(ws, BH, bdata, [15, 32, 9, 12, 18, 10, 22], numcols=("Amount needing a decision (MWK)",))

wb.save(OUT)
print(OUT, len(fees), "lines;", len(decide), "need a decision;", len(both), "pairs,", len(differ), "differ;",
      sum(1 for f in decide if f["rule"] is None), "with no rule; blocked loans", blocked)
