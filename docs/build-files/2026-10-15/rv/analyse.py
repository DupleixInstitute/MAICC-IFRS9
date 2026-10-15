"""Per-period and Dec 2025 / Sep 2026 tables from a results database. Read-only.
usage: python -I analyse.py <db> <out_json>"""
import sys, json, csv
import pymysql
from collections import defaultdict

db, out = sys.argv[1], sys.argv[2]
con = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor)
c = con.cursor()
MF = "('1050301','1050302','1050303','1050304','1050305','1050306','1050307')"
EAD = "(coalesce(carrying_amount,0)+coalesce(commitments,0)*coalesce(facility_utilisation_rate,1))"
res = {}

c.execute(f"""select reporting_period p,
  count(*) n, sum(carrying_amount) gross, sum({EAD}) ead,
  sum(case when ifrs9stage_post_qualitative='1' then {EAD} end) ead1, sum(case when ifrs9stage_post_qualitative='2' then {EAD} end) ead2, sum(case when ifrs9stage_post_qualitative='3' then {EAD} end) ead3,
  sum(case when ifrs9stage_post_qualitative='1' then 1 else 0 end) n1, sum(case when ifrs9stage_post_qualitative='2' then 1 else 0 end) n2, sum(case when ifrs9stage_post_qualitative='3' then 1 else 0 end) n3,
  sum(ecl_value) ecl, sum(case when ifrs9stage_post_qualitative='1' then ecl_value end) ecl1, sum(case when ifrs9stage_post_qualitative='2' then ecl_value end) ecl2, sum(case when ifrs9stage_post_qualitative='3' then ecl_value end) ecl3,
  sum(ecl_value_discounted) disc, sum(case when ifrs9stage_post_qualitative='1' then ecl_value_discounted end) disc1, sum(case when ifrs9stage_post_qualitative='2' then ecl_value_discounted end) disc2, sum(case when ifrs9stage_post_qualitative='3' then ecl_value_discounted end) disc3,
  sum(ecl_value is not null and ecl_value_discounted is null) undisc_n, sum(case when ecl_value_discounted is null then ecl_value end) undisc_ecl
  from loan_books where product_code not in {MF} group by 1 order by 1""")
res['maiic_by_period'] = c.fetchall()

c.execute(f"""select reporting_period p, count(*) n, sum(carrying_amount) gross,
  sum(case when ifrs9stage_post_qualitative='1' then carrying_amount end) g1, sum(case when ifrs9stage_post_qualitative='2' then carrying_amount end) g2, sum(case when ifrs9stage_post_qualitative='3' then carrying_amount end) g3,
  sum(case when ifrs9stage_post_qualitative='1' then 1 else 0 end) n1, sum(case when ifrs9stage_post_qualitative='2' then 1 else 0 end) n2, sum(case when ifrs9stage_post_qualitative='3' then 1 else 0 end) n3
  from loan_books where product_code in {MF} group by 1 order by 1""")
mf = {r['p']: r for r in c.fetchall()}
c.execute("select m.* from megafarm_ecl_runs m join (select reporting_period, max(id) id from megafarm_ecl_runs group by reporting_period) x on x.id=m.id")
for r in c.fetchall():
    b = json.loads(r['basis'] or '{}')
    mf.setdefault(r['reporting_period'], {}).update({'programme_ecl': r['programme_ecl'], 'maiic_ecl': r['maiic_ecl'], 'declined': r['declined'],
        'scalar': b.get('scalar'), 'lgd': b.get('lgd'), 'pd_by_stage': b.get('pd_by_stage'), 'lgd_basis': (b.get('basis') or {}).get('lgd_basis'),
        'measured_scalar': (b.get('basis') or {}).get('measured_scalar')})
res['megafarm_by_period'] = mf

for p in ('2025-12', '2026-09'):
    c.execute(f"""select lp.name portfolio, ifrs9stage_post_qualitative st, count(*) n, sum(carrying_amount) gross, sum({EAD}) ead,
      sum(pd_post_fli*{EAD})/nullif(sum({EAD}),0) pd12_w, sum(lgd_value*{EAD})/nullif(sum({EAD}),0) lgd_w, sum(ecl_value) ecl, sum(ecl_value_discounted) disc,
      sum(ecl_value is not null and ecl_value_discounted is null) undisc_n
      from loan_books l join loan_portfolios lp on lp.id=l.loan_portfolio_id where reporting_period=%s and product_code not in {MF} group by 1,2 order by 1,2""", (p,))
    res['by_portfolio_' + p] = c.fetchall()
    c.execute("select r.segment_key, segment_label, stage, cohort_loans, cohort_balance, default_loans, default_balance, observed_rate, r.status, applied_from_key, applied_rate, period_loans from segment_parameter_results r join segment_parameter_runs u on u.run_id=r.run_id where u.reporting_period=%s and u.measure='PD' and u.status='applied' order by r.segment_key, stage", (p,))
    res['pd_cells_' + p] = c.fetchall()
    c.execute("select r.* from segment_parameter_results r join segment_parameter_runs u on u.run_id=r.run_id where u.reporting_period=%s and u.measure='LGD' and u.status='applied'", (p,))
    res['lgd_cells_' + p] = c.fetchall()
c.execute("select reporting_period, measure, status, left(failure_reason,300) reason from segment_parameter_runs order by reporting_period, measure, id")
res['segment_runs'] = c.fetchall()
c.execute("select reporting_period, start_period, lgd_calculation_level, lgd_calculation_code, start_total_stage3, cure_rate, recovery_rate, loss_given_default_percentage, written_offs from loss_given_default where reporting_period in ('2025-12-01','2026-09-01')")
res['lgd_rows'] = c.fetchall()
# EIR state
c.execute("select calculation_status, schedule_source, schedule_approval_status, count(*) n from contract_eir group by 1,2,3")
res['eir_status'] = c.fetchall()
c.execute("select reporting_period, count(*) n, sum(opening_gross) op, sum(interest_accrued) eirint, sum(cash_received) cash, sum(closing_gross) cl, sum(opening_basis='LEDGER') ledger_n from eir_amortisation group by 1 order by 1")
res['eir_rollforward'] = c.fetchall()


def conv(o):
    if isinstance(o, dict):
        return {k: conv(v) for k, v in o.items()}
    if isinstance(o, list):
        return [conv(v) for v in o]
    try:
        import decimal, datetime
        if isinstance(o, decimal.Decimal):
            return float(o)
        if isinstance(o, (datetime.date, datetime.datetime)):
            return o.isoformat()
    except Exception:
        pass
    return o


json.dump(conv(res), open(out, 'w', encoding='utf-8'), indent=1, default=str)
print('written', out)
