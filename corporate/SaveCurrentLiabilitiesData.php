
<html>

<head>
<Title>Saving Non Current Liabilities Data</Title>
</head>

<body>
    <?php
      
	 
	 
	    //VARIABLE INITIALISATION===========================================================================================================
	     $BankOverdraftYear0= null;
	     $BankOverdraftYear1= null;
	     $BankOverdraftYear2= null;
	     $BankOverdraftYear3= null;
		 
		 $AccountsPayableYear0=null;
		 $AccountsPayableYear1=null;
		 $AccountsPayableYear2=null;
		 $AccountsPayableYear3=null;
		 
		 $AccrualsYear0 = null;
		 $AccrualsYear1 = null;
		 $AccrualsYear2 = null;
		 $AccrualsYear3 = null;
		 
		 $TaxPayableYear0 = null;
		 $TaxPayableYear1 = null;
		 $TaxPayableYear2 = null;
		 $TaxPayableYear3 = null;
		 
		 $DividendsPayableYear0 = null;
		 $DividendsPayableYear1 = null;
		 $DividendsPayableYear2 = null;
		 $DividendsPayableYear3 = null;

		 $CurrentPortionLongTermDebtYear0 = null;
		 $CurrentPortionLongTermDebtYear1 = null;
		 $CurrentPortionLongTermDebtYear2 = null;
		 $CurrentPortionLongTermDebtYear3 = null;

		 $OtherCurrentLiabilitiesNonInterCompanyLine1Year0 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine1Year1 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine1Year2 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine1Year3 = null;
		 
		 $OtherCurrentLiabilitiesNonInterCompanyLine2Year0 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine2Year1 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine2Year2 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine2Year3 = null;

		 $OtherCurrentLiabilitiesNonInterCompanyLine3Year0 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine3Year1 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine3Year2 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyLine3Year3 = null;
 
 	     $OtherCurrentLiabilitiesInterCompanyLine1Year0 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine1Year1 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine1Year2 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine1Year3 = null;
		 
		 $OtherCurrentLiabilitiesInterCompanyLine2Year0 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine2Year1 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine2Year2 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine2Year3 = null;

		 $OtherCurrentLiabilitiesInterCompanyLine3Year0 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine3Year1 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine3Year2 = null;
		 $OtherCurrentLiabilitiesInterCompanyLine3Year3 = null;
	     
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine0 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine1 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine2 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine3 = null;
		 
		 $OtherCurrentLiabilitiesInterCompanyDescLine0 = null;
		 $OtherCurrentLiabilitiesInterCompanyDescLine1 = null;
		 $OtherCurrentLiabilitiesInterCompanyDescLine2 = null;
		 $OtherCurrentLiabilitiesInterCompanyDescLine3 = null;
		 
		 $OtherCurrentLiabilitiesInterCompanyTotalYear0 = null;
		 $OtherCurrentLiabilitiesInterCompanyTotalYear1 = null;
		 $OtherCurrentLiabilitiesInterCompanyTotalYear2 = null;
		 $OtherCurrentLiabilitiesInterCompanyTotalYear3 = null;
 	 
	     $OtherCurrentLiabilitiesNonInterCompanyTotalYear0 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear1 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear2 = null;
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear3 = null;
 
	     $TotalCurrentLiabilitiesYear0 = null;
		 $TotalCurrentLiabilitiesYear1 = null;
		 $TotalCurrentLiabilitiesYear2 = null;
		 $TotalCurrentLiabilitiesYear3 = null;
		 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $BankOverdraftYear0= str_replace(",","",$_POST['BankOverdraftYear0']);
	     $BankOverdraftYear1= str_replace(",","",$_POST['BankOverdraftYear1']);
	     $BankOverdraftYear2= str_replace(",","",$_POST['BankOverdraftYear2']);
	     $BankOverdraftYear3= str_replace(",","",$_POST['BankOverdraftYear3']);
		
	   	 $AccountsPayableYear0=str_replace(",","",$_POST['AccountsPayableYear0']);
		 $AccountsPayableYear1=str_replace(",","",$_POST['AccountsPayableYear1']);
		 $AccountsPayableYear2=str_replace(",","",$_POST['AccountsPayableYear2']);
		 $AccountsPayableYear3=str_replace(",","",$_POST['AccountsPayableYear3']);
		 
		 $AccrualsYear0 = str_replace(",","",$_POST['AccrualsYear0']);
		 $AccrualsYear1 = str_replace(",","",$_POST['AccrualsYear1']);
		 $AccrualsYear2 = str_replace(",","",$_POST['AccrualsYear2']);
		 $AccrualsYear3 = str_replace(",","",$_POST['AccrualsYear3']);
		 
		 $TaxPayableYear0 = str_replace(",","",$_POST['TaxPayableYear0']);
		 $TaxPayableYear1 = str_replace(",","",$_POST['TaxPayableYear1']);
		 $TaxPayableYear2 = str_replace(",","",$_POST['TaxPayableYear2']);
		 $TaxPayableYear3 = str_replace(",","",$_POST['TaxPayableYear3']);
		 
		 $DividendsPayableYear0 = str_replace(",","",$_POST['DividendsPayableYear0']);
		 $DividendsPayableYear1 = str_replace(",","",$_POST['DividendsPayableYear1']);
		 $DividendsPayableYear2 = str_replace(",","",$_POST['DividendsPayableYear2']);
		 $DividendsPayableYear3 = str_replace(",","",$_POST['DividendsPayableYear3']);
		    
		 $CurrentPortionLongTermDebtYear0 = str_replace(",","",$_POST['CurrentPortionLongTermDebtYear0']);
		 $CurrentPortionLongTermDebtYear1 = str_replace(",","",$_POST['CurrentPortionLongTermDebtYear1']);
		 $CurrentPortionLongTermDebtYear2 = str_replace(",","",$_POST['CurrentPortionLongTermDebtYear2']);
		 $CurrentPortionLongTermDebtYear3 = str_replace(",","",$_POST['CurrentPortionLongTermDebtYear3']);
			

			
		 $OtherCurrentLiabilitiesNonIntercompanyLine1Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year0']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine1Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year1']);
		 
		 $OtherCurrentLiabilitiesNonIntercompanyLine1Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year2']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine1Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year3']);
		 
	     $OtherCurrentLiabilitiesNonIntercompanyLine2Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year0']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine2Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year1']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine2Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year2']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine2Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year3']);

		 $OtherCurrentLiabilitiesNonIntercompanyLine3Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year0']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine3Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year1']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine3Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year2']);
		 $OtherCurrentLiabilitiesNonIntercompanyLine3Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year3']);
		 
		 $OtherCurrentLiabilitiesIntercompanyLine1Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year0']);
		 $OtherCurrentLiabilitiesIntercompanyLine1Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year1']);
		 $OtherCurrentLiabilitiesIntercompanyLine1Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year2']);
		 $OtherCurrentLiabilitiesIntercompanyLine1Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine1Year3']);
		 
	     $OtherCurrentLiabilitiesIntercompanyLine2Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year0']);
		 $OtherCurrentLiabilitiesIntercompanyLine2Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year1']);
		 $OtherCurrentLiabilitiesIntercompanyLine2Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year2']);
		 $OtherCurrentLiabilitiesIntercompanyLine2Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine2Year3']);

		 $OtherCurrentLiabilitiesIntercompanyLine3Year0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year0']);
		 $OtherCurrentLiabilitiesIntercompanyLine3Year1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year1']);
		 $OtherCurrentLiabilitiesIntercompanyLine3Year2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year2']);
		 $OtherCurrentLiabilitiesIntercompanyLine3Year3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyLine3Year3']);
		 
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine1 = $_POST['OtherCurrentLiabilitiesNonInterCompanyDescLine1'];
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine2 = $_POST['OtherCurrentLiabilitiesNonInterCompanyDescLine2'];
		 $OtherCurrentLiabilitiesNonInterCompanyDescLine3 = $_POST['OtherCurrentLiabilitiesNonInterCompanyDescLine3'];
		 
		 $OtherCurrentLiabilitiesInterCompanyDescLine1 = $_POST['OtherCurrentLiabilitiesInterCompanyDescLine1'];
		 $OtherCurrentLiabilitiesInterCompanyDescLine2 = $_POST['OtherCurrentLiabilitiesInterCompanyDescLine2'];
		 $OtherCurrentLiabilitiesInterCompanyDescLine3 = $_POST['OtherCurrentLiabilitiesInterCompanyDescLine3'];
		 
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyTotalYear0']);
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyTotalYear1']);
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyTotalYear2']);
		 $OtherCurrentLiabilitiesNonInterCompanyTotalYear3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesNonInterCompanyTotalYear3']);

		 $OtherCurrentLiabilitiesInterCompanyTotalYear0 = str_replace(",","",$_POST['OtherCurrentLiabilitiesInterCompanyTotalYear0']);
		 $OtherCurrentLiabilitiesInterCompanyTotalYear1 = str_replace(",","",$_POST['OtherCurrentLiabilitiesInterCompanyTotalYear1']);
		 $OtherCurrentLiabilitiesInterCompanyTotalYear2 = str_replace(",","",$_POST['OtherCurrentLiabilitiesInterCompanyTotalYear2']);
		 $OtherCurrentLiabilitiesInterCompanyTotalYear3 = str_replace(",","",$_POST['OtherCurrentLiabilitiesInterCompanyTotalYear3']);
		 
		 $TotalCurrentLiabilitiesYear0 = str_replace(",","",$_POST['TotalCurrentLiabilitiesYear0']);
		 $TotalCurrentLiabilitiesYear1 = str_replace(",","",$_POST['TotalCurrentLiabilitiesYear1']);
		 $TotalCurrentLiabilitiesYear2 = str_replace(",","",$_POST['TotalCurrentLiabilitiesYear2']);
		 $TotalCurrentLiabilitiesYear3 = str_replace(",","",$_POST['TotalCurrentLiabilitiesYear3']);
		 
	 

	 
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
			line_desc = 'BankOverdraftYear1',
			reporting_year = '$BankOverdraftYear1',
			amount = '$BankOverdraftYear1',
			comment = '$OtherNonCurrentLiabilitiesComments'
		    ";
  **/
        //$application_ref = $_POST['application_ref];
		//$username = $_POST['username'];
		//$company_reg_no = $_POST['company_reg_no'];
        
		$application_ref =$_POST['application_ref'];
		$username = $_POST['username'];
		$report_name = "CurrentLiabilities";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
		//========================UPDATE THE PROGRESS TRACKER - Current Liabilities Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Current Liabilities' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		//================================================================================================================================
		 
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                                    ,line_desc2                                         ,reporting_year     , amount)
			VALUES 
			  /** Current Liabilities **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','BankOverdraft'                               ,'$blank'                                           ,'$reporting_year0' ,'$BankOverdraftYear0'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','BankOverdraft'                               ,'$blank'                                           ,'$reporting_year1' ,'$BankOverdraftYear1'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','BankOverdraft'                               ,'$blank'                                           ,'$reporting_year2' ,'$BankOverdraftYear2'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','BankOverdraft'                               ,'$blank'                                           ,'$reporting_year3' ,'$BankOverdraftYear3'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsPayable'                             ,'$blank'                                           ,'$reporting_year0' ,'$AccountsPayableYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsPayable'                             ,'$blank'                                           ,'$reporting_year1' ,'$AccountsPayableYear1'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsPayable'                             ,'$blank'                                           ,'$reporting_year2' ,'$AccountsPayableYear2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsPayable'                             ,'$blank'                                           ,'$reporting_year3' ,'$AccountsPayableYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Accruals'                                    ,'$blank'                                           ,'$reporting_year0' ,'$AccrualsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Accruals'                                    ,'$blank'                                           ,'$reporting_year1' ,'$AccrualsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Accruals'                                    ,'$blank'                                           ,'$reporting_year2' ,'$AccrualsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Accruals'                                    ,'$blank'                                           ,'$reporting_year3' ,'$AccrualsYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TaxPayable'                                  ,'$blank'                                           ,'$reporting_year0' ,'$TaxPayableYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TaxPayable'                                  ,'$blank'                                           ,'$reporting_year1' ,'$TaxPayableYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TaxPayable'                                  ,'$blank'                                           ,'$reporting_year2' ,'$TaxPayableYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TaxPayable'                                  ,'$blank'                                           ,'$reporting_year3' ,'$TaxPayableYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DividendsPayable'                            ,'$blank'                                           ,'$reporting_year0' ,'$DividendsPayableYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DividendsPayable'                            ,'$blank'                                           ,'$reporting_year1' ,'$DividendsPayableYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DividendsPayable'                            ,'$blank'                                           ,'$reporting_year2' ,'$DividendsPayableYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DividendsPayable'                            ,'$blank'                                           ,'$reporting_year3' ,'$DividendsPayableYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$blank'                                           ,'$reporting_year0' ,'$CurrentPortionLongTermDebtYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$blank'                                           ,'$reporting_year1' ,'$CurrentPortionLongTermDebtYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$blank'                                           ,'$reporting_year2' ,'$CurrentPortionLongTermDebtYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CurrentPortionLongTermDebt'                  ,'$blank'                                           ,'$reporting_year3' ,'$CurrentPortionLongTermDebtYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine1' ,'$reporting_year0' ,'$OtherCurrentLiabilitiesNonIntercompanyLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine1' ,'$reporting_year1' ,'$OtherCurrentLiabilitiesNonIntercompanyLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine1' ,'$reporting_year2' ,'$OtherCurrentLiabilitiesNonIntercompanyLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine1' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine1' ,'$reporting_year3' ,'$OtherCurrentLiabilitiesNonIntercompanyLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine2' ,'$reporting_year0' ,'$OtherCurrentLiabilitiesNonIntercompanyLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine2' ,'$reporting_year1' ,'$OtherCurrentLiabilitiesNonIntercompanyLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine2' ,'$reporting_year2' ,'$OtherCurrentLiabilitiesNonIntercompanyLine2Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine2' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine2' ,'$reporting_year3' ,'$OtherCurrentLiabilitiesNonIntercompanyLine2Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine3' ,'$reporting_year0' ,'$OtherCurrentLiabilitiesNonIntercompanyLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine3' ,'$reporting_year1' ,'$OtherCurrentLiabilitiesNonIntercompanyLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine3' ,'$reporting_year2' ,'$OtherCurrentLiabilitiesNonIntercompanyLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyLine3' ,'$OtherCurrentLiabilitiesNonInterCompanyDescLine3' ,'$reporting_year3' ,'$OtherCurrentLiabilitiesNonIntercompanyLine3Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine1'    ,'$reporting_year0' ,'$OtherCurrentLiabilitiesIntercompanyLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine1'    ,'$reporting_year1' ,'$OtherCurrentLiabilitiesIntercompanyLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine1'    ,'$reporting_year2' ,'$OtherCurrentLiabilitiesIntercompanyLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine1'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine1'    ,'$reporting_year3' ,'$OtherCurrentLiabilitiesIntercompanyLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine2'    ,'$reporting_year0' ,'$OtherCurrentLiabilitiesIntercompanyLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine2'    ,'$reporting_year1' ,'$OtherCurrentLiabilitiesIntercompanyLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine2'    ,'$reporting_year2' ,'$OtherCurrentLiabilitiesIntercompanyLine2Year2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine2'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine2'    ,'$reporting_year3' ,'$OtherCurrentLiabilitiesIntercompanyLine2Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine3'    ,'$reporting_year0' ,'$OtherCurrentLiabilitiesIntercompanyLine3Year0'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine3'    ,'$reporting_year1' ,'$OtherCurrentLiabilitiesIntercompanyLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine3'    ,'$reporting_year2' ,'$OtherCurrentLiabilitiesIntercompanyLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyLine3'    ,'$OtherCurrentLiabilitiesInterCompanyDescLine3'    ,'$reporting_year3'  ,'$OtherCurrentLiabilitiesIntercompanyLine3Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$blank'                                           ,'$reporting_year0' ,'$OtherCurrentLiabilitiesNonInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$blank'                                           ,'$reporting_year1' ,'$OtherCurrentLiabilitiesNonInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$blank'                                           ,'$reporting_year2' ,'$OtherCurrentLiabilitiesNonInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesNonIntercompanyTotal' ,'$blank'                                           ,'$reporting_year3' ,'$OtherCurrentLiabilitiesNonInterCompanyTotalYear3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$blank'                                           ,'$reporting_year0' ,'$OtherCurrentLiabilitiesInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$blank'                                           ,'$reporting_year1' ,'$OtherCurrentLiabilitiesInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$blank'                                           ,'$reporting_year2' ,'$OtherCurrentLiabilitiesInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentLiabilitiesIntercompanyTotal'    ,'$blank'                                           ,'$reporting_year3' ,'$OtherCurrentLiabilitiesInterCompanyTotalYear3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentLiabilities'                     ,'$blank'                                           ,'$reporting_year0' ,'$TotalCurrentLiabilitiesYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentLiabilities'                     ,'$blank'                                           ,'$reporting_year1' ,'$TotalCurrentLiabilitiesYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentLiabilities'                     ,'$blank'                                           ,'$reporting_year2' ,'$TotalCurrentLiabilitiesYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentLiabilities'                     ,'$blank'                                           ,'$reporting_year3' ,'$TotalCurrentLiabilitiesYear3')";
	
	
	
	
	    $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
		 //========================UPDATE THE PROGRESS TRACKER - Current Liabilities Section================================================================================
		  $progress_tracker_insert_query =  "INSERT IGNORE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section            ,   username   , date_saved )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Current Liabilities' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 

		  if (!$resultm)
          {                                                    
             echo 'error with inserting current assets record on progress tracker'. mysqli_error();
          }
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Current Liabilities Data Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   current_liabilities_pdate    = now(), 
		   current_liabilities_username = 'username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
	       
	   
	   
	   header("Location:CorporateAddMenu.php");
       exit;
	   }
 ?>
</body>

</html>