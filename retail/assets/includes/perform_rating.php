<?php
/**  PERFORMING RATING

     Variable,             Category,       Weight,  ,    MaxScore, MinScore, No.of Variables,    Line No   Comment
	 =========,            ========,       ======,       ========, ========, ===============,    =======
	 $age,                 Personal Data,   0.02,  (2%),     400,      100,           4,    
     $marital_status,	   Personal Data,   0.03,  (3%),     500,      200,           5,
	 $no_of_children,      Personal Data,   0.01,  (1%),     400,      100,          11,
     $dependants_at_home,  Personal Data,   0.01,  (1%),     300,      100,           9,
     $education,           Personal Data,   0.04,  (4%),     350,      100,           9,
     $professional,        Personal Data,   0.08,  (8%),     500,      200,           4,
     $employment,          Personal Data,   0.08,  (8%),     500,      200,           3,                   Type of contract
     $years_at_job,        Personal Data,   0.02,  (2%),     400,      200,           4,
	 $revenues,            Personal Data,   0.03,  (3%),     500,      250,           3,                   Single or double salary
	 $location,            Personal Data,   0.02,  (3%),     400,      200,           4,
	 $relationship,        Length,          0.03,  (2%),     500,      100,           4,    
     $total_bbs_products,  Length,          0.02,  (2%),     500,      100,           5,
     $loan_arrears,        Length,          0.10,  (10%),    400,       0,            5,                   ?is na without quotes rated?
     $renegotiate,         Length,          0.15,  (15%),    400,       0,            4,                   Check if N/A is score (not in quotation marks)
     $paid_debts,          ITC,             0.02,  (2%),     250,       0,            2,
	 $judgement,           ITC,             0.09,  (9%),     500,       0,            4,                   
	 $default_data,        ITC,             0.09,  (9%),     500,       0,            4,
	 $trace_alerts,        ITC,             0.02,  (2%),     500,       0,            4,                   ? shouldnt it have more weights
**** card_held_since,      Personal,        0.02,  (2%),     250,       0,            5,                   ? No $ on variable (is it rated)
**** loans_outstanding,    Personal,        0.02,  (2%),     250,       0,            5,                   ? No $ on variable (is it rated)
     $affordability_ratio, Personal,        0.05,  (5%),     500,       0,            5,                   ? Does if for status comment work without {} 
     $dsr,                 Personal,        0.03,  (3%),     400,      100,           2,
**** $deduct,              ITC,             0.02,  (2%),     400,      200,           2,                   ? Not being tallied?? Not too low weight?
     $rate_type,           Product,         0.02,  (2%),     400,      200,           3,
     $loan_maturity,       Product,         0.01,  (1%),     400,      250,           7,
	 
**/
//-----------------AGE GROUP OF BORROWER-------------------------------------------------------------

switch ($age) {
    case ($age > 17 AND $age < 25):
       $age_score=100;
		break;
    case ($age > 24 AND $age < 35):
        $age_score=200;
        break;
    case ($age > 34  AND $age < 46):
      $age_score=400;
        break;
	case ($age > 45):
       $age_score=200;
       break;
	
}
$w_age_score=$age_score*0.02;

$score_sql        = "SELECT * FROM model_calibration";
$score_sql_result = mysqli_query($connect,$score_sql); 

