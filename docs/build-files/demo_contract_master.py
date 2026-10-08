"""Demo: build a contract-master file from the E-Banker masters (P1_02, P1_03) and the latest loan-book row, with the
headers the contract-master importer already accepts, so the system's own importer loads it. ISO dates throughout."""
import pandas as pd, os
F = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
OUT = r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\demo_contract_master.csv"
acm = pd.read_csv(F + r"\P1_02_account_master_all.csv", dtype=str).iloc[:, 1:].set_index("NEW_AC_NUMBER")
alm = pd.read_csv(F + r"\P1_03_loan_master_all.csv", dtype=str).iloc[:, 1:].drop_duplicates("NEW_AC_NUMBER").set_index("NEW_AC_NUMBER")
lb = pd.read_csv(F + r"\P2_08_loan_book_history.csv", dtype=str).iloc[:, 1:]
lb["ID"] = pd.to_numeric(lb.LOAN_BOOK_DET_ID_A); lb["ASON"] = pd.to_datetime(lb.ASONDATE, format="%m/%d/%Y")
latest = lb.sort_values(["ASON", "ID"]).groupby("NEW_AC_NUMBER").tail(1).set_index("NEW_AC_NUMBER")
dd = pd.read_csv(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Request Responses 6 Oct 2026\Dupleix\Dupleix\DD_09_schemes_all.csv", dtype=str).iloc[:, 1:]
scheme = dict(zip(dd.iloc[:, 0], dd.iloc[:, 1].str.strip()))
GLFUND = {"1050201": "FInES", "1050202": "FInES"}
def iso(v):
    try: return pd.to_datetime(v, format="%m/%d/%Y").strftime("%Y-%m-%d") if isinstance(v, str) and v.strip() else ""
    except Exception: return ""
def n(v):
    try: return float(str(v).replace(",", "")) if isinstance(v, str) and v.strip() else 0.0
    except ValueError: return 0.0
rows = []
for ac, a in acm.iterrows():
    l = alm.loc[ac] if ac in alm.index else None; b = latest.loc[ac] if ac in latest.index else None
    gl = str(a.get("GLCODE", "") or (b.GLCODE if b is not None else "")).strip()
    months = (n(l.PERIOD_YEARS) * 12 + n(l.PERIOD_MONTHS)) if l is not None else (n(b.TENOR_YRS) * 12 if b is not None else 0)
    policy = str(a.INTEREST_POLICY).strip() if isinstance(a.INTEREST_POLICY, str) else ""
    rows.append({
        "CONTRACT_ID": ac, "CUSTOMER_ID": str(a.CUSTOMER_ID).strip(), "CUSTOMER_NAME": a.ACCOUNT_NAME, "GL_ACCOUNT_CODE": gl,
        "PRODUCT_TYPE": scheme.get(str(a.SCHEME_MST_ID).strip(), f"Scheme {a.SCHEME_MST_ID}"), "FUNDING_SOURCE": GLFUND.get(gl, "MAIIC"), "CURRENCY": "MWK",
        "VALUE_DATE": iso(b.VALUE_DATE) if b is not None else iso(a.ACCOUNT_OPEN_DATE), "MATURITY_DATE": iso(b.MATURITY_DATE) if b is not None else (iso(l.EXPIRY_DATE) if l is not None else ""),
        "SANCTIONED_AMOUNT": n(l.SANCTION_AMOUNT) if l is not None else (n(b.APPROVED) if b is not None else 0),
        "DISBURSED_AMOUNT": n(b.DISBURSD) if b is not None else 0,
        "INTEREST_RATE": n(b.INTEREST_RATE) if b is not None else n(a.INTEREST_RATE),
        "RATE_TYPE": "FLOATING" if policy == "P" else "FIXED",
        "TENOR_MONTHS": int(round(months)), "MORATORIUM_MONTHS": int(n(l.PMOROTORIUM_PERIOD)) if l is not None else 0,
        "INTEREST_POLICY": policy, "FLOATING_FLAG": str(a.get("FLOATING_FLAG", "")).strip() if isinstance(a.get("FLOATING_FLAG", ""), str) else "",
        "INTEREST_START_DATE": iso(b.VALUE_DATE) if b is not None else "", "STATUS": str(a.STATUS_CODE).strip(),
    })
df = pd.DataFrame(rows); df.to_csv(OUT, index=False)
print("contract master rows:", len(df), "| floating:", (df.RATE_TYPE == "FLOATING").sum(), "| fixed:", (df.RATE_TYPE == "FIXED").sum(), "| with maturity:", (df.MATURITY_DATE != "").sum())
print(df.head(3).to_string(index=False))
