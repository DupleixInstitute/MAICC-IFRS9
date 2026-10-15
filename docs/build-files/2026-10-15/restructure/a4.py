import csv, glob, collections, datetime as dt, json
D="C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4/Export Results/"
def d(s):
    s=(s or '').strip().split(' ')[0]
    for f in ('%m/%d/%Y','%Y-%m-%d'):
        try: return dt.datetime.strptime(s,f).date().isoformat()
        except: pass
def jl(f): return [json.loads(l.replace(chr(92)*2,chr(92))) for l in open(f,encoding='utf-8')]
num=lambda v: float(str(v or '0').replace(',','') or 0)
rows=collections.defaultdict(dict)
def add(p,sup):
    if p.get('ACTIVE_FLAG','Y')!='Y': return
    if not sup and p.get('DELETE_FLAG','N')=='Y': return
    rows[p['NEW_AC_NUMBER']][p['INST_CHART_DET_ID']]=dict(created=d(p['TRANSACTION_DATE']),replaced=(d(p['OPERATION_DATE']) or '0000-00-00') if sup else None,due=d(p['INSTALLMENT_DATE']),amt=num(p['INSTALLMENT_AMT']),prin=num(p['PRIN_INSTALLMENT_AMT']),bal=num(p['EXP_BALANCE']),det=p.get('INST_DET_ID',''),sr=int(p.get('INST_SR_NO') or 0),user=p.get('ENTER_USER_ID'),pas=p.get('PASS_USER_ID'),ver=p.get('VERIFY_FLAG'))
for p in jl('P2_10.jsonl'): add(p,False)
for f in sorted(glob.glob(D+'RS_02a_*.csv')):
    for p in csv.DictReader(open(f,encoding='utf-8-sig')): add(p,True)
plr=set(d(p.get('APPLI_FROM_DATE')) for p in jl('P2_06.jsonl'))
rs=collections.defaultdict(set)
for p in jl('P1_04.jsonl'):
    for k in ('TRAN_DATE','APPLICABLE_FROM_DATE'):
        if d(p.get(k)): rs[p['NEW_AC_NUMBER']].add(d(p.get(k)))
def inforce(a,date):
    best={}
    for r in rows[a].values():
        if r['created'] and r['created']<=date and (r['replaced'] is None or r['replaced']>date) and r['due']:
            if r['due'] not in best or r['created']>=best[r['due']]['created']: best[r['due']]=r
    return [best[k] for k in sorted(best)]
def months(a,b):
    A=dt.date.fromisoformat(a);B=dt.date.fromisoformat(b); return (B.year-A.year)*12+B.month-A.month
out=[]
for a in rows:
    created=sorted(set(r['created'] for r in rows[a].values() if r['created']))
    for D_ in created[1:]:
        prev=(dt.date.fromisoformat(D_)-dt.timedelta(days=1)).isoformat()
        old=[r for r in inforce(a,prev) if r['due']>D_ and r['amt']>0]
        new=[r for r in rows[a].values() if r['created']==D_ and (r['replaced'] is None or r['replaced']>D_) and r['due'] and r['due']>D_ and r['amt']>0]
        new.sort(key=lambda r:r['due'])
        if not old or not new: 
            out.append((a,D_,'NO_OLD' if not old else 'NO_NEW',None)); continue
        oldall=inforce(a,prev); 
        exp_old=[r['bal'] for r in oldall if r['due']<=D_]
        old_bal=(old[0]['bal']+old[0]['prin'])
        new_bal=(new[0]['bal']+new[0]['prin'])
        ms=months(old[-1]['due'],new[-1]['due'])
        rate=(D_ in plr) or (D_ in rs[a])
        cap=new_bal-old_bal
        out.append((a,D_,'EV',dict(rate=rate,ms=ms,n=(len(old),len(new)),bal=(round(old_bal),round(new_bal)),cap=round(cap),pct=round(cap/old_bal*100,2) if old_bal else None,inst=(old[0]['amt'],new[0]['amt']))))
c=collections.Counter()
for a,D_,k,x in out:
    if k!='EV': c[k]+=1; continue
    key=('rate' if x['rate'] else 'norate', 'ms<=1' if abs(x['ms'])<=1 else 'ms>1', 'cap' if x['pct'] and abs(x['pct'])>1 else 'nocap')
    c[key]+=1
for k,v in sorted(c.items(),key=str): print(k,v)
print('---- non-trivial')
for a,D_,k,x in out:
    if k=='EV' and (not x['rate'] or abs(x['ms'])>1 or (x['pct'] and abs(x['pct'])>1)):
        print(a,D_,x)
