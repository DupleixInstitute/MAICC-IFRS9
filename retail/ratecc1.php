 <HTML>
<HEAD>
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

//echo $mymonth; echo "<br>"; echo $month; echo "<br>";
//echo $myyear; echo "<br>"; echo $year; echo "<br>";
if ($mymonth > $month){
$age=$age+1;
}

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
//$to18=$_POST['to18'];
//$to26=$_POST['to26'];
//$to12=$_POST['to12'];
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

/*$connect=mysql_connect($host,$username,$password); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());

}
$insert="INSERT INTO Access_Table VALUES('$ip',CURDATE())";
$result=(mysql_query($insert));
if($result)
{
//echo "done";
}*/

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
<table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">
<tr>
<td>Borrower</td>
<td>Rating</td>
<td>Score</td>
<td>PD</td>
</tr>
<tr>
<td>xxx</td>
<td>6</td>
<td>106</td>
<td>12.00%</td>
</tr></table>
</br>
<table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">


  <tr  bgcolor="#E1F3FB">
    <td>&nbsp;</td>
    <td>//comments</td>
  </tr>
  
  <tr>
    <td>Customer Type: Legal/Physical</td>
	<?php if ($customer_type=="Legal Entity")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td >Nationality: Batswana/Foreign</td>
	<?php if ($nationality=="Foreigner")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td >Blacklist Flag</td>
    <	<?php if ($blacklisted=="Yes")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td >Automatic Fraud Alert</td>
   
  <?php if ($fraud_alert=="Yes")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr bgcolor="#E1F3FB">
    <td class="ntext" >&nbsp;&nbsp;</td>
    <td>&nbsp;</td>
    
  </tr>
  <tr>
    <td >Loan to Value Policy</td>
    <?php if ($ltvcomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
	
      </tr>
  <tr>
    <td >Affordability Policy</td>
    <?php if ($$affordabilitycomment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
	

    
  </tr>
  <tr>
    <td ># of arreas incidence over the last 12 months</td>
    <?php if ($loan_arrears_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  
  </tr>
  <tr>
    <td >Loan overdue & renegotiated (last 2 years)</td>
    <?php if ($renegotiate_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
    
  </tr>
 <tr>
    <td >Paid Debts vs.Defaults(if 1= more paid than defaults)</td>
    <?php if ($paid_debts_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
   
  </tr>
  <tr>
    <td >Judgements</td>
    <?php if ($judgement_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td >Defaults</td>
    <?php if ($default_data_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
  </tr>
  <tr>
    <td >Traces</td>
    <?php if ($trace_alerts_score_comment=="REJECT")
	echo "<td bgcolor=\"#F20000\"> REJECT </td>";
	else
	echo "<td bgcolor=\"#32C079\">OK</td>";?>
	
   
  </tr>
  

</table>

</form></table>
          </form>
    </table></td></tr>
  </table>
</table></table>
</font> 
</BODY>
</HTML>
