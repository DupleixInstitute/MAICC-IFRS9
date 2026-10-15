"""Every figure of the 14 Oct 2026 results review, from one results database. Read-only
(SELECT only). usage: python -I figures.py <db> <rv_dir> <out_json>
<rv_dir> holds eclcomp_dec2025.csv and mf_eclcomp.csv (MAIIC's December 2025 models, row by row)."""
import sys, json, csv, decimal, datetime
from collections import defaultdict
import pymysql

db, rv, out = sys.argv[1], sys.argv[2], sys.argv[3]
con = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor)
c = con.cursor()
c.execute('SET SESSION TRANSACTION READ ONLY')
MF = "('1050301','1050302','1050303','1050304','1050305','1050306','1050307')"
WO = "CAST(COALESCE(json_extract(build_basis, '$.ledger.written_off'), 0) AS DECIMAL(20,2))"
GROSS = "(COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))"
EAD = f"(CASE WHEN (COALESCE(carrying_amount,0) <= 0 AND {WO} > 0) THEN 0 WHEN {GROSS} > 0 THEN {GROSS} ELSE 0 END)"
DISC = "(ecl_discount_status='CALCULATED_TIME_PHASED' AND ecl_value_discounted IS NOT NULL)"
SHOWN = f"(CASE WHEN {DISC} THEN ecl_value_discounted ELSE COALESCE(ecl_value,0) END)"
ST = "ifrs9stage_post_qualitative"
R = {'db': db}


def q(sql, args=None):
    c.execute(sql, args)
    return c.fetchall()


def one(sql, args=None):
    c.execute(sql, args)
    return c.fetchone()


# ---------------------------------------------------------------- MAIIC book by month
R['by_period'] = q(f"""select reporting_period p, count(*) n, sum(carrying_amount>0) n_bal, sum(carrying_amount) gross,
  sum(case when carrying_amount<0 then carrying_amount else 0 end) credit_bal,
  sum(commitments*coalesce(facility_utilisation_rate,1)) undrawn_util, sum({EAD}) ead,
  sum({ST}='1') n1, sum({ST}='2') n2, sum({ST}='3') n3,
  sum(case when {ST}='1' then {EAD} end) ead1, sum(case when {ST}='2' then {EAD} end) ead2, sum(case when {ST}='3' then {EAD} end) ead3,
  sum(ecl_value) ecl, sum(case when {ST}='1' then ecl_value end) ecl1, sum(case when {ST}='2' then ecl_value end) ecl2, sum(case when {ST}='3' then ecl_value end) ecl3,
  sum({DISC}) disc_n, sum(case when {DISC} then ecl_value_discounted end) disc, sum(case when {DISC} then ecl_value end) disc_undisc,
  sum(ecl_value is not null and not {DISC}) undisc_n, sum(case when ecl_value is not null and not {DISC} then ecl_value end) undisc,
  sum(case when ecl_value is not null and not {DISC} and ecl_value>0 then 1 else 0 end) undisc_pos_n,
  sum({SHOWN}) shown, sum(ecl_value is null) none_n
  from loan_books where product_code not in {MF} group by 1 order by 1""")

# Mega Farm by month
mfp = {r['p']: r for r in q(f"""select reporting_period p, count(*) n, sum(carrying_amount>0) n_bal, sum(carrying_amount) gross,
  sum(case when carrying_amount>0 then carrying_amount else 0 end) gross_pos, sum({EAD}) ead,
  sum({ST}='1') n1, sum({ST}='2') n2, sum({ST}='3') n3,
  sum(case when {ST}='1' then carrying_amount end) g1, sum(case when {ST}='2' then carrying_amount end) g2, sum(case when {ST}='3' then carrying_amount end) g3,
  sum(case when {ST}='3' and carrying_amount>0 then carrying_amount else 0 end) g3pos
  from loan_books where product_code in {MF} group by 1 order by 1""")}
