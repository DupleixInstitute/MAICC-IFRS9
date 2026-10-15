import os, hashlib, json, zipfile, glob, sys
SRC = r"C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC"
REPO = r"C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap"
def sha(b): return hashlib.sha256(b).hexdigest()
committed = {}
def add_manifest(m, where):
    for f in m['files']:
        if f.get('sha256'):
            committed.setdefault(f['sha256'], []).append((where, f['file'], f.get('query_id'), f.get('rows')))
for mp in [REPO + '/manifest.json'] + glob.glob(REPO + '/ebanker-pack-*/manifest.json'):
    add_manifest(json.load(open(mp, encoding='utf-8')), os.path.relpath(mp, REPO))
for zp in glob.glob(REPO + '/packs/*.zip'):
    z = zipfile.ZipFile(zp)
    for n in z.namelist():
        if n.endswith('manifest.json'):
            add_manifest(json.loads(z.read(n)), os.path.basename(zp))
disk = {}
for p in glob.glob(REPO + '/**/*.csv', recursive=True):
    disk.setdefault(sha(open(p, 'rb').read()), []).append(os.path.relpath(p, REPO))
for zp in glob.glob(REPO + '/packs/*.zip'):
    z = zipfile.ZipFile(zp)
    for n in z.namelist():
        if n.endswith('.csv'):
            disk.setdefault(sha(z.read(n)), []).append('zip:' + n)
rows = []
for root, ds, fs in os.walk(SRC):
    for fn in fs:
        p = os.path.join(root, fn)
        rel = os.path.relpath(p, SRC).replace(os.sep, '/')
        if not (rel.startswith('Received') or rel.startswith('Follow-Up')):
            continue
        if fn.lower().endswith(('.sql', '.pdf', '.md', '.txt')) and 'Big files' not in rel and 'BIG FILES' not in fn and 'RUN 0' not in fn and 'correction' not in fn:
            continue
        b = open(p, 'rb').read(); h = sha(b)
        rows.append((rel, len(b), h, committed.get(h), disk.get(h)))
for r in sorted(rows):
    tag = 'M' if r[3] else ('D' if r[4] else '-')
    where = (r[3][0][0] + ':' + r[3][0][1] + ' rows=' + str(r[3][0][3])) if r[3] else (r[4][0] if r[4] else '')
    print(tag, '|', r[0], '|', r[1], '|', where)
