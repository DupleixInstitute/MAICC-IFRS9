import pymysql, sys
db='maiic_ifrs9_results2'
c=pymysql.connect(host='127.0.0.1',user='root',db=db,cursorclass=pymysql.cursors.DictCursor); cur=c.cursor()
accts=sys.argv[1].split(',')
for a in accts:
    cur.execute("select t.contract_id,t.block_id,t.origination_date,t.original_principal,t.contractual_rate,t.takeon_posting,b.* from contract_takeon t join takeon_blocks b on b.id=t.block_id where t.contract_id=%s",(a,))
    r=cur.fetchone()
    print('=====',a,r['facility_name'],r['title'],'blk',r['block_no'],'row',r['sheet_row'],'princ',r['principal'],'rate',r['rate'],'lbrate',r['loan_book_rate'],'per',r['periods_per_year'],'nper',r['total_periods'],'pmt',r['pmt'],'value',r['value_date'],'mat',r['maturity_date'],'appr',r['approved'],'disb',r['disbursed'],'post',r['takeon_posting'],'restr',r['restructured'],'conf',r['confirmed'],'acct',r['account'],'prop',r['proposed_account'],'confid',r['confidence'])
    cur.execute("select serial, sheet_row, period_start, period_end, days, opening_balance ob, instalment ins, interest i, principal p, closing_balance cb, amount_repaid rep, accumulated_arrears arr from takeon_schedule_lines where block_id=%s order by period_end, serial",(r['block_id'],))
    prev=None
    for l in cur.fetchall():
        jump = '' if prev is None or l['ob'] is None or prev['cb'] is None else ('JUMP %+.2f'%(float(l['ob'])-float(prev['cb'])) if abs(float(l['ob'])-float(prev['cb']))>1 else '')
        print('  ',l['serial'],l['sheet_row'],l['period_start'],l['period_end'],l['days'],l['ob'],l['ins'],l['i'],l['p'],l['cb'],l['rep'],jump)
        prev=l
