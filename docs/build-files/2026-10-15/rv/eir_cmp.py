"""EIR comparison: Dr Thom's December 2025 EIR assessment (34 loans of 2025) against
the system's contract_eir, fees, schedules and roll-forward on a database. Read-only.
Also computes, for illustration only, the EIR each loan would carry if the fees on
record in E-Banker (contract_fees, pending review) were integral: the IRR of the
version 1 schedule against the drawn amount net of those fees. Nothing is written.

usage: python -I eir_cmp.py <db> <eir_assess_csv> <out_csv>
"""
import sys, csv, datetime as dt
import pymysql

db, src, out = sys.argv[1:4]
con = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor)
c = con.cursor()


def xirr(flows):
    """flows: list of (date, amount); annual effective rate by Newton/bisection."""
    t0 = flows[0][0]
    def npv(r):
        return sum(a / (1 + r) ** ((d - t0).days / 365.0) for d, a in flows)
    lo, hi = -0.9, 5.0
    if npv(lo) * npv(hi) > 0:
        return None
    for _ in range(200):
        mid = (lo + hi) / 2
        if npv(lo) * npv(mid) <= 0:
            hi = mid
        else:
            lo = mid
    return (lo + hi) / 2


rows = list(csv.reader(open(src, encoding='utf-8')))[1:]
res = []
for x in rows:
    acct = x[3].strip()
    if not acct or not acct.startswith('0'):
        continue
    cid = acct.lstrip('0')
    rate = float(x[11] or 0)
    principal = float(x[15] or 0)
    legal = sum(float(v or 0) for v in (x[18], x[19]))
    arr = float(x[20] or 0)
    appr = float(x[17] or 0)
    thom_fees = legal + arr + appr + float(x[21] or 0)
    thom_eir = float(x[23] or 0)
    c.execute('select * from contract_eir where contract_id=%s', (cid,))
    e = c.fetchone()
    c.execute("select fee_type, classification_status, sum(amount) a, count(*) n from contract_fees where contract_id=%s group by fee_type, classification_status", (cid,))
    fees = c.fetchall()
    los_fees = sum(float(f['a']) for f in fees)
    c.execute("select due_date, principal_due+interest_due+fee_due amt, schedule_source from contract_cashflow_schedule where contract_id=%s and schedule_version=1 order by due_date", (cid,))
    sched = c.fetchall()
    c.execute("select disbursement_date d, amount a from contract_disbursements where contract_id=%s order by disbursement_date", (cid,))
    tranches = c.fetchall()
    illus = illus_thom = None
    if e and sched and e['origination_date']:
        orig = e['origination_date'] if isinstance(e['origination_date'], dt.date) else dt.date.fromisoformat(str(e['origination_date'])[:10])
        draws = [(t['d'] if isinstance(t['d'], dt.date) else dt.date.fromisoformat(str(t['d'])[:10]), -float(t['a'])) for t in tranches] or [(orig, -float(e['drawn_amount'] or 0))]
        cf = [(s['due_date'] if isinstance(s['due_date'], dt.date) else dt.date.fromisoformat(str(s['due_date'])[:10]), float(s['amt'])) for s in sched]
        base = sorted(draws + cf)
        for fee_total, key in ((los_fees, 'illus'), (thom_fees, 'illus_thom')):
            f = list(base)
            f[0] = (f[0][0], f[0][1] + fee_total)
            v = xirr(f)
            if key == 'illus':
                illus = v
            else:
                illus_thom = v
        no_fee = xirr(base)
    else:
        no_fee = None
    c.execute("select sum(interest_accrued) eirint, min(reporting_period) p0, max(reporting_period) p1 from eir_amortisation where contract_id=%s and reporting_period between '2026-01' and '2026-09'", (cid,))
    am = c.fetchone()
    c.execute("select closing_gross from eir_amortisation where contract_id=%s and reporting_period='2025-12'", (cid,))
    ac = c.fetchone()
    c.execute("select carrying_amount from loan_books where contract_id=%s and reporting_period='2025-12'", (cid,))
    lb = c.fetchone()
    res.append({
        'account': acct, 'name': x[5], 'value_date': x[7], 'rate_thom': rate, 'principal_thom': principal,
        'fees_thom': thom_fees, 'eir_thom': thom_eir, 'eir_thom_minus_rate': thom_eir - rate,
        'los_fees_on_record': los_fees, 'los_fee_status': ';'.join(f"{f['fee_type']}:{f['classification_status']}:{round(float(f['a']))}" for f in fees),
        'fee_diff_vs_thom': los_fees - thom_fees,
        'eir_status': e['calculation_status'] if e else 'NO CONTRACT', 'schedule_source': e['schedule_source'] if e else '',
        'calc_error': (e['calculation_error'] or '')[:90] if e else '', 'contractual_rate': float(e['contractual_rate']) if e and e['contractual_rate'] is not None else None,
        'eir_locked_eff': float(e['eir_effective_annual']) if e and e['locked_at'] and e['eir_effective_annual'] is not None else None,
        'irr_no_fees': no_fee, 'irr_with_los_fees': illus, 'irr_with_thom_fees': illus_thom,
        'tranches': len(tranches), 'amortised_cost_dec2025': float(ac['closing_gross']) if ac else None,
        'carrying_dec2025': float(lb['carrying_amount']) if lb else None, 'eir_interest_2026': float(am['eirint']) if am and am['eirint'] is not None else None,
    })
w = csv.DictWriter(open(out, 'w', newline='', encoding='utf-8'), fieldnames=list(res[0].keys()))
w.writeheader()
w.writerows(res)
print(len(res), 'loans')
