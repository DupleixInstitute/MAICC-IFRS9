import openpyxl, sys, re, warnings
warnings.filterwarnings('ignore')
from openpyxl.formula.translate import Translator
p=sys.argv[1]
wf=openpyxl.load_workbook(p,data_only=False)
wv=openpyxl.load_workbook(p,data_only=True)
f=wf['ECLComp']; v=wv['ECLComp']
cols=['R','U','X','Y','AR','AS','AV','AW','AX','AZ','BA','BB','BC','BE','BF','BG','CA','CB','CC']
for col in cols:
  base=f[col+'11'].value; dev=[]
  for r in range(12,119):
    val=f[f'{col}{r}'].value
    exp=Translator(base,origin=col+'11').translate_formula(col+str(r)) if isinstance(base,str) and base.startswith('=') else base
    if val!=exp: dev.append((r,val))
  print(col,'R11',base,'R12',f[col+'12'].value,'deviations',len(dev),dev[:6])
# last rows
for r in range(115,125):
  print(r,[ (c,f[f'{c}{r}'].value) for c in ['B','D','R','AV','BC'] ])
def n(x):
  try: return float(x)
  except: return 0.0
tot={'EAD':0,'PV':0,'ECL':0,'ECLno':0,'ECLnoSec':0}; st={}
rows=[]
for r in range(11,119):
  if f[f'B{r}'].value is None: continue
  ead=n(v[f'AV{r}'].value); pv=n(v[f'BB{r}'].value); pd=n(v[f'AR{r}'].value); lgd=n(v[f'AS{r}'].value); ecl=n(v[f'BC{r}'].value)
  stg=v[f'R{r}'].value
  eno=max(pd*lgd*ead,0)
  chk=max(pd*lgd*(ead-pv),0)
  s=st.setdefault(stg,{'n':0,'EAD':0,'PV':0,'ECL':0,'ECLno':0,'chk':0,'cb_nonzero':0,'lgd_wavg_num':0,'X':0,'BA':0})
  s['n']+=1; s['EAD']+=ead; s['PV']+=pv; s['ECL']+=ecl; s['ECLno']+=eno; s['chk']+=chk
  s['X']+=n(v[f'X{r}'].value); s['BA']+=n(v[f'BA{r}'].value); s['lgd_wavg_num']+=lgd*ead
  if n(v[f'CB{r}'].value)!=0: s['cb_nonzero']+=1
  rows.append((r,stg,pd,lgd,ead,pv,ecl,eno))
for k,s in st.items():
  print('Stage',k,{a:(round(b,2) if isinstance(b,float) else b) for a,b in s.items()}, 'LGDwavg',s['lgd_wavg_num']/s['EAD'] if s['EAD'] else None)
T={a:sum(s[a] for s in st.values()) for a in ['n','EAD','PV','ECL','ECLno','chk','X','BA']}
print('TOTAL',T,'BC8',v['BC8'].value,'diff',T['ECLno']-T['ECL'])
print('EAD-PV',T['EAD']-T['PV'],'ratio PV/EAD',T['PV']/T['EAD'],'(EAD-PV)/EAD',(T['EAD']-T['PV'])/T['EAD'])
# rows where ead-pv <0 or pd=1
print('PD>=1 rows',[(r,stg,round(ead),round(ecl),round(eno)) for r,stg,pd,lgd,ead,pv,ecl,eno in rows if pd>=0.999][:20])
print('LGD zero rows',sum(1 for x in rows if x[3]==0), 'LGD=1 rows',sum(1 for x in rows if x[3]>=0.999))
# discount factor implied
import statistics
print('sample pv/ead', [(r, round(pv/ead,4) if ead else None, v[f'AW{r}'].value) for r,stg,pd,lgd,ead,pv,ecl,eno in rows[:5]])
# top contributors to the uplift
up=sorted(rows,key=lambda x:-(x[7]-x[6]))[:8]
for r,stg,pd,lgd,ead,pv,ecl,eno in up: print('top',r,v[f'E{r}'].value,'stg',stg,'PD',round(pd,4),'LGD',round(lgd,4),'EAD',round(ead),'ECL',round(ecl),'ECLno',round(eno),'up',round(eno-ecl))
