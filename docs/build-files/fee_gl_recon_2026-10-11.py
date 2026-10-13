"""Reconcile the GL 4871 / 4873 fee spools to the trial balances and to the loan-account ledger (P1_01). 11 Oct 2026, spec v4 section 3.7.
Usage: python fee_gl_recon_2026-10-11.py "<OneDrive>\2026\Projects\MAIIC\2. Documents from clients"
"""
import sys, os, re, glob, csv, io, collections, warnings
import pandas as pd
warnings.filterwarnings("ignore")

base = sys.argv[1]
spool_dir = os.path.join(base, "New Doc Received 19 Aug", "Dupleix 2026", "Dupleix 2")
num = lambda s: float(str(s).replace(",", "")) if pd.notna(s) and str(s).strip() not in ("", "nan") else 0.0


def parse(path):
    df = pd.read_excel(path, header=None)
    pos = None
    rows, pages = [], []
    for i in range(len(df)):
        r = df.iloc[i]
        cells = {j: v for j, v in r.items() if pd.notna(v)}
        vals = [str(v).strip() for v in cells.values()]
        if "Particulars" in vals and "Debit" in vals:
            pos = {str(v).strip(): j for j, v in cells.items()}
            continue
        if pos is None:
            continue
        if any("Recursive Page Total" in v for v in vals):
            pages.append(cells)
            continue
        d = r.get(pos["Tran. Date"])
        if pd.isna(d) or not re.match(r"\d{2}/\d{2}/\d{4}", str(d)):
            continue
        part = str(r.get(pos["Particulars"], "")).strip()
        if part == "Opening Balance":
            continue
        dd, mm, yy = str(d).split("/")
        rows.append({"date": f"{yy}-{mm}-{dd}", "part": part, "dr": num(r.get(pos["Debit"])), "cr": num(r.get(pos["Credit"])),
                     "bal": num(r.get(pos["Balance"])), "enter": str(r.get(pos["Enter By"], "")), "pass": str(r.get(pos["Pass By"], ""))})
    return rows


spools = {}
for f in sorted(glob.glob(os.path.join(spool_dir, "GL 487*.xls"))):
    spools[os.path.basename(f)] = parse(f)
    rows = spools[os.path.basename(f)]
    net = sum(r["cr"] - r["dr"] for r in rows)
    print(f"{os.path.basename(f):45s} lines {len(rows):3d}  {rows[0]['date']} to {rows[-1]['date']}  net credit {net:,.2f}  last balance {rows[-1]['bal']:,.2f}  debits {sum(r['dr'] for r in rows):,.2f}")

a = spools["GL 4871 as at 31 Dec 2025.xls"]; b = spools["GL 4871 as at 31 Dec 2025-MAITAMSITFIL02.xls"]
print("4871 Dec 2025: the two copies identical?", [(x["date"], x["cr"], x["dr"]) for x in a] == [(x["date"], x["cr"], x["dr"]) for x in b])

# trial balances, YTD
mon = {m: i for i, m in enumerate(["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"], 1)}
tb = {}
for f in glob.glob(os.path.join(base, "Trial Balances", "*.xls")):
    m = re.search(r"_(\d+) (\w+) (\d{4})", f); key = f"{m.group(3)}-{mon[m.group(2)]:02d}"
    df = pd.read_excel(f, header=None); v = {}
    for _, r in df.iterrows():
        t = str(r[1]).strip()
        for code in ("4871", "4873"):
            if t.startswith(code + "..."):
                v[code] = num(r[3]) - num(r[2])
    tb[key] = v
tb["2025-12"] = {"4871": 171128135.0, "4873": 311342792.0}  # Final E-Banker TB Dec 2025, AFS mapping workbook

print("\nYTD per spool against YTD per trial balance")
print(f"{'month':8} | {'4871 spool':>15} {'4871 TB':>15} {'diff':>12} | {'4873 spool':>15} {'4873 TB':>15} {'diff':>12}")
for key in sorted(tb):
    yr = key[:4]
    out = [key]
    for code in ("4871", "4873"):
        src = spools[f"GL {code} as at 31 Dec 2025.xls"] if yr == "2025" else spools[f"GL {code} as at 31 July 2026.xls"]
        if yr == "2026" and key > "2026-07":
            out += [None, tb[key].get(code, 0), None]; continue
        ytd = sum(r["cr"] - r["dr"] for r in src if r["date"][:7] <= key and r["date"][:4] == yr)
        out += [ytd, tb[key].get(code, 0), ytd - tb[key].get(code, 0)]
    f = lambda x: f"{x:15,.2f}" if x is not None else f"{'n/a':>15}"
    g = lambda x: f"{x:12,.2f}" if x is not None else f"{'n/a':>12}"
    print(f"{out[0]:8} | {f(out[1])} {f(out[2])} {g(out[3])} | {f(out[4])} {f(out[5])} {g(out[6])}")

# match each 2025 spool credit to a fee debit on a loan account in P1_01 (same date, same amount)
def ymd(s):
    m = re.match(r"^(\d{1,2})/(\d{1,2})/(\d{4})", s.strip()); return f"{m.group(3)}-{int(m.group(1)):02d}-{int(m.group(2)):02d}" if m else s[:10]
L = list(csv.DictReader(io.open(os.path.join(base, "Raw Query Scripts/Query Requests to MAIIC/Follow-Up Scripts Resutls/P1_01_ledger_all.csv"), encoding="utf-8", errors="replace", newline="")))
pool = collections.defaultdict(list)
for r in L:
    pool[(ymd(r["TRANSACTION_DATE"]), round(abs(float(r["TRANSAMT"] or 0)), 2))].append(r)

print("\nEach 2025 and 2026 spool line, matched to a loan-account posting in P1_01 (same date and amount)")
summary = collections.defaultdict(lambda: [0, 0.0, 0, 0.0])
for name in ("GL 4871 as at 31 Dec 2025.xls", "GL 4873 as at 31 Dec 2025.xls", "GL 4871 as at 31 July 2026.xls", "GL 4873 as at 31 July 2026.xls"):
    print("==", name)
    for r in spools[name]:
        amt = r["cr"] or r["dr"]
        hit = pool.get((r["date"], round(amt, 2)))
        acct = hit[0]["NEW_AC_NUMBER"] + " " + hit[0]["TRANTYPE"] + "/" + hit[0]["BATCH_TYPE"] if hit else "-- no loan-account leg in P1_01"
        k = (name[:7], name[-13:-4])
        if hit: summary[k][0] += 1; summary[k][1] += r["cr"] - r["dr"]
        else: summary[k][2] += 1; summary[k][3] += r["cr"] - r["dr"]
        print(f"  {r['date']}  {('DR ' if r['dr'] else '')}{amt:>15,.2f}  {r['part'][:55]:55s}  {acct}")
print("\nSummary (matched lines, value | unmatched lines, value)")
for k, v in summary.items():
    print(f"  {k[0]} {k[1]}: matched {v[0]} = {v[1]:,.2f} | not in P1_01 {v[2]} = {v[3]:,.2f}")
