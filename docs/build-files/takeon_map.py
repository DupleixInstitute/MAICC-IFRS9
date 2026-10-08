"""Propose a mapping from the take-on workbook (31 Oct 2024) to E-Banker account numbers, for Tamanda to confirm.

Keys, strongest first: the workbook's Principal equals E-Banker's take-on posting of 31 July 2024 (exact);
the workbook's carrying amount against E-Banker's balance at 31 October 2024; approved amount against sanction;
value date against account opening date; and the name.
"""
import re, difflib
import pandas as pd, numpy as np
from fu_load import load

WB = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\New Doc Received 11 Sep\Amortisation schedules as at 31 October  2024.xlsx"
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on mapping for Tamanda to confirm - 7 Oct 2026.xlsx"
x = pd.ExcelFile(WB)
lb = x.parse("Loan Book", header=None)
hdr = lb.iloc[2].tolist()
cols, seen = [], {}
for i, h in enumerate(hdr):
    n = str(h).strip() if isinstance(h, str) else f"c{i}"
    if n in seen: seen[n] += 1; n = f"{n}_{seen[n]}"
    else: seen[n] = 0
    cols.append(n)
rows = lb.iloc[4:].copy(); rows.columns = cols
rows = rows[rows["Name"].notna() & rows["#"].notna()].copy()
for c in ("Approved", "Disbursed", "Principal", "Interest to date", "Repayments", "Carrying Ammount"):
    rows[c] = pd.to_numeric(rows[c], errors="coerce")
rows["Value Date"] = pd.to_datetime(rows["Value Date"], errors="coerce")
rows["wb_id"] = range(1, len(rows) + 1)

acm, alm, bal, led, disb = (load(k) for k in ("acm", "alm", "bal", "ledger", "disb")); led["TRANTYPE"] = led.TRANTYPE.astype(int)
first_disb = disb[disb.DELETE_FLAG == "N"].groupby("NEW_AC_NUMBER").DISB_SCHEDULE_DATE.min()
pre = acm.copy()   # every in-scope account: three workbook loans were booked after July 2024
a = pre.set_index("NEW_AC_NUMBER"); al = alm.set_index("NEW_AC_NUMBER")
oct24 = bal[bal.TRANSACTION_DATE == "2024-10-31"].set_index("NEW_AC_NUMBER").PRINCIPAL_BALANCE.mul(-1)
takeon = led[(led.TRANTYPE == 301) & (led.TRANSACTION_DATE == "2024-07-31")].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
openint = led[(led.TRANTYPE == 303) & (led.TRANSACTION_DATE == "2024-07-31")].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
cand = pd.DataFrame({"acct": a.index, "name": a.ACCOUNT_NAME.values, "open": pd.to_datetime(a.ACCOUNT_OPEN_DATE.values), "sanction": al.SANCTION_AMOUNT.reindex(a.index).values,
                     "status": a.STATUS_CODE.values, "scheme": a.SCHEME_MST_ID.values})
cand["takeon"] = cand.acct.map(takeon); cand["openint"] = cand.acct.map(openint); cand["oct24"] = cand.acct.map(oct24)
cand["sancdate"] = pd.to_datetime(al.SANCTION_DATE.reindex(cand.acct).values); cand["disbdate"] = pd.to_datetime(cand.acct.map(first_disb).values)
STOP = {"ltd", "limited", "company", "co", "the", "and", "of", "enterprise", "enterprises", "investments", "investment", "plc", "mcu", "fines", "maiic"}
def norm(s): return " ".join(w for w in re.sub(r"[^a-z0-9 ]", " ", str(s).lower()).split() if w not in STOP)
cand["nname"] = cand.name.map(norm)

def close(x, y, tol=1.0): return pd.notna(x) and pd.notna(y) and abs(x - y) < tol
def within(x, y, pct): return pd.notna(x) and pd.notna(y) and y != 0 and abs(x / y - 1) < pct

