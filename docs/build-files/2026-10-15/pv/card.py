import sys, datetime as dt
from lz import *
RECEIPT = {'305','306','343','900','901'}
def pdate(s):
    s = str(s or '').strip()
    if not s: return None
    for f in ('%m/%d/%Y','%Y-%m-%d'):
        try: return dt.datetime.strptime(s.split(' ')[0], f).date()
        except: pass
    return None
def receipts_to(led, d):
    return sum(num(r['payload'].get('TRANSAMT')) for r in led if str(r['row_date']) <= str(d) and num(r['payload'].get('TRANSAMT')) > 0)
def indep_dpd(ch, led, d):
    """oldest instalment (P2_10 INSTALLMENT_AMT) not covered by all credits to date d"""
    rec = receipts_to(led, d); cum = 0.0
    for r in ch:
        due = r['row_date']; amt = num(r['payload'].get('INSTALLMENT_AMT'))
        if amt <= 0: continue
        if str(due) > str(d): return 0, None, rec, cum
        cum += amt
        if cum > rec + 0.5:
            return (d - due).days, due, rec, cum
    return 0, None, rec, cum
def missed(ch, rec, d):
    m = 0
    for r in ch:
        due = r['row_date']; amt = num(r['payload'].get('INSTALLMENT_AMT'))
        if amt <= 0: continue
        if str(due) > str(d): break
        if rec + 0.005 >= amt: rec -= amt; m = 0
        else: rec = 0.0; m += 1
    return m
def card(cid, s, e):
    a = acct(cid); bk = {r['reporting_period']: r for r in book(cid)}
    led = ledger(a); ch = chart(a); rn = runs(a)
    print(f"\n######## {cid} {bk.get(s, bk.get(e, {})).get('customer_name')} cust {bk.get(s,{}).get('customer_id')}")
    path = []
    for p in sorted(bk):
        if s <= p <= e:
            r = bk[p]; path.append(f"{p[2:]}:{r['ifrs9stage_pre_qualitative']}/{r['ifrs9stage_post_qualitative']}:{int(r['overdue_days'])}d:{float(r['carrying_amount'])/1e6:.1f}")
    print(' path', ' '.join(path))
    er = bk.get(e)
    if er:
        me = dt.date.fromisoformat(e + '-01'); me = (me.replace(day=28) + dt.timedelta(days=4)); me = me - dt.timedelta(days=me.day)
        run = rn.get(str(me)); p = run['payload'] if run else {}
        print(f" end: od={er['overdue_principal_date']} due={er['due_date']} tenor={er['tenor']} run={run and run['source_key']} rundate={p.get('TRANSACTION_DATE')} ARR_TOT={num(p.get('ARREAS_TOTAL'))/1e6:.2f} ARR_PRIN={num(p.get('ARREA_PRINCIPAL'))/1e6:.2f} buckets={[round(num(p.get(k))/1e6,2) for k in ('DAY_1_30','DAY_31_91','DAY_91_180','DAY_181_270','DAY_271_360')]} INST={num(p.get('INSTALLMENT_AMT'))/1e6:.2f}")
        d, due, rec, cum = indep_dpd(ch, led, me)
        print(f" indep: chart rows={len(ch)} dpd={d} oldest_unpaid={due} receipts_to_date={rec/1e6:.2f} due_to_date={cum/1e6:.2f} missed_run={missed(ch, rec, me) if ch else 'no chart'}")
    win = [r for r in led if s + '-01' <= str(r['row_date']) <= e + '-31']
    cr = [r for r in win if num(r['payload'].get('TRANSAMT')) > 0]
    print(f" ledger in window: {len(win)} postings; credits {sum(num(r['payload'].get('TRANSAMT')) for r in cr)/1e6:.2f}m in {len(cr)}; last credit {cr[-1]['row_date'] if cr else None}")
    for r in win:
        pp = r['payload']
        if pp.get('TRANTYPE') not in ('303',):
            print(f"   {r['row_date']} {pp.get('TRANTYPE')} {pp.get('BATCH_TYPE')} {num(pp.get('TRANSAMT'))/1e6:>10.2f} {(pp.get('PARTICULARS') or '')[:50]}")
if __name__ == '__main__':
    s, e = sys.argv[1], sys.argv[2]
    for c in sys.argv[3:]: card(c, s, e)
