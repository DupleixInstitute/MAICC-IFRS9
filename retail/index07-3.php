<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>Untitled Document</title>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="../bbscomplaints/complaintstyle.css" rel="stylesheet" type="text/css">
</head>

<body>
<?php
<?php
$user = $_POST['username'];
$pass = $_POST['password'];
$username='root';
$passwd='sefalana2008';
$mydate=date('Y-m-d');
$mytime=date("H.i");
//echo $mytime;echo $mydate;
//echo 'ttt';
//echo $mytime;j
?>

<table><tr><td><img src="../bbscomplaints/img1.gif" width="385" height="208" border="0" ></td><td><img src="../bbscomplaints/img2.jpg" width="375" height="208" border="0" ></td></tr>
</table>
<form  action="../bbscomplaints/complaint.php" method="post">
<input type="submit" name="submit" class="btn" value="SCORE A NEW CUSTOMER &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='menu.php';"><br><br>
<input type="submit" name="submit" class="btn2" value="CHECK IF CUSTOMER HAS BEEN SCORED" onclick="form.action='scored.php';"><br><br>
<input type="submit" name="submit" class="btn" value="APPROVE OR DISSAPPROVE LOANS&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='search.php';" ><br><br>
<input type="submit" name="submit" class="btn" value="MANAGER REPORTS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='report.php';" >

<table>
<tr><td><input type="hidden" name="mydate"  value="<?php echo $mydate ?>"/></td></tr>
<tr><td><input type="hidden" name="mytime"  value="<?php echo $mytime ?>"/></td></tr>
<tr><td><input type="hidden" name="username"  value="<?php echo $username?>"/></td></tr>
<tr><td><input type="hidden" name="passwd"  value="<?php echo $passwd ?>"/></td></tr></table>
</form>
</body>
</html>
