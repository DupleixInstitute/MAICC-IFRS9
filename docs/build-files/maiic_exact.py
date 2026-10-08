import pandas as pd, warnings
warnings.filterwarnings("ignore")
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
C = pd.read_excel(D + r"\ExtractC_Jan2025-July2026.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
y = A[A["AS_OF_DATE"].astype(str).str[:10] == "31-12-2025"].set_index("LOAN_ACCOUNT_NUMBER")
act = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"]
bi = act[act.TRANSACTION_TYPE == "Interest"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
bo = act[act.TRANSACTION_TYPE == "Other/Adjustment"].groupby("LOAN_ACCOUNT_NUMBER").TOTAL_AMOUNT.sum()
M = C[(C.PERIOD_TYPE == "MONTH") & (C.RUN_ID == 41)]
cd = M[M.PERIOD_YEAR == 2025].groupby("LOAN_ACCOUNT_NUMBER").INTEREST_INCOME_POSTED.sum()
j = pd.concat([bi.rename("B"), bo.rename("O"), cd.rename("C")], axis=1).fillna(0); j["d"] = j.B - j.C
for k, r in j[j.d.abs() >= 1].sort_values("d", ascending=False).iterrows():
    print(f'["{k}", "{y.CUSTOMER_NAME[k]}", "{r.B:,.2f}", "{r.O:,.2f}", "{r.C:,.2f}", "{r.d:,.2f}"],')
print("totals: B", f"{j.B.sum():,.2f}", "C", f"{j.C.sum():,.2f}", "diff", f"{j.d.sum():,.2f}", "| sum of 6 diffs", f"{j[j.d.abs() >= 1].d.sum():,.2f}", "| max abs diff among the 101:", f"{j[j.d.abs() < 1].d.abs().max():.4f}")
for run in (7, 25, 41):
    m = C[(C.PERIOD_TYPE == "MONTH") & (C.RUN_ID == run)]
    print("run", run, "rows", int((C.RUN_ID == run).sum()), "accounts", m.LOAN_ACCOUNT_NUMBER.nunique(), "2025", f"{m[m.PERIOD_YEAR == 2025].INTEREST_INCOME_POSTED.sum():,.2f}", "2026", f"{m[m.PERIOD_YEAR == 2026].INTEREST_INCOME_POSTED.sum():,.2f}", "gen", C[C.RUN_ID == run].GENERATED_ON.astype(str).str[:16].unique().tolist())
allm = C[C.PERIOD_TYPE == "MONTH"]
print("all runs 2025 monthly:", f"{allm[allm.PERIOD_YEAR == 2025].INTEREST_INCOME_POSTED.sum():,.2f}", "| all rows sum", f"{C.INTEREST_INCOME_POSTED.sum():,.2f}", "| monthly rows sum", f"{allm.INTEREST_INCOME_POSTED.sum():,.2f}")
miss = y[~y.index.isin(C.LOAN_ACCOUNT_NUMBER.unique())]
for k, r in miss[miss.ACCOUNT_STATUS == "Active"].sort_values("SANCTIONED_AMOUNT", ascending=False).iterrows():
    print(f'["{k}", "{r.CUSTOMER_NAME}", "{r.GL_ACCOUNT_TITLE}", "{r.SANCTIONED_AMOUNT:,.2f}"],')
lm = y.loc["000106050000009"]; print("Lake Malawi:", lm.CUSTOMER_NAME, f"{lm.SANCTIONED_AMOUNT:,.2f}", lm.DISBURSEMENT_TRANCHES)
eb = y.loc["000104430000084"]; print("Ebenezer:", eb.CUSTOMER_NAME, eb.DISBURSEMENT_TRANCHES, "| ledger disbursement rows:", int(((act.LOAN_ACCOUNT_NUMBER == "000104430000084") & (act.TRANSACTION_TYPE == "Disbursement")).sum()), f"{act[(act.LOAN_ACCOUNT_NUMBER == '000104430000084') & (act.TRANSACTION_TYPE == 'Disbursement')].TOTAL_AMOUNT.sum():,.2f}")
print("tranche entries per row:", y.DISBURSEMENT_TRANCHES.dropna().str.count(";").add(1).value_counts().to_dict(), "| ledger 062 customer:", y.CUSTOMER_NAME["000104430000062"])