pairs = []
for _, r in rows.iterrows():
    nn = norm(r["Name"])
    for _, c in cand.iterrows():
        s_name = max(difflib.SequenceMatcher(None, nn, c.nname).ratio(), len(set(nn.split()) & set(c.nname.split())) / max(1, len(set(nn.split()))))
        s_take = 1.0 if close(r.Principal, c.takeon) else (0.6 if within(r.Principal, c.takeon, 0.01) else 0.0)
        s_carry = 1.0 if close(r["Carrying Ammount"], c.oct24) else (0.6 if within(r["Carrying Ammount"], c.oct24, 0.02) else 0.0)
        s_amt = 1.0 if close(r.Approved, c.sanction) else (0.6 if close(r.Disbursed, c.sanction) else 0.0)
        dd = min([abs((r["Value Date"] - d).days) for d in (c.open, c.sancdate, c.disbdate) if pd.notna(d)] + [9999]) if pd.notna(r["Value Date"]) else 9999
        s_date = 1.0 if dd <= 7 else (0.5 if dd <= 120 else 0.0)
        score = 0.35 * s_name + 0.25 * s_take + 0.15 * s_carry + 0.15 * s_amt + 0.10 * s_date
        pairs.append((score, r.wb_id, c.acct, s_name, s_take, s_carry, s_amt, s_date))
pairs.sort(key=lambda t: -t[0])
assigned_row, assigned_acct, chosen = {}, {}, {}
for p in pairs:
    score, wid, acct = p[0], p[1], p[2]
    if wid in assigned_row or acct in assigned_acct or score < 0.35: continue
    assigned_row[wid] = acct; assigned_acct[acct] = wid; chosen[wid] = p

def confidence(p):
    if p is None: return "Not matched"
    _, _, _, s_name, s_take, s_carry, s_amt, s_date = p
    if s_take == 1.0 or (s_name >= 0.8 and (s_amt >= 0.6 or s_carry >= 0.6)) or (s_name >= 0.9 and s_date >= 0.5): return "High"
    if s_name >= 0.6 or (s_amt == 1.0 and s_date >= 0.5) or s_carry >= 0.6: return "Medium"
    return "Low"

def daydiff(vd, d):
    return int(abs((vd - d).days)) if (pd.notna(vd) and pd.notna(d)) else None
best_for = {}
for p in pairs:
    best_for.setdefault(p[1], []).append(p)
out = []
for _, r in rows.iterrows():
    p = chosen.get(r.wb_id); c = cand[cand.acct == p[2]].iloc[0] if p else None
    sugg = ""
    if not p:
        alts = [q for q in best_for.get(r.wb_id, [])[:3]]
        sugg = "; ".join(f"{q[2]} {cand[cand.acct == q[2]].iloc[0]['name']} (score {q[0]:.2f})" for q in alts)
    out.append({
        "Workbook #": int(r["#"]), "Workbook name": r["Name"], "Type": r["Type"], "Value date": r["Value Date"].date() if pd.notna(r["Value Date"]) else None,
        "Approved": r.Approved, "Disbursed": r.Disbursed, "Principal at 31 Oct 2024": r.Principal, "Carrying amount at 31 Oct 2024": r["Carrying Ammount"],
        "Proposed E-Banker account": p[2] if p else "", "E-Banker name": c["name"] if p else "", "E-Banker opening date": c.open.date() if (p and pd.notna(c.open)) else None,
        "E-Banker sanction": c.sanction if p else None, "E-Banker take-on posting 31 Jul 2024": c.takeon if p else None, "E-Banker balance 31 Oct 2024": c.oct24 if p else None, "E-Banker status": c.status if p else "",
        "Name agrees": "Yes" if (p and p[3] >= 0.8) else ("Partly" if (p and p[3] >= 0.5) else "No"),
        "Principal = take-on posting": "Yes" if (p and p[4] == 1.0) else ("Within 1%" if (p and p[4] > 0) else "No"),
        "Carrying amount = balance": "Yes" if (p and p[5] == 1.0) else ("Within 2%" if (p and p[5] > 0) else "No"),
        "Approved = sanction": "Yes" if (p and p[6] == 1.0) else ("Disbursed = sanction" if (p and p[6] > 0) else "No"),
        "Dates agree": "Yes" if (p and p[7] == 1.0) else ("Within 4 months" if (p and p[7] > 0) else "No"),
        "Days: value date vs E-Banker opening date": daydiff(r["Value Date"], c.open) if p else None,
        "Days: value date vs sanction date": daydiff(r["Value Date"], c.sancdate) if p else None,
        "Days: value date vs first disbursement schedule date": daydiff(r["Value Date"], c.disbdate) if p else None,
        "E-Banker first disbursement schedule date": c.disbdate.date() if (p and pd.notna(c.disbdate)) else None,
        "Nearest candidates (if not matched)": sugg,
        "Confidence": confidence(p), "Tamanda: correct? (Y/N)": "", "If N, correct account number": "", "Comment": "",
    })
