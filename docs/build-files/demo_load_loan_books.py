"""Demo database only: build loan_books from the stored Loan Book Report runs (spec v4 method A, the bootstrap).

Reads P2_08 (every stored run) and M09_03 if present, keeps the latest run per account and month-end, maps the
columns onto loan_books, and writes INSERT ... ON DUPLICATE KEY UPDATE statements that touch only the loan-book
columns (never the ECL columns). Target: maiic_ifrs9_demo. The current database is not opened.
"""
import pandas as pd, os, subprocess, datetime
F = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
R8 = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Received 8 October"
OUT = r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\demo_loan_books.sql"
MYSQL = r"C:\xampp\mysql\bin\mysql.exe"; DB = "maiic_ifrs9_demo"

lb = pd.read_csv(F + r"\P2_08_loan_book_history.csv", dtype=str).iloc[:, 1:]
m09 = R8 + r"\M09_03_loan_book_runs_sep.csv"
if os.path.exists(m09):
    lb = pd.concat([lb, pd.read_csv(m09, dtype=str, encoding="utf-8-sig").iloc[:, 1:]], ignore_index=True)
lb["ID"] = pd.to_numeric(lb.LOAN_BOOK_DET_ID_A); lb["ASON"] = pd.to_datetime(lb.ASONDATE, format="%m/%d/%Y")
lb = lb.sort_values("ID").groupby(["NEW_AC_NUMBER", "ASON"]).tail(1).copy()
acm = pd.read_csv(F + r"\P1_02_account_master_all.csv", dtype=str).iloc[:, 1:].set_index("NEW_AC_NUMBER")
GROUP = {"1050101": ("MAIIC Agricultural Loans", "MAIIC"), "1050102": ("MAIIC Industrial Loans", "MAIIC"), "1050103": ("MAIIC Loans (1050103)", "MAIIC"),
         "1050201": ("FInES Agricultural Loans", "FInES"), "1050202": ("FInES Industrial Loans", "FInES"), "1050401": ("MAIIC Term Loans", "MAIIC")}
def num(v):
    try:
        x = float(str(v).replace(",", "")) if v not in (None, "", "nan") else 0.0
        return 0.0 if x != x else x
    except ValueError: return 0.0
def dt(v):
    try: return pd.to_datetime(v, format="%m/%d/%Y").strftime("%Y-%m-%d") if v not in (None, "", "nan") else None
    except Exception: return None
def q(v): return "NULL" if v is None else "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"
cols = ["contract_id", "customer_id", "customer_name", "product_group", "product_code", "funding_source", "loan_portfolio_id", "reporting_year", "reporting_month",
        "reporting_period", "create_date", "due_date", "interest_rate", "principal_balance", "approved_amount", "disbursed", "repayments", "carrying_amount",
        "commitments", "arrears_1_to_30", "arrears_30_to_90", "arrears_91_to_180", "arrears_180_to_270", "overdue_days", "tenor", "industry_code", "industry_type", "is_month_end", "created_at", "updated_at"]
upd = [c for c in cols if c not in ("contract_id", "reporting_period", "created_at")]
now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
rows = []
for _, r in lb.iterrows():
    gl = str(r.GLCODE).strip(); grp, fund = GROUP.get(gl, (f"GL {gl}", "MAIIC"))
    name = acm.ACCOUNT_NAME.get(r.NEW_AC_NUMBER, None)
    vals = {"contract_id": str(r.NEW_AC_NUMBER).lstrip("0"), "customer_id": str(r.CUSTOMER_ID).strip(), "customer_name": name, "product_group": grp, "product_code": gl,
            "funding_source": fund, "loan_portfolio_id": 1, "reporting_year": r.ASON.year, "reporting_month": r.ASON.month, "reporting_period": r.ASON.strftime("%Y-%m"),
            "create_date": dt(r.VALUE_DATE), "due_date": dt(r.MATURITY_DATE), "interest_rate": num(r.INTEREST_RATE), "principal_balance": num(r.PRINCIPAL),
            "approved_amount": num(r.APPROVED), "disbursed": num(r.DISBURSD), "repayments": num(r.REPAYMENT), "carrying_amount": num(r.CARRYING_AMOUNT),
            "commitments": num(r.NOT_YET_DISBURS), "arrears_1_to_30": num(r.DAY_1_30), "arrears_30_to_90": num(r.DAY_31_91), "arrears_91_to_180": num(r.DAY_91_180),
            "arrears_180_to_270": num(r.DAY_181_270), "overdue_days": int(num(r.OVERDUE_PERIOD)), "tenor": int(round(num(r.TENOR_YRS) * 12)),
            "industry_code": (str(r.INDUSTRY_CODE).strip() or None) if str(r.INDUSTRY_CODE) != "nan" else None,
            "industry_type": (str(r.IND_DESCR).strip()[:100] or None) if str(r.IND_DESCR) != "nan" else None, "is_month_end": 1, "created_at": now, "updated_at": now}
    rows.append("(" + ",".join(q(vals[c]) if isinstance(vals[c], str) or vals[c] is None else str(vals[c]) for c in cols) + ")")
sql = ["SET NAMES utf8mb4;", "START TRANSACTION;"]
for i in range(0, len(rows), 500):
    sql.append(f"INSERT INTO loan_books ({','.join(cols)}) VALUES\n" + ",\n".join(rows[i:i+500]) + "\nON DUPLICATE KEY UPDATE " + ", ".join(f"{c}=VALUES({c})" for c in upd) + ";")
sql.append("COMMIT;")
open(OUT, "w", encoding="utf-8").write("\n".join(sql))
print("rows prepared:", len(rows), "| months:", lb.ASON.dt.strftime("%Y-%m").nunique(), lb.ASON.min().date(), "to", lb.ASON.max().date())
res = subprocess.run([MYSQL, "-uroot", DB, "-e", f"source {OUT}"], capture_output=True, text=True)
print("mysql:", "ok" if res.returncode == 0 else res.stderr[:400])
