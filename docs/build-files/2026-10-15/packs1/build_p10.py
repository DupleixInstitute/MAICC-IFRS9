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


e10 = P4 + "/Pack 10/Export Results/"
pack('ebanker-pack-2026-10-14-p10', {
    'pack': 'MAIIC E-Banker pack 10, received 14 October 2026',
    'assembled': '2026-10-14', 'period': '2026-10',
    'source': SRCLINE.format(n=10, d='14 Oct 2026').replace('schema VGCBS', 'schemas VGCBS and MAIIC_ORA_LIVE'), 'date_format': 'm/d/Y', 'scope_scheme_ids': [],
    'accepted_exceptions': [
        RUN0,
        "OD_02 continues the overdue run's daily rows of pack 9 (2025 Q3 to 2026 Q1) with 2026 Q2, Q3 and 1 to 12 October 2026; the keys (OVERDUE_DET_ID) do not overlap any pack 9 file; each part equals the pack 10 profile (OD_00) for its months",
        "RF_01 is a slice of the live ledger (every leg of the batches that paid off a loan from a new one); it has its own query id outside the LEDGER family, so no engine reads it twice",
    ],
}, [
    (e10 + "OD_02_overdue_daily_2026Q2.csv", 'OD_02', "pack 10 RUN 2a: OVERDUE_DETAILS daily rows of April to June 2026 (34 columns); 10,721 rows = the profile's 3,520 + 3,639 + 3,562"),
    (e10 + "OD_02_overdue_daily_2026Q3.csv", 'OD_02', "pack 10 RUN 2b: OVERDUE_DETAILS daily rows of July to September 2026; 9,589 rows = 3,193 + 3,216 + 3,180"),
    (e10 + "OD_02_overdue_daily_2026Q4.csv", 'OD_02', "pack 10 RUN 2c: OVERDUE_DETAILS daily rows of 1 to 12 October 2026; 1,272 rows"),
    (e10 + "RF_01_refinance_batch_legs.csv", 'RF_01', "pack 10 RUN 4: every leg of the batches that paid off a loan from a new one; links The Food Empire 000104450000068 to 000106050000003 (24 Nov 2025, DISB 20) and Malawi Police SACCO 000104430000072 to 000106050000006 (March 2026 reallocation); no link for JVD Agro"),
], [
    (e10 + "OD_00_overdue_profile.csv", "the profile of the overdue run (23 rows, one per calculation month, December 2024 to 12 October 2026): the control counts of OD_01 and OD_02, read, not landed, as in pack 9"),
    (e10 + "CV_15_crm_acmaster_05082024.csv", "CRM_ACMASTER_05082024 holds 102 vendor test or training accounts (101 on GL 2563, one on GL 2136), not loans; it cannot map the old 00010075 loan accounts"),
    (e10 + "DD_17_login_and_synonyms.csv", "a probe: the extract login is the schema owner VGCBS, no synonyms (and three PROC_BUILD_MAIIC_* procedures listed)"),
    (e10 + "DD_18_read_access.csv", "a probe: read access per table (32 of 35 return a row; the three empty ones are empty, not closed)"),
    (e10 + "DD_20_link_columns_probe.csv", "a probe of column names: E-Banker keeps no refinance-link column"),
    (e10 + "TB_06_stored_tb_balance_check.csv", "a rebuild check: the stored trial balance is still 88,000.00 out of balance on every day from 15 May 2026 (posting 368352 not yet rebuilt by the vendor)"),
    (e10 + "ZF_04_zaithwa_balance_rows_recheck.csv", "a rebuild check: the Zaithwa Farms ACCOUNT_BALANCE rows are unchanged from pack 1 (the vendor rebuild is still owed; the ledger is used, accepted exception of pack 1)"),
])
