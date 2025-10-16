
<html>

<head>
<Title>CORPORATE CREDIT SCORING - Saving Shareholder Analysis Data</Title>
</head>

<body>
<?php

include 'InitialiseShareholderAnalysisVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$ShareholderName1             =  $_POST['ShareholderName1'] ;
$ShareholderDate1             =  $_POST['ShareholderDate1'] ;
$ShareholderGender1           =  $_POST['ShareholderGender1'] ;
$ShareholderAge1              =  $_POST['ShareholderAge1'] ;
$ShareholderPercentageShares1 =  $_POST['ShareholderPercentageShares1'] ;
$ShareholderITCRef1           =  $_POST['ShareholderITCRef1'] ;
$ShareholderITCDate1          =  $_POST['ShareholderITCDate1'] ;
$ShareholderPaidDebts1        =  $_POST['ShareholderPaidDebts1'] ;
$ShareholderDefaults1         =  $_POST['ShareholderDefaults1'] ;
$ShareholderJudgements1       =  $_POST['ShareholderJudgements1'] ;
$ShareholderTraceAlerts1      =  $_POST['ShareholderTraceAlerts1'] ;
$ShareholderBlacklisted1      =  $_POST['ShareholderBlacklisted1'] ;
$ShareholderFraudAlert1       =  $_POST['ShareholderFraudAlert1'] ;
$ShareholderTotalScore1       =  0;// $_POST['ShareholderTotalScore1'] ;
$ShareholderName2             =  $_POST['ShareholderName2'] ;
$ShareholderDate2             =  $_POST['ShareholderDate2'] ;
$ShareholderGender2           =  $_POST['ShareholderGender2'] ;
$ShareholderAge2              =  $_POST['ShareholderAge2'] ;
$ShareholderPercentageShares2 =  0;//$_POST['ShareholderPercentageShares2'] ;
$ShareholderITCRef2           =  $_POST['ShareholderITCRef2'] ;
$ShareholderITCDate2          =  $_POST['ShareholderITCDate2'] ;
$ShareholderPaidDebts2        =  $_POST['ShareholderPaidDebts2'] ;
$ShareholderDefaults2         =  $_POST['ShareholderDefaults2'] ;
$ShareholderJudgements2       =  $_POST['ShareholderJudgements2'] ;
$ShareholderTraceAlerts2      =  $_POST['ShareholderTraceAlerts2'] ;
$ShareholderBlacklisted2      =  $_POST['ShareholderBlacklisted2'] ;
$ShareholderFraudAlert2       =  $_POST['ShareholderFraudAlert2'] ;
$ShareholderTotalScore2       =  0;//$_POST['ShareholderTotalScore2'] ;
$ShareholderName3             =  $_POST['ShareholderName3'] ;
$ShareholderDate3             =  $_POST['ShareholderDate3'] ;
$ShareholderGender3           =  $_POST['ShareholderGender3'] ;
$ShareholderAge3              =  $_POST['ShareholderAge3'] ;
$ShareholderPercentageShares3 =  $_POST['ShareholderPercentageShares3'] ;
$ShareholderITCRef3           =  $_POST['ShareholderITCRef3'] ;
$ShareholderITCDate3          =  $_POST['ShareholderITCDate3'] ;
$ShareholderPaidDebts3        =  $_POST['ShareholderPaidDebts3'] ;
$ShareholderDefaults3         =  $_POST['ShareholderDefaults3'] ;
$ShareholderJudgements3       =  $_POST['ShareholderJudgements3'] ;
$ShareholderTraceAlerts3      =  $_POST['ShareholderTraceAlerts3'] ;
$ShareholderBlacklisted3      =  $_POST['ShareholderBlacklisted3'] ;
$ShareholderFraudAlert3       =  $_POST['ShareholderFraudAlert3'] ;
$ShareholderTotalScore3       =  0;//$_POST['ShareholderTotalScore3'] ;
$ShareholderName4             =  $_POST['ShareholderName4'] ;
$ShareholderDate4             =  $_POST['ShareholderDate4'] ;
$ShareholderGender4           =  $_POST['ShareholderGender4'] ;
$ShareholderAge4              =  $_POST['ShareholderAge4'] ;
$ShareholderPercentageShares4 =  $_POST['ShareholderPercentageShares4'] ;
$ShareholderITCRef4           =  $_POST['ShareholderITCRef4'] ;
$ShareholderITCDate4          =  $_POST['ShareholderITCDate4'] ;
$ShareholderPaidDebts4        =  $_POST['ShareholderPaidDebts4'] ;
$ShareholderDefaults4         =  $_POST['ShareholderDefaults4'] ;
$ShareholderJudgements4       =  $_POST['ShareholderJudgements4'] ;
$ShareholderTraceAlerts4      =  $_POST['ShareholderTraceAlerts4'] ;
$ShareholderBlacklisted4      =  $_POST['ShareholderBlacklisted4'] ;
$ShareholderFraudAlert4       =  $_POST['ShareholderFraudAlert4'] ;
$ShareholderTotalScore4       =  0;//$_POST['ShareholderTotalScore4'] ;
$ShareholderName5             =  $_POST['ShareholderName5'] ;
$ShareholderDate5             =  $_POST['ShareholderDate5'] ;
$ShareholderGender5           =  $_POST['ShareholderGender5'] ;
$ShareholderAge5              =  $_POST['ShareholderAge5'] ;
$ShareholderPercentageShares5 =  $_POST['ShareholderPercentageShares5'] ;
$ShareholderITCRef5           =  $_POST['ShareholderITCRef5'] ;
$ShareholderITCDate5          =  $_POST['ShareholderITCDate5'] ;
$ShareholderPaidDebts5        =  $_POST['ShareholderPaidDebts5'] ;
$ShareholderDefaults5         =  $_POST['ShareholderDefaults5'] ;
$ShareholderJudgements5       =  $_POST['ShareholderJudgements5'] ;
$ShareholderTraceAlerts5      =  $_POST['ShareholderTraceAlerts5'] ;
$ShareholderBlacklisted5      =  $_POST['ShareholderBlacklisted5'] ;
$ShareholderFraudAlert5       =  $_POST['ShareholderFraudAlert5'] ;
$ShareholderTotalScore5       =  0;//$_POST['ShareholderTotalScore5'] ;
$ShareholderComment           =  $_POST['ShareholderComment'] ;





