import csv, collections, sys, datetime
d='C:/xampp/htdocs/MAICC-IFRS9/storage/app/bootstrap-packs/ebanker-pack-2026-10-12/'
a=list(csv.DictReader(open(d+'GL_04a_fee_gl_legs.csv',encoding='utf-8-sig')))
b=list(csv.DictReader(open(d+'GL_04b_fee_batch_legs.csv',encoding='utf-8-sig')))
LOAN={'1050101','1050102','1050103','1050201','1050202','1050401'}
FEE={'4871','4873','4874','4875'}
def dt(s): m,dd,y=s.split('/'); return f"{y}-{int(m):02d}-{int(dd):02d}"
key=lambda r:(dt(r['TRANSACTION_DATE']),r['BATCH_TYPE'],r['BATCH_NUMBER'])
batches=collections.defaultdict(list)
for r in b: batches[key(r)].append(r)
yr=collections.Counter(); 
for k in sorted(batches):
  legs=[l for l in batches[k] if l['DELETE_FLAG']!='Y']
  fees=[l for l in legs if l['AC_GLCODE'] in FEE]
  if not fees: continue
  if k[0] < sys.argv[1] or k[0] > sys.argv[2]: continue
  print('==',k)
  for l in legs:
    print('   ',l['AC_GLCODE'],l['NEW_AC_NUMBER'],l['DBCR'],l['TRANTYPE'],l['TRANSAMT'],l['PARTICULARS'][:40])
