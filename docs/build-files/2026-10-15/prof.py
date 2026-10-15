import csv,sys,glob,collections
c=collections.Counter(); s=collections.defaultdict(float); narr={}
for f in sorted(glob.glob(sys.argv[1]+'/MF_03_*.csv')):
    for r in csv.DictReader(open(f,encoding='utf-8-sig',newline='')):
        k=(r['CB_GLCODE'],r['AC_GLCODE'],r['TRANTYPE'],r['DBCR'],r['DELETE_FLAG'])
        c[k]+=1; s[k]+=float(r['TRANSAMT'] or 0); narr.setdefault(k,r['PARTICULARS'][:50])
for k,v in sorted(c.items()):
    print(k,v,round(s[k],2),narr[k])
