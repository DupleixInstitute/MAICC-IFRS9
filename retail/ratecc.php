 <HTML>
<HEAD>
<style>
<!--
 /* Font Definitions */
 @font-face
	{font-family:"Cambria Math";
	panose-1:2 4 5 3 5 4 6 3 2 4;
	mso-font-charset:0;
	mso-generic-font-family:roman;
	mso-font-pitch:variable;
	mso-font-signature:-1610611985 1107304683 0 0 159 0;}
@font-face
	{font-family:Calibri;
	panose-1:2 15 5 2 2 2 4 3 2 4;
	mso-font-charset:0;
	mso-generic-font-family:swiss;
	mso-font-pitch:variable;
	mso-font-signature:-1610611985 1073750139 0 0 159 0;}
 /* Style Definitions */
 p.MsoNormal, li.MsoNormal, div.MsoNormal
	{mso-style-unhide:no;
	mso-style-qformat:yes;
	mso-style-parent:"";
	margin-top:0cm;
	margin-right:0cm;
	margin-bottom:10.0pt;
	margin-left:0cm;
	line-height:115%;
	mso-pagination:widow-orphan;
	font-size:11.0pt;
	font-family:"Calibri","sans-serif";
	mso-ascii-font-family:Calibri;
	mso-ascii-theme-font:minor-latin;
	mso-fareast-font-family:Calibri;
	mso-fareast-theme-font:minor-latin;
	mso-hansi-font-family:Calibri;
	mso-hansi-theme-font:minor-latin;
	mso-bidi-font-family:"Times New Roman";
	mso-bidi-theme-font:minor-bidi;
	mso-fareast-language:EN-US;}
span.SpellE
	{mso-style-name:"";
	mso-spl-e:yes;}
.MsoChpDefault
	{mso-style-type:export-only;
	mso-default-props:yes;
	mso-ascii-font-family:Calibri;
	mso-ascii-theme-font:minor-latin;
	mso-fareast-font-family:Calibri;
	mso-fareast-theme-font:minor-latin;
	mso-hansi-font-family:Calibri;
	mso-hansi-theme-font:minor-latin;
	mso-bidi-font-family:"Times New Roman";
	mso-bidi-theme-font:minor-bidi;
	mso-fareast-language:EN-US;}
.MsoPapDefault
	{mso-style-type:export-only;
	margin-bottom:10.0pt;
	line-height:115%;}
@page WordSection1
	{size:595.3pt 841.9pt;
	margin:72.0pt 72.0pt 72.0pt 72.0pt;
	mso-header-margin:35.4pt;
	mso-footer-margin:35.4pt;
	mso-paper-source:0;}
div.WordSection1
	{page:WordSection1;}
-->
</style>
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