// Save Data to Database		 

$pass= "";
$host="localhost"; 
$user="root"; 
$db_name="corporatescoring"; 
                   // Connect to server and select databse.
                   //$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
                   //echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); 
    
if (!$connect) 
{
   mysqli_close($connect); 
   echo "Cannot connect to the database! Please Check your username and password."; 
}
else 
{
  echo 'Successful Connected';
			 
			// mysqli_select_db( $connect,$db_name)  
            // or die("Couldn't open $dbase: ".mysqli_error());
}

/**
        $insert_query="INSERT INTO financials 
		SET
		    company_reg_no = '1',
			update_type = 'application',
			username = 'Edward',
			report_name = 'BS',
			line_desc = 'OpeningNBVYear1',
			reporting_year = '$OpeningNBVYear1',
				amount = '$OpeningNBVYear1',
			comment = '$OtherNonCurrentAssetsComments'
		    ";
  **/
         
$username = $_POST['username'];
$application_ref = $_POST['application_ref'];
$company_reg_no = $_POST['company_reg_no'];
$loan_number = $_POST['loan_number'];
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Non Current AAssets		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   shareholder_analysis_pdate    = now(), 
		   shareholder_analysis_username = '$username'
		   
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
          
$insert_query  =  "REPLACE INTO shareholder_analysis
     ( application_ref, company_reg_no, loan_number, 
       ShareholderName1,
       ShareholderDate1,
       ShareholderGender1,
       ShareholderAge1,
       ShareholderPercentageShares1,
       ShareholderITCRef1,
       ShareholderITCDate1,
       ShareholderPaidDebts1,
       ShareholderDefaults1,
       ShareholderJudgements1,
       ShareholderTraceAlerts1,
       ShareholderBlacklisted1,
       ShareholderFraudAlert1,
       ShareholderTotalScore1,
       ShareholderName2,
       ShareholderDate2,
       ShareholderGender2,
       ShareholderAge2,
       ShareholderPercentageShares2,
       ShareholderITCRef2,
       ShareholderITCDate2,
       ShareholderPaidDebts2,
       ShareholderDefaults2,
       ShareholderJudgements2,
       ShareholderTraceAlerts2,
       ShareholderBlacklisted2,
       ShareholderFraudAlert2,
       ShareholderTotalScore2,
       ShareholderName3,
       ShareholderDate3,
       ShareholderGender3,
       ShareholderAge3,
       ShareholderPercentageShares3,
       ShareholderITCRef3,
       ShareholderITCDate3,
       ShareholderPaidDebts3,
       ShareholderDefaults3,
       ShareholderJudgements3,
       ShareholderTraceAlerts3,
       ShareholderBlacklisted3,
       ShareholderFraudAlert3,
       ShareholderTotalScore3,
       ShareholderName4,
       ShareholderDate4,
       ShareholderGender4,
       ShareholderAge4,
       ShareholderPercentageShares4,
       ShareholderITCRef4,
       ShareholderITCDate4,
       ShareholderPaidDebts4,
       ShareholderDefaults4,
       ShareholderJudgements4,
       ShareholderTraceAlerts4,
       ShareholderBlacklisted4,
       ShareholderFraudAlert4,
       ShareholderTotalScore4,
       ShareholderName5,
       ShareholderDate5,
       ShareholderGender5,
       ShareholderAge5,
       ShareholderPercentageShares5,
       ShareholderITCRef5,
       ShareholderITCDate5,
       ShareholderPaidDebts5,
       ShareholderDefaults5,
       ShareholderJudgements5,
       ShareholderTraceAlerts5,
       ShareholderBlacklisted5,
       ShareholderFraudAlert5,
       ShareholderTotalScore5,
       ShareholderComment,
       username)
	   
	   VALUES
	   
       ( '$application_ref', '$company_reg_no', '$loan_number', 
       '$ShareholderName1',
       '$ShareholderDate1',
       '$ShareholderGender1',
       '$ShareholderAge1',
       '$ShareholderPercentageShares1',
       '$ShareholderITCRef1',
       '$ShareholderITCDate1',
       '$ShareholderPaidDebts1',
       '$ShareholderDefaults1',
       '$ShareholderJudgements1',
       '$ShareholderTraceAlerts1',
       '$ShareholderBlacklisted1',
       '$ShareholderFraudAlert1',
       '$ShareholderTotalScore1',
       '$ShareholderName2',
       '$ShareholderDate2',
       '$ShareholderGender2',
       '$ShareholderAge2',
       '$ShareholderPercentageShares2',
       '$ShareholderITCRef2',
       '$ShareholderITCDate2',
       '$ShareholderPaidDebts2',
       '$ShareholderDefaults2',
       '$ShareholderJudgements2',
       '$ShareholderTraceAlerts2',
       '$ShareholderBlacklisted2',
       '$ShareholderFraudAlert2',
       '$ShareholderTotalScore2',
       '$ShareholderName3',
       '$ShareholderDate3',
       '$ShareholderGender3',
       '$ShareholderAge3',
       '$ShareholderPercentageShares3',
       '$ShareholderITCRef3',
       '$ShareholderITCDate3',
       '$ShareholderPaidDebts3',
       '$ShareholderDefaults3',
       '$ShareholderJudgements3',
       '$ShareholderTraceAlerts3',
       '$ShareholderBlacklisted3',
       '$ShareholderFraudAlert3',
       '$ShareholderTotalScore3',
       '$ShareholderName4',
       '$ShareholderDate4',
       '$ShareholderGender4',
       '$ShareholderAge4',
       '$ShareholderPercentageShares4',
       '$ShareholderITCRef4',
       '$ShareholderITCDate4',
       '$ShareholderPaidDebts4',
       '$ShareholderDefaults4',
       '$ShareholderJudgements4',
       '$ShareholderTraceAlerts4',
       '$ShareholderBlacklisted4',
       '$ShareholderFraudAlert4',
       '$ShareholderTotalScore4',
       '$ShareholderName5',
       '$ShareholderDate5',
       '$ShareholderGender5',
       '$ShareholderAge5',
       '$ShareholderPercentageShares5',
       '$ShareholderITCRef5',
       '$ShareholderITCDate5',
       '$ShareholderPaidDebts5',
       '$ShareholderDefaults5',
       '$ShareholderJudgements5',
       '$ShareholderTraceAlerts5',
       '$ShareholderBlacklisted5',
       '$ShareholderFraudAlert5',
       '$ShareholderTotalScore5',
       '$ShareholderComment',
      '$username')";
	   
$resultm= mysqli_query($connect,$insert_query); 
if (!$resultm)
{                                  
  echo 'error with inserting data'. mysqli_error();
}
else
{
  echo 'Data successfully saved';	    
  
  header("Location:CorporateAddMenu.php");
  exit;		     
}
 ?>
</body>

</html>