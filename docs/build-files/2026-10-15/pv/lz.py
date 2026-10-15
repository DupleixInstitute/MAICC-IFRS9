import pymysql, json, sys, datetime as dt
DB = 'maiic_ifrs9_results2'
_c = None
def conn():
    global _c
    if _c is None:
        _c = pymysql.connect(host='127.0.0.1', user='root', password='', database=DB, cursorclass=pymysql.cursors.DictCursor)
    return _c
def q(sql, args=None):
    with conn().cursor() as c:
        c.execute(sql, args); return c.fetchall()
LOADS = None
def landed():
    global LOADS
    if LOADS is None:
        LOADS = [r['id'] for r in q("select id from ebanker_loads where status='LANDED'")]
    return LOADS
def fam(qids, account=None):
    ph = ','.join(['%s']*len(qids)); lp = ','.join(str(x) for x in landed())
    sql = f"select load_id, query_id, source_key, account, row_date, payload from ebanker_raw_rows where superseded_at is null and load_id in ({lp}) and query_id in ({ph})"
    args = list(qids)
    if account: sql += " and account=%s"; args.append(account)
    sql += " order by load_id, id"
    out = {}
    for r in q(sql, args):
        r['payload'] = json.loads(r['payload']); out[r['source_key']] = r
    return list(out.values())
def acct(a):
    a = str(a)
    return a if len(a) == 15 else a.rjust(15, '0')
LOAN_GLS = {'1050101','1050102','1050103','1050201','1050202','1050401'}
def ledger(a):
    rows = [r for r in fam(['P1_01','M09_01','MF_03','GL_03','ZF_01'], acct(a)) if r['payload'].get('DELETE_FLAG','N') != 'Y']
    rows = [r for r in rows if (r['payload'].get('AC_GLCODE') or r['payload'].get('CB_GLCODE') or '').strip() in LOAN_GLS or not (r['payload'].get('AC_GLCODE') or r['payload'].get('CB_GLCODE'))]
    rows.sort(key=lambda r: (str(r['row_date']), int(r['source_key']) if str(r['source_key']).isdigit() else 0))
    return rows
def chart(a, fams=('P2_10',)):
    rows = [r for r in fam(list(fams), acct(a)) if r['payload'].get('DELETE_FLAG','N') != 'Y' and r['payload'].get('ACTIVE_FLAG','Y') == 'Y']
    rows.sort(key=lambda r: str(r['row_date']))
    return rows
def num(v):
    try: return float(str(v).replace(',', ''))
    except: return 0.0
def runs(a):
    rs = fam(['P2_08','M09_03','MF_05'], acct(a)); best = {}
    for r in rs:
        d = str(r['row_date'])
        if d not in best or int(r['source_key']) > int(best[d]['source_key']): best[d] = r
    return dict(sorted(best.items()))
def book(cid):
    return q("select * from loan_books where contract_id=%s order by reporting_period", (str(int(acct(cid))),))
