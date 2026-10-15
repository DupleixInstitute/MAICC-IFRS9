import pymysql, sys, datetime
c=pymysql.connect(host='127.0.0.1',user='root',db='maiic_ifrs9_results2',cursorclass=pymysql.cursors.DictCursor); cur=c.cursor()
cur.execute("select t.*, b.block_no, b.principal bprinc, b.rate brate, b.value_date, b.facility_name, b.restructured, b.sheet_row, b.loan_book_rate, b.title from contract_takeon t join takeon_blocks b on b.id=t.block_id where t.basis='RECOMPUTED'")
rows=cur.fetchall()
def solve(flows, orig):
    def npv(r): return sum(a/(1+r)**((d-orig).days/365) for d,a in flows)
    lo,hi=-0.99,20
    if npv(lo)*npv(hi)>0: return None
    for _ in range(300):
        m=(lo+hi)/2
        if npv(lo)*npv(m)<=0: hi=m
        else: lo=m
    return m
nom=lambda e: None if e is None else 12*((1+e)**(1/12)-1)*100
f=lambda x: None if x is None else float(x)
res=[]
for r in rows:
    cur.execute("select * from takeon_schedule_lines where block_id=%s and period_end is not null order by serial, sheet_row",(r['block_id'],))
    L=cur.fetchall()
    orig=L[0]['period_start'] or r['origination_date']
    t0=f(L[0]['opening_balance'])
    v=[(orig,-t0)]; prev=None; rates=[]; notes=[]; dup=0; seen={}
    for l in L:
        ob,cb,p,i=f(l['opening_balance']),f(l['closing_balance']),f(l['principal']) or 0,f(l['interest']) or 0
        if l['period_end'] in seen: dup+=1
        seen[l['period_end']]=1
        if prev is not None:
            j=ob-f(prev['closing_balance'])
            if abs(j)>1: v.append((l['period_start'] or l['period_end'],-j)); notes.append('%s %+.0f between'%(l['period_start'],j))
        w=cb-(ob-p)
        if abs(w)>1: v.append((l['period_end'],-w)); notes.append('%s %+.0f within'%(l['period_end'],w))
        if ob and i and l['days'] and ob>0: rates.append(i/ob*365/l['days']*100)
        if p+i!=0:
            if l['period_end']>orig: v.append((l['period_end'],p+i))
            else: notes.append('receipt on/before t0')
        prev=l
    last=f(L[-1]['closing_balance']) or 0
    nf=solve(v,orig)
    fees=float(r['fees_total'] or 0)
    v2=[(orig,-(t0-fees))]+v[1:]
    wf=solve(v2,orig)
    lo,hi=(min(rates),max(rates)) if rates else (None,None)
    ok = nf is not None and lo is not None and lo-0.5<=nom(nf)<=hi+0.5 and abs(last)<=1
    res.append((('OK ' if ok else 'BAD'),r['contract_id'],(r['facility_name'] or '')[:22],str(orig),str(r['origination_date']),float(r['contractual_rate']),None if nf is None else round(nom(nf),2),None if wf is None else round(nom(wf),2),round(lo,2) if lo else None,round(hi,2) if hi else None,round(last,2),dup,notes[:4],round(nom(float(r['recomputed_eir'])),2)))
for x in sorted(res): print(*x, sep=' | ')
print(sum(1 for x in res if x[0]=='OK '),'ok of',len(res))
