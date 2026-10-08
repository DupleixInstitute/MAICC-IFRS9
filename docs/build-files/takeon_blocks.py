"""Parse the amortisation blocks of the take-on workbook and test the mapping against them; build the upload summary."""
import re
import pandas as pd, numpy as np
from fu_load import load
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 40); pd.set_option("display.max_colwidth", 36)
WB = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\New Doc Received 11 Sep\Amortisation schedules as at 31 October  2024.xlsx"
am = pd.read_excel(WB, sheet_name="Amortisation and Repayments", header=None)
# a block starts at a row whose column 2 is 'Principal (MK)'; its title is the nearest non-empty text above in column 2
blocks = []
starts = am.index[am[2].astype(str).str.strip().eq("Principal (MK)")].tolist()
for i, s in enumerate(starts):
    end = starts[i + 1] - 1 if i + 1 < len(starts) else len(am) - 1
    title = None
    for k in range(s - 1, max(-1, s - 6), -1):
        v = am.iat[k, 2]
        if isinstance(v, str) and v.strip() and "Principal" not in v: title = v.strip(); break
    principal = pd.to_numeric(am.iat[s, 3], errors="coerce")
    rate = pd.to_numeric(am.iat[s + 1, 3], errors="coerce") if str(am.iat[s + 1, 2]).strip() == "Rate" else np.nan
    # restructure marker and rate-step headers in the rows above
    head = am.iloc[max(0, s - 3):s + 12, 3:14].astype(str)
    restructured = head.apply(lambda col: col.str.contains("Restructur", case=False)).values.any()
    steps = int(head.apply(lambda col: col.str.contains("Rate Adj", case=False)).values.sum())
    # period rows: column 1 numeric or column 2 a date, between the 'Period Start' header and the block end
    hdr = am.index[(am.index > s) & (am.index <= end) & am[2].astype(str).str.strip().eq("Period Start")]
    nrows = first = last = None; repaid = False; closing = np.nan
    if len(hdr):
        body = am.loc[hdr[0] + 1:end]
        dates = pd.to_datetime(body[2], errors="coerce")
        body = body[dates.notna()]
        if len(body):
            nrows = len(body); first = dates[body.index[0]].date(); last = pd.to_datetime(body[3], errors="coerce").iloc[-1]
            last = last.date() if pd.notna(last) else None
            closing = pd.to_numeric(body[9], errors="coerce").iloc[-1]
            repaid = body.astype(str).apply(lambda col: col.str.contains("FULLY REPAID", case=False)).values.any()
    blocks.append(dict(block=i + 1, title=title, principal=principal, rate=rate, restructured=bool(restructured), rate_steps=steps, periods=nrows, first_period=first, last_period=last, closing_balance=closing, fully_repaid=bool(repaid)))
b = pd.DataFrame(blocks)
print("blocks:", len(b), "| with a title:", int(b.title.notna().sum()), "| restructured:", int(b.restructured.sum()), "| with rate steps:", int((b.rate_steps > 0).sum()), "| fully repaid:", int(b.fully_repaid.sum()))
print("periods per block:", b.periods.describe()[["min", "50%", "max"]].to_dict(), "| first period range:", b.first_period.min(), b.first_period.max(), "| last:", b.last_period.min(), b.last_period.max())

# link blocks to loan book rows by principal (exact), then to accounts via the mapping
m = pd.read_csv("takeon_mapping.csv", dtype={"Proposed E-Banker account": str})
m["Proposed E-Banker account"] = m["Proposed E-Banker account"].fillna("")
import difflib
STOP = {"ltd", "limited", "company", "co", "the", "and", "of", "enterprise", "enterprises", "investments", "investment", "plc", "mcu", "fines", "maiic", "armortization", "amortization", "amortisation", "schedule"}
def norm(v): return " ".join(w for w in re.sub(r"[^a-z0-9 ]", " ", str(v).lower()).split() if w not in STOP)
m["nname"] = m["Workbook name"].map(norm)
link = []
for _, r in b.iterrows():
    nt = norm(r.title)
    sc = m.apply(lambda q: max(difflib.SequenceMatcher(None, nt, q.nname).ratio(), len(set(nt.split()) & set(q.nname.split())) / max(1, len(set(nt.split())))), axis=1)
    amt = m["Principal at 31 Oct 2024"].round(2).eq(round(r.principal, 2)) | m["Disbursed"].round(2).eq(round(r.principal, 2)) | m["Approved"].round(2).eq(round(r.principal, 2))
    score = sc + 0.5 * amt.astype(float)
    top = score.sort_values(ascending=False)
    hit = m.loc[top.index[:1]] if top.iloc[0] >= 0.6 else m.iloc[0:0]
    link.append((r.block, len(hit), hit["Workbook #"].tolist(), hit["Proposed E-Banker account"].tolist(), round(float(top.iloc[0]), 2)))
