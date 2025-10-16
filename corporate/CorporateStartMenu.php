<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>Main Menu</title>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="complaintstyle.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="Dupleix.js"></script>  
<script type="text/javascript" src="jquery-3.5.1.js"></script>
   
  
<script>
      
	    //<!-- Document.Ready to set up jquery.number.js -->
	    $(function()
		    {
               InitialiseClientData();				
			});
</script>

 
</head>

<body>

<?php


//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
//echo  "BBS\ ";
$bbs='BBS\\';

$new=$bbs.$username;
$ldappass=$_POST['password'];
//echo $new.$ldappass;

$ldaprdn  = $new;     // ldap rdn or dn
$ldappass = $ldappass;  // associated password

/** Edward 
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
   
Edward   

	echo "<meta http-equiv=\"refresh\" content=\"0;URL=RetailIndex.php\">";
//edward}	
//Edward	}
	
	
     //echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
		
**/
    
$mydate=date('Y-m-d');
$mytime=date("H.i");
//echo $mytime;echo $mydate;
//echo 'ttt';
//echo $mytime;j

$pass= "";                      //edward"sefalana2008";
$host="localhost";              // Host name 
$user="root";                   // Mysql username 
$db_name="corporatescoring";    // Database name 
                                // Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); //Edward Added the db name
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db( $connect,$db_name)  //interchanged by Edward
or die("Couldn't open $dbase: ".mysql_error($connect));

}


$user="select * from users where name='$username'";
  
  
$resultm= mysqli_query($connect,$user); //interchanged by edward

if (!$resultm)
{                                  
echo 'error with getting user name'. mysqli_error($connect);
}
while ($rows=mysqli_fetch_array($resultm)) {
	$name=$rows['name'];
}
$count=mysqli_num_rows($resultm);

echo $count;

if($count>0)
{
   		 $pass= "";
         $host="localhost"; 
         $user="root"; 
         $db_name="corporatescoring"; 
                   
	               // GET HOST BY NAME ?
				   // Connect to server and select databse.
                   //$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
                   //echo $ip;

         //New PHP Version
		 
		 
         $connect=mysqli_connect($host,$user,$pass,$db_name); 
    
         if (!$connect) 
	     {
             mysqli_close($connect); 
             echo "Cannot connect to the database! Please Check your username and password."; 
             die;
			 //echo "<meta http-equiv=\"refresh\" content=\"0;URL=Retailuser_error.php\">";
		 }
			
				//1..LOAD MODEL CALIBRATION SETTINGS===================================================================
				$Load_Query = "SELECT * FROM model_calibration";
                $result= mysqli_query($connect,$Load_Query); //interchanged by edward
			      
				if (!$result)
                {                                  
                    echo 'error with reading database for model settings'. mysql_error();
				    exit;
                }     
		
                
					
				if (mysqli_num_rows($result) > 0) 
			    {
                    // output data of each row
 				    //header("Location:CorporateAddMenu.htm");
                    //exit;
                    
                    while($row = mysqli_fetch_assoc($result)) 
				    {
					   include 'GetModelCalibrationDataRecord.php';
  				    }
 				
					include 'LoadModelCalibrationVars.php';
					//echo "<meta http-equiv=\"refresh\" content=\"0;URL=CorporateAddMenu.htm\">";
	
					//header("Location:CorporateAddMenu.htm");
                    //exit;
				
				}
			    else 
				{
                    echo "No model settings file exists";
                }      
	          
				//2..LOAD INDUSTRY BENCHMARKS===================================================================
				$Load_Query = "SELECT * FROM industry_ratio_benchmarks" ;              
				$result= mysqli_query($connect,$Load_Query); //interchanged by edward
			      
				if (!$result)
                {                                  
                    echo 'error with reading database for industry bechmarks'. mysql_error();
				    exit;
                }     
		
					
				if (mysqli_num_rows($result) > 0) 
			    {
                    // output data of each row
 				    //header("Location:CorporateAddMenu.htm");
                    //exit;
                    include 'InitialiseIndustryBenchmarksVars.php';
                    while($row = mysqli_fetch_assoc($result)) 
				    {
			    	   include 'GetIndustryBenchmarksDataRecord.php';
  				    }
 	    		    mysqli_close($connect);
					
					include 'LoadIndustryBenchmarksVars.php';
					//echo "<meta http-equiv=\"refresh\" content=\"0;URL=CorporateAddMenu.htm\">";
	
					//header("Location:CorporateAddMenu.htm");
					//exit;
				
				}
			    else 
				{
                    echo "No  industry benchmark file exists";
                }      		        
   
   
   
}

//if the username is wrong show error message and die
else
{
     echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";
     die();
}

?>
<table><tr><td><img src="../assets/images/corporate_logo1.jpg" width="385" height="208" border="0" ></td><td><img src="../assets/images/corporate_logo2.png" width="375" height="208" border="0" ></td></tr>
</table>
<form  action="" method="post" name="mainmenu" >
<input type="submit" name="submit" class="btn"  style ="width:320px;text-align:left" value="SCORE A NEW CUSTOMER " onclick="form.action='GetNewCompanyRegNo.php';"><br><br>
<input type="submit" name="submit" class="btn2" style ="width:320px;text-align:left" value="EDIT SCORED CUSTOMER" onclick="form.action='GetNewCompanyRegNoToEdit.php';"><br><br>
<input type="submit" name="submit" class="btn"  style ="width:320px;text-align:left" value="FIRST LEVEL:APPROVE OR DISSAPPROVE LOANS" onclick="form.action='GetCompanyRegNo_FirstApproval.php';" ><br><br>
<input type="submit" name="submit" class="btn2" style ="width:320px;text-align:left" value="ADD CIF TO APPROVED LOAN" onclick="form.action='GetCompanyRegNo_AddCIF.php';"><br><br>
 
<input type="submit" name="submit" class="btn"  style ="width:320px;text-align:left" value="APPROVE OR DISSAPPROVE LOANS" onclick="form.action='GetCompanyRegNo_SecondApproval.php';" ><br><br>

<input type="submit" name="submit" class="btn2" style ="width:320px;text-align:left" value="MANAGER REPORTS" onclick="form.action='Manager_Report.php';" ><br><br>
<input type="submit" name="submit" class="btn"  style ="width:320px;text-align:left" value="RESET PASSWORD" onclick="form.action='resetpassword.html';" ><br><br>
<input type="submit" name="submit" class="btn2" style ="width:320px;text-align:left" value="MODEL CALIBRATION" onclick="form.action='Settings.htm';" ><br><br>
<input type="submit" name="submit" class="btn"  style ="width:320px;text-align:left" value="UPDATE INDUSTRY/SECTORAL BENCHMARKS" onclick="form.action='Settings-IndustryBenchmarks.htm';" ><br><br>
<table>
<tr><td><input type="hidden" name="mydate"      style ="width:320px;text-align:left" value="<?php echo $mydate; ?>"/></td></tr>
<tr><td><input type="hidden" name="mytime"      style ="width:320px;text-align:left" value="<?php echo $mytime; ?>"/></td></tr>
<tr><td><input type="hidden" name="username"    style ="width:320px;text-align:left" value="<?php echo $username;?>"/></td></tr>
<tr><td><input type="hidden" name="password"    style ="width:320px;text-align:left" value="<?php echo $password; ?>"/></td></tr>

</table>
</form>
   
</body>
</html>
