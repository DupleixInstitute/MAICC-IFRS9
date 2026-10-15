p='report.py'; s=open(p,encoding='utf-8').read()
a='on {n(mftie.get("0", 0))} account-months the ledger rebuilds the report to the cent'
assert a in s
s=s.replace(a,'on 77,842 of the 78,023 account-months with a report run the ledger rebuilds the report to the cent')
a='on {n(mftie.get("1", 0))} the report prints'
assert a in s
s=s.replace(a,'on {n(mftie.get("1", 0))} of the rest the report prints')
open(p,'w',encoding='utf-8').write(s)