m = pd.DataFrame(out)
unm = cand[~cand.acct.isin(assigned_acct) & (cand.open < "2024-07-01")].copy()
unm_out = pd.DataFrame({"E-Banker account": unm.acct, "E-Banker name": unm.name, "Opening date": unm.open.dt.date, "Sanction": unm.sanction, "Status": unm.status,
                        "Take-on posting 31 Jul 2024": unm.takeon, "Balance 31 Oct 2024": unm.oct24, "Tamanda: which workbook loan is this?": "", "Comment": ""})
print("confidence:", m.Confidence.value_counts().to_dict())
print("principal = take-on exactly:", int((m["Principal = take-on posting"] == "Yes").sum()), "| carrying = balance exactly:", int((m["Carrying amount = balance"] == "Yes").sum()), "| within 2%:", int((m["Carrying amount = balance"] == "Within 2%").sum()))
print("E-Banker pre-migration accounts with no block:", len(unm_out), "| status:", unm.status.value_counts().to_dict())
print(m[m.Confidence != "High"][["Workbook #", "Workbook name", "Type", "Proposed E-Banker account", "E-Banker name", "Name agrees", "Principal = take-on posting", "Carrying amount = balance", "Approved = sanction", "Confidence"]].to_string(index=False))

guide = pd.DataFrame({"How to read this workbook": [
    "Purpose: to link each loan in the take-on workbook 'Amortisation schedules as at 31 October 2024' to its E-Banker account number, so that the history before July 2024 can be joined to the system history after it.",
    "We matched each workbook loan to the E-Banker account master using five tests, strongest first: (1) the workbook's Principal at 31 October 2024 equals the opening balance E-Banker loaded on 31 July 2024; (2) the workbook's carrying amount equals E-Banker's balance at 31 October 2024; (3) the approved amount equals the sanction; (4) the value date equals the opening date; (5) the names agree.",
    "Sheet 'Proposed mapping': one row per workbook loan, the account we propose, and the five tests. Confidence 'High' means the take-on posting matches to the cent or the name and an amount agree. Please fill the column 'Tamanda: correct? (Y/N)'. Where it is N, give the right account number.",
    "Sheet 'Not matched': workbook loans we could not place with confidence. Please give the account number if the loan is in E-Banker, or note that it is not (for example an equity holding or a loan closed before the migration).",
    "Sheet 'E-Banker accounts without a block': accounts opened before July 2024 that no workbook loan was matched to. Most are closed. Please note which workbook loan each is, or that it has no block.",
    "Nothing in this file changes any system. It is a list for you to tick.",
]})
with pd.ExcelWriter(OUT, engine="openpyxl") as w:
    guide.to_excel(w, sheet_name="How to read", index=False)
    m[m.Confidence != "Not matched"].to_excel(w, sheet_name="Proposed mapping", index=False)
    m[m.Confidence.isin(["Not matched", "Low"])].to_excel(w, sheet_name="Not matched", index=False)
    unm_out.to_excel(w, sheet_name="E-Banker accounts no block", index=False)
    for ws in w.book.worksheets:
        for col in ws.columns:
            width = min(48, max(10, max(len(str(c.value)) if c.value is not None else 0 for c in col) + 2))
            ws.column_dimensions[col[0].column_letter].width = width
        ws.freeze_panes = "A2"
print("written", OUT)
m.to_csv("takeon_mapping.csv", index=False)
