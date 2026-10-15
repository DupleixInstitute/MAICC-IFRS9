"""Assemble packs 5 to 8 under docs/bootstrap from the files as received (byte-identical copies),
each with a manifest.json in the house format, and record every received file that is not landed."""
import os, io, json, hashlib, shutil, csv, sys

SRC = r"C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC"
P4 = "Received 12 October 2026/Pack 4"
OUT = r"C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap"
DRY = '--dry' in sys.argv

CLASSIFICATION = "MAIIC client data: borrower names and balances. Private repository; access as for the production database."


def text_rows(path):
    raw = open(path, 'rb').read()
    try:
        t = raw[3:].decode('utf-8') if raw.startswith(b'\xef\xbb\xbf') else raw.decode('utf-8')
    except UnicodeDecodeError:
        t = raw.decode('cp1252')
    # quote-aware, as PackLandingService::readCsv (a quoted value may hold a line break: CV_04a3)
    recs = [r for r in csv.reader(io.StringIO(t, newline='')) if r != [] and ''.join(r).strip() != '' or (r != [] and len(r) > 1)]
    return len(recs) - 1, raw


def pack(folder, meta, files, not_landed):
    d = os.path.join(OUT, folder)
    entries = []
    for rel, qid, kind in files:
        src = os.path.join(SRC, rel)
        n, raw = text_rows(src)
        name = os.path.basename(rel)
        entries.append({'file': name, 'query_id': qid, 'kind': kind, 'rows': n,
                        'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw), 'received_as': rel})
        if not DRY:
            os.makedirs(d, exist_ok=True)
            shutil.copy2(src, os.path.join(d, name))
    m = dict(meta)
    m['not_landed'] = [{'received_as': r, 'reason': why} for r, why in not_landed]
    m['classification'] = CLASSIFICATION
    m['files'] = entries
    for r, _ in not_landed:
        assert os.path.exists(os.path.join(SRC, r)) or '*' in r, r
    if not DRY:
        with open(os.path.join(d, 'manifest.json'), 'w', encoding='utf-8', newline='\n') as f:
            f.write(json.dumps(m, indent=2, ensure_ascii=False) + '\n')
    for e in entries:
        print(folder, e['file'], e['query_id'], e['rows'], e['bytes'])


RUN0 = "RUN 0 not applied: every date is m/d/yyyy; the manifest declares the format (D23)"
SRCLINE = "E-Banker (Virtual Galaxy CBS, schema VGCBS), run by Barry Makumba in SQL Developer from FOLLOW-UP extracts for Barry - pack {n} - {d}.sql; RUN 0 was not applied, so dates are exported as m/d/yyyy; amounts signed, debits negative"

# ---- pack 5 ----------------------------------------------------------------
e5 = P4 + "/Pack 5/Export Results/"
pack('ebanker-pack-2026-10-12-p5', {
    'pack': 'MAIIC E-Banker pack 5, received 12 October 2026',
    'assembled': '2026-10-12', 'period': '2026-09',
    'source': SRCLINE.format(n=5, d='12 Oct 2026'), 'date_format': 'm/d/Y', 'scope_scheme_ids': [],
    'accepted_exceptions': [
        RUN0,
        "Every pack 5 query has its own query id, outside the ledger, trial balance and charges families the engines read: GL_04c repeats the 205 GL_04a fee legs with all columns, GL_06a, GL_06b and GL_07 are slices of the live ledger already landed in P1_01 and M09_01, and TB_04 slices E-Banker's stored trial balance (TB_03); landing them under their own ids keeps every engine figure unchanged",
        "GL_06b returned no rows (no posting of exactly 88,000 in July and August 2026); it is landed as an empty file so the load records that the ledger was searched",
        "P2_13b carries no account column: it is joined to P2_13 on LOS_REQ_ID",
    ],
}, [
    (e5 + "P2_13b_los_requests_text_part2.csv", 'P2_13b', "pack 5 RUN 1: pieces 3 and 4 of the 64 LOS request payloads longer than two pieces (P2_13 of pack 4 holds pieces 1 and 2); joined on LOS_REQ_ID, 64 requests complete; 34 of the pieces mention a fee"),
    (e5 + "GL_06a_6730_postings_jun_sep.csv", 'GL_06a', "pack 5 RUN 2a: the postings to GL 6730 (Fines Expenses) from June to September 2026 with their audit columns (the 88,000 question)"),
    (e5 + "GL_06b_88000_postings.csv", 'GL_06b', "pack 5 RUN 2b: every posting of exactly 88,000 in July and August 2026; none"),
    (e5 + "TB_04_6730_history_jul_aug.csv", 'TB_04', "pack 5 RUN 2c: E-Banker's stored daily trial balance rows for GL 6730, 25 June to 5 August 2026"),
    (e5 + "GL_06d_6730_opening_balances.csv", 'GL_06d', "pack 5 RUN 2d: the year-opening balance rows of GL 6730 (1 January 2024)"),
    (e5 + "GL_04c_fee_gl_legs_all_columns.csv", 'GL_04c', "pack 5 RUN 3a: the 205 fee income GL legs of GL_04a with all 83 ledger columns; ties to GL_04a; none of the columns carries a loan account on the 107 unlinked fee lines"),
    (e5 + "GL_07_gl2010_batch_legs.csv", 'GL_07', "pack 5 RUN 3b: the 19 legs of the 6 batches touching GL 2010 (Working Capital Loan) since 2024; ties to its control count (CHECK_GL_07); links three October 2024 fees (Mafeni, Agwenda x2) to their loan through GL 2010"),
    (e5 + "CV_02_ledger_tables_probe.csv", 'CV_02', "pack 5 RUN 4: the 134 tables whose names match the ledger-copy patterns, with the statistics row counts of 13 June 2026; names and counts only"),
], [
    (e5 + "CHECK_GL_07_row_counts.csv", "check file (the row count of RUN 3b, 19, which GL_07 matches); never landed, as the pack 4 CHECK files (spec v4 6.4)"),
])

# ---- pack 6 ----------------------------------------------------------------
e6 = P4 + "/Pack 6/Export Results/"
pack('ebanker-pack-2026-10-12-p6', {
    'pack': 'MAIIC E-Banker pack 6, received 12 October 2026',
    'assembled': '2026-10-12', 'period': '2026-09',
    'source': SRCLINE.format(n=6, d='12 Oct 2026'), 'date_format': 'm/d/Y', 'scope_scheme_ids': [],
    'accepted_exceptions': [
        RUN0,
        "GL_08a is a slice of the live ledger (P1_01) and TB_05 of E-Banker's stored trial balance (TB_03); each has its own query id outside the families the engines read, so no engine figure moves",
    ],
}, [
    (e6 + "GL_08a_6730_postings_jan_jun.csv", 'GL_08a', "pack 6 RUN 1a: the 22 postings to GL 6730 from 1 January to 25 June 2026 with their audit columns; finds posting 368352 (88,000, 15 May 2026, batch TRF 4) that the stored trial balance history left out"),
    (e6 + "TB_05_6730_history_jan_jun.csv", 'TB_05', "pack 6 RUN 1b: E-Banker's stored daily trial balance rows for GL 6730, 2 January to 25 June 2026"),
    (e6 + "CV_03_ledger_copies_by_year.csv", 'CV_03', "pack 6 RUN 2: postings and accounts by year in each of the eight ledger copies (CUMVOUCH_BK240824, CUMVOUCH_BAK, CUMVOUCH_NF, CUMVOUCH_CHET13062025 and the small working copies); the control counts for pack 7"),
], [])

# ---- pack 7 ----------------------------------------------------------------
e7 = P4 + "/Pack 7/Export Results/"
pack('ebanker-pack-2026-10-13-p7', {
    'pack': 'MAIIC E-Banker pack 7, received 13 October 2026',
    'assembled': '2026-10-13', 'period': '2026-09',
    'source': SRCLINE.format(n=7, d='12 Oct 2026'), 'date_format': 'm/d/Y', 'scope_scheme_ids': [],
    'accepted_exceptions': [
        RUN0,
        "CV_04a (CUMVOUCH_BK240824, a copy of the ledger taken on 24 August 2024) and CV_04b (CUMVOUCH_BAK, an older copy under the earlier chart) are the ledger before the July 2024 migration. They land under their own query ids, outside the LEDGER family (LandingZoneReader::LEDGER), so the loan book, the EIR, the staging and the ECL read none of them; their posting ids come from other sequences than the live CUMVOUCH and must never be merged on id with it",
        "Each copy arrives in three parts by year (a1 to a3, b1 to b3); the keys do not overlap across the parts of one copy; each part's rows equal the pack 6 count by year (CV_03)",
        "CV_04a loan GLs net to the 1 January 2024 GL opening balances of CV_14 (pack 9) within 0.50 (pack 9 results review)",
    ],
}, [
    (e7 + "CV_04a1_bk240824_2018_2022.csv", 'CV_04a', "pack 7 RUN 1a: CUMVOUCH_BK240824 (ledger copy of 24 August 2024), postings 2018 to 2022, all 84 columns; 3,530 rows as counted in CV_03"),
    (e7 + "CV_04a2_bk240824_2023.csv", 'CV_04a', "pack 7 RUN 1b: CUMVOUCH_BK240824, postings of 2023; 2,083 rows as counted in CV_03"),
    (e7 + "CV_04a3_bk240824_2024.csv", 'CV_04a', "pack 7 RUN 1c: CUMVOUCH_BK240824, postings 1 January to 15 August 2024; 6,851 rows as counted in CV_03"),
    (e7 + "CV_04b1_bak_2000_2021.csv", 'CV_04b', "pack 7 RUN 2a: CUMVOUCH_BAK (older ledger copy, loan GLs 1050, 1052, 1053), postings 2000 to 2021; 7,019 rows as counted in CV_03 (the complete pack 7 copy)"),
    (e7 + "CV_04b2_bak_2022.csv", 'CV_04b', "pack 7 RUN 2b: CUMVOUCH_BAK, postings of 2022; 5,639 rows as counted in CV_03 (the complete pack 7 copy)"),
    (e7 + "CV_04b3_bak_2023_2024.csv", 'CV_04b', "pack 7 RUN 2c: CUMVOUCH_BAK, postings 3 January 2023 to 27 March 2024; 7,122 rows as counted in CV_03"),
], [])

# ---- pack 8 ----------------------------------------------------------------
e8 = P4 + "/Pack 8/Export Results/"
pack('ebanker-pack-2026-10-13-p8', {
    'pack': 'MAIIC E-Banker pack 8, received 13 October 2026',
    'assembled': '2026-10-13', 'period': '2026-09',
    'source': SRCLINE.format(n=8, d='13 Oct 2026'), 'date_format': 'm/d/Y', 'scope_scheme_ids': [],
    'accepted_exceptions': [
        RUN0,
        "CV_05a and CV_05b read the account and loan masters for the old 00010075 block of CUMVOUCH_BAK; they land under their own query ids, outside the ACCOUNT_MASTER and LOAN_MASTER families, so no build reads them",
        "CV_05b returned no rows (none of the old loans is in ACLOANMASTER); it is landed as an empty file so the load records that the table was searched",
        "CV_06 and CV_07 are probes of table and column names (no client data); landed for the record of where the migration working is kept",
    ],
}, [
    (e8 + "CV_05a_old_accounts_master.csv", 'CV_05a', "pack 8 RUN 1a: ACMASTER rows asked for the old 00010075 block and two control accounts: 3 rows, all control accounts (000100750000001 Term Loans, 000101270000001 GL 1053, 000101350000001 GL 1052), no borrower"),
    (e8 + "CV_05b_old_accounts_loan_master.csv", 'CV_05b', "pack 8 RUN 1b: ACLOANMASTER rows for the same accounts; none"),
    (e8 + "CV_06_migration_tables_probe.csv", 'CV_06', "pack 8 RUN 2: the 53 tables that may hold the migration working (opening balances, old ledgers, account maps), with the statistics row counts of 13 June 2026; names only"),
    (e8 + "CV_07_account_mapping_columns_probe.csv", 'CV_07', "pack 8 RUN 3: the 47 columns that may map old account numbers to new (ACCOUNT_NO_MAPP, NEW_ACMAP, T_ACCOUNT, TEMP_MASTER and others); names only"),
], [
    (e8 + "CV_04a1_bk240824_2018_2022.csv", "byte-identical to the pack 7 file (same SHA-256), which is landed"),
    (e8 + "CV_04a2_bk240824_2023.csv", "byte-identical to the pack 7 file (same SHA-256), which is landed"),
    (e8 + "CV_04a3_bk240824_2024.csv", "byte-identical to the pack 7 file (same SHA-256), which is landed"),
    (e8 + "CV_04b3_bak_2023_2024.csv", "byte-identical to the pack 7 file (same SHA-256), which is landed"),
    (e8 + "CV_04b1_bak_2000_2021.csv", "truncated copy: 5,980 rows against 7,019 in pack 7; every row it holds is in the pack 7 file with the same values (an export that stopped early); the complete pack 7 file is landed"),
    (e8 + "CV_04b2_bak_2022.csv", "truncated copy: 4,465 rows against 5,639 in pack 7; every row it holds is in the pack 7 file with the same values; the complete pack 7 file is landed"),
])
