import pandas as pd, warnings, re
warnings.filterwarnings("ignore")
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
y = A[A["AS_OF_DATE"].astype(str).str[:10] == "31-12-2025"].set_index("LOAN_ACCOUNT_NUMBER")
dis = B[(B.SCHEDULED_ACTUAL_FLAG == "Actual") & (B.TRANSACTION_TYPE == "Disbursement")]
e = dis[dis.LOAN_ACCOUNT_NUMBER == "000104430000084"]
print("Ebenezer ledger disbursements:", [(str(d)[:19], f"{a:,.2f}") for d, a in zip(e.TRANSACTION_DATE, e.TOTAL_AMOUNT)])
print("Ebenezer A:", y.DISBURSEMENT_TRANCHES["000104430000084"], "| start", y.LOAN_START_DATE["000104430000084"])
g = dis.groupby("LOAN_ACCOUNT_NUMBER").agg(n=("TOTAL_AMOUNT", "size"), amt=("TOTAL_AMOUNT", "sum"), dates=("TRANSACTION_DATE", lambda s: sorted(set(str(x)[:10] for x in s))))
rows = []
for k, r in g.iterrows():
    if k not in y.index: continue
    t = y.DISBURSEMENT_TRANCHES[k]
    if not isinstance(t, str): rows.append((k, r.n, None, r.amt, None, None, r.dates)); continue
    parts = [p.strip() for p in t.split(";")]
    adates = sorted(set(p.split(":")[0] for p in parts)); aamt = sum(float(p.split(":")[1]) for p in parts)
    rows.append((k, r.n, len(parts), r.amt, aamt, adates, r.dates))
df = pd.DataFrame(rows, columns=["acct", "ledger_n", "A_n", "ledger_amt", "A_amt", "A_dates", "ledger_dates"])
print("accounts with 2025 ledger disbursements:", len(df), "| A tranche list blank:", int(df.A_n.isna().sum()))
d2 = df.dropna(subset=["A_n"])
print("same count:", int((d2.ledger_n == d2.A_n).sum()), "| ledger more:", int((d2.ledger_n > d2.A_n).sum()), "| A more:", int((d2.ledger_n < d2.A_n).sum()), "| same total amount:", int(((d2.ledger_amt - d2.A_amt).abs() < 1).sum()))
print(d2.head(8).to_string())
both = A.DISBURSEMENT_TRANCHES.dropna()
print("all 362 rows: populated", len(both), "| entries per row:", both.str.count(";").add(1).value_counts().to_dict())
