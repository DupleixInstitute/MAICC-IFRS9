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

$username=$_POST['username'];  
echo $username;

//$db_name="sour"; // Database name 

// Connect to server and select databse.
include("assets/includes/authenticate.php");


//initiliase customer information temporary variables
include("assets/includes/initialise_customer_information_variables.php");

//extract customer information post variables
include("assets/includes/extract_customer_information_post_variables.php");

//calculate age and total age
$todayv   = getdate();

$myyear	  = $todayv['year'];
$mymonth  = $todayv['month'];

if ($mymonth== 'January')   {$mymonth=1;}
if ($mymonth=='February' )  {$mymonth=2; }
if ($mymonth=='March'    )  {$mymonth=3; }
if ($mymonth=='April'    )  {$mymonth=4; }
if ($mymonth=='May'      )  {$mymonth=5; }
if ($mymonth=='June'     )  {$mymonth=6; }
if ($mymonth=='July'     )  {$mymonth=7; }
if ($mymonth=='August'   )  {$mymonth=8; }
if ($mymonth=='September')  {$mymonth=9; }
if ($mymonth=='October'  )  {$mymonth=10;}
if ($mymonth=='November' )  {$mymonth=11;}
if ($mymonth=='December' )  {$mymonth=12;}

$age       = $myyear - $year;
$total_age = $age    + $loan_maturity;

if($mymonth < $month)
//echo "am in if";
$age=$age-1;

include ("assets/includes/determine_status_comments.php");
//===============================================================================================================
//VALIDATION CHECK - Ensure today's date <> spouse, borrower, wedding or divorce date
//================================================================================================================

//Get to day's date parts and combine
$today1 = date("m");
$today2 = date("j");
$today3 = date("Y");
$today=$today3.$today1.$today2;
//Combine borrower date parts
$birth=$year.$month.$day;
//combine spouse date parts
$spouse=$year2.$month2.$day2;
//combine wedding date parts
$wedding=$year22.$month22.$day22;
//combine divorce date parts
$divorce=$year23.$month23.$day23;
//perform validation check and die/error message if failed
$a=date($today);
$b=date($birth);
$c=date($spouse);
$d=date($wedding);
$e=date($divorce);


