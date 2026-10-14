import openpyxl, os, warnings
from collections import Counter
warnings.filterwarnings("ignore")
base = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\New Doc Received 13 Oct"
MAIN = os.path.join(base, "MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx")
MEGA = os.path.join(base, "Mega Farms ECL Model - 14th December 2025_Final.xlsx")

def num(v):
    try:
        return float(str(v).replace(",", "")) if v not in (None, "", "-") else 0.0
    except Exception:
        return 0.0

for path, tag in [(MAIN, "MAIN"), (MEGA, "MEGA")]:
    print("=" * 90, "\n", tag)
    wv = openpyxl.load_workbook(path, data_only=True)
    lb = wv["Loan Book"]
    # Loan Book: columns D(#) E(name) G(value) H(maturity) I(tenor yrs) J(morat) O(principal) S(interest) T(repay) U(carrying) Z..AE buckets
    rows = []
    for r in range(6, lb.max_row + 1):
        if lb.cell(r, 5).value is None or lb.cell(r, 4).value is None:
            continue
        if not isinstance(lb.cell(r, 4).value, (int, float)):
            continue
        rows.append(dict(r=r, name=lb.cell(r, 5).value, tenor=lb.cell(r, 9).value, morat=lb.cell(r, 10).value,
                         principal=num(lb.cell(r, 15).value), interest=num(lb.cell(r, 19).value), repay=num(lb.cell(r, 20).value), carrying=num(lb.cell(r, 21).value),
                         b1=num(lb.cell(r, 26).value), b31=num(lb.cell(r, 27).value), b91=num(lb.cell(r, 28).value), b181=num(lb.cell(r, 29).value), b271=num(lb.cell(r, 30).value), b360=num(lb.cell(r, 31).value)))
    print("loan book rows:", len(rows))
    # bucket population
    pop = Counter()
    for x in rows:
        for k in ("b1", "b31", "b91", "b181", "b271", "b360"):
            if x[k] > 0: pop[k] += 1
    print("rows with a positive amount per bucket:", dict(pop))
    # rows whose arrears sit only in 271-360 / above 360 (the revised bracket formula ignores those columns)
    missed = [x for x in rows if x["b181"] == 0 and x["b91"] == 0 and x["b31"] == 0 and (x["b271"] > 0 or x["b360"] > 0)]
    print("rows with arrears ONLY in 271-360 / above-360 (bracket formula would read 1-30):", len(missed))
    for x in missed[:15]:
        print("   ", x["r"], x["name"], "carrying", round(x["carrying"]), "271-360", round(x["b271"]), ">360", round(x["b360"]))
    # Stage 3 rows (b181>0): interest share of carrying amount (is contractual interest left in the exposure?)
    s3 = [x for x in rows if x["b181"] > 0]
    tot_c = sum(x["carrying"] for x in s3); tot_i = sum(x["interest"] for x in s3); tot_p = sum(x["principal"] for x in s3); tot_r = sum(x["repay"] for x in s3)
    print(f"bracket >181 rows: {len(s3)}  carrying {tot_c:,.0f}  principal {tot_p:,.0f}  interest-to-date {tot_i:,.0f}  repayments {tot_r:,.0f}")
    # tenor distribution
    ten = Counter()
    for x in rows:
        t = x["tenor"]
        if isinstance(t, (int, float)):
            ten["<=1y" if t <= 1 else ("1-5y" if t <= 5 else ">5y")] += 1
        else:
            ten[f"text:{t!r}"[:30]] += 1
    print("tenor (years) classes:", dict(ten))
    mor = Counter(str(x["morat"]) for x in rows)
    print("moratorium values (top 8):", mor.most_common(8))
    # Amortisation sheet 'Restructured' blocks
    am = wv["Amortisation and Repayments"]
    print("Amortisation sheet: rows with 'Restructured' label")
    for r in range(1, min(am.max_row, 4200) + 1):
        for c in range(1, 16):
            v = am.cell(r, c).value
            if isinstance(v, str) and "estructur" in v:
                ctx = [am.cell(r, cc).value for cc in range(1, 10)]
                ctx2 = [am.cell(r - 1, cc).value for cc in range(1, 10)] if r > 1 else []
                print("   ", am.cell(r, c).coordinate, ctx2, ctx)
    # 'MAIIC & FInES' sheet header
    if "MAIIC & FInES" in wv.sheetnames:
        ms = wv["MAIIC & FInES"]
        print("'MAIIC & FInES' sheet first rows:")
        for r in range(1, 8):
            print("   ", [ms.cell(r, c).value for c in range(1, 14)])
    # Summary(OriginalMethod) rows 3-9 and 13-26 values
    sm = wv["Summary(OriginalMethod)"]
    print("Summary(OriginalMethod) B3:O9")
    for r in range(3, 10):
        print("   ", [sm.cell(r, c).value for c in range(2, 16)])
