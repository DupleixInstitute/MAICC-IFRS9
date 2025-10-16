
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">



</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php
error_reporting(0);
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

include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;
// Connect to server and select databse.
$ip  = gethostbyname($_SERVER['REMOTE_ADDR']); 


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
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text"> 
          <td>
        <p></br> 
        <form ACTION="summary_results.php" name="search22" method="post">
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">
<tr>
                <td><strong><em><font color="#FF9900" size="1">&nbsp;&nbsp;&nbsp;&nbsp;Enter customer's Omang/passport number below</font></em></td>
              </tr>
<tr><td>&nbsp;&nbsp;&nbsp;Omang/Pasport Number :&nbsp;&nbsp;
<input name="omang" type="text" >
</td>
<td>&nbsp;&nbsp;&nbsp;Loan Number</td><td>
<SELECT NAME="loan_number">
<option value=""> </option>
<option value="1">1 </option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>
<option value="9">9</option>
<option value="10">10</option>
</SELECT></td>


</tr>

<tr><td>&nbsp;  </td></tr>
<tr>
<td align="right">
<input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/>
<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
<input type="SUBMIT" name="RATE"  class="btn" value="Submit"/>  


</tr>

</form>



</td></tr>
</table>
</td>
</tr>    
 </table> 
</table>



</table>
</font>
</BODY>
</HTML>