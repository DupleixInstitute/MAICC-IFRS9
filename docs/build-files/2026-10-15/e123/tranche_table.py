import csv
import pymysql

d = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/'
ill = {r['contract_id']: r for r in csv.DictReader(open(d + 'e123/illustrate.csv'))}
nofee_before = {r['contract_id']: r for r in csv.DictReader(open(d + 'rv/eir_sandbox_nofee.csv'))}
fee_before = {r['contract_id']: r for r in csv.DictReader(open(d + 'rv/eir_sandbox.csv'))}
c = pymysql.connect(host='127.0.0.1', user='root', db='maiic_ifrs9_results2', cursorclass=pymysql.cursors.DictCursor)
cur = c.cursor()
cur.execute("""select d.contract_id, count(*) n, c.eir_nominal_annual e, c.calculation_status s, c.contractual_rate r from contract_disbursements d
  join contract_eir c on c.contract_id=d.contract_id group by d.contract_id having count(*)>1 order by d.contract_id""")
before = cur.fetchall()
c2 = pymysql.connect(host='127.0.0.1', user='root', db='maiic_ifrs9_eircheck', cursorclass=pymysql.cursors.DictCursor)
cur2 = c2.cursor()
pct = lambda x: '' if x in (None, '') else '%.2f' % (float(x) * 100)
print('| Account | Name | Rate | Drawdown dates now (rows before) | EIR before, no fee | EIR now, no fee | Fees on record (m) | EIR before, with fees | EIR now, with fees | Status now |')
print('|---|---|---:|---|---:|---:|---:|---:|---:|---|')
for b in before:
    i = b['contract_id']
    cur2.execute("select count(distinct disbursement_date) n, sum(coalesce(capitalised_fees,0)) f from contract_disbursements where contract_id=%s", (i,))
    reg = cur2.fetchone()
    cur2.execute("select customer_name from loan_books where contract_id=%s and customer_name is not null order by id desc limit 1", (i,))
    nm = (cur2.fetchone() or {}).get('customer_name', '')
    cur2.execute("select calculation_status, schedule_approval_status from contract_eir where contract_id=%s", (i,))
    st = cur2.fetchone()
    now = ill.get(i, {})
    eb_nofee = b['e'] if b['s'] == 'LOCKED' else (nofee_before.get(i, {}).get('eir_nominal') or None)
    eb_fee = fee_before.get(i, {}).get('eir_nominal') if i in fee_before else None
    status = st['calculation_status'] if st else '-'
    if st and st['schedule_approval_status'] != 'APPROVED':
        status = 'no schedule'
    print('| %s | %s | %s | %s (%s) | %s | %s | %s | %s | %s | %s |' % (i, (nm or '')[:26], pct(b['r']), reg['n'], b['n'], pct(eb_nofee), pct(now.get('eir_nofee')),
          '%.1f' % (float(now.get('fees_integral') or 0) / 1e6) if now else '', pct(eb_fee), pct(now.get('eir_with_fees')), status))
