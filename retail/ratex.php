
function dateDiff($dformat, $endDate, $beginDate)
{
$date_parts1=explode($dformat, $beginDate);
$date_parts2=explode($dformat, $endDate);
$start_date=gregoriantojd($date_parts1[0], $date_parts1[1], $date_parts1[2]);
$end_date=gregoriantojd($date_parts2[0], $date_parts2[1], $date_parts2[2]);
return $end_date – $start_date;
}

Now let us see how we use this function:

$date1="07/11/2003";
$date2="09/04/2004";

print "If we minus " . $date1 . " from " . $date2 . " we get " . dateDiff("/", $date2, $date1) . ".";


 <HTML>
 
<HEAD>

function dateDiff($dformat, $endDate, $beginDate)
{
$date_parts1=explode($dformat, $beginDate);
$date_parts2=explode($dformat, $endDate);
$start_date=gregoriantojd($date_parts1[0], $date_parts1[1], $date_parts1[2]);
$end_date=gregoriantojd($date_parts2[0], $date_parts2[1], $date_parts2[2]);
return $end_date – $start_date;
}

Now let us see how we use this function:

$date1="07/11/2003";
$date2="09/04/2004";

print "If we minus " . $date1 . " from " . $date2 . " we get " . dateDiff("/", $date2, $date1) . ".";


<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>SOUR PROJECT</TITLE>
<script type="text/javascript" language="JavaScript1.2" src="file:///Z|/Desktop/BBS_website/stmenu.js"></script>
<script language="JavaScript">




</script>
<link href="btu.css" rel="stylesheet" type="text/css">
</HEAD>
<BODY BGCOLOR=#FFFFFF >
<font class="s_text"> 
<?php
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="sour"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 

$loan_amount=$_POST['loan_amount'];
$property_type=$_POST['property_type'];
$open_market_value=$_POST['open_market_value'];
$loan_type=$_POST['loan_type'];
$loan_maturity=$_POST['loan_maturity'];
$rate_type=$_POST['rate_type'];
$irate=$_POST['irate'];
$insurance_replacement=$_POST['insurance_replacement'];
$insurance_premium=$_POST['insurance_premium'];
$loan_installment=$_POST['loan_installment'];
$loanandinsurance=$_POST['loanandinsurance'];
$customer_type=$_POST['customer_type'];
$rev4afford=$_POST['rev4afford'];
$name=$_POST['name'];
$surname=$_POST['surname'];
$spouse_name=$_POST['spouse_name'];
$spouse_surname=$_POST['spouse_surname'];
$address=$_POST['address'];
$street_add=$_POST['street_add'];
$town=$_POST['town'];
$country=$_POST['country'];
$permanent_residence=$_POST['permanent_residence'];
$location=$_POST['location'];
$day=$_POST['day'];
$month=$_POST['month'];
$year=$_POST['year'];

$dob="08/12/1979";
echo "If you were born on " . $dob . ", then today your age is approximately " . round(dateDiff("/", date("m/d/Y", time()), $dob)/365, 0) . " years.";

$day2=$_POST['day2'];
$month2=$_POST['month2'];
$year2=$_POST['year2'];
$day22=$_POST['day22'];
$month22=$_POST['month22'];
$year22=$_POST['year22'];
$day23=$_POST['day23'];
$month23=$_POST['month23'];
$year23=$_POST['year23'];
$nationality=$_POST['nationality'];
$marital_status=$_POST['marital_status'];
$marital_contract_type=$_POST['marital_contract_type'];
$spouse_borrowing_realtionship=$_POST['spouse_borrowing_relationship'];
$no_of_children=$_POST['no_of_children'];
//$13to18=$_POST['13to18'];
//$19to26=$_POST['19to26'];
//$0to12=$_POST['0to12'];
$aunts_uncles_cousins=$_POST['aunts_uncles_cousins'];
$other=$_POST['other'];
$years_at_address=$_POST['years_at_address'];
$rent=$_POST['rent'];
$education=$_POST['education'];
$professional=$_POST['Professional'];
$employment=$_POST['employment'];
$years_at_job=$_POST['years_at_job'];
$revenues=$_POST['revenues'];
$annual_sal=$_POST['annual_sal'];
$fixed_perm_allowances=$_POST['fixed_perm_allowances'];
$total_rev_for_afford=$_POST['total_rev_for_afford'];
$hp_annual_sal=$_POST['hp_annual_sal'];
$hp_fixed_perm_allowances=$_POST['hp_fixed_perm_allowances'];
$hp_total_rev_for_afford=$_POST['hp_total_rev_for_afford'];
$total_allowable_household_revenues=$_POST['total_allowable_household_revenues'];
$mainbank=$_POST['mainbank'];
$secondbank=$_POST['secondbank'];
$thirdbank=$_POST['thirdbank'];
$Savings=$_POST['Savings'];
$Deposit=$_POST['Deposit'];
$Share=$_POST['Share'];
$ST=$_POST['ST'];
$Mortgages=$_POST['Mortgages'];
$why_renegotiate=$_POST['why_renegotiate'];
$itc_ref=$_POST['itc_ref'];
$itc_sub_no=$_POST['itc_sub_no'];
$paid_debts=$_POST['paid_debts'];
$judgement=$_POST['judgement'];
$default_data=$_POST['default_data'];
$trace_alerts=$_POST['trace_alerts'];
$number_of_dependants=$_POST['number_of_dependants'];
$dependants_at_home=$_POST['dependants_at_home'];
$relationship=$_POST['relationship'];
//echo 'týyyyyyyyy';
//echo $relationship;
$total_bbs_products=$_POST['total_bbs_products'];
$renegotiate=$_POST['renegotiate'];
$loan_arrears=$_POST['loan_arrears'];
$cards_held=$_POST['cards_held'];
$card_held_since=$_POST['card_held_since'];
$loans_outstanding=$_POST['loans_outstanding'];
$affordability_ratio=$_POST['affordability_ratio'];
$blacklisted=$_POST['blacklisted'];
$fraud_alert=$_POST['fraud_alert'];
$ltv_policy=$_POST['ltv_policy'];
$affordability_policy=$_POST['affordability_policy'];
$ltv=$_POST['ltv'];

if ($ltv > $ltv_policy)
$ltvcomment="REJECT";
else
$ltvcomment="OK";



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

?>
<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" ><tr><td colspan="6" class="s_text">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" >
    <tr> 
      <td width="1286"><table width="770" height="165">
          <td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
          <td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
        </table></td>
    </tr>
    <tr> 
      <td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT REPORT</font></td>
    <tr> </tr><tr> </tr><tr> </tr><tr> </tr><tr> </tr><tr> </tr> <tr> </tr><tr> </tr><tr> </tr><tr> </tr><tr> </tr><tr> </tr>
 <table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"   class="s_text">
