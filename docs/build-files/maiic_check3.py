import pandas as pd, numpy as np, warnings
warnings.filterwarnings("ignore")
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 30); pd.set_option("display.max_colwidth", 60)
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
C = pd.read_excel(D + r"\ExtractC_Jan2025-July2026.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
print("C RUN_ID counts:", C.RUN_ID.value_counts().to_dict(), "| GENERATED_ON:", C.GENERATED_ON.astype(str).str[:16].value_counts().head(5).to_dict())
print("B RUN_ID counts:", B.RUN_ID.value_counts().to_dict())
M = C[C.PERIOD_TYPE == "MONTH"]
k = ["LOAN_ACCOUNT_NUMBER", "PERIOD_YEAR", "PERIOD_MONTH"]
g = M.groupby(k).size()
print("C monthly rows", len(M), "| distinct account-months", len(g), "| rows per account-month:", g.value_counts().to_dict())
print("   by year, rows per account-month:", M.groupby(["PERIOD_YEAR"]).apply(lambda d: d.groupby(k).size().mean()).round(2).to_dict())
ex = M[(M.LOAN_ACCOUNT_NUMBER == "000104430000062") & (M.PERIOD_YEAR == 2025)].sort_values("PERIOD_MONTH")
print(ex[["RUN_ID", "PERIOD_MONTH", "INTEREST_INCOME_POSTED", "TRANSACTION_COUNT", "POSTING_REFERENCES"]].head(9).to_string())
bx = B[(B.LOAN_ACCOUNT_NUMBER == "000104430000062") & (B.SCHEDULED_ACTUAL_FLAG == "Actual")]
print(bx[["GL_POSTING_REF", "TRANSACTION_DATE", "TRANSACTION_TYPE", "TOTAL_AMOUNT", "BALANCE_AFTER_TRANSACTION"]].head(8).to_string())
# de-duplicated C vs B
Cd = M.drop_duplicates(subset=k + ["INTEREST_INCOME_POSTED", "POSTING_REFERENCES"])
print("C monthly after dropping identical repeats:", len(Cd), "| 2025 total", round(Cd[Cd.PERIOD_YEAR == 2025].INTEREST_INCOME_POSTED.sum()), "| 2026 total", round(Cd[Cd.PERIOD_YEAR == 2026].INTEREST_INCOME_POSTED.sum()))
act = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"]
bi = act[act.TRANSACTION_TYPE == "Interest"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
cd = Cd[Cd.PERIOD_YEAR == 2025].groupby("LOAN_ACCOUNT_NUMBER").INTEREST_INCOME_POSTED.sum()
j = pd.concat([bi.rename("B"), cd.rename("C")], axis=1).fillna(0); j["diff"] = j.B - j.C
print("after de-dup: B interest total", round(j.B.sum()), "| C 2025", round(j.C.sum()), "| accounts equal within 1:", int((j["diff"].abs() < 1).sum()), "of", len(j))
print(j[j["diff"].abs() >= 1].sort_values("diff", key=abs, ascending=False).head(6).round(0).to_string())
# snapshot differences sample
A["snap"] = A["AS_OF_DATE"].astype(str).str[:10]; s = sorted(A.snap.unique())
x = A[A.snap == s[0]].set_index("LOAN_ACCOUNT_NUMBER"); y = A[A.snap == s[1]].set_index("LOAN_ACCOUNT_NUMBER")
for col in ["PRINCIPAL_DISBURSED", "REPAYMENT_FREQUENCY", "INTEREST_RATE", "ACCOUNT_STATUS"]:
    d = x[col].astype(str) != y[col].astype(str)
    print(col, "differs on", int(d.sum()), "| sample:", list(zip(x[col][d].head(3).tolist(), y[col][d].head(3).tolist())))
print("GENERATED_ON by snapshot:", A.groupby("snap").GENERATED_ON.agg(lambda v: v.astype(str).str[:16].unique().tolist()).to_dict())
