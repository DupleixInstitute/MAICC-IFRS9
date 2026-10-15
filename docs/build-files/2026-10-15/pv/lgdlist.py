import sys, json, pymysql
db, s, e, which = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
c = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor).cursor()
cond = "s.loan_portfolio_id=8" if which == 'mf' else "s.loan_portfolio_id<>8"
c.execute(f"""select s.contract_id cid, left(s.customer_name,26) name, s.carrying_amount sb, s.repayments sr, s.build_basis sbb, e.carrying_amount eb, e.repayments er, e.build_basis ebb,
  COALESCE(e.calculated_ifrs9_stage,'3') cs, e.contract_id ecid from loan_books s left join loan_books e on e.contract_id=s.contract_id and e.loan_portfolio_id=s.loan_portfolio_id and e.reporting_period=%s
  where s.reporting_period=%s and s.calculated_ifrs9_stage='3' and {cond}""", (e, s))
wo = lambda b: (json.loads(b).get('ledger', {}).get('written_off', 0) or 0) if b else 0
tot = {'n': 0, 'sb': 0, 'cured': 0, 'cured_n': 0, 'nil_repaid': 0, 'nil_wo': 0, 'nil_n': 0, 'absent': 0, 'stay3': 0, 'rec': 0, 'wo': 0, 'nil_start': 0}
rows = []
for r in c.fetchall():
    sb = float(r['sb'])
    if sb <= 0.005: tot['nil_start'] += 1; continue
    tot['n'] += 1; tot['sb'] += sb
    eb = None if r['eb'] is None else float(r['eb'])
    rep = max(0.0, float(r['er'] or 0) - float(r['sr'] or 0)) if r['ecid'] else 0.0
    w = wo(r['ebb']) - wo(r['sbb'])
    if r['ecid'] is None: kind = 'absent'; tot['absent'] += sb
    elif r['cs'] in ('1', '2') and eb > 0.005: kind = 'cure'; tot['cured'] += sb; tot['cured_n'] += 1
    elif eb is not None and eb <= 0.005:
        kind = 'nil'; tot['nil_n'] += 1
        if rep >= sb - 0.005: tot['nil_repaid'] += sb
        else: tot['nil_wo'] += sb
    else: kind = 'stage3'; tot['stay3'] += sb
    tot['rec'] += min(rep, sb) if kind != 'cure' else 0; tot['wo'] += max(0, w)
    rows.append((kind, r['cid'], r['name'], sb, eb, rep, w))
print({k: (round(v / 1e6, 1) if isinstance(v, float) else v) for k, v in tot.items()})
if which != 'mf':
    for k in sorted(rows, key=lambda x: (x[0], -x[3])):
        print(f"  {k[0]:7s} {k[1]} {k[2]:26s} start {k[3]/1e6:8.2f} end {('-' if k[4] is None else f'{k[4]/1e6:8.2f}')} receipts {k[5]/1e6:8.2f} written off {k[6]/1e6:6.2f}")
