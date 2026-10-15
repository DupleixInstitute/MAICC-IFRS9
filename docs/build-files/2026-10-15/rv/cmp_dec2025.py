"""Loan-by-loan comparison of MAIIC's December 2025 ECL model (ECLComp) with the
system's December 2025 loan book on a given database. Read-only.

usage: python -I cmp_dec2025.py <db> <eclcomp_csv> <out_csv> [period]
"""
import sys, csv, re, difflib
import pymysql

db, src, out = sys.argv[1], sys.argv[2], sys.argv[3]
period = sys.argv[4] if len(sys.argv) > 4 else '2025-12'
MF = ('1050301', '1050302', '1050303', '1050304', '1050305', '1050306', '1050307')

con = pymysql.connect(host='127.0.0.1', user='root', password='', database=db, cursorclass=pymysql.cursors.DictCursor)
cur = con.cursor()
cur.execute("""select contract_id, external_identity_id, customer_name, product_group, product_code, loan_portfolio_id,
  carrying_amount, commitments, coalesce(facility_utilisation_rate,1) util, overdue_days, tenor, remaining_tenor,
  coalesce(ifrs9stage_post_qualitative, calculated_ifrs9_stage) stage, ifrs9stage_pre_qualitative pre,
  pd_prefli, pd_post_fli, lgd_value, collection_lgd, ecl_value, ecl_value_discounted, ecl_discount_status, interest_rate
  from loan_books where reporting_period=%s and product_code not in %s""", (period, MF))
ours = cur.fetchall()

def norm(s):
    s = (s or '').lower()
    s = re.sub(r'\b(limited|ltd|plc|company|co)\b', '', s)
    return re.sub(r'[^a-z0-9]', '', s)

theirs = list(csv.DictReader(open(src, encoding='utf-8')))
used = set()
rows = []
for t in theirs:
    try:
        exp = float(t['Y'] or 0)
    except ValueError:
        exp = 0.0
    match, how = None, None
    cands = [o for o in ours if o['contract_id'] not in used and abs(float(o['carrying_amount'] or 0) - exp) < 0.02] if abs(exp) > 0.01 else []
    if len(cands) == 1:
        match, how = cands[0], 'amount'
    elif len(cands) > 1:
        best = max(cands, key=lambda o: difflib.SequenceMatcher(None, norm(o['customer_name']), norm(t['E'])).ratio())
        match, how = best, 'amount+name'
    else:
        pool = [o for o in ours if o['contract_id'] not in used]
        scored = sorted(pool, key=lambda o: difflib.SequenceMatcher(None, norm(o['customer_name']), norm(t['E'])).ratio(), reverse=True)
        if scored and difflib.SequenceMatcher(None, norm(scored[0]['customer_name']), norm(t['E'])).ratio() > 0.8:
            match, how = scored[0], 'name'
    if match:
        used.add(match['contract_id'])
    f = lambda k: float(match[k]) if match and match[k] is not None else None
    ead_ours = (f('carrying_amount') or 0) + (f('commitments') or 0) * (f('util') or 1) if match else None
    rows.append({
        'maiic_row': t['A'], 'maiic_name': t['E'], 'maiic_bracket': t['P'], 'maiic_stage': t['R'], 'maiic_exposure': t['Y'],
        'maiic_commit': t['AU'], 'maiic_ead': t['AV'], 'maiic_pd': t['AR'], 'maiic_lgd': t['AS'], 'maiic_rate': t['AW'],
        'maiic_years': t['AY'], 'maiic_pvfcl': t['BB'], 'maiic_ecl': t['BC'], 'maiic_security': t['U'],
        'match': how or 'UNMATCHED', 'contract_id': match['contract_id'] if match else '', 'our_name': match['customer_name'] if match else '',
        'portfolio': match['product_group'] if match else '', 'our_carrying': f('carrying_amount'), 'our_commit': f('commitments'),
        'our_ead': ead_ours, 'our_dpd': match['overdue_days'] if match else '', 'our_stage': match['stage'] if match else '',
        'our_pre_stage': match['pre'] if match else '', 'our_pd12': f('pd_post_fli'), 'our_lgd': f('lgd_value'),
        'our_ecl': f('ecl_value'), 'our_ecl_disc': f('ecl_value_discounted'), 'our_disc_status': match['ecl_discount_status'] if match else '',
        'our_rem_tenor': f('remaining_tenor'), 'our_rate': f('interest_rate'),
    })
for o in ours:
    if o['contract_id'] not in used and (float(o['carrying_amount'] or 0) != 0 or float(o['commitments'] or 0) != 0):
        rows.append({'match': 'OURS_ONLY', 'contract_id': o['contract_id'], 'our_name': o['customer_name'], 'portfolio': o['product_group'],
                     'our_carrying': float(o['carrying_amount'] or 0), 'our_commit': float(o['commitments'] or 0), 'our_stage': o['stage'],
                     'our_dpd': o['overdue_days'], 'our_pd12': o['pd_post_fli'], 'our_lgd': o['lgd_value'], 'our_ecl': o['ecl_value'],
                     'our_ecl_disc': o['ecl_value_discounted']})
keys = []
for r in rows:
    for k in r:
        if k not in keys:
            keys.append(k)
w = csv.DictWriter(open(out, 'w', newline='', encoding='utf-8'), fieldnames=keys)
w.writeheader()
w.writerows(rows)
print('MAIIC rows', len(theirs), 'matched', sum(1 for r in rows if r['match'] not in ('UNMATCHED', 'OURS_ONLY')),
      'unmatched', sum(1 for r in rows if r['match'] == 'UNMATCHED'), 'ours only', sum(1 for r in rows if r['match'] == 'OURS_ONLY'))
