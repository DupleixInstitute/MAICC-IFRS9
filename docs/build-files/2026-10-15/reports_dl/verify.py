import urllib.request, json, re, html, sys, hashlib, time
D = r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/'
CK = open(D + 'tools/ck_reports.txt').read().strip()
BASE = 'http://127.0.0.1:8070/'
TOT = {'2026-08': ('10517469001.95', '24638632687.9', '135'), '2026-07': ('9070200333.58', '22628187224.68', '132')}


def get(path, tries=4):
    for i in range(tries):
        try:
            return _get(path)
        except (ConnectionResetError, urllib.error.URLError, TimeoutError) as e:
            time.sleep(5)
    return 0, '', b'', 0


def _get(path):
    req = urllib.request.Request(BASE + path, headers={'Cookie': CK})
    t = time.time()
    try:
        r = urllib.request.urlopen(req, timeout=300)
        return r.status, r.headers.get('Content-Type', ''), r.read(), time.time() - t
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Content-Type', ''), e.read(), time.time() - t


def page(path):
    s, ct, body, _ = get(path)
    m = re.search(rb'data-page="([^"]*)"', body)
    if s != 200 or not m:
        return s, None
    return s, json.loads(html.unescape(m.group(1).decode()))


def plain(b):
    return b.decode('utf-8', 'ignore').replace(',', '')


keys = sys.argv[1:] or ['executive', 'ecl', 'sector-ecl', 'portfolio-trend', 'account-ecl', 'stage-migration', 'lgd-collateral',
                        'macro-scenario', 'rbm-classification', 'data-quality', 'ews',
                        'stage-allocation', 'pd-report', 'product-group-ecl', 'grade-ecl', 'concentration', 'coop-linkage', 'sicr-trigger',
                        'crm-agri', 'ead-report', 'scenario-ecl', 'ifrs9-vs-rbm', 'provision-comparison', 'npl-arrears', 'ai-narrative',
                        'ecl-reconciliation', 'gross-movement', 'ecl-charge']
for k in keys:
    res = {}
    for p in ['2026-08', '2026-07']:
        s, pg = page(f'ifrs9-reports/{k}?period={p}')
        rep = pg['props']['report'] if pg else {}
        fm = {}
        csvtext = ''
        for f in ['pdf', 'xlsx', 'csv']:
            st, ct, body, dt = get(f'ifrs9-reports/{k}?period={p}&download={f}')
            ok = st == 200 and (('pdf' in ct) if f == 'pdf' else ('sheet' in ct) if f == 'xlsx' else ('csv' in ct))
            fm[f] = 'ok' if ok else f'{st}'
            if f == 'csv' and ok:
                csvtext = plain(body)
        ecl, ead, n = TOT[p]
        ties = [x for x, v in (('ECL', ecl), ('EAD', ead), ('loans', n)) if v in csvtext]
        res[p] = dict(status=s, title=rep.get('title'), period=rep.get('period'), parts=len(rep.get('parts') or []),
                      sections=len(rep.get('sections') or []), rows=sum(len(x.get('rows') or []) for x in rep.get('sections') or []),
                      empty=[x.get('heading') for x in rep.get('sections') or [] if not x.get('rows')],
                      formats=fm, ties=ties, hash=hashlib.md5(csvtext.split('Prepared on')[-1].split('\n', 1)[-1].encode()).hexdigest()[:8])
    a, b = res['2026-08'], res['2026-07']
    print(json.dumps({'key': k, 'title': a['title'], 'loads': [a['status'], b['status']], 'period': [a['period'], b['period']],
                      'parts': a['parts'], 'rows': [a['rows'], b['rows']], 'differs': a['hash'] != b['hash'],
                      'formats': [a['formats'], b['formats']], 'ties': [a['ties'], b['ties']], 'empty': [a['empty'], b['empty']]}))
