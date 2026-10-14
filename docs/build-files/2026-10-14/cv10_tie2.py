import json, os, csv
from collections import Counter, defaultdict
S = os.path.dirname(__file__)
cv10 = json.load(open(os.path.join(S, "cv10.json")))
tk = json.load(open(os.path.join(S, "takeon.json")))
pk = {a.lstrip("0"): v for a, v in tk["postings"].items()}
def num(v):
    try: return float(str(v).replace(",", "")) if v not in (None, "", "-") else 0.0
    except: return 0.0
loan_rows = [r for r in cv10 if r["GLCODE"].startswith("1050")]
# 301 against NEWDISBURS_AMT (the restructured amount where restructured)
ok = 0; bad = []
for r in loan_rows:
    p = pk.get(r["ACCNO"].lstrip("0"))
    if not p: continue
    nd = -num(r["NEWDISBURS_AMT"]); t = -p.get("301", 0.0)
    if abs(nd - t) <= 1: ok += 1
    else: bad.append((r["ACCNO"], r["NAME_OF_CUST"][:24], t, nd, -num(r["DISBURS_AMT"])))
print("301 = NEWDISBURS_AMT on", ok, "accounts; differs:", bad)
# interest: 303 vs TOTAL_INT_CHARG, and the two that differ
for r in loan_rows:
    p = pk.get(r["ACCNO"].lstrip("0"))
    if p and abs(-p.get("303", 0.0) - (-num(r["TOTAL_INT_CHARG"]))) > 1:
        print("interest differs:", r["ACCNO"], r["NAME_OF_CUST"][:24], "303", -p.get("303", 0.0), "TOTAL_INT_CHARG", -num(r["TOTAL_INT_CHARG"]), "INT_REPAY_UPTO", num(r["INT_REPAY_UPTO"]))
# repayments: 305 vs TOTA_REPAY_UPTO; does 305 = PRINC_REPY_UPTO + INT_REPAY_UPTO or something else?
alt = Counter()
for r in loan_rows:
    p = pk.get(r["ACCNO"].lstrip("0"))
    if not p: continue
    t = p.get("305", 0.0); rep = num(r["TOTA_REPAY_UPTO"]); pr = num(r["PRINC_REPY_UPTO"]); ir = num(r["INT_REPAY_UPTO"])
    if abs(t - rep) <= 1: alt["=TOTA_REPAY"] += 1
    elif abs(t - (pr + ir)) <= 1: alt["=PRINC+INT"] += 1
    else:
        alt["other"] += 1; print("repay differs:", r["ACCNO"], r["NAME_OF_CUST"][:24], "305", t, "TOTA_REPAY", rep, "PRINC", pr, "INT", ir, "diff", round(t - rep, 2))
print("305 vs worksheet:", dict(alt))
# the odd accounts
for r in cv10:
    if r["ACCNO"] in ("000104460000101", "000104430000059", "000104430000062"):
        print("row:", {k: r[k] for k in ("GLCODE", "ACCNO", "NAME_OF_CUST", "SANCTION_DATE", "DISBURS_AMT", "BALANCE_AMT", "TOTAL_INT_CHARG", "TOTA_REPAY_UPTO", "NEWDISBURS_AMT")})
# totals by GL of BALANCE_AMT (the take-on principal) for CV_14 context
byg = defaultdict(float)
for r in loan_rows: byg[r["GLCODE"]] += num(r["BALANCE_AMT"])
print("CV_10 BALANCE_AMT by GL:", {k: round(v, 2) for k, v in byg.items()})

# CV_14 tie: the pack 7 bk240824 ledger copy summed by GL to 31 Dec 2023
P7 = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Received 12 October 2026\Pack 4\Pack 7\Export Results"
gls = defaultdict(float); n = 0; cols = None
from datetime import datetime
for f in ["CV_04a1_bk240824_2018_2022.csv", "CV_04a2_bk240824_2023.csv"]:
    with open(os.path.join(P7, f), encoding="utf-8-sig") as fh:
        rd = csv.DictReader(fh)
        if cols is None:
            cols = rd.fieldnames; print("ledger columns (first 20):", cols[:20])
        for r in rd:
            d = r.get("TRANSACTION_DATE", "")
            try: dt = datetime.strptime(d.split(" ")[0], "%m/%d/%Y")
            except Exception:
                try: dt = datetime.strptime(d[:10], "%Y-%m-%d")
                except Exception: continue
            if dt.year > 2023: continue
            n += 1
            gls[r.get("GLCODE", "?")] += num(r.get("TRANSAMT"))
print("bk240824 rows to 31 Dec 2023:", n)
for g in ["1050101", "1050102", "1050201", "1050202", "1052", "1053", "1320", "1016"]:
    print(f"   GL {g}: net postings to 31 Dec 2023 {gls.get(g, 0.0):>20,.2f}")