$scores = [];
while ($row = mysqli_fetch_array($score_sql_result)) {
   //determine score for affordability ratio
   if ($affordability_ratio   >  floatval($row['score_option_min']  )*100   and 
       $affordability_ratio   <  floatval($row['score_option_upper'])*100   and
	   $row['charecteristic'] == 'Affordability Ratio') 
   {
       $affordability_score         = $row[$loan_category.'_max_score'];
       $w_affordability_score       = $row[$loan_category.'_effective_score'];
       $affordability_score_comment_flag 
                 = $row['reject_threshold'];
       $affordability_score_comment
                 = ($affordability_score_comment_flag == 0) ? "OK":"REJECT";
   }	   
   //mortgage
   $scores['mortgage']          
          [$row['charecteristic']]
          [$row['score_option']]
		  ['reject_threshold']    = $row['reject_threshold'];

   $scores['mortgage']          
          [$row['charecteristic']]
          [$row['score_option']]
		  ['mortgage_max_score']         = $row['mortgage_max_score'];
		  
   $scores['mortgage']          
		  [$row['charecteristic']]
		  [$row['score_option']]
		  ['mortgage_effective_score']   = $row['mortgage_effective_score'];

   $scores['mortgage']          
		  [$row['charecteristic']]
		  [$row['score_option']]
		  ['mortgage_effective_weight']  = $row['mortgage_overall_effective_weight'];
   
   //Personal Loans
   $scores['unsecured_loan']          
          [$row['charecteristic']]
          [$row['score_option']]
		  ['reject_threshold']           = $row['reject_threshold'];

   $scores['unsecured_loan']          
          [$row['charecteristic']]
          [$row['score_option']]
		  ['unsecured_loan_max_score']         = $row['unsecured_loan_max_score'];
		  
   $scores['unsecured_loan']          
		  [$row['charecteristic']]
		  [$row['score_option']]
		  ['unsecured_loan_effective_score']   = $row['unsecured_loan_effective_score'];

   $scores['unsecured_loan']          
		  [$row['charecteristic']]
		  [$row['score_option']]
		  ['unsecured_loan_effective_weight']  = $row['unsecured_loan_overall_effective_weight'];		  
}
$marital_status_score   = $scores[$loan_category]['Marital Status'][$marital_status][$loan_category.'_max_score'];   
$w_marital_status_score = $scores[$loan_category]['Marital Status'][$marital_status][$loan_category.'_effective_score']; 

$no_of_children_score   = $scores[$loan_category]['Number of children'][$no_of_children][$loan_category.'_max_score'];   
$w_no_of_children_score = $scores[$loan_category]['Number of children'][$no_of_children][$loan_category.'_effective_score']; 

$dependants_at_home_score    = $scores[$loan_category]['Number of household members'][$dependants_at_home][$loan_category.'_max_score'];   
$w_dependants_at_home_score  = $scores[$loan_category]['Number of household members'][$dependants_at_home][$loan_category.'_effective_score']; 

$education_level_score   = $scores[$loan_category]['Education level'][$education][$loan_category.'_max_score'];   
$w_education_level_score = $scores[$loan_category]['Education level'][$education][$loan_category.'_effective_score']; 

$professional_score    = $scores[$loan_category]['Professional category '][$professional][$loan_category.'_max_score'];   
$w_professional_score  = $scores[$loan_category]['Professional category '][$professional][$loan_category.'_effective_score']; 

$employment_score    = $scores[$loan_category]['Employment Contract Type'][$employment][$loan_category.'_max_score'];   
$w_employment_score  = $scores[$loan_category]['Employment Contract Type'][$employment][$loan_category.'_effective_score']; 

$years_at_job_score    = $scores[$loan_category]['Years with employer'][$years_at_job][$loan_category.'_max_score'];   
$w_years_at_job_score  = $scores[$loan_category]['Years with employer'][$years_at_job][$loan_category.'_effective_score']; 

$revenues_score        = $scores[$loan_category]['Single or double salary'][$revenues][$loan_category.'_max_score'];   
$w_revenues_score      = $scores[$loan_category]['Single or double salary'][$revenues][$loan_category.'_effective_score']; 

$location_score        = $scores[$loan_category]['Area of residence'][$location][$loan_category.'_max_score'];   

$w_location_score      = $scores[$loan_category]['Area of residence'][$location][$loan_category.'_effective_score']; 

