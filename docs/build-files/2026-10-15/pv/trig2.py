import sys, datetime as dt
from pit import *
from trig import missed, month_end, RECEIPT, KIND
for cid in sys.argv[3:]:
    a = acct(cid); led = ledger(a)
    lv = sorted([x for x in [(r['row_date'], num(r['payload'].get('INSTALLMENT_AMT'))) for r in chart(a)] if x[1] > 0])
    out = []
    p = sys.argv[1]
    while p <= sys.argv[2]:
        D = month_end(p)
        gross = sum(num(r['payload'].get('TRANSAMT')) for r in led if r['row_date'] <= D and num(r['payload'].get('TRANSAMT')) > 0)
        net = sum(num(r['payload'].get('TRANSAMT')) for r in led if r['row_date'] <= D and (r['payload'].get('TRANTYPE') in RECEIPT or r['payload'].get('TRANTYPE') in KIND))
        pc = sorted([(due, num(pp.get('INSTALLMENT_AMT'))) for due, pp in chart_at(a, D) if num(pp.get('INSTALLMENT_AMT')) > 0])
        out.append(f"{p}: live {missed(lv, gross, D) if lv else '-'} pit {missed(pc, net, D) if pc else 'none'}")
        y, m = int(p[:4]), int(p[5:]); m += 1
        if m == 13: y, m = y + 1, 1
        p = f"{y}-{m:02d}"
    print(cid, ' | '.join(out))
