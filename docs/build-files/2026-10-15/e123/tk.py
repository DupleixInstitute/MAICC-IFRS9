import pymysql, datetime, math, sys, json
db=sys.argv[1] if len(sys.argv)>1 else 'maiic_ifrs9_results2'
c=pymysql.connect(host='127.0.0.1',user='root',db=db,cursorclass=pymysql.cursors.DictCursor)
cur=c.cursor()
cur.execute("select t.*, b.block_no, b.title, b.principal bprinc, b.rate brate, b.periods_per_year ppy, b.total_periods nper, b.pmt, b.value_date, b.loan_book_rate, b.approved, b.disbursed, b.carrying_31_oct_2024 c31, b.facility_name, b.confirmed, b.restructured, b.status bstatus from contract_takeon t left join takeon_blocks b on b.id=t.block_id where t.recomputed_eir is not null")
rows=cur.fetchall()
def solve(net, flows, orig):
    def npv(r): return -net+sum(a/(1+r)**((d-orig).days/365) for d,a in flows)
    lo,hi=-0.9999,10
    if npv(lo)*npv(hi)>0: return None
    for _ in range(200):
        m=(lo+hi)/2
        if npv(lo)*npv(m)<=0: hi=m
        else: lo=m
    return m
out=[]
for r in rows:
    cur.execute("select period_end, instalment, principal, interest, opening_balance, closing_balance from takeon_schedule_lines where block_id=%s order by period_end, serial",(r['block_id'],))
    L=cur.fetchall()
    orig=r['origination_date']
    flows=[(l['period_end'], float(l['instalment'] if l['instalment'] is not None else (l['principal'] or 0)+(l['interest'] or 0))) for l in L if l['period_end'] and l['period_end']>orig]
    sp=sum(float(l['principal'] or 0) for l in L)
    first_open=float(L[0]['opening_balance']) if L and L[0]['opening_balance'] is not None else None
    nofee=solve(float(r['original_principal']), flows, orig)
    first_end=L[0]['period_end'] if L else None
    nom=lambda e: None if e is None else 12*((1+e)**(1/12)-1)*100
    rate=float(r['contractual_rate'] or 0)
    eir=float(r['recomputed_eir'])
    impl = eir<0 or nom(eir) > 2*rate
    out.append(dict(acct=r['contract_id'],blk=r['block_no'],name=(r['facility_name'] or '')[:28],title=(r['title'] or '')[:30],orig=str(orig),first_due=str(first_end),princ=float(r['original_principal']),sched_princ=round(sp,2),first_open=first_open,rate=rate,lbrate=r['loan_book_rate'],ppy=r['ppy'],nper=r['nper'],n=len(L),fees=float(r['fees_total'] or 0),eir_nom=round(nom(eir),2),nofee_nom=None if nofee is None else round(nom(nofee),2),post=float(r['takeon_posting']),approved=r['approved'],disbursed=r['disbursed'],impl=impl,restr=r['restructured']))
out.sort(key=lambda x:x['eir_nom'])
import csv
w=csv.DictWriter(open('takeon_diag.csv','w',newline=''),fieldnames=list(out[0].keys())); w.writeheader(); w.writerows(out)
for o in out:
    if o['impl'] or (o['nofee_nom'] is not None and abs(o['nofee_nom']-o['rate'])>1):
        print(o)
print(sum(o['impl'] for o in out),'implausible by <0 or >2x rate')
