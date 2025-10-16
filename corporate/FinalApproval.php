<html>
<head>
<?php

$username=$_POST['username'];

$bbs='BBS\\';

$new=$bbs.$username;
$ldappass=$_POST['password'];
//echo $new.$ldappass;

/**
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
**/
     //echo "<meta http-equiv=\"refresh\" content=\"0;URL=RetailIndex.php\">";
		

   // }
//
//}

$application_ref    = $_POST['application_ref'];
$company_reg_no     = $_POST['company_reg_no'];

$loan_number        = $_POST['loan_number'];
$full_customer_name = $_POST['full_customer_name'];

$pass="";
$host="localhost"; // Host name 
$user="root"; // Mysql username 
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name )
or die("Couldn't open $dbase: ".mysqli_error($connect));

}


?>
<link href="btn.css" rel="stylesheet" type="text/css">
<link href="btu.css" rel="stylesheet" type="text/css">

</head>
<body>

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
<form name="search2" method="post" action="status2.php">
<?php
$host="localhost"; //Database host.
$dbase="corporatescoring"; //Database
$username = $_POST['username'];
$password = $_POST['password'];

$connect=mysqli_connect($host,"root","",$dbase); 
//echo $pass;
if (!$connect) {
   mysqli_close($connect); 
   echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
   mysqli_select_db( $connect,$dbase)
   or die("Couldn't open $dbase: ".mysqli_error($connect));
/**
$selectO="SELECT company_reg_no FROM loan_data where company_reg_no ='$company_reg_no' and loan_number=$loan_number";
$result1=mysqli_query($connect,$selectO);

if(!$result1)
{
  echo "failed first".mysqli_error($connect);
}

$mycounts=0;
while($rows1=mysqli_fetch_array($result1)){
$mycounts++; 

}
**/

$sql="select legal_name,customer_type,trading_name from loan_data where company_reg_no='$company_reg_no' and loan_number=$loan_number";
$result=mysqli_query($connect,$sql);

if(!$result)
{
   echo "failed".mysqli_error($connect);
}
?>


<table width="600" border="0" cellspacing="1" cellpadding="0" 
       style = "font-size:12;font-family:bolder 84% 'trebuchet ms', helvetica, sans-serif;
	            font-weight:normal; line-height:2">

<?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
<tr> <td width ="70%">Loan Application Reference Number:</td>
     <td><?php echo $application_ref;?></td>
</tr>
<tr> <td>Company Registration Number:</td>
     <td><?php echo $company_reg_no;?></td>
</tr>
<tr> <td>Loan Number:</td>
     <td><?php echo $loan_number;?></td>
</tr>
<tr> <td>Name:</td>
     <td><input type="text" name="names" value= "<?php echo $full_customer_name; ?>"></td>
</tr>
<?php
}
?>
<tr> <td>Status: &nbsp;&nbsp;</td>
     <td><select name="status1">
            <option value="approved">approved</option>
            <option value="not approved">not approved</option>
            <option value="returned for correction">returned for correction</option>
         </select>
	 </td>
</tr>
<tr>

<td>Reason:&nbsp;&nbsp;</td>
<td><select name="reason1">
<option value=""></option>
<option value="location">location</option>
<option value="collateral">collateral</option>
<option value="poor credit rating">poor credit rating</option>
</select>
</tr>
<tr>
<td>Other Reasons:&nbsp;&nbsp;</td><td><textarea rows="6" cols="25" name="reason"></textarea>  </td>
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
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="application_ref" type="hidden" id="counts" value="<?php echo $application_ref ?>"></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="company_reg_no" type="hidden" id="counts" value="<?php echo $company_reg_no ?>"></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="loan" type="hidden" id="counts" value="<?php echo $loan_number; ?>"></td>

<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="username"  value="<?php echo $username; ?>"/></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/></td>

</tr>

<?php
}
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

<a href="summary_results.php"><font color="red" size="2"><strong>BACK</strong></font></a>
<br><br>
<a href="index.php"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br></body>

</body>
</html>



