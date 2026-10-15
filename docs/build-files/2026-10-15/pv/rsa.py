import sys
from lz import *
from collections import Counter
for a in sys.argv[1:]:
    rows = fam(['RS_02a'], acct(a))
    c = Counter((r['payload'].get('TRANSACTION_DATE'), r['payload'].get('INST_DET_ID'), (r['payload'].get('DELETE_DESC') or '')[:40], r['payload'].get('OPERATION_DATE'), r['payload'].get('STATUS_CHANGE_DATE')) for r in rows)
    print(a, len(rows))
    for k, n in sorted(c.items(), key=lambda x: str(x[0]))[:40]: print('  ', k, n)
    if rows: print(rows[0]['payload'])
