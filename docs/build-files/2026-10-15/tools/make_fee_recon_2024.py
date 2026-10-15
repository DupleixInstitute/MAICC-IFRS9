"""Reconcile the 2023-2024 loans in Finance's EIR assessment workbook to the 2024 fee postings on GL 4871/4873
(old ledger CUMVOUCH_BK240824 for Jan-Aug 2024, E-Banker from Oct 2024). Read-only."""
import subprocess, sys, re, json
from datetime import datetime, date
from collections import defaultdict
from itertools import combinations
import openpyxl
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

FIN, OUT = sys.argv[1], sys.argv[2]
M = r"C:\xampp\mysql\bin\mysql.exe"


def rows(sql):
    r = subprocess.run([M, "-uroot", "-B", "--default-character-set=utf8mb4", "maiic_ifrs9"], input=sql,
                       capture_output=True, text=True, encoding="utf-8")
    if r.returncode:
        raise SystemExit(r.stderr)
    lines = r.stdout.rstrip("\n").split("\n")
    head = lines[0].split("\t")
    return [dict(zip(head, [None if v == "NULL" else v for v in l.split("\t")])) for l in lines[1:]]


STOP = {"limited", "ltd", "trf", "via", "ref", "mmm", "acc", "new", "fsf", "company", "group", "the", "investment", "investments", "holdings", "services", "and", "general",
        "farm", "farms", "trf", "fees", "fee", "legal", "arrangement", "arrangment", "loan", "drawdown", "for", "from", "mega",
        "international", "enterprises", "enterprise", "trading", "sacco", "africa", "malawi", "innovation", "foods", "food", "processors"}


def toks(s):
    return {w for w in re.findall(r"[a-z]{3,}", (s or "").lower()) if w not in STOP}


# ---------------------------------------------------------------- Finance's workbook (all rows; the 2023-2024 loans are the scope)
ws = openpyxl.load_workbook(FIN, data_only=True)["EIR Assessment December 2025"]
fin = []
for i, r in enumerate(ws.iter_rows(min_row=5, values_only=True), 5):
    name, vd = r[4], r[6]
    if not name or not vd or not isinstance(r[3], (int, float)):
        continue
    if isinstance(vd, str):
        vd = datetime.strptime(vd.strip(), "%d/%m/%Y")
    fin.append({"row": i, "no": int(r[3]), "name": str(name).strip(), "vd": vd.date(), "legal": float(r[17] or 0), "legal_cost": float(r[18] or 0),
                "arrangement": float(r[19] or 0), "appraisal": float(r[16] or 0)})
scope = [f for f in fin if f["vd"].year <= 2024]

# ---------------------------------------------------------------- ledger postings dated 2024 on GL 4871 and 4873
old = rows("""
SELECT CONCAT('O', id) pid, row_date d, JSON_UNQUOTE(JSON_EXTRACT(payload,'$.CB_GLCODE')) gl,
       JSON_UNQUOTE(JSON_EXTRACT(payload,'$.TRANSAMT')) amount,
       CONCAT_WS(' / ', NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.VARCHAR_2')),''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.PARTICULARS')),'')) narration,
       CONCAT(JSON_UNQUOTE(JSON_EXTRACT(payload,'$.BATCH_TYPE')),' ',JSON_UNQUOTE(JSON_EXTRACT(payload,'$.BATCH_NUMBER'))) ref
FROM ebanker_raw_rows WHERE query_id = 'CV_04a' AND superseded_at IS NULL AND YEAR(row_date) = 2024
  AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.CB_GLCODE')) IN ('4871','4873')""")
for p in old:
    p["state"] = "Old ledger (before E-Banker)"; p["loan"] = None
