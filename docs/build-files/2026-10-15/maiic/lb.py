import csv,sys,glob,os
for p in sorted(glob.glob(sys.argv[1]+'/*/*.csv')):
    with open(p,encoding='utf-8-sig',errors='replace') as f:
        rows=list(csv.reader(f))
    h=[c.strip().lower() for c in rows[0]]
    ci=[i for i,c in enumerate(h) if 'carrying' in c]
    tot=0
    for r in rows[1:]:
        try: tot+=float(r[ci[0]].replace(',','')) if ci else 0
        except: pass
    print(f"{os.path.basename(p):28s} rows={len(rows)-1:4d} cols={len(h)} CA_m={tot/1e6:,.1f}")
