import csv, io, sys, os, glob, hashlib
P = r"C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4"
files = sys.argv[1:]
for rel in files:
    p = os.path.join(P, rel)
    raw = open(p, 'rb').read()
    if raw.startswith(b'\xef\xbb\xbf'):
        t = raw[3:].decode('utf-8')
    else:
        try:
            t = raw.decode('utf-8')
        except UnicodeDecodeError:
            t = raw.decode('cp1252'); print('  (cp1252)')
    lines = [l for l in t.splitlines() if l.strip() != '']
    rd = list(csv.reader(lines))
    h = [c.strip() for c in rd[0]]
    drop = h[0] == ''
    if drop: h = h[1:]
    rows = [r[1:] if drop else r for r in rd[1:]]
    bad = sum(1 for r in rows if len(r) != len(h))
    print('==', rel, 'rows', len(rows), 'bad', bad, 'sha', hashlib.sha256(raw).hexdigest(), 'bytes', len(raw))
    print('   cols', len(h), h[:12], '...' if len(h) > 12 else '')
    for k in h:
        if k.endswith('_ID') or k in ('NEW_AC_NUMBER', 'TABLE_NAME', 'GLCODE', 'AC_GLCODE', 'OWNER', 'COLUMN_NAME'):
            vals = [r[h.index(k)] for r in rows if len(r) == len(h)]
            print('   key?', k, 'unique', len(set(vals)) == len(vals), 'blank', sum(1 for v in vals if v.strip() == ''))
    for k in h:
        if 'DATE' in k:
            vals = [r[h.index(k)] for r in rows if len(r) == len(h)]
            print('   date', k, vals[:2])
