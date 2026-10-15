from pit import *
from collections import defaultdict, Counter
lp = ','.join(str(x) for x in landed())
rows = q(f"select account, row_date, source_key, json_unquote(json_extract(payload,'$.TRANSACTION_DATE')) rd, json_unquote(json_extract(payload,'$.OVERDUE_PRINCI_DATE')) od, json_unquote(json_extract(payload,'$.ARREAS_TOTAL')) arr, json_unquote(json_extract(payload,'$.CARRYING_AMOUNT')) bal, json_unquote(json_extract(payload,'$.GLCODE')) gl, json_unquote(json_extract(payload,'$.DAY_181_270')) b4 from ebanker_raw_rows where superseded_at is null and load_id in ({lp}) and query_id in ('P2_08','M09_03') ")
by = defaultdict(list)
for r in rows:
    if r['gl'] in ('1050101','1050102','1050103','1050201','1050202','1050401'): by[(r['account'], r['row_date'])].append(r)
rd = Counter()
for (a, d), rs in by.items():
    for r in rs: rd[(str(d)[:7], pdate(r['rd']).strftime('%Y-%m') if pdate(r['rd']) else None)] += 1
months = sorted(set(k[0] for k in rd))
for m in months:
    print(m, sorted([(k[1], n) for k, n in rd.items() if k[0] == m]))
diff = Counter(); ex = []
for (a, d), rs in by.items():
    rs.sort(key=lambda r: int(r['source_key']))
    first = min(rs, key=lambda r: (pdate(r['rd']) or dt.date(2100,1,1), int(r['source_key']))); last = rs[-1]
    same = (first['od'] == last['od'] and abs(num(first['arr']) - num(last['arr'])) < 1 and abs(num(first['bal'])-num(last['bal']))<1)
    diff[(str(d)[:7], same)] += 1
    if not same and len(ex) < 400: ex.append((a, str(d), first['rd'], first['od'], round(num(first['arr'])/1e6,2), round(num(first['bal'])/1e6,2), last['rd'], last['od'], round(num(last['arr'])/1e6,2), round(num(last['bal'])/1e6,2)))
print(sorted(diff.items()))
import json; json.dump(ex, open('runcmp_ex.json','w'), default=str)
for e in ex[:40]: print(e)