lk = pd.DataFrame(link, columns=["block", "candidates", "wb_rows", "accounts", "link_score"])
print("blocks linked to a loan book row by title (and amount):", int((lk.candidates == 1).sum()), "| not linked:", int((lk.candidates == 0).sum()), "| distinct rows linked:", len(set(sum(lk.wb_rows.tolist(), []))))
b = b.merge(lk, on="block")
b.to_csv("takeon_blocks.csv", index=False)

# ---- upload summary sheet
acm, alm, bal = (load(k) for k in ("acm", "alm", "bal"))
a = acm.set_index("NEW_AC_NUMBER"); al = alm.set_index("NEW_AC_NUMBER")
lb = pd.read_excel(WB, sheet_name="Loan Book", header=None)
hdr = lb.iloc[2].tolist(); cols, seen = [], {}
for i, h in enumerate(hdr):
    n = str(h).strip() if isinstance(h, str) else f"c{i}"
    if n in seen: seen[n] += 1; n = f"{n}_{seen[n]}"
    else: seen[n] = 0
    cols.append(n)
rows = lb.iloc[4:].copy(); rows.columns = cols; rows = rows[rows["Name"].notna() & rows["#"].notna()].copy()
rows["#"] = pd.to_numeric(rows["#"], errors="coerce").astype(int)
mm = m.set_index("Workbook #")
def rate_pct(v):
    v = pd.to_numeric(v, errors="coerce")
    return round(v * 100, 2) if pd.notna(v) and v < 1 else v
up = []
for _, r in rows.iterrows():
    k = int(r["#"]); mp = mm.loc[k] if k in mm.index else None
    if isinstance(mp, pd.DataFrame): mp = mp[mp["Type"] == r["Type"]].iloc[0] if len(mp[mp["Type"] == r["Type"]]) else mp.iloc[0]
    acct = mp["Proposed E-Banker account"] if mp is not None else ""
    blk = b[b.wb_rows.apply(lambda l: k in l)]
    up.append({
        "E-Banker account": acct, "Confidence": mp["Confidence"] if mp is not None else "", "Workbook #": k, "Workbook name": r["Name"], "Type": r["Type"],
        "E-Banker name": a.ACCOUNT_NAME.get(acct, ""), "Scheme id": a.SCHEME_MST_ID.get(acct, ""), "GL code": a.GLCODE.get(acct, ""), "E-Banker status": a.STATUS_CODE.get(acct, ""),
        "Interest policy": a.INTEREST_POLICY.get(acct, ""), "E-Banker rate now %": a.INTEREST_RATE.get(acct, ""),
        "Value date": pd.to_datetime(r["Value Date"], errors="coerce").date() if pd.notna(pd.to_datetime(r["Value Date"], errors="coerce")) else None,
        "Maturity date": pd.to_datetime(r["Maturity Date"], errors="coerce").date() if pd.notna(pd.to_datetime(r["Maturity Date"], errors="coerce")) else None,
        "Tenor (years)": pd.to_numeric(r["Tenor"], errors="coerce"), "Moratorium (workbook text)": r["Moratorium"], "Rate at 31 Oct 2024 %": rate_pct(r["Interest Rate"]),
        "Approved": pd.to_numeric(r["Approved"], errors="coerce"), "Disbursed": pd.to_numeric(r["Disbursed"], errors="coerce"), "Not yet disbursed": pd.to_numeric(r["Not yet disbursed"], errors="coerce"),
        "Principal 31 Oct 2024": pd.to_numeric(r["Principal"], errors="coerce"), "Interest to date": pd.to_numeric(r["Interest to date"], errors="coerce"), "Repayments": pd.to_numeric(r["Repayments"], errors="coerce"),
        "Carrying amount 31 Oct 2024": pd.to_numeric(r["Carrying Ammount"], errors="coerce"), "Arrears principal": pd.to_numeric(r["Principal_1"], errors="coerce"), "Arrears interest": pd.to_numeric(r["Interest"], errors="coerce"), "Arrears total": pd.to_numeric(r["Total"], errors="coerce"),
        "Segment": r["SEGMENT"], "Industry": r["INDUSTRY - DESCRIPTION"],
        "E-Banker sanction": al.SANCTION_AMOUNT.get(acct, None), "E-Banker sanction date": al.SANCTION_DATE.get(acct, None), "E-Banker expiry": al.EXPIRY_DATE.get(acct, None),
        "E-Banker P moratorium": al.PMOROTORIUM_PERIOD.get(acct, None), "E-Banker I moratorium": al.IMOROTORIUM_PERIOD.get(acct, None),
        "E-Banker take-on posting 31 Jul 2024": mp["E-Banker take-on posting 31 Jul 2024"] if mp is not None else None, "E-Banker balance 31 Oct 2024": mp["E-Banker balance 31 Oct 2024"] if mp is not None else None,
        "Amortisation block #": blk.block.iloc[0] if len(blk) else None, "Block rate steps": blk.rate_steps.iloc[0] if len(blk) else None, "Block periods": blk.periods.iloc[0] if len(blk) else None, "Block restructured": blk.restructured.iloc[0] if len(blk) else None,
    })
