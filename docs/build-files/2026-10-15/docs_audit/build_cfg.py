"""Build the screenshot config: the existing manual shot list (route name to key)
plus the new keys of the documentation audit. IDs for detail pages are passed
in ids.json (looked up read-only on the database being shot)."""
import json, os, sys

here = os.path.dirname(os.path.abspath(__file__))
routes = {r['name']: r['uri'] for r in json.load(open(os.path.join(here, 'routes.json'), encoding='utf-8')) if r.get('name')}
ids = json.load(open(os.path.join(here, 'ids.json'))) if os.path.exists(os.path.join(here, 'ids.json')) else {}

FULL = {'dashboard', 'workspace', 'loanbook', 'ecl', 'ecl-create', 'ecl-projections', 'tmatrix', 'lgd',
        'stress-testing', 'ecl-reconciliation', 'loanbook-reconciliation', 'disbursements',
        'eir-data', 'eir-intake', 'eir-fees', 'eir-calculations', 'eir-reconciliation', 'eir-coverage',
        'report-executive', 'report-ecl', 'report-account-ecl', 'report-rbm-classification', 'report-fs-disclosure', 'ews'}

def url(name, params=None, query=''):
    uri = routes[name]
    for k, v in (params or {}).items():
        uri = uri.replace('{' + k + '}', str(v))
    return '/' + uri.lstrip('/') + (('?' + query) if query else '')

shots = []
for line in open(os.path.join(here, 'existing_shots.txt')):
    name, key = line.split()
    if name not in routes:
        print('skip (no route)', name, file=sys.stderr)
        continue
    shots.append({'key': key, 'url': url(name), 'fullPage': key in FULL})

NEW = [
    ('sidebar-collapsed', url('dashboard'), False, {'ls': {'collapsed': True}}),
    ('sidebar-blocks', url('dashboard'), False, {'actions': [{'eval': "(()=>{Object.keys(localStorage).filter(k=>k.startsWith('maiic.sidebar.blocks.')).forEach(k=>localStorage.removeItem(k)); const id=(window.__uid||1); localStorage.setItem('maiic.sidebar.blocks.'+id, JSON.stringify({'Data foundation':true,'Financial modelling':true})); location.reload();})()", 'wait': 4000}]}),
    ('sidebar-admin', url('users.index'), False, {}),
    ('dashboard-charts', url('dashboard'), False, {'actions': [{'scrollTo': 'ECL by portfolio over months'}]}),
    ('dashboard-where', url('dashboard'), False, {'actions': [{'scrollTo': 'ECL build-up'}]}),
    ('dashboard-stressed', url('dashboard'), False, {'actions': [{'scrollTo': 'Base and stressed ECL'}]}),
    ('workspace-approvals', url('workspace.index'), False, {'actions': [{'clickText': 'Awaiting approval'}]}),
    ('import-modal', url('sicr-groups.index'), False, {'actions': [{'clickText': 'Import CSV', 'wait': 1500}]}),
    ('clients-import', url('clients.import.create'), True, {}),
    ('eir-feed-loads', url('eir-feed.index', query='tab=loads'), True, {}),
    ('eir-feed-builds', url('eir-feed.index', query='tab=builds'), True, {}),
    ('eir-feed-periods', url('eir-feed.index', query='tab=periods'), True, {}),
    ('eir-feed-queries', url('eir-feed.index', query='tab=queries'), True, {}),
    ('eir-takeon-blocks', url('eir-takeon.index', query='tab=blocks'), True, {}),
    ('eir-takeon-population', url('eir-takeon.index', query='tab=population'), True, {}),
    ('eir-data', url('eir-data.index', query='tab=contracts'), True, {}),
    ('eir-data-cashflows', url('eir-data.index', query='tab=cashflows'), True, {}),
    ('eir-schedule-review', url('eir-data.index', query='tab=schedules'), True, {}),
    ('eir-drawdowns', url('eir-drawdowns.index'), True, {}),
    ('eir-reference-rates', url('eir-reference-rates.index'), True, {}),
    ('eir-trial-balances', url('eir-trial-balances.index'), True, {}),
    ('eir-write-offs', url('eir-write-offs.index'), True, {}),
    ('eir-restructures', url('eir-restructures.index'), True, {}),
    ('eir-coverage', url('eir-coverage.index', query='tab=blockers'), True, {}),
    ('eir-coverage-portfolios', url('eir-coverage.index', query='tab=portfolios'), True, {}),
    ('eir-coverage-facilities', url('eir-coverage.index', query='tab=facilities'), True, {}),
    ('eir-as-at', url('eir-as-at.index'), True, {}),
    ('eir-governance', url('eir-governance.index'), True, {}),
    ('fli-overview', url('fli.overview'), True, {}),
    ('fli-impact', url('fli.impact'), True, {}),
    ('macro-dashboard', url('macro-statistics.index', query='tab=dashboard'), True, {}),
    ('macro-variables', url('macro-statistics.index', query='tab=variables'), True, {}),
    ('macro-entry', url('macro-statistics.index', query='tab=entry'), True, {}),
    ('macro-scenarios', url('macro-statistics.index', query='tab=scenarios'), True, {}),
    ('macro-import', url('macro-statistics.index', query='tab=import'), True, {}),
    ('fli-correlation', url('fli-correlation.index'), True, {}),
    ('fli-regression', url('fli-adjustments.index'), True, {}),
    ('fli-models', url('fli-models.index'), True, {}),
    ('scenario-sets', url('scenario-sets.index'), True, {}),
    ('fli-overlays', url('fli-overlays.index'), True, {}),
    ('megafarm', url('megafarm.index'), True, {}),
    ('audit-trace', url('audit-trace.index'), False, {}),
    ('compliance-audits', url('compliance-audits.index'), False, {}),
    ('maintenance', url('maintenance.index'), True, {}),
    ('rbm-return', url('rbm-return.index'), True, {}),
    ('auditor-pack', url('auditor-pack.index'), True, {}),
    ('report-sensitivity', url('ifrs9-reports.sensitivity'), True, {}),
]
if ids.get('schedule'):
    NEW.append(('eir-schedule-show', url('eir-schedules.show', {'contractEir': ids['schedule']}), True, {}))
if ids.get('grade_profile'):
    NEW.append(('internal-grades-profile', url('internal-grading.grades', {'profile': ids['grade_profile']}), True, {}))
    NEW.append(('internal-grades-matrix', url('internal-grading.matrix.view', {'profile': ids['grade_profile']}), True, {}))
for extra in ids.get('extra', []):
    NEW.append((extra['key'], extra['url'], extra.get('fullPage', False), {k: v for k, v in extra.items() if k in ('actions', 'ls')}))

bykey = {s['key']: i for i, s in enumerate(shots)}
for key, u, full, more in NEW:
    s = {'key': key, 'url': u, 'fullPage': full, **more}
    if key in bykey:
        shots[bykey[key]] = s
    else:
        bykey[key] = len(shots)
        shots.append(s)

cfg = {'baseUrl': sys.argv[1], 'email': 'admin@localhost.com', 'password': sys.argv[2], 'captcha': sys.argv[3],
       'outDir': sys.argv[4], 'manifestDir': here, 'shots': shots}
json.dump(cfg, open(os.path.join(here, 'shots.json'), 'w'), indent=1)
print(len(shots), 'shots')
