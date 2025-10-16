<?php
  //Check ldap connection in old and new PHP*******************************************************************************************************************/
	$username = $_POST['username'];
	$password = $_POST['password'];

    echo "User name.. ".$username;  

	$bbs      = 'BBS\\';

	$new      = $bbs.$username;
	$ldappass = $_POST['password'];

	$ldaprdn  = $new;       // ldap rdn or dn
	$ldappass = $ldappass;  // associated password

	//ladap extension added on php.ini i.e. => extension = php_ldap.dll
	//$builtWithLdap = extension_loaded('ldap');
	//check for ladap extension -- not used currently, using a try instead
	$builtWithLdap = function_exists('ldap_add');

	//==================================================================================================================
	try {  
		// connect to ldap server using ldap_connect
		//****************************************************************************************************************
		//Return Values:
		//Returns an LDAP\Connection instance when the provided LDAP URI seems plausible. 
		//It's a syntactic check of the provided parameter but the server(s) will not be contacted! 
		//If the syntactic check fails it returns false. 
		//ldap_connect() will otherwise return a LDAP\Connection instance as it does not actually connect but 
		//just initializes the connecting parameters. T
		//The actual connect happens with the next calls to ldap_* functions, usually with ldap_bind().
		//***********************************************************************************************************************
		$ldapconn = ldap_connect("10.0.9.10") or die("Could not connect to LDAP server.");
		if ($ldapconn) {
			// binding to ldap server//LDAP directory with specified RDN(Relative Distinguished Name) and password.
			//$ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);
			// IF binding NOT successfull, return to password entry screen
			//if ($ldapbind) {
			  
			//} else {  
			  //echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";  
			  //echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
		   // }	
	    }
	}  
	//catch exception  
	catch (Exception $e) {  
	   //echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";  
	   
	   echo 'Exception Message: ' .$e->getMessage();  
	}  
//finally {  
  // echo '</br> It is finally block, which always executes.';  
//}  
//================================================================================================================

	
	
	include("assets/includes/database_connection.php");
	
	
	
	$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
	$user_sql="select * from users where name='$username'";
	//==================================================================================================================
	try {  
		  $resultm= mysql_query($user,$connect);
		  if (!$resultm){
			echo 'error with getting user name'. mysql_error();
		  }
		  $rows=mysql_fetch_array($resultm);
		  $name=$rows['name'];
		  $count=mysql_num_rows($resultm);
	}  
	//catch exception  
	catch (Error $e) {  
		  $resultm= mysqli_query($connect,$user_sql);
		  if (!$resultm){
			echo 'error with geting user name'. mysqli_error();
		  }
		  $rows=mysqli_fetch_array($resultm);
		  $name=$rows['name'];
		  $count=mysqli_num_rows($resultm);
		  
		 }  
	  
	if($count>0)
	{
	}
	else
	{
		 echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";
	}
?>