<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width=747 align="right" 
 style='width:560.0pt;margin-left:-53.75pt;border-collapse:collapse;mso-yfti-tbllook:
 1184;mso-padding-alt:0cm 5.4pt 0cm 5.4pt'>
 <tr style='mso-yfti-irow:0;mso-yfti-firstrow:yes;height:12.75pt'>
  <td width=440 nowrap colspan=2 rowspan=2 valign=bottom style='width:330.0pt;
  border:solid windowtext 1.0pt;mso-border-alt:solid windowtext .5pt;
  background:#DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>&nbsp;<o:p></o:p></span></p>
  </td>
  <td width=68 rowspan=2 style='width:51.0pt;border:solid windowtext 1.0pt;
  border-left:none;mso-border-left-alt:solid windowtext .5pt;mso-border-alt:
  solid windowtext .5pt;background:#DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;
  height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>RATE<o:p></o:p></span></p>
  </td>
  <td width=171 colspan=2 style='width:128.0pt;border:solid windowtext 1.0pt;
  border-left:none;mso-border-top-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Score<o:p></o:p></span></p>
  </td>
  <td width=68 rowspan=2 style='width:51.0pt;border:solid windowtext 1.0pt;
  border-left:none;mso-border-left-alt:solid windowtext .5pt;mso-border-alt:
  solid windowtext .5pt;background:#DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;
  height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>PD<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:1;height:12.75pt'>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Max<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Min<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:2;height:12.75pt'>
  <td width=123 rowspan=5 style='width:92.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Standard<o:p></o:p></span></p>
  </td>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Excellent
  very low PD<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>1<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>337<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>304<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>3.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:3;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Very
  Good low PD<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>2<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>304<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>271<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>3.50%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:4;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Good
  existing but still low PD<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>3<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>271<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>238<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>4.50%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:5;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Average
  creditworthiness average PD<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>4<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>238<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>205<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>5.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:6;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>high
  risk but still acceptable PD<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>5<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>205<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>172<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>7.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:7;height:12.75pt'>
  <td width=123 nowrap style='width:92.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Special Mention<o:p></o:p></span></p>
  </td>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Possible
  default, watch list<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>6<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>172<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>139<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>12.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:8;height:12.75pt'>
  <td width=123 rowspan=3 style='width:92.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Non Accruing<o:p></o:p></span></p>
  </td>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Past
  due 30 days<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>7<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>139<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>106<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>20.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:9;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Past
  due 90 days<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>8<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>106<o:p></o:p></span></p>
  </td>
  <td width=85 nowrap valign=bottom style='width:64.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>73<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>27.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:10;height:12.75pt'>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Loan
  called and Collateral repossessed<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>9<o:p></o:p></span></p>
  </td>
  <td width=171 nowrap colspan=2 valign=bottom style='width:128.0pt;border-top:
  none;border-left:solid windowtext 1.0pt;border-bottom:solid windowtext 1.0pt;
  border-right:solid black 1.0pt;mso-border-top-alt:solid windowtext .5pt;
  mso-border-alt:solid windowtext .5pt;mso-border-right-alt:solid black .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span class=SpellE><b><span
  style='font-size:10.0pt;mso-ascii-font-family:Calibri;mso-fareast-font-family:
  "Times New Roman";mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>na</span></b></span><b><span
  style='font-size:10.0pt;mso-ascii-font-family:Calibri;mso-fareast-font-family:
  "Times New Roman";mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'><o:p></o:p></span></b></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>50.00%<o:p></o:p></span></p>
  </td>
 </tr>
 <tr style='mso-yfti-irow:11;mso-yfti-lastrow:yes;height:12.75pt'>
  <td width=123 nowrap style='width:92.0pt;border:solid windowtext 1.0pt;
  border-top:none;mso-border-left-alt:solid windowtext .5pt;mso-border-bottom-alt:
  solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;background:
  #DDD9C3;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span style='font-size:10.0pt;
  mso-ascii-font-family:Calibri;mso-fareast-font-family:"Times New Roman";
  mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>Loss<o:p></o:p></span></p>
  </td>
  <td width=317 nowrap valign=bottom style='width:238.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal style='margin-bottom:0cm;margin-bottom:.0001pt;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>Write-Off<o:p></o:p></span></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border:none;border-bottom:
  solid windowtext 1.0pt;mso-border-bottom-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:20.0pt;mso-char-indent-count:2.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>10<o:p></o:p></span></p>
  </td>
  <td width=171 nowrap colspan=2 valign=bottom style='width:128.0pt;border-top:
  none;border-left:solid windowtext 1.0pt;border-bottom:solid windowtext 1.0pt;
  border-right:solid black 1.0pt;mso-border-top-alt:solid windowtext .5pt;
  mso-border-alt:solid windowtext .5pt;mso-border-right-alt:solid black .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=center style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:center;line-height:normal'><span class=SpellE><b><span
  style='font-size:10.0pt;mso-ascii-font-family:Calibri;mso-fareast-font-family:
  "Times New Roman";mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'>na</span></b></span><b><span
  style='font-size:10.0pt;mso-ascii-font-family:Calibri;mso-fareast-font-family:
  "Times New Roman";mso-hansi-font-family:Calibri;mso-bidi-font-family:"Times New Roman";
  color:black;mso-fareast-language:EN-GB'><o:p></o:p></span></b></p>
  </td>
  <td width=68 nowrap valign=bottom style='width:51.0pt;border-top:none;
  border-left:none;border-bottom:solid windowtext 1.0pt;border-right:solid windowtext 1.0pt;
  mso-border-bottom-alt:solid windowtext .5pt;mso-border-right-alt:solid windowtext .5pt;
  background:#EEECE1;padding:0cm 5.4pt 0cm 5.4pt;height:12.75pt'>
  <p class=MsoNormal align=right style='margin-bottom:0cm;margin-bottom:.0001pt;
  text-align:right;text-indent:10.0pt;mso-char-indent-count:1.0;line-height:
  normal'><span style='font-size:10.0pt;mso-ascii-font-family:Calibri;
  mso-fareast-font-family:"Times New Roman";mso-hansi-font-family:Calibri;
  mso-bidi-font-family:"Times New Roman";color:black;mso-fareast-language:EN-GB'>100.00%<o:p></o:p></span></p>
  </td>
 </tr>
</table>

</form></table>
          </form>
    </table></td></tr>
  </table>
</table>



</table>
</font> 
</BODY>
</HTML>
