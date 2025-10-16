
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php

include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;

$ip     = gethostbyname($_SERVER['REMOTE_ADDR']); 

//$status = $_POST['status'];
$day    = $_POST['day2'];
$month  = $_POST['month2'];
$year   = $_POST['year2'];

$day2   = $_POST['day22'];
$month2 = $_POST['month22'];
$year2  = $_POST['year22'];

if (isset($day)) {

$sql1="select count(*) as count,cust_information.gender as gender from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and approvals.status='approved' and cast(approvals.date as date)=$year$month$day group by cust_information.gender;";
}

if (isset($day2)) {

$sql1="select count(*) as count,cust_information.gender as gender from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and approvals.status='approved' and cast(approvals.date as date)=$year2$month2$day2 group by cust_information.gender;";
}


if(isset($day) && isset($day2))
{
$sql1="select count(*) as count,cust_information.gender as gender from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and approvals.status='approved' and cast(approvals.date as date) between $year$month$day and $year2$month2$day2 group by cust_information.gender;";

}

if(empty($day) && empty($day2)){ 
$sql1="select count(*) as count,cust_information.gender as gender from cust_information,approvals where cust_information.omang_passport_num=approvals.omang and approvals.status='approved' group by cust_information.gender;";
}
$result=mysqli_query($connect,$sql1);
  
if (!$result)
{
  die('Error2: ' . mysqli_error());
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>GENDER APPROVALS COUNTS
   
   </strong></strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="300" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="150" bgcolor="#000066"><strong><font color="#FFFFFF">RATE TYPE</font></strong></td>
                  <td width="150" bgcolor="#000066"><strong><font color="#FFFFFF">COUNT</font></strong></td>
				  
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="150" bgcolor="#FFFFFF" ><?php echo $rows['gender'];?></td>
                <td width="150" bgcolor="#FFFFFF" ><?php echo $rows['count']; ?></td>
				
                <?php
}
?>
              </tr>
              <tr> </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='status_report.php'"><br><br>

</form>