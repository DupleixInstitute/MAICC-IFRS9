from lz import *
from collections import Counter, defaultdict
lp = ','.join(str(x) for x in landed())
rows = q(f"select account, json_unquote(json_extract(payload,'$.TRANSACTION_DATE')) t, json_unquote(json_extract(payload,'$.ACTIVE_FLAG')) af, json_unquote(json_extract(payload,'$.DELETE_FLAG')) df, query_id from ebanker_raw_rows where superseded_at is null and load_id in ({lp}) and query_id in ('P2_10','RS_02a')")
by = defaultdict(Counter)
for r in rows: by[(r['query_id'], r['account'])][(r['t'], r['af'], r['df'])] += 1
tot = Counter()
for (qid, a), c in sorted(by.items()):
    if qid == 'P2_10':
        tot[tuple(sorted(set(k[0] for k in c)))[-1]] += 1
print('P2_10 latest txn date per account:', tot.most_common())
ra = Counter()
for (qid, a), c in by.items():
    if qid == 'RS_02a':
        for k, n in c.items(): ra[(k[1], k[2])] += n
print('RS_02a flags:', ra)
