 <HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>
<script type="text/javascript" language="JavaScript1.2" src="file:///Z|/Desktop/BBS_website/stmenu.js"></script>
<script language="JavaScript">


</script>
<link href="btu.css" rel="stylesheet" type="text/css">
</HEAD>
<BODY BGCOLOR=#FFFFFF >
<p><font class="s_text"> 
  <?php
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="sour"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 

$loan_amount=NULL;
//echo 'ttmm'; echo $loan_amount;=NULL;
$property_type=NULL;
$open_market_value=NULL;
$loan_type=NULL;
$loan_maturity=NULL;
$rate_type=NULL;
$irate=NULL;
$insurance_replacement=NULL;
$insurance_premium=NULL;
$loan_installment=NULL;
$loanandinsurance=NULL;
$customer_type=NULL;
$rev4afford=NULL;
$name=NULL;
$surname=NULL;
$spouse_name=NULL;
$spouse_surname=NULL;
$address=NULL;
$street_add=NULL;
$town=NULL;
$country=NULL;
$permanent_residence=NULL;
$location=NULL;
$day=NULL;
$month=NULL;
$year=NULL;
$day2=NULL;
$month2=NULL;
$year2=NULL;
$day22=NULL;
$month22=NULL;
$year22=NULL;
$day23=NULL;
$month23=NULL;
$year23=NULL;
$nationality=NULL;
$marital_status=NULL;
$marital_contract_type=NULL;
$spouse_borrowing_realtionship=NULL;
$no_of_children=NULL;
//$to18=NULL;
//$to26=NULL;
//$to12=NULL;
$aunts_uncles_cousins=NULL;
$other=NULL;
$years_at_address=NULL;
$rent=NULL;
$education=NULL;
$professional=NULL;
$employment=NULL;
$years_at_job=NULL;
$revenues=NULL;
$annual_sal=NULL;
$fixed_perm_allowances=NULL;
$total_rev_for_afford=NULL;
$hp_annual_sal=NULL;
$hp_fixed_perm_allowances=NULL;
$hp_total_rev_for_afford=NULL;
$total_allowable_household_revenues=NULL;
$mainbank=NULL;
$secondbank=NULL;
$thirdbank=NULL;
$Savings=NULL;
$Deposit=NULL;
$Share=NULL;
$ST=NULL;
$Mortgages=NULL;
$why_renegotiate=NULL;
$itc_ref=NULL;
$itc_sub_no=NULL;
$paid_debts=NULL;
$judgement=NULL;
//echo $judgement; echo "thato";
$default_data=NULL;
$trace_alerts=NULL;
$number_of_dependants=NULL;
$dependants_at_home=NULL;
$relationship=NULL;
//echo 'týyyyyyyyy';=NULL;
//echo $relationship;=NULL;
$total_bbs_products=NULL;
$renegotiate=NULL;
$loan_arrears=NULL;
$cards_held=NULL;
$card_held_since=NULL;
$loans_outstanding=NULL;
$affordability_ratio=NULL;
$blacklisted=NULL;
$fraud_alert=NULL;
$ltv_policy=NULL;
$affordability_policy=NULL;
$ltv=NULL;
$w_education_level_score=NULL;
 $age_score =NULL;
 $card_held_since_score =NULL;
 $default_data_score =NULL;
 $dependants_at_home_score =NULL;
 $education_level_score =NULL;
 $employment_score =NULL;
 $judgement_score =NULL;
 $loan_arrears_score =NULL;
 $loan_maturity_score=NULL;
 $loans_outstanding_score =NULL;
 $location_score=NULL;
 $no_of_children_score =NULL;
 $paid_debts_score =NULL;
 $professional_score =NULL;
 $relationship_score =NULL;
 $renegotiate_score=NULL;
 $revenues_score =NULL;
 $total_bbs_products_score =NULL;
 $total_ICT =NULL;
 $total_length =NULL;
 $total_personal =NULL;
 $total_personal=NULL;
 $trace_alerts_score=NULL;
 $w_age_score =NULL;
 $w_card_held_since_score =NULL;
 $w_default_data_score =NULL;
 $w_dependants_at_home_score =NULL;
 $w_education_level_score =NULL;
 $w_employment_score =NULL;
 $w_judgement_score =NULL;
 $w_loan_arrears_score =NULL;
 $w_loan_maturity_score=NULL;
 $w_loans_outstanding_score=NULL;
 $w_location_score=NULL;
 $w_no_of_children_score =NULL;
 $w_paid_debts_score =NULL;
 $w_professional_score =NULL;
 $w_relationship_score =NULL;
 $w_renegotiate_score=NULL;
 $w_revenues_score =NULL;
 $w_total_bbs_products_score =NULL;
 $w_total_ICT =NULL;
 $w_total_length =NULL;
 $w_total_personal =NULL;
 $w_total_personal=NULL;
 $w_trace_alerts_score=NULL;
 $w_years_at_job_score =NULL;
 $years_at_job_score =NULL;
