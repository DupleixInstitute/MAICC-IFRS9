select reporting_period p, count(*) n,
 round(sum(case when ead>0 then ead else 0 end)/1e6,1) ead,
 concat(sum(ifrs9stage_post_qualitative='1'),'/',sum(ifrs9stage_post_qualitative='2'),'/',sum(ifrs9stage_post_qualitative='3')) st,
 round(sum(case when ifrs9stage_post_qualitative='1' then ecl_value end)/1e6,1) e1,
 round(sum(case when ifrs9stage_post_qualitative='2' then ecl_value end)/1e6,1) e2,
 round(sum(case when ifrs9stage_post_qualitative='3' then ecl_value end)/1e6,1) e3,
 round(sum(ecl_value)/1e6,1) ecl, round(sum(ecl_value_discounted)/1e6,1) disc
from loan_books where loan_portfolio_id<>8 and reporting_period>='2025-12' group by 1;
