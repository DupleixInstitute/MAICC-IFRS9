import csv, sys
rows = list(csv.DictReader(open('sysdpd.csv')))
s, e = sys.argv[1], sys.argv[2]
for cid in sys.argv[3:]:
    rr = [r for r in rows if r['cid'] == cid and s <= r['p'] <= e]
    if not rr: print(cid, 'none'); continue
    print(f"--- {cid} {rr[0]['name']} pf{rr[0]['pf']} tenor {rr[0]['tenor']} due {rr[-1]['due']}")
    for r in rr:
        print(f"  {r['p']} bal {float(r['bal']):8.2f} rep {r['dpd']:>5} od {r['od']} bkt {r['rep_bucket']:>3} st {r['pre']}/{r['post']} | pit {r['pit_dpd']:>5} oldest {r['oldest']} due {r['due_td']} rec {r['rec_td']} short {r['short']} expbal {r['exp_bal']} miss {r['nmiss']}")
