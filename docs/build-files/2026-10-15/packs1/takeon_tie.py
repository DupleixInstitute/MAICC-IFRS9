import csv, collections
R = r"C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/"
def rows(p):
    raw = open(p, 'rb').read()
    try:
        t = raw[3:].decode('utf-8') if raw.startswith(b'\xef\xbb\xbf') else raw.decode('utf-8')
    except UnicodeDecodeError:
        t = raw.decode('cp1252')
    rd = list(csv.reader([l for l in t.splitlines() if l.strip()]))
    h = [c.strip() for c in rd[0]]; d = h[0] == ''
    if d: h = h[1:]
    return [dict(zip(h, r[1:] if d else r)) for r in rd[1:]]
num = lambda s: float((s or '0').replace(',', '') or 0)
led = rows(R + 'ebanker-pack-2026-10-07/P1_01_ledger_all.csv')
print(led[0].keys())
post = collections.defaultdict(lambda: collections.defaultdict(float))
dates = collections.Counter()
for r in led:
    if r['TRANTYPE'] in ('301', '303', '305') and r.get('DELETE_FLAG', 'N') != 'Y':
        dates[(r['TRANTYPE'], r['TRANSACTION_DATE'])] += 1
        if r['TRANSACTION_DATE'] == '7/31/2024':
            post[r['NEW_AC_NUMBER']][r['TRANTYPE']] += num(r['TRANSAMT'])
print(dates.most_common(8))
ws = {r['ACCNO']: r for r in rows(R + 'ebanker-pack-2026-10-14/CV_10_opening_bal.csv') if r['GLCODE'].startswith('1050')}
both = [a for a in ws if a in post]
print('worksheet loans', len(ws), 'posted accounts', len(post), 'both', len(both))
for tt, col in [('301', 'NEWDISBURS_AMT'), ('303', 'TOTAL_INT_CHARG'), ('305', 'TOTA_REPAY_UPTO')]:
    agree_signed = sum(1 for a in both if abs(post[a][tt] - num(ws[a][col])) < 0.01)
    agree_abs = sum(1 for a in both if abs(abs(post[a][tt]) - abs(num(ws[a][col]))) < 0.01)
    print(tt, col, 'signed', agree_signed, 'abs', agree_abs)
a = both[0]; print(a, dict(post[a]), ws[a]['NEWDISBURS_AMT'], ws[a]['TOTAL_INT_CHARG'], ws[a]['TOTA_REPAY_UPTO'])
print('---')
parts = collections.Counter((r['TRANTYPE'], r['PARTICULARS'][:40], r['DELETE_FLAG']) for r in led if r['TRANSACTION_DATE'] == '7/31/2024' and r['TRANTYPE'] in ('301','303','305'))
print(parts.most_common(12))
for a in both:
    d = post[a]['305'] - num(ws[a]['TOTA_REPAY_UPTO'])
    if abs(d) >= 0.01: print(a, ws[a]['NAME_OF_CUST'], round(d, 2))
print('===')
for col in ['PRINC_REPY_UPTO', 'INT_REPAY_UPTO', 'TOTA_REPAY_UPTO']:
    for tol in (0.01, 1.0):
        print(col, tol, sum(1 for a in both if abs(post[a]['305'] - num(ws[a][col])) < tol))
for a in ['000104420000007','000104450000022','000104450000056','000104460000079','000104460000096']:
    print(a, post[a]['305'], ws[a]['TOTA_REPAY_UPTO'], ws[a]['PRINC_REPY_UPTO'], ws[a]['INT_REPAY_UPTO'])
