import sys
from lz import *
for a in sys.argv[1:]:
    print('====', a)
    for f in ('P2_10','RS_02a','RS_02b','RS_02c','RS_02d','RS_02e'):
        rows = [r for r in fam([f], acct(a)) if r['payload'].get('DELETE_FLAG','N')!='Y']
        if not rows: continue
        byid = {}
        for r in rows: byid.setdefault((r['payload'].get('INST_DET_ID'), r['payload'].get('ACTIVE_FLAG'), r['payload'].get('TRANSACTION_DATE')), []).append(r)
        for k, rs in byid.items():
            rs.sort(key=lambda r: str(r['row_date']))
            amts = [num(r['payload'].get('INSTALLMENT_AMT')) for r in rs]
            nz = [(str(r['row_date']), round(num(r['payload'].get('INSTALLMENT_AMT'))/1e6,2)) for r in rs if num(r['payload'].get('INSTALLMENT_AMT'))>0]
            print(f" {f} instdet={k[0]} active={k[1]} txn={k[2]} rows={len(rs)} first={rs[0]['row_date']} last={rs[-1]['row_date']} total={sum(amts)/1e6:.2f} nz_first={nz[:3]} nz_last={nz[-2:]}")
