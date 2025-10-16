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






//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
//echo  "BBS\ ";
/*
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
$pass="";
$host="localhost"; // Host name 
$user="root"; // Mysql username 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); 
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error());

}
$insert="INSERT INTO Access_Table VALUES('$ip',CURDATE())";
$result=mysqli_query($connect,$insert);
if($result)
{
//echo "done";
}

?>
<link href="file:///X|/complaints/btn.css" rel="stylesheet" type="text/css">
<link href="file:///X|/complaints/btu.css" rel="stylesheet" type="text/css">


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
<form name="search2" method="post" action="status.php">
<?php
$user = 'root';
$pass = '';
$host="localhost"; //Database host.
//$user="root"; //Database username.
//$pass="vfdx0379"; //Database password.
$dbase="creditscoring"; //Database

//echo testing;
//echo $host;
//echo $user;
$omang = $_POST['omang'];
$loan_number=$_POST['loan_number'];

$username = $_POST['username'];
$password = $_POST['password'];
//$date = $_POST['date'];
//echo $cell;


$connect=mysqli_connect($host,$user,$pass,$dbase); 
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$dbase)
or die("Couldn't open $dbase: ".mysqli_error());
}

$sql="select borrower_name,other_names,surname from cust_information where omang_passport_num= $omang";

//echo "<b>The Following Direct Debit Record Has Added</b> <br>";
//mysql_close($connect);
//}//close bracket for else statement

$result=mysqli_query($connect,$sql);

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
<?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
<tr>
<td>
Omang:&nbsp;&nbsp;
</td>

<td>
<?php echo $omang;?>
</td>

</tr>

<tr>
<td>
Loan Number:&nbsp;&nbsp;
</td>

<td>
<?php echo $loan_number;?>
</td>

</tr>
<tr><td>&nbsp;&nbsp;</td></tr>

<tr>
<td>Name:&nbsp;&nbsp;</td>
<td><input type="text" size="30" name="names" value= "<?php echo $rows['borrower_name']." ". $rows['other_names']." ".$rows['surname']; ?>"></td>

<?php
}
?>


<tr>

<td>Status: &nbsp;&nbsp;</td>
<td><select name="status1">
<option value="pre-approved">pre-approved</option>
<option value="pre-disapproved">pre-disapproved</option>
<option value="returned for correction">returned for correction</option>
</select>
</tr>

<tr>
<td>Reasons:&nbsp;&nbsp;</td><td><textarea rows="6" cols="25" name="comment"></textarea>  </td>
</tr>
<tr>
<td>&nbsp;&nbsp;</td>
</tr>
<tr>
<td>&nbsp;&nbsp;</td>
</tr>
<tr>
<td colspan="8" align="center">
<input type="SUBMIT" name="RATE" class="btn" value="SUBMIT"/>  
 </td>
</tr>
<tr>

<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="username"  value="<?php echo $username; ?>"/></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="password"  value="<?php echo $_POST['password']; ?>"/></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="omang" type="hidden" id="counts" value="<?php echo $_POST['omang']; ?>"></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="loan_number" type="hidden" id="counts" value="<?php echo $loan_number; ?>"></td>

</tr>

<?php

mysqli_close($connect);
?>

</table>
</form>
</td>
</tr>
<tr><td>

</td></tr>
</table>
<br>

<a href="search.php"><font color="red" size="2"><strong>BACK</strong></font></a>
<br><br>
<a href="index2.php"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br></body>
</html>



