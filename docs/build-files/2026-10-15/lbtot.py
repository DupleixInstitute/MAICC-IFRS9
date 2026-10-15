import sys, xlrd
for p in sys.argv[1:]:
    wb = xlrd.open_workbook(p, on_demand=True); sh = wb.sheet_by_index(0)
    hdr = None; n=0; tot={}
    for i in range(sh.nrows):
        r = sh.row_values(i)
        if 'A/C Number' in r: hdr=r; continue
        if hdr is None: continue
        ac = str(r[hdr.index('A/C Number')]).strip()
        if len(ac)==15 and ac.startswith('0001'):
            n+=1
            for k in ('Principal','Carrying Amount','Outstanding Balance'):
                if k in hdr:
                    v=r[hdr.index(k)]
                    try: v=float(str(v).replace(',',''))
                    except: v=0
                    tot[k]=tot.get(k,0)+v
    print(p.split('/')[-1], n, {k:round(v) for k,v in tot.items()}, [h for h in hdr if h][:30] if 'Dec' in p else '')
