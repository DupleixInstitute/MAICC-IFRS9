import csv, os
from collections import Counter, defaultdict
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Received 12 October 2026\Pack 4\Pack 9\Export Results"
def load(n):
    with open(os.path.join(D, n), encoding="utf-8-sig") as f:
        return list(csv.DictReader(f))
def num(v):
    try: return float(str(v).replace(",", "")) if v not in (None, "", "-") else 0.0
    except: return 0.0

# CV_08 counts vs files
counts = {r["TABLE_NAME"]: int(r["ROW_COUNT"]) for r in load("CV_08_table_counts.csv")}
files = {"CV_09a_account_no_mapp.csv": "VGCBS.ACCOUNT_NO_MAPP", "CV_09b_new_acmap.csv": "VGCBS.NEW_ACMAP", "CV_10_opening_bal.csv": "VGCBS.OPENING_BAL",
         "CV_11a_t_account.csv": "VGCBS.T_ACCOUNT", "CV_11b_t_accountbalance.csv": "VGCBS.T_ACCOUNTBALANCE", "CV_12a_old_block_g_acmaster.csv": "VGCBS.G_ACMASTER",
         "CV_12b_old_block_acmaster_mw.csv": "VGCBS.ACMASTER_MW", "CV_12c_old_block_acmaster_nf.csv": "VGCBS.ACMASTER_NF", "CV_12d_acmaster_05082024_loans.csv": "MAIIC_ORA_LIVE.CRM_ACMASTER_05082024",
         "CV_12e_temp_master.csv": "VGCBS.TEMP_MASTER", "CV_13_cumovuch_old_profile.csv": "VGCBS.CUMOVUCH_OLD", "CV_14_gl_opening_balance_bk240824.csv": "VGCBS.GL_OPENING_BALANCE_BK240824"}
print("== file rows vs RUN 1 table counts")
for f, t in files.items():
    print(f"  {f:42s} rows {len(load(f)):6d}   table {t} = {counts.get(t)}")

# CV_13 profile
cv13 = load("CV_13_cumovuch_old_profile.csv")
tot = sum(int(r["ROW_COUNT"]) for r in cv13)
print(f"\n== CV_13: profile rows {len(cv13)}, ROW_COUNT sum {tot} (table count {counts['VGCBS.CUMOVUCH_OLD']})")
byyr = defaultdict(int); byblk = defaultdict(int)
for r in cv13: byyr[r["YR"]] += int(r["ROW_COUNT"]); byblk[r["ACCOUNT_BLOCK"]] += int(r["ROW_COUNT"])
print("  by year:", dict(sorted(byyr.items())))
print("  blocks (top 12):", sorted(byblk.items(), key=lambda x: -x[1])[:12])
print("  00010075 block rows:", byblk.get("00010075", 0), " 00010442/43/44/45/46 (today's loan blocks):", {b: byblk.get(b, 0) for b in ["00010442", "00010443", "00010444", "00010445", "00010446"]})

# CV_14 loan GLs
cv14 = load("CV_14_gl_opening_balance_bk240824.csv")
print(f"\n== CV_14: {len(cv14)} rows; FY {set(r['FINYEARSTARTDATE'] for r in cv14)}; operation dates {Counter(r['OPERATION_DATE'] for r in cv14).most_common(3)}")
print("  loan and interest GLs:")
for r in cv14:
    if r["GLCODE"] in ("1050", "1050101", "1050102", "1050103", "1050201", "1050202", "1050203", "1050401", "1050402", "1052", "1053", "4206", "4215", "4216", "1320"):
        print(f"    GL {r['GLCODE']:8s} opening {num(r['OPENING_BAL']):>20,.2f}  receipts {num(r['RECEIPT_AMOUNT']):>18,.2f}  payments {num(r['PAYMENT_AMOUNT']):>18,.2f}")
print("  sum of all opening balances:", f"{sum(num(r['OPENING_BAL']) for r in cv14):,.2f}")

# CV_10 opening balances
cv10 = load("CV_10_opening_bal.csv")
print(f"\n== CV_10: {len(cv10)} rows; GLs {Counter(r['GLCODE'] for r in cv10)}")
print("  accounts:", len(set(r["ACCNO"] for r in cv10)), " with restructure date:", sum(1 for r in cv10 if r["RESTRUCTURE_DATE"]))
print("  sums: BALANCE_AMT", f"{sum(num(r['BALANCE_AMT']) for r in cv10):,.2f}", " TOTAL_INT_CHARG", f"{sum(num(r['TOTAL_INT_CHARG']) for r in cv10):,.2f}",
      " TOTA_REPAY_UPTO", f"{sum(num(r['TOTA_REPAY_UPTO']) for r in cv10):,.2f}", " DISBURS_AMT", f"{sum(num(r['DISBURS_AMT']) for r in cv10):,.2f}", " TOT_OVD", f"{sum(num(r['TOT_OVD_AMT']) for r in cv10):,.2f}")
import json
json.dump(cv10, open(os.path.join(os.path.dirname(__file__), "cv10.json"), "w"), indent=1)
