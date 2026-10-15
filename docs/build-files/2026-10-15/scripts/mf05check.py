import sys, csv, io, calendar
from datetime import datetime
def read(p):
    t = open(p, 'rb').read().decode('utf-8-sig'); rows = list(csv.reader(io.StringIO(t, newline='')))
    h = [x.strip() for x in rows[0]][1:]
    return [dict(zip(h, r[1:])) for r in rows[1:] if any(c.strip() for c in r)]
a = read(sys.argv[1]); b = []
for p in sys.argv[2:]: b += read(p)
def me(s):
    d = datetime.strptime(s, '%m/%d/%Y').date(); return d.day == calendar.monthrange(d.year, d.month)[1]
la = {}
for r in a:
    if me(r['ASONDATE']):
        k = (r['NEW_AC_NUMBER'], r['ASONDATE']); la[k] = max(la.get(k, 0), int(r['LOAN_BOOK_DET_ID_A']))
bk = {}
for r in b:
    k = (r['NEW_AC_NUMBER'], r['ASONDATE']); bk[k] = max(bk.get(k, 0), int(r['LOAN_BOOK_DET_ID_A']))
miss = [k for k in la if k not in bk]; later = [k for k in la if k in bk and la[k] > bk[k]]
print('pack3 month-end account-months', len(la), 'missing in pack4', len(miss), 'pack3 later run than pack4', len(later), miss[:5], later[:5])
print('pack4 month-ends', sorted({datetime.strptime(k[1], '%m/%d/%Y').date().isoformat() for k in bk})[:3], '...', len({k[1] for k in bk}))
