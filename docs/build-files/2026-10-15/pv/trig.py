import datetime as dt, csv
from pit import *
RECEIPT = {'305','306','343','900','901'}; KIND = {'401'}
def month_end(p):
    d = dt.date.fromisoformat(p + '-01'); d = d.replace(day=28) + dt.timedelta(days=4); return d - dt.timedelta(days=d.day)
def missed(inst, rec, D):
    m = 0
    for due, amt in inst:
        if due > D: break
        if rec + 0.005 >= amt: rec -= amt; m = 0
        else: rec = 0.0; m += 1
    return m
def main():
    books = q("select contract_id, reporting_period p, customer_name n, carrying_amount bal, ifrs9stage_pre_qualitative pre, ifrs9stage_post_qualitative post from loan_books where loan_portfolio_id<>8 and reporting_period>='2024-12'")
    led = {}; live = {}; diff = []
    for b in books:
        a = acct(b['contract_id']); D = month_end(b['p'])
        if a not in led:
            led[a] = ledger(a)
            lv = [(r['row_date'], num(r['payload'].get('INSTALLMENT_AMT'))) for r in chart(a)]
            live[a] = sorted([x for x in lv if x[1] > 0])
        if not live[a] and not versions(a): continue
        gross = sum(num(r['payload'].get('TRANSAMT')) for r in led[a] if r['row_date'] <= D and num(r['payload'].get('TRANSAMT')) > 0)
        net = sum(num(r['payload'].get('TRANSAMT')) for r in led[a] if r['row_date'] <= D and (r['payload'].get('TRANTYPE') in RECEIPT or r['payload'].get('TRANTYPE') in KIND))
        pit = sorted([(due, num(p.get('INSTALLMENT_AMT'))) for due, p in chart_at(a, D) if num(p.get('INSTALLMENT_AMT')) > 0])
        m_old = missed(live[a], gross, D) if live[a] else None
        m_new = missed(pit, net, D) if pit else None
        if (m_old or 0) >= 4 or (m_new or 0) >= 4:
            diff.append((b['p'], b['contract_id'], (b['n'] or '')[:22], round(float(b['bal'])/1e6,1), b['pre'], b['post'], m_old, m_new, round(gross/1e6,1), round(net/1e6,1), len(pit)))
    for d in sorted(diff):
        if d[0] in ('2025-12','2026-09') or (d[6] or 0) >= 4 and (d[7] or 0) < 4 or (d[7] or 0) >= 4 and (d[6] or 0) < 4: print(d)
if __name__ == "__main__":
    main()
