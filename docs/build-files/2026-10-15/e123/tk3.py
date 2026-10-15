import pymysql, sys, datetime
c=pymysql.connect(host='127.0.0.1',user='root',db='maiic_ifrs9_results2',cursorclass=pymysql.cursors.DictCursor); cur=c.cursor()
cur.execute("select t.*, b.block_no, b.principal bprinc, b.rate brate, b.value_date, b.facility_name, b.restructured from contract_takeon t join takeon_blocks b on b.id=t.block_id where t.basis='RECOMPUTED'")
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
res=[]
for r in rows:
    cur.execute("select * from takeon_schedule_lines where block_id=%s and period_end is not null order by period_end, serial",(r['block_id'],))
    L=cur.fetchall(); orig=r['origination_date']
    flows=[]; prev=None; ups=[]; downs=[]; notes=[]
    first_open=float(L[0]['opening_balance']) if L[0]['opening_balance'] is not None else None
    for l in L:
        ob=None if l['opening_balance'] is None else float(l['opening_balance'])
        if prev is not None and ob is not None and prev['closing_balance'] is not None:
            j=ob-float(prev['closing_balance'])
            if j>1: ups.append((l['period_start'] or l['period_end'], j))
            elif j<-1: downs.append((l['period_start'] or l['period_end'], j))
        amt = float(l['instalment']) if l['instalment'] is not None else float(l['principal'] or 0)+float(l['interest'] or 0)
        if l['period_end']>orig: flows.append((l['period_end'],amt))
        else: notes.append('line on/before orig')
        prev=l
    last_close=float(L[-1]['closing_balance'] or 0)
    princ=float(r['original_principal'])
    t0 = first_open if first_open else princ
    v=[(orig,-t0)]+[(d,-a) for d,a in ups]+flows
    nf=solve(v,orig)
    fees=float(r['fees_total'] or 0)
    v2=[(orig,-(t0-fees))]+[(d,-a) for d,a in ups]+flows
    wf=solve(v2,orig)
    rate=float(r['contractual_rate'])
    res.append((r['contract_id'],r['facility_name'][:26],str(orig),round(princ/1e6,2),round(t0/1e6,2),len(ups),round(sum(a for d,a in ups)/1e6,2),len(downs),round(sum(a for d,a in downs)/1e6,2),round(last_close/1e6,3),rate,round(nom(float(r['recomputed_eir'])),2),None if nf is None else round(nom(nf),2),None if wf is None else round(nom(wf),2),r['restructured'],len(notes)))
res.sort(key=lambda x:(x[12] is None, abs((x[12] or 999)-x[10])))
print('acct name orig princ t0 nUp up nDown down lastClose rate oldEIR nofee withfee restr pre')
for x in res: print(*x, sep=' | ')
