"""Commit pack 9's two data files as a bootstrap pack folder with a manifest (hashes, rows, provenance)."""
import csv, hashlib, json, os, shutil

SRC = r"C:\Users\DELL\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Received 12 October 2026\Pack 4\Pack 9\Export Results"
DST = r"C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap\ebanker-pack-2026-10-14"
REL = "Received 12 October 2026/Pack 4/Pack 9/Export Results/"

LAND = [
    ("CV_10_opening_bal.csv", "CV_10", "pack 9 RUN 3: OPENING_BAL, the July 2024 migration worksheet (78 term loans taken on at 31 July 2024 plus 17 GL 2000 supplier balances); per loan the sanction, disbursement, restructure date and amount, rate, tenor, balance, interest charged and repaid, overdue amounts and NEWDISBURS_AMT; ties to the 301/303/305 take-on postings on 76 of 77 loans (pack 9 results review)"),
    ("CV_14_gl_opening_balance_bk240824.csv", "CV_14", "pack 9 RUN 7: GL_OPENING_BALANCE_BK240824, the GL opening balances of financial year 2024 (1 January 2024); the loan GLs tie to the pack 7 ledger copy CUMVOUCH_BK240824 to 31 December 2023 within 0.50"),
]
NOT_LANDED = [
    ("CV_08_table_counts.csv", "row counts of the pack 9 tables (RUN 1); a probe, not data"),
    ("CV_09a_account_no_mapp.csv", "ACCOUNT_NO_MAPP is empty: E-Banker keeps no old-to-new account map there"),
    ("CV_09b_new_acmap.csv", "NEW_ACMAP is empty"),
    ("CV_11a_t_account.csv", "T_ACCOUNT is empty"),
    ("CV_11b_t_accountbalance.csv", "T_ACCOUNTBALANCE is empty"),
    ("CV_12a_old_block_g_acmaster.csv", "no row of the 00010075 block in G_ACMASTER"),
    ("CV_12b_old_block_acmaster_mw.csv", "one row: 000100750000001, an expense account (GL 6000), not a loan"),
    ("CV_12c_old_block_acmaster_nf.csv", "ACMASTER_NF is empty"),
    ("CV_12d_acmaster_05082024_loans.csv", "no row matched the loan GLs or the old block; follow-up RUN 5d-2 asked"),
    ("CV_12e_temp_master.csv", "TEMP_MASTER is empty"),
    ("CV_13_cumovuch_old_profile.csv", "a count profile of CUMOVUCH_OLD (13,359 rows, 2018-2022) under account blocks that match no loan; the table itself is not requested"),
    ("OD_01_overdue_details.csv", "a 21-row fragment (SQL Developer stopped at 100 MB); RUN 8 was split into 8a, 8b and 8c, which supersede it"),
]

os.makedirs(DST, exist_ok=True)
files = []
for name, qid, kind in LAND:
    src = os.path.join(SRC, name)
    data = open(src, "rb").read()
    with open(src, encoding="utf-8", errors="replace", newline="") as fh:
        rows = sum(1 for _ in csv.reader(fh)) - 1
    shutil.copyfile(src, os.path.join(DST, name))
    files.append({"file": name, "query_id": qid, "kind": kind, "rows": rows,
                  "sha256": hashlib.sha256(data).hexdigest(), "bytes": len(data), "received_as": REL + name})

manifest = {
    "pack": "MAIIC E-Banker pack 9, received 14 October 2026",
    "assembled": "2026-10-14",
    "period": "2026-09",
    "source": "E-Banker (Virtual Galaxy CBS, schemas VGCBS and MAIIC_ORA_LIVE), run by Barry Makumba in SQL Developer from FOLLOW-UP extracts for Barry - pack 9 - 13 Oct 2026.sql, uploaded to OneDrive by Vanessa Magaleta on 14 October 2026 at 09:09; RUN 0 was not applied, so dates are exported as m/d/yyyy; amounts signed, debits negative",
    "date_format": "m/d/Y",
    "scope_scheme_ids": [],
    "accepted_exceptions": [
        "RUN 0 not applied: every date is m/d/yyyy; the manifest declares the format (D23)",
        "CV_10 and CV_14 are migration-time references (31 July 2024 and 1 January 2024); they are not monthly feed queries and carry no row date the ledger readers use",
    ],
    "not_landed": [{"received_as": REL + n, "reason": r} for n, r in NOT_LANDED],
    "classification": "MAIIC client data: borrower names and balances. Private repository; access as for the production database.",
    "files": files,
}
json.dump(manifest, open(os.path.join(DST, "manifest.json"), "w", encoding="utf-8"), indent=2)
print(json.dumps([(f["file"], f["rows"]) for f in files]))
