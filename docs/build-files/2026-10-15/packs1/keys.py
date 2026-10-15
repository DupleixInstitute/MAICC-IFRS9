import csv, os
P = r"C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4"
def read(rel):
    raw = open(os.path.join(P, rel), 'rb').read()
    t = raw[3:].decode('utf-8') if raw.startswith(b'\xef\xbb\xbf') else (raw.decode('utf-8', 'strict') if True else '')
    return t
def rows(rel):
    raw = open(os.path.join(P, rel), 'rb').read()
    try:
        t = raw[3:].decode('utf-8') if raw.startswith(b'\xef\xbb\xbf') else raw.decode('utf-8')
    except UnicodeDecodeError:
        t = raw.decode('cp1252')
    rd = list(csv.reader([l for l in t.splitlines() if l.strip()]))
    h = [c.strip() for c in rd[0]]
    d = h[0] == ''
    if d: h = h[1:]
    return [dict(zip(h, r[1:] if d else r)) for r in rd[1:]]
def uniq(files, cols):
    seen = {}; dup = 0; n = 0
    for f in files:
        for r in rows(f):
            k = tuple(r.get(c, '').strip() for c in cols); n += 1
            if k in seen: dup += 1
            seen[k] = 1
    print(files[0][:40], cols, 'rows', n, 'dups', dup)
uniq(["Pack 5/Export Results/TB_04_6730_history_jul_aug.csv"], ['GLCODE', 'TRANSACTION_DATE'])
uniq(["Pack 6/Export Results/TB_05_6730_history_jan_jun.csv"], ['GLCODE', 'TRANSACTION_DATE'])
uniq(["Pack 6/Export Results/CV_03_ledger_copies_by_year.csv"], ['TABLE_NAME', 'YEAR'])
uniq(["Pack 8/Export Results/CV_06_migration_tables_probe.csv"], ['OWNER', 'TABLE_NAME'])
uniq(["Pack 8/Export Results/CV_07_account_mapping_columns_probe.csv"], ['OWNER', 'TABLE_NAME', 'COLUMN_NAME'])
uniq(["Pack 5/Export Results/CV_02_ledger_tables_probe.csv"], ['OWNER', 'TABLE_NAME'])
a = ["Pack 7/Export Results/CV_04a1_bk240824_2018_2022.csv", "Pack 7/Export Results/CV_04a2_bk240824_2023.csv", "Pack 7/Export Results/CV_04a3_bk240824_2024.csv"]
b = ["Pack 7/Export Results/CV_04b1_bak_2000_2021.csv", "Pack 7/Export Results/CV_04b2_bak_2022.csv", "Pack 7/Export Results/CV_04b3_bak_2023_2024.csv"]
uniq(a, ['CUMVOUCH_DET_ID'])
uniq(b, ['CUMVOUCH_DET_ID'])
# pack 8 truncated copies are subsets of pack 7
for f7, f8 in [(b[0], "Pack 8/Export Results/CV_04b1_bak_2000_2021.csv"), (b[1], "Pack 8/Export Results/CV_04b2_bak_2022.csv")]:
    s7 = {tuple(sorted(r.items())) for r in rows(f7)}
    r8 = rows(f8)
    print(f8[-28:], len(r8), 'all in pack 7:', all(tuple(sorted(r.items())) in s7 for r in r8), 'missing', len(s7) - len(r8))
for col in ['TRANSACTION_DATE']:
    for f in a + b:
        v = [r[col] for r in rows(f)]
        print(f[-30:], min(v, key=len), max(v, key=len), sum(1 for x in v if not x))
print('TB_04 dates', [r['TRANSACTION_DATE'] for r in rows("Pack 5/Export Results/TB_04_6730_history_jul_aug.csv")][:3])
print('GL_06d', rows("Pack 5/Export Results/GL_06d_6730_opening_balances.csv")[0]['FINYEARSTARTDATE'])
print('CV_05a', [ (r['NEW_AC_NUMBER'], r.get('ACCOUNT_OPEN_DATE')) for r in rows("Pack 8/Export Results/CV_05a_old_accounts_master.csv")])
print('CV_02 LAST_ANALYZED', [r['LAST_ANALYZED'] for r in rows("Pack 5/Export Results/CV_02_ledger_tables_probe.csv")][:3])
