import json, os
from collections import Counter
S = os.path.dirname(__file__)
cv10 = json.load(open(os.path.join(S, "cv10.json")))
tk = json.load(open(os.path.join(S, "takeon.json")))
posts = tk["postings"]; book = tk["book_2024_07"]
def num(v):
    try: return float(str(v).replace(",", "")) if v not in (None, "", "-") else 0.0
    except: return 0.0
def key(a): return a.lstrip("0")

print("== take-on postings: sample", list(posts.items())[:2])
pk = {key(a): v for a, v in posts.items()}
loan_rows = [r for r in cv10 if r["GLCODE"].startswith("1050")]
gl2000 = [r for r in cv10 if r["GLCODE"] == "2000"]
print(f"CV_10 loan-GL rows {len(loan_rows)} on {len(set(r['ACCNO'] for r in loan_rows))} accounts; GL 2000 rows {len(gl2000)}")
print("GL 2000 rows (what are they):")
for r in gl2000[:17]:
    print(f"   {r['ACCNO']} {r['NAME_OF_CUST'][:30]:30s} bal {num(r['BALANCE_AMT']):>18,.2f} int {num(r['TOTAL_INT_CHARG']):>18,.2f} repay {num(r['TOTA_REPAY_UPTO']):>18,.2f}")
dup = [a for a, n in Counter(r["ACCNO"] for r in loan_rows).items() if n > 1]
print("duplicated accounts in loan rows:", dup)

inpack = set(key(r["ACCNO"]) for r in loan_rows); intake = set(pk)
print(f"accounts in CV_10 loan rows: {len(inpack)}; with take-on postings: {len(intake)}; both: {len(inpack & intake)}; CV_10 only: {sorted(inpack - intake)}; take-on only: {sorted(intake - inpack)}")

# per-account tie: 301 (principal) vs BALANCE_AMT / NEWDISBURS_AMT; 303 vs TOTAL_INT_CHARG; 305 vs TOTA_REPAY_UPTO
agree = Counter(); diffs = []
tot = Counter()
for r in loan_rows:
    k = key(r["ACCNO"]); p = pk.get(k)
    if not p: continue
    t301 = p.get("301", 0.0); t303 = p.get("303", 0.0); t305 = p.get("305", 0.0)
    bal = num(r["BALANCE_AMT"]); intc = -num(r["TOTAL_INT_CHARG"]); rep = num(r["TOTA_REPAY_UPTO"]); disb = -num(r["DISBURS_AMT"]); ndisb = -num(r["NEWDISBURS_AMT"])
    princ = num(r["PRINC_REPY_UPTO"]); intrep = num(r["INT_REPAY_UPTO"])
    tot["301"] += t301; tot["303"] += t303; tot["305"] += t305; tot["bal"] += bal; tot["int"] += intc; tot["rep"] += rep; tot["disb"] += disb
    for name, a, b in [("301=DISBURS", t301, disb), ("301=BALANCE", t301, bal), ("303=INT_CHARG", t303, intc), ("305=TOTA_REPAY", t305, rep), ("301+303-305=BAL+INT-REP?", t301 + t303 - t305, bal)]:
        if abs(abs(a) - abs(b)) <= 1.0: agree[name] += 1
    if abs(abs(t301) - abs(disb)) > 1 or abs(abs(t303) - abs(intc)) > 1 or abs(abs(t305) - abs(rep)) > 1:
        diffs.append((r["ACCNO"], r["NAME_OF_CUST"][:24], t301, disb, bal, t303, intc, t305, rep, r["RESTRUCTURE_DATE"]))
print("\nper-account agreement counts (of accounts in both):", dict(agree))
print("totals: 301 %.2f vs DISBURS %.2f | 303 %.2f vs INT_CHARG %.2f | 305 %.2f vs REPAY %.2f | BALANCE_AMT %.2f" % (tot["301"], tot["disb"], tot["303"], tot["int"], tot["305"], tot["rep"], tot["bal"]))
print(f"\naccounts where a take-on posting differs from CV_10: {len(diffs)}")
print("ACCNO | name | 301 | DISBURS | BALANCE | 303 | INT_CHARG | 305 | REPAY | restructure")
for d in diffs[:40]:
    print(" | ".join(str(round(x, 2)) if isinstance(x, float) else str(x) for x in d))
print("\nrows with RESTRUCTURE_DATE:")
for r in cv10:
    if r["RESTRUCTURE_DATE"]:
        print(f"   {r['ACCNO']} {r['NAME_OF_CUST'][:28]:28s} GL {r['GLCODE']} sanction {r['SANCTION_DATE']} restructured {r['RESTRUCTURE_DATE']} amt {num(r['RESTRUCURE_AMT']):>16,.2f} open {r['OPEN_DATE']} maturity {r['MATURITY_DATE']} tenor {r['TENOR_YRS']}")
# book carrying at Jul 2024 vs BALANCE_AMT + interest?
print("\nbook (system, Jul 2024) carrying vs CV_10 BALANCE_AMT:")
same = 0; shown = 0
for r in loan_rows:
    k = key(r["ACCNO"]); b = book.get(k)
    if not b: continue
    if abs(b["carrying"] - num(r["BALANCE_AMT"])) <= 1: same += 1
    elif shown < 12:
        shown += 1; print(f"   {r['ACCNO']} {b['name'][:24]:24s} system carrying {b['carrying']:>18,.2f}  CV_10 BALANCE_AMT {num(r['BALANCE_AMT']):>18,.2f}  diff {b['carrying']-num(r['BALANCE_AMT']):>16,.2f}")
print("   carrying equal on", same, "accounts")
