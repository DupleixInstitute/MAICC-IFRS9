"""Demo: set overdue_days from OVERDUE_PRINCI_DATE and write the five arrears buckets as the classifier expects, then stage via the system.
Writes a staging input file (contract_id, period, buckets, dpd) and an SQL update for overdue_days; the staging itself runs in tinker."""
import pandas as pd, subprocess
F = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
OUT = r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\demo_stage_input.csv"
MYSQL = r"C:\xampp\mysql\bin\mysql.exe"; DB = "maiic_ifrs9_demo"
lb = pd.read_csv(F + r"\P2_08_loan_book_history.csv", dtype=str).iloc[:, 1:]
lb["ID"] = pd.to_numeric(lb.LOAN_BOOK_DET_ID_A); lb["ASON"] = pd.to_datetime(lb.ASONDATE, format="%m/%d/%Y")
lb = lb.sort_values("ID").groupby(["NEW_AC_NUMBER", "ASON"]).tail(1).copy()
lb["OD"] = pd.to_datetime(lb.OVERDUE_PRINCI_DATE, format="%m/%d/%Y", errors="coerce")
lb["dpd"] = ((lb.ASON - lb.OD).dt.days).clip(lower=0).fillna(0).astype(int)
tot = pd.to_numeric(lb.ARREAS_TOTAL, errors="coerce").fillna(0)
lb.loc[tot <= 0, "dpd"] = 0   # no arrears, no days past due, whatever the stale date says
out = pd.DataFrame({"contract_id": lb.NEW_AC_NUMBER.str.lstrip("0"), "period": lb.ASON.dt.strftime("%Y-%m"), "dpd": lb.dpd,
                    "1_30_days": pd.to_numeric(lb.DAY_1_30, errors="coerce").fillna(0), "31_90_days": pd.to_numeric(lb.DAY_31_91, errors="coerce").fillna(0),
                    "91_180_days": pd.to_numeric(lb.DAY_91_180, errors="coerce").fillna(0), "181_270_days": pd.to_numeric(lb.DAY_181_270, errors="coerce").fillna(0),
                    "271_360_days": pd.to_numeric(lb.DAY_271_360, errors="coerce").fillna(0), "arrears_total": tot})
out.to_csv(OUT, index=False)
print("rows", len(out), "| with arrears:", (out.arrears_total > 0).sum(), "| dpd>0:", (out.dpd > 0).sum(), "| dpd>=91:", (out.dpd >= 91).sum(), "| dpd>=181:", (out.dpd >= 181).sum(), "| max dpd:", out.dpd.max())
sql = "START TRANSACTION;\n" + "\n".join(f"UPDATE loan_books SET overdue_days={int(r.dpd)} WHERE contract_id='{r.contract_id}' AND reporting_period='{r.period}';" for r in out.itertuples()) + "\nCOMMIT;"
p = OUT.replace(".csv", ".sql"); open(p, "w", encoding="utf-8").write(sql)
res = subprocess.run([MYSQL, "-uroot", DB, "-e", f"source {p}"], capture_output=True, text=True); print("overdue_days updated:", "ok" if res.returncode == 0 else res.stderr[:200])
