<?php
// add new columns in database ie contractual years remaining and life_cover_amount

$save_sql=
"REPLACE INTO creditscore 
(
	loan_category,
	omang_passport_num,
	cif,
	loan_number,
	marital_status_score, 
	w_marital_status_score,
	age_score,
	w_age_score,
	no_of_children_score,
	w_no_of_children_score,
	dependants_at_home ,
	w_dependants_at_home,
	education_level_score,
	w_education_level_score,
	professional_score, 
	w_professional_score,
	employment_score, 
	w_employment_score, 
	years_at_job_score, 
	w_years_at_job_score, 
	revenues_score, 
	w_revenues_score,
	location_score, 
	w_location_score, 
	relationship_score, 
	w_relationship_score, 
	total_bbs_products_score, 
	w_total_bbs_products_score, 
	loan_arrears, 
	w_loan_arrears, 
	renegotiate_score, 
	w_renegotiate_score, 
	paid_debts_score, 
	w_paid_debts_score, 
	judgement_score, 
	w_judgement_score, 
	default_data_score, 
	w_default_data_score, 
	trace_alerts_score, 
	w_trace_alerts_score, 
	card_held_since_score, 
	w_card_held_since_score, 
	loans_outstanding_score, 
	w_loans_outstanding_score,
	affordability_ratio_score, 
	w_affordability_ratio_score, 
	rate_type_score, 
	w_rate_type_score, 
	loan_maturity_score, 
	w_loan_maturity_score,
	score,
	deduct_score,
	w_deduct_score,
	age_comment
) 
VALUES
(
	'$loan_category',
	'$omang_passport_num',
	'$cif' ,
	'$loan_number',
	'$marital_status_score',
	'$w_marital_status_score',
	'$age_score',
	'$w_age_score',
	'$no_of_children_score',
	'$w_no_of_children_score',
	'$dependants_at_home',
	'$w_dependants_at_home_score',
	'$education_level_score',
	'$w_education_level_score',
	'$professional_score', 
	'$w_professional_score',
	'$employment_score', 
	'$w_employment_score', 
	'$years_at_job_score', 
	'$w_years_at_job_score', 
	'$revenues_score', 
	'$w_revenues_score',
	'$location_score', 
	'$w_location_score', 
	'$relationship_score', 
	'$w_relationship_score', 
	'$total_bbs_products_score', 
	'$w_total_bbs_products_score', 
	'$loan_arrears', 
	'$w_loan_arrears_score', 
	'$renegotiate_score', 
	'$w_renegotiate_score', 
	'$paid_debts_score', 
	'$w_paid_debts_score', 
	'$judgement_score', 
	'$w_judgement_score', 
	'$default_data_score', 
	'$w_default_data_score', 
	'$trace_alerts_score', 
	'$w_trace_alerts_score', 
	'$card_held_since_score', 
	'$w_card_held_since_score', 
	'$loans_outstanding_score', 
	'$w_loans_outstanding_score',
	'$affordability_ratio_score', 
	'$w_affordability_ratio_score', 
	'$rate_type_score', 
	'$w_rate_type_score', 
	'$loan_maturity_score', 
	'$w_loan_maturity_score',
	'$score',
	'$deduct_score',
	'$w_deduct_score',
	'$age_comment'
)";
// executie query in either old or new PHP
try {  
   //old php
   if (!mysql_query($save_sql,$connect)){
      die('Error1: ' . mysql_error());
   }
   //close connection;
} catch (Error $e) {
   //new php
   if (!mysqli_query($connect,$save_sql)){
      die('Error1: ' . mysqli_error());
   }	
}
//close connection
try {mysql_close($connect);}catch(Error $e){mysqli_close($connect);}	
?>