import csv,sys,glob,collections
c=collections.Counter()
for f in sorted(glob.glob(sys.argv[1]+'/MF_03_*.csv')):
    for r in csv.DictReader(open(f,encoding='utf-8-sig',newline='')):
        c[(r['CB_GLCODE'],r['CB_GLTYPE'],r['CB_ALNX_TYPE'],r['BATCH_TYPE'],r['TRANTYPE'])]+=1
for k,v in sorted(c.items()): print(k,v)
