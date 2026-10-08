s = open("takeon_blocks.py", encoding="utf-8").read()


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:60]
    s = s.replace(old, new)


rep('''link = []
for _, r in b.iterrows():
    hit = m[(m["Principal at 31 Oct 2024"].round(2) == round(r.principal, 2)) | (m["Disbursed"].round(2) == round(r.principal, 2)) | (m["Approved"].round(2) == round(r.principal, 2))] if pd.notna(r.principal) else m.iloc[0:0]
    link.append((r.block, len(hit), hit["Workbook #"].tolist()[:3], hit["Proposed E-Banker account"].tolist()[:3]))
lk = pd.DataFrame(link, columns=["block", "candidates", "wb_rows", "accounts"])
print("blocks linked to exactly one loan book row by amount:", int((lk.candidates == 1).sum()), "| to several:", int((lk.candidates > 1).sum()), "| to none:", int((lk.candidates == 0).sum()))''',
    '''import difflib
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
print("blocks linked to a loan book row by title (and amount):", int((lk.candidates == 1).sum()), "| not linked:", int((lk.candidates == 0).sum()), "| distinct rows linked:", len(set(sum(lk.wb_rows.tolist(), []))))''')

rep('''d = u[u["E-Banker account"].astype(str).str.len() == 15]''',
    r'''OUTX = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on mapping for Tamanda to confirm - 7 Oct 2026.xlsx"
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
d = u[u["E-Banker account"].astype(str).str.len() == 15]''')
open("takeon_blocks.py", "w", encoding="utf-8").write(s)
print("patched")
