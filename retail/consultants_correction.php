
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
$pass               = $_POST['password'];

$username           = $_POST['username'];
$password           = $_POST['password'];

include  ("assets/includes/authenticate.php");        // Connect to server and select databse.
include  ("assets/includes/database_connection.php"); //Open database connection;

$ip                 = gethostbyname($_SERVER['REMOTE_ADDR']); 

$day                = $_POST['day2'];
$month              = $_POST['month2'];
$year               = $_POST['year2'];

$day2               = $_POST['day22'];
$month2             = $_POST['month22'];
$year2              = $_POST['year22'];

$omang_passport_num = $_POST['omang'];



if (isset($day)){
//echo $day;

$sql1="select count(*) as count,cust_information.username as consultant,returned.status as status from cust_information,returned where cust_information.omang_passport_num=returned.omang and cast(returned.date as date)=$year$month$day group by cust_information.username";
}
if (isset($day2)){
$sql1="select count(*) as count,cust_information.username as consultant,returned.status as status from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction' and cast(returned.date as date)=$year2$month2$day2 group by cust_information.username";
}

if (isset($day) && isset($day2)){
$sql1="select count(*) as count,cust_information.username as consultant,returned.status as status from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction' and cast(returned.date as date) between $year$month$day and $year2$month2$day2 group by cust_information.username";
}
if(empty($day) && empty($day2)){
  $sql1  = "select count(*) as count,cust_information.username as consultant,returned.status as status from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction' group by cust_information.username";
}

$result  = mysqli_query($connect,$sql1);
  
if (!$result)
{
    die('Error2: ' . mysqli_error());
}
 
if (isset($day)) {
  $sql3="select * from cust_information,returned where cust_information.omang_passport_num=returned.omang and cast(returned.date as date)=$year$month$day ";
}
if (isset($day2)) {
  $sql3="select * from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction' and cast(returned.date as date)=$year2$month2$day2";
}
if(isset($day) && isset($day2)) {
  $sql3="select * from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction' and cast(returned.date as date) between $year$month$day and $year2$month2$day2";
}

if(empty($day) && empty($day2)) {
  $sql3="select * from cust_information,returned where cust_information.omang_passport_num=returned.omang and  returned.status='returned for correction'";
}

$result3=mysqli_query($connect,$sql3);
  
if (!$result3)
{
   die('Error2: ' . mysqli_error());
}
$count3 = mysqli_num_rows($result3); 

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
   <tr><td><font color="#000066" size="3" class="btn"><strong>FILES RETURNED FOR CORRECTIONS </strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="500" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="200" bgcolor="#000066"><strong><font color="#FFFFFF">LOCATION</font></strong></td>
                  <td width="100" bgcolor="#000066"><strong><font color="#FFFFFF">TOTAL</font></strong></td>
				       <td width="400" bgcolor="#000066"><strong><font color="#FFFFFF">STATUS</font></strong></td>
					     
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
  $mycount++; 
?>
    <tr> 
       <td width="200" bgcolor="#FFFFFF" ><?php echo $rows['consultant']; ?></td>
       <td width="100" bgcolor="#FFFFFF" ><?php echo $rows['count']; ?></td>
	   <td width="400" bgcolor="#FFFFFF" ><?php echo $rows['status']; ?></td>		
<?php
}
?></tr>
         <tr>
			  <td bgcolor="#FFFFFF" width="220"><strong>TOTAL</strong></td>
               <td bgcolor="#FFFFFF" width="220"><strong><?php echo $count3;?></strong></td>
			                 <td bgcolor="#FFFFFF" width="220"><strong><?php echo $count34;?></strong></td>

		  </tr>     
          <tr> </tr>
     </table>
     </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index2.php';"><br><br>

</form>