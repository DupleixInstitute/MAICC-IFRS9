with l as (
 select account, row_date d, json_unquote(json_extract(payload,'$.TRANTYPE')) t,
   cast(replace(json_unquote(json_extract(payload,'$.TRANSAMT')),',','') as decimal(20,2)) a, source_key k
 from ebanker_raw_rows where query_id in ('P1_01','M09_01') and superseded_at is null
   and coalesce(json_unquote(json_extract(payload,'$.DELETE_FLAG')),'N') <> 'Y')
select r.t, r.account, r.d, r.a,
 (select min(datediff(r.d, o.d)) from l o where o.account=r.account and o.a=-r.a and o.d<=r.d and o.t not in ('306','343','901')) gap,
 (select count(*) from l o where o.account=r.account and o.a=-r.a and o.t not in ('306','343','901')) cands
from l r where r.t in ('306','343','901') group by r.k order by gap desc limit 50;