for r in q("select m.* from megafarm_ecl_runs m join (select reporting_period, max(id) id from megafarm_ecl_runs group by reporting_period) x on x.id=m.id"):
    b = json.loads(r['basis'] or '{}')
    bb = b.get('basis') or {}
    mfp.setdefault(r['reporting_period'], {}).update({
        'programme_ecl': r['programme_ecl'], 'maiic_ecl': r['maiic_ecl'], 'share': r['share'], 'declined': r['declined'], 'scope': r['scope'],
        'scalar': b.get('scalar'), 'lgd': b.get('lgd'), 'pd_by_stage': b.get('pd_by_stage'), 'sector_pd_by_stage': bb.get('sector_pd_by_stage'),
        'lgd_basis': bb.get('lgd_basis')})
R['mf_by_period'] = mfp

# month records
R['fli_records'] = q("""select s.reporting_period p, s.id, s.status, s.loans, s.booked_ecl, s.weighted_ecl, s.reconciles, s.discounted_loans,
  s.discount_unresolved_loans, s.unresolved_ecl, s.booked_ecl_discounted, s.weighted_ecl_discounted, s.discounted_reconciles, s.programme_ecl
  from fli_run_snapshots s join (select reporting_period, max(id) id from fli_run_snapshots group by reporting_period) x on x.id=s.id order by 1""")

# ---------------------------------------------------------------- by stage and by portfolio, Dec 2025 and Sep 2026
for p in ('2025-12', '2026-09'):
    R['stage_' + p] = q(f"""select {ST} st, count(*) n, sum(carrying_amount>0) n_bal, sum(carrying_amount) gross, sum({EAD}) ead,
      sum(case when {ST} in ('1','2') then pd_post_fli else 1 end * {EAD})/nullif(sum({EAD}),0) pd12,
      sum(coalesce(lifetime_pd, case when {ST}='3' then 1 end) * {EAD})/nullif(sum({EAD}),0) pdlt,
      sum(lgd_value*{EAD})/nullif(sum({EAD}),0) lgd,
      sum(ecl_value) ecl, sum(case when {DISC} then ecl_value_discounted end) disc, sum({DISC}) disc_n,
      sum(case when {DISC} then ecl_value end) disc_undisc,
      sum(ecl_value is not null and not {DISC}) undisc_n, sum(case when not {DISC} then ecl_value end) undisc, sum({SHOWN}) shown
      from loan_books where reporting_period=%s and product_code not in {MF} group by 1 order by 1""", (p,))
    R['portfolio_' + p] = q(f"""select lp.name portfolio, {ST} st, count(*) n, sum(carrying_amount) gross, sum({EAD}) ead,
      sum(case when {ST} in ('1','2') then pd_post_fli else 1 end * {EAD})/nullif(sum({EAD}),0) pd12,
      sum(lgd_value*{EAD})/nullif(sum({EAD}),0) lgd, sum(ecl_value) ecl, sum(case when {DISC} then ecl_value_discounted end) disc,
      sum(ecl_value is not null and not {DISC}) undisc_n, sum({SHOWN}) shown
      from loan_books l join loan_portfolios lp on lp.id=l.loan_portfolio_id where reporting_period=%s and product_code not in {MF} group by 1,2 order by 1,2""", (p,))
    R['discstatus_' + p] = q(f"""select {ST} st, coalesce(ecl_discount_status,'NONE') status, coalesce(ecl_discount_rate_source,'-') src, count(*) n,
      sum(ecl_value) ecl, sum(ecl_value_discounted) disc from loan_books where reporting_period=%s and product_code not in {MF} group by 1,2,3 order by 1,2,3""", (p,))
    R['pd_cells_' + p] = q("""select segment_key, stage, cohort_loans, cohort_balance, default_loans, default_balance, observed_rate, status, applied_from_key, applied_rate
      from segment_parameter_results where measure='PD' and reporting_period=%s order by segment_key, stage""", (p,))
    R['lgd_' + p] = q("""select lgd_calculation_code code, start_period, start_total_stage3 s3, cure_rate, recovery_rate, loss_given_default_percentage lgd, written_offs, cured_amount, recovered_amount
      from loss_given_default where reporting_period like %s order by id""", (p + '%',))
    R['undrawn_top_' + p] = q(f"""select contract_id, customer_name, carrying_amount, commitments, facility_utilisation_rate u, due_date from loan_books
      where reporting_period=%s and product_code not in {MF} and commitments>0 order by commitments*coalesce(facility_utilisation_rate,1) desc limit 8""", (p,))
    R['written_off_nil_' + p] = one(f"""select count(*) n, sum(commitments) com from loan_books where reporting_period=%s and product_code not in {MF}
      and COALESCE(carrying_amount,0) <= 0 AND {WO} > 0""", (p,))
    R['credit_' + p] = one(f"""select count(*) n, sum(carrying_amount) amt from loan_books where reporting_period=%s and product_code not in {MF} and carrying_amount<0""", (p,))
    R['mf_stage_' + p] = q(f"""select {ST} st, count(*) n, sum(carrying_amount>0) n_bal, sum(carrying_amount) gross, sum(case when carrying_amount<0 then 1 else 0 end) n_credit,
      sum(case when carrying_amount<0 then carrying_amount else 0 end) credit, sum({EAD}) ead, sum(ecl_value) ecl
      from loan_books where reporting_period=%s and product_code in {MF} group by 1 order by 1""", (p,))

