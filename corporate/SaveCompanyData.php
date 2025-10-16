<html>

<head>
<Title>CORPORATE SCORING MODEL - Saving Model Calibration Company Data</Title>
</head>

<body>
    <?php
        /***************************************************************************************************************************************
	     STEP 1 : INITIALISE VARIABLES
         ****************************************************************************************************************************************
		**/ 
		 $application_ref                         = null;
	     $company_reg_no                          = null;
	     $CIF                                     = null;
		 $loan_number                             = null;
		 $registration_year                       = null;
         $vat_reg_no                              = null;
		 $customer_type                           = null;  
         $legal_name                              = null;
         $trading_name                            = null;
         $registration_country                    = null;
         $financial_year1                         = null;
         $financial_year2                         = null;
         $financial_year3                         = null;
         $reporting_month_year1                   = null;
		 $reporting_month_year2                   = null;
         $reporting_month_year3                   = null;
         $audit_status_year1                      = null;
		 $audit_status_year2                      = null;
		 $audit_status_year3                      = null;
		 $real_inflation_year1                    = null;
         $real_inflation_year2                    = null;
         $real_inflation_year3                    = null;
         $nominal_inflation_year1                 = null;
         $nominal_inflation_year2                 = null;
         $nominal_inflation_year3                 = null;
         $industrial_sector                       = null;
         $borrower_present_address                = null;
         $street_name_and_number                  = null;
         $years_at_present_address                = null;
         $e_mail                                  = null;
         $bond_plot                               = null;
		 //$permanent_country_of_residence          = null;
         $location_of_acquired_real_estate        = null;
         $main_bank                               = null;
         $second_bank                             = null;
         $third_bank                              = null;
         		 
	    /***************************************************************************************************************************************
	     STEP 2 : ASSIGN THE VARIABLES WITH EXTRACTED VALUES FROM THE FORM
         ****************************************************************************************************************************************/
 		 $application_ref                         = $_POST['application_ref'];
	     $company_reg_no                          = $_POST['company_reg_no'];
	     $CIF                                     = $_POST['CIF'];
		 $loan_number                             = $_POST['loan_number'];
		 $registration_year                       = $_POST['registration_year'];
         $vat_reg_no                              = $_POST['vat_reg_no'];
		 $customer_type                           = $_POST['customer_type'];  
         $legal_name                              = $_POST['legal_name'];
         $trading_name                            = $_POST['trading_name'];
         $registration_country                    = $_POST['registration_country'];
         $years_in_business                       = $_POST['years_in_business'];
	     $financial_year0                         = $_POST['financial_year0'];
  		 $financial_year1                         = $_POST['financial_year1'];
         $financial_year2                         = $_POST['financial_year2'];
         $financial_year3                         = $_POST['financial_year3'];
         $reporting_month_year0                   = $_POST['reporting_month_year0'];
         $reporting_month_year1                   = $_POST['reporting_month_year1'];
		 $reporting_month_year2                   = $_POST['reporting_month_year2'];
         $reporting_month_year3                   = $_POST['reporting_month_year3'];
         $audit_status_year0                      = $_POST['audit_status_year0'];
         $audit_status_year1                      = $_POST['audit_status_year1'];
		 $audit_status_year2                      = $_POST['audit_status_year2'];
		 $audit_status_year3                      = $_POST['audit_status_year3'];
		 $real_inflation_year0                    = $_POST['real_inflation_year0'];
 		 $real_inflation_year1                    = $_POST['real_inflation_year1'];
         $real_inflation_year2                    = $_POST['real_inflation_year2'];
         $real_inflation_year3                    = $_POST['real_inflation_year3'];
         $nominal_inflation_year0                 = $_POST['nominal_inflation_year0'];
         $nominal_inflation_year1                 = $_POST['nominal_inflation_year1'];
         $nominal_inflation_year2                 = $_POST['nominal_inflation_year2'];
         $nominal_inflation_year3                 = $_POST['nominal_inflation_year3'];
         $industrial_sector                       = $_POST['industrial_sector'];
         $borrower_present_address                = $_POST['borrower_present_address'];
         $street_name_and_number                  = $_POST['street_name_and_number'];
         $years_at_present_address                = $_POST['years_at_present_address'];
         $e_mail                                  = $_POST['e_mail'];
         $bond_plot                               = $_POST['bond_plot'];
		 //$permanent_country_of_residence          = $_POST['permanent_country_of_residence'];
         $location_of_acquired_real_estate        = $_POST['location_of_acquired_real_estate'];
         $main_bank                               = $_POST['main_bank'];
         $second_bank                             = $_POST['second_bank'];
         $third_bank                              = $_POST['third_bank'];
         $username                                = $_POST['username'];
     
		/***************************************************************************************************************************************
	     STEP 3 : CONNECT TO DATABASE AND SAVE THE ASSIGNED VARIABLES
         ****************************************************************************************************************************************/
      		 
	     
		 //Connecting to the database
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
		 }
         else 
		  {
             echo 'Successfully Connected';
			 
          }
		  
		 //Old PHP Version
			       // mysqli_select_db( $connect,$db_name)  
                   // or die("Couldn't open $dbase: ".mysqli_error())


   
		$insert_query = "UPDATE loan_data 
		
		SET
	        company_reg_no                          = '$company_reg_no',
	        CIF                                     = '$CIF',
		    loan_number                             = '$loan_number',
		    registration_year                       = '$registration_year',
            vat_reg_no                              = '$vat_reg_no',
			customer_type                           = '$customer_type',          
			legal_name                              = '$legal_name',           
			trading_name                            = '$trading_name',          
			registration_country                    = '$registration_country',
            years_in_business                       = '$years_in_business',		
			financial_year0                         = '$financial_year0',		
			financial_year1                         = '$financial_year1',
            financial_year2                         = '$financial_year2',
            financial_year3                         = '$financial_year3',           
			reporting_month_year0                 	= '$reporting_month_year0',
 			reporting_month_year1                 	= '$reporting_month_year1',
   		    reporting_month_year2                   = '$reporting_month_year2',
            reporting_month_year3                   = '$reporting_month_year3',
            audit_status_year0                      = '$audit_status_year0',
            audit_status_year1                      = '$audit_status_year1',
   	  	    audit_status_year2                      = '$audit_status_year2',
		    audit_status_year3                      = '$audit_status_year3',
 		    real_inflation_year0                    = '$real_inflation_year0',
		    real_inflation_year1                    = '$real_inflation_year1',
            real_inflation_year2                    = '$real_inflation_year2',
            real_inflation_year3                    = '$real_inflation_year3',
            nominal_inflation_year0                 = '$nominal_inflation_year0',
            nominal_inflation_year1                 = '$nominal_inflation_year1',
            nominal_inflation_year2                 = '$nominal_inflation_year2',
            nominal_inflation_year3                 = '$nominal_inflation_year3',
	        industrial_sector                       = '$industrial_sector',
            borrower_present_address                = '$borrower_present_address',
            street_name_and_number                  = '$street_name_and_number',
            years_at_present_address                = '$years_at_present_address',
            e_mail                                  = '$e_mail',
			bond_plot                               = '$bond_plot',
            location_of_acquired_real_estate        = '$location_of_acquired_real_estate',
            main_bank                               = '$main_bank',
            second_bank                             = '$second_bank',
            third_bank                              = '$third_bank',
            username                                = '$username'
			
			WHERE  application_ref = '$application_ref'"; /**
 
        			//permanent_country_of_residence          = $permanent_country_of_residence,
 
			**/
		$resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting company data'. mysqli_error($connect);
          }
        else
		  {
		 //========================UPDATE THE PROGRESS TRACKER - Company Data Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Company Data' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error($connect);
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Compnay Data		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   company_reg_no        = '$company_reg_no',
		   company_data_pdate    = now(), 
		   company_data_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  echo mysqli_affected_rows($connect);
		  //If the progress tracker record is non-existant then insert a new one;
		 
		 if (mysqli_affected_rows($connect) == 0) {
			 $progress_tracker_single_record_insert_query =  
		     "INSERT IGNORE INTO progress_tracker_single_record 
		     (application_ref,company_reg_no,company_data_pdate,company_data_username)
			 VALUES
			 ($application_ref,'$company_reg_no',now(),'$username')";
		     $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  }
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error($connect);
          }
      
		 //================================================================================================================================
	    //========================CREATE A BALANK RECORD OF SCORES RECORD================================================================================
		  $score_card_insert_query =  
		  "INSERT IGNORE INTO scores
		   (application_ref,company_reg_no,loan_number)
		   VALUES
		   ('$application_ref','$company_reg_no','$loan_number')";

		  $resultm= mysqli_query($connect,$score_card_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting initial blank score record'. mysqli_error($connect);
          }
      
		 //================================================================================================================================
 		 //INSERT A BLOCK OF BLANK RECORDS INTO THE DATABASE FOR THE 5 FINANCIAL REPORTS SO THAT THEY ARE ADJACENT TO EACH OTHER 
		  
		  // Assign Report names into variables so that the record insert script is evenly formatted in a tabular structure and more readable 
		   $report_name1 = "CurrentAssets";
		   $report_name2 = "NonCurrentAssets";
		   $report_name3 = "CurrentLiabilities";
		   $report_name4 = "NonCurrentLiabilities";
		   $report_name5 = "Equity";
		   
		 // Set the $update_type variable to "application to distinguish with capturing of financials provided by existing customers
		   $update_type = "Application";
		  
		   $insert_query =  "INSERT IGNORE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type   , username  ,line_desc1                               ,reporting_year    )
			  VALUES 
			  /** 1. CURRENT ASSETS===========================================================================================================================**/
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','CashBal'                                ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','CashBal'                                ,'$financial_year1' ),
		      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','CashBal'                                ,'$financial_year2' ),
              ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','CashBal'                                ,'$financial_year3' ),

              ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','AccountsReceivable'                     ,'$financial_year0' ),
              ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','AccountsReceivable'                     ,'$financial_year1' ),
              ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','AccountsReceivable'                     ,'$financial_year2' ),
		      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','AccountsReceivable'                     ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Prepayments'                            ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Prepayments'                            ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Prepayments'                            ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Prepayments'                            ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','PrepaidTax'                             ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','PrepaidTax'                             ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','PrepaidTax'                             ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','PrepaidTax'                             ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Inventory'                              ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Inventory'                              ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Inventory'                              ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','Inventory'                              ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$financial_year3' ),

			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$financial_year0' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$financial_year2' ),
		      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$financial_year3' ),

	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$financial_year0' ),
	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$financial_year3' ),

	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$financial_year0' ),
	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$financial_year3' ),

	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$financial_year0' ),
	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$financial_year3' ),

	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','TotalCurrentAssets'                     ,'$financial_year0' ),
	   	      ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','TotalCurrentAssets'                     ,'$financial_year1' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','TotalCurrentAssets'                     ,'$financial_year2' ),
			  ('$application_ref','$company_reg_no','$report_name1','$update_type','$username','TotalCurrentAssets'                     ,'$financial_year3' ),

			  /** 2. NON CURRENT ASSETS ======================================================================================================================**/
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OpeningNBV'                             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OpeningNBV'                             ,'$financial_year1'),
		      ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OpeningNBV'                             ,'$financial_year2'),
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OpeningNBV'                             ,'$financial_year3'),

              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Additions'                              ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Additions'                              ,'$financial_year1'),
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Additions'                              ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Additions'                              ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Revaluation'                            ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Revaluation'                            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Revaluation'                            ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','Revaluation'                            ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationTotal'                      ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationTotal'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationTotal'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationTotal'                      ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationCostOfSales'                ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationCostOfSales'                ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationCostOfSales'                ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationCostOfSales'                ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationOpex'                       ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationOpex'                       ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationOpex'                       ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','DepreciationOpex'                       ,'$financial_year3'),
			    
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine1'             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine1'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine1'             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine1'             ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine2'             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine2'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine2'             ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine2'             ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine3'             ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine3'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine3'             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsLine3'             ,'$financial_year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherMovementsPPE'                      ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherMovementsPPE'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherMovementsPPE'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherMovementsPPE'                      ,'$financial_year3'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','ClosingNBV'                             ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','ClosingNBV'                             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','ClosingNBV'                             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','ClosingNBV'                             ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','LandBuildingsNBV'                       ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','LandBuildingsNBV'                       ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','LandBuildingsNBV'                       ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','LandBuildingsNBV'                       ,'$financial_year3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalOtherPPE'                          ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalOtherPPE'                          ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalOtherPPE'                          ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalOtherPPE'                          ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','IntangibleAssets'                       ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','IntangibleAssets'                       ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','IntangibleAssets'                       ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','IntangibleAssets'                       ,'$financial_year3'),
	
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsTotal'             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsTotal'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsTotal'             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsTotal'             ,'$financial_year3'),
	
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsGrandTotal'        ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsGrandTotal'        ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsGrandTotal'        ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','OtherNonCurrentAssetsGrandTotal'        ,'$financial_year3'),
				  
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','InvestmentProperty'                     ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','InvestmentProperty'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','InvestmentProperty'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','InvestmentProperty'                     ,'$financial_year3'),
	   	      
			  
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalNonCurrentAssets'                  ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalNonCurrentAssets'                  ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalNonCurrentAssets'                  ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name2','$update_type','$username','TotalNonCurrentAssets'                  ,'$financial_year3'),
	
			  /** 3. CURRENT LIABILITIES======================================================================================================================**/
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','BankOverdraft'                             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','BankOverdraft'                             ,'$financial_year1'),
		      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','BankOverdraft'                             ,'$financial_year2'),
              ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','BankOverdraft'                             ,'$financial_year3'),

              ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','AccountsPayable'                           ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','AccountsPayable'                           ,'$financial_year1'),
              ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','AccountsPayable'                           ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','AccountsPayable'                           ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','Accruals'                                  ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','Accruals'                                  ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','Accruals'                                  ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','Accruals'                                  ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TaxPayable'                                ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TaxPayable'                                ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TaxPayable'                                ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TaxPayable'                                ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','DividendsPayable'                          ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','DividendsPayable'                          ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','DividendsPayable'                          ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','DividendsPayable'                          ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','CurrentPortionLongTermDebt'                ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','CurrentPortionLongTermDebt'                ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TotalCurrentLiabilities'                     ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TotalCurrentLiabilities'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TotalCurrentLiabilities'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name3','$update_type','$username','TotalCurrentLiabilities'                     ,'$financial_year3'),
	
			  /** 4. NON CURRENT LIABILITIES ====================================================================================================================**/
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','MortgageLoans'                               ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','MortgageLoans'                               ,'$financial_year1'),
		      ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','MortgageLoans'                               ,'$financial_year2'),
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','MortgageLoans'                               ,'$financial_year3'),
 
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TermLoans'                                   ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TermLoans'                                   ,'$financial_year1'),
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TermLoans'                                   ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TermLoans'                                   ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Bonds'                                       ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Bonds'                                       ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Bonds'                                       ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Bonds'                                       ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','FinanceLeases'                               ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','FinanceLeases'                               ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','FinanceLeases'                               ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','FinanceLeases'                               ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherLongTermBorrowings'                     ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherLongTermBorrowings'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherLongTermBorrowings'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherLongTermBorrowings'                     ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','ShareholdersLoans'                           ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','ShareholdersLoans'                           ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','ShareholdersLoans'                           ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','ShareholdersLoans'                           ,'$financial_year3'),
			  
			  
			  
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine1'                      ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine1'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine1'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine1'                      ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine2'                      ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine2'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine2'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine2'                      ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine3'                      ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine3'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine3'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansLine3'                      ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine1'             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine1'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine1'             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine1'             ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine2'             ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine2'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine2'             ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine2'             ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine3'             ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine3'             ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine3'             ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesLine3'             ,'$financial_year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'      ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'      ,'$financial_year3'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsInterCompanyTotal'         ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsInterCompanyTotal'         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsInterCompanyTotal'         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','LongTermBorrowingsInterCompanyTotal'         ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansTotal'                      ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansTotal'                      ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansTotal'                      ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','InterCompanyLoansTotal'                      ,'$financial_year3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalLongTermBorrowings'                     ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalLongTermBorrowings'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalLongTermBorrowings'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalLongTermBorrowings'                     ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'        ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'        ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'        ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'        ,'$financial_year3'),
	
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','DeferredTaxBalance'                          ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','DeferredTaxBalance'                          ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','DeferredTaxBalance'                          ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','DeferredTaxBalance'                          ,'$financial_year3'),
	
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Provisions'                                 ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Provisions'                                 ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Provisions'                                 ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','Provisions'                                 ,'$financial_year3'),
				  
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesTotal'            ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesTotal'            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesTotal'            ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','OtherNonCurrentLiabilitiesTotal'            ,'$financial_year3'),
	   	      
			  
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalNonCurrentLiabilities'                 ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalNonCurrentLiabilities'                 ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalNonCurrentLiabilities'                 ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name4','$update_type','$username','TotalNonCurrentLiabilities'                 ,'$financial_year3'),
			  
			  /** 5. EQUITY =====================================================================================================================================**/
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ShareCapital'                               ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ShareCapital'                               ,'$financial_year1'),
		      ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ShareCapital'                               ,'$financial_year2'),
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ShareCapital'                               ,'$financial_year3'),

              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','SharePremium'                               ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','SharePremium'                               ,'$financial_year1'),
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','SharePremium'                               ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','SharePremium'                               ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RevaluationReserve'                         ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RevaluationReserve'                         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RevaluationReserve'                         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RevaluationReserve'                         ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ClosingRetainedProfits'                     ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ClosingRetainedProfits'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ClosingRetainedProfits'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','ClosingRetainedProfits'                     ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OpeningRetainedProfits'                     ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OpeningRetainedProfits'                     ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OpeningRetainedProfits'                     ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OpeningRetainedProfits'                     ,'$financial_year3'),
			    
			  
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine1'         ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine1'         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine1'         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine1'         ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine2'         ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine2'         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine2'         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine2'         ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine3'         ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine3'         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine3'         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesLine3'         ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine1'            ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine1'            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine1'            ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine1'            ,'$financial_year3'),

			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine2'            ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine2'            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine2'            ,'$financial_year2'),
		      ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine2'            ,'$financial_year3'),

	   	      ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine3'            ,'$financial_year0'),
	   	      ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine3'            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine3'            ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesLine3'            ,'$financial_year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','NetProfit'                                  ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','NetProfit'                                  ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','NetProfit'                                  ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','NetProfit'                                  ,'$financial_year3'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','Dividends'                                  ,'$financial_year0'),
              ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','Dividends'                                  ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','Dividends'                                  ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','Dividends'                                  ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RetainedProfitsAdjustments'                 ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RetainedProfitsAdjustments'                 ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RetainedProfitsAdjustments'                 ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','RetainedProfitsAdjustments'                 ,'$financial_year3'),
	   	      
			  			  
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesTotal'            ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesTotal'            ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesTotal'            ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherDistributableReservesTotal'            ,'$financial_year3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesTotal'         ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesTotal'         ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesTotal'         ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','OtherNonDistributableReservesTotal'         ,'$financial_year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','TotalEquity'                                ,'$financial_year0'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','TotalEquity'                                ,'$financial_year1'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','TotalEquity'                                ,'$financial_year2'),
			  ('$application_ref','$company_reg_no','$report_name5','$update_type','$username','TotalEquity'                                ,'$financial_year3')";

               $resultm= mysqli_query($connect,$insert_query); 

               if (!$resultm)
               {                                                    
                  echo 'error with inserting data'. mysqli_error($connect);
               }
               else
               {
			      echo 'Data successfully saved';
			   }
			   mysqli_close($connect); 
               header("Location:CorporateAddMenu.php");
               exit;
			   //?status=success
       
	      }
		  
 ?>
</body>

</html>