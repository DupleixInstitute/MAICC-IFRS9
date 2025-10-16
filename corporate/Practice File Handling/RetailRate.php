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

//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
/*
//echo  "BBS\ ";
$bbs='BBS\\';

$new=$bbs.$username;
$ldappass=$_POST['password'];
//echo $new.$ldappass;

$ldaprdn  = $new;     // ldap rdn or dn
$ldappass = $ldappass;  // associated password

// connect to ldap server
$ldapconn = ldap_connect("10.0.9.10")
    or die("Could not connect to LDAP server.");

if ($ldapconn) {

    // binding to ldap server
    $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);

    // verify binding
    if ($ldapbind) {
        echo "";
    } else {
	
     echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
		

    }

}
  */
  
  echo $username;
$pass="sefalana2008";;
$host="localhost"; // Host name 
$user="root";
//$username="root"; // Mysql username 
//$password="sefalana2008"; // Mysql password 
$db_name="sour"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 

$cif=NULL;
$one=NULL;
$loan_amount=NULL;
$deduct=NULL;
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
$to18=NULL;
$to26=NULL;
$to12=NULL;
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
$gender=NULL;
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
  $deduct_score =NULL;
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
$installment1=NULL;
$ainstallment1=NULL;
$ainstallment2=NULL;
$binstallment1=NULL;
$binstallment2=NULL;
$binstallment3=NULL;
$cinstallment1=NULL;
$cinstallment2=NULL;
$cinstallment3=NULL;
$cinstallment4=NULL;
$perm_res=NULL;

$tax2=NULL;
$tax=NULL;

$email=NULL;


$username=$_POST['username'];
$loan_number=$_POST['loan_number'];
$password=$_POST['password'];
$installment1=$_POST['installment1'];
$ainstallment1=$_POST['ainstallment1'];
$ainstallment2=$_POST['ainstallment2'];
$binstallment1=$_POST['binstallment1'];
$binstallment2=$_POST['binstallment2'];
$binstallment3=$_POST['binstallment3'];
$cinstallment1=$_POST['cinstallment1'];
$cinstallment2=$_POST['cinstallment2'];
$cinstallment3=$_POST['cinstallment3'];
$cinstallment4=$_POST['cinstallment4'];

$dinstallment1=$_POST['dinstallment1'];
$dinstallment2=$_POST['dinstallment2'];
$dinstallment3=$_POST['dinstallment3'];
$dinstallment4=$_POST['dinstallment4'];
$dinstallment5=$_POST['dinstallment5'];

$einstallment1=$_POST['einstallment1'];
$einstallment2=$_POST['einstallment2'];
$einstallment3=$_POST['einstallment3'];
$einstallment4=$_POST['einstallment4'];
$einstallment5=$_POST['einstallment5'];
$einstallment6=$_POST['einstallment6'];

$finstallment1=$_POST['finstallment1'];
$finstallment2=$_POST['finstallment2'];
$finstallment3=$_POST['finstallment3'];
$finstallment4=$_POST['finstallment4'];
$finstallment5=$_POST['finstallment5'];
$finstallment6=$_POST['finstallment6'];
$finstallment7=$_POST['finstallment7'];


$ginstallment1=$_POST['ginstallment1'];
$ginstallment2=$_POST['ginstallment2'];
$ginstallment3=$_POST['ginstallment3'];
$ginstallment4=$_POST['ginstallment4'];
$ginstallment5=$_POST['ginstallment5'];
$ginstallment6=$_POST['ginstallment6'];
$ginstallment7=$_POST['ginstallment7'];
$ginstallment8=$_POST['ginstallment8'];

$hinstallment1=$_POST['hinstallment1'];
$hinstallment2=$_POST['hinstallment2'];
$hinstallment3=$_POST['hinstallment3'];
$hinstallment4=$_POST['hinstallment4'];
$hinstallment5=$_POST['hinstallment5'];
$hinstallment6=$_POST['hinstallment6'];
$hinstallment7=$_POST['hinstallment7'];
$hinstallment8=$_POST['hinstallment8'];
$hinstallment9=$_POST['hinstallment9'];

$cif=$_POST['cif'];
$perm_res=$_POST['perm_res'];
$gender=$_POST['gender'];
//echo $gender;
$loan_amount=$_POST['loan_amount'];
//echo 'ttmm'; echo $loan_amount;
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
$email=$_POST['email'];
$street_add=$_POST['street_add'];
$town=$_POST['town'];
$country=$_POST['country'];
$permanent_residence=$_POST['permanent_residence'];
$location=$_POST['location'];
$day=$_POST['day'];