# ---------------------------------------------------------------- PD default events, loan by loan
def window(s, e):
    G = lambda a: f"COALESCE(NULLIF({a}.ifrs9stage_post_qualitative,''),NULLIF({a}.calculated_ifrs9_stage,''),{a}.ifrs9stage_pre_qualitative)"
    rows = q(f"""select s.contract_id cid, s.customer_name name, s.carrying_amount sb, {G('s')} sg, s.overdue_days sdpd, s.build_basis sbb,
      e.contract_id ecid, {G('e')} eg, e.ifrs9stage_pre_qualitative epre, e.overdue_days edpd, e.carrying_amount eb, e.build_basis ebb
      from loan_books s left join loan_books e on e.contract_id=s.contract_id and e.reporting_period=%s
      where s.reporting_period=%s and s.product_code not in {MF} and s.loan_portfolio_id is not null""", (e, s))
    wo = lambda b: (json.loads(b).get('ledger', {}).get('written_off', 0) or 0) if b else 0
    res = []
    for r in rows:
        sb = float(r['sb'] or 0)
        if sb <= 0.005 or r['sg'] not in ('1', '2'):
            continue
        end, how = r['eg'], ''
        ebb = r['ebb']
        if r['ecid'] is None:
            last = one("select carrying_amount, build_basis from loan_books where contract_id=%s and reporting_period>=%s and reporting_period<%s order by reporting_period desc limit 1", (r['cid'], s, e))
            end = 'paid' if last and float(last['carrying_amount']) <= 0 else '3'
            how = 'left the book'
            ebb = last['build_basis'] if last else None
        if end != '3' and wo(ebb) > wo(r['sbb']) + 0.005:
            end, how = '3', 'written off'
        elif end == '3' and not how:
            how = 'trigger' if (r['epre'] or '') != '3' else 'days past due'
        res.append({'cid': r['cid'], 'name': r['name'], 'start_stage': r['sg'], 'start': sb, 'start_dpd': r['sdpd'], 'end': end, 'end_pre': r['epre'],
                    'end_dpd': r['edpd'], 'end_bal': float(r['eb'] or 0), 'how': how})
    return res


R['pd_window_2025-12'] = window('2024-12', '2025-12')
R['pd_window_2026-09'] = window('2025-09', '2026-09')

