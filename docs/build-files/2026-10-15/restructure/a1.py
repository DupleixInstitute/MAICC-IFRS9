import csv, glob, collections, datetime as dt, sys
D="C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4/Export Results/"
def d(s):
    s=(s or '').strip().split(' ')[0]
    if not s: return None
    for f in ('%m/%d/%Y','%Y-%m-%d'):
        try: return dt.datetime.strptime(s,f).date()
        except: pass
    return None
rows=[]
for f in sorted(glob.glob(D+'RS_02a_*.csv')):
    rows+=list(csv.DictReader(open(f,encoding='utf-8-sig')))
print(len(rows))
g=collections.defaultdict(lambda: collections.Counter())
flags=collections.Counter()
for r in rows:
    flags[(r['DELETE_FLAG'],r['ACTIVE_FLAG'])]+=1
    g[r['NEW_AC_NUMBER']][(r['TRANSACTION_DATE'].split(' ')[0], r['OPERATION_DATE'].split(' ')[0], r['INST_DET_ID'])]+=1
print(flags)
n=0
for a in sorted(g):
    ev=sorted(g[a].items(), key=lambda kv:(d(kv[0][1]) or dt.date(1,1,1)))
    print(a, len(ev), [ (k[0],k[1],k[2],v) for k,v in ev][:12])
