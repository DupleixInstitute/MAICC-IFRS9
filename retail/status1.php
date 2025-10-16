<?php
function calc_payment($pv, $payno, $int, $accuracy) 
{ 
// check that required values have been supplied 
if (empty($pv)) { 
   echo "<p class='error'>a value for PRINCIPAL is required</p>"; 
   exit; 
} // if 
if (empty($payno)) { 
   echo "<p class='error'>a value for NUMBER of PAYMENTS is required</p>"; 
   exit; 
} // if 
if (empty($int)) { 
   echo "<p class='error'>a value for INTEREST RATE is required</p>"; 
   exit; 
} // if 

// now do the calculation using this formula: 

//****************************************** 
//            INT * ((1 + INT) ** PAYNO) 
// PMT = PV * -------------------------- 
//             ((1 + INT) ** PAYNO) - 1 
//****************************************** 

$int    = $int / 100;    // convert to a percentage 
$value1 = $int * pow((1 + $int), $payno); 
$value2 = pow((1 + $int), $payno) - 1; 
$pmt    = $pv * ($value1 / $value2); 
// $accuracy specifies the number of decimal places required in the result 
$pmt    = number_format($pmt, $accuracy, ".", ""); 

return $pmt; 

} // calc_payment ==================================================================== 
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
$connect=mysql_connect($host,$username,$password); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());
}
?>
<link href="creditscore/btu.css" rel="stylesheet" type="text/css">
<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" bgcolor="#ECF8FF">
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>
</tr>
<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="6" face="Arial Narrow">CREDIT SCORING - RECOMMENDATION</font></td><tr>
      </tr>
<table>
<font class="btn" color="#333333" size="2"><strong>
<?php
$username = $_POST['username'];
$password = $_POST['password'];
$host="localhost"; //Database host.
//$user="root"; //Database username.
//$pass="vfdx0379"; //Database password.
$dbase="creditscoring"; //Database

//echo testing;
//echo $host;
//echo $user;
$status = $_POST['status'];
$other_reason = $_POST['other_reason'];
$reason = $_POST['reason'];
$omang = $_POST['omang'];
$mydate= date('Y-m-d');
$mytime=date("H.i");
$loan_number=$_POST['loan_number'];
echo  $loan_number;
/*
echo $reason;
echo $omang;
echo $status;
*/
$connect=mysql_connect($host,$username,$password); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($dbase, $connect)
or die("Couldn't open $dbase: ".mysql_error());
}
$sql2="insert into approvals values ('$omang','$loan_number','$status','$username','$reason','$other_reason', NOW())";
$result2=mysql_query($sql2,$connect);
if(!$result2)
{
 die("UPDATE FAILED: ".mysql_error());
}
else
{
echo "</BR>"."</BR>"."</BR>";
echo "Customer Idetification Number: $omang"."</BR>"."</BR>";
echo  "Recommendation: $status"."</BR>"."</BR>";
echo  "Reason: $reason"."</BR>"."</BR>";
echo  "Reason: $other_reason"."</BR>"."</BR>";
echo  "Recommended By: $username"."</BR>"."</BR>";
echo  "Date: $mydate $mytime"."</BR>"."</BR>";


echo "SUCCESSFULLY UPDATED"."</BR>"."</BR>"."</BR>"."</BR>";


echo "first level approval Signature:   ________________________"."</BR>"."</BR>"."</BR>";

echo "Second level approval Signature: ________________________"."</BR>"."</BR>"."</BR>";

echo " notes  ________________________"."</BR>";   
echo "       "." ________________________"."</BR>";   
echo "       "."________________________"."</BR>";   
echo "       "."________________________"."</BR>";   
echo "       "."________________________"."</BR>";   
}
?>
</strong>

</font>
<table width="400" border="0" cellspacing="1" cellpadding="0">
<tr>
<tr>
</tr>
<?php
//$mycount=0;
//while($rows=mysql_fetch_array($result)){
//$mycount++; 
//echo $rows['bbsAccount'];
?>

<?php
///}
?>
<tr>

<tr>

</tr>
<tr>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="omang" type="hidden" id="counts" value="<?php echo $omang ?>"></td>

</tr>

<?php

mysql_close();
?>

</table>
</form>
</td>
</tr>
</table>
<br>
<br>
<br>
<br>
</body>
</html>



