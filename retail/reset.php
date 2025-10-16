<html>
<head>
<title>Password Reset</title>
</head>

<body>
<table border="0" width="100%" cellpadding="10">
<tr> <img src="img1.gif" width="30%" height="160"></tr>
<tr>
</tr>
<tr>
</tr>
 <tr>  <td> </td> <td><font color="darkblue"  size="6"><strong>BBS CREDIT SCORING</strong></font> </td></tr>
<tr>
</tr>
<tr>
</tr>
<tr>
</tr>
<tr>
</tr>
<tr>
</tr>
</table>

<?php

$user = $_POST['username'];
$pass = $_POST['oldpasswd'];
$newpasswd1 = $_POST['passwd'];
$newpasswd2 = $_POST['passwd1'];

if ($newpasswd1!==$newpasswd2){
die ("Your new passwords do not match. Please go back and re-enter your passwords again");
}
//if (empty($name)) {
  //  echo "$name is either 0, empty, or not set at all";
//}

include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;


$sql="SET PASSWORD = PASSWORD('$newpasswd1');";


if (!mysqli_query($connect,$sql))
  {
  die('Error: ' . mysqli_error());
  }
//echo "<b>The Following Direct Debit Record Has Added</b> <br>";
mysqli_close($connect);

?>
<font color="orange"  size="4"><strong>Your password has successfully been reset.</strong></font>
<?php
 }  
?>
<br>
<br>
<br>
<br>
<a href="./index.html"><font color="red" size="2"><strong>RETURN TO MAIN MENU</strong></font></a><br>
<a href="./add-dd.html"><font color="red" size="2"><strong>RETURN TO ADD A DIRECT DEBIT</strong></font></a>
</body>
</html>



