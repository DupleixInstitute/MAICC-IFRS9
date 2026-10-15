import csv, glob, collections, datetime as dt, json
D="C:/Users/DELL/OneDrive/2026/Projects/MAIIC/2. Documents from clients/Raw Query Scripts/Query Requests to MAIIC/Received 12 October 2026/Pack 4/Export Results/"
def d(s):
    s=(s or '').strip().split(' ')[0]
    for f in ('%m/%d/%Y','%Y-%m-%d'):
        try: return dt.datetime.strptime(s,f).date().isoformat()
        except: pass
live=set(json.loads(l.replace(chr(92)*2,chr(92)))['NEW_AC_NUMBER'] for l in open('P2_10.jsonl',encoding='utf-8'))
sup=set()
for f in glob.glob(D+'RS_02a_*.csv'):
    for p in csv.DictReader(open(f,encoding='utf-8-sig')): sup.add(p['NEW_AC_NUMBER'])
for f in ['RS_02b_chart_300824.csv','RS_02c_chart_141124.csv','RS_02d_chart_28sep.csv','RS_02e_chart1.csv']:
    R=list(csv.DictReader(open(D+f,encoding='utf-8-sig')))
    acc=set(r['NEW_AC_NUMBER'] for r in R)
    td=[d(r['TRANSACTION_DATE']) for r in R if d(r['TRANSACTION_DATE'])]
    od=[d(r['OPERATION_DATE']) for r in R if d(r['OPERATION_DATE'])]
    fl=collections.Counter((r['ACTIVE_FLAG'],r['DELETE_FLAG']) for r in R)
    print(f,len(R),len(acc),'txn',min(td),max(td),'op',(min(od),max(od)) if od else None, fl, 'notlive',len(acc-live),'not in history',len(acc-live-sup))
    b=[r for r in R if r['NEW_AC_NUMBER']=='000104460000059']
    dd=collections.Counter(d(r['INSTALLMENT_DATE']) for r in b)
    print('  BenCal rows',len(b),'max per date',max(dd.values()) if dd else 0, 'txn dates',set(d(r['TRANSACTION_DATE']) for r in b), 'det',set(r['INST_DET_ID'] for r in b))
print('BenCal in live',('000104460000059' in live),'in RS02a',('000104460000059' in sup))
