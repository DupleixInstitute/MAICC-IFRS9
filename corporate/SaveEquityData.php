
    <?php
	    //VARIABLE INITIALISATION===========================================================================================================
	     $ShareCapitalYear0= null;
	     $ShareCapitalYear1= null;
	     $ShareCapitalYear2= null;
	     $ShareCapitalYear3= null;
		 
		 $SharePremiumYear0=null;
		 $SharePremiumYear1=null;
		 $SharePremiumYear2=null;
		 $SharePremiumYear3=null;
		 
		 $RevaluationReserveYear0 = null;
		 $RevaluationReserveYear1 = null;
		 $RevaluationReserveYear2 = null;
		 $RevaluationReserveYear3 = null;
		 
		 $OtherNonDistributableReservesTotalYear0 = null;
		 $OtherNonDistributableReservesTotalYear1 = null;
		 $OtherNonDistributableReservesTotalYear2 = null;
		 $OtherNonDistributableReservesTotalYear3 = null;
		 
		 $ClosingRetainedProfitsYear0 = null;
		 $ClosingRetainedProfitsYear1 = null;
		 $ClosingRetainedProfitsYear2 = null;
		 $ClosingRetainedProfitsYear3 = null;

		 $OpeningRetainedProfitsYear0 = null;
		 $OpeningRetainedProfitsYear1 = null;
		 $OpeningRetainedProfitsYear2 = null;
		 $OpeningRetainedProfitsYear3 = null;

		 $OtherNonDistributableReservesLine1Year0 = null;
		 $OtherNonDistributableReservesLine1Year1 = null;
		 $OtherNonDistributableReservesLine1Year2 = null;
		 $OtherNonDistributableReservesLine1Year3 = null;
		 
		 $OtherNonDistributableReservesLine2Year0 = null;
		 $OtherNonDistributableReservesLine2Year1 = null;
		 $OtherNonDistributableReservesLine2Year2 = null;
		 $OtherNonDistributableReservesLine2Year3 = null;

		 $OtherNonDistributableReservesLine3Year0 = null;
		 $OtherNonDistributableReservesLine3Year1 = null;
		 $OtherNonDistributableReservesLine3Year2 = null;
		 $OtherNonDistributableReservesLine3Year3 = null;
 
 	     $OtherDistributableReservesLine1Year0 = null;
		 $OtherDistributableReservesLine1Year1 = null;
		 $OtherDistributableReservesLine1Year2 = null;
		 $OtherDistributableReservesLine1Year3 = null;
		 
		 $OtherDistributableReservesLine2Year0 = null;
		 $OtherDistributableReservesLine2Year1 = null;
		 $OtherDistributableReservesLine2Year2 = null;
		 $OtherDistributableReservesLine2Year3 = null;

		 $OtherDistributableReservesLine3Year0 = null;
		 $OtherDistributableReservesLine3Year1 = null;
		 $OtherDistributableReservesLine3Year2 = null;
		 $OtherDistributableReservesLine3Year3 = null;
	     
		 $OtherNonDistributableReservesDescLine0 = null;
		 $OtherNonDistributableReservesDescLine1 = null;
		 $OtherNonDistributableReservesDescLine2 = null;
		 $OtherNonDistributableReservesDescLine3 = null;
		 
		 $OtherDistributableReservesDescLine0 = null;
		 $OtherDistributableReservesDescLine1 = null;
		 $OtherDistributableReservesDescLine2 = null;
		 $OtherDistributableReservesDescLine3 = null;
		 
		 $NetProfitYear0 = null;
		 $NetProfitYear1 = null;
		 $NetProfitYear2 = null;
		 $NetProfitYear3 = null;
		 
		 $DividendsYear0 = null;
		 $DividendsYear1 = null;
		 $DividendsYear2 = null;
		 $DividendsYear3 = null;
		
	     $RetainedProfitsAdjustmentsYear0 = null;
		 $RetainedProfitsAdjustmentsYear1 = null;
		 $RetainedProfitsAdjustmentsYear2 = null;
		 $RetainedProfitsAdjustmentsYear3 = null;
		 
		 $TotalLongTermBorrowingsYear0 = null;
		 $TotalLongTermBorrowingsYear1 = null;
		 $TotalLongTermBorrowingsYear2 = null;
		 $TotalLongTermBorrowingsYear3 = null;
		 
		 $OtherDistributableReservesTotalYear0 = null;
		 $OtherDistributableReservesTotalYear1 = null;
		 $OtherDistributableReservesTotalYear2 = null;
		 $OtherDistributableReservesTotalYear3 = null;
 	 		 
	     $TotalEquityYear0      = null;
		 $TotalEquityYear1      = null;
		 $TotalEquityYear2      = null;
		 $TotalEquityYear3      = null;
	


	 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $ShareCapitalYear0                                = str_replace(",","",$_POST['ShareCapitalYear0']);
	     $ShareCapitalYear1                                = str_replace(",","",$_POST['ShareCapitalYear1']);
	     $ShareCapitalYear2                                = str_replace(",","",$_POST['ShareCapitalYear2']);
	     $ShareCapitalYear3                                = str_replace(",","",$_POST['ShareCapitalYear3']);
		
	   	 $SharePremiumYear0                                = str_replace(",","",$_POST['SharePremiumYear0']);
		 $SharePremiumYear1                                = str_replace(",","",$_POST['SharePremiumYear1']);
		 $SharePremiumYear2                                = str_replace(",","",$_POST['SharePremiumYear2']);
		 $SharePremiumYear3                                = str_replace(",","",$_POST['SharePremiumYear3']);
		 
		 $RevaluationReserveYear0                          = str_replace(",","",$_POST['RevaluationReserveYear0']);
		 $RevaluationReserveYear1                          = str_replace(",","",$_POST['RevaluationReserveYear1']);
		 $RevaluationReserveYear2                          = str_replace(",","",$_POST['RevaluationReserveYear2']);
		 $RevaluationReserveYear3                          = str_replace(",","",$_POST['RevaluationReserveYear3']);
		 
		 $RetainedProfitsAdjustmentsYear0                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear0']);
		 $RetainedProfitsAdjustmentsYear1                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear1']);
		 $RetainedProfitsAdjustmentsYear2                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear2']);
		 $RetainedProfitsAdjustmentsYear3                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear3']);
		 
		 $ClosingRetainedProfitsYear0                      = str_replace(",","",$_POST['ClosingRetainedProfitsYear0']);
		 $ClosingRetainedProfitsYear1                      = str_replace(",","",$_POST['ClosingRetainedProfitsYear1']);
		 $ClosingRetainedProfitsYear2                      = str_replace(",","",$_POST['ClosingRetainedProfitsYear2']);
		 $ClosingRetainedProfitsYear3                      = str_replace(",","",$_POST['ClosingRetainedProfitsYear3']);
		    
		 $OpeningRetainedProfitsYear0                      = str_replace(",","",$_POST['OpeningRetainedProfitsYear0']);
		 $OpeningRetainedProfitsYear1                      = str_replace(",","",$_POST['OpeningRetainedProfitsYear1']);
		 $OpeningRetainedProfitsYear2                      = str_replace(",","",$_POST['OpeningRetainedProfitsYear2']);
		 $OpeningRetainedProfitsYear3                      = str_replace(",","",$_POST['OpeningRetainedProfitsYear3']);
				
		 $OtherNonDistributableReservesLine1Year0          = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year0']);
		 $OtherNonDistributableReservesLine1Year1          = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year1']);
		 $OtherNonDistributableReservesLine1Year2          = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year2']);
		 $OtherNonDistributableReservesLine1Year3          = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year3']);
		 
	     $OtherNonDistributableReservesLine2Year0          = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year0']);
		 $OtherNonDistributableReservesLine2Year1          = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year1']);
		 $OtherNonDistributableReservesLine2Year2          = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year2']);
		 $OtherNonDistributableReservesLine2Year3          = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year3']);

		 $OtherNonDistributableReservesLine3Year0          = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year0']);
		 $OtherNonDistributableReservesLine3Year1          = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year1']);
		 $OtherNonDistributableReservesLine3Year2          = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year2']);
		 $OtherNonDistributableReservesLine3Year3          = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year3']);
		 
		 $OtherDistributableReservesLine1Year0             = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year0']);
		 $OtherDistributableReservesLine1Year1             = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year1']);
		 $OtherDistributableReservesLine1Year2             = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year2']);
		 $OtherDistributableReservesLine1Year3             = str_replace(",","",$_POST['OtherNonDistributableReservesLine1Year3']);
		 
	     $OtherDistributableReservesLine2Year0             = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year0']);
		 $OtherDistributableReservesLine2Year1             = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year1']);
		 $OtherDistributableReservesLine2Year2             = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year2']);
		 $OtherDistributableReservesLine2Year3             = str_replace(",","",$_POST['OtherNonDistributableReservesLine2Year3']);

		 $OtherDistributableReservesLine3Year0             = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year0']);
		 $OtherDistributableReservesLine3Year1             = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year1']);
		 $OtherDistributableReservesLine3Year2             = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year2']);
		 $OtherDistributableReservesLine3Year3             = str_replace(",","",$_POST['OtherNonDistributableReservesLine3Year3']);
		 
		 $OtherNonDistributableReservesDescLine1           = $_POST['OtherNonDistributableReservesDescLine1'];
		 $OtherNonDistributableReservesDescLine2           = $_POST['OtherNonDistributableReservesDescLine2'];
		 $OtherNonDistributableReservesDescLine3           = $_POST['OtherNonDistributableReservesDescLine3'];
		 
		 $OtherDistributableReservesDescLine1              = $_POST['OtherDistributableReservesDescLine1'];
		 $OtherDistributableReservesDescLine2              = $_POST['OtherDistributableReservesDescLine2'];
		 $OtherDistributableReservesDescLine3              = $_POST['OtherDistributableReservesDescLine3'];
		 
		 $NetProfitYear0                                   = str_replace(",","",$_POST['NetProfitYear0']);
		 $NetProfitYear1                                   = str_replace(",","",$_POST['NetProfitYear1']);
		 $NetProfitYear2                                   = str_replace(",","",$_POST['NetProfitYear2']);
		 $NetProfitYear3                                   = str_replace(",","",$_POST['NetProfitYear3']);
		 
         $NetProfitYear0                                   = str_replace(",","",$_POST['NetProfitYear0']);
		 $NetProfitYear1                                   = str_replace(",","",$_POST['NetProfitYear1']);
		 $NetProfitYear2                                   = str_replace(",","",$_POST['NetProfitYear2']);
		 $NetProfitYear3                                   = str_replace(",","",$_POST['NetProfitYear3']);
		 
		 $DividendsYear0                                   = str_replace(",","",$_POST['DividendsYear0']);
		 $DividendsYear1                                   = str_replace(",","",$_POST['DividendsYear1']);
		 $DividendsYear2                                   = str_replace(",","",$_POST['DividendsYear2']);
		 $DividendsYear3                                   = str_replace(",","",$_POST['DividendsYear3']);
		
	     $RetainedProfitsAdjustmentsYear0                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear0']);
		 $RetainedProfitsAdjustmentsYear1                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear1']);
		 $RetainedProfitsAdjustmentsYear2                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear2']);
		 $RetainedProfitsAdjustmentsYear3                  = str_replace(",","",$_POST['RetainedProfitsAdjustmentsYear3']);
	     
		 
		 $OtherDistributableReservesTotalYear0             = str_replace(",","",$_POST['OtherDistributableReservesTotalYear0']);
		 $OtherDistributableReservesTotalYear1             = str_replace(",","",$_POST['OtherDistributableReservesTotalYear1']);
		 $OtherDistributableReservesTotalYear2             = str_replace(",","",$_POST['OtherDistributableReservesTotalYear2']);
		 $OtherDistributableReservesTotalYear3             = str_replace(",","",$_POST['OtherDistributableReservesTotalYear3']);
 	 	
		 $OtherNonDistributableReservesTotalYear0          = str_replace(",","",$_POST['OtherNonDistributableReservesTotalYear0']);
		 $OtherNonDistributableReservesTotalYear1          = str_replace(",","",$_POST['OtherNonDistributableReservesTotalYear1']);
		 $OtherNonDistributableReservesTotalYear2          = str_replace(",","",$_POST['OtherNonDistributableReservesTotalYear2']);
		 $OtherNonDistributableReservesTotalYear3          = str_replace(",","",$_POST['OtherNonDistributableReservesTotalYear3']);
		 
	     $TotalEquityYear0                                 = str_replace(",","",$_POST['TotalEquityYear0']);
		 $TotalEquityYear1                                 = str_replace(",","",$_POST['TotalEquityYear1']);
		 $TotalEquityYear2                                 = str_replace(",","",$_POST['TotalEquityYear2']);
		 $TotalEquityYear3                                 = str_replace(",","",$_POST['TotalEquityYear3']);
	
    
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
			line_desc = 'ShareCapitalYear1',
			reporting_year = '$ShareCapitalYear1',
			amount = '$ShareCapitalYear1',
			comment = '$OtherDistributableReservesComments'
		    ";
  **/
        //$application_ref = $_POST['application_ref];
		//$username = $_POST['username'];
		//$company_reg_no = $_POST['company_reg_no'];
        
		$application_ref =$_POST['application_ref'];
		$username = $_POST['username'];
		$report_name = "Equity";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
		 //========================UPDATE THE PROGRESS TRACKER - Equity Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Equity' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Equity		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   equity_pdate    = now(), 
		   equity_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 		
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                                ,line_desc2                                         ,reporting_year     , amount)
			VALUES 
			  /** EQUITY **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareCapital'                           ,'$blank'                                           ,'$reporting_year0' ,'$ShareCapitalYear0'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareCapital'                           ,'$blank'                                           ,'$reporting_year1' ,'$ShareCapitalYear1'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareCapital'                           ,'$blank'                                           ,'$reporting_year2' ,'$ShareCapitalYear2'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareCapital'                           ,'$blank'                                           ,'$reporting_year3' ,'$ShareCapitalYear3'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','SharePremium'                           ,'$blank'                                           ,'$reporting_year0' ,'$SharePremiumYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','SharePremium'                           ,'$blank'                                           ,'$reporting_year1' ,'$SharePremiumYear1'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','SharePremium'                           ,'$blank'                                           ,'$reporting_year2' ,'$SharePremiumYear2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','SharePremium'                           ,'$blank'                                           ,'$reporting_year3' ,'$SharePremiumYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RevaluationReserve'                     ,'$blank'                                           ,'$reporting_year0' ,'$RevaluationReserveYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RevaluationReserve'                     ,'$blank'                                           ,'$reporting_year1' ,'$RevaluationReserveYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RevaluationReserve'                     ,'$blank'                                           ,'$reporting_year2' ,'$RevaluationReserveYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RevaluationReserve'                     ,'$blank'                                           ,'$reporting_year3' ,'$RevaluationReserveYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingRetainedProfits'                 ,'$blank'                                           ,'$reporting_year0' ,'$ClosingRetainedProfitsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingRetainedProfits'                 ,'$blank'                                           ,'$reporting_year1' ,'$ClosingRetainedProfitsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingRetainedProfits'                 ,'$blank'                                           ,'$reporting_year2' ,'$ClosingRetainedProfitsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingRetainedProfits'                 ,'$blank'                                           ,'$reporting_year3' ,'$ClosingRetainedProfitsYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningRetainedProfits'                 ,'$blank'                                           ,'$reporting_year0' ,'$OpeningRetainedProfitsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningRetainedProfits'                 ,'$blank'                                           ,'$reporting_year1' ,'$OpeningRetainedProfitsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningRetainedProfits'                 ,'$blank'                                           ,'$reporting_year2' ,'$OpeningRetainedProfitsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningRetainedProfits'                 ,'$blank'                                           ,'$reporting_year3' ,'$OpeningRetainedProfitsYear3'),
			  	  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine1'     ,'$OtherNonDistributableReservesDescLine1'          ,'$reporting_year0' ,'$OtherNonDistributableReservesLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine1'     ,'$OtherNonDistributableReservesDescLine1'          ,'$reporting_year1' ,'$OtherNonDistributableReservesLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine1'     ,'$OtherNonDistributableReservesDescLine1'          ,'$reporting_year2' ,'$OtherNonDistributableReservesLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine1'     ,'$OtherNonDistributableReservesDescLine1'          ,'$reporting_year3' ,'$OtherNonDistributableReservesLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine2'     ,'$OtherNonDistributableReservesDescLine2'          ,'$reporting_year0' ,'$OtherNonDistributableReservesLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine2'     ,'$OtherNonDistributableReservesDescLine2'          ,'$reporting_year1' ,'$OtherNonDistributableReservesLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine2'     ,'$OtherNonDistributableReservesDescLine2'          ,'$reporting_year2' ,'$OtherNonDistributableReservesLine2Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine2'     ,'$OtherNonDistributableReservesDescLine2'          ,'$reporting_year3' ,'$OtherNonDistributableReservesLine2Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine3'     ,'$OtherNonDistributableReservesDescLine3'          ,'$reporting_year0' ,'$OtherNonDistributableReservesLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine3'     ,'$OtherNonDistributableReservesDescLine3'          ,'$reporting_year1' ,'$OtherNonDistributableReservesLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine3'     ,'$OtherNonDistributableReservesDescLine3'          ,'$reporting_year2' ,'$OtherNonDistributableReservesLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesLine3'     ,'$OtherNonDistributableReservesDescLine3'          ,'$reporting_year3' ,'$OtherNonDistributableReservesLine3Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine1'        ,'$OtherDistributableReservesDescLine1'             ,'$reporting_year0' ,'$OtherDistributableReservesLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine1'        ,'$OtherDistributableReservesDescLine1'             ,'$reporting_year1' ,'$OtherDistributableReservesLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine1'        ,'$OtherDistributableReservesDescLine1'             ,'$reporting_year2' ,'$OtherDistributableReservesLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine1'        ,'$OtherDistributableReservesDescLine1'             ,'$reporting_year3' ,'$OtherDistributableReservesLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine2'        ,'$OtherDistributableReservesDescLine2'             ,'$reporting_year0' ,'$OtherDistributableReservesLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine2'        ,'$OtherDistributableReservesDescLine2'             ,'$reporting_year1' ,'$OtherDistributableReservesLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine2'        ,'$OtherDistributableReservesDescLine2'             ,'$reporting_year2' ,'$OtherDistributableReservesLine2Year2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine2'        ,'$OtherDistributableReservesDescLine2'             ,'$reporting_year3' ,'$OtherDistributableReservesLine2Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine3'        ,'$OtherDistributableReservesDescLine3'             ,'$reporting_year0' ,'$OtherDistributableReservesLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine3'        ,'$OtherDistributableReservesDescLine3'             ,'$reporting_year1' ,'$OtherDistributableReservesLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine3'        ,'$OtherDistributableReservesDescLine3'             ,'$reporting_year2' ,'$OtherDistributableReservesLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesLine3'        ,'$OtherDistributableReservesDescLine3'             ,'$reporting_year3' ,'$OtherDistributableReservesLine3Year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                              ,'$blank'                                            ,'$reporting_year0' ,'$NetProfitYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                              ,'$blank'                                            ,'$reporting_year1' ,'$NetProfitYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                              ,'$blank'                                            ,'$reporting_year2' ,'$NetProfitYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                              ,'$blank'                                            ,'$reporting_year3' ,'$NetProfitYear3'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Dividends'                              ,'$blank'                                            ,'$reporting_year0' ,'$DividendsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Dividends'                              ,'$blank'                                            ,'$reporting_year1' ,'$DividendsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Dividends'                              ,'$blank'                                            ,'$reporting_year2' ,'$DividendsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Dividends'                              ,'$blank'                                            ,'$reporting_year3' ,'$DividendsYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RetainedProfitsAdjustments'             ,'$blank'                                           ,'$reporting_year0' ,'$RetainedProfitsAdjustmentsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RetainedProfitsAdjustments'             ,'$blank'                                           ,'$reporting_year1' ,'$RetainedProfitsAdjustmentsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RetainedProfitsAdjustments'             ,'$blank'                                           ,'$reporting_year2' ,'$RetainedProfitsAdjustmentsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','RetainedProfitsAdjustments'             ,'$blank'                                           ,'$reporting_year3' ,'$RetainedProfitsAdjustmentsYear3'),
	   	      
			  			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesTotal'        ,'$blank'                                           ,'$reporting_year0' ,'$OtherDistributableReservesTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesTotal'        ,'$blank'                                           ,'$reporting_year1' ,'$OtherDistributableReservesTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesTotal'        ,'$blank'                                           ,'$reporting_year2' ,'$OtherDistributableReservesTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherDistributableReservesTotal'        ,'$blank'                                           ,'$reporting_year3' ,'$OtherDistributableReservesTotalYear3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesTotal'     ,'$blank'                                           ,'$reporting_year0' ,'$OtherNonDistributableReservesTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesTotal'     ,'$blank'                                           ,'$reporting_year1' ,'$OtherNonDistributableReservesTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesTotal'     ,'$blank'                                           ,'$reporting_year2' ,'$OtherNonDistributableReservesTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonDistributableReservesTotal'     ,'$blank'                                           ,'$reporting_year3' ,'$OtherNonDistributableReservesTotalYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalEquity'                            ,'$blank'                                          ,'$reporting_year0' ,'$TotalEquityYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalEquity'                            ,'$blank'                                          ,'$reporting_year1' ,'$TotalEquityYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalEquity'                            ,'$blank'                                          ,'$reporting_year2' ,'$TotalEquityYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalEquity'                            ,'$blank'                                          ,'$reporting_year3' ,'$TotalEquityYear3')";
	
	
	
	    $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting equity data'. mysqli_error();
          }
        else
	      {
		 //========================UPDATE THE PROGRESS TRACKER - Equity Data Section================================================================================
		  $progress_tracker_insert_query =  "INSERT IGNORE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username   , date_saved )
			  VALUES
		  ('$application_ref','$company_reg_no' ,'Equity Data' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 

		  if (!$resultm)
          {                                                    
             echo 'error with inserting equity data record on progress tracker'. mysqli_error();
          }


          header("Location:CorporateAddMenu.php");
          exit;
	      }
 ?>