new = rows("""
SELECT CONCAT('T', f.id) pid, f.transaction_date d, f.gl_account_ref gl, f.amount, CONCAT_WS(' / ', f.description,
       (SELECT l.customer_name FROM loan_books l WHERE l.contract_id = f.contract_id AND l.customer_name <> '' ORDER BY l.reporting_period DESC LIMIT 1)) narration,
       f.source_reference ref, f.contract_id loan, 'E-Banker: tied to a loan by the system' state
FROM contract_fees f WHERE f.source_system = 'E-Banker GL fee posting' AND f.gl_account_ref IN ('4871','4873') AND YEAR(f.transaction_date) = 2024
UNION ALL
SELECT CONCAT('H', id), transaction_date, gl_code, amount, narration, CONCAT(batch, ' / ', external_transaction_id), candidate_contract_id,
       'E-Banker: held, no loan in the batch'
FROM fee_gl_unassigned WHERE gl_code IN ('4871','4873') AND YEAR(transaction_date) = 2024""")
led = old + new
for p in led:
    p["amount"] = float(p["amount"]); p["d"] = date.fromisoformat(p["d"][:10])
    p["type"] = "legal" if p["gl"] == "4871" else "arrangement"; p["used"] = False
    # the programme's own monthly accrual is posted to "Mega Farms" itself; a borrower's loan under the programme names the borrower first
    p["mega"] = bool(re.match(r"\s*(by trf\s+)?mega farms?\b", (p["narration"] or "").lower()))
progr = [p for p in led if p["mega"]]
loanled = [p for p in led if not p["mega"]]
tot = {t: sum(p["amount"] for p in led if p["type"] == t) for t in ("legal", "arrangement")}
tot_prog = {t: sum(p["amount"] for p in progr if p["type"] == t) for t in ("legal", "arrangement")}


def name_hit(p, f):
    return bool(toks(p["narration"]) & toks(f["name"]))


def days(p, f):
    return (p["d"] - f["vd"]).days


items = [{"f": f, "t": t, "want": round(f[t], 2), "posts": [], "how": []} for f in fin for t in ("legal", "arrangement") if round(f[t], 2)]
inscope = lambda it: it["f"]["vd"].year <= 2024


def take(it, ps, why):
    for p in ps:
        p["used"] = True; it["posts"].append(p); it["how"].append(f"{p['pid']}: {why}")


def free(t):
    return [p for p in loanled if not p["used"] and p["type"] == t]


# A. exact amount and the customer's name; B. exact amount, within 120 days of the value date (scope loans only)
for test, why, only_scope in ((lambda p, it: name_hit(p, it["f"]), "same amount, customer's name in the narration", False),
                              (lambda p, it: -30 <= days(p, it["f"]) <= 120, "same amount, near the value date (no name)", True)):
    for it in items:
        if it["posts"] or (only_scope and not inscope(it)):
            continue
        c = [p for p in free(it["t"]) if abs(p["amount"] - it["want"]) < 0.5 and test(p, it)]
        c.sort(key=lambda p: abs(days(p, it["f"])))
        if c:
            take(it, [c[0]], why)
# C. two or three postings naming the customer that add up (a fee in parts, or with a correction)
for it in items:
    if it["posts"]:
        continue
    pool = [p for p in free(it["t"]) if name_hit(p, it["f"])]
    for k in (2, 3):
        hit = next((c for c in combinations(pool, k) if abs(sum(p["amount"] for p in c) - it["want"]) < 0.5), None)
        if hit:
            take(it, list(hit), "several postings naming the customer, together the amount"); break
# D. the nearest posting naming the customer (amount differs), scope loans only
for it in items:
    if it["posts"] or not inscope(it):
        continue
    c = [p for p in free(it["t"]) if name_hit(p, it["f"])]
    c.sort(key=lambda p: abs(p["amount"] - it["want"]))
    if c:
        take(it, [c[0]], "nearest posting naming the customer (amount differs)")

recon = []
for it in items:
    if not inscope(it) and not it["posts"]:
        continue
    f = it["f"]; ledger = round(sum(p["amount"] for p in it["posts"]), 2); diff = round(it["want"] - ledger, 2)
    if not it["posts"]:
        status = "In Finance's file, no 2024 posting found"
    elif abs(diff) > 0.5:
        status = "Amounts differ"
    elif not inscope(it):
        status = "2025 loan: fee posted in 2024"
    else:
        status = "Agrees"
    src = sorted({p["state"].split(":")[0].split(" (")[0] for p in it["posts"]})
    recon.append({"no": f["no"], "name": f["name"], "vd": f["vd"], "type": it["t"], "fin": it["want"], "ledger": ledger, "diff": diff,
                  "status": status, "where": ", ".join(src), "postings": ", ".join(p["pid"] for p in it["posts"]),
                  "dates": ", ".join(p["d"].isoformat() for p in it["posts"]), "how": "; ".join(it["how"]), "cost": f.get("legal_cost", 0) if it["t"] == "legal" else None,
                  "loan": next((p["loan"] for p in it["posts"] if p["loan"]), None)})
