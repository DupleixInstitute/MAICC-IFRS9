"""Demo: seed the schemes table from DD_10 (E-Banker scheme settings) and add the payment frequency to the contract master."""
import pandas as pd, subprocess, datetime
DD = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Request Responses 6 Oct 2026\Dupleix\Dupleix\DD_10_scheme_settings.csv"
F = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
CM = r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\demo_contract_master.csv"
MYSQL = r"C:\xampp\mysql\bin\mysql.exe"; DB = "maiic_ifrs9_demo"
d = pd.read_csv(DD, dtype=str).iloc[:, 1:]
print(d[["SCHEME_MST_ID", "SCHEME_NAME", "INTEREST_POLICY", "FLOATING_FLAG", "LOANINT_SANCWISE_BALWISE", "INSTALLMENT_TYPE", "EMI_BASED_ON", "INTEREST_PAYMENT_FREQUENCY", "APPLICABLE_FROM_DATE"]].to_string(index=False))
# scheme -> GL code from the account master (the product code the schemes table expects)
acm = pd.read_csv(F + r"\P1_02_account_master_all.csv", dtype=str).iloc[:, 1:]
gl_by_scheme = acm.groupby("SCHEME_MST_ID").GLCODE.agg(lambda s: s.mode().iloc[0]).to_dict()
now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
def q(v): return "NULL" if v is None or (isinstance(v, float) and v != v) or v == "" else "'" + str(v).replace("'", "''") + "'"
rows = []
for _, r in d.iterrows():
    sid = str(r.SCHEME_MST_ID).strip()
    base = {"S": "S", "B": "B", "N": "N"}.get(str(r.LOANINT_SANCWISE_BALWISE).strip(), None)
    inst = {"S": "SANCTION", "D": "DISBURSEMENT"}.get(str(r.EMI_BASED_ON).strip()[:1].upper() if isinstance(r.EMI_BASED_ON, str) else "", None)
    eff = pd.to_datetime(r.APPLICABLE_FROM_DATE, format="%m/%d/%Y", errors="coerce"); eff = eff.strftime("%Y-%m-%d") if pd.notna(eff) else "2000-01-01"
    rows.append(f"({q(sid)},{q(gl_by_scheme.get(sid))},{q(str(r.INTEREST_POLICY).strip())},{q(str(r.FLOATING_FLAG).strip())},{q(base)},{q(inst)},{q(str(r.INSTALLMENT_TYPE).strip()[:1] if isinstance(r.INSTALLMENT_TYPE, str) else None)},NULL,{q(eff)},{q(now)},{q(now)})")
sql = "INSERT INTO schemes (scheme_code, product_code, interest_policy, floating_flag, interest_calc_base, installment_based_on, emi_calc_type, default_moratorium_type, effective_from, created_at, updated_at) VALUES\n" + ",\n".join(rows) + "\nON DUPLICATE KEY UPDATE product_code=VALUES(product_code), interest_policy=VALUES(interest_policy), floating_flag=VALUES(floating_flag), interest_calc_base=VALUES(interest_calc_base), installment_based_on=VALUES(installment_based_on), emi_calc_type=VALUES(emi_calc_type), updated_at=VALUES(updated_at);"
res = subprocess.run([MYSQL, "-uroot", DB, "-e", sql], capture_output=True, text=True); print("schemes:", "ok" if res.returncode == 0 else res.stderr[:300])
# payment frequency onto the contract master from the account master's INTEREST_PAYMENT_FREQUENCY
cm = pd.read_csv(CM, dtype=str, keep_default_na=False)
freq = acm.set_index("NEW_AC_NUMBER").INTEREST_PAYMENT_FREQUENCY.to_dict()
cm["REPAYMENT_FREQUENCY"] = cm.CONTRACT_ID.map(lambda a: {"M": "MONTHLY", "Q": "QUARTERLY", "H": "SEMI-ANNUAL", "Y": "ANNUAL", "A": "ANNUAL"}.get(str(freq.get(a, "")).strip(), ""))
cm["SCHEME_CODE"] = cm.CONTRACT_ID.map(acm.set_index("NEW_AC_NUMBER").SCHEME_MST_ID.to_dict())
cm.to_csv(CM, index=False); print("frequency filled on", (cm.REPAYMENT_FREQUENCY != "").sum(), "of", len(cm), "| values:", cm.REPAYMENT_FREQUENCY.value_counts().to_dict())
