<?php
try {
	$connect = mysql_connect("localhost","admin","password");
	if (!$connect){
	  mysql_close($connect);
	  die('Could not connect: ' . mysql_error());
	}
	mysql_select_db("creditscoring", $connect);
} catch(Error $e) {
	$connect = mysqli_connect("localhost","admin","password","creditscoring",);
	if (!$connect){
	  die('Could not connect: ' . mysqli_error());
	}
}
?>
