import sys; from lz import *
for a in sys.argv[1:]:
    print('=====', a)
    for r in ledger(a):
        p = r['payload']
        print(r['row_date'], r['query_id'], p.get('TRANTYPE'), p.get('BATCH_TYPE'), p.get('DBCR'), f"{num(p.get('TRANSAMT')):>16,.2f}", (p.get('PARTICULARS') or '')[:60])
