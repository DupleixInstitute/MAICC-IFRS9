SET @s='2025-09', @e='2026-09';
SELECT s.contract_id, s.customer_id, left(s.customer_name,30) name, s.loan_portfolio_id pf,
  COALESCE(NULLIF(s.ifrs9stage_post_qualitative,''),NULLIF(s.calculated_ifrs9_stage,''),s.ifrs9stage_pre_qualitative) sg,
  s.ifrs9stage_pre_qualitative pre, s.overdue_days sdpd, round(s.carrying_amount/1e6,2) sbal, s.due_date,
  COALESCE(NULLIF(e.ifrs9stage_post_qualitative,''),NULLIF(e.calculated_ifrs9_stage,''),e.ifrs9stage_pre_qualitative) eg,
  e.ifrs9stage_pre_qualitative epre, e.overdue_days edpd, round(e.carrying_amount/1e6,2) ebal,
  (select max(reporting_period) from loan_books x where x.contract_id=s.contract_id and x.reporting_period>=@s and x.reporting_period<@e) lastp,
  (select round(carrying_amount/1e6,2) from loan_books x where x.contract_id=s.contract_id and x.reporting_period>=@s and x.reporting_period<@e order by reporting_period desc limit 1) lastbal,
  (select contract_status from loan_books x where x.contract_id=s.contract_id and x.reporting_period>=@s and x.reporting_period<@e order by reporting_period desc limit 1) laststat
FROM loan_books s LEFT JOIN loan_books e ON e.contract_id=s.contract_id AND e.reporting_period=@e
WHERE s.reporting_period=@s AND s.loan_portfolio_id<>8
ORDER BY sg, s.carrying_amount desc;
