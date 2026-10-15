import openpyxl, sys, warnings, itertools
warnings.filterwarnings('ignore')
p=sys.argv[1]
wf=openpyxl.load_workbook(p,data_only=False)
wv=openpyxl.load_workbook(p,data_only=True)
n=wv['NPL']; print('NPL A217',n['A217'].value,n['B217'].value,'hdr BK',[n.cell(r,63).value for r in range(1,5)])
ca=wf['Closed Accounts']; cv=wv['Closed Accounts']; print('Closed dims',ca.dimensions)
for r in range(1,6): print([str(x)[:25] for x in [c.value for c in ca[r]] if x is not None])
lb=wf['Loan Book']; lv=wv['Loan Book']
for r in range(126,136):
  out=[(c.coordinate,str(c.value)[:60],lv[c.coordinate].value) for c in lb[r] if c.value is not None]
  if out: print(out)
for r in range(1,6): print('LB hdr',[str(x)[:25] for x in [c.value for c in lb[r]] if x is not None])
# brute-force: any ratio of two numeric values in ECLComp rows 1-10/120-124, Loan Book 120-140, Closed Accounts totals that equals 0.4697984
target=0.4697984013775794
pool=[]
for s in wv.sheetnames:
  ws=wv[s]
  if ws.max_row*ws.max_column>400000: continue
  for row in ws.iter_rows():
    for c in row:
      x=c.value
      if isinstance(x,(int,float)) and not isinstance(x,bool) and abs(x)>1e-9: pool.append((abs(float(x)),s,c.coordinate))
print('pool',len(pool))
import bisect
pool.sort()
vals=[p[0] for p in pool]
hits=[]
for a,s,co in pool:
  t=a*target  # find b = a*target with a as denominator
  i=bisect.bisect_left(vals,t*(1-1e-9))
  while i<len(vals) and vals[i]<=t*(1+1e-9):
    hits.append((pool[i],(a,s,co))); i+=1
  t2=a*(1-target)
  i=bisect.bisect_left(vals,t2*(1-1e-9))
  while i<len(vals) and vals[i]<=t2*(1+1e-9):
    hits.append(('1-r',pool[i],(a,s,co))); i+=1
import re
def triv(t): return t[1].startswith("ECLComp") and re.match(r"(CB|CF)d",t[2])
hits=[h for h in hits if not any(triv(x) for x in h if isinstance(x,tuple))]
print('ratio hits',len(hits)); 
for h in hits[:40]: print(h)