# ---------------------------------------------------------------- LGD cohorts, loan by loan
def cohort(s, e):
    rows = q(f"""select s.contract_id cid, s.customer_name name, s.carrying_amount sb, s.repayments sr, s.build_basis sbb, e.carrying_amount eb, e.repayments er,
      e.build_basis ebb, COALESCE(e.calculated_ifrs9_stage,'3') cs, e.contract_id ecid from loan_books s left join loan_books e on e.contract_id=s.contract_id and e.reporting_period=%s
      where s.reporting_period=%s and s.calculated_ifrs9_stage='3' and s.product_code not in {MF}""", (e, s))
    wo = lambda b: (json.loads(b).get('ledger', {}).get('written_off', 0) or 0) if b else 0
    res = []
    for r in rows:
        sb = float(r['sb'] or 0)
        if sb <= 0.005:
            continue
        eb = None if r['eb'] is None else float(r['eb'])
        rep = max(0.0, float(r['er'] or 0) - float(r['sr'] or 0)) if r['ecid'] else 0.0
        w = wo(r['ebb']) - wo(r['sbb'])
        if r['ecid'] is None:
            kind = 'left the book'
        elif r['cs'] in ('1', '2') and eb > 0.005:
            kind = 'cure'
        elif eb is not None and eb <= 0.005:
            kind = 'repaid to nil' if rep >= sb - 0.005 else 'closed at nil (written off)'
        else:
            kind = 'still Stage 3'
        res.append({'cid': r['cid'], 'name': r['name'], 'start': sb, 'end': eb, 'receipts': rep, 'written_off': w, 'kind': kind})
    return res


R['lgd_cohort_2025-12'] = cohort('2024-12', '2025-12')
R['lgd_cohort_2026-09'] = cohort('2025-09', '2026-09')

# ---------------------------------------------------------------- loan-level Dec 2025 for the walk
ours = q(f"""select contract_id, customer_name, product_group, loan_portfolio_id pf, carrying_amount, commitments, coalesce(facility_utilisation_rate,1) util,
  {EAD} ead_o, overdue_days dpd, remaining_tenor, tenor, {ST} stage, ifrs9stage_pre_qualitative pre, pd_post_fli pd12, lifetime_pd pdlt, lgd_value lgd,
  ecl_value ecl, ecl_value_discounted disc, ecl_discount_status ds, {SHOWN} shown, interest_rate, due_date,
  arrears_91_to_180 a91, arrears_180_to_270 a181, arrears_271_to_360 a271
  from loan_books where reporting_period='2025-12' and product_code not in {MF}""")
theirs = list(csv.DictReader(open(rv + '/eclcomp_dec2025.csv', encoding='utf-8')))
f = lambda v: float(v) if v not in ('', None, 'None') else 0.0
used = set()
pairs = []
for t in theirs:
    exp = f(t['Y'])
    cands = [o for o in ours if o['contract_id'] not in used and abs(float(o['carrying_amount'] or 0) - exp) < 0.02] if abs(exp) > 0.01 else []
    m = None
    if len(cands) == 1:
        m = cands[0]
    elif len(cands) > 1:
        import difflib, re
        norm = lambda s: re.sub(r'[^a-z0-9]', '', re.sub(r'\b(limited|ltd|plc|company|co)\b', '', (s or '').lower()))
        m = max(cands, key=lambda o: difflib.SequenceMatcher(None, norm(o['customer_name']), norm(t['E'])).ratio())
    if m:
        used.add(m['contract_id'])
    pairs.append((t, m))
only_ours = [o for o in ours if o['contract_id'] not in used]

# MAIIC model by stage (12-month AK, lifetime AL, applied AR = AL x (1 + AO))
ms = defaultdict(lambda: defaultdict(float))
for t in theirs:
    s = t['R']; e = f(t['AV'])
    d = ms[s]
    d['n'] += 1; d['gross'] += f(t['Y']); d['ead'] += e; d['undrawn'] += f(t['AU']); d['ak'] += f(t['AK']) * e; d['al'] += f(t['AL']) * e
    d['ar'] += f(t['AR']) * e; d['lgd'] += f(t['AS']) * e; d['ecl'] += f(t['BC']); d['pvfcl'] += f(t['BB'])
    d['ecl_norec'] += f(t['AR']) * f(t['AS']) * max(e, 0)
R['maiic_model_stage'] = {s: {k: v for k, v in d.items()} for s, d in ms.items()}

# walk
B = [0.0298, 0.0609, 0.0846, 0.1045, 0.1196, 0.1311, 0.1414, 0.1501, 0.1575, 0.1652]
MACRO = {1: 0.13517635, 2: 0.153822359375, 3: 0.1575, 4: 0.15425, 5: 0.1375, 6: 0.137775}
for y in range(7, 11):
    MACRO[y] = 0.14247517739583335


