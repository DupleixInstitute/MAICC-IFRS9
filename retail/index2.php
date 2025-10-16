<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>Untitled Document</title>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="complaintstyle.css" rel="stylesheet" type="text/css">
<script src="assets/js/jquery.min.js" type="text/javascript"></script>

</head>

<body>
<script>
	$(function(){
		localStorage.divorce_disabled      = "true"
		localStorage.wedding_disabled      = "true"
		localStorage.spouse_birth_disabled = "false"
    });
</script>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/** UNCOMMENT THE FOLLOWING LINES **********************************************************************************

echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
$ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);


*******************************************************************************************************************/
include("assets/includes/authenticate.php");

$mydate  = date('Y-m-d');
$mytime  = date("H.i");
//echo $mytime;echo $mydate;
//echo 'ttt';
//echo $mytime;j
$pass    = "password";
$host    = "localhost"; // Host name
$user    = "admin"; // Mysql username
$db_name ="creditscoring"; // Database name
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']);
$user_sql="select * from users where name='$username'";
//==================================================================================================================
try {
      // connect to database using PHP 5
      $connect=mysql_connect($host,$user,$pass);
	  if (!$connect) {
		  mysql_close($connect);
		  echo "Cannot connect to the database! Please Check your username and password.";
	  }
	  else {
		 mysql_select_db($db_name, $connect)
		 or die("Couldn't open $dbase: ".mysql_error());
	  }
	  $resultm= mysql_query($user,$connect);

	  if (!$resultm){
		echo 'error with geting ms mols name'. mysql_error();
	  }
	  $rows=mysql_fetch_array($resultm);
	  $name=$rows['name'];
		//echo $name;
	  $count=mysql_num_rows($resultm);
}
//catch exception
catch (Error $e) {
      // connect to database using PHP >5
      $connect=mysqli_connect($host,$user,$pass,$db_name);
	  if (!$connect) {
		  mysqli_close($connect);
		  echo "Cannot connect to the database! Please Check your username and password.";
	  }
	  $resultm= mysqli_query($connect,$user_sql);
	  if (!$resultm){
		echo 'error with geting ms mols name'. mysqli_error();
	  }
	  $rows=mysqli_fetch_array($resultm);
	  $name=$rows['name'];
		//echo $name;
	  $count=mysqli_num_rows($resultm);
 }

//================================================================================================================

//echo $count;
if($count>0)
{
echo "";
}
else
{
     echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";
}

?>

<table><tr><td><img src="../assets/images/corporate_logo1.jpg" width="385" height="208" border="0" ></td><td><img src="../assets/images/corporate_logo2.png" width="375" height="208" border="0" ></td></tr>
</table>
<form  action="../bbscomplaints/complaint.php" method="post" name="mainmenu" >

<table name = "menu" id = "menu">
	<tr><td><input type="submit" name="submit" class="btn" value="SCORE A NEW CUSTOMER - Mortgage Loan (Original Script    - Hard Coded Scoring) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='score_mortgage_loan_original.php';"><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="SCORE A NEW CUSTOMER - Unsecured Personal Loan (Original Script    - Hard Coded Scoring) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='score_unsecured_loan_original.php';"><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn2" value="EDIT SCORED CUSTOMER" onclick="form.action='edit_loan_record.php';"><br><br></tr>
	<tr><td><input type="submit" name="submit" class="btn2" value="VIEW SCORED CUSTOMERS" onclick="form.action='credit_applications/credit_applications.php';"><br><br></tr>
	<tr><td><input type="submit" name="submit" class="btn2" value="VIEW SCORED CUSTOMERS - Detailed Scores" onclick="form.action='credit_applications/view_detailed_scores.php';"><br><br></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="FIRST LEVEL:APPROVE OR DISSAPPROVE LOANS" onclick="form.action='search22.php';" >&nbsp;&nbsp;<br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn2" value="Add CIF to approved loan" onclick="form.action='addcif.php';"><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="APPROVE OR DISSAPPROVE LOANS&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='Summary_report.php';" ><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn2" value="MANAGER REPORTS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='manager_report.php';" ><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="RESET PASSWORD&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='resetpassword.html';" ><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="MODEL CALIBRATION INPUT&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='model_calibration/model_calibration.php';" ><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="MODEL CALIBRATION REPORT - By Charecteristic Groups &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='calibration_report_by_charecteristic_group/calibration_report_by_charecteristic_group.php';" ><br><br></td></tr>
	<tr><td><input type="submit" name="submit" class="btn" value="MODEL CALIBRATION REPORT - By Charecteristic Options &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='calibration_report_by_charecteristic/calibration_report_by_charecteristic.php';" ><br><br></td></tr>

<table>
<tr><td><input type="hidden" name="mydate"  value="<?php echo $mydate; ?>"/></td></tr>
<tr><td><input type="hidden" name="mytime"  value="<?php echo $mytime; ?>"/></td></tr>
<tr><td><input type="hidden" name="username"  value="<?php echo $username;?>"/></td></tr>
<tr><td><input type="hidden" name="password"  value="<?php echo $password; ?>"/></td></tr>

</table>
</form>
</body>
</html>
