import datetime as dt
from lz import *
DISB = {'301','400'}
def windows(s, e):
    rows = q("""select s.contract_id cid, s.customer_id cust, s.customer_name name, s.carrying_amount sb,
      COALESCE(NULLIF(s.ifrs9stage_post_qualitative,''),s.ifrs9stage_pre_qualitative) sg, e.carrying_amount eb,
      COALESCE(NULLIF(e.ifrs9stage_post_qualitative,''),e.ifrs9stage_pre_qualitative) eg
      from loan_books s left join loan_books e on e.contract_id=s.contract_id and e.reporting_period=%s
      where s.reporting_period=%s and s.loan_portfolio_id<>8 and s.carrying_amount>0.5e6""", (e, s))
    accts_by_cust = {}
    for r in q("select distinct customer_id, contract_id from loan_books where loan_portfolio_id<>8"):
        accts_by_cust.setdefault(r['customer_id'], set()).add(r['contract_id'])
    for r in rows:
        led = ledger(r['cid'])
        big = [x for x in led if s <= str(x['row_date'])[:7] <= e and num(x['payload'].get('TRANSAMT')) > 0.25 * float(r['sb'])]
        for b in big:
            d = b['row_date']
            for other in accts_by_cust.get(r['cust'], set()) - {r['cid']}:
                for y in ledger(other):
                    if y['payload'].get('TRANTYPE') in DISB and abs((y['row_date'] - d).days) <= 7:
                        print(f"{s}->{e} {r['cid']} {r['name'][:25]} start S{r['sg']} {float(r['sb'])/1e6:.1f} end S{r['eg']} {float(r['eb'] or 0)/1e6:.1f} | credit {d} {num(b['payload'].get('TRANSAMT'))/1e6:.1f} '{(b['payload'].get('PARTICULARS') or '')[:40]}' | {other} disb {y['row_date']} {num(y['payload'].get('TRANSAMT'))/1e6:.1f}")
windows('2024-12', '2025-12'); windows('2025-09', '2026-09')
