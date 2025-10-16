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
<font class="s_text"> 
<?php
//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
  echo $username;

$pass="sefalana2008";
$host="localhost"; // Host name 
$user="root";
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 

$loan_amount=$_POST['loan_amount'];
//echo 'ttmm'; echo $loan_amount;
$property_type=$_POST['property_type'];
$open_market_value=$_POST['open_market_value'];
$loan_type=$_POST['loan_type'];
$ltv=$_POST['ltv'];
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
$paid_debts_score_comment=$_POST['paid_debts_score_comment'];
$judgement_score_comment=$_POST['judgement_score_comment'];
$default_data_score_comment=$_POST['default_data_score_comment'];
$trace_alerts_score_comment=$_POST['trace_alerts_score_comment'];
//echo  $trace_alerts_score_comment;
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
$omang_passport_num=$_POST['omang_passport_num'];
$loan_number=$_POST['loan_number'];

//echo "mooly"; echo $omang_passport_num;
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

$ltvcomment=$_POST['ltvcomment'];

$affordability_policy=$_POST['affordability_policy'];
$ltv=$_POST['ltv'];
$haircut=$open_market_value*.25;
//echo $loanandinsurance; echo 'll';
//echo "my install"; echo $loanandinsurance;
$install=$loanandinsurance*3;
if ($ltv > $ltv_policy)
$ltvcomment="REJECT";
else
$ltvcomment="OK";

if ($affordability_ratio > $affordability_policy)
{ 
$affordabilitycomment="REJECT";
//echo "1";
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

/*if ($affordability_ratio > $affordability_policy)
$affordabilitycomment="REJECT";
else
$affordabilitycomment="OK";
*/

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
//general cost
$gcost=$loan_amount*.05;
$score=$_POST['w_score'];
$totalscore=$_POST['score'];


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
if ($score < 72){
$rate="unrated";
$pd="above acceptable level";
}
if ($marital_status =="Divorced")
$marital_status_score=1;
else
$marital_status_score=0;
//echo "marital_status_score";
//echo $marital_status_score;
//echo $marital_status;



$deduct=$_POST['deduct'];

if ($deduct == "Yes")
$deduct_score=1;
else
$deduct_score=0;
if ($annual_sal < 120000)
{
            if ($annual_sal >= 0)
            {
                        $annual_sal_score = 1;
            }
            else
            {
                        $annual_sal_score = 0;  
            }
}
else
{
$annual_sal_score = 0;
}
if ($annual_sal < 150000)
{
            if ($annual_sal >= 120000)
            {
                        $annual_sal_score1 = 1;
            }
            else
            {
                        $annual_sal_score1 = 0;            
            }
}
else
{
$annual_sal_score1 = 0;
}
$tttt = exp(-3.4949+(-0.5035*$total_bbs_products)+(0.4305*$loans_outstanding)+(0.9053*$marital_status_score)+(0.9956*

$annual_sal_score)+(0.8133*$annual_sal_score1)+(-1.0289*$deduct_score));
//PD = (A/(1+A))
$pdscore = ($tttt/(1+$tttt));
$pd=number_format($pdscore, 4, '.', '');

if ($pd > 0.05)
{
	$rate = 4;
	$score = 500;
}
else
{
	if ($pd > 0.025)
	{
		$rate = 3;
		$score = 750;		
	}
	else
	{
		if($pd > 0.01)
		{
			$rate = 2;
			$score = 900;	
		}
		else
		{
			$rate = 1;
			$score = 1000;
			//Low	
		}	
	}
}
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
   <form ACTION="customer.php" method="post" name="f1">  
   
   <table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">
<tr>
<td>Borrower</td>
<td>Rating</td>
<td>Score</td>
<td>PD</td>
</tr>
<tr>
<td><?php echo $name; echo " "; echo $surname; ?></td>
<td><?php echo $rate; ?></td>
<td><?php echo $score; ?></td>
<td><?php echo $pd*100; echo "%";?></td>
</tr></table>
</br>
<table width="60%" border="1" align="center" cellpadding="0" cellspacing="0"  bordercolorlight="#60BAF0"  class="s_text">
  <tr  bgcolor="#E1F3FB">
    <td>&nbsp;</td>
    <td></td>
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
	<?php if ($blacklisted=="Yes")
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
   <p></p>
      <p></p>   <p></p>
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
    <td><?php echo $loan_amount?></td>
    <td>100%</td>
  </tr>
  <tr>
    <td >3 Month Instalments</td>
    <td>3</td>
    <td><?php echo $install;?></td>
    <td>3%</td>
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
    <td class="ntext"  >Estimated EAD</td>
    <td>&nbsp;</td>
    <td class="ntext"><?php echo $ead ;?></td>
    <td>108%</td>
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
  $con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysql_select_db("creditscoring", $con);
  
  
  $sql3="UPDATE cust_credit_rating SET
 CIF='$cif',
 pd='$pd',
loan_amount='$loan_amount',
loan_percentage=100,
installment ='$install',
installment_percentage ='$instalp',
cost_estimation='$gcost',
cost_estimation_percentage=5,
estimated_EAD='$ead',
estimated_EAD_percentage='$eadp',
estimated_OMV='$open_market_value',
estimated_OMV_percentage='$omv',
haircut='$haircut',
haircut_percentage='$hc',
estimated_LGD='$lgd',
estimated_LGD_percentage='$h2',
Expected_loss='$el2',
expected_loss_percentage='$llpfinal', 
ltv='$ltv', 
affordabilty='$affordability_ratio', 
rate='$rate',
date=LOCALTIME() where omang_passport_num='$omang_passport_num' and loan_number=$loan_number";


  
if (!mysql_query($sql3,$con))
  {
  die('Error2: ' . mysql_error());
  }
  ?>
  
    <input type="hidden" value="<?php echo $omang_passport_num;?>" name="omang"/>
    <input type="hidden" value="<?php echo $loan_number;?>" name="loan" />

  <tr><td>
      <input type="submit" name="submit" value="submit" class="btn">
</td></tr>
 </form>
    
    
    </table></td></tr>
  </table>
   
</table></table>
</font> 
</BODY>
</HTML>
