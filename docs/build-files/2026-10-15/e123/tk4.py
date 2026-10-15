import pymysql, sys, datetime
c=pymysql.connect(host='127.0.0.1',user='root',db='maiic_ifrs9_results2',cursorclass=pymysql.cursors.DictCursor); cur=c.cursor()
cur.execute("select t.*, b.block_no, b.principal bprinc, b.rate brate, b.value_date, b.facility_name, b.restructured, b.sheet_row, b.loan_book_rate from contract_takeon t join takeon_blocks b on b.id=t.block_id where t.basis='RECOMPUTED'")
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
verbose=set(sys.argv[1].split(',')) if len(sys.argv)>1 else set()
out=[]
for r in rows:
    cur.execute("select * from takeon_schedule_lines where block_id=%s and period_end is not null order by period_end, serial",(r['block_id'],))
    L=cur.fetchall(); orig=r['origination_date']
    v=[]; prev=None; ups=[]; downs=[]; rates=[]; anomalies=[]
    t0=f(L[0]['opening_balance']); orig=L[0]['period_start'] or orig
    v.append((orig,-t0))
    for l in L:
        ob,cb,p,i,ins=f(l['opening_balance']),f(l['closing_balance']),f(l['principal']),f(l['interest']),f(l['instalment'])
        if prev is not None and ob is not None and f(prev['closing_balance']) is not None:
            j=ob-f(prev['closing_balance'])
            if j>1: ups.append((l['period_start'] or l['period_end'], j, 'between'))
            elif j<-1: downs.append((l['period_start'] or l['period_end'], j,'between'))
        if ob is not None and cb is not None and p is not None:
            w=cb-(ob-p)
            if w>1: ups.append((l['period_end'], w,'within'))
            elif w<-1: downs.append((l['period_end'], w,'within'))
        amt = (p or 0)+(i or 0)
        if ob and i and l['days']:
            rates.append(i/ob*365/l['days']*100)
        if l['period_end']>orig: v.append((l['period_end'],amt))
        else: anomalies.append('line %s on/before origination amt %.2f'%(l['serial'],amt))
        prev=l
    for d,a,k in ups: v.append((d,-a))
    nf=solve(v,orig)
    out.append((r['contract_id'],(r['facility_name'] or '')[:24],float(r['contractual_rate']),None if nf is None else round(nom(nf),2),round(min(rates),2) if rates else None,round(max(rates),2) if rates else None,len(ups),round(sum(a for d,a,k in ups)/1e6,2),len(downs),round(sum(a for d,a,k in downs)/1e6,2),anomalies))
    if r['contract_id'] in verbose:
        print('==',r['contract_id'],r['facility_name'],'rate',r['contractual_rate'],'lb',r['loan_book_rate'],'princ',r['bprinc'],'row',r['sheet_row'])
        for l in L[:int(sys.argv[2]) if len(sys.argv)>2 else 14]:
            print('   ',l['serial'],l['period_start'],l['period_end'],l['days'],l['opening_balance'],l['instalment'],l['interest'],l['principal'],l['closing_balance'],l['amount_repaid'],l['accumulated_arrears'])
        print('   ups',ups,'downs',downs)
if not verbose:
  for x in sorted(out,key=lambda x:(x[3] is None, x[3] or 0)):
    ok = x[3] is not None and x[4] is not None and x[4]-0.5 <= x[3] <= x[5]+0.5
    print('OK ' if ok else 'BAD', *x, sep=' | ')
