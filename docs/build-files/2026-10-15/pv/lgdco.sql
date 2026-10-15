SELECT s.contract_id, left(s.customer_name,26) name, s.loan_portfolio_id spf, round(s.carrying_amount/1e6,2) sb, s.overdue_days sdpd, s.ifrs9stage_pre_qualitative spre,
 e2.loan_portfolio_id epf_any, e.loan_portfolio_id epf_same, COALESCE(e.calculated_ifrs9_stage,'(absent)') cs, round(e2.carrying_amount/1e6,2) eb_any, e2.calculated_ifrs9_stage cs_any, e2.overdue_days edpd,
 round((e2.repayments - s.repayments)/1e6,2) rep_delta
FROM loan_books s
LEFT JOIN loan_books e ON e.contract_id=s.contract_id AND e.loan_portfolio_id=s.loan_portfolio_id AND e.reporting_period=@e
LEFT JOIN loan_books e2 ON e2.contract_id=s.contract_id AND e2.reporting_period=@e
WHERE s.reporting_period=@s AND s.calculated_ifrs9_stage='3' AND s.loan_portfolio_id<>8
ORDER BY s.carrying_amount DESC;