left = [p for p in loanled if not p["used"]]
fin_tot = {t: sum(f[t] for f in scope) for t in ("legal", "arrangement")}

# ---------------------------------------------------------------- workbook
TEAL = "1F5C66"; HDR = Font(bold=True, color="FFFFFF"); HF = PatternFill("solid", fgColor=TEAL)
RED = PatternFill("solid", fgColor="FDE2E1"); AMB = PatternFill("solid", fgColor="FFF4CC"); GRN = PatternFill("solid", fgColor="E2F0D9")
WRAP = Alignment(wrap_text=True, vertical="top"); NUM = "#,##0.00"; LINE = Border(bottom=Side(style="thin", color="DDDDDD"))
wb = Workbook()


def table(ws, headers, data, widths, numcols=(), start=1):
    for c, h in enumerate(headers, 1):
        x = ws.cell(row=start, column=c, value=h); x.font = HDR; x.fill = HF; x.alignment = WRAP
    for i, row in enumerate(data, start + 1):
        for c, v in enumerate(row, 1):
            x = ws.cell(row=i, column=c, value=v); x.alignment = WRAP; x.border = LINE
            if headers[c - 1] in numcols: x.number_format = NUM
            if isinstance(v, date): x.number_format = "yyyy-mm-dd"
    for c, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(c)].width = w
    ws.row_dimensions[start].height = 32
    ws.freeze_panes = ws.cell(row=start + 1, column=3)
    ws.auto_filter.ref = f"A{start}:{get_column_letter(len(headers))}{start + len(data)}"


order = ["Agrees", "Amounts differ", "In Finance's file, no 2024 posting found", "2025 loan: fee posted in 2024"]
cnt = defaultdict(int)
for r in recon: cnt[r["status"]] += 1
ws = wb.active; ws.title = "Summary"
txt = [
    ("MAIIC: 2024 fees, Finance's workbook against the general ledger", "t"),
    ("Prepared by Dupleix Institute, 15 October 2026. Finance's file: 'MAIIC EIR Assessment as of 31st December 2025.xlsx' (the version with all the loans tested; it has no account numbers, so loans are matched by name and amount). Scope: the "
     f"{len(scope)} loans in it with a value date in 2023 or 2024. Ledger: every 2024 posting on GL 4871 (legal fees) and GL 4873 (arrangement fees): the old ledger (CUMVOUCH_BK240824, to 15 August 2024) and E-Banker (from 3 October 2024; E-Banker holds no fee posting between the migration and that date). Nothing in either has been changed.", None),
    ("", None), ("1. Totals", "h")]
for i, (t, k) in enumerate(txt, 1):
    x = ws.cell(row=i, column=1, value=t); x.alignment = WRAP
    if k == "t": x.font = Font(bold=True, size=14, color=TEAL)
    if k == "h": x.font = Font(bold=True, color=TEAL)
r0 = len(txt) + 1
hd = ["", "General ledger, all of 2024", "of which the Mega Farm programme's monthly arrangement fee accruals", "of which loans",
      f"Finance's file, the {len(scope)} loans of 2023-2024", "Loans: ledger less Finance's file"]
for c, h in enumerate(hd, 1):
    x = ws.cell(row=r0, column=c, value=h); x.font = HDR; x.fill = HF; x.alignment = WRAP
ws.row_dimensions[r0].height = 60
for j, (lab, t) in enumerate((("Legal fees, GL 4871", "legal"), ("Arrangement fees, GL 4873", "arrangement")), r0 + 1):
    for c, v in enumerate([lab, tot[t], tot_prog[t], tot[t] - tot_prog[t], fin_tot[t], tot[t] - tot_prog[t] - fin_tot[t]], 1):
        x = ws.cell(row=j, column=c, value=v)
        if c > 1: x.number_format = NUM
r1 = r0 + 4
ws.cell(row=r1, column=1, value="2. Loan by loan").font = Font(bold=True, color=TEAL)
for c, h in enumerate(["Result", "Loan and fee lines"], 1):
    x = ws.cell(row=r1 + 1, column=c, value=h); x.font = HDR; x.fill = HF
for j, s in enumerate(order, r1 + 2):
    ws.cell(row=j, column=1, value=s); ws.cell(row=j, column=2, value=cnt.get(s, 0))
