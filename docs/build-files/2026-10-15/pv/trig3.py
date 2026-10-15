import datetime as dt
from pit import *
from trig import missed, month_end, RECEIPT, KIND
books = q("select contract_id, reporting_period p, customer_name n, carrying_amount bal, overdue_days dpd, ifrs9stage_pre_qualitative pre, ifrs9stage_post_qualitative post from loan_books where loan_portfolio_id<>8 and reporting_period in ('2024-12','2025-09','2025-12','2026-09') and ifrs9stage_pre_qualitative<>'3'")
led = {}; live = {}
for b in books:
    a = acct(b['contract_id']); D = month_end(b['p'])
    if a not in led:
        led[a] = ledger(a)
        live[a] = sorted([x for x in [(r['row_date'], num(r['payload'].get('INSTALLMENT_AMT'))) for r in chart(a)] if x[1] > 0])
    gross = sum(num(r['payload'].get('TRANSAMT')) for r in led[a] if r['row_date'] <= D and num(r['payload'].get('TRANSAMT')) > 0)
    net = sum(num(r['payload'].get('TRANSAMT')) for r in led[a] if r['row_date'] <= D and (r['payload'].get('TRANTYPE') in RECEIPT or r['payload'].get('TRANTYPE') in KIND))
    pc = sorted([(due, num(pp.get('INSTALLMENT_AMT'))) for due, pp in chart_at(a, D) if num(pp.get('INSTALLMENT_AMT')) > 0])
    t0 = missed(live[a], gross, D) if live[a] else None
    t1 = missed(pc, net, D) if pc and float(b['bal']) > 0.005 else None
    dpd = int(b['dpd']); start = D - dt.timedelta(days=dpd)
    t2 = (sum(1 for due, amt in pc if start <= due <= D) if dpd > 0 else 0) if pc and float(b['bal']) > 0.005 else None
    if max(t0 or 0, t1 or 0, t2 or 0) >= 4:
        print(b['p'], b['contract_id'], (b['n'] or '')[:22].ljust(22), f"{float(b['bal'])/1e6:8.1f}", 'pre', b['pre'], 'post', b['post'], 'dpd', dpd, '| T0', t0, 'T1', t1, 'T2', t2)
