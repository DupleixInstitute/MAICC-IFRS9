"""Mega Farm Dec 2025: MAIIC's model (ECLComp, by account) against the system. Read-only.
usage: python -I cmp_mf.py <db> <mf_eclcomp_csv>"""
import sys, csv
import pymysql
from collections import defaultdict

db, src = sys.argv[1], sys.argv[2]
con = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor)
c = con.cursor()
c.execute("""select external_identity_id acc, product_group, carrying_amount ca, coalesce(commitments,0) com, ifrs9stage_post_qualitative st, overdue_days dpd, pd_post_fli pd, lgd_value lgd, ecl_value ecl
  from loan_books where reporting_period='2025-12' and product_code in ('1050301','1050302','1050303','1050304','1050305','1050306','1050307')""")
ours = {r['acc']: r for r in c.fetchall()}
c.execute("select * from megafarm_ecl_runs where reporting_period='2025-12' order by id desc limit 1")
run = c.fetchone()
theirs = list(csv.DictReader(open(src, encoding='utf-8')))
m = defaultdict(lambda: [0, 0.0, 0.0])
matched = 0
only_theirs = [0, 0.0]
for t in theirs:
    o = ours.get(t['B'])
    if not o:
        only_theirs[0] += 1; only_theirs[1] += float(t['AV'] or 0)
        continue
    matched += 1
    k = (t['R'], o['st'])
    m[k][0] += 1; m[k][1] += float(t['AV'] or 0); m[k][2] += float(o['ca'] or 0)
only_ours = [(a, r) for a, r in ours.items() if a not in {t['B'] for t in theirs}]
print('MAIIC MF rows', len(theirs), 'matched', matched, 'only theirs', only_theirs[0], round(only_theirs[1] / 1e6, 1),
      'only ours', len(only_ours), round(sum(float(r['ca'] or 0) for a, r in only_ours) / 1e6, 1))
for k, v in sorted(m.items()):
    print('their stage', k[0], 'our stage', k[1], 'loans', v[0], 'their EAD', round(v[1] / 1e6, 1), 'our gross', round(v[2] / 1e6, 1))
ead_t = sum(float(t['AV'] or 0) for t in theirs)
ecl_t = sum(float(t['BC'] or 0) for t in theirs)
print('theirs EAD', round(ead_t / 1e6, 1), 'ECL', round(ecl_t / 1e6, 1))
print('ours gross', round(sum(float(r['ca'] or 0) for r in ours.values()) / 1e6, 1), 'run', run and {k: run[k] for k in ('programme_ecl', 'maiic_ecl', 'declined')})
