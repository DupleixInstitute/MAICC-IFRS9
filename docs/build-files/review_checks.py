import pandas as pd, numpy as np, warnings
warnings.filterwarnings("ignore")
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 30); pd.set_option("display.max_colwidth", 40)
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
A["snap"] = A["AS_OF_DATE"].astype(str).str[:10]
x = A[A.snap == "2025-01-01"].set_index(["LOAN_ACCOUNT_NUMBER", "SUB_ACCOUNT_NO"]); y = A[A.snap == "31-12-2025"].set_index(["LOAN_ACCOUNT_NUMBER", "SUB_ACCOUNT_NO"])
act = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"].copy()
act["seq"] = act.GL_POSTING_REF.str.split("-").str[-1].astype(int)
act["pos"] = range(len(act))
key = ["LOAN_ACCOUNT_NUMBER", "SUB_ACCOUNT_NO"]
# (b) direction of each transaction type, from the change in the running balance (file order within account)
act["prev"] = act.groupby(key).BALANCE_AFTER_TRANSACTION.shift(1)
act["delta"] = act.BALANCE_AFTER_TRANSACTION - act.prev
t = act.dropna(subset=["prev"])
up = (t.delta - t.TOTAL_AMOUNT).abs() < 0.02; dn = (t.delta + t.TOTAL_AMOUNT).abs() < 0.02
t = t.assign(dir=np.where(up, "balance up", np.where(dn, "balance down", "neither")))
print("(b) direction by type (rows after the first per account; file order):")
print(pd.crosstab(t.TRANSACTION_TYPE, t.dir).to_string())
print("    is file order = posting-id order within account?", bool((act.groupby(key).seq.diff().dropna() > 0).all()), "| rows out of id order:", int((act.groupby(key).seq.diff() < 0).sum()))
# first rows: is first balance equal to first amount (i.e. history starts inside the file)?
f = act.groupby(key).head(1)
print("    first row per account: balance equals amount on", int(((f.BALANCE_AFTER_TRANSACTION - f.TOTAL_AMOUNT).abs() < 0.02).sum()), "of", len(f), "accounts (others carry history from before 2025)")
# (a) Extract B closing running balance vs Extract A balance at 31 Dec 2025 (ACCOUNT_BALANCE table)
last = act.groupby(key).tail(1).set_index(key)
j = last[["BALANCE_AFTER_TRANSACTION", "TRANSACTION_DATE"]].join(y[["OPENING_BALANCE", "BALANCE_SNAPSHOT_DATE", "ACCOUNT_STATUS", "CUSTOMER_NAME"]], how="left")
j["diff"] = j.BALANCE_AFTER_TRANSACTION - j.OPENING_BALANCE
have = j.dropna(subset=["OPENING_BALANCE"])
print("\n(a) B closing running balance vs A balance at 31-Dec-2025: accounts with B actual rows", len(j), "| with an A balance", len(have), "| agree within 1:", int((have["diff"].abs() < 1).sum()), "| differ:", int((have["diff"].abs() >= 1).sum()), "| no A balance:", int(j.OPENING_BALANCE.isna().sum()))
print(have[have["diff"].abs() >= 1].sort_values("diff", key=abs, ascending=False)[["CUSTOMER_NAME", "BALANCE_AFTER_TRANSACTION", "OPENING_BALANCE", "diff", "BALANCE_SNAPSHOT_DATE", "TRANSACTION_DATE"]].head(12).round(2).to_string())
# (c) Happie Foods other/adjustment rows
h = act[(act.LOAN_ACCOUNT_NUMBER == "000104420000062")]
print("\n(c) Happie Foods, Other/Adjustment rows and neighbours:")
print(h[h.TRANSACTION_TYPE.isin(["Other/Adjustment"])][["GL_POSTING_REF", "TRANSACTION_DATE", "TRANSACTION_TYPE", "TOTAL_AMOUNT", "prev", "BALANCE_AFTER_TRANSACTION", "delta"]].round(2).to_string())
o = t[t.TRANSACTION_TYPE == "Other/Adjustment"]
print("    all Other/Adjustment rows:", len(act[act.TRANSACTION_TYPE == "Other/Adjustment"]), "| total", f"{act[act.TRANSACTION_TYPE == 'Other/Adjustment'].TOTAL_AMOUNT.sum():,.2f}", "| by direction:", o.dir.value_counts().to_dict(), "| amount by direction:", o.groupby("dir").TOTAL_AMOUNT.sum().round(2).to_dict())
# (d) repayment frequency between snapshots
xa, ya = x.REPAYMENT_FREQUENCY.fillna("(blank)"), y.REPAYMENT_FREQUENCY.reindex(x.index).fillna("(blank)")
print("\n(d) repayment frequency, 1-Jan-2025 snapshot (rows) vs 31-Dec-2025 snapshot (columns):")
print(pd.crosstab(xa, ya).to_string())
d = xa != ya
print("    differing accounts by loan start year text:", x[d].LOAN_START_DATE.astype(str).str.extract(r"(20\d\d)")[0].value_counts().to_dict(), "| NUM_INSTALMENTS zero among them:", int((x[d].NUM_INSTALMENTS == 0).sum()), "of", int(d.sum()))
# keys
print("\n(e) keys: A duplicates on snapshot+account+sub:", int(A.duplicated(["snap", "LOAN_ACCOUNT_NUMBER", "SUB_ACCOUNT_NO"]).sum()), "| sub-account values:", A.SUB_ACCOUNT_NO.unique().tolist())
sch = B[B.SCHEDULED_ACTUAL_FLAG == "Scheduled"]
print("(f) scheduled rows: principal+interest = total on", int(((sch.PRINCIPAL_COMPONENT.fillna(0) + sch.INTEREST_COMPONENT.fillna(0) - sch.TOTAL_AMOUNT).abs() < 0.02).sum()), "of", len(sch))
