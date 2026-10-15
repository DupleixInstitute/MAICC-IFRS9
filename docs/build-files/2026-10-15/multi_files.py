import json, glob, os, re, collections, zipfile
base = r'C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap'
seeder = open(r'C:\xampp\htdocs\MAICC-IFRS9\database\seeders\EbankerQuerySeeder.php', encoding='utf-8').read()
keyless = set()
for m in re.finditer(r"\[\s*'([A-Z0-9_a-z]+)',\s*'[^']*(?:\\'[^']*)*',\s*(null|'[^']*'),\s*(null|'[^']*')", seeder):
    if m.group(3) == 'null':
        keyless.add(m.group(1))
mans = [os.path.join(base, 'manifest.json')] + glob.glob(base + r'\ebanker-pack-*\manifest.json')
data = []
for m in mans:
    data.append((m, json.load(open(m, encoding='utf-8'))))
for z in glob.glob(base + r'\packs\*.zip'):
    zf = zipfile.ZipFile(z)
    name = os.path.basename(z)[:-4]
    data.append((z, json.loads(zf.read(name + '/manifest.json'))))
allq = collections.defaultdict(list)
for m, j in data:
    c = collections.Counter(f.get('query_id') for f in j['files'] if str(f.get('file', '')).lower().endswith('.csv'))
    for q, n in c.items():
        allq[q].append((os.path.basename(os.path.dirname(m)) if m.endswith('json') else os.path.basename(m), n))
for q, v in sorted(allq.items()):
    if q in keyless and (len(v) > 1 or any(n > 1 for _, n in v)):
        print('KEYLESS MULTI', q, v)
print('keyless count', len(keyless))
