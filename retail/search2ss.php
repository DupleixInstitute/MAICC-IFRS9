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
<form name="search2" method="post" action="status1.php">
<?php
//$user = 'root';
//$pass = 'sefalana2008';
$host="localhost"; //Database host.
//$user="root"; //Database username.
//$pass="vfdx0379"; //Database password.
$dbase="creditscoring"; //Database
$username = $_POST['username'];
$password = $_POST['password'];
//echo testing;
//echo $host;
//echo $user;
$omang2 = $_POST['omang2'];
$loan_number2 = $_POST['loan_number2'];
echo $loan_number2; echo "molly";
echo $omang2;
//$extacc = $_POST['extacc'];
//$amount = $_POST['amount'];
//$date = $_POST['date'];
//echo $cell;

$connect=mysql_connect($host,$username,$password); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($dbase, $connect)
or die("Couldn't open $dbase: ".mysql_error());


$sql="select borrower_name,other_names,surname from cust_information where omang_passport_num='$omang2' and loan_number= $loan_number2";

//echo "<b>The Following Direct Debit Record Has Added</b> <br>";
//mysql_close($connect);
//}//close bracket for else statement

$result=mysql_query($sql,$connect);

if(!$result)
{
echo "failed";
}
?>

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
while($rows=mysql_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
<tr>
<td>
Omang:&nbsp;&nbsp;
</td>

<td>
<?php echo $omang2;?>
</td>
<tr>
<tr>
<td>Loan Number&nbsp;</td>
<td><?php echo $loan_number2; ?></td>
</tr>
<tr><td>&nbsp;&nbsp;</td></tr>
<tr>
<td>Name:&nbsp;&nbsp;</td>
<td><input type="text" name="names" value= "<?php echo $rows['borrower_name']." ". $rows['other_names']." ".$rows['surname']; ?>"></td>

<?php
}
?>

</tr>
<tr>

<td>Status: &nbsp;&nbsp;</td>
<td><select name="status">
<option value="approved">approved</option>
<option value="not approved">not approved</option>
<option value="returned for correction">returned for correction</option>
</select>
</tr>
<tr>

<td>Reason:&nbsp;&nbsp;</td>
<td><select name="reason">
<option value=""></option>
<option value="location">location</option>
<option value="collateral">collateral</option>
<option value="poor credit rating">poor credit rating</option>
</select>
</tr>
<tr>
<td>Other Reasons:&nbsp;&nbsp;</td><td><textarea rows="6" cols="25" name="other_reason"></textarea>  </td>
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
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="omang" type="hidden" id="counts" value="<?php echo $_POST['omang'] ?>"></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/></td>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/></td>

</tr>

<?php
}
mysql_close();
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
<a href="index2.php"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br>

</body>
</html>



