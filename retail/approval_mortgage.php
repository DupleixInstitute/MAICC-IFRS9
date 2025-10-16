<?php

$username=$_POST['username'];
echo $username;
// Connect to server and select databse.
include("assets/includes/authenticate.php");
include("assets/includes/database_connection.php");

$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 

//insert audit trail into access table
try {
	$insert="INSERT INTO Access_Table VALUES('$ip',CURDATE())";
	$result=(mysqli_query($connect,$insert));
	if($result)
	{
	}
} catch (Exception $e) {
  echo $e->getMessage();
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
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING - Mortgage Loan</font></td><tr>
      </tr>
<table>
<form name="search2" method="post" action="status_mortgage.php">
<?php
$user = 'root';
$pass = 'sefalana2008';
$host="localhost"; //Database host.
$dbase="creditscoring"; //Database

$omang = $_POST['omang'];
$loan_number=$_POST['loan_number'];

$username = $_POST['username'];
$password = $_POST['password'];
//$date = $_POST['date'];
//echo $cell;


$sql="select borrower_name,other_names,surname from cust_information where omang_passport_num= '$omang'";

$result=mysqli_query($connect,$sql,);

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

<!--
<input hidden  name = "password" type = "text" value = "<?php echo $password;?>">
<input hidden  name = "username" type = "text" value = "<?php echo $username;?>">

<a href="search22.php"><font color="red" size="2"><strong>BACK</strong></font></a>
<br><br>
<a href="index2.php"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br></body>
-->
</html>



