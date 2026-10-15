import csv, collections
d='C:/xampp/htdocs/MAICC-IFRS9/storage/app/bootstrap-packs/ebanker-pack-2026-10-12/'
b=list(csv.DictReader(open(d+'GL_04b_fee_batch_legs.csv',encoding='utf-8-sig')))
LOAN={'1050101','1050102','1050103','1050201','1050202','1050401'}
FEE={'4871','4873','4874','4875'}
key=lambda r:(r['TRANSACTION_DATE'],r['BATCH_TYPE'],r['BATCH_NUMBER'])
B=collections.defaultdict(list)
for r in b:
  if r['DELETE_FLAG']!='Y': B[key(r)].append(r)
n=0
for k,legs in sorted(B.items()):
  loans=[l for l in legs if l['AC_GLCODE'] in LOAN]
  fees=[l for l in legs if l['AC_GLCODE'] in FEE]
  if not loans or not fees: continue
  ls=sum(-float(l['TRANSAMT']) for l in loans); fs=sum(float(l['TRANSAMT']) for l in fees)
  pure = abs(ls-fs)<0.01 and len(legs)==len(loans)+len(fees)
  if not pure:
    n+=1
    print('==',k)
    for l in legs: print('   ',l['AC_GLCODE'],l['NEW_AC_NUMBER'],l['DBCR'],l['TRANTYPE'],l['TRANSAMT'],l['PARTICULARS'][:45])
print('non-pure',n)
mf=[k for k,legs in B.items() if any(l['AC_GLCODE'].startswith('10503') for l in legs)]
print('batches with mega farm legs',len(mf))
