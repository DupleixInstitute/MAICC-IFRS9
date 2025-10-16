<?php
	$servername = "localhost";
	$username = "root";
	$password = "";
	$db="ifrs9";
	// Create connection
	$connect = mysqli_connect($servername, $username, $password,$db);
	
	//connection 
	$dbDetails = array( 
    'host' => 'localhost', 
    'user' => 'root', 
    'pass' => '', 
    'db'   => 'ifrs9' 
); 