$affordability_ratio_score =NULL;
$marital_status_score =NULL;
$rate_type_score =NULL;
$score=NULL;
$total_ICT=NULL;
$total_length=NULL;
$total_personal=NULL;
$total_personal=NULL;
$total_product=NULL;
$total_product =NULL;
$w_affordability_ratio_score =NULL;
$w_marital_status_score =NULL;
$w_rate_type_score =NULL;
$w_score=NULL;
$w_total_ICT=NULL;
$w_total_length=NULL;
$w_total_personal=NULL;
$w_total_personal=NULL;
$w_total_product=NULL;
$w_total_product =NULL;

$cif =NULL;




$con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysql_select_db("credit", $con);

$result = mysql_query("SELECT * FROM cust_information_test");

while($row = mysql_fetch_array($result))
  {
  //echo $row['loan_amount_requested'] . " " . $row['loan_amount_requested'];
  //echo "<br />";


$cif=$row['CIF'];
echo $cif;
$loan_amount=$row['loan_amount_requested'];
//echo 'pppp'; echo $loan_amount;echo 'pppp';
//echo 'ttmm'; echo $loan_amount;
$property_type=$row['property_type'];
$open_market_value=$row['open_market_value'];
$loan_type=$row['loan_type'];
$loan_maturity=$row['loan_maturity_requested'];
$rate_type=$row['rate_type_requested'];
$irate=$row['current_interest_rate'];
$insurance_replacement=$row['insurance_replacement'];
$insurance_premium=$_POST['insurance_premium'];
$loan_installment=$_POST['loan_installment'];
$loanandinsurance=$row['estimated_installment_insurance'];;
//$loanandinsurance=$_POST['loanandinsurance'];
$customer_type=$row['cutomer_type'];
$rev4afford=$row['total_reveneues_affordability'];
$name=$row['name'];
$surname=$_POST['surname'];
$spouse_name=$_POST['spouse_name'];
$spouse_surname=$_POST['spouse_surname'];
$address=$_POST['address'];
$street_add=$_POST['street_add'];
$newage=$row['age'];
$town=$_POST['town'];
$country=$_POST['country'];
$permanent_residence=$_POST['permanent_country_of_residence'];
$location=$row['location_of_acquired_real_estate'];
$day=$row['day'];
$month=$row['month'];
$year=$row['year'];

$todayv = getdate();
$myyear=$todayv[year];
$mymonth=$todayv[month];
if ($mymonth=='January'){
$mymonth=1;
}
if ($mymonth=='February'){
$mymonth=2;
}
if ($mymonth=='March'){
$mymonth=3;
}
if ($mymonth=='April'){
$mymonth=4;
}
if ($mymonth=='May'){
$mymonth=5;
}
if ($mymonth=='June'){
$mymonth=6;
}
if ($mymonth=='July'){
$mymonth=7;
}
if ($mymonth=='August'){
$mymonth=8;
}
if ($mymonth=='September'){
$mymonth=9;
}
if ($mymonth=='October'){
$mymonth=10;
}
if ($mymonth=='November'){
$mymonth=11;
}
if ($mymonth=='December'){
$mymonth=12;
}
//echo $myyear;
$age=$myyear - $year;
//$mymonth=8;
//echo $mymonth; echo "<br>"; echo $month; echo "<br>";
//echo $myyear; echo "<br>"; echo $year; echo "<br>";

//$x=$mymonth + $month;
//echo $x;
if($mymonth < $month)
//echo "am in if";
$age=$age-1;


//echo $age;
$day2=$row['day2'];
$month2=$row['month2'];
$year2=$row['year2'];
$day22=$row['day22'];
$month22=$row['month22'];
$year22=$row['year22'];
$day23=$row['day23'];
$month23=$row['month23'];
$year23=$row['year23'];
$nationality=$row['nationality'];
$marital_status=$row['marital_status'];
$marital_contract_type=$row['marital_contract_type'];
$spouse_borrowing_realtionship=$row['spouse_borrowing_relationship'];
$no_of_children=$row['total_number_of_children'];
//$to18=$row['to18'];
//$to26=$row['to26'];
//$to12=$row['to12'];
$aunts_uncles_cousins=$row['aunts_uncles_cousins'];
$other=$row['other'];
$years_at_address=$row['years_at_address'];
$rent=$row['rent'];
$education=$row['borrower_education'];
$professional=$row['professional_category'];
$employment=$row['employment_contract'];
$years_at_job=$row['number_of_years_at_employer'];
//echo $years_at_job;
$revenues=$row['household_proffessional_revenue'];
$annual_sal=$row['annual_sal'];
$fixed_perm_allowances=$row['fixed_perm_allowances'];
$total_rev_for_afford=$row['total_rev_for_afford'];
$hp_annual_sal=$row['hp_annual_sal'];
$hp_fixed_perm_allowances=$row['hp_fixed_perm_allowances'];
$hp_total_rev_for_afford=$row['hp_total_rev_for_afford'];
$total_allowable_household_revenues=$row['total_allowable_household_revenues'];
$mainbank=$row['mainbank'];
$secondbank=$row['secondbank'];
$thirdbank=$row['thirdbank'];
$Savings=$row['Savings'];
$Deposit=$row['Deposit'];
$Share=$row['Share'];
$ST=$row['ST'];
$Mortgages=$row['Mortgages'];
$why_renegotiate=$row['why_renegotiate'];
$itc_ref=$row['itc_ref'];
$itc_sub_no=$row['itc_sub_no'];
$paid_debts=$row['paid_debt'];
$judgement=$row['judgment'];
$default_data=$row['default_data'];
$trace_alerts=$row['trace_alerts'];
$number_of_dependants=$row['total_dependants'];
$dependants_at_home=$row['total_dependants'];
$relationship=$row['age_of_relationship'];
//echo 'týyyyyyyyy';
//echo $relationship;
$total_bbs_products=$row['total_bbs_products'];
$renegotiate=$row['renegotiated'];
//echo 'týyyyyyyyy'; echo $renegotiate;
$loan_arrears=$row['bbs_arreas_12months'];
$card_held=$row['no_of_credit_card'];
//echo 'thato'; echo $cards_held;
$card_held_since=$row['card_held_since'];
$loans_outstanding=$row['number_of_loans_outstanding'];
$affordability_ratio=$row['affordability_ratio'];
$blacklisted=$row['blacklist_flag'];
$fraud_alert=$row['fraud_alerts'];
$ltv_policy=$row['ltv_policy'];
$affordability_policy=$row['affordability_policy'];

$v=($loan_amount/$open_market_value)*100;
//echo $v;
//}
if ($v > 90)
$ltvcomment="REJECT";
else
$ltvcomment="OK";


$v2=number_format($v, 2, '.', '');
if ($affordability_ratio > $affordability_policy)
$affordabilitycomment="REJECT";
else
$affordabilitycomment="OK";


if ($blacklisted=="True")
$blacklistedcomment="REJECT";
else 
$blacklistedcomment="OK";


if ($fraud_alert =="True")
$fraud_alert_comment="REJECT";
else
$fraud_alert_comment="OK";


if ($customer_type=="Legal Entity")
$customer_type_comment="REJECT";
else
$customer_type_comment="OK";

if ($nationality =="Foreigner")
$nationality_comment="REJECT";
else
$nationality_comment="OK";

 //echo 'kk';echo $dependants_at_home; echo 'kk';

?>
  </font></p>
<font class="s_text">
<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" ><tr>
  <td colspan="6" class="s_text"> <table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr> 
        <td width="1286"><table width="770" height="165">
            <td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
            <td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
          </table></td>
      </tr>
      <tr> 
        <td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT 
          REPORT</font></td>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <tr> </tr>
      <form ACTION="report.php" method="post" name="f1">
        <table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"   class="s_text">
          <p></p>
          <tr > 
            <td colspan="4" align="center" class="ntext">GENERAL INITIAL DATA</td>
          </tr>
          <tr> 
            <td class="ntext">BORROWER CIF</td>
            <td colspan="3" class="ntext" ><?php echo $cif; echo ' '; echo $surname;?></td>
          </tr>
          <tr> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td class="s_text">&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
		  <tr> 
            <td class="ntext">Loan Amount: </td>
            <td><?php echo $loan_amount;?></td>
            <td class="s_text">&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
		  
		   <tr> 
            <td class="ntext">Open Market Value </td>
            <td><?php echo $open_market_value;?></td>
            <td class="s_text">&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
		  <tr> 
            <td class="ntext">Cust Salary </td>
            <td><?php echo $loanandinsurance;?></td>
            <td class="s_text">&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
		   <tr> 
            <td class="ntext"></td>
            <td></td>
            <td class="s_text">&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
          <tr> 
            <td>Customer Type: Legal / Physical</td>
            <td >Reject if Legal </td>
            <td><?php echo $customer_type;?></td>
            <?php if ($customer_type=="Legal Entity")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Nationality: Boatswana/ Foreign</td>
            <td >Reject if Foreign</td>
            <td><?php echo $nationality;?></td>
            <?php if ($nationality=="Foreigner")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Permanent Residency: Botswana/Other</td>
            <td>&nbsp;</td>
            <td><?php echo $permanent_residence; ?></td>
            <td bgcolor="#32C079"><?php echo "OK"; ?></td>
          </tr>
          <tr> 
            <td>Blacklist Flag</td>
            <td>YES / NO</td>
            <td><?php echo $blacklisted; ?></td>
            <?php if ($blacklisted=="Yes")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Automatic Fraud Alert</td>
            <td>YES / NO</td>
            <td><?php echo $fraud_alert; ?></td>
            <?php if ($fraud_alert=="Yes")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Loan to Value Policy</td>
            <td>Policy</td>
            <td><?php echo $v2; ?></td>
            <?php if ($ltvcomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Affordability Policy</td>
            <td>Policy</td>
            <td><?php echo $affordability_ratio; ?></td>
            <?php if ($affordabilitycomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
        </table></br></br></br>
        <table width="95%"  border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"   class="s_text" >
          <?php  
		  $paid_debts_original=$paid_debts;
if ($paid_debts >= $default_data)
$paid_debts=1;
else
$paid_debts=0;

		   
			   
//-----------------AGE GROUP OF BORROWER-------------------------------------------------------------

//echo "age "; echo $age;
	switch ($newage) {
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
//echo $marital_status_score;		   
//---------------MARITAL STATUS----------------------------------------------------------------------			
			
			    
switch ($marital_status) {
    case "SINGLE":
        $marital_status_score=300;
		break;
    case "MARRIED":
        $marital_status_score=400;
        break;
    case "DIVORCED":
       $marital_status_score=500;
        break;
	case "WIDOWED":
        $marital_status_score=300;
        break;
	case "OTHER":
       $marital_status_score=200;
        break;
}
$w_marital_status_score=$marital_status_score*0.03;
//echo $marital_status_score;
//echo '<p>';
//echo $w_marital_status_score;
//echo "test marital";
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
case 'UNIVERSITY SCHOOL':
       $education_level_score=350;
		break;
	case 'TECHNICAL SCHOOL':
       $education_level_score=300;
		break;
case 'SECONDARY SCHOOL':
      $education_level_score=250;
		break;

case 'PRIMARY SCHOOL':
       $education_level_score=200;
		break;
case 'NONE':
       $education_level_score=100;
		break;
}
$w_education_level_score=$education_level_score*0.04;

//----------------PROFESSIONAL CATEGORY SCORE----------------------------------------------------------


switch($professional){
case 'CIVIL SERVANT':
       $professional_score=500;
		break;
	case 'EMPLOYEE':
       $professional_score=400;
		break;
case 'SELF EMPLOYED':
       $professional_score=200;
		break;

case 'OTHER':
       $professional_score=200;
		break;
}
$w_professional_score=$professional_score*0.08;

//------------------EMPLOYMENT CONTRACT TYPE SCORE---------------------------------------------------

switch($employment){
case 'PERMANENT':
       $employment_score=500;
		break;
	case 'CONTRACTUAL':
       $employment_score=300;
		break;
case 'OTHER':
       $employment_score=200;
		break;
}
$w_employment_score=$employment_score*0.08;

//-------------------------------YEARS WITH EMPLOYER----------------------------------------------

switch($years_at_job){
case "0 TO 1":
       $years_at_job_score=200;
		break;
	case "1 TO 5":
      $years_at_job_score=400;
		break;
case "5 TO 20":
       $years_at_job_score=300;
		break;
case "20 AND more":
       $years_at_job_score=300;
		break;
}
$w_years_at_job_score=$years_at_job_score*0.02;

//-----------------------SINGLE OR DOUBLE SALARY SCORE----------------------------------------------

switch($revenues){
case 'SINGLE':
       $revenues_score=250;
		break;
case 'DOUBLE':
       $revenues_score=500;
		break;
}
$w_revenues_score=$revenues_score*0.03;

//-----------------------AREA OF RESIDENCE SCORE---------------------------------------------------

switch($location){
case ($location=='MAJOR URBAN');
       $location_score=400;
		break;
	case ($location=='MINOT URBAN');
       $location_score=300;
		break;
case ($location=='RURAL');
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
case ($relationship=='SINCE LESS THAN 1 YEAR');
$relationship_score=200;
break;
case ($relationship=='SINCE 1 TO 5 YEARS');
$relationship_score=400;
break;
case ($relationship=='MORE THAN 5 YEARS');
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
case na:
       $loan_arrears_score=100;
	     $loan_arrears_score_comment='OK';
		break;
}
 $w_loan_arrears_score= $loan_arrears_score*0.10; 
 
 
 //---------------RENEGOTIATIONS-----------------------------------------------------------------------
 switch($renegotiate){
  case 0:
       $renegotiate_score=400;
	    $renegotiate_comment='OK';
		break;
case 1:
       $renegotiate_score=100;
	    $renegotiate_comment='OK';
		break;
case 2:
       $renegotiate_score=0;
	   $renegotiate_comment='REJECT';
		break;
case na:
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

switch(card_held){
case 0:
       $card_held_score=100;
		break;
case 1:
       $card_held__score=200;
		break;
case 2:
       $card_held_score=250;
		break;
case 3:
       $card_held_score=100;
	   
		break;
case 4:
       $card_held_score=0;
		break;
}
$w_card_held_score=$card_held_score*0.02;




//---------------OUTSTANDING LOANS-----------------------------------------------------------------------

switch(loans_outstanding){
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
$w_affordability_ratio_score=$affordability_ratio_score*0.07;

if ($affordability_ratio > 40)

$affordability_ratio_comment="REJECT";
else
$affordability_ratio_comment="OK";


//---------------INTEREST RATE TYPE-----------------------------------------------------------------------
switch($rate_type)
{
case ($rate_type=="FIXED");
       $rate_type_score=400;
		break;
case ($rate_type=="FLOATING");
       $rate_type_score=300;
		break;
case ($rate_type=="VATIABLE");
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

//ADD THE TOTAL---------------------




$total_personal_data=$marital_status_score + $age_score + $no_of_children_score + $dependants_at_home_score + $education_level_score 
+ $professional_score + $employment_score + $years_at_job_score + $revenues_score + $location_score;

$w_total_personal_data=$w_marital_status_score + $w_age_score + $w_no_of_children_score + $w_dependants_at_home_score + $w_education_level_score 
+ $w_professional_score + $w_employment_score + $w_years_at_job_score + $w_revenues_score + $w_location_score;

$total_length= $relationship_score + $total_bbs_products_score + $loan_arrears_score + $renegotiate_score;

$w_total_length= $w_relationship_score + $w_total_bbs_products_score + $w_loan_arrears_score + $w_renegotiate_score;

$total_ICT= $paid_debts_score + $judgement_score + $default_data_score + $trace_alerts_score;

$w_total_ICT= $w_paid_debts_score + $w_judgement_score + $w_default_data_score + $w_trace_alerts_score;


$total_personal=$affordability_ratio_score + $card_held_score + $loans_outstanding_score; 
$w_total_personal=$w_affordability_ratio_score + $w_card_held_score + $w_loans_outstanding_score;

$total_product=$rate_type_score + $loan_maturity_score;
$w_total_product=$w_rate_type_score + $w_loan_maturity_score;

$tlamelo=$total_product + $total_personal + $total_length + $total_personal_data;

$tshiamo=$w_total_product + $w_total_personal +  $w_total_length + $w_total_personal_data;
//echo $total_personal;


?>
          <tr class="bdr2"  align="center"  > 
            <td class="ntext">SCOIRNG ATTRIBUTES</td>
            <td class="ntext">WEIGHT</td>
			 <td class="ntext">VALUE</td>
            <td class="ntext">SCORE</td>
            <td class="ntext">WEIGHTED SCORE</td>
            <td class="ntext">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td class="ntext">MIN</td>
            <td class="ntext">MAX</td>
          </tr>
          <tr align="center"> 
            <td>Marital Status</td>
            <td>3</td>
			<td><?php echo $marital_status;?></td>
            <td><?php echo $marital_status_score;?></td>
            <td><?php echo $w_marital_status_score;?></td>
            <td>&nbsp;</td>
            <td>6</td>
            <td>15</td>
          </tr>
          <tr align="center"> 
            <td>Age Group of Borrower</td>
            <td>2</td>
			<td><?php echo $newage;?></td>
            <td><?php echo $age_score;?></td>
            <td><?php echo $w_age_score;?></td>
            <td>&nbsp;</td>
            <td>2</td>
            <td>8</td>
          </tr>
          <tr align="center"> 
            <td>Number of Chidren</td>
            <td>1</td>
			<td><?php echo $no_of_children; ?></td>
            <td><?php echo $no_of_children_score; ?></td>
            <td><?php echo $w_no_of_children_score; ?></td>
            <td>&nbsp;</td>
            <td>1</td>
            <td>4</td>
          </tr>
          <tr align="center"> 
            <td>Number of household members</td>
            <td>1</td>
			<td><?php echo $dependants_at_home; ?></td>
            <td><?php echo $dependants_at_home_score; ?></td>
            <td><?php echo  $w_dependants_at_home_score; ?></td>
            <td>&nbsp;</td>
            <td>1</td>
            <td>3</td>
          </tr>
          <tr align="center"> 
            <td>Education level</td>
            <td>4</td>
			<td><?php echo $education; ?></td>
            <td><?php echo $education_level_score; ?></td>
            <td><?php echo $w_education_level_score; ?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>14</td>
          </tr>
          <tr align="center"> 
            <td>Professional category</td>
            <td>8</td>
			<td><?php echo $professional; ?></td>
            <td><?php echo $professional_score; ?></td>
            <td><?php echo $w_professional_score; ?></td>
            <td>&nbsp;</td>
            <td>16</td>
            <td>40</td>
          </tr>
          <tr align="center"> 
            <td>Employment Contract type</td>
            <td>8</td>
			<td><?php echo $employment;?></td>
            <td><?php echo $employment_score;?></td>
            <td><?php echo $w_employment_score;?></td>
            <td>&nbsp;</td>
            <td>16</td>
            <td>40</td>
          </tr>
          <tr align="center"> 
            <td>Years with employer(0-1; 1-5...)</td>
            <td>2</td>
			<td><?php echo $years_at_job;?></td>
            <td><?php echo $years_at_job_score;?></td>
            <td><?php echo $w_years_at_job_score;?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>8</td>
          </tr>
          <tr align="center"> 
            <td>Single or double salary</td>
            <td>3</td>
			<td><?php echo $revenues;?></td>
            <td><?php echo $revenues_score;?></td>
            <td><?php echo $w_revenues_score;?></td>
            <td>&nbsp;</td>
            <td>7.5</td>
            <td>15</td>
          </tr>
          <tr align="center"> 
            <td>Area Of Residence</td>
            <td>2</td>
			<td><?php echo $location;?></td>
            <td><?php echo $location_score;?></td>
            <td><?php echo $w_location_score;?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>8</td>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>PERSONAL DATA</td>
            <td>34</td>
			 <td></td>
            <td><?php echo $total_personal_data;?></td>
            <td><?php echo $w_total_personal_data;?></td>
            <td>&nbsp;</td>
            <td>61.5</td>
            <td>155</td>
          </tr>
          <tr align="center" bgcolor="#E1F3FB"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
			<td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
          <tr align="center"> 
            <td>Years of banking relationship with BBS </td>
            <td>3</td>
			 <td><?php echo $relationship; ?></td>
            <td><?php echo $relationship_score; ?></td>
            <td><?php echo $w_relationship_score; ?></td>
            <td>&nbsp;</td>
            <td>3</td>
            <td>15</td>
          </tr>
          <tr align="center"> 
            <td># of banking products with BBS excl.new request</td>
            <td>2</td>
			<td><?php echo $total_bbs_products;?></td>
            <td><?php echo $total_bbs_products_score;?></td>
            <td><?php echo $w_total_bbs_products_score;?></td>
            <td>&nbsp;</td>
            <td>2</td>
            <td>10</td>
          </tr>
          <tr align="center"> 
            <td># of arreas incidence over the last 12 months</td>
            <td>10</td>
			<td><?php echo $loan_arrears;?></td>
            <td><?php echo $loan_arrears_score;?></td>
            <td><?php echo $w_loan_arrears_score;?></td>
            <?php if ($loan_arrears_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>40</td>
          </tr>
          <tr align="center"> 
            <td>Loan overdue & renegotiated(last 2 years)</td>
            <td>15</td>
			<td><?php echo $renegotiate;?></td>
            <td><?php echo $renegotiate_score;?></td>
            <td><?php echo $w_renegotiate_score;?></td>
            <?php if ($renegotiate_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>60</td>
          </tr>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>Length & depth of banking BBS relationship</td>
            <td>30</td>
			<td></td>
            <td><?php echo $total_length;?></td>
            <td><?php echo $w_total_length;?></td>
            <td>&nbsp;</td>
            <td>5</td>
            <td>125</td>
          </tr>
          <tr align="center" bgcolor="#E1F3FB"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          <tr align="center"> 
            <td>paid debts vs.Defaults(if 1=more paid than defaults)</td>
            <td>2</td>
			<td><?php echo $paid_debts_original;?></td>
            <td><?php echo $paid_debts_score;?></td>
            <td><?php echo $w_paid_debts_score;?></td>
            <?php if ($paid_debts_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>5</td>
          </tr>
          <tr align="center"> 
            <td>Judgements</td>
            <td>9</td>
			   <td><?php echo $judgement;?></td>
            <td><?php echo $judgement_score;?></td>
            <td><?php echo $w_judgement_score;?></td>
            <?php if ($judgement_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>45</td>
          </tr>
          <tr align="center"> 
            <td>Defaults</td>
            <td>9</td>
			<td><?php echo $default_data;?></td>
            <td><?php echo $default_data_score;?></td>
            <td><?php echo $w_default_data_score;?></td>
            <?php if ($default_data_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>45</td>
          </tr>
          <tr align="center"> 
            <td>Traces</td>
            <td>2</td>
			 <td><?php echo $trace_alerts;?></td>
            <td><?php echo $trace_alerts_score;?></td>
            <td><?php echo $w_trace_alerts_score;?></td>
            <?php if ($trace_alerts_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>10</td>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>ITC REPORT SUB-RECORD</td>
            <td>22</td>
			<td></td>
            <td><?php echo $total_ICT;?></td>
            <td><?php echo $w_total_ICT;?></td>
            <td>&nbsp;</td>
            <td>0</td>
            <td>105</td>
          <tr align="center" bgcolor="#E1F3FB"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
			<td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          <tr align="center"> 
            <td>Number of credit card personal held</td>
            <td>2</td>
			<td><?php echo $card_held;?></td>
            <td><?php echo $card_held_score;?></td>
            <td><?php echo $w_card_held_score;?></td>
            <td>&nbsp;</td>
            <td>0</td>
            <td>5</td>
          </tr>
          <tr align="center"> 
            <td>Number of loans presently outstanding</td>
            <td>2</td>
			 <td><?php echo $loans_outstanding;?></td>
            <td><?php echo $loans_outstanding_score;?></td>
            <td><?php echo $w_loans_outstanding_score;?></td>
            <td>&nbsp;</td>
            <td>0</td>
            <td>5</td>
          </tr>
          <tr align="center"> 
            <td>Affordability ratio(Inst/Salary; from 0 to y%,etc) </td>
            <td>7</td>
			 <td><?php echo $affordability_ratio;?></td>
            <td><?php echo $affordability_ratio_score;?></td>
            <td><?php echo $w_affordability_ratio_score;?></td>
            <?php if ($affordability_ratio_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
            <td>0</td>
            <td>35</td>
          </tr>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>Persona Financial Data</td>
            <td>11</td>
			    <td></td>
            <td><?php echo $total_personal;?></td>
            <td><?php echo $w_total_personal;?></td>
            <td>&nbsp;</td>
            <td>0</td>
            <td>45</td>
          </tr>
          <tr align="center" bgcolor="#E1F3FB"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
			<td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          <tr align="center"> 
            <td>Mortgage Loan Type</td>
            <td>2</td>
			 <td><?php echo $rate_type;?></td>
            <td><?php echo $rate_type_score;?></td>
            <td><?php echo $w_rate_type_score;?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>8</td>
          </tr>
          <tr align="center"> 
            <td>Maturity of the loan in years</td>
            <td>1</td>
			 <td><?php echo $loan_maturity;?></td>
            <td><?php echo $loan_maturity_score;?></td>
            <td><?php echo $w_loan_maturity_score;?></td>
            <td>&nbsp;</td>
            <td>2.5</td>
            <td>4</td>
          </tr>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>Product Characteristics</td>
            <td>3</td>
			<td></td>
            <td><?php echo $total_product;?></td>
            <td><?php echo $w_total_product;?></td>
            <td>&nbsp;</td>
            <td>7</td>
            <td>12</td>
          </tr>
          <tr align="center" bgcolor="#FFFFFF"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
   <td>&nbsp;</td>
          <tr align="center"> 
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
			   <td>&nbsp;</td>
          </tr>
		  
          <tr align="center" bgcolor="#CCEBF7" class="ntext"> 
            <td>TOTAL FINAL RESULTS</td>
            <td>100%</td>
			 <td><?php echo "SCORE";?></td>
            <td><?php echo $tlamelo;?></td>
            <td><?php echo $tshiamo;?></td>
            <td>&nbsp;</td>
            <td>73</td>
            <td>337</td>
          </tr>
        </table>
   <?php 
   $score=$tshiamo;
$totalscore=$tlamelo;
//echo $score;echo 'xxx';
if ($score > 303 and $score <338)
{
$rate=1;
$pd=.03;
}

if ($score > 270 and $score <305)
{
$rate=2;
$pd=.035;
}
if ($score > 237 and $score <272)
{
$rate=3;
$pd=.045;
}
if ($score > 204 and $score < 239)
{
$rate=1;
$pd=.03;
}
if ($score > 204 and $score <238)
{
$rate=4;
$pd=.05;
}
if ($score > 171 and $score <206)
{
$rate=5;
$pd=.07;
}
if ($score > 138 and $score <173)
{
$rate=6;
$pd=.12;
}
if ($score > 105 and $score <140)
{
$rate=7;
$pd=.2;
}

if ($score > 72 and $score <107)
{
$rate=8;
$pd=.27;
}

/*if ($score > 303 and $score < 338)
{
$rate=9;
$pd=.5;
}
if ($score > 303 and $score <338)
{
$rate=10;
$pd=1;
}*/
//echo "score ";echo $score;
if ($score < 72){
$rate="unrated";
$pd="above acceptable level";
}
?>
</br>
<table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">
<tr>
            <td><strong>Rating</strong></td>
            <td><strong>Score</strong></td>
            <td><strong>PD</strong></td>
</tr>
<td><?php echo $rate; ?></td>
<td><?php echo $score; ?></td>
<td><?php echo $pd*100; echo "%"; $pdefault=$pd*100;?></td>
</tr></table>
   
 <?php  $haircut=$open_market_value*.25;
 $install=$loanandinsurance*3;
   //general cost
$gcost=$loan_amount*.05;
   ?>     
		<table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">
  <tr  bgcolor="#E1F3FB">
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td class="ntext" align="center">BWP</td>
    <td class="ntext" align="center">%</td>
  </tr>
  <tr bgcolor="#E1F3FB">
    <td class="ntext"  >Estimated PD</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td >Loan Amount</td>
    <td>&nbsp;</td>
    <td><?php echo $loan_amount;?></td>
    <td>100%</td>
  </tr>
  <tr>
    <td >3 Month Instalments</td>
    <td>3</td>
    <td><?php echo $install;?></td>
	<?php $installpercent=$install/$loan_amount*100;  $instalp=number_format($installpercent, 2, '.', '');?>
    <td><?php echo $instalp; echo '%';?></td>
  </tr>
  <tr>
    <td >General Costs estimation</td>
    <td>5%</td>
    <td><?php echo $gcost;?></td>
    <td>5%</td>
  </tr>
   <?php $a=$open_market_value/$loan_amount*100; ?>
  <?php $b=$haircut/$loan_amount*100; ?>
  <?php $ead=$install + $gcost + $loan_amount ;?>
  <tr bgcolor="#E1F3FB">
    <td class="ntext"  >Esitimated EAD</td>
    <td>&nbsp;</td>
    <td class="ntext"><?php echo $ead ;?></td>
	<?php $eadperc=$ead/$loan_amount*100;  $eadp=number_format($eadperc, 2, '.', '');?>
    <td><?php echo $eadp; echo '%';?></td>
  </tr>
   <?php 
 $omv1= $open_market_value/$loan_amount*100;
  $omv=number_format($omv1, 2, '.', '');
  ?>
  <tr>
    <td >Estimated OMV</td>
    <td>&nbsp;</td>
    <td><?php echo $open_market_value;?></td>
    <td><?php echo $omv ?></td>
  </tr>
  <?php 
 $hc1= $haircut/$loan_amount*100;
  $hc=number_format($hc1, 2, '.', '');
  ?>
 
  <tr>
    <td >Haircut</td>
    <td>25%</td>
    <td><?php echo $haircut;?></td>
    <td><?php echo $hc; ?></td>
  </tr>
  
 <?php $lgd=$open_market_value-$haircut;?>
  <tr  bgcolor="#E1F3FB">
    <td class="ntext">Estimated LGD</td>
    <td>&nbsp;</td>
    <td class="ntext"><?php echo $lgd;?></td>
	<?php $hud= $a-$b;  $h2=number_format($hud, 2, '.', '');?>
    <td><?php echo $h2;  ?></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <?php
    $xx=$install + $gcost + $loan_amount;
  $yy=$open_market_value-$haircut;
//echo $xx; echo "<br>";
//echo $yy; echo "<br>";
//echo $pd; echo "<br>";
$el=($xx-$yy)*$pd;
$llp=($el/$loan_amount)*100;
$llpfinal=number_format($llp, 2, '.', '');
$el2=number_format($el, 2, '.', '');

?>
  
  
   <tr bgcolor="#E1F3FB">
    <td class="ntext">Expected Loss (to be LLp-ed)</td>
    <td>&nbsp;</td>
    <td><?php echo $el2;?></td>
    <td><?php echo $llpfinal;?></td>
  </tr>
  
  </table>
 
 
 <?php
 
$sql3="INSERT INTO cust_credit_rating_test(
 CIF,pd,
loan_amount,
loan_percentage,
installment,
installment_percentage,
cost_estimation,
cost_estimation_percentage,
estimated_EAD,
estimated_EAD_percentage,
estimated_OMV,
estimated_OMV_percentage,
haircut,
haircut_percentage,
estimated_LGD,
estimated_LGD_percentage,
Expected_loss,
expected_loss_percentage, ltv, affordabilty)
VALUES 
(
'$cif',
'$pdefault',
'$loan_amount',
100,
'$install',
'$instalp',
'$gcost',
5,
'$ead',
'$eadp',
'$open_market_value',
'$omv',
'$haircut', 
'$hc',
'$lgd',
'$h2',
'$el2',
'$llpfinal', '$v2', '$affordability_ratio'
)";


$sql2="INSERT INTO creditscore_test (cif, marital_status_score, w_marital_status_score,
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
score
) 
VALUES('$cif' ,'$marital_status_score' ,'$w_marital_status_score',
'$age_score',
'$w_age_score',
'$no_of_children_score',
'$w_no_of_children_score',
'$dependants_at_home',
'$w_dependants_at_home',
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
'$w_loan_arrears', 
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
'$score'
)";

if (!mysql_query($sql3,$con))
  {
  die('Error3Custcreditrating: ' . mysql_error());
  }
 echo "testing123"; 
if (!mysql_query($sql2,$con))
  {
  die('Error2creditscore: ' . mysql_error());
  }
  
  echo "</br>";
  
  
 }//end while loop?> 
<p></p>
    
    
    </table>
      </form>
    </table></td></tr>
</table></table></table>
</font> 
</BODY>
</HTML>