$relationship_score    = $scores[$loan_category]['Years of banking relationship with BBS'][$relationship][$loan_category.'_max_score'];   
$w_relationship_score  = $scores[$loan_category]['Years of banking relationship with BBS'][$relationship][$loan_category.'_effective_score']; 

$total_bbs_products_score    = $scores[$loan_category]['No of banking products'][$total_bbs_products][$loan_category.'_max_score'];   
$w_total_bbs_products_score  = $scores[$loan_category]['No of banking products'][$total_bbs_products][$loan_category.'_effective_score']; 

$loan_arrears_score    = $scores[$loan_category]['No of Arrears'][$loan_arrears][$loan_category.'_max_score'];   
$w_loan_arrears_score  = $scores[$loan_category]['No of Arrears'][$loan_arrears][$loan_category.'_effective_score']; 

$renegotiate_score      = $scores[$loan_category]['Renegotiated'][$renegotiate][$loan_category.'_max_score'];   
$w_renegotiate_score    = $scores[$loan_category]['Renegotiated'][$renegotiate][$loan_category.'_effective_score'];
$renegotiate_comment_flag 
                  = $scores[$loan_category]['Renegotiated'][$renegotiate]['reject_threshold'];
$renegotiate_comment
                  = $renegotiate_comment_flag = 0 ? "OK":"REJECT";
				  
$paid_debts_score      = $scores[$loan_category]['Paid Debts'][$paid_debts][$loan_category.'_max_score'];   
$w_paid_debts_score    = $scores[$loan_category]['Paid Debts'][$paid_debts][$loan_category.'_effective_score']; 
$paid_debts_comment_flag 
                 = $scores[$loan_category]['Paid Debts'][$paid_debts]['reject_threshold'];
$paid_debts_score_comment
                 = $paid_debts_comment_flag = 0 ? "OK":"REJECT";
	

$judgement_score       = $scores[$loan_category]['Judgements'][$judgement][$loan_category.'_max_score'];   
$w_judgement_score     = $scores[$loan_category]['Judgements'][$judgement][$loan_category.'_effective_score']; 
$judgement_comment_flag 
                 = $scores[$loan_category]['Judgements'][$judgement]['reject_threshold'];
$judgement_score_comment
                 = $judgement_comment_flag = 0 ? "OK":"REJECT";



$default_data_score    = $scores[$loan_category]['Defaults'][$default_data][$loan_category.'_max_score'];   
$w_default_data_score  = $scores[$loan_category]['Defaults'][$default_data][$loan_category.'_effective_score']; 
$default_data_comment_flag 
                 = $scores[$loan_category]['Defaults'][$default_data]['reject_threshold'];
$default_data_score_comment
                 = $default_data_comment_flag = 0 ? "OK":"REJECT";

$trace_alerts_score    = $scores[$loan_category]['Traces'][$trace_alerts][$loan_category.'_max_score'];   
$w_trace_alerts_score  = $scores[$loan_category]['Traces'][$trace_alerts][$loan_category.'_effective_score'];
$trace_alerts_comment_flag 
                 = $scores[$loan_category]['Traces'][$trace_alerts]['reject_threshold'];
$trace_alerts_score_comment
                 = $trace_alerts_comment_flag = 0 ? "OK":"REJECT";

$card_held_since_score     = $scores[$loan_category]['Number of credit cards personally held'][$card_held_since][$loan_category.'_max_score'];   
$w_card_held_since_score   = $scores[$loan_category]['Number of credit cards personally held'][$card_held_since][$loan_category.'_effective_score'];

$loans_outstanding_score   = $scores[$loan_category]['Number of loans presently outstanding'][$card_held_since][$loan_category.'_max_score'];   
$w_loans_outstanding_score = $scores[$loan_category]['Number of loans presently outstanding'][$card_held_since][$loan_category.'_effective_score'];