def their_pd(stage, years):
    y = max(1, min(10, int(round(years or 1))))
    if stage == '3':
        return 1.0
    if stage == '2':
        return min(1.0, B[y - 1] * (1 + MACRO[y]))
    return 0.0003 * 1.1575


E = [0.0] * 7
by_stage_walk = defaultdict(lambda: [0.0] * 7)
loans = []
for t, o in pairs:
    if o is None:
        continue
    pdt, lgdt, eadt = f(t['AR']), f(t['AS']), max(f(t['AV']), 0)
    st = o['stage'] or '1'
    eado = float(o['ead_o'] or 0)
    lgdo = float(o['lgd'] or 0)
    eclo = float(o['ecl'] or 0)
    pdo = eclo / (lgdo * eado) if lgdo * eado > 0 else (1.0 if st == '3' else float(o['pd12'] or 0))
    v = [f(t['BC']), pdt * lgdt * eadt, their_pd(st, f(t['AY'])) * lgdt * eadt, pdo * lgdt * eadt, pdo * lgdo * eadt, eclo, float(o['shown'] or 0)]
    for i in range(7):
        E[i] += v[i]
        by_stage_walk[st][i] += v[i]
    loans.append({'cid': o['contract_id'], 'name': o['customer_name'], 'bracket': t['P'], 'their_stage': t['R'], 'our_stage': st, 'our_pre': o['pre'],
                  'dpd': o['dpd'], 'their_ead': eadt, 'our_ead': eado, 'their_pd': pdt, 'our_pd': pdo, 'their_lgd': lgdt, 'our_lgd': lgdo,
                  'their_ecl': f(t['BC']), 'our_ecl': eclo, 'our_shown': float(o['shown'] or 0), 'their_pvfcl': f(t['BB']),
                  'eb_over90': float(o['a91'] or 0) + float(o['a181'] or 0) + float(o['a271'] or 0), 'their_rate': f(t['AW']), 'their_years': f(t['AY'])})
only_t = sum(f(t['BC']) for t, o in pairs if o is None)
only_o_ecl = sum(float(o['ecl'] or 0) for o in only_ours)
only_o_shown = sum(float(o['shown'] or 0) for o in only_ours)
R['walk'] = {'steps': E, 'only_theirs_ecl': only_t, 'only_ours_ecl': only_o_ecl, 'only_ours_shown': only_o_shown,
             'only_ours': [{'cid': o['contract_id'], 'name': o['customer_name'], 'gross': float(o['carrying_amount'] or 0), 'ecl': float(o['ecl'] or 0)} for o in only_ours if abs(float(o['carrying_amount'] or 0)) > 0.005 or float(o['ecl'] or 0) > 0],
             'matched': sum(1 for t, o in pairs if o is not None), 'maiic_rows': len(theirs),
             'unmatched_theirs': [{'name': t['E'], 'exposure': f(t['Y']), 'ecl': f(t['BC'])} for t, o in pairs if o is None],
             'by_stage': {k: v for k, v in by_stage_walk.items()}}
R['walk_loans'] = loans
# stage matrix and causes
mat = defaultdict(lambda: [0, 0.0, 0.0, 0.0])
causes = defaultdict(lambda: [0, 0.0, 0.0, 0.0])
for l in loans:
    k = (l['their_stage'], l['our_stage'])
    mat[k][0] += 1; mat[k][1] += l['their_ead']; mat[k][2] += l['their_ecl']; mat[k][3] += l['our_ecl']
    ts, os_, pre = int(l['their_stage'] or 1), int(l['our_stage'] or 1), int(l['our_pre'] or 1)
    if os_ == ts:
        cz = 'same'
    elif os_ < ts:
        cz = 'maiic_higher'
    elif pre > ts:
        cz = 'ageing'
    else:
        cz = 'trigger'
    l['cause'] = cz
    causes[cz][0] += 1; causes[cz][1] += l['their_ead']; causes[cz][2] += l['their_ecl']; causes[cz][3] += l['our_ecl']
