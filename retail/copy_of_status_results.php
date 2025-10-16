
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
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

$status=$_POST['status'];
$day=$_POST['day2'];
$month=$_POST['month2'];
$year=$_POST['year2'];

$day2=$_POST['day22'];
$month2=$_POST['month22'];
$year2=$_POST['year22'];

echo "DATE1 ".$day.$month.$year."</BR>";
echo "DATE2 ".$day2.$month2.$year2;
echo "STATUS ".$status;

$connect=mysql_connect($host,$username,$password); 
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());


}

if (isset($day) && $status!="All") {

$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date)=$year$month$day AND approvals.status='$status' order by approvals.date;";
}

if (isset($day2) && $status!="All") {

$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date)=$year2$month2$day2 AND approvals.status='$status' order by approvals.date;";
}


if(isset($day) && $status=="All")
{
$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date)=$year$month$day order by approvals.date;";

}

if(isset($day2) && $status=="All")
{
$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date)=$year2$month2$day2 order by approvals.date;";

}


if(isset($day) && isset($day2) && $status=="All")
{
$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date) between $year$month$day and $year2$month2$day2  order by approvals.date;";

}

if(isset($day) && isset($day2) && $status!="All") {

$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and cast(approvals.date as date)between $year$month$day and $year2$month2$day2 AND approvals.status='$status' order by approvals.date;";
}



if(empty($day) && empty($day2) && $status!="All") {

$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and approvals.status='$status' order by approvals.date;";
}

if(empty($day) && empty($day2) && $status =="All") {

$sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approvals.reason as reason,approvals.other_reason as other ,approvals.status as status,approvals.date as date1 from cust_information,approvals where cust_information.omang_passport_num=approvals.omang order by approvals.date;";
}


$result=mysql_query($sql1,$connect);
  
if (!$result)
  {
  die('Error2: ' . mysql_error());
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
   <table class="s_text" >
   <tr><td><font color="#000066" size="4" class="btn"><strong>
   
   <?PHP if($status=="not approved")
   {
   echo "FILES THAT ARE NOT APPROVED";
   }
   else if
($status=="All")
   {
   echo "STATUS RESULTS FOR ALL FILES";
   }
   else
   {   
   echo "FILES THAT HAVE BEEN ".$status;
   }
   ?> </strong></strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="1000" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">USERNAME</font></strong></td>
                  <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">OMANG/PASSPORT</font></strong></td>
				  <td width="800" bgcolor="#000066"><strong><font color="#FFFFFF">LOAN #</font></strong></td>
                  <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">REASON 1</font></strong></td>
				  <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">REASONS 2</font></strong></td>
				   <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">STATUS</font></strong></td>
                   <td width="600" bgcolor="#000066"><strong><font color="#FFFFFF">DATE</font></strong></td>
              </tr>
              <?php
$mycount=0;
while($rows=mysql_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['username'];?></td>
                <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['omang']; ?></td>
				 <td width="800" bgcolor="#FFFFFF" ><?php echo $rows['loan']; ?></td>
                <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['reason']; ?></td>
				 <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['other']; ?></td>
				  <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['status']; ?></td>
				<td width="600" bgcolor="#FFFFFF" ><?php echo $rows['date1']; ?></td>
                <?php
}
?>
              </tr>
              <tr> </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='status_report.php'"><br><br>

</form>