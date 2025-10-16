
<html>

<head>
<Title>Saving Non Current Assets Data</Title>
</head>

<body>
    <?php
      
	 
	 
	    //VARIABLE INITIALISATION===========================================================================================================
	     $CashBalYear0= null;
	     $CashBalYear1= null;
	     $CashBalYear2= null;
	     $CashBalYear3= null;
		 
		 $AccountsReceivableYear0=null;
		 $AccountsReceivableYear1=null;
		 $AccountsReceivableYear2=null;
		 $AccountsReceivableYear3=null;
		 
		 $PrepaymentsYear0 = null;
		 $PrepaymentsYear1 = null;
		 $PrepaymentsYear2 = null;
		 $PrepaymentsYear3 = null;
		 
		 $PrepaidTaxYear0 = null;
		 $PrepaidTaxYear1 = null;
		 $PrepaidTaxYear2 = null;
		 $PrepaidTaxYear3 = null;
		 
		 $InventoryYear0 = null;
		 $InventoryYear1 = null;
		 $InventoryYear2 = null;
		 $InventoryYear3 = null;

		 $OtherCurrentAssetsNonInterCompanyLine1Year0 = null;
		 $OtherCurrentAssetsNonInterCompanyLine1Year1 = null;
		 $OtherCurrentAssetsNonInterCompanyLine1Year2 = null;
		 $OtherCurrentAssetsNonInterCompanyLine1Year3 = null;
		 
		 $OtherCurrentAssetsNonInterCompanyLine2Year0 = null;
		 $OtherCurrentAssetsNonInterCompanyLine2Year1 = null;
		 $OtherCurrentAssetsNonInterCompanyLine2Year2 = null;
		 $OtherCurrentAssetsNonInterCompanyLine2Year3 = null;

		 $OtherCurrentAssetsNonInterCompanyLine3Year0 = null;
		 $OtherCurrentAssetsNonInterCompanyLine3Year1 = null;
		 $OtherCurrentAssetsNonInterCompanyLine3Year2 = null;
		 $OtherCurrentAssetsNonInterCompanyLine3Year3 = null;
 
 	     $OtherCurrentAssetsInterCompanyLine1Year0 = null;
		 $OtherCurrentAssetsInterCompanyLine1Year1 = null;
		 $OtherCurrentAssetsInterCompanyLine1Year2 = null;
		 $OtherCurrentAssetsInterCompanyLine1Year3 = null;
		 
		 $OtherCurrentAssetsInterCompanyLine2Year0 = null;
		 $OtherCurrentAssetsInterCompanyLine2Year1 = null;
		 $OtherCurrentAssetsInterCompanyLine2Year2 = null;
		 $OtherCurrentAssetsInterCompanyLine2Year3 = null;

		 $OtherCurrentAssetsInterCompanyLine3Year0 = null;
		 $OtherCurrentAssetsInterCompanyLine3Year1 = null;
		 $OtherCurrentAssetsInterCompanyLine3Year2 = null;
		 $OtherCurrentAssetsInterCompanyLine3Year3 = null;
	     
		 $OtherCurrentAssetsNonInterCompanyDescLine0 = null;
		 $OtherCurrentAssetsNonInterCompanyDescLine1 = null;
		 $OtherCurrentAssetsNonInterCompanyDescLine2 = null;
		 $OtherCurrentAssetsNonInterCompanyDescLine3 = null;
		 
		 $OtherCurrentAssetsInterCompanyDescLineYear0 = null;
		 $OtherCurrentAssetsInterCompanyDescLineYear1 = null;
		 $OtherCurrentAssetsInterCompanyDescLineYear2 = null;
		 $OtherCurrentAssetsInterCompanyDescLineYear3 = null;
		 
		 $OtherCurrentAssetsInterCompanyTotalYear0 = null;
		 $OtherCurrentAssetsInterCompanyTotalYear1 = null;
		 $OtherCurrentAssetsInterCompanyTotalYear2 = null;
		 $OtherCurrentAssetsInterCompanyTotalYear3 = null;
 	 
	     $OtherCurrentAssetsNonInterCompanyTotalYear0 = null;
		 $OtherCurrentAssetsNonInterCompanyTotalYear1 = null;
		 $OtherCurrentAssetsNonInterCompanyTotalYear2 = null;
		 $OtherCurrentAssetsNonInterCompanyTotalYear3 = null;
 
	     $TotalCurrentAssetsYear0 = null;
		 $TotalCurrentAssetsYear1 = null;
		 $TotalCurrentAssetsYear2 = null;
		 $TotalCurrentAssetsYear3 = null;
		 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $CashBalYear0= str_replace(",","",$_POST['CashBalYear0']);
	     $CashBalYear1= str_replace(",","",$_POST['CashBalYear1']);
	     $CashBalYear2= str_replace(",","",$_POST['CashBalYear2']);
	     $CashBalYear3= str_replace(",","",$_POST['CashBalYear3']);
		
	   	 $AccountsReceivableYear0=str_replace(",","",$_POST['AccountsReceivableYear0']);
		 $AccountsReceivableYear1=str_replace(",","",$_POST['AccountsReceivableYear1']);
		 $AccountsReceivableYear2=str_replace(",","",$_POST['AccountsReceivableYear2']);
		 $AccountsReceivableYear3=str_replace(",","",$_POST['AccountsReceivableYear3']);
		 
		 $PrepaymentsYear0 = str_replace(",","",$_POST['PrepaymentsYear0']);
		 $PrepaymentsYear1 = str_replace(",","",$_POST['PrepaymentsYear1']);
		 $PrepaymentsYear2 = str_replace(",","",$_POST['PrepaymentsYear2']);
		 $PrepaymentsYear3 = str_replace(",","",$_POST['PrepaymentsYear3']);
		 
		 $PrepaidTaxYear0 = str_replace(",","",$_POST['PrepaidTaxYear0']);
		 $PrepaidTaxYear1 = str_replace(",","",$_POST['PrepaidTaxYear1']);
		 $PrepaidTaxYear2 = str_replace(",","",$_POST['PrepaidTaxYear2']);
		 $PrepaidTaxYear3 = str_replace(",","",$_POST['PrepaidTaxYear3']);
		 
		 $InventoryYear0 = str_replace(",","",$_POST['InventoryYear0']);
		 $InventoryYear1 = str_replace(",","",$_POST['InventoryYear1']);
		 $InventoryYear2 = str_replace(",","",$_POST['InventoryYear2']);
		 $InventoryYear3 = str_replace(",","",$_POST['InventoryYear3']);
		                                                                          
		 $OtherCurrentAssetsNonIntercompanyLine1Year0 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine1Year0']);
		 $OtherCurrentAssetsNonIntercompanyLine1Year1 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine1Year1']);
		 
		 $OtherCurrentAssetsNonIntercompanyLine1Year2 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine1Year2']);
		 $OtherCurrentAssetsNonIntercompanyLine1Year3 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine1Year3']);
		 
	     $OtherCurrentAssetsNonIntercompanyLine2Year0 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine2Year0']);
		 $OtherCurrentAssetsNonIntercompanyLine2Year1 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine2Year1']);
		 $OtherCurrentAssetsNonIntercompanyLine2Year2 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine2Year2']);
		 $OtherCurrentAssetsNonIntercompanyLine2Year3 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine2Year3']);

		 $OtherCurrentAssetsNonIntercompanyLine3Year0 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine3Year0']);
		 $OtherCurrentAssetsNonIntercompanyLine3Year1 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine3Year1']);
		 $OtherCurrentAssetsNonIntercompanyLine3Year2 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine3Year2']);
		 $OtherCurrentAssetsNonIntercompanyLine3Year3 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyLine3Year3']);
		 
		 $OtherCurrentAssetsIntercompanyLine1Year0 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine1Year0']);
		 $OtherCurrentAssetsIntercompanyLine1Year1 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine1Year1']);
		 $OtherCurrentAssetsIntercompanyLine1Year2 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine1Year2']);
		 $OtherCurrentAssetsIntercompanyLine1Year3 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine1Year3']);
		 
	     $OtherCurrentAssetsIntercompanyLine2Year0 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine2Year0']);
		 $OtherCurrentAssetsIntercompanyLine2Year1 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine2Year1']);
		 $OtherCurrentAssetsIntercompanyLine2Year2 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine2Year2']);
		 $OtherCurrentAssetsIntercompanyLine2Year3 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine2Year3']);

		 $OtherCurrentAssetsIntercompanyLine3Year0 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine3Year0']);
		 $OtherCurrentAssetsIntercompanyLine3Year1 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine3Year1']);
		 $OtherCurrentAssetsIntercompanyLine3Year2 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine3Year2']);
		 $OtherCurrentAssetsIntercompanyLine3Year3 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyLine3Year3']);
		 
		 $OtherCurrentAssetsNonInterCompanyDescLine1 = $_POST['OtherCurrentAssetsNonInterCompanyDescLine1'];
		 $OtherCurrentAssetsNonInterCompanyDescLine2 = $_POST['OtherCurrentAssetsNonInterCompanyDescLine2'];
		 $OtherCurrentAssetsNonInterCompanyDescLine3 = $_POST['OtherCurrentAssetsNonInterCompanyDescLine3'];
		 
		 $OtherCurrentAssetsInterCompanyDescLine1 = $_POST['OtherCurrentAssetsInterCompanyDescLine1'];
		 $OtherCurrentAssetsInterCompanyDescLine2 = $_POST['OtherCurrentAssetsInterCompanyDescLine2'];
		 $OtherCurrentAssetsInterCompanyDescLine3 = $_POST['OtherCurrentAssetsInterCompanyDescLine3'];
		 
		 $OtherCurrentAssetsNonInterCompanyTotalYear0 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyTotalYear0']);
		 $OtherCurrentAssetsNonInterCompanyTotalYear1 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyTotalYear1']);
		 $OtherCurrentAssetsNonInterCompanyTotalYear2 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyTotalYear2']);
		 $OtherCurrentAssetsNonInterCompanyTotalYear3 = str_replace(",","",$_POST['OtherCurrentAssetsNonInterCompanyTotalYear3']);

		 $OtherCurrentAssetsInterCompanyTotalYear0 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyTotalYear0']);
		 $OtherCurrentAssetsInterCompanyTotalYear1 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyTotalYear1']);
		 $OtherCurrentAssetsInterCompanyTotalYear2 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyTotalYear2']);
		 $OtherCurrentAssetsInterCompanyTotalYear3 = str_replace(",","",$_POST['OtherCurrentAssetsInterCompanyTotalYear3']);
		 
		 $TotalCurrentAssetsYear0 = str_replace(",","",$_POST['TotalCurrentAssetsYear0']);
		 $TotalCurrentAssetsYear1 = str_replace(",","",$_POST['TotalCurrentAssetsYear1']);
		 $TotalCurrentAssetsYear2 = str_replace(",","",$_POST['TotalCurrentAssetsYear2']);
		 $TotalCurrentAssetsYear3 = str_replace(",","",$_POST['TotalCurrentAssetsYear3']);
		 
	 

	 
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

      
	  
	  
	  
	  //$application_ref = $_POST['application_ref];
		//$username = $_POST['username'];
		//$company_reg_no = $_POST['company_reg_no'];
        
		$application_ref =$_POST['application_ref'];
		$username = $_POST['username'];
		$report_name = "CurrentAssets";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
		 
		 //========================UPDATE THE PROGRESS TRACKER - Current Assets Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Current Assets' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Current Assets Data Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   current_assets_pdate    = now(), 
		   current_assets_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
			 
				 
		 
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                               ,line_desc2                                    ,reporting_year     , amount)
			VALUES 
			  /** Current Assets **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CashBal'                                ,'$blank'                                      ,'$reporting_year0' ,'$CashBalYear0'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CashBal'                                ,'$blank'                                      ,'$reporting_year1' ,'$CashBalYear1'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CashBal'                                ,'$blank'                                      ,'$reporting_year2' ,'$CashBalYear2'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CashBal'                                ,'$blank'                                      ,'$reporting_year3' ,'$CashBalYear3'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsReceivable'                     ,'$blank'                                      ,'$reporting_year0' ,'$AccountsReceivableYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsReceivable'                     ,'$blank'                                      ,'$reporting_year1' ,'$AccountsReceivableYear1'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsReceivable'                     ,'$blank'                                      ,'$reporting_year2' ,'$AccountsReceivableYear2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','AccountsReceivable'                     ,'$blank'                                      ,'$reporting_year3' ,'$AccountsReceivableYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Prepayments'                            ,'$blank'                                      ,'$reporting_year0' ,'$PrepaymentsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Prepayments'                            ,'$blank'                                      ,'$reporting_year1' ,'$PrepaymentsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Prepayments'                            ,'$blank'                                      ,'$reporting_year2' ,'$PrepaymentsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Prepayments'                            ,'$blank'                                      ,'$reporting_year3' ,'$PrepaymentsYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PrepaidTax'                             ,'$blank'                                      ,'$reporting_year0' ,'$PrepaidTaxYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PrepaidTax'                             ,'$blank'                                      ,'$reporting_year1' ,'$PrepaidTaxYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PrepaidTax'                             ,'$blank'                                      ,'$reporting_year2' ,'$PrepaidTaxYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PrepaidTax'                             ,'$blank'                                      ,'$reporting_year3' ,'$PrepaidTaxYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Inventory'                              ,'$blank'                                      ,'$reporting_year0' ,'$InventoryYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Inventory'                              ,'$blank'                                      ,'$reporting_year1' ,'$InventoryYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Inventory'                              ,'$blank'                                      ,'$reporting_year2' ,'$InventoryYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Inventory'                              ,'$blank'                                      ,'$reporting_year3' ,'$InventoryYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$OtherCurrentAssetsNonInterCompanyDescLine1' ,'$reporting_year0' ,'$OtherCurrentAssetsNonIntercompanyLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$OtherCurrentAssetsNonInterCompanyDescLine1' ,'$reporting_year1' ,'$OtherCurrentAssetsNonIntercompanyLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$OtherCurrentAssetsNonInterCompanyDescLine1' ,'$reporting_year2' ,'$OtherCurrentAssetsNonIntercompanyLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine1' ,'$OtherCurrentAssetsNonInterCompanyDescLine1' ,'$reporting_year3' ,'$OtherCurrentAssetsNonIntercompanyLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$OtherCurrentAssetsNonInterCompanyDescLine2' ,'$reporting_year0' ,'$OtherCurrentAssetsNonIntercompanyLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$OtherCurrentAssetsNonInterCompanyDescLine2' ,'$reporting_year1' ,'$OtherCurrentAssetsNonIntercompanyLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$OtherCurrentAssetsNonInterCompanyDescLine2' ,'$reporting_year2' ,'$OtherCurrentAssetsNonIntercompanyLine2Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine2' ,'$OtherCurrentAssetsNonInterCompanyDescLine2' ,'$reporting_year3' ,'$OtherCurrentAssetsNonIntercompanyLine2Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$OtherCurrentAssetsNonInterCompanyDescLine3' ,'$reporting_year0' ,'$OtherCurrentAssetsNonIntercompanyLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$OtherCurrentAssetsNonInterCompanyDescLine3' ,'$reporting_year1' ,'$OtherCurrentAssetsNonIntercompanyLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$OtherCurrentAssetsNonInterCompanyDescLine3' ,'$reporting_year2' ,'$OtherCurrentAssetsNonIntercompanyLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyLine3' ,'$OtherCurrentAssetsNonInterCompanyDescLine3' ,'$reporting_year3' ,'$OtherCurrentAssetsNonIntercompanyLine3Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$OtherCurrentAssetsInterCompanyDescLine1'    ,'$reporting_year0' ,'$OtherCurrentAssetsIntercompanyLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$OtherCurrentAssetsInterCompanyDescLine1'    ,'$reporting_year1' ,'$OtherCurrentAssetsIntercompanyLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$OtherCurrentAssetsInterCompanyDescLine1'    ,'$reporting_year2' ,'$OtherCurrentAssetsIntercompanyLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine1'    ,'$OtherCurrentAssetsInterCompanyDescLine1'    ,'$reporting_year3' ,'$OtherCurrentAssetsIntercompanyLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$OtherCurrentAssetsInterCompanyDescLine2'    ,'$reporting_year0' ,'$OtherCurrentAssetsIntercompanyLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$OtherCurrentAssetsInterCompanyDescLine2'    ,'$reporting_year1' ,'$OtherCurrentAssetsIntercompanyLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$OtherCurrentAssetsInterCompanyDescLine2'    ,'$reporting_year2' ,'$OtherCurrentAssetsIntercompanyLine2Year2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine2'    ,'$OtherCurrentAssetsInterCompanyDescLine2'    ,'$reporting_year3' ,'$OtherCurrentAssetsIntercompanyLine2Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$OtherCurrentAssetsInterCompanyDescLine3'    ,'$reporting_year0' ,'$OtherCurrentAssetsIntercompanyLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$OtherCurrentAssetsInterCompanyDescLine3'    ,'$reporting_year1' ,'$OtherCurrentAssetsIntercompanyLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$OtherCurrentAssetsInterCompanyDescLine3'    ,'$reporting_year2' ,'$OtherCurrentAssetsIntercompanyLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyLine3'    ,'$OtherCurrentAssetsInterCompanyDescLine3'    ,'$reporting_year3'  ,'$OtherCurrentAssetsIntercompanyLine3Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$blank'                                      ,'$reporting_year0' ,'$OtherCurrentAssetsNonInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$blank'                                      ,'$reporting_year1' ,'$OtherCurrentAssetsNonInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$blank'                                      ,'$reporting_year2' ,'$OtherCurrentAssetsNonInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsNonIntercompanyTotal' ,'$blank'                                      ,'$reporting_year3' ,'$OtherCurrentAssetsNonInterCompanyTotalYear3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$blank'                                      ,'$reporting_year0' ,'$OtherCurrentAssetsInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$blank'                                      ,'$reporting_year1' ,'$OtherCurrentAssetsInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$blank'                                      ,'$reporting_year2' ,'$OtherCurrentAssetsInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherCurrentAssetsIntercompanyTotal'    ,'$blank'                                      ,'$reporting_year3' ,'$OtherCurrentAssetsInterCompanyTotalYear3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentAssets'                     ,'$blank'                                      ,'$reporting_year0' ,'$TotalCurrentAssetsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentAssets'                     ,'$blank'                                      ,'$reporting_year1' ,'$TotalCurrentAssetsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentAssets'                     ,'$blank'                                      ,'$reporting_year2' ,'$TotalCurrentAssetsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalCurrentAssets'                     ,'$blank'                                      ,'$reporting_year3' ,'$TotalCurrentAssetsYear3')";
	
	
	
	
	$resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
		 //========================UPDATE THE PROGRESS TRACKER - Current Assets Section================================================================================
		  $progress_tracker_insert_query =  "INSERT IGNORE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username   , date_saved )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Current Assets' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 

		  if (!$resultm)
          {                                                    
             echo 'error with inserting current assets record on progress tracker'. mysqli_error();
          }
		     
			 header("Location:CorporateAddMenu.php");
             exit;
	      }
 ?>
</body>

</html>