if($a == $b  || $a==$c || $a==$d || $a==$e && $a <> 0 )
{?>
 
<?php
echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed.php\">";
die();
}
else
{

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
            <?php if ($nationality_comment=="REJECT")
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
            <td><?php echo $ltv_policy;?></td>
            <td><?php echo $ltv; ?></td>
            <?php if ($ltvcomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
          <tr> 
            <td>Affordability Policy</td>
            <td>40%</td>
            <td><?php echo $affordability_ratio;  ?></td>
            <?php if ($affordabilitycomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
		  <tr> 
            <td>Debt Service Ratio</td>
            <td>80%</td>
            <td><?php echo $dsr ; ?></td>
            <?php if ($dsr_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
		    <tr> 
            <td>age</td>
            <td>below 65</td>
            <td><?php echo $age ; ?></td>
            <?php if ($age_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
          </tr>
		  
		    <tr> 
            <td>Age plus loan maturity</td>
            <td>below 65</td>
            <td><?php echo $total_age; ?></td>
            <?php if ($total_age_comment=="REJECT")
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

include ("assets/includes/perform_rating.php");		   
			   

?>
          <tr class="bdr2"  align="center"  > 
            <td class="ntext">SCORING ATTRIBUTES</td>
            <td class="ntext">WEIGHT</td>
			<td class="ntext"><!--VALUE--></td>
            <td class="ntext">SCORE</td>
            <td class="ntext">WEIGHTED SCORE</td>
            <td class="ntext">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
            <td class="ntext">MIN</td>
            <td class="ntext">MAX</td>
          </tr>
          <tr align="center"> 
           <!-- <td>Marital Status</td>
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
			<td><?php echo $age;?></td>
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
            <td><?php echo $w_location_score; ?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>8</td>-->
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
            <!--<td>Years of banking relationship with BBS </td>
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
	echo "<td bgcolor=\"#32C079\">OK</td>"; ?>
            <td>0</td>
            <td>60</td>-->
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
          
           <!-- <td>paid debts vs.Defaults(if 1=more paid than defaults)</td>
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
	echo "<td bgcolor=\"#32C079\">OK</td>"; ?>
            <td>0</td>
            <td>10</td>-->
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
            <!--<td>Number of credit card personal held</td>
            <td>2</td>
			<td><?php echo $card_held_since;?></td>
            <td><?php echo $card_held_since_score;?></td>
            <td><?php echo $w_card_held_since_score;?></td>
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
	echo "<td bgcolor=\"#32C079\">OK</td>"; ?>
            <td>0</td>
            <td>35</td>-->
          </tr>
          <tr align="center" bgcolor="#60BAF0" class="ntext"> 
            <td>Personal Financial Data</td>
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
         <!--   <td>Mortgage Loan Type</td>
            <td>2</td>
			 <td><?php echo $rate_type;?></td>
            <td><?php echo $rate_type_score;?></td>
            <td><?php echo $w_rate_type_score;?></td>
            <td>&nbsp;</td>
            <td>4</td>
            <td>8</td>
          </tr>
          <tr align="center"> 
           <!-- <td>Maturity of the loan in years</td>
            <td>1</td>
			 <td><?php echo $loan_maturity;?></td>
            <td><?php echo $loan_maturity_score;?></td>
            <td><?php echo $w_loan_maturity_score;?></td>
            <td>&nbsp;</td>
            <td>2.5</td>
            <td>4</td>-->
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
            <td><?php echo $score;?></td>
            <td><?php echo $w_score;?></td>
            <td>&nbsp;</td>
            <td>73</td>
            <td>337</td>
          </tr>
        </table>
		<?php
		//save the credit scores and customer information
	    try {	
    		include ("assets/includes/database_connection.php");		
	    	include ("assets/includes/save_credit_scores.php");
		    include ("assets/includes/database_connection.php");		
		    include ("assets/includes/save_customer_information.php");  
		} catch (Exception $e) {
			echo 'Database update aborted: ' .$e->getMessage();  
		}
		?>
		
		<!-- Added pass variables -->
		<input type="hidden" name="username" value="<?php echo $username;?>"/>
		<input type="hidden" name="password" value="<?php echo $password;?>"/>
		<input type="hidden" name="cif"      value="<?php echo $cif;?>"/>

		<input type="hidden" name="affordability_ratio" value="<?php echo $affordability_ratio;?>"/>
		<input type="hidden" name="affordability_policy" value="<?php echo $affordability_policy;?>"/>
		<input type="hidden" name="fraud_alert" value="<?php echo $fraud_alert;?>"/>
		<input type="hidden" name="loan_type"   value="<?php echo $_POST['loan_type'];?>"/>

		<input type="hidden" name="year"                   value="<?php echo $year;?>"/>
		<input type="hidden" name="month"                  value="<?php echo $month;?>"/>
		<input type="hidden" name="renegotiate_comment"    value="<?php echo $renegotiate_comment;?>"/>
		<!-- End of Added pass variables -->

		
		
		<input type="hidden" name="loan_amount" value="<?php echo $loan_amount;?>"/>
		<input type="hidden" name="loanandinsurance" value="<?php echo $loanandinsurance;?>"/>
		<input type="hidden" name="open_market_value" value="<?php echo $open_market_value;?>"/>
		<input type="hidden" name="loan_amount" value="<?php echo $loan_amount;?>"/>
		<input type="hidden" name="name" value="<?php echo $name;?>"/>
		<input type="hidden" name="surname" value="<?php echo $surname;?>"/>
		<input type="hidden" name="customer_type" value="<?php echo $customer_type;?>"/>
		<input type="hidden" name="ltvcomment" value="<?php echo $ltvcomment;?>"/>
		<input type="hidden" name="ltv" value="<?php echo $ltv;?>"/>
		<input type="hidden" name="nationality" value="<?php echo $nationality;?>"/>
		<input type="hidden" name="blacklisted" value="<?php echo $blacklisted;?>"/>
		<input type="hidden" name="open_market_value" value="<?php echo $open_market_value;?>"/>
		<input type="hidden" name="affordabilitycomment" value="<?php echo $affordabilitycomment;?>"/>
		<input type="hidden" name="loan_arrears_score_comment" value="<?php echo $loan_arrears_score_comment;?>"/>
		<input type="hidden" name="paid_debts_score_comment" value="<?php echo $paid_debts_score_comment;?>"/>
		<input type="hidden" name="judgement_score_comment" value="<?php echo $judgement_score_comment;?>"/>
		<input type="hidden" name="default_data_score_comment" value="<?php echo $default_data_score_comment;?>"/>
		<input type="hidden" name="trace_alerts_score_comment" value="<?php echo $trace_alerts_score_comment;?>"/>
		<input type="hidden" name="omang_passport_num" value="<?php echo $omang_passport_num;?>"/>
		<input type="hidden" name="loan_number" value="<?php echo $loan_number;?>"/>
		<input type="hidden" name="score" value="<?php echo $score;?>"/>
		<input type="hidden" name="w_score" value="<?php echo $w_score;?>"/>
		<input type="hidden" name="ltv_policy" value="<?php echo $ltv_policy;?>"/>
		<input type="hidden" name="total_bbs_products" value="<?php echo $total_bbs_products;?>"/>
		<input type="hidden" name="loans_outstanding" value="<?php echo $loans_outstanding;?>"/>
		<input type="hidden" name="marital_status" value="<?php echo $marital_status;?>"/>
		<input type="hidden" name="annual_sal" value="<?php echo $annual_sal;?>"/>
		<input type="hidden" name="deduct" value="<?php echo $deduct;?>"/>
	    <input type="SUBMIT" name="RATE"  class="btn" value="Submit"/>
      </form>
    </table></td></tr>
</table></table></table>
<?php
}
?>
</font> 
</BODY>
</HTML>