$rate_type_score   = $scores[$loan_category]['Loan Type'][$rate_type][$loan_category.'_max_score'];   
$w_rate_type_score = $scores[$loan_category]['Loan Type'][$rate_type][$loan_category.'_effective_score'];

$deduct_score      = $scores[$loan_category]['deduct_at_source'][$deduct][$loan_category.'_max_score'];   
$w_deduct_score    = $scores[$loan_category]['deduct_at_source'][$deduct][$loan_category.'_effective_score'];


/**
//---------------MARITAL STATUS----------------------------------------------------------------------						    
switch ($marital_status) {
    case "Single":
        $marital_status_score=300;
		break;
    case "Married":
        $marital_status_score=400;
        break;
    case "Divorced":
       $marital_status_score=500;
        break;
	case "Widowed":
        $marital_status_score=300;
        break;
	case "Other":
       $marital_status_score=200;
        break;
}
$w_marital_status_score=$marital_status_score*0.03;

//---------------NUMBER OF CHILDREN----------------------------------------------------------------------
switch($no_of_children){
case 0:
       $no_of_children_score=100;
		break;
case 1:
       $no_of_children_score=200;
		break;
case 2:
       $no_of_children_score=300;
		break;

case 3:
       $no_of_children_score=400;
		break;

case 4:
       $no_of_children_score=300;
		break;

case 5:
       $no_of_children_score=200;
		break;

case 6:
       $no_of_children_score=100;
		break;
case 7:
       $no_of_children_score=100;
		break;
case 8:
       $no_of_children_score=100;
		break;
case 9:
       $no_of_children_score=100;
		break;
case 10:
       $no_of_children_score=100;
		break;
}
$w_no_of_children_score=$no_of_children_score*0.01;


//---------------TOTAL DEPENDANTS LIVING AT HOME-----------------------------------------------------------------------
switch($dependants_at_home){
case 0:
      $dependants_at_home_score=100;
		break;
case 1:
        $dependants_at_home_score=200;
		break;
case 2:
      $dependants_at_home_score=300;
		break;
case 3:
       $dependants_at_home_score=300;
		break;
case 4:
       $dependants_at_home_score=100;
		break;
case 5:
       $dependants_at_home_score=100;
		break;
case 6:
       $dependants_at_home_score=100;
		break;
case 7:
       $dependants_at_home_score=100;
		break;
case 8:
       $dependants_at_home_score=100;
		break;
}
$w_dependants_at_home_score=$dependants_at_home_score*0.01;
//---------------EDUCATION LEVEL SCORE-----------------------------------------------------------------------
switch($education){
case 'Primary Education':
       $education_level_score=150;
		break;
case 'Secondary Education':
       $education_level_score=200;
		break;
case 'Certificate':
      $education_level_score=250;
		break;		
case 'Diploma':
      $education_level_score=300;
		break;
case 'Degree':
       $education_level_score=350;
		break;
case 'Masters Degree':
       $education_level_score=350;
		break;
case 'PHD':
      $education_level_score=350;
		break;
case 'Professional Course':
      $education_level_score=350;
		break;
case 'None':
       $education_level_score=100;
		break;
}
$w_education_level_score=$education_level_score*0.04;
	
//----------------PROFESSIONAL CATEGORY SCORE----------------------------------------------------------
switch($professional){
case 'Civil Servant':
       $professional_score=500;
		break;
case 'Employee':
       $professional_score=400;
		break;
case 'Self Employed':
       $professional_score=200;
		break;
case 'Other':
       $professional_score=200;
		break;
}
$w_professional_score=$professional_score*0.08;

//------------------EMPLOYMENT CONTRACT TYPE SCORE---------------------------------------------------

switch($employment){
case 'Permanent':
       $employment_score=500;
		break;
case 'Contractual':
       $employment_score=300;
		break;
case 'Other':
       $employment_score=200;
		break;
}
$w_employment_score=$employment_score*0.08;

//-------------------------------YEARS WITH EMPLOYER----------------------------------------------

switch($years_at_job){
case "0 to 1":
       $years_at_job_score=200;
		break;
case "1 to 5":
      $years_at_job_score =400;
		break;
case "5 to 20":
       $years_at_job_score=300;
		break;
case "20 and more":
       $years_at_job_score=300;
		break;
}
$w_years_at_job_score=$years_at_job_score*0.02;

//-----------------------SINGLE OR DOUBLE SALARY SCORE----------------------------------------------

switch($revenues){
case 'Single':
       $revenues_score=250;
		break;
case 'Double':
       $revenues_score=500;
		break;
}
$w_revenues_score=$revenues_score*0.03;

//-----------------------AREA OF RESIDENCE SCORE---------------------------------------------------

switch($location){
case ($location=='Urban'
       $location_score=400;
		break;
case ($location=='Semi Urban A');
       $location_score=350;
		break;
case ($location=='Semi Urban B');
       $location_score=300;
		break;
case ($location=='Rural');
       $location_score=200;
		break;
}
$w_location_score=$location_score*0.02;


//--------------LENGTH OF RELATIONSHIP-----------------------------------------------------------------------
switch($relationship)
{
case ($relationship=='NA');
   $relationship_score=100;
   break;
case ($relationship=='Since less than 1 year');
   $relationship_score=200;
   break;
case ($relationship=='Since 1 to 5 years');
   $relationship_score=400;
   break;
case ($relationship=='More than 5 years');
   $relationship_score=500;
   break;
}
$w_relationship_score=$relationship_score*0.03;
//---------------TOTAL BBS PRODUCTS-----------------------------------------------------------------------
switch($total_bbs_products){
case 0:
       $total_bbs_products_score=100;
		break;
case 1:
       $total_bbs_products_score=200;
		break;
case 2:
       $total_bbs_products_score=300;
		break;

case 3:
       $total_bbs_products_score=400;
		break;

case 4:
       $total_bbs_products_score=500;
		break;
}
 $w_total_bbs_products_score= $total_bbs_products_score*0.02;
 
 
//---------------LOAN ARREARS-----------------------------------------------------------------------
	
switch($loan_arrears){
case 0:
       $loan_arrears_score=400;
	   $loan_arrears_score_comment='OK';
		break;
case 1:
       $loan_arrears_score=100;
	     $loan_arrears_score_comment='OK';
		break;
case 2:
       $loan_arrears_score=50;
	     $loan_arrears_score_comment='OK';
		break;
case 3:
       $loan_arrears_score=0;
	   $loan_arrears_score_comment='REJECT';
		break;
case "na":
       $loan_arrears_score=100;
	     $loan_arrears_score_comment='OK';
		break;
}
 $w_loan_arrears_score= $loan_arrears_score*0.10; 
 
 
 //---------------RENEGOTIATIONS-----------------------------------------------------------------------
switch($renegotiate){
case "0":
       $renegotiate_score=400;
	    $renegotiate_comment='OK';
		break;
case "1":
       $renegotiate_score=100;
	    $renegotiate_comment='OK';
		break;
case "2":
       $renegotiate_score=0;
	   $renegotiate_comment='REJECT';
		break;
case "N/A":
       $renegotiate_score=100;
	   $renegotiate_comment='OK';
		break;
 } 
$w_renegotiate_score=$renegotiate_score*0.15;


//---------------PAID DEBTS----------------------------------------------------------------------- 

 //echo 'ásk about paid debts';
switch($paid_debts){
case 0:
       $paid_debts_score=0;
	    $paid_debts_score_comment='REJECT';
		break;
case 1:
       $paid_debts_score=250;
	    $paid_debts_score_comment='OK';
		break;
}
 $w_paid_debts_score= $paid_debts_score*0.02;
//---------------JUDGEMENT-----------------------------------------------------------------------
switch($judgement) 
{
case 0:
       $judgement_score=500;
	    $judgement_score_comment='OK';
		break;
case 1:
       $judgement_score=200;
	    $judgement_score_comment='OK';
		break;
case 2:
       $judgement_score=100;
	      $judgement_score_comment='OK';
		break;
case 3:
       $judgement_score=0;
	   $judgement_score_comment='REJECT';
		break;
case $judgement>3:
       $judgement_score=0;
	   $judgement_score_comment='REJECT';
		break;		
 }

$w_judgement_score=$judgement_score * 0.09;



//---------------DEFAULT DATA - ITC----------------------------------------------------------------------
switch($default_data){
case 0:
       $default_data_score=500;
	   $default_data_score_comment='OK';
		break;
case 1:
       $default_data_score=200;
	   	   $default_data_score_comment='OK';
		break;
case 2:
       $default_data_score=100;
	   	   $default_data_score_comment='OK';
		break;
case 3:
       $default_data_score=0;
	   $default_data_score_comment='REJECT';
		break;
case $default_data>3:
       $default_data_score=0;
	   $default_data_score_comment='REJECT';
		break;		
}

$w_default_data_score=$default_data_score* 0.09;

//PAID DEBTS VS DEFAULTS----------------------------------------------------------------------------------

//---------------TRACE ALERTS ITC-----------------------------------------------------------------------

switch($trace_alerts){
case 0:
       $trace_alerts_score=500;
	     $trace_alerts_score_comment='OK';
		break;
case 1:
       $trace_alerts_score=200;
	    $trace_alerts_score_comment='OK';
		break;
case 2:
       $trace_alerts_score=100;
	    $trace_alerts_score_comment='OK';
		break;
case 3:
       $trace_alerts_score=0;
	   $trace_alerts_score_comment='REJECT';
		break;
case $trace_alerts>3:
       $trace_alerts_score=0;
	   $trace_alerts_score_comment='REJECT';
		break;
}
$w_trace_alerts_score=$trace_alerts_score * 0.02;


//---------------CREDIT CARD HELD SINCE-----------------------------------------------------------------------

switch($card_held_since){
case 0:
       $card_held_since_score=100;
		break;
case 1:
       $card_held_since_score=200;
		break;
case 2:
       $card_held_since_score=250;
		break;
case 3:
       $card_held_since_score=100;
	   
		break;
case 4:
       $card_held_since_score=0;
		break;
}
$w_card_held_since_score=$card_held_since_score*0.02;




//---------------OUTSTANDING LOANS-----------------------------------------------------------------------

switch($loans_outstanding){
case 0:
       $loans_outstanding_score=100;
		break;
case 1:
       $loans_outstanding_score=200;
		break;
case 2:
       $loans_outstanding_score=250;
		break;
case 3:
       $loans_outstanding_score=100;
	   
		break;
case 4:
       $loans_outstanding_score=0;
		break;
}
$w_loans_outstanding_score=$loans_outstanding_score*0.02;




//---------------AFFORDABILITY RATIO-----------------------------------------------------------------------
//echo "ask about affordability";
switch($affordability_ratio){
case ($affordability_ratio > 0 and $affordability_ratio < 16);
    $affordability_ratio_score =500;
    break;
case ($affordability_ratio > 15 and $affordability_ratio < 31);
    $affordability_ratio_score =400;
    break;
case ($affordability_ratio > 30 and $affordability_ratio < 41);
    $affordability_ratio_score =200;
    break;
case ($affordability_ratio > 40 and $affordability_ratio < 50.5);
    $affordability_ratio_score =100;
    break;
case ($affordability_ratio > 50.4);
    $affordability_ratio_score =0;
    break;
}

//?   does the if condition work without curly brackets????//
$w_affordability_ratio_score=$affordability_ratio_score*0.05;
if   ($affordability_ratio > 40) $affordability_ratio_comment="REJECT";
else  $affordability_ratio_comment="OK";
/**
//DEDUCTION FROM SOURCE-------------------------------------------------------------------------------------
switch($deduct)
{
case ($deduct=="Yes");
      $deduct_score=400;
	  break;
case ($deduct=="No");
      $deduct_score=200;
	  break;	
}
$w_deduct_score=$deduct_score*0.02;
//---------------INTEREST RATE TYPE-----------------------------------------------------------------------
switch($rate_type)
{
case ($rate_type=="Fixed");
       $rate_type_score=400;
		break;
case ($rate_type=="Floating");
       $rate_type_score=300;
		break;
case ($rate_type=="Variable");
       $rate_type_score=200;
		break;
}
$w_rate_type_score=$rate_type_score*0.02;



//---------------EDUCATION LEVEL SCORE-----------------------------------------------------------------------


switch($loan_maturity)
 {
case ($loan_maturity > 0 and $loan_maturity < 6);
    $loan_maturity_score =300;
    break;
case ($loan_maturity > 5 and $loan_maturity < 11);
    $loan_maturity_score =350;
    break;
case ($loan_maturity > 10 and $loan_maturity < 16);
    $loan_maturity_score =400;
    break;
case ($loan_maturity > 15 and $loan_maturity < 20);
    $loan_maturity_score =400;
    break;
case ($loan_maturity > 19 and $loan_maturity < 25);
    $loan_maturity_score =350; 
    break;
case ($loan_maturity > 24 and $loan_maturity < 30);
    $loan_maturity_score =300;
    break;
case ($loan_maturity=30);
    $loan_maturity_score =250;
    break;
}
$w_loan_maturity_score=$loan_maturity_score * 0.01;
**/
//ADD THE TOTAL---------------------