u = pd.DataFrame(up)
for c in ("E-Banker sanction date", "E-Banker expiry"):
    u[c] = pd.to_datetime(u[c], errors="coerce").dt.date
u["Carrying vs E-Banker balance"] = u["Carrying amount 31 Oct 2024"] - u["E-Banker balance 31 Oct 2024"]
u.to_csv("takeon_upload_summary.csv", index=False)
OUTX = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on mapping for Tamanda to confirm - 7 Oct 2026.xlsx"
bl = b.copy(); bl["wb_rows"] = bl.wb_rows.map(lambda l: ", ".join(str(v) for v in l)); bl["accounts"] = bl.accounts.map(lambda l: ", ".join(str(v) for v in l))
bl = bl.rename(columns={"block": "Block #", "title": "Block title", "principal": "Principal in block", "rate": "Opening rate", "restructured": "Restructured", "rate_steps": "Rate steps", "periods": "Periods", "first_period": "First period", "last_period": "Last period", "closing_balance": "Closing balance", "fully_repaid": "Fully repaid", "candidates": "Linked", "wb_rows": "Loan Book #", "accounts": "E-Banker account", "link_score": "Link score"})
with pd.ExcelWriter(OUTX, engine="openpyxl", mode="a", if_sheet_exists="replace") as w:
    u.to_excel(w, sheet_name="Upload summary", index=False)
    bl.to_excel(w, sheet_name="Amortisation blocks", index=False)
    wb = w.book
    order = ["How to read", "Upload summary", "Proposed mapping", "Not matched", "E-Banker accounts no block", "Amortisation blocks"]
    wb._sheets = [wb[n] for n in order if n in wb.sheetnames]
    for ws in wb.worksheets:
        for col in ws.columns:
            ws.column_dimensions[col[0].column_letter].width = min(44, max(10, max(len(str(c.value)) if c.value is not None else 0 for c in col) + 2))
        ws.freeze_panes = "A2"
print("workbook updated with Upload summary and Amortisation blocks")
d = u[u["E-Banker account"].astype(str).str.len() == 15]
print("\nupload rows with an account:", len(d), "of", len(u))
print("carrying amount vs E-Banker balance 31 Oct 2024: both present", int(d["Carrying vs E-Banker balance"].notna().sum()), "| within 1:", int((d["Carrying vs E-Banker balance"].abs() < 1).sum()), "| within 1%:", int(((d["Carrying amount 31 Oct 2024"] / d["E-Banker balance 31 Oct 2024"] - 1).abs() < 0.01).sum()), "| within 5%:", int(((d["Carrying amount 31 Oct 2024"] / d["E-Banker balance 31 Oct 2024"] - 1).abs() < 0.05).sum()))
print("principal = take-on posting exact:", int(((d["Principal 31 Oct 2024"] - d["E-Banker take-on posting 31 Jul 2024"]).abs() < 1).sum()), "| no take-on posting (closed before):", int(d["E-Banker take-on posting 31 Jul 2024"].isna().sum()))
print("rows with a block:", int(d["Amortisation block #"].notna().sum()), "| rate in workbook present:", int(d["Rate at 31 Oct 2024 %"].notna().sum()), "| moratorium text present:", int(d["Moratorium (workbook text)"].notna().sum()))
print("workbook rate vs E-Banker rate now, equal:", int(((d["Rate at 31 Oct 2024 %"].astype(float) - d["E-Banker rate now %"].astype(float)).abs() < 0.05).sum()))
big = d[(d["Carrying vs E-Banker balance"].abs() >= 1) & d["E-Banker balance 31 Oct 2024"].notna()].copy()
big["pct"] = (big["Carrying amount 31 Oct 2024"] / big["E-Banker balance 31 Oct 2024"] - 1) * 100
print(big.sort_values("pct", key=abs, ascending=False)[["E-Banker account", "Workbook name", "Carrying amount 31 Oct 2024", "E-Banker balance 31 Oct 2024", "pct"]].head(10).round(2).to_string(index=False))
