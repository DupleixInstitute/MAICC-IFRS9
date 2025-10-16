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
//echo $ip;


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
$insert="INSERT INTO Access_Table VALUES('$ip',CURDATE())";
$result=(mysql_query($insert));
if($result)
{
//echo "done";
}

?>

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
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING</font></td><tr>
      </tr>
<table>
<form name="index2.php" method="post" action="status.php">
<?php
$user = 'root';
$pass = 'sefalana2008';
$host="localhost"; //Database host.
//$user="root"; //Database username.
//$pass="vfdx0379"; //Database password.
$dbase="creditscoring"; //Database

//echo testing;
//echo $host;
//echo $user;
$omang = $_POST['omang'];

//$extacc = $_POST['extacc'];
//$amount = $_POST['amount'];
//$date = $_POST['date'];
//echo $cell;
echo $omang;

$connect=mysql_connect($host,$user,$pass); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($dbase, $connect)
or die("Couldn't open $dbase: ".mysql_error());


}

$sql="select borrower_name,other_names,surname from cust_information where omang_passport_num= $omang";

//echo "<b>The Following Direct Debit Record Has Added</b> <br>";
//mysql_close($connect);
//}//close bracket for else statement

$result=mysql_query($sql,$connect);

if(!$result)
{
echo "failed";
}
?>


<table width="400" border="0" cellspacing="1" cellpadding="0">
<tr>
<td>
<table align="right">
<tr>
<td>&nbsp;</td>
</tr>
<tr>


</tr>

<tr>
<td>&nbsp;&nbsp;</td>
</tr>
<table width="60%" align="center">
<tr></tr>
<tr></tr>
<tr></tr>
<tr ><td colspan="3"><font color="red"  size="4"><strong>Login Details</strong></font></td></tr>
<tr ><td colspan="3"><font color="red"  size="2"><strong>Temp passwords are mortgage. Please change at your nearest convinience.</strong></font></td></tr>
<tr><td>Username:</td> <td></td><td><input type="text" name="username"  /></td></tr>
<tr></tr>
<tr></tr>
<tr><td>Password:</td> <td></td><td><input type="password" size="20" name="passwd"  /></td></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr><td> <input type="submit" name="login" value="Login" /></td> </tr>

</table>
<tr>
<td>&nbsp;&nbsp;</td>
</tr><tr>
<td>&nbsp;&nbsp;</td>
</tr>
<tr>
<td colspan="8" align="center">
 </td>
</tr>
<tr>


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



