import sys, xlrd, re
def norm(s): return re.sub(r'\s+',' ',str(s)).strip().lower()
for p in sys.argv[1:]:
    sh = xlrd.open_workbook(p).sheet_by_index(0)
    hi=None
    for i in range(sh.nrows):
        r=[norm(c) for c in sh.row_values(i)]
        if any('a/c number' in c for c in r): hi=i; hdr=r; break
    ac=[j for j,c in enumerate(hdr) if 'a/c number' in c][0]
    ca=[j for j,c in enumerate(hdr) if 'carrying' in c]
    pr=[j for j,c in enumerate(hdr) if c=='principal']
    n=0; tca=0; tpr=0
    for i in range(hi+1, sh.nrows):
        r=sh.row_values(i)
        a=str(r[ac]).strip()
        if re.fullmatch(r'0001\d{11}', a):
            n+=1
            f=lambda v: float(str(v).replace(',','') or 0)
            tca+=f(r[ca[0]]) if ca else 0; tpr+=f(r[pr[0]]) if pr else 0
    print(p.split('/')[-1], n, round(tpr), round(tca))
