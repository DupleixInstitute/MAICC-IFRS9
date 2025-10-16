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



$con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysql_select_db("credit", $con);

$result = mysql_query("SELECT * FROM NEWALLMORT");

while($row = mysql_fetch_array($result))
  {
  


$cif=$row['CIF'];
echo $cif;
$oldscore=$row['SCORE'];
//echo 'pppp'; echo $loan_amount;echo 'pppp';
//echo 'ttmm'; echo $loan_amount;
$account=$row['ACCOUNT'];
$contract=$row['CONTRACT'];
$name=$row['NAME'];
$loanandinsurance=$row['MONTHLYREPAY'];
$rate_type=$row['RATE_TYPE_REQUESTED'];
$loan_amount=$row['OUTSTANDING'];
$rate_type=$row['RATETYPE'];
$open_market_value=$row['OPENMARKET'];
$salary=$row['salary'];
$maturityinmonths=$row['loan_maturity'];
//$loanandinsurance=$_POST['loanandinsurance'];
 
 
 echo $open_market_value; echo 'thato';






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




<?PHP
$affordability_ratio=$intall/$salary * 100;
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
//SO WE CAN ADD IT USINGD ATA FROM ALL MORT

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
case ($rate_type=="VARIABLE");
       $rate_type_score=200;
		break;
}
//SO WE CAN ADD IT USINGD ATA FROM ALL MORT

$w_rate_type_score=$rate_type_score*0.02;



//---------------EDUCATION LEVEL SCORE-----------------------------------------------------------------------

$loan_maturity=$maturityinmonths/12;

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
//SO WE CAN ADD IT USINGD ATA FROM ALL MORT
$w_loan_maturity_score=$loan_maturity_score * 0.01;
		   
$oldscore2=$w_loan_maturity_score+$w_affordability_ratio_score+$w_rate_type_score;

$score=$oldscore+$oldscore2;


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
 CIF,pd,sd,acc,name,
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
'$pdefault', '$contract','$account','$name',
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



if (!mysql_query($sql3,$con))
  {
  die('Error3Custcreditrating: ' . mysql_error());
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
