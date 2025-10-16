
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING - Load Data To Edit</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">

//<!-- Document.Ready to set up jquery.number.js -->

<script>	    
			
</script>
<?php
	    
//Get search variables from previous page
$application_ref = $_POST['application_ref'];
$company_reg_no = $_POST['company_reg_no'];
$loan_number = $_POST['loan_number'];
$username = $_POST['username'];
				 
//Connect to the database
$pass= "";
$host="localhost"; 
$user="root"; 
$db_name="corporatescoring"; 
                   
// GET HOST BY NAME ?
// Connect to server and select databse.
//$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;

$connect=mysqli_connect($host,$user,$pass,$db_name);     

//Failed Connection ==> die
if (!$connect) {
   mysqli_close($connect); 
   echo "Cannot connect to the database! Please Check your username and password."; 
   die;
   echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";
}
          
// Construct Search queries depending on the search key used:===========================
if ($application_ref !== ""){       // Where search key used is application_ref
	$CompanyData_Query      = "SELECT * FROM loan_data            WHERE application_ref ='$application_ref' "; 
}
else {                              // Where search key used is company reg no + loan number
   $CompanyData_Query = "SELECT * FROM loan_data WHERE 
			             company_reg_no ='$company_reg_no' and loan_number = '$loan_number' ";     
}	        

//1. COMPANY DATA - Run CompanyData query and Update local Storage===============================================================================
$result= mysqli_query($connect,$CompanyData_Query); //interchanged by edward
if (!$result){                                  
   echo 'error with getting registration no'. mysql_error();
   exit;
}     
// 
include 'InitialiseLoanDataVars.php';  
while($row = mysqli_fetch_assoc($result)) 
{
   include ("GetCompanyDataRecord.php");  //Updating local storage via PHP variables
}
    
//Construct search queries from all tables needed to assess client
$Financials_Query       = "SELECT * FROM accounts             WHERE application_ref ='$application_ref'";
$Management_Query       = "SELECT * FROM management_analysis  WHERE application_ref ='$application_ref'";
$Industry_Query         = "SELECT * FROM industry_analysis    WHERE application_ref ='$application_ref'";
$Shareholder_Query      = "SELECT * FROM shareholder_analysis WHERE application_ref ='$application_ref'";
$IndBenchOverride_Query = "SELECT * FROM industry_ratio_benchmarks_overrides 
                                                              WHERE application_ref ='$application_ref'";
$ProgressTracker_Query  = "SELECT * FROM progress_tracker_single_record
                                                              WHERE application_ref ='$application_ref'";


/**Check if the company data record exists and proceed to read other tables in this order i.e:

2. FINANCIAL DATA
3. MANAGEMENT ANALYSIS DATA
4. INDUSTRY ANALYSIS DATA
5. BEHAVIORAL ANALYSIS DATA
6. INDUSTRY BENCHMARKS OVERRIDES DATA

**/
if (mysqli_num_rows($result) > 0) {                          // if Compnay Data Record Exists
   
	//Initialise PHP variable before they hold the data from database table
 	include 'InitialiseFinVars.php';                         // Financials Data
	include 'InitialiseManagementVars.php';                  // Management Analysis Data
	include 'InitialiseIndustryVars.php';                    // Industry Analysis Data
	include 'InitialiseShareholderVars.php';                 // Shareholder Analysis Data
    include 'InitialiseIndustryBenchmarksOverridesVars.php'; // Industry Bencharks Overrides Data
    
	//2. FINANCIAL DATA - Run Financials Query and Update local Storage==================================================================================
	$result= mysqli_query($connect,$Financials_Query); //interchanged by edward		      
	if (!$result){                                  	
		echo 'error with getting executive financials data query'. mysql_error();
		exit;
	}   	
	
	//Loop through the accounts table and transfer relevant fields into various PHP variables
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetFinancialDataRecord.php';
	}
	//transfer the PHP variables into local storage
	include 'LoadFinVars.php';  // will generate an error if localStorage assignment is to an uninitialised PHP variable

    //3. MANAGEMENT DATA - Run Management Query and Update local Storage==================================================================================
	$result= mysqli_query($connect,$Management_Query); 		      
	if (!$result){                                  	
		echo 'error with getting executing management data query'. mysql_error();
		exit;
	}   	
	
	//Loop through the management analysis table and transfer relevant fields into various PHP variables
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetManagementDataRecord.php';
	}

   //4. INDUSTRY DATA - Run Industry Query and Update local Storage==================================================================================
	$result= mysqli_query($connect,$Industry_Query); 		      
	if (!$result){                                  	
		echo 'error with getting executing industry data query'. mysql_error();
		exit;
	}   	
	
	//Loop through the management analysis table and transfer relevant fields into various PHP variables
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetIndustryDataRecord.php';
	}
   //5. SHAREHOLDER DATA - Run Shareholder Query and Update local Storage==================================================================================
	$result= mysqli_query($connect,$Shareholder_Query); 		      
	if (!$result){                                  	
		echo 'error with getting executing industry data query'. mysql_error();
		exit;
	}   		
	//Loop through the shareholder analysis table and transfer relevant fields into various PHP variables
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetShareholderDataRecord.php';
	}
   //6. INDUSTRY BENCHMARKS DATA - Run Industry Benchmarks Overrides Query and Update local Storage==================================================================================
	$result= mysqli_query($connect,$IndBenchOverride_Query); 		      
	if (!$result){                                  	
		echo 'error with getting executing industry data query'. mysqli_error();
		exit;
	}   		
	//Loop through the Industry Benchmarks Overrides table and transfer relevant fields into various PHP variables
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetIndustryBenchmarksOverridesDataRecord.php';
	  echo "Industry Benchmark Overrides";
	}
	//Loop through the Progress tracker single record table and transfer relevant fields into various PHP variables
	$result= mysqli_query($connect,$ProgressTracker_Query); 		      
	if (!$result){                                  	
		echo 'error with getting executing progress tracker data query'. mysqli_error();
		exit;
	}   		
	while($row = mysqli_fetch_assoc($result)) {
	  include 'GetProgressTrackerDataRecord.php';
	}
		
    //close database connection and jump to CorporateAddMenu.htm
	mysqli_close($connect);			
	echo "<meta http-equiv=\"refresh\" content=\"0;URL=CorporateAddMenu.php\">";
    exit;				
}
else {
    echo "No companies data record exists exits";
}      
   

?>

