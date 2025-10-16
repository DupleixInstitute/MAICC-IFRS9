
<html>

<head>
<Title>CORPORATE CREDIT SCORING - Saving Management Analysis Data</Title>
</head>

<body>
<?php

//include 'InitialiseManagementAnalysisVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$Commitment                          =  $_POST['Commitment'] ;
$Integrity                           =  $_POST['Integrity'] ;
$InformationQuality                  =  $_POST['InformationQuality'] ;
$Leadership                          =  $_POST['Leadership'] ;
$Strategy                            =  $_POST['Strategy'] ;
$Structure                           =  $_POST['Structure'] ;
$Management                          =  $_POST['Management'] ;
$SuccessionPlanning                  =  $_POST['SuccessionPlanning'] ;
$OrganisationalPlanning              =  $_POST['OrganisationalPlanning'] ;
$CommitmentComment                   =  $_POST['CommitmentComment'] ;
$IntegrityComment                    =  $_POST['IntegrityComment'] ;
$InformationQualityComment           =  $_POST['InformationQualityComment'] ;
$LeadershipComment                   =  $_POST['LeadershipComment'] ;
$StrategyComment                     =  $_POST['StrategyComment'] ;
$StructureComment                    =  $_POST['StructureComment'] ;
$ManagementComment                   =  $_POST['ManagementComment'] ;
$SuccessionPlanningComment           =  $_POST['SuccessionPlanningComment'] ;
$OrganisationalPlanningComment       =  $_POST['OrganisationalPlanningComment'] ;
$CommitmentReviewComment             =  $_POST['CommitmentReviewComment'] ;
$IntegrityReviewComment              =  $_POST['IntegrityReviewComment'] ;
$InformationQualityReviewComment     =  $_POST['InformationQualityReviewComment'] ;
$LeadershipReviewComment             =  $_POST['LeadershipReviewComment'] ;
$StrategyReviewComment               =  $_POST['StrategyReviewComment'] ;
$StructureReviewComment              =  $_POST['StructureReviewComment'] ;
$ManagementReviewComment             =  $_POST['ManagementReviewComment'] ;
$SuccessionPlanningReviewComment     =  $_POST['SuccessionPlanningReviewComment'] ;
$OrganisationalPlanningReviewComment =  $_POST['OrganisationalPlanningReviewComment'] ;





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
		 //========================UPDATE THE PROGRESS TRACKER - Management Analysis Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Management Analysis' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Management Analysis		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   management_analysis_pdate    = now(), 
		   management_analysis_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 	        
$insert_query  =  "REPLACE INTO management_analysis
     ( application_ref, company_reg_no, loan_number, 
       Commitment,
       Integrity,
       InformationQuality,
       Leadership,
       Strategy,
       Structure,
       Management,
       SuccessionPlanning,
       OrganisationalPlanning,
       CommitmentComment,
       IntegrityComment,
       InformationQualityComment,
       LeadershipComment,
       StrategyComment,
       StructureComment,
       ManagementComment,
       SuccessionPlanningComment,
       OrganisationalPlanningComment,
       CommitmentReviewComment,
       IntegrityReviewComment,
       InformationQualityReviewComment,
       LeadershipReviewComment,
       StrategyReviewComment,
       StructureReviewComment,
       ManagementReviewComment,
       SuccessionPlanningReviewComment,
       OrganisationalPlanningReviewComment,
       username)

       VALUES

       ('$application_ref', '$company_reg_no', '$loan_number', 
       '$Commitment',
       '$Integrity',
       '$InformationQuality',
       '$Leadership',
       '$Strategy',
       '$Structure',
       '$Management',
       '$SuccessionPlanning',
       '$OrganisationalPlanning',
       '$CommitmentComment',
       '$IntegrityComment',
       '$InformationQualityComment',
       '$LeadershipComment',
       '$StrategyComment',
       '$StructureComment',
       '$ManagementComment',
       '$SuccessionPlanningComment',
       '$OrganisationalPlanningComment',
       '$CommitmentReviewComment',
       '$IntegrityReviewComment',
       '$InformationQualityReviewComment',
       '$LeadershipReviewComment',
       '$StrategyReviewComment',
       '$StructureReviewComment',
       '$ManagementReviewComment',
       '$SuccessionPlanningReviewComment',
       '$OrganisationalPlanningReviewComment',
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