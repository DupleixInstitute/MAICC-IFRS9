import datetime as dt
from lz import *
def pdate(s):
    s = str(s or '').strip()
    if not s: return None
    for f in ('%m/%d/%Y', '%Y-%m-%d'):
        try: return dt.datetime.strptime(s.split(' ')[0], f).date()
        except: pass
    return None
_cache = {}
def versions(a):
    a = acct(a)
    if a in _cache: return _cache[a]
    by = {}
    for r in fam(['P2_10'], a):
        p = r['payload']
        if p.get('DELETE_FLAG', 'N') == 'Y' or p.get('ACTIVE_FLAG', 'Y') != 'Y': continue
        by[p.get('INST_CHART_DET_ID')] = (pdate(p.get('TRANSACTION_DATE')), None, r['row_date'], p)
    for r in fam(['RS_02a'], a):
        p = r['payload']
        by[p.get('INST_CHART_DET_ID')] = (pdate(p.get('TRANSACTION_DATE')), pdate(p.get('OPERATION_DATE')), r['row_date'], p)
    rows = list(by.values())
    _cache[a] = rows
    return rows
def chart_at(a, d):
    """instalment rows in force at date d: created on or before d, not superseded by d"""
    out = [(due, p) for c, s, due, p in versions(a) if c is not None and c <= d and (s is None or s > d)]
    out.sort(key=lambda x: str(x[0]))
    return out
def chart_versions(a):
    from collections import Counter
    return Counter((c, s) for c, s, due, p in versions(a))