<p></p>
  <tr >
    <td colspan="4" align="center" class="ntext">GENERAL INITIAL DATA</td>
    
  </tr>
  <tr>
    <td class="ntext">BORROWER NAME</td>
    <td colspan="3" class="ntext" ><?php echo $name; echo ' '; echo $surname;?></td>
	
	  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
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
    <td><?php echo $ltv; ?></td>
   <?php if ($ltvcomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td>Affordability Policy</td>
    <td>Policy</td>
    <td><?php echo $affordability_ratio; ?></td>
  <?php if ($$affordabilitycomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
</table>
  </br>    </br>   </br> 
  
     
<table width="95%"  border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"   class="s_text" >
  
		
		
		
		
          <form ACTION="testing.php" method="post" name="f1">
            <?php  
			   
//---------------MARITAL STATUS----------------------------------------------------------------------			
			
			    
switch ($marital_status) {
    case "Single":
        $marital_status_score=300;
		break;
    case "Married":
        $marital_status_score=400;
        break;
    case "Divorced":
       $marital_status_score=300;
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

}
$w_dependants_at_home_score=  $dependants_at_home_score_score*0.01;
//---------------EDUCATION LEVEL SCORE-----------------------------------------------------------------------


switch($education){
case 'Professional Course':
       $education_level_score=350;
		break;
case 'PHD':
       $education_level_score=350;
		break;
case 'Masters Dgree':
       $education_level_score=350;
		break;
case 'Degree':
       $education_level_score=350;
		break;
case 'Diploma':
       $education_level_score=300;
		break;
case 'Certificate':
       $education_level_score=250;
		break;
case 'Secondary School':
      $education_level_score=200;
		break;
case 'Primary School':
       $education_level_score=150;
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
	case 'Contactual':
       $employment_score=300;
		break;
case 'Other':
       $employment_score=200;
		break;
}
$w_employment_score=$employment_score*0.08;

//-------------------------------YEARS WITH EMPLOYER----------------------------------------------

switch($years_at_job){
case ($years_at_job >0 and $years_at_job<=1 ):
       $years_at_job_score=500;
		break;
	case ($years_at_job >1 and $years_at_job<=5):
      $years_at_job_score=400;
		break;
case ($years_at_job >5 and $years_at_job<=20):
       $years_at_job_score=300;
		break;
case ($years_at_job >20):
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
case ($location=='Major Urban');
       $location_score=400;
		break;
	case ($location=='Minor Urban');
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
$w_relationship_score=$relationship_score*0.02;



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
}

$w_default_data_score=$default_data_score* 0.09;


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
}
$w_trace_alerts_score=$trace_alerts_score * 0.02;


//---------------CREDIT CARD HELD SINCE-----------------------------------------------------------------------

switch(card_held_since){
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
case ($affordability_ratio > 40 and $affordability_ratio < 51);
$affordability_ratio_score =100;
break;
case ($affordability_ratio > 50);
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
case ($loan_maturity > 15 and $loan_maturity < 21);
$loan_maturity_score =400;
break;
case ($loan_maturity > 20 and $loan_maturity < 26);
$loan_maturity_score =350;
break;
case ($loan_maturity > 25 and $loan_maturity < 31);
$loan_maturity_score =300;
break;
}
$w_loan_maturity_score=$loan_maturty_score * 0.01;


?>
<tr class="bdr2"  align="center"  >
    <td class="ntext">SCOIRNG ATTRIBUTES</td>
    <td class="ntext">WEIGHT</td>
    <td class="ntext">SCORE</td>
    <td class="ntext">WEIGHTED SCORE</td>
    <td class="ntext">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
    <td class="ntext">MIN</td>
    <td class="ntext">MAX</td>
  </tr>
  <tr align="center">
    <td>Marital Status</td>
    <td>3</td>
    <td><?php echo $marital_status_score;?></td>
    <td><?php echo $w_marital_status_score;?></td>
    <td>&nbsp;</td>
    <td>6</td>
    <td>15</td>

  </tr>
  <tr align="center">
    <td>&nbsp;</td>
    <td>2</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>2</td>
    <td>8</td>
  
  </tr>
  
  <tr align="center">
    <td>Number of Chidren</td>
    <td>1</td>
   
    <td><?php echo $no_of_children_score; ?></td>
    <td><?php echo $w_no_of_children_score; ?></td>
	 <td>&nbsp;</td>
    <td>1</td>
    <td>4</td>
  </tr>
  <tr align="center">
    <td>Number of household members</td>
    <td>1</td>
    <td><?php echo $dependants_at_home_score; ?></td>
    <td><?php echo  $w_dependants_at_home_score; ?></td>
    <td>&nbsp;</td>
    <td>1</td>
    <td>3</td>

  </tr>
  <tr align="center">
    <td>Education level</td>
    <td>4</td>
    <td><?php echo $education_level_score; ?></td>
    <td><?php echo $w_education_level_score; ?></td>
    <td>&nbsp;</td>
    <td>4</td>
    <td>14</td>
 
  </tr>
  <tr align="center">
    <td>Professional category</td>
    <td>8</td>
    <td><?php echo $professional_score; ?></td>
    <td><?php echo $w_professional_score; ?></td>
    <td>&nbsp;</td>
    <td>16</td>
    <td>40</td>

  </tr>
  <tr align="center">
    <td>Employment Contract type</td>
    <td>8</td>
    <td><?php echo $employment_score;?></td>
    <td><?php echo $w_employment_score;?></td>
    <td>&nbsp;</td>
    <td>16</td>
    <td>40</td>
  
  </tr>
  <tr align="center">
    <td>Years with employer(0-1; 1-5...)</td>
    <td>2</td>
    <td><?php echo $years_at_job_score;?></td>
    <td><?php echo $w_years_at_job_score;?></td>
    <td>&nbsp;</td>
    <td>4</td>
    <td>8</td>

  </tr>
  <tr align="center">
    <td>Single or double salary</td>
    <td>3</td>
    <td><?php echo $revenues_score;?></td>
    <td><?php echo $w_revenues_score;?></td>
    <td>&nbsp;</td>
    <td>7.5</td>
    <td>15</td>
    
  </tr>
  <tr align="center">
    <td>Area Of Residence</td>
    <td>2</td>
    <td><?php echo $location_score;?></td>
    <td><?php echo $w_location_score;?></td>
    <td>&nbsp;</td>
    <td>4</td>
    <td>8</td>

  <tr align="center" bgcolor="#60BAF0" class="ntext">
    <td>PERSONAL DATA</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
</tr>
 <tr align="center" bgcolor="#E1F3FB">
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
   
  </tr>
  </tr>
  <tr align="center">
    <td>Years of banking relationship with BBS </td>
    <td>3</td>
    <td><?php echo $relationship_score; ?></td>
    <td><?php echo $w_relationship_score; ?></td>
    <td>&nbsp;</td>
    <td>3</td>
    <td>15</td>
  </tr>
  <tr align="center">
 
    <td># of banking products with BBS excl.new request</td>
    <td>2</td>
    <td><?php echo $total_bbs_products_score;?></td>
    <td><?php echo $w_total_bbs_products_score;?></td>
    <td>&nbsp;</td>
    <td>2</td>
    <td>10</td>

  </tr>
  <tr align="center">
    <td># of arreas incidence over the last 12 months</td>
    <td>10</td>
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
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
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
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
<tr align="center" bgcolor="#E1F3FB">
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
    <td><?php echo $card_held_since_score;?></td>
    <td><?php echo $w_card_held_since_score;?></td>
    <td>&nbsp;</td>
      <td>0</td>
    <td>5</td>

  </tr>
  <tr align="center">
    <td>Number of loans presently outstanding</td>
    <td>2</td>
    <td><?php echo $loans_outstanding_score;?></td>
    <td><?php echo $w_loans_outstanding_score;?></td>
    <td>&nbsp;</td>
      <td>0</td>
       <td>5</td>

  </tr>
  <tr align="center">
    <td>Affordability ratio(Inst/Salary; from 0 to y%,etc) </td>
    <td>7</td>
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
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    
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
    <td>Mortgage Loan Type</td>
    <td>2</td>
    <td><?php echo$rate_type_score;?></td>
    <td><?php echo$w_rate_type_score;?></td>
    <td>&nbsp;</td>
    <td>4</td>
    <td>8</td>

  </tr>
  
  <tr align="center">
    <td>Maturity of the loan in years</td>
    <td>1</td>
    <td><?php echo $loan_maturity_score;?></td>
    <td><?php echo $w_loan_maturity_score;?></td>
    <td>&nbsp;</td>
   <td>2.5</td>
    <td>4</td>
   </tr>

  
    <tr align="center" bgcolor="#60BAF0" class="ntext">
    <td>Product Characteristics</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  
  </tr>
  <tr align="center" bgcolor="#FFFFFF">
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
 
  </tr>
  <tr align="center" bgcolor="#CCEBF7">
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
   
  </tr>
</form></table>
          </form>
    </table></td></tr>
  </table>
</table></table>
</font> 
</BODY>
</HTML>
