
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CORPORATE CREDIT SCORING - Approvals Versus Approvals</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php
error_reporting(0);
//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
//echo  "BBS\ ";
$bbs='BBS\\';

/**
$new=$bbs.$username;
$ldappass=$_POST['password'];
//echo $new.$ldappass;

$ldaprdn  = $new;     // ldap rdn or dn
$ldappass = $ldappass;  // associated password

// connect to ldap server
$ldapconn = ldap_connect("10.0.9.10")
    or die("Could not connect to LDAP server.");

if ($ldapconn) {

    // binding to ldap server
    $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);

    // verify binding
    if ($ldapbind) {
        echo "";
    } else {
	
     echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
		

    }

}

**/




$pass="";
$host="localhost"; // Host name 
$user="root"; // Mysql username 
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;

$company_reg_no=$_POST['company_reg_no'];

$day=$_POST['day2'];
$month=$_POST['month2'];
$year=$_POST['year2'];

$day2=$_POST['day22'];
$month2=$_POST['month22'];
$year2=$_POST['year22'];

$connect=mysqli_connect($host,$user,$pass,$db_name);
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error());

}

if (isset($day)) {

$sql1="select count(*) as count,status as status from approved where cast(date as date)=$year$month$day group by status;";
}

if (isset($day2)) {

$sql1="select count(*) as count,status as status from approved where cast(approved.date as date)=$year2$month2$day2  group by status;";
}


if(isset($day) && isset($day2)) {

$sql1="select count(*) as count,status as status from approved where cast(approved.date as date)between $year$month$day and $year2$month2$day2 group by status;";
}


if(empty($day) && empty($day2)) {

$sql1="select count(*) as count,status as status from approved group by status";
}










$result=mysqli_query($connect,$sql1);
  
if (!$result)
  {
  die('Error2: ' . mysqli_error());
  }
  ?>
  
  <?php
  
  if (isset($day)) {

$sql3="select * from approved where cast(approved.date as date)=$year$month$day ;";
}

if (isset($day2)) {

$sql3="select * from approved where cast(approved.date as date)=$year2$month2$day2;";
}


if(isset($day) && isset($day2)) {

$sql3="select * from approved where cast(approved.date as date)between $year$month$day and $year2$month2$day2 ;";
}


if(empty($day) && empty($day2)) {

  $sql3="select * from approved;";
}



  
  

$result3=mysqli_query($connect,$sql3);
  
if (!$result3)
  {
  die('Error2: ' . mysqli_error());
  }
$count3=mysqli_num_rows($result3);
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
   <tr>
      <td><font color="#000066" size="4" class="btn"><strong>LOANS BY STATUS</strong></font></td>
    </tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="323" height="57" border="0" align="center"  class="s_text"   bgcolor="#000066">
              <tr> 
                  <td width="220" ><strong><font color="#FFFFFF">STATUS</font></strong></td>
                  <td width="220" ><strong><font color="#FFFFFF">COUNT</font></strong></td>
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="220" bgcolor="#FFFFFF" ><?php echo $rows['status']; ?></td>
                <td width="220" bgcolor="#FFFFFF" ><?php echo $rows['count']; ?></td>
                <?php
}
?>
              </tr>
              <tr>  <td width="220" bgcolor="#FFFFFF" ><strong>TOTAL</strong></td>
                <td width="220" bgcolor="#FFFFFF" ><strong><?php echo $count3; ?></strong></td></tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index.php';"><br><br>

</form>