<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<body>
<?php






$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;




$connect=mysql_connect($host,$username,$password); 
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());


}

$date="select cast(date as date) from approvals";

$resultd=mysql_query($date,$connect);
  
if (!$resultd)
  {
  die('Error2: ' . mysql_error());
  }

else 
echo "done";

$mycountd=0;
while($rowss=mysql_fetch_array($resultd)){
$mycountd++; 

echo $rowss['date'];
}
?>
</body>
</html>