r2 = r1 + 2 + len(order) + 1
ws.cell(row=r2, column=1, value=f"Loan fee postings of 2024 not matched to a loan in Finance's file: {len(left)} ({sum(p['amount'] for p in left):,.2f}); listed on 'Ledger postings'").font = Font(bold=True)
notes = [("3. Notes", "h"),
         ("Finance's file carries a loan's fees from the customer's statement; the old ledger names the customer on each posting (from 1 January to July 2024) and E-Banker names it in the narration or through the loan in the batch. A match on amount alone, with no name, is shown as such for Finance to confirm.", None),
         ("The Mega Farm programme's arrangement fees are accrued monthly to GL 4873 as one amount for the programme, not per loan, so they are outside Finance's per-loan file and shown separately.", None),
         ("Postings in 2024 that match no loan in Finance's file are mostly fees of loans the December 2025 file does not include (loans repaid or written off before then, or not in its test). They are listed for Finance to identify.", None)]
for j, (t, k) in enumerate(notes, r2 + 2):
    x = ws.cell(row=j, column=1, value=t); x.alignment = WRAP
    if k == "h": x.font = Font(bold=True, color=TEAL)
ws.column_dimensions["A"].width = 90
for col in "BCDEF": ws.column_dimensions[col].width = 20

ws = wb.create_sheet("By loan")
H = ["Finance #", "Customer", "Value date", "Fee", "Finance's file (MWK)", "Finance: legal cost, not a fee (MWK)", "Ledger 2024 (MWK)", "Difference (MWK)", "Result", "Ledger",
     "E-Banker loan", "Posting IDs", "Posting dates", "How matched", "Finance: confirm / correct", "Comment"]
data = [[r["no"], r["name"], r["vd"], r["type"].title(), r["fin"], r["cost"], r["ledger"], r["diff"], r["status"], r["where"], r["loan"],
         r["postings"], r["dates"], r["how"], None, None] for r in sorted(recon, key=lambda r: (order.index(r["status"]), r["no"], r["type"]))]
table(ws, H, data, [8, 30, 11, 11, 15, 15, 15, 14, 24, 20, 14, 14, 22, 36, 18, 28], numcols=("Finance's file (MWK)", "Finance: legal cost, not a fee (MWK)", "Ledger 2024 (MWK)", "Difference (MWK)"))
for i, row in enumerate(data, 2):
    ws.cell(row=i, column=9).fill = GRN if row[8] == "Agrees" else AMB if row[8].startswith("2025") else RED
    if "no name" in (row[13] or ""): ws.cell(row=i, column=14).fill = AMB
    for c in (15, 16): ws.cell(row=i, column=c).fill = AMB

ws = wb.create_sheet("Ledger postings")
match_of = defaultdict(list)
for r in recon:
    for pid in filter(None, r["postings"].split(", ")): match_of[pid].append(f"#{r['no']} {r['name']}")
H2 = ["Posting ID", "Ledger", "GL", "Fee", "Date", "Amount (MWK)", "Narration / customer", "Batch / reference", "E-Banker loan", "Matched to"]
data2 = [[p["pid"], p["state"], p["gl"], p["type"].title(), p["d"], p["amount"], p["narration"], p["ref"], p["loan"],
          "Mega Farm programme accrual" if p["mega"] else ", ".join(match_of.get(p["pid"], [])) or "NOT MATCHED"]
         for p in sorted(led, key=lambda p: (p["d"], p["gl"]))]
table(ws, H2, data2, [9, 26, 6, 11, 11, 16, 50, 16, 14, 30], numcols=("Amount (MWK)",))
for i, row in enumerate(data2, 2):
    if row[9] == "NOT MATCHED": ws.cell(row=i, column=10).fill = RED

wb.save(OUT)
print("ledger", tot, "programme", tot_prog, "finance scope", fin_tot, dict(cnt), "left", len(left), round(sum(p["amount"] for p in left), 2))
for r in recon:
    if r["status"] != "Agrees" or "no name" in r["how"]:
        print(r["no"], r["name"][:28], r["type"], r["fin"], r["ledger"], r["diff"], r["status"], "|", r["how"][:110])
for p in left:
    print("LEFT", p["pid"], p["gl"], p["d"], p["amount"], (p["narration"] or "")[:70])
