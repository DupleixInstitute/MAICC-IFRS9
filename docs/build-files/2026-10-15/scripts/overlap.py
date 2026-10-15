"""Compare keys of a file A against a set of files B: how many A keys are in B, and with the same values on the shared columns.
Usage: python -I overlap.py KEY A.csv B1.csv [B2.csv ...]"""
import sys, csv, io


def read(path):
    raw = open(path, 'rb').read()
    try:
        text = raw.decode('utf-8-sig')
    except UnicodeDecodeError:
        text = raw.decode('cp1252')
    rows = list(csv.reader(io.StringIO(text, newline='')))
    hdr = [h.strip() for h in rows[0]]
    drop = hdr[0] == ''
    if drop:
        hdr = hdr[1:]
    out = []
    for r in rows[1:]:
        if not any(c.strip() for c in r):
            continue
        r = r[1:] if drop else r
        out.append(dict(zip(hdr, r)))
    return hdr, out


key = sys.argv[1]
ha, a = read(sys.argv[2])
b = {}
hb = None
for p in sys.argv[3:]:
    h, rows = read(p)
    hb = h
    for r in rows:
        b[r[key]] = r
shared = [c for c in ha if c in hb and c != key]
inb = same = 0
diffcols = {}
for r in a:
    o = b.get(r[key])
    if o is None:
        continue
    inb += 1
    d = [c for c in shared if r[c].strip() != o[c].strip()]
    if not d:
        same += 1
    for c in d:
        diffcols[c] = diffcols.get(c, 0) + 1
print(f'A rows {len(a)}; keys in B {inb}; identical on {len(shared)} shared cols {same}; B rows {len(b)}')
print('cols differing:', dict(sorted(diffcols.items(), key=lambda x: -x[1])[:10]))
if 'ASONDATE' in ha:
    am = {(r['NEW_AC_NUMBER'], r['ASONDATE']) for r in a}
    bm = {(r['NEW_AC_NUMBER'], r['ASONDATE']) for r in b.values()}
    print('account-months in A', len(am), 'not in B', len(am - bm), 'example', sorted(am - bm)[:5])
