<?php


include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;

$omang2 		= $_POST['omang2'];
$loan_number2 	= $_POST['loan_number2'];

$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;



?>
<link href="btn.css" rel="stylesheet" type="text/css">
<link href="btu.css" rel="stylesheet" type="text/css">


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
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING-Final Approval</font></td><tr>
      </tr>
<table>
<form name="search2" method="post" action="status2.php">
<?php

$password = $_POST['password'];


$selectO="SELECT omang_passport_num FROM cust_information where omang_passport_num='$omang2' and loan_number=$loan_number2";
$result1=(mysqli_query($connect,$selectO));
if($result1)
{
}

$mycounts=0;
while($rows1=mysqli_fetch_array($result1)){
   $mycounts++; 
}


$sql="select borrower_name,other_names,surname from cust_information where omang_passport_num= '$omang2' and loan_number=$loan_number2";

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
	?>
	<tr>
	<td>
	Omang:&nbsp;&nbsp;
	</td>

	<td>
	<?php echo $omang2;?>
	</td>

	</tr>
	<tr>
	<td>
	Loan Number:&nbsp;&nbsp;
	</td>

	<td>
	<?php echo $loan_number2;?>
	</td>

	</tr>
	<tr><td>&nbsp;&nbsp;</td></tr>
	<tr>
	<td>Name:&nbsp;&nbsp;</td>
	<td><input type="text" name="names" value= "<?php echo $rows['borrower_name']." ". $rows['other_names']." ".$rows['surname']; ?>"></td>

	<?php
}
	?>


	<tr>

	<td>Status: &nbsp;&nbsp;</td>
	<td><select name="status1">
	<option value="approved">approved</option>
	<option value="not approved">not approved</option>
	<option value="returned for correction">returned for correction</option>
	</select>
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
	<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="omang" type="hidden" id="counts" value="<?php echo $omang2 ?>"></td>
	<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="loan" type="hidden" id="counts" value="<?php echo $loan_number2; ?>"></td>

	<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="username"  value="<?php echo $username; ?>"/></td>
	<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/></td>

	</tr>

	<?php
 // end while
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
<a href="/index2.php"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br></body>
</html>