$total_personal_data  =
						$marital_status_score     + 
						$age_score                + 
						$no_of_children_score     + 
						$dependants_at_home_score + 
						$education_level_score    + 
						$professional_score       + 
						$employment_score         + 
						$years_at_job_score       + 
						$revenues_score           + 
						$location_score;

$w_total_personal_data=
						$w_marital_status_score        + 
						$w_age_score                   + 
						$w_no_of_children_score        + 
						$w_dependants_at_home_score    + 
						$w_education_level_score       + 
						$w_professional_score          + 
						$w_employment_score            + 
						$w_years_at_job_score          + 
						$w_revenues_score              + 
						$w_location_score;

$total_length          = 
						$relationship_score       + 
						$total_bbs_products_score + 
						$loan_arrears_score       + 
						$renegotiate_score;

$w_total_length        = 
						$w_relationship_score       + 
						$w_total_bbs_products_score + 
						$w_loan_arrears_score       + 
						$w_renegotiate_score;

$total_ICT             =            
						$paid_debts_score     + 
						$judgement_score      + 
						$default_data_score   + 
						$trace_alerts_score;  //+ 
						//$deduct_score;

$w_total_ICT           = 
						$w_paid_debts_score    + 
						$w_judgement_score     + 
						$w_default_data_score  + 
						$w_trace_alerts_score; //+ 
						//$w_deduct_score;


$total_personal        =
						$affordability_ratio_score + 
						$card_held_since_score     + 
						$loans_outstanding_score   + 
						$dsr_score; 
$w_total_personal      =
						$w_affordability_ratio_score + 
						$w_card_held_since_score     + 
						$w_loans_outstanding_score   + 
						$dsr_w_score;

$total_product         =
						$rate_type_score + 
						$loan_maturity_score;
$w_total_product       =
						$w_rate_type_score + 
						$w_loan_maturity_score;

$score                 =
						$total_product       + 
						$total_ICT           +
						$total_personal      +  
						$total_length        + 
						$total_personal_data;

$w_score               =
						$w_total_product        + 
						$w_total_ICT            +
						$w_total_personal       + 
						$w_total_length         + 
						$w_total_personal_data;

?>