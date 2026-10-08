"""Assemble docs/bootstrap/ in MAICC-IFRS9: the 7 October E-Banker pack, the take-on workbook and the dictionary results, with a manifest."""
import hashlib, json, os, shutil, csv, io, datetime
SRC = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts"
Q = SRC + r"\Query Requests to MAIIC"
DST = r"C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap"

def sha(p):
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for b in iter(lambda: f.read(1 << 20), b""): h.update(b)
    return h.hexdigest()

def rows(p):
    with open(p, "rb") as f: return max(sum(1 for _ in f) - 1, 0)

def copy_set(files, sub, kind):
    out = []
    os.makedirs(os.path.join(DST, sub), exist_ok=True)
    for src in files:
        name = os.path.basename(src); dst = os.path.join(DST, sub, name)
        shutil.copyfile(src, dst)
        qid = name.split("_")[0] + ("_" + name.split("_")[1] if name[:2] in ("DD", "GL", "ZF", "P1", "P2", "P3") and len(name.split("_")) > 1 else "")
        qid = name[:5] if name[:2] in ("P1", "P2", "P3") else (name[:5] if name[:2] in ("GL", "ZF") else (name.split("_")[0] + "_" + name.split("_")[1] if name.startswith("DD") else name))
        out.append({"file": f"{sub}/{name}", "query_id": qid, "kind": kind, "rows": rows(dst) if name.endswith(".csv") else None, "sha256": sha(dst), "bytes": os.path.getsize(dst)})
    return out

R = Q + r"\Follow-Up Scripts Resutls"
pack = sorted(os.path.join(R, f) for f in os.listdir(R) if f.endswith(".csv"))
gl = sorted(os.path.join(R, "GL and ZF loan", f) for f in os.listdir(os.path.join(R, "GL and ZF loan")) if f.endswith(".csv") and (f.startswith("GL") or f.startswith("ZF")))
dd_dir = SRC + r"\Query Request Responses 6 Oct 2026\Dupleix\Dupleix"
dd = sorted(os.path.join(dd_dir, f) for f in os.listdir(dd_dir) if f.endswith(".csv"))
wbk = [Q + r"\Take-on schedules with mapping - with fee columns - for Tamanda to confirm - 7 Oct 2026.xlsx"]
sql = [Q + r"\FOLLOW-UP extracts for Barry - 6 Oct 2026.sql", Q + r"\MAIIC_EBanker_Data_Dictionary_Queries.sql"]

if os.path.isdir(DST): shutil.rmtree(DST)
files = []
files += copy_set(pack, "ebanker-pack-2026-10-07", "follow-up extract, run 7 Oct 2026 by 10:00")
files += copy_set(gl, "ebanker-pack-2026-10-07", "follow-up query of 7 Oct 2026 afternoon (GL openings, Zaithwa Farms)")
files += copy_set(dd, "data-dictionary-2026-10-06", "data-dictionary result, run 6 Oct 2026")
files += copy_set(wbk, "takeon", "take-on amortisation schedules mapped to E-Banker accounts; fee columns AO:AU for Finance to complete")
files += copy_set(sql, "queries", "the SQL that produced the pack; version 1")
manifest = {
    "pack": "MAIIC E-Banker bootstrap inputs",
    "assembled": datetime.date.today().isoformat(),
    "source": "E-Banker (Virtual Galaxy CBS, schema VGCBS), run by Barry Makumba in SQL Developer; dates exported as m/d/yyyy (the RUN 0 ISO settings were not applied on this first pack); amounts signed, debits negative",
    "scope_scheme_ids": [84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145],
    "accepted_exceptions": [
        "GL 1050201 trial balance +400,000.00 and 1050202 -1,000,000.00 against the accounts: keyed GL opening balances, Finance to correct (spec v4 s.3.5)",
        "Account 000104450000015 Zaithwa Farms: balance-history rows wrong from May 2025 (duplicate 30 Apr 2025 row); ledger correct; vendor to rebuild (spec v4 s.3.5)",
        "P2_06 PLR master: use this (morning) export; the afternoon re-export lost the date on PLR id 2",
        "P1_05 interest summary: latest month only (Aug 2026); the table holds no history",
    ],
    "classification": "MAIIC client data: borrower names and balances. Private repository; access as for the production database.",
    "files": files,
}
with open(os.path.join(DST, "manifest.json"), "w", encoding="utf-8") as f: json.dump(manifest, f, indent=2)
readme = """# Bootstrap inputs

The client input files a clean install of MAICC-IFRS9 is bootstrapped from, committed so that a
server builds the whole EIR data foundation without an external file (spec v4, section 6.11).
The committed copy is read first; a OneDrive path is only a local fallback.

| Folder | What | Loaded by |
|---|---|---|
| `ebanker-pack-2026-10-07/` | The 18 follow-up extracts of 7 October 2026 and the 5 afternoon queries: the ledger, masters, rates, PLR, charges, loan-book history, balance history, charts, plans, schedules, status history, daily accrual | `eir:bootstrap` step 3 (route 1 pack into the landing zone) |
| `data-dictionary-2026-10-06/` | The 24 data-dictionary results of 6 October 2026 (tables, columns, keys, schemes, codes, row counts, samples) | reference; `DD_09` schemes read for product names |
| `takeon/` | The take-on amortisation schedules mapped to E-Banker accounts, with the fee columns for Finance | `eir:bootstrap` step 4 (take-on build) |
| `queries/` | The SQL that produced the pack, version 1 | the E-Banker Feed's query register |
| `manifest.json` | Every file with its query id, row count and SHA-256; the accepted exceptions the gates allow | the gates |

Every file is exactly as received. None is opened or re-saved in Excel. A replacement (for example the
workbook returned by Finance with the fees filled in) is a new file beside the old one and a new manifest
entry; nothing here is edited in place.

Dates in this first pack are m/d/yyyy, as the export tool wrote them; the gates parse that format for this
pack by its manifest, and ISO for every later pack produced with the RUN 0 session settings.
"""
with open(os.path.join(DST, "README.md"), "w", encoding="utf-8") as f: f.write(readme)
print(len(files), "files;", sum(x["bytes"] for x in files) / 1e6, "MB")
