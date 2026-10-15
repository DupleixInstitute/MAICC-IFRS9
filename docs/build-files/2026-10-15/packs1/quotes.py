import glob, zipfile, re, os
REPO = r"C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap"
SRC = r"C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4"
def check(name, raw):
    if raw.startswith(b'\xef\xbb\xbf'):
        raw = raw[3:]
    odd = 0
    for line in re.split(rb'\r\n|\n|\r', raw):
        if line.count(b'"') % 2:
            odd += 1
    return odd
tot = 0; bad = []
for p in glob.glob(REPO + '/**/*.csv', recursive=True):
    tot += 1
    o = check(p, open(p, 'rb').read())
    if o: bad.append((p, o))
for zp in glob.glob(REPO + '/packs/*.zip'):
    z = zipfile.ZipFile(zp)
    for n in z.namelist():
        if n.endswith('.csv'):
            tot += 1
            o = check(n, z.read(n))
            if o: bad.append((n, o))
print('committed csv files', tot, 'with odd-quote lines', bad)
for p in glob.glob(SRC + '/Pack [5-8]/Export Results/*.csv'):
    o = check(p, open(p, 'rb').read())
    if o: print('new', os.path.basename(os.path.dirname(os.path.dirname(p))), os.path.basename(p), o)
