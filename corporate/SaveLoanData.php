
<html>

<head>
<Title>CORPORATE CREDIT SCORING - Saving Loan Data</Title>
</head>

<body>
<?php

include 'InitialiseLoanDataVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$loan_type             =  $_POST['loan_type'] ;
$loan_amount           =  str_replace(",","",$_POST['loan_amount']) ;
$property_type         =  $_POST['property_type'] ;
$open_market_value     =  $_POST['open_market_value'] ;
$loan_maturity         =  $_POST['loan_maturity'] ;
$rate_type             =  $_POST['rate_type'] ;
$irate                 =  $_POST['irate'] ;
$insurance_replacement =  $_POST['insurance_replacement'] ;
$insurance_premium     =  $_POST['insurance_premium'] ;
$loan_installment      =  $_POST['loan_installment'] ;
$loanandinsurance      =  $_POST['loanandinsurance'] ;
$ltv_policy            =  $_POST['ltv_policy'] ;
$ltv                   =  $_POST['ltv'] ;
$rent                  =  $_POST['rent'] ;
$relationship          =  $_POST['relationship'] ;
$Savings               =  $_POST['Savings'] ;
$Deposit               =  $_POST['Deposit'] ;
$Share                 =  $_POST['Share'] ;
$ST                    =  $_POST['ST'] ;
$Mortgages             =  $_POST['Mortgages'] ;
$total_bbs_products    =  $_POST['total_bbs_products'] ;
$loan_arrears          =  $_POST['loan_arrears'] ;
$renegotiate           =  $_POST['renegotiate'] ;
$why_renogotiation     =  $_POST['why_renogotiation'] ;
$LoanName1             =  $_POST['LoanName1'] ;
$LoanName2             =  $_POST['LoanName2'] ;
$LoanName3             =  $_POST['LoanName3'] ;
$LoanName4             =  $_POST['LoanName4'] ;
$LoanName5             =  $_POST['LoanName5'] ;
$LoanName6             =  $_POST['LoanName6'] ;
$LoanName7             =  $_POST['LoanName7'] ;
$LoanName8             =  $_POST['LoanName8'] ;
$LoanName9             =  $_POST['LoanName9'] ;
$LoanInstalment1       =  str_replace(",","",$_POST['LoanInstalment1']) ;
$LoanInstalment2       =  str_replace(",","",$_POST['LoanInstalment2']) ;
$LoanInstalment3       =  str_replace(",","",$_POST['LoanInstalment3']) ;
$LoanInstalment4       =  str_replace(",","",$_POST['LoanInstalment4']) ;
$LoanInstalment5       =  str_replace(",","",$_POST['LoanInstalment5']) ;
$LoanInstalment6       =  str_replace(",","",$_POST['LoanInstalment6']) ;
$LoanInstalment7       =  str_replace(",","",$_POST['LoanInstalment7']) ;
$LoanInstalment8       =  str_replace(",","",$_POST['LoanInstalment8']) ;
$LoanInstalment9       =  str_replace(",","",$_POST['LoanInstalment9']) ;
$TotalInstalments      =  str_replace(",","",$_POST['TotalInstalments']) ;
$loans_outstanding     =  $_POST['loans_outstanding'] ;
$itc_ref               =  $_POST['itc_ref'] ;
$paid_debts            =  $_POST['paid_debts'] ;
$judgement             =  $_POST['judgement'] ;
$default_data          =  $_POST['default_data'] ;
$trace_alerts          =  $_POST['trace_alerts'] ;
$blacklisted           =  $_POST['blacklisted'] ;
$fraud_alert           =  $_POST['fraud_alert'] ;
$deduct                =  $_POST['deduct'] ;





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
		//$CurrentRatioBenchmark_DateUpdated = NULL; 
		 //========================UPDATE THE PROGRESS TRACKER - Loan Data Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Loan Data' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Loan Data		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   loan_data_pdate    = now(), 
		   loan_data_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 	        
$insert_query  =  "UPDATE  loan_data SET
       loan_type                      = '$loan_type',
       Loan_amount_requested          = '$loan_amount',
       property_type                  = '$property_type',
       open_market_value              = '$open_market_value',
       
	   loan_maturity_requested        = '$loan_maturity',
      
	   rate_type_requested            = '$rate_type',
       
	   current_interest_rate          = '$irate',
       insurance_replacement_cost     = '$insurance_replacement',
       
	   estimated_insurance_premium    = '$insurance_premium',
       
	   estimated_installment		  ='$loan_installment',
       
	   estimated_installment_insurance= '$loanandinsurance',
       
	   loan_to_value_policy           = '$ltv_policy',
       loan_to_value                  = '$ltv',
	   
       monthly_payment                = '$rent',
       
	   age_of_relationship            = '$relationship',
       savings_Account                = '$Savings',
       
	   deposit_Account                = '$Deposit',
       share_Account                  = '$Share',
       ST_Loans                       = '$ST',
      
	   mortgages                      = '$Mortgages',
       total_bbs_products             = '$total_bbs_products',
       bbs_arreas_12months            = '$loan_arrears',
       renegotiated                   = '$renegotiate',
       why_renegotiated               = '$why_renogotiation',
       
	   loan_name1                     = '$LoanName1',
       loan_name2                     = '$LoanName2',
       loan_name3                     = '$LoanName3',
       loan_name4                     = '$LoanName4',
       loan_name5                     = '$LoanName5',
       loan_name6                     = '$LoanName6',
       loan_name7                     = '$LoanName7',
       loan_name8                     = '$LoanName8',
       loan_name9                     = '$LoanName9',

	   loan_instalment1               = '$LoanInstalment1',
       loan_instalment2               = '$LoanInstalment2',
       loan_instalment3               = '$LoanInstalment3',
       loan_instalment4               = '$LoanInstalment4',
       loan_instalment5               = '$LoanInstalment5',
       loan_instalment6               = '$LoanInstalment6',
       loan_instalment7               = '$LoanInstalment7',
       loan_instalment8               = '$LoanInstalment8',
       loan_instalment9               = '$LoanInstalment9',
       number_of_loans_outstanding    = '$loans_outstanding',
       
	   ITC_REF                        = '$itc_ref',
       paid_debt                      = '$paid_debts',
       judgment                       = '$judgement',
       default_data                   = '$default_data',
       trace_alerts                   = '$trace_alerts',
       blacklist_flag                 = '$blacklisted',
       fraud_alerts                   = '$fraud_alert',
       deduct                         = '$deduct',
       username                       = '$username'
       
	   WHERE application_ref ='$application_ref'";
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