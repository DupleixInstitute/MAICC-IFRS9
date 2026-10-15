from pit import *
from collections import Counter, defaultdict
lp = ','.join(str(x) for x in landed())
accts = [r['account'] for r in q(f"select distinct account from ebanker_raw_rows where superseded_at is null and load_id in ({lp}) and query_id in ('P2_10','RS_02a','RS_02b','RS_02c','RS_02d','RS_02e') and account like '0001%%' and account not like '0001050%%'")]
for a in sorted(accts):
    out = []
    for f in ('P2_10','RS_02b','RS_02c','RS_02d','RS_02e'):
        rows = [r for r in fam([f], a) if r['payload'].get('DELETE_FLAG','N') != 'Y' and r['payload'].get('ACTIVE_FLAG','Y') == 'Y' and num(r['payload'].get('INSTALLMENT_AMT')) > 0]
        c = Counter(str(r['row_date']) for r in rows)
        dup = [(d, n) for d, n in c.items() if n > 2]
        if dup: out.append(f"{f}:{max(dup, key=lambda x: x[1])}")
    if out:
        b = q("select reporting_period, round(carrying_amount/1e6,1) bal, overdue_days from loan_books where contract_id=%s and reporting_period in ('2025-12','2026-09')", (str(int(a)),))
        print(a, ' '.join(out), [(x['reporting_period'], float(x['bal']), int(x['overdue_days'])) for x in b])
