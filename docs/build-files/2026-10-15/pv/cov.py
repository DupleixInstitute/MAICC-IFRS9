from lz import *
from collections import Counter, defaultdict
lp = ','.join(str(x) for x in landed())
r = q(f"select query_id, count(distinct account) n, count(*) c from ebanker_raw_rows where superseded_at is null and load_id in ({lp}) and query_id in ('P2_10','RS_02a','RS_02b','RS_02c','RS_02d','RS_02e','RS_02g','P3_11') and account not like '0001050%%' group by query_id")
for x in r: print(x)
# RS_02a sample and fields
s = q(f"select account,row_date,payload from ebanker_raw_rows where superseded_at is null and query_id='RS_02a' and account='000104450000056' limit 3")
for x in s: print(x['row_date'], x['payload'][:600])
g = q(f"select account,row_date,payload from ebanker_raw_rows where superseded_at is null and query_id='RS_02g' limit 3")
for x in g: print(x['payload'][:800])
