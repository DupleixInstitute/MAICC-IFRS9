import csv, glob, collections, datetime as dt, json
D="C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4/Export Results/"
def d(s):
    s=(s or '').strip().split(' ')[0]
    if not s: return None
    for f in ('%m/%d/%Y','%Y-%m-%d'):
        try: return dt.datetime.strptime(s,f).date().isoformat()
        except: pass
    return None
def jl(f): return [json.loads(l.replace(chr(92)*2,chr(92))) for l in open(f,encoding='utf-8')]
num=lambda v: float(str(v or '0').replace(',','') or 0)
rows=collections.defaultdict(dict)
for p in jl('P2_10.jsonl'):
    if p.get('ACTIVE_FLAG','Y')!='Y' or p.get('DELETE_FLAG','N')=='Y': continue
    rows[p['NEW_AC_NUMBER']][p['INST_CHART_DET_ID']]=dict(created=d(p['TRANSACTION_DATE']),replaced=None,due=d(p['INSTALLMENT_DATE']),amt=num(p['INSTALLMENT_AMT']),prin=num(p['PRIN_INSTALLMENT_AMT']),bal=num(p['EXP_BALANCE']),det=p.get('INST_DET_ID',''),sr=int(p.get('INST_SR_NO') or 0))
for f in sorted(glob.glob(D+'RS_02a_*.csv')):
    for p in csv.DictReader(open(f,encoding='utf-8-sig')):
        if p.get('ACTIVE_FLAG','Y')!='Y': continue
        rows[p['NEW_AC_NUMBER']][p['INST_CHART_DET_ID']]=dict(created=d(p['TRANSACTION_DATE']),replaced=d(p['OPERATION_DATE']) or '0000-00-00',due=d(p['INSTALLMENT_DATE']),amt=num(p['INSTALLMENT_AMT']),prin=num(p['PRIN_INSTALLMENT_AMT']),bal=num(p['EXP_BALANCE']),det=p.get('INST_DET_ID',''),sr=int(p.get('INST_SR_NO') or 0))
def at(a,date):
    rs=[r for r in rows[a].values() if r['created'] and r['created']<=date and (r['replaced'] is None or r['replaced']>date)]
    latest={}
    for i,r in enumerate(rs):
        k=(r['det'],r['sr']) if r['det'] and r['sr']>0 else ('row',i)
        if k not in latest or r['created']>=latest[k]['created']: latest[k]=r
    return sorted(latest.values(), key=lambda r:r['due'] or '')
def terms(ch, date):
    rem=[r for r in ch if r['due'] and r['due']>date and r['amt']>0]
    allpos=[r for r in ch if r['amt']>0]
    return dict(n=len(rem), maturity=(allpos[-1]['due'] if allpos else None), remaining_amt=round(sum(r['amt'] for r in rem),2), remaining_prin=round(sum(r['prin'] for r in rem),2), inst=(rem[0]['amt'] if rem else None), first=(rem[0]['due'] if rem else None), total=len(allpos))
# PLR change dates
plr=sorted(set((d(p.get('APPLI_FROM_DATE')) ,num(p['PLR_RATE'])) for p in jl('P2_06.jsonl') if p.get('DELETE_FLAG','N')!='Y'))
plrdates=set(x[0] for x in plr)
evs=[]
for a in rows:
    dates=sorted(set(r['replaced'] for r in rows[a].values() if r['replaced'] and r['replaced']!='0000-00-00') | set(r['created'] for r in rows[a].values() if r['created']))
    prev=None
    for D_ in dates:
        before=at(a,(dt.date.fromisoformat(D_)-dt.timedelta(days=1)).isoformat())
        after=at(a,D_)
        if not before or not after: continue
        tb=terms(before,D_); ta=terms(after,D_)
        if tb==ta: continue
        kind=[]
        if tb['maturity']!=ta['maturity']: kind.append('MAT')
        if tb['n']!=ta['n']: kind.append('N')
        if abs(tb['remaining_prin']-ta['remaining_prin'])>max(1,0.005*abs(tb['remaining_prin'])): kind.append('PRIN')
        if not kind: kind.append('AMT_ONLY')
        evs.append((a,D_,'+'.join(kind),D_ in plrdates,tb,ta))
c=collections.Counter((e[2],e[3]) for e in evs)
print(len(evs)); 
for k,v in sorted(c.items()): print(k,v)
print('PLR dates',sorted(plrdates)[-15:])
for e in evs:
    if e[2]!='AMT_ONLY' : print(e[0],e[1],e[2],e[3],'B',e[4],'A',e[5])
