
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

$status=$_POST['status'];
$day=$_POST['day2'];
$month=$_POST['month2'];
$year=$_POST['year2'];

$day2=$_POST['day22'];
$month2=$_POST['month22'];
$year2=$_POST['year22'];

//echo "DATE1 ".$day.$month.$year."</BR>";
//echo "DATE2 ".$day2.$month2.$year2;
//echo "STATUS ".$status;

$connect=mysqli_connect($host,$username,$password,$db_name); 
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error($connect));
}

if (isset($day)) {

$sql1="select count(*) as count,
              loan_data.industrial_sector as industrial_sector 
		 from loan_data 
		where cast(creation as date)=$year$month$day 
	 group by industrial_sector";
}

if (isset($day2)) {

$sql1="select count(*) as count,
              loan_data.industrial_sector as industrial_sector 
		 from loan_data 
		where cast(creation as date)=$year2$month2$day2 
	 group by industrial_sector";
}

if(isset($day) && isset($day2))
{
$sql1="select count(*) as count,
              loan_data.industrial_sector as industrial_sector 
		 from loan_data where cast(creation as date) between $year$month$day and $year2$month2$day2 
	 group by industrial_sector";
}

if(empty($day) && empty($day2)){ 
$sql1="select count(*) as count,
              loan_data.industrial_sector as industrial_sector 
		 from loan_data 
     group by industrial_sector";
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>INDUSTRY SECTOR COUNTS
   
   </strong></strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="300" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="150" bgcolor="#000066"><strong><font color="#FFFFFF">INDUSTRY</font></strong></td>
                  <td width="150" bgcolor="#000066"><strong><font color="#FFFFFF">COUNT</font></strong></td>
				  
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="150" bgcolor="#FFFFFF" ><?php echo $rows['industrial_sector'];?></td>
                <td width="150" bgcolor="#FFFFFF" ><?php echo $rows['count']; ?></td>
				
                <?php
}
?>
              </tr>
              <tr> </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='sector_report.php'"><br><br>

</form>