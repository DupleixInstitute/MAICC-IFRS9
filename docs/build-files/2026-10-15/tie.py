import csv,sys,glob,collections,datetime
d=sys.argv[1]
def dt(s):
    s=s.strip().split(' ')[0]
    if not s: return None
    m,dd,y=s.split('/'); return datetime.date(int(y),int(m),int(dd))
led={}
for f in sorted(glob.glob(d+'/MF_03_*.csv')):
    for r in csv.DictReader(open(f,encoding='utf-8-sig',newline='')):
        led[r['CUMVOUCH_DET_ID']]=r
runs={}
for f in sorted(glob.glob(d+'/MF_05_*.csv')):
    for r in csv.DictReader(open(f,encoding='utf-8-sig',newline='')):
        k=(r['NEW_AC_NUMBER'],dt(r['ASONDATE']))
        if k not in runs or int(r['LOAN_BOOK_DET_ID_A'])>int(runs[k]['LOAN_BOOK_DET_ID_A']): runs[k]=r
by=collections.defaultdict(list)
for r in led.values():
    if r['DELETE_FLAG']=='Y' or not r['NEW_AC_NUMBER']: continue
    by[r['NEW_AC_NUMBER']].append((dt(r['TRANSACTION_DATE']),r))
for a in by: by[a].sort(key=lambda x:x[0])
def num(s):
    s=s.replace(',','').strip(); 
    try: return float(s)
    except: return 0.0
stat=collections.Counter(); resid=[]
for (a,me),run in runs.items():
    allsum=0; gov400=0; c2071=0; own=0
    for t,r in by.get(a,[]):
        if t>me: break
        x=num(r['TRANSAMT']); allsum+=x
        if r['CB_GLCODE']=='2071': c2071+=x
        if r['TRANTYPE']=='400' and 'GOVT_INT' in r['PARTICULARS']: gov400+=x
    cur=round(-allsum,2); stored=num(run['CARRYING_AMOUNT'])
    v1=round(-(allsum-c2071),2)
    stat['n']+=1
    if abs(cur-stored)<0.005: stat['cur_tie']+=1
    if abs(v1-stored)<0.005: stat['v1_tie']+=1
    else: resid.append((a,me,v1-stored,cur-stored,gov400,c2071))
print(stat)
resid.sort(key=lambda x:-abs(x[2]))
print(len(resid), sum(r[2] for r in resid))
for r in resid[:25]: print(r)
mons=collections.Counter(r[1] for r in resid); print(sorted(mons.items())[:40])