$month=$_POST['month'];
$year=$_POST['year'];
$omang_passport_num=$_POST['one'];
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
//echo $year;
//$age=$myyear - $year;

//echo $myyear;
$age=$myyear - $year;
$age=$myyear - $year;
$total_age=$age + $loan_maturity;

echo $total_age;
//echo $age;
//$mymonth=8;
//echo $mymonth; echo "<br>"; echo $month; echo "<br>";
//echo $myyear; echo "<br>"; echo $year; echo "<br>";

//$x=$mymonth + $month;
//echo $x;
if($mymonth < $month)
//echo "am in if";
$age=$age-1;

$tax=$_POST['tax'];
$tax2=$_POST['tax2'];
//echo $age;
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
$to18=$_POST['to18'];
$to26=$_POST['to26'];
$to12=$_POST['to12'];
$aunts_uncles_cousins=$_POST['aunts_uncles_cousins'];
$other=$_POST['other'];
$years_at_address=$_POST['years_at_address'];
$rent=$_POST['rent'];
$education=$_POST['education'];
$professional=$_POST['Professional'];
$employment=$_POST['employment'];
$years_at_job=$_POST['years_at_job'];
//echo $years_at_job;
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
$bond_plot=$_POST['bond_plot'];
$ST=$_POST['ST'];
$Mortgages=$_POST['Mortgages'];
$why_renegotiate=$_POST['why_renogotiation'];
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
//echo 'týyyyyyyyy'; echo $renegotiate;
$loan_arrears=$_POST['loan_arrears'];
$cards_held=$_POST['cards_held'];
//echo 'thato'; echo $cards_held;
$card_held_since=$_POST['card_held_since'];
$loans_outstanding=$_POST['loans_outstanding'];
$affordability_ratio=$_POST['affordability_ratio'];
$blacklisted=$_POST['blacklisted'];
$fraud_alert=$_POST['fraud_alert'];
$ltv_policy=$_POST['ltv_policy'];
$affordability_policy=$_POST['affordability_policy'];
$ltv=$_POST['ltv'];
$other_names=$_POST['other_name'];
$grandparents=$_POST['grandparents'];
$deduct=$_POST['deduct'];
$nationality_comment=NULL;
if ($ltv > $ltv_policy)
$ltvcomment="REJECT";
else
$ltvcomment="OK";


if ($affordability_ratio > $affordability_policy)
{ 
$affordabilitycomment="REJECT";
}
else
{
$affordabilitycomment="OK";
//echo "2";
}
//echo $affordabilitycomment;
if ($loan_type=="Guaranteed Scheme" || $loan_type=="Non-Guaranteed Scheme"){
$affordabilitycomment="OK";
}

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

if ($nationality=="Foreigner" and $perm_res=="Yes")
$nationality_comment="REJECT";
else
$nationality_comment="OK";

if ($age>65)
$age_comment="REJECT";
else
$age_comment="OK";

if ($total_age>65)
$total_age_comment="REJECT";
else
$total_age_comment="OK";

//echo $perm_res;
$numloans=$_POST['loans_outstanding'];
//echo $numloans; echo "Papip";

if ($numloans==0)
{
$debtserviceratio=($loanandinsurance / $rev4afford )*100;
}

else if ($numloans==1)
{
//echo "chose 1";
$debtserviceratio=(($installment1 +  $loanandinsurance )/ $rev4afford )*100;
}


else if ($numloans==2)
{
//echo "chose 2";
$debtserviceratio=(($ainstallment1 + $ainstallment2 + $loanandinsurance )/ $rev4afford )*100;
}