R['stage_matrix'] = [{'their': k[0], 'ours': k[1], 'n': v[0], 'ead': v[1], 'their_ecl': v[2], 'our_ecl': v[3]} for k, v in sorted(mat.items())]
R['stage_causes'] = {k: v for k, v in causes.items()}

# ---------------------------------------------------------------- Mega Farm, Dec 2025 against MAIIC's model
mft = list(csv.DictReader(open(rv + '/mf_eclcomp.csv', encoding='utf-8')))
mfo = {r['acc']: r for r in q(f"""select external_identity_id acc, carrying_amount ca, {ST} st from loan_books where reporting_period='2025-12' and product_code in {MF}""")}
mm = defaultdict(lambda: [0, 0.0, 0.0])
matched = 0
for t in mft:
    o = mfo.get(t['B'])
    if not o:
        continue
    matched += 1
    k = t['R'] + '/' + str(o['st'])
    mm[k][0] += 1; mm[k][1] += f(t['AV']); mm[k][2] += float(o['ca'] or 0)
mstage = defaultdict(lambda: [0, 0.0, 0.0])
for t in mft:
    mstage[t['R']][0] += 1; mstage[t['R']][1] += f(t['AV']); mstage[t['R']][2] += f(t['BC'])
R['mf_compare'] = {'rows': len(mft), 'matched': matched, 'model_ead': sum(f(t['AV']) for t in mft), 'model_ecl': sum(f(t['BC']) for t in mft),
                   'matrix': {k: v for k, v in mm.items()}, 'model_stage': {k: v for k, v in mstage.items()},
                   'ours_gross': sum(float(r['ca'] or 0) for r in mfo.values()), 'ours_accounts': len(mfo)}

# ---------------------------------------------------------------- EIR
R['eir_status'] = q("select calculation_status, schedule_source, count(*) n from contract_eir group by 1,2 order by 1,2")
blk = q("select contract_id, calculation_status, calculation_error from contract_eir where calculation_status<>'LOCKED'")
cats = defaultdict(int)
for b in blk:
    e = b['calculation_error'] or ''
    if b['calculation_status'] == 'PENDING':
        cats['no schedule'] += 1
    elif 'take-on EIR refused' in e:
        cats['take-on check refused (and fee review)'] += 1
    elif 'not independently reviewed' in e:
        cats['fee review only'] += 1
    else:
        cats['other: ' + e[:120]] += 1
R['eir_block_cats'] = dict(cats)
R['eir_locked'] = q("""select contract_id, customer_name, schedule_source, contractual_rate, eir_nominal_annual, eir_effective_annual, floating_flag from contract_eir
  where calculation_status='LOCKED' order by contract_id""")
R['takeon'] = q("select coalesce(eir_check,'NONE') chk, count(*) n, min(recomputed_eir) lo, max(recomputed_eir) hi from contract_takeon group by 1")
R['takeon_refused'] = q("select account, contract_id, left(eir_check_reason,300) reason from contract_takeon where eir_check='REFUSED'")
R['pending_schedule'] = q("""select e.contract_id, e.customer_name, left(coalesce(e.schedule_review_notes,''),200) notes,
  (select carrying_amount from loan_books l where l.contract_id=e.contract_id and l.reporting_period='2026-09' limit 1) ca
  from contract_eir e where calculation_status='PENDING'""")
R['fees'] = q("""select source_system, classification_status, superseded_by_source is not null sup, count(*) n, count(distinct contract_id) contracts, sum(amount) amt
  from contract_fees group by 1,2,3 order by 1,2,3""")
R['fee_gl_unassigned'] = one("select count(*) n, sum(amount) amt, sum(candidate_contract_id is not null) cand_n, sum(case when candidate_contract_id is not null then amount end) cand_amt from fee_gl_unassigned")
R['fees_by_contract'] = q("""select contract_id, source_system, sum(amount) amt from contract_fees where superseded_by_source is null group by 1,2""")
R['tranches'] = q("""select d.contract_id, e.customer_name, count(*) n, sum(d.amount) amt, min(d.disbursement_date) first, max(d.disbursement_date) last
  from contract_disbursements d left join contract_eir e on e.contract_id=d.contract_id group by 1,2 having count(*)>1 order by amt desc""") if one("select count(*) n from information_schema.columns where table_schema=%s and table_name='contract_disbursements' and column_name='disbursement_date'", (db,))['n'] else []

