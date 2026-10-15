import csv, collections, sys
d='C:/xampp/htdocs/MAICC-IFRS9/storage/app/bootstrap-packs/ebanker-pack-2026-10-12/'
a=list(csv.DictReader(open(d+'GL_04a_fee_gl_legs.csv',encoding='utf-8-sig')))
b=list(csv.DictReader(open(d+'GL_04b_fee_batch_legs.csv',encoding='utf-8-sig')))
LOAN={'1050101','1050102','1050103','1050201','1050202','1050401'}
print(len(a),len(b))
print(collections.Counter(r['AC_GLCODE'] for r in a))
print(collections.Counter((r['DBCR'],r['TRANTYPE']) for r in a))
print('deleted a',sum(r['DELETE_FLAG']=='Y' for r in a),'deleted b',sum(r['DELETE_FLAG']=='Y' for r in b))
ida={r['CUMVOUCH_DET_ID'] for r in a}; idb={r['CUMVOUCH_DET_ID'] for r in b}
print('a in b',len(ida&idb), 'a not in b', len(ida-idb))
key=lambda r:(r['TRANSACTION_DATE'],r['BATCH_TYPE'],r['BATCH_NUMBER'])
batches=collections.defaultdict(list)
for r in b: batches[key(r)].append(r)
for r in a:
  if r['CUMVOUCH_DET_ID'] not in idb: batches[key(r)].append(r)
print('batches',len(batches))
cnt=collections.Counter()
for k,legs in batches.items():
  fees=[l for l in legs if l['AC_GLCODE'] in ('4871','4873','4874','4875')]
  loans={l['NEW_AC_NUMBER'] for l in legs if l['AC_GLCODE'] in LOAN}
  cnt[len(loans)]+=1
print('loans per batch',cnt)
print(collections.Counter(l['AC_GLCODE'] for legs in batches.values() for l in legs).most_common(30))
