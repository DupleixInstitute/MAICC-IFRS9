<?php

include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;


$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;




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


$status1 			= 	$_POST['status1'];
$reason 			= 	$_POST['reason1'];
$reason1 			= 	$_POST['reason'];
$omang 				= 	$_POST['omang'];
$mydate				= 	date('Y-m-d');
$mytime				=	date("H.i");
$loan_number		=	$_POST['loan'];

if ($status1=="approved")
{
	$sql2	="insert into approved 
	         (omang, loan_number,status,userid,reason,other_reason,date)
			  values 
			 ('$omang','$loan_number','$status1','$username','$reason','$reason1', NOW())
			 
			 ON DUPLICATE KEY UPDATE
                omang 		 = '$omang',
				loan_number  = '$loan_number',
			    status       = '$status1',
				userid       = '$username',
				reason       = '$reason1',
				other_reason = '$reason',
				date         = NOW()
			 ";
			 
    $update_sql	="UPDATE cust_information 
	              SET status = 'Final-Approved'
                  WHERE	omang_passport_num = '".$omang."' and loan_number = '".$loan_number."'";
    
}

if ($status1=="returned for correction")
{
    $update_sql	="insert into returned values 
	         (omang, loan_number,status,userid,reason,other_reason,date)
			  values 
			 ('$omang','$loan_number','$status1','$username','$reason','$reason1', NOW())
			 ON DUPLICATE KEY UPDATE
                omang 		 = '$omang',
				loan_number  = '$loan_number',
			    status       = '$status1',
				userid       = '$username',
				reason       = '$reason',
				other_reason = '$reason1',
				date         = NOW()
			 ";

    $update_sql	="UPDATE cust_information 
	             SET status = 'Final-Returned'
              WHERE	omang_passport_num = '".$omang."' and loan_number = '$loan_number'";
}
if ($status1=="not approved")
{
    $sql2	="insert into approved 
	         (omang, loan_number,status,userid,reason,other_reason,date)
			  values 
			 ('$omang','$loan_number','$status1','$username','$reason','$reason1', NOW())
			 
			 ON DUPLICATE KEY UPDATE
                omang 		 = '$omang',
				loan_number  = '$loan_number',
			    status       = '$status1',
				userid       = '$username',
				reason       = '$reason',
				other_reason = '$reason1',
				date         = NOW()
			 ";
    $update_sql	="UPDATE cust_information 
	             SET status = 'Final-Disapproved'
              WHERE	omang_passport_num = '".$omang."' and loan_number = '$loan_number'";
}


$result2=mysqli_query($connect,$sql2);
$result3=mysqli_query($connect,$update_sql);

if(!$result2 or !$result3)
{
    die("UPDATE FAILED: ".mysqli_error());
}
    else
{
	
echo "</BR>"."</BR>"."</BR>";
echo  "Customer Idetification Number: $omang"."</BR>"."</BR>";
echo  "Recommendation: $status1"."</BR>"."</BR>";
echo  "Reason: $reason1"."</BR>"."</BR>";
echo  "Recommended By: $username"."</BR>"."</BR>";
echo  "Date: $mydate $mytime"."</BR>"."</BR>";


echo "SUCCESSFULLY UPDATED"."</BR>"."</BR>"."</BR>"."</BR>";


echo "First level approval Signature:   ________________________"."</BR>"."</BR>"."</BR>";

echo "Second level approval Signature: ________________________"."</BR>"."</BR>"."</BR>";

echo " notes "."______________________________________________________________"."</BR>";   
echo "       "."______________________________________________________________"."</BR>";   
echo "       "."______________________________________________________________"."</BR>";   
echo "       "."______________________________________________________________"."</BR>";   
echo "       "."______________________________________________________________"."</BR>";   


}

?>
</strong>

</font>
<table width="400" border="0" cellspacing="1" cellpadding="0">
<tr>


<tr>


</tr>
<?php

?>

<?php

?>
<tr>

<tr>

</tr>
<tr>
<td colspan="8" align="center" bgcolor="#FFFFFF"><input name="omang" type="hidden" id="counts" value="<?php echo $omang ?>"></td>

</tr>

<?php

mysqli_close($connect);
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



