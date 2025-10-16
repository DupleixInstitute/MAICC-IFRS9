<?php
	$connect = mysqli_connect("localhost","root","","corporatescoring",);
	if (!$connect){
	  die('Could not connect: ' . mysqli_error());
	}
?>