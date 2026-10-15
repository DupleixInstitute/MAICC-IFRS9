import csv,sys,glob,collections
led={}
for f in sorted(glob.glob(sys.argv[1]+'/MF_03_*.csv')):
    for r in csv.DictReader(open(f,encoding='utf-8-sig',newline='')):
        led[r['CUMVOUCH_DET_ID']]=r
c=collections.Counter(); 
d400=collections.defaultdict(float); c401=collections.defaultdict(float)
for r in led.values():
    if r['DELETE_FLAG']=='Y': continue
    t=r['TRANTYPE']; g='GOVT_INT' in r['PARTICULARS'].upper()
    c[(t,g,r['CB_GLCODE']==r['AC_GLCODE'],r['DBCR'])]+=1
    k=(r['NEW_AC_NUMBER'],r['TRANSACTION_DATE'],r['BATCH_TYPE'],r['BATCH_NUMBER'])
    if t=='400': d400[k]+=float(r['TRANSAMT'])
    if t=='401': c401[k]+=float(r['TRANSAMT'])
print(sorted(c.items()))
bad=[k for k in set(d400)|set(c401) if abs(d400.get(k,0)+c401.get(k,0))>0.005]
print('batch-keys',len(set(d400)|set(c401)),'unpaired',len(bad)); print(bad[:10])
