"""Every start-of-window loan of the MAIIC scope and its end grade, per PdSegmentationService rules (pdcheck)."""
import sys, json, pymysql
db, s, e = sys.argv[1], sys.argv[2], sys.argv[3]
c = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor).cursor()
G = lambda a: f"COALESCE(NULLIF({a}.ifrs9stage_post_qualitative,''),NULLIF({a}.calculated_ifrs9_stage,''),{a}.ifrs9stage_pre_qualitative)"
c.execute(f"select distinct loan_portfolio_id from loan_books where reporting_period=%s and loan_portfolio_id is not null and product_code not in ('1050301','1050302','1050303','1050304','1050305','1050306','1050307')", (e,))
pf = [r['loan_portfolio_id'] for r in c.fetchall()]
c.execute(f"""select s.contract_id cid, left(s.customer_name,28) name, s.loan_portfolio_id pf, s.carrying_amount sb, {G('s')} sg, s.overdue_days sdpd, s.build_basis sbb,
  e.contract_id ecid, {G('e')} eg, e.ifrs9stage_pre_qualitative epre, e.overdue_days edpd, e.carrying_amount eb, e.build_basis ebb, e.build_flag ef
  from loan_books s left join loan_books e on e.contract_id=s.contract_id and e.reporting_period=%s
  where s.reporting_period=%s and s.loan_portfolio_id in ({','.join(str(x) for x in pf)})""", (e, s))
wo = lambda b: (json.loads(b).get('ledger', {}).get('written_off', 0) or 0) if b else 0
out = []
for r in c.fetchall():
    sb = float(r['sb'])
    if sb <= 0.005: continue
    end = r['eg']; how = ''
    if r['ecid'] is None:
        c.execute("select carrying_amount, build_basis from loan_books where contract_id=%s and reporting_period>=%s and reporting_period<%s order by reporting_period desc limit 1", (r['cid'], s, e))
        last = c.fetchone(); end = 'Paid' if last and float(last['carrying_amount']) <= 0 else '3'; how = 'exit'
        ebb = last['build_basis'] if last else None
    else:
        ebb = r['ebb']
    if end != '3' and wo(ebb) > wo(r['sbb']) + 0.005:
        end = '3'; how = 'write-off'
    out.append((r['sg'], end, r['cid'], r['name'], sb, r['sdpd'], r['epre'], r['edpd'], float(r['eb'] or 0), how, (r['ef'] or '')[:60]))
for st in ('1', '2'):
    rows = [o for o in out if o[0] == st]
    tot = sum(o[4] for o in rows); d = [o for o in rows if o[1] == '3']
    print(f"Stage {st}: {len(rows)} loans {tot/1e6:,.1f}m; defaults {len(d)} {sum(o[4] for o in d)/1e6:,.1f}m = {sum(o[4] for o in d)/tot:.4f}")
    for o in sorted(d, key=lambda o: -o[4]):
        print(f"   {o[2]} {o[3]:28s} start {o[4]/1e6:8.2f} dpd {o[5]} -> pre {o[6]} dpd {o[7]} end bal {o[8]/1e6:8.2f} {o[9]} {o[10]}")
