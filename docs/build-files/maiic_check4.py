import pandas as pd, numpy as np, warnings
warnings.filterwarnings("ignore")
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 30); pd.set_option("display.max_colwidth", 45)
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
C = pd.read_excel(D + r"\ExtractC_Jan2025-July2026.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
A["snap"] = A["AS_OF_DATE"].astype(str).str[:10]
x = A[A.snap == "2025-01-01"].set_index("LOAN_ACCOUNT_NUMBER"); y = A[A.snap == "31-12-2025"].set_index("LOAN_ACCOUNT_NUMBER")
print("GENERATED_ON:", {s: A[A.snap == s].GENERATED_ON.astype(str).str[:16].unique().tolist() for s in A.snap.unique()})
for col in ["INTEREST_RATE", "ACCOUNT_STATUS", "SANCTIONED_AMOUNT", "TENOR", "NUM_INSTALMENTS", "RATE_BASIS", "PRINCIPAL_DISBURSED", "REPAYMENT_FREQUENCY", "DISBURSEMENT_TRANCHES"]:
    xa, ya = x[col], y[col].reindex(x.index)
    d = ~((xa == ya) | (xa.isna() & ya.isna()))
    print(f"{col}: differs on {int(d.sum())} | one side blank {int((xa.isna() ^ ya.isna()).sum())} | sample {list(zip(xa[d].head(3).tolist(), ya[d].head(3).tolist()))}")
print("open dates of accounts where disbursed differs:", x.loc[(x.PRINCIPAL_DISBURSED.isna() ^ y.PRINCIPAL_DISBURSED.reindex(x.index).isna())].LOAN_START_DATE.astype(str).str[:10].head(8).tolist())
print("accounts closed (status Closed) with closure date in 2025 per latest snapshot:", int(y[y.ACCOUNT_STATUS == "Closed"].ACTUAL_CLOSURE_DATE.astype(str).str.contains("2025").sum()), "| status in Jan-2025 snapshot for those:", x.loc[y[(y.ACCOUNT_STATUS == "Closed") & y.ACTUAL_CLOSURE_DATE.astype(str).str.contains("2025")].index].ACCOUNT_STATUS.value_counts().to_dict())
print("status counts latest:", y.ACCOUNT_STATUS.value_counts().to_dict(), "| NUM_INSTALMENTS zero:", int((y.NUM_INSTALMENTS == 0).sum()), "| repayment freq blank:", int(y.REPAYMENT_FREQUENCY.isna().sum()))
act = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"]
bx = act[act.LOAN_ACCOUNT_NUMBER == "000104430000062"].head(4)
for _, r in bx.iterrows(): print("  ledger 062:", r.GL_POSTING_REF, str(r.TRANSACTION_DATE)[:10], r.TRANSACTION_TYPE, f"{r.TOTAL_AMOUNT:,.2f}", f"{r.BALANCE_AFTER_TRANSACTION:,.2f}")
print("B negative amounts:", int((B.TOTAL_AMOUNT < 0).sum()), "| 305 note rows:", int(B.ROW_NOTE.astype(str).str.contains("ESTIMATED").sum()))
sch = B[B.SCHEDULED_ACTUAL_FLAG == "Scheduled"]
print("scheduled rows with balance:", int(sch.BALANCE_AFTER_TRANSACTION.notna().sum()), "of", len(sch))
M = C[(C.PERIOD_TYPE == "MONTH") & (C.RUN_ID == 41)]
miss = y[~y.index.isin(C.LOAN_ACCOUNT_NUMBER.unique())]
print("active accounts with no interest row in C:")
print(miss[miss.ACCOUNT_STATUS == "Active"][["CUSTOMER_NAME", "GL_ACCOUNT_TITLE", "LOAN_START_DATE", "SANCTIONED_AMOUNT", "OPENING_BALANCE"]].to_string())
bi = act[act.TRANSACTION_TYPE == "Interest"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
bo = act[act.TRANSACTION_TYPE == "Other/Adjustment"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
cd = M[M.PERIOD_YEAR == 2025].groupby("LOAN_ACCOUNT_NUMBER").INTEREST_INCOME_POSTED.sum()
j = pd.concat([bi.rename("B"), bo.rename("B_other"), cd.rename("C")], axis=1).fillna(0); j["diff"] = j.B - j.C
print(j[j["diff"].abs() >= 1].join(y.CUSTOMER_NAME).round(2).to_string())
print("C run 41 negative month rows:", int((M.INTEREST_INCOME_POSTED < 0).sum()), "| months covered:", sorted(set(zip(M.PERIOD_YEAR, M.PERIOD_MONTH)))[0], sorted(set(zip(M.PERIOD_YEAR, M.PERIOD_MONTH)))[-1])
