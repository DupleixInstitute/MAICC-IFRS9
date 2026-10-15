import datetime as dt, csv, sys
from pit import *
WO = None
def month_end(p):
    d = dt.date.fromisoformat(p + '-01'); d = d.replace(day=28) + dt.timedelta(days=4); return d - dt.timedelta(days=d.day)
books = q("select contract_id, reporting_period p, customer_name, customer_id, loan_portfolio_id pf, carrying_amount bal, overdue_days dpd, overdue_principal_date od, due_date, tenor, ifrs9stage_pre_qualitative pre, ifrs9stage_post_qualitative post, arrears_1_to_30 a1, arrears_30_to_90 a2, arrears_91_to_180 a3, arrears_180_to_270 a4, arrears_271_to_360 a5 from loan_books where loan_portfolio_id<>8 and reporting_period>='2024-12' order by contract_id, reporting_period")
ledgers = {}
out = []
for b in books:
    a = acct(b['contract_id']); D = month_end(b['p'])
    if a not in ledgers: ledgers[a] = ledger(a)
    led = ledgers[a]
    rec = sum(num(r['payload'].get('TRANSAMT')) for r in led if r['row_date'] <= D and r['payload'].get('TRANTYPE') in ('305','306','343','900','901','401'))
    ch = chart_at(a, D)
    pit = None; oldest = None; due_td = 0.0; exp_bal = None; nmiss = None
    if ch:
        cum = 0.0; pit = 0; left = rec; nmiss = 0
        for due, p in ch:
            amt = num(p.get('INSTALLMENT_AMT'))
            if due > D: break
            exp_bal = num(p.get('EXP_BALANCE'))
            if amt <= 0: continue
            cum += amt
            if oldest is None and cum > rec + 0.5: oldest = due
            if left + 0.005 >= amt: left -= amt; nmiss = 0
            else: left = 0.0; nmiss += 1
        due_td = cum
        pit = (D - oldest).days if oldest else 0
    out.append(dict(cid=b['contract_id'], p=b['p'], name=(b['customer_name'] or '')[:28], pf=b['pf'], bal=round(float(b['bal'])/1e6,2), dpd=int(b['dpd']), od=b['od'], due=b['due_date'], tenor=b['tenor'], pre=b['pre'], post=b['post'],
        rep_bucket=max([k for k,v in zip([1,31,91,181,271],[b['a1'],b['a2'],b['a3'],b['a4'],b['a5']]) if float(v or 0)>0] or [0]),
        chart_rows=len(ch), pit_dpd=pit, oldest=oldest, due_td=round(due_td/1e6,2), rec_td=round(rec/1e6,2), short=round((due_td-rec)/1e6,2) if ch else None, exp_bal=round(exp_bal/1e6,2) if exp_bal is not None else None, nmiss=nmiss))
with open('sysdpd.csv', 'w', newline='') as f:
    w = csv.DictWriter(f, fieldnames=list(out[0].keys())); w.writeheader(); w.writerows(out)
print(len(out))
