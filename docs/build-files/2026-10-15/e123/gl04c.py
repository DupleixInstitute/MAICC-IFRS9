import csv, collections
d='C:/xampp/htdocs/MAICC-IFRS9/storage/app/bootstrap-packs/ebanker-pack-2026-10-12/'
a=list(csv.DictReader(open(d+'GL_04a_fee_gl_legs.csv',encoding='utf-8-sig')))
b=list(csv.DictReader(open(d+'GL_04b_fee_batch_legs.csv',encoding='utf-8-sig')))
print(collections.Counter((r['PASS_FLAG'],r['DELETE_FLAG'],r['MODIFY_DELETE_FLAG']) for r in a))
print(collections.Counter((r['BATCH_TYPE'],r['TRANTYPE'],r['DBCR']) for r in a if r['DELETE_FLAG']!='Y'))
def dt(s): m,dd,y=s.split('/'); return f"{y}-{int(m):02d}"
tot=collections.defaultdict(float)
for r in a:
  if r['DELETE_FLAG']=='Y' or r['BATCH_TYPE']=='YRCLO': continue
  tot[(dt(r['TRANSACTION_DATE'])[:4], r['AC_GLCODE'])]+=float(r['TRANSAMT'])
for k in sorted(tot): print(k, round(tot[k],2))
for r in a:
  if r['DELETE_FLAG']=='Y': print('DEL', r['TRANSACTION_DATE'], r['AC_GLCODE'], r['TRANSAMT'], r['BATCH_TYPE'], r['BATCH_NUMBER'], r['PARTICULARS'][:40])
