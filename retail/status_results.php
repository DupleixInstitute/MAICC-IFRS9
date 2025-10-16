
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php

$pass     = $_POST['password'];
include  ("assets/includes/authenticate.php");        // Connect to server and select databse.
include  ("assets/includes/database_connection.php"); //Open database connection;
$ip       = gethostbyname($_SERVER['REMOTE_ADDR']); 

$status   = $_POST['status'];
$day      = $_POST['day2'];
$month    = $_POST['month2'];
$year     = $_POST['year2'];

$day2     = $_POST['day22'];
$month2   = $_POST['month22'];
$year2    = $_POST['year22'];

if (isset($day) || isset($day2) && $status=="approved") {

$sql1 = "SELECT 
                   cust_information.username           as username,
		           cust_information.omang_passport_num as omang,
		           cust_information.loan_number        as loan,
		           approved.reason                     as reason,
		           approved.other_reason               as other ,
		           approved.status                     as status,
		           approved.date                       as date1 
		 
		 FROM      cust_information, approved 
		 
		 WHERE     cust_information.omang_passport_num = approved.omang  AND 
		           cast(approved.date as date)         = $year$month$day AND 
			       approved.status                     = '$status' 
			   
		 ORDER BY  approved.date;";
}

if (isset($day) || isset($day2) && $status=="not approved") {

$sql1="SELECT 
                   cust_information.username           as username,
		           cust_information.omang_passport_num as omang,
		           cust_information.loan_number        as loan,
		           approved.reason                     as reason,
		           approved.other_reason               as other ,
		           approved.status                     as status,
		           approved.date                       as date1 
	   
	   FROM        cust_information, approved 
	   WHERE       cust_information.omang_passport_num = approved.omang  AND 
	               cast(approved.date as date)         = $year$month$day AND 
			       approved.status                     = '$status' 
	   
	   ORDER BY approved.date;";
}


if (isset($day) || isset($day2) && $status=="returned for correction") 
{
   $sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,returned.reason as reason,returned.other_reason as other ,returned.status as status,returned.date as date1 from cust_information,returned where cust_information.omang_passport_num=returned.omang and cast(returned.date as date)=$year$month$day AND returned.status='$status' order by returned.date;";
}

if(empty($day) && empty($day2) && $status =="approved" || $status=="not approved") {
   $sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,approved.reason as reason,approved.other_reason as other ,approved.status as status,approved.date as date1 from cust_information,approved where cust_information.omang_passport_num=approved.omang and approved.status='$status' order by approved.date;";
}

if(empty($day) && empty($day2) && $status =="returned for correction") {
   $sql1="select cust_information.username as username,cust_information.omang_passport_num as omang,cust_information.loan_number as loan,returned.reason as reason,returned.other_reason as other ,returned.status as status,returned.date as date1 from cust_information,returned where cust_information.omang_passport_num=returned.omang order by returned.date;";
}

$result=mysqli_query($connect,$sql1);  
if (!$result){
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
while($rows=mysqli_fetch_array($result)){
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