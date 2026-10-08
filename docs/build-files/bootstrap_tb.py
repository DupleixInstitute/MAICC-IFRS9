"""docs/bootstrap/trial-balances: the monthly TBs, the December 2025 AFS bridge (both received versions) and the GL-to-AFS mapping, with manifest entries."""
import hashlib, json, os, shutil, re
B = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients"
DST = r"C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap"
new = os.path.join(DST, "trial-balances")
if os.path.isdir(new): shutil.rmtree(new)
for d in ("monthly", os.path.join("afs-bridge-2025-12", "2026-08-19"), os.path.join("afs-bridge-2025-12", "2026-09-10"), "afs-mapping-2023-to-2026-08"):
    os.makedirs(os.path.join(new, d), exist_ok=True)
def sha(p):
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for b in iter(lambda: f.read(1 << 20), b""): h.update(b)
    return h.hexdigest()
MON = {m: i for i, m in enumerate(["January","February","March","April","May","June","July","August","September","October","November","December"], 1)}
entries = []
def add(src, parts, qid, kind):
    dst = os.path.join(new, *parts); shutil.copyfile(src, dst)
    entries.append({"file": "trial-balances/" + "/".join(parts), "query_id": qid, "kind": kind, "rows": None, "sha256": sha(dst), "bytes": os.path.getsize(dst)})
TB = os.path.join(B, "Trial Balances")
for f in sorted(os.listdir(TB)):
    m = re.match(r"Trial Balance_(\d+) (\w+) (\d{4})\.xls$", f)
    if m: add(os.path.join(TB, f), ["monthly", f], f"TB_{m.group(3)}-{MON[m.group(2)]:02d}", "monthly trial balance as received from Finance (P&L cumulative year-to-date); Jan 2025 to Jul 2026 received 19 Aug 2026, Aug 2026 received 15 Sep 2026")
AFS = "AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx"
add(os.path.join(B, "New Doc Received 19 Aug", "Dupleix 2026", AFS), ["afs-bridge-2025-12", "2026-08-19", AFS], "TB_AFS_2025-12_v1", "AFS bridge, December 2025, as received 19 Aug 2026: sheet 'Final E-Banker TB Dec 2025' holds amounts as text with thousands separators; superseded by the 10 Sep 2026 version for loading")
add(os.path.join(B, "AFS 2025", AFS), ["afs-bridge-2025-12", "2026-09-10", AFS], "TB_AFS_2025-12", "AFS bridge, December 2025, as received 10 Sep 2026: 'FINAL AFS TB as of 19th Mar 26', 'Initial TB Submitted to Auditor', 'TB December 2025', 'Final E-Banker TB Dec 2025' (amounts numeric). The mapped December 2025 TB that agrees to the audited accounts; the version the importer reads")
MAP = "Mapping TBs August 2026 to Audited Financial Statements4.xlsx"
add(os.path.join(B, "New Doc Received 21 Sep", MAP), ["afs-mapping-2023-to-2026-08", MAP], "TB_AFS_MAP", "every GL line mapped to its audited-accounts category, with year-end balances Dec 2023, Dec 2024, Dec 2025 and Aug 2026 (sheets 'Mapping TBs', 'Income Statement 2022 to Aug26', 'Balance Sheet 2022 to Aug 2026'); received 21 Sep 2026, saved 24 Sep; the [TB] of spec v3 F17")
m = json.load(open(os.path.join(DST, "manifest.json"), encoding="utf-8"))
m["files"] = [f for f in m["files"] if not f["file"].startswith("trial-balances")] + entries
json.dump(m, open(os.path.join(DST, "manifest.json"), "w", encoding="utf-8"), indent=2)
r = open(os.path.join(DST, "README.md"), encoding="utf-8").read()
r = re.sub(r"\| `trial-balances[^\n]*\n", "", r)
r = r.replace("| `queries/` |",
    "| `trial-balances/monthly/` | The 20 monthly trial balances, January 2025 to August 2026, as received from Finance | `eir:bootstrap` step 3 (landed as `ebanker_trial_balances`; the GL side of the reconciliation is derived from them) |\n"
    "| `trial-balances/afs-bridge-2025-12/` | The December 2025 AFS bridge in its two received versions (19 Aug 2026, amounts as text; 10 Sep 2026, amounts numeric, the one that is loaded): final AFS TB, initial TB to the auditor, TB December 2025, final E-Banker TB mapped | step 3; sheet 'Final E-Banker TB Dec 2025' |\n"
    "| `trial-balances/afs-mapping-2023-to-2026-08/` | Every GL line mapped to its audited-accounts category with year-end balances Dec 2023, Dec 2024, Dec 2025 and Aug 2026 | step 3; the category bridge to the audited accounts (F17) |\n| `queries/` |", 1)
open(os.path.join(DST, "README.md"), "w", encoding="utf-8").write(r)
print(len(entries), "TB entries; manifest", len(m["files"]), "files;", round(sum(e["bytes"] for e in entries)/1e6, 1), "MB")
