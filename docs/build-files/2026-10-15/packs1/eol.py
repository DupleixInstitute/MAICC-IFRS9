"""For every manifest entry: does the working-tree file match the manifest SHA-256, and would it still
match if checked out with LF line endings (what a Linux clone or autocrlf=false gets from an LF index)?"""
import json, glob, os, hashlib, subprocess
REPO = r"C:/xampp/htdocs/MAICC-IFRS9"
B = REPO + "/docs/bootstrap"
eol = {}
out = subprocess.run(['git', '-C', REPO, 'ls-files', '--eol', 'docs/bootstrap'], capture_output=True, text=True).stdout
for line in out.splitlines():
    meta, path = line.split('\t', 1)
    eol[path] = meta.split()[0]
res = {}
for mp in [B + '/manifest.json'] + glob.glob(B + '/ebanker-pack-*/manifest.json'):
    m = json.load(open(mp, encoding='utf-8'))
    base = B if os.path.dirname(mp) == B else os.path.dirname(mp)
    for f in m['files']:
        p = os.path.join(base, f['file'])
        rel = os.path.relpath(p, REPO).replace(os.sep, '/')
        if not os.path.exists(p):
            res.setdefault('missing', []).append(rel); continue
        raw = open(p, 'rb').read()
        ok = hashlib.sha256(raw).hexdigest() == f.get('sha256')
        lf = raw.replace(b'\r\n', b'\n')
        ok_lf = hashlib.sha256(lf).hexdigest() == f.get('sha256')
        k = (os.path.splitext(p)[1], eol.get(rel, 'untracked'), 'wt-ok' if ok else ('no-sha' if not f.get('sha256') else 'wt-BAD'), 'lf-ok' if ok_lf else 'lf-BAD')
        res.setdefault(k, []).append(rel)
for k, v in sorted(res.items(), key=str):
    print(k, len(v), v[:2])