# roll-forward against the ledger and the posted interest
rf = q("""select a.contract_id, a.reporting_period p, a.opening_gross, a.advances, a.opening_basis, a.eir_annual, a.interest_accrued, a.interest_basis, a.unwind_amount,
  a.cash_received, a.closing_gross, a.ecl_allowance, l.carrying_amount ca, l.ifrs9stage_post_qualitative st, g.posted
  from eir_amortisation a left join loan_books l on l.contract_id=a.contract_id and l.reporting_period=a.reporting_period
  left join (select contract_id, left(reporting_period,7) p, sum(interest_income_posted) posted from gl_interest_postings group by 1,2) g on g.contract_id=a.contract_id and g.p=a.reporting_period
  order by a.contract_id, a.reporting_period""")
R['rollforward'] = rf
yr = defaultdict(lambda: defaultdict(float))
for r in rf:
    y = r['p'][:4] if r['p'] < '2026-01' else '2026 (Jan-Sep)'
    d = yr[y]
    d['rows'] += 1; d['eir'] += float(r['interest_accrued'] or 0); d['unwind'] += float(r['unwind_amount'] or 0); d['cash'] += float(r['cash_received'] or 0)
    d['posted'] += float(r['posted'] or 0); d['net_rows'] += 1 if r['interest_basis'] == 'NET' else 0
    if r['interest_basis'] == 'NET':
        d['net_eir'] += float(r['interest_accrued'] or 0); d['net_posted'] += float(r['posted'] or 0)
R['rev_by_year'] = {k: dict(v) for k, v in yr.items()}
# amortised cost against the ledger at the two month-ends
for p in ('2025-12', '2026-09'):
    R['ac_' + p] = q("""select a.contract_id, a.closing_gross ac, l.carrying_amount ca, l.ifrs9stage_post_qualitative st from eir_amortisation a
      join loan_books l on l.contract_id=a.contract_id and l.reporting_period=a.reporting_period where a.reporting_period=%s""", (p,))
# where each locked loan's roll-forward stops, and the stage then
stops = []
for lk in R['eir_locked']:
    last = one("select max(reporting_period) p, min(reporting_period) f, count(*) n from eir_amortisation where contract_id=%s", (lk['contract_id'],))
    nxt = None
    if last and last['p'] and last['p'] < '2026-09':
        y, m = int(last['p'][:4]), int(last['p'][5:7])
        y, m = (y + 1, 1) if m == 12 else (y, m + 1)
        np_ = f"{y}-{m:02d}"
        nxt = one(f"select reporting_period p, {ST} st, carrying_amount ca, ecl_value, ecl_discount_status from loan_books where contract_id=%s and reporting_period=%s", (lk['contract_id'], np_))
        prv = one(f"select ecl_value, ecl_discount_status from loan_books where contract_id=%s and reporting_period=%s", (lk['contract_id'], last['p']))
        if nxt is not None:
            nxt['prior_ecl'] = prv and prv['ecl_value']
            nxt['prior_status'] = prv and prv['ecl_discount_status']
    stops.append({'cid': lk['contract_id'], 'name': lk['customer_name'], 'first': last and last['f'], 'last': last and last['p'], 'rows': last and last['n'], 'next': nxt})
R['rf_stops'] = stops

# restructures and resets
R['restructures'] = q("select status, suggested_class, source, count(*) n, count(distinct contract_id) loans, sum(capitalised_amount) cap from restructure_events group by 1,2,3 order by 1,2,3")
R['restructure_mod'] = q("""select contract_id, customer_name, event_date, source, old_balance, new_balance, capitalised_amount, left(suggestion_reason,200) why
  from restructure_events where suggested_class='MODIFICATION' order by coalesce(capitalised_amount,0)+coalesce(new_balance,0) desc limit 20""")
