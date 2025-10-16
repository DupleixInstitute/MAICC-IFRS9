
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
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password=""; // Mysql password 
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;
$day=$_POST['day2'];
$month=$_POST['month2'];
$year=$_POST['year2'];

$day2=$_POST['day22'];
$month2=$_POST['month22'];
$year2=$_POST['year22'];

$company_reg_no=$_POST['company_reg_no'];
$connect=mysqli_connect($host,$username,$password,$db_name); 
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error($connect));

}

if (isset($day)){
$sql1="select count(*) as count,
               loan_data.location_of_acquired_real_estate as location,
			   approvals.status as status 
		  from loan_data,approvals 
		 where loan_data.company_reg_no=approvals.company_reg_no and  
		       approvals.status='approved' and 
			   cast(approvals.date as date)=$year$month$day 
	 group by  loan_data.location_of_acquired_real_estate";

}
if (isset($day2)){
$sql1="select count(*) as count,
              loan_data.location_of_acquired_real_estate as location,
			  approvals.status as status 
	     from loan_data,approvals 
		where loan_data.oompany_reg_no=approvals.company_reg_no and  
		      approvals.status='approved' and 
			  cast(approvals.date as date)=$year2$month2$day2 
	group by  loan_data.location_of_acquired_real_estate";
}

if (isset($day) && isset($day2)){
$sql1="select count(*) as count,
              loan_data.location_of_acquired_real_estate as location,
			  approvals.status as status 
		 from loan_data,approvals 
		where loan_data.company_reg_no=approvals.company_reg_no and  
		      approvals.status='approved' and 
			  cast(approvals.date as date) between $year$month$day and $year2$month2$day2 
	 group by loan_data.location_of_acquired_real_estate";
}


//IF BOTH DAYS ARE DISABLED, SHOW ALL APPROVED LOCATIONS

if (empty($day) && empty($day2)){
$sql1="selects count(*) as count,
              loan_data.location_of_acquired_real_estate as location,
			  approvals.status as status 
		 from loan_data,approvals 
		where loan_data.company_reg_no=approvals.company_reg_no and  
		      approvals.status='approved' 
	 group by loan_data.location_of_acquired_real_estate";
}

$result=mysqli_query($connect,$sql1);
  
if (!$result)
  {
  die('Error2: ' . mysqli_error($connect));
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>LOCATIONS APPROVED</strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="300" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="200" bgcolor="#000066"><strong><font color="#FFFFFF">LOCATION</font></strong></td>
                  <td width="200" bgcolor="#000066"><strong><font color="#FFFFFF">TOTAL</font></strong></td>
				       <td width="200" bgcolor="#000066"><strong><font color="#FFFFFF">STATUS</font></strong></td>
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="200" bgcolor="#FFFFFF" ><?php echo $rows['location']; ?></td>
                <td width="200" bgcolor="#FFFFFF" ><?php echo $rows['count']; ?></td>
				 <td width="200" bgcolor="#FFFFFF" ><?php echo $rows['status']; ?></td>
                <?php
}
?>
              </tr>
              <tr> </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='locations.php';"><br><br>

</form>