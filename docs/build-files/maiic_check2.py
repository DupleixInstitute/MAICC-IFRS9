import pandas as pd, numpy as np, warnings
warnings.filterwarnings("ignore")
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 30)
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
C = pd.read_excel(D + r"\ExtractC_Jan2025-July2026.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
A["snap"] = A["AS_OF_DATE"].astype(str).str[:10]
print("1) RATE_BASIS by GL title (one snapshot):")
a1 = A[A.snap == A.snap.max()]
print(pd.crosstab(a1["GL_ACCOUNT_TITLE"], a1["RATE_BASIS"]).to_string())
print("\n2) Fields that differ between the two snapshots for the same account:")
s = sorted(A.snap.unique()); x = A[A.snap == s[0]].set_index("LOAN_ACCOUNT_NUMBER"); y = A[A.snap == s[1]].set_index("LOAN_ACCOUNT_NUMBER")
common = x.index.intersection(y.index); print("   snapshots", s, "| accounts in both", len(common))
for col in A.columns:
    if col in ("AS_OF_DATE", "snap", "GENERATED_ON", "LOAN_ACCOUNT_NUMBER") or str(col).strip() == "" or str(col).startswith("Unnamed"): continue
    d = (x.loc[common, col].astype(str) != y.loc[common, col].astype(str)).sum()
    if d: print(f"   {col}: differs on {d}")
print("\n3) PRINCIPAL_DISBURSED vs SANCTIONED_AMOUNT (latest snapshot):")
p = a1.dropna(subset=["PRINCIPAL_DISBURSED"])
print("   rows with disbursed populated", len(p), "| equal to sanctioned", int((p.PRINCIPAL_DISBURSED.round(2) == p.SANCTIONED_AMOUNT.round(2)).sum()),
      "| less", int((p.PRINCIPAL_DISBURSED < p.SANCTIONED_AMOUNT - 1).sum()), "| more", int((p.PRINCIPAL_DISBURSED > p.SANCTIONED_AMOUNT + 1).sum()))
lm = a1[a1.LOAN_ACCOUNT_NUMBER.str.endswith("106050000009")]
print("   Lake Malawi:", lm[["SANCTIONED_AMOUNT", "PRINCIPAL_DISBURSED", "DISBURSEMENT_TRANCHES", "OPENING_BALANCE"]].to_dict("records"))
print("\n4) FIRST_REPAYMENT_DATE equals LOAN_START_DATE (as text):", int((a1.FIRST_REPAYMENT_DATE.astype(str) == a1.LOAN_START_DATE.astype(str)).sum()), "of", int(a1.FIRST_REPAYMENT_DATE.notna().sum()))
print(a1[["LOAN_START_DATE", "FIRST_REPAYMENT_DATE", "CONTRACTUAL_MATURITY_DATE", "TENOR", "NUM_INSTALMENTS", "PRINCIPAL_GRACE_PERIOD"]].head(6).to_string())
print("\n5) Extract B 2025 interest vs Extract C 2025, by account:")
act = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"]
print("   B actual rows by type:", act.TRANSACTION_TYPE.value_counts().to_dict())
bi = act[act.TRANSACTION_TYPE == "Interest"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
bo = act[act.TRANSACTION_TYPE == "Other/Adjustment"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
cy = C[(C.PERIOD_TYPE == "YEAR") & (C.PERIOD_YEAR == 2025)].groupby("LOAN_ACCOUNT_NUMBER").INTEREST_INCOME_POSTED.sum()
cm = C[(C.PERIOD_TYPE == "MONTH") & (C.PERIOD_YEAR == 2025)].groupby("LOAN_ACCOUNT_NUMBER").INTEREST_INCOME_POSTED.sum()
print("   C: YEAR rows", int((C.PERIOD_TYPE == "YEAR").sum()), "| MONTH rows", int((C.PERIOD_TYPE == "MONTH").sum()), "| sum of all rows", round(C.INTEREST_INCOME_POSTED.sum()), "| sum MONTH only", round(C[C.PERIOD_TYPE == "MONTH"].INTEREST_INCOME_POSTED.sum()))
print("   C 2025: YEAR total", round(cy.sum()), "| MONTH total", round(cm.sum()), "| negative monthly rows (all years)", int((C[C.PERIOD_TYPE == "MONTH"].INTEREST_INCOME_POSTED < 0).sum()))
j = pd.concat([bi.rename("B_interest"), bo.rename("B_other"), cm.rename("C_2025")], axis=1).fillna(0)
j["diff"] = j.B_interest - j.C_2025
print("   accounts: in B interest", int((j.B_interest > 0).sum()), "| in C 2025", int((j.C_2025 != 0).sum()), "| totals B", round(j.B_interest.sum()), "C", round(j.C_2025.sum()))
print("   accounts where B interest = C (within 1):", int((j["diff"].abs() < 1).sum()), "| differ:", int((j["diff"].abs() >= 1).sum()))
dd = j[j["diff"].abs() >= 1].copy(); dd["diff_minus_other"] = dd["diff"].abs() - dd.B_other
print(dd.sort_values("diff", key=abs, ascending=False).head(8).round(0).to_string())
print("\n6) Accounts in A but not in C, by status (latest snapshot):")
missing = a1[~a1.LOAN_ACCOUNT_NUMBER.isin(C.LOAN_ACCOUNT_NUMBER.unique())]
print("   ", len(missing), "missing |", missing.ACCOUNT_STATUS.value_counts().to_dict())
print("    in C by status:", a1[a1.LOAN_ACCOUNT_NUMBER.isin(C.LOAN_ACCOUNT_NUMBER.unique())].ACCOUNT_STATUS.value_counts().to_dict())
print("\n7) Scheduled rows in B:")
sch = B[B.SCHEDULED_ACTUAL_FLAG == "Scheduled"]
print("   rows", len(sch), "| accounts", sch.LOAN_ACCOUNT_NUMBER.nunique(), "| accounts with actual rows", act.LOAN_ACCOUNT_NUMBER.nunique())
print("   305 rows", int((act.TRANSACTION_TYPE == "Principal+Interest").sum()), "| of which interest component = total (capped):", int(((act.TRANSACTION_TYPE == "Principal+Interest") & ((act.INTEREST_COMPONENT - act.TOTAL_AMOUNT).abs() < 0.01)).sum()))
print("   status H/F rows: rate basis", a1[a1.ACCOUNT_STATUS.isin(["H", "F"])].groupby("ACCOUNT_STATUS").OPENING_BALANCE.agg(["count", "sum"]).round(0).to_dict())
