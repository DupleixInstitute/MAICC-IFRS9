"""EIR metrics on one database: status counts, tranche EIRs, amortised cost against the ledger, revenue against posted interest."""
import json
import sys
import collections
import pymysql

db = sys.argv[1]
out = sys.argv[2] if len(sys.argv) > 2 else None
c = pymysql.connect(host='127.0.0.1', user='root', db=db, cursorclass=pymysql.cursors.DictCursor)
cur = c.cursor()
R = {}

cur.execute("select calculation_status s, count(*) n from contract_eir group by 1")
R['calculation_status'] = {r['s']: r['n'] for r in cur.fetchall()}
cur.execute("select schedule_approval_status s, coalesce(schedule_source,'-') src, count(*) n from contract_eir group by 1,2")
R['schedules'] = [(r['s'], r['src'], r['n']) for r in cur.fetchall()]
cur.execute("select count(*) n from contract_eir where locked_at is not null")
R['locked'] = cur.fetchone()['n']
cur.execute("select contract_id, calculation_error e from contract_eir where calculation_status='BLOCKED'")
reasons = collections.Counter()
blocked = {}
for r in cur.fetchall():
    e = (r['e'] or '')
    key = e.split(':')[0][:60] if not e.startswith('{') else e[:60]
    for code in ['FEE_CLASSIFICATION_PENDING', 'TAKEON_SOLVE_REFUSED', 'TAKEON_NOT_RECOMPUTED', 'PRINCIPAL_NOT_RECONCILED', 'SCHEDULE_DATE_INVALID', 'FREQUENCY_ASSUMED', 'SCHEDULE_NOT_APPROVED', 'ORIGINAL_SCHEDULE_MISSING']:
        if code in e:
            key = code
            break
    reasons[key] += 1
    blocked[r['contract_id']] = e[:300]
R['blocked_reasons'] = dict(reasons)

# tranche facilities: drawdowns on more than one date
cur.execute("""select d.contract_id, count(distinct disbursement_date) dates, count(*) n, sum(amount) tot, sum(coalesce(capitalised_fees,0)) fees,
  c.contractual_rate, c.eir_nominal_annual, c.eir_effective_annual, c.calculation_status, c.schedule_approval_status, c.locked_at, c.rate_source
  from contract_disbursements d join contract_eir c on c.contract_id=d.contract_id group by d.contract_id""")
R['tranche'] = []
for r in cur.fetchall():
    if r['n'] > 1:
        R['tranche'].append({k: (float(v) if hasattr(v, 'as_integer_ratio') or type(v).__name__ == 'Decimal' else (str(v) if v is not None else None)) for k, v in r.items()})

def rollforward(period):
    cur.execute("""select a.contract_id, a.closing_gross, a.opening_basis, lb.carrying_amount from eir_amortisation a
      left join loan_books lb on lb.contract_id=a.contract_id and replace(substr(lb.reporting_period,1,7),'-','')=%s
      where a.reporting_period=%s""", (period.replace('-', ''), period))
    rows = cur.fetchall()
    ac = sum(float(r['closing_gross']) for r in rows)
    led = sum(float(r['carrying_amount'] or 0) for r in rows)
    worst = sorted(rows, key=lambda r: -abs(float(r['closing_gross']) - float(r['carrying_amount'] or 0)))[:6]
    return {'loans': len(rows), 'amortised_cost': round(ac, 2), 'ledger': round(led, 2), 'difference': round(ac - led, 2),
            'largest': [(r['contract_id'], round(float(r['closing_gross']) / 1e6, 2), round(float(r['carrying_amount'] or 0) / 1e6, 2), r['opening_basis']) for r in worst]}

R['ac_2025_12'] = rollforward('2025-12')
R['ac_2026_09'] = rollforward('2026-09')

def revenue(p_from, p_to):
    cur.execute("""select a.contract_id, sum(a.interest_accrued) eir, sum(a.unwind_amount) unwind from eir_amortisation a
      where a.reporting_period between %s and %s group by a.contract_id""", (p_from, p_to))
    rows = {r['contract_id']: r for r in cur.fetchall()}
    if not rows:
        return {'loans': 0}
    ids = list(rows)
    fmt = ','.join(['%s'] * len(ids))
    cur.execute(f"""select contract_id, sum(interest_income_posted) p from gl_interest_postings where contract_id in ({fmt})
      and substr(reporting_period,1,7) between %s and %s group by contract_id""", ids + [p_from, p_to])
    posted = {r['contract_id']: float(r['p']) for r in cur.fetchall()}
    eir = sum(float(r['eir']) for r in rows.values())
    unwind = sum(float(r['unwind'] or 0) for r in rows.values())
    pst = sum(posted.values())
    return {'loans': len(rows), 'eir_interest': round(eir, 2), 'unwind': round(unwind, 2), 'posted': round(pst, 2), 'shift': round(eir + unwind - pst, 2)}

R['rev_2025'] = revenue('2025-01', '2025-12')
R['rev_2026'] = revenue('2026-01', '2026-09')
cur.execute("select count(*) n, count(distinct contract_id) c from eir_amortisation")
R['amortisation_rows'] = cur.fetchone()

cur.execute("show columns from contract_fees like 'superseded_by_source'")
sup = cur.fetchone() is not None
cur.execute("select source_system s, classification_status st, count(*) n, round(sum(amount),2) amt" + (", sum(superseded_by_source is not null) sup" if sup else "") + " from contract_fees group by 1,2")
R['fees'] = [{k: (float(v) if type(v).__name__ == 'Decimal' else v) for k, v in r.items()} for r in cur.fetchall()]

text = json.dumps(R, indent=1, default=str)
if out:
    open(out, 'w').write(text)
    json.dump(blocked, open(out.replace('.json', '_blocked.json'), 'w'), indent=1)
print(text)