if ($numloans==3)
{
//echo "chose 3";
$debtserviceratio=(($installment1 + $binstallment2 + $binstallment3 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==4)
{
//echo "chose 4";
$debtserviceratio=(($cinstallment1 + $cinstallment2 + $cinstallment3 + $cinstallment4 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==5)
{
//echo "chose 4";
$debtserviceratio=(($dinstallment1 + $dinstallment2 + $dinstallment3 + $dinstallment4 + $dinstallment5 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==6)
{
//echo "chose 4";
$debtserviceratio=(($einstallment1 + $einstallment2 + $einstallment3 + $einstallment4 + $einstallment5 + $einstallment6 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==7)
{
//echo "chose 4";
$debtserviceratio=(($finstallment1 + $finstallment2 + $finstallment3 + $finstallment4 + $finstallment5 + $finstallment6 + $finstallment7 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==8)
{
//echo "chose 4";
$debtserviceratio=(($ginstallment1 + $ginstallment2 + $ginstallment3 + $ginstallment4 + $ginstallment5 + $ginstallment6 + $ginstallment7 + $ginstallment8 + $loanandinsurance )/ $rev4afford )*100;
}
if ($numloans==9)
{
//echo "chose 4";
$debtserviceratio=(($hinstallment1 + $hinstallment2 + $hinstallment3 + $hinstallment4 + $hinstallment5 + $hinstallment6 + $hinstallment7 + $hinstallment8 + $hinstallment9 + $loanandinsurance )/ $rev4afford )*100;
}

//echo "hhh";
//echo "kee  ".$debtserviceratio;
$dsr=number_format($debtserviceratio, 2, '.', '');
/*if ($debtserviceratio > 80)
$dsr_comment="REJECT";
else
$dsr_comment="OK";*/
//add user details
if ($debtserviceratio > 80){
$dsr_score=100;
$dsr_comment="REJECT";
}
else{
$dsr_score=400;
$dsr_comment="OK";
}
$dsr_w_score=$dsr_score * 0.03;

$today1 = date("m");
$today2 = date("j");
$today3 = date("Y");
$today=$today3.$today1.$today2;
$birth=$year.$month.$day;
$spouse=$year2.$month2.$day2;
$wedding=$year22.$month22.$day22;
$divorce=$year23.$month23.$day23;

$a=date($today);
$b=date($birth);
$c=date($spouse);
$d=date($wedding);
$e=date($divorce);


if($a == $b || $a==$c || $a==$d || $a==$e )
{?>
 
<?php
echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed.php\">";
die();
}
else
{
//echo "correct";
//echo $age."eee".$myyear;

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

		   
			   
//-----------------AGE GROUP OF BORROWER-------------------------------------------------------------

//echo "age "; echo $age;
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
//echo $marital_status_score;		   
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
      $years_at_job_score=400;
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
case ($location=='Urban');
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
case N/A:
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
case ($affordability_ratio > 40 and $affordability_ratio < 50.5);
$affordability_ratio_score =100;
break;
case ($affordability_ratio > 50.4);
$affordability_ratio_score =0;
break;
}
$w_affordability_ratio_score=$affordability_ratio_score*0.05;

if ($affordability_ratio > 40)

$affordability_ratio_comment="REJECT";
else
$affordability_ratio_comment="OK";




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

//ADD THE TOTAL---------------------








$total_personal_data=$marital_status_score + $age_score + $no_of_children_score + $dependants_at_home_score + $education_level_score 
+ $professional_score + $employment_score + $years_at_job_score + $revenues_score + $location_score;

$w_total_personal_data=$w_marital_status_score + $w_age_score + $w_no_of_children_score + $w_dependants_at_home_score + $w_education_level_score 
+ $w_professional_score + $w_employment_score + $w_years_at_job_score + $w_revenues_score + $w_location_score;

$total_length= $relationship_score + $total_bbs_products_score + $loan_arrears_score + $renegotiate_score;

$w_total_length= $w_relationship_score + $w_total_bbs_products_score + $w_loan_arrears_score + $w_renegotiate_score;

$total_ICT= $paid_debts_score + $judgement_score + $default_data_score + $trace_alerts_score + deduct_score;

$w_total_ICT= $w_paid_debts_score + $w_judgement_score + $w_default_data_score + $w_trace_alerts_score + w_deduct_score;


$total_personal=$affordability_ratio_score + $card_held_since_score + $loans_outstanding_score + $dsr_score; 
$w_total_personal=$w_affordability_ratio_score + $w_card_held_since_score + $w_loans_outstanding_score + $dsr_w_score;

$total_product=$rate_type_score + $loan_maturity_score;
$w_total_product=$w_rate_type_score + $w_loan_maturity_score;

$score=$total_product + $total_personal +  $total_length + $total_personal_data;

$w_score=$w_total_product + $w_total_personal  + $w_total_length + $w_total_personal_data;



?>
          <tr class="bdr2"  align="center"  > 
            <td class="ntext">SCOIRNG ATTRIBUTES</td>
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
$con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysqli_select_db("creditscoring", $con);
		
$sql2="INSERT INTO creditscore (omang_passport_num,cif,loan_number,marital_status_score, w_marital_status_score,
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
VALUES('$omang_passport_num','$cif' ,'$loan_number','$marital_status_score' ,'$w_marital_status_score',
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
'$score',
'$deduct_score',
'$w_deduct_score',
'$age_comment'
)";

  
if (!mysql_query($sql2,$con))
  {
  die('Error1: ' . mysql_error());
  }
  ?>
       <?php
$con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysql_select_db("creditscoring", $con);
		
$sql3="INSERT INTO cust_information (omang_passport_num,
CIF,
loan_number,
loan_amount_requested,
property_type,
open_market_value,
loan_type,
loan_maturity_requested,
rate_type_requested,
current_interest_rate,
insurance_replacement_cost,
estimated_insurance_premium,
estimated_installment,
estimated_installment_insurance,
loan_to_value_policy,
loan_to_value,
total_revenues_affordability,
affordability_policy,
affordability_ratio,
cutomer_type,
nationality,
borrower_name,
other_names,
surname,
gender,
spouse_partner_co_borrower_name,
spouse_surname,
borrower_present_address,
E_mail,
Street_name_and_number,
bond_plot,
town,
country,
permanent_country_of_residence,
location_of_acquired_real_estate,
borrower_birth,
spouse_birth,
wedding,
divorce,
marital_status,
marital_contract_type,
spouse_borrowing_relationship,
total_number_of_children,
children0_to12,
childred13_to18,
children19_to26,
other_dependents_grandparents,
other_dependents_aunts,
other_dependents,
total_dependants,
years_at_present_address,
monthly_payment,
borrower_education,
professional_category,
employment_contract,
number_of_years_at_employer,
household_proffessional_revenue,
annual_salary,
fixed_permanent_allowances,
total_revenue_for_affordability,
partner_annual_salary,
partner_permanent_allowances,
partner_revenue_for_affordability,
total_allowable_household_revenues,
main_bank,
second_bank,
third_bank,
age_of_relationship,
savings_Account,
deposit_Account,
share_Account,
ST_Loans,
mortgages,
total_bbs_products,
bbs_arreas_12months,
renegotiated,
why_renegotiated,
no_of_credit_card,
card_held_since,
number_of_loans_outstanding,
ITC_REF,
ITC_subject_no,
paid_debt,
judgment,
default_data,
trace_alerts,
blacklist_flag,
fraud_alerts,
age,
creation,
deduct,
username,
debtserviceratio,
tax,
tax2
) 
VALUES('$omang_passport_num',
'$cif',
'$loan_number',
'$loan_amount',
'$property_type',
'$open_market_value',
'$loan_type',
'$loan_maturity',
'$rate_type',
'$irate',
'$insurance_replacement',
'$insurance_premium',
'$loan_installment',
'$loanandinsurance',
'$ltv_policy',
'$ltv',
'$total_rev_for_afford',
'$affordability_policy',
'$affordability_ratio',
'$customer_type',
'$nationality',
'$name',
'$other_names',
'$surname',
'$gender',
'$spouse_name',
'$spouse_surname',
'$address',
'$email',
'$street_add',
'$bond_plot',
'$town',
'$country',
'$permanent_residence',
'$location',
'$year$month$day',
'$year2$month2$day2',
'$year22$month22$day22',
'$year23$month23$day23',
'$marital_status',
'$marital_contract_type',
'$spouse_borrowing_realtionship',
'$no_of_children',
'$to12',
'$to18',
'$to26',
'$grandparents',
'$aunts_uncles_cousins',
'$other',
'$dependants_at_home',
'$years_at_address',
'$rent',
'$education',
'$professional',
'$employment',
'$years_at_job',
'$revenues',
'$annual_sal',
'$fixed_perm_allowances',
'$rev4afford',
'$hp_annual_sal',
'$hp_fixed_perm_allowances',
'$hp_total_rev_for_afford',
'$total_allowable_household_revenues',
'$mainbank',
'$secondbank',
'$thirdbank',
'$relationship',
'$Savings',
'$Deposit',
'$Share',
'$ST',
'$Mortgages',
'$total_bbs_products',
'$loan_arrears',
'$renegotiate',
'$why_renegotiate',
'$cards_held',
'$card_held_since',
'$loans_outstanding',
'$itc_ref',
'$itc_sub_no',
'$paid_debts',
'$judgement',
'$default_data',
'$trace_alerts',
'$blacklisted',
'$fraud_alert',
'$age',
LOCALTIME(),
'$deduct',
'$username',
'$debtserviceratio',
'$tax',
'$tax2'
)";

  
if (!mysql_query($sql3,$con))
  {
  die('Error2ERR: ' . mysql_error());
  }
  ?>
	   
	   
	   
	   
	   
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