R['resets'] = q("select event_type, status, classification_status, count(*) n, count(distinct contract_id) loans from rate_reset_events group by 1,2,3 order by 1,2,3")
R['reset_rows'] = one("select count(*) n from eir_rate_periods")

# data ties
R['ties'] = {
    'maiic_2025_12': one(f"select count(*) n, sum(carrying_amount) ca from loan_books where reporting_period='2025-12' and product_code not in {MF}"),
    'maiic_2026_09': one(f"select count(*) n, sum(carrying_amount) ca from loan_books where reporting_period='2026-09' and product_code not in {MF}"),
    'mf_2025_12': one(f"select count(*) n, sum(carrying_amount>0) nbal, sum(carrying_amount) ca from loan_books where reporting_period='2025-12' and product_code in {MF}"),
    'mf_2026_09': one(f"select count(*) n, sum(carrying_amount>0) nbal, sum(carrying_amount) ca from loan_books where reporting_period='2026-09' and product_code in {MF}"),
}
R['mf_tie'] = q(f"""select json_unquote(json_extract(build_basis,'$.mega_farm.report_sign_error')) is not null sign_err, count(*) n from loan_books
  where product_code in {MF} and reporting_period>='2024-12' group by 1""")
R['mf_split'] = one(f"""select sum(json_extract(build_basis,'$.mega_farm.interest_own_gl')) own, sum(json_extract(build_basis,'$.mega_farm.interest_government_portion')) gov,
  sum(json_extract(build_basis,'$.mega_farm.interest_total')) tot from loan_books where product_code in {MF} and reporting_period='2026-09'""")
R['eir_all'] = q("""select contract_id, customer_name, calculation_status, schedule_source, contractual_rate, eir_nominal_annual, left(coalesce(calculation_error,''),400) err from contract_eir""")
R['ecl_move'] = q(f"""select coalesce(a.contract_id,b.contract_id) cid, coalesce(b.customer_name,a.customer_name) name, a.carrying_amount g0, b.carrying_amount g1,
  a.{ST} s0, b.{ST} s1, a.ecl_value e0, b.ecl_value e1, (CASE WHEN a.ecl_discount_status='CALCULATED_TIME_PHASED' AND a.ecl_value_discounted IS NOT NULL THEN a.ecl_value_discounted ELSE COALESCE(a.ecl_value,0) END) sh0,
  (CASE WHEN b.ecl_discount_status='CALCULATED_TIME_PHASED' AND b.ecl_value_discounted IS NOT NULL THEN b.ecl_value_discounted ELSE COALESCE(b.ecl_value,0) END) sh1
  from (select * from loan_books where reporting_period='2025-12' and product_code not in {MF}) a
  left join (select * from loan_books where reporting_period='2026-09' and product_code not in {MF}) b on a.contract_id=b.contract_id
  union select b.contract_id, b.customer_name, null, b.carrying_amount, null, b.{ST}, null, b.ecl_value, 0,
  (CASE WHEN b.ecl_discount_status='CALCULATED_TIME_PHASED' AND b.ecl_value_discounted IS NOT NULL THEN b.ecl_value_discounted ELSE COALESCE(b.ecl_value,0) END)
  from loan_books b where b.reporting_period='2026-09' and b.product_code not in {MF} and not exists (select 1 from loan_books a where a.reporting_period='2025-12' and a.contract_id=b.contract_id)""")
R['writeoffs'] = one(f"select count(distinct contract_id) n from loan_books where product_code not in {MF} and {WO} > 0 and reporting_period='2026-09'")


def conv(o):
    if isinstance(o, dict):
        return {str(k): conv(v) for k, v in o.items()}
    if isinstance(o, (list, tuple)):
        return [conv(v) for v in o]
    if isinstance(o, decimal.Decimal):
        return float(o)
    if isinstance(o, (datetime.date, datetime.datetime)):
        return o.isoformat()
    return o


json.dump(conv(R), open(out, 'w', encoding='utf-8'), indent=1, default=str)
print('written', out)
