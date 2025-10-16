
<html>

<head>
<Title>Saving Income Statement Entry Data</Title>
</head>

<body>
    <?php
      
	 
	 
	    //VARIABLE INITIALISATION===========================================================================================================
	     $NetSalesYear0           = null;
	     $NetSalesYear1           = null;
	     $NetSalesYear2           = null;
	     $NetSalesYear3           = null;
		 
		 $CosDepY'ear0             = null;
		 $CosDepYear1             = null;
		 $CosDepYear2             = null;
		 $CosDepYear3             = null;
		 
		 $CosOtherYear0           = null;
		 $CosOtherYear1           = null;
		 $CosOtherYear2           = null;
		 $CosOtherYear3           = null;
		 
         $StaffCostsYear0         = null;
	     $StaffCostsYear1         = null;
		 $StaffCostsYear2         = null;
		 $StaffCostsYear3         = null;
		 
		 $DirectorsFeesYear0      = null;
		 $DirectorsFeesYear1      = null;
		 $DirectorsFeesYear2      = null;
		 $DirectorsFeesYear3      = null;

		 $DepreciationOpexYear0   = null;
		 $DepreciationOpexYear1   = null;
		 $DepreciationOpexYear2   = null;
		 $DepreciationOpexYear3   = null;

 	     $OtherIncomeLine1Year0   = null;
		 $OtherIncomeLine1Year1   = null;
		 $OtherIncomeLine1Year2   = null;
		 $OtherIncomeLine1Year3   = null;

		 
		 $OtherIncomeLine2Year0   = null;
		 $OtherIncomeLine2Year1   = null;
		 $OtherIncomeLine2Year2   = null;
		 $OtherIncomeLine2Year3   = null;


		 $OtherIncomeLine3Year0   = null;
		 $OtherIncomeLine3Year1   = null;
		 $OtherIncomeLine3Year2   = null;
		 $OtherIncomeLine3Year3   = null;
	     		 
		 $OtherIncomeLine4Year0   = null;
		 $OtherIncomeLine4Year1   = null;
		 $OtherIncomeLine4Year2   = null;
		 $OtherIncomeLine4Year3   = null;
		 
		 $OtherIncomeDescLine1    = null;
		 $OtherIncomeDescLine2    = null;
		 $OtherIncomeDescLine3    = null;
		 $OtherIncomeDescLine4    = null;

		 
		 $AmortisationYear0       = null;
		 $AmortisationYear1       = null;
		 $AmortisationYear2       = null;
		 $AmortisationYear3       = null;
	
		 $NoNameOpexLine1Year0    = null;
		 $NoNameOpexLine1Year1    = null;
		 $NoNameOpexLine1Year2    = null;
		 $NoNameOpexLine1Year3    = null;
	
		 $NoNameOpexLine2Year0    = null;
		 $NoNameOpexLine2Year1    = null;
		 $NoNameOpexLine2Year2    = null;
		 $NoNameOpexLine2Year3    = null;
	     
		 $NoNameOpexDescLine1     = null;
		 $NoNameOpexDescLine2     = null;

		 $OtherOpexYear0          = null;
		 $OtherOpexYear1          = null;
		 $OtherOpexYear2          = null;
		 $OtherOpexYear3          = null;
		
	     $NetFinanceCostsYear0    = null;
         $NetFinanceCostsYear1    = null;
		 $NetFinanceCostsYear2    = null;
		 $NetFinanceCostsYear3    = null;
		 
		 $IncomeTaxYear0          = null;
		 $IncomeTaxYear1          = null;
		 $IncomeTaxYear2          = null;
		 $IncomeTaxYear3          = null;
		 
		 $DeferredTaxYear0         = null;
		 $DeferredTaxYear1         = null;
		 $DeferredTaxYear2         = null;
		 $DeferredTaxYear3         = null;
 	 	
		 $CosTotalYear0            = null;
		 $CosTotalYear1            = null;
		 $CosTotalYear2            = null;
		 $CosTotalYear3            = null;
		 
		 $GrossProfitYear0         = null;
		 $GrossProfitYear1         = null;
		 $GrossProfitYear2         = null;
		 $GrossProfitYear3         = null;
		  
		 $OtherIncomeTotalYear0    = null;
		 $OtherIncomeTotalYear1    = null;
		 $OtherIncomeTotalYear2    = null;
		 $OtherIncomeTotalYear3    = null;
		 
	     $OpexTotalYear0           = null;
	     $OpexTotalYear1           = null;
		 $OpexTotalYear2           = null;
		 $OpexTotalYear3           = null;
	     
		 $EBITYear0                = null;
		 $EBITYear1                = null;
		 $EBITYear2                = null;
		 $EBITYear3                = null;

		 $TaxationYear0            = null;
		 $TaxationYear1            = null;
		 $TaxationYear2            = null;
		 $TaxationYear3            = null;
 
 		 $NetProfitYear0           = null;
 		 $NetProfitYear1           = null;
		 $NetProfitYear2           = null;
		 $NetProfitYear3           = null;
		 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $NetSalesYear0               = str_replace(",","",$_POST['NetSalesYear0']);
	 	 $NetSalesYear1               = str_replace(",","",$_POST['NetSalesYear1']);
	     $NetSalesYear2               = str_replace(",","",$_POST['NetSalesYear2']);
	     $NetSalesYear3               = str_replace(",","",$_POST['NetSalesYear3']);
		
	   	 $CosDepYear0                 = str_replace(",","",$_POST['CosDepYear0']);
	   	 $CosDepYear1                 = str_replace(",","",$_POST['CosDepYear1']);
		 $CosDepYear2                 = str_replace(",","",$_POST['CosDepYear2']);
		 $CosDepYear3                 = str_replace(",","",$_POST['CosDepYear3']);
		 
		 $CosOtherYear0               = str_replace(",","",$_POST['CosOtherYear0']);
		 $CosOtherYear1               = str_replace(",","",$_POST['CosOtherYear1']);
		 $CosOtherYear2               = str_replace(",","",$_POST['CosOtherYear2']);
		 $CosOtherYear3               = str_replace(",","",$_POST['CosOtherYear3']);
		 
		 $StaffCostsYear0             = str_replace(",","",$_POST['StaffCostsYear0']);
		 $StaffCostsYear1             = str_replace(",","",$_POST['StaffCostsYear1']);
		 $StaffCostsYear2             = str_replace(",","",$_POST['StaffCostsYear2']);
		 $StaffCostsYear3             = str_replace(",","",$_POST['StaffCostsYear3']);
		 
		 $DirectorsFeesYear0          = str_replace(",","",$_POST['DirectorsFeesYear0']);
		 $DirectorsFeesYear1          = str_replace(",","",$_POST['DirectorsFeesYear1']);
		 $DirectorsFeesYear2          = str_replace(",","",$_POST['DirectorsFeesYear2']);
		 $DirectorsFeesYear3          = str_replace(",","",$_POST['DirectorsFeesYear3']);
		    
		 $DepreciationYear0       = str_replace(",","",$_POST['DepreciationYear0']);
		 $DepreciationYear1       = str_replace(",","",$_POST['DepreciationYear1']);
		 $DepreciationYear2       = str_replace(",","",$_POST['DepreciationYear2']);
		 $DepreciationYear3       = str_replace(",","",$_POST['DepreciationYear3']);
				
		 $OtherIncomeLine1Year0       = str_replace(",","",$_POST['OtherIncomeLine1Year0']);
		 $OtherIncomeLine1Year1       = str_replace(",","",$_POST['OtherIncomeLine1Year1']);
		 $OtherIncomeLine1Year2       = str_replace(",","",$_POST['OtherIncomeLine1Year2']);
		 $OtherIncomeLine1Year3       = str_replace(",","",$_POST['OtherIncomeLine1Year3']);
		 
	     $OtherIncomeLine2Year0       = str_replace(",","",$_POST['OtherIncomeLine2Year0']);
	     $OtherIncomeLine2Year1       = str_replace(",","",$_POST['OtherIncomeLine2Year1']);
		 $OtherIncomeLine2Year2       = str_replace(",","",$_POST['OtherIncomeLine2Year2']);
		 $OtherIncomeLine2Year3       = str_replace(",","",$_POST['OtherIncomeLine2Year3']);

		 $OtherIncomeLine3Year0       = str_replace(",","",$_POST['OtherIncomeLine3Year0']);
		 $OtherIncomeLine3Year1       = str_replace(",","",$_POST['OtherIncomeLine3Year1']);
		 $OtherIncomeLine3Year2       = str_replace(",","",$_POST['OtherIncomeLine3Year2']);
		 $OtherIncomeLine3Year3       = str_replace(",","",$_POST['OtherIncomeLine3Year3']);
		 		 
		 $OtherIncomeLine4Year0       = str_replace(",","",$_POST['OtherIncomeLine4Year0']);
		 $OtherIncomeLine4Year1       = str_replace(",","",$_POST['OtherIncomeLine4Year1']);
		 $OtherIncomeLine4Year2       = str_replace(",","",$_POST['OtherIncomeLine4Year2']);
		 $OtherIncomeLine4Year3       = str_replace(",","",$_POST['OtherIncomeLine4Year3']);
		 		 
		 $OtherIncomeDescLine1        = $_POST['OtherIncomeDescLine1'];
		 $OtherIncomeDescLine2        = $_POST['OtherIncomeDescLine2'];
		 $OtherIncomeDescLine3        = $_POST['OtherIncomeDescLine3'];
		 $OtherIncomeDescLine4        = $_POST['OtherIncomeDescLine4'];
		 
		 $AmortisationYear0           = str_replace(",","",$_POST['AmortisationYear0']);
		 $AmortisationYear1           = str_replace(",","",$_POST['AmortisationYear1']);
		 $AmortisationYear2           = str_replace(",","",$_POST['AmortisationYear2']);
		 $AmortisationYear3           = str_replace(",","",$_POST['AmortisationYear3']);
		 
         $NoNameOpexLine1Year0        = str_replace(",","",$_POST['NoNameOpexLine1Year0']);
         $NoNameOpexLine1Year1        = str_replace(",","",$_POST['NoNameOpexLine1Year1']);
		 $NoNameOpexLine1Year2        = str_replace(",","",$_POST['NoNameOpexLine1Year2']);
		 $NoNameOpexLine1Year3        = str_replace(",","",$_POST['NoNameOpexLine1Year3']);
		 
         $NoNameOpexLine2Year0        = str_replace(",","",$_POST['NoNameOpexLine2Year0']);
         $NoNameOpexLine2Year1        = str_replace(",","",$_POST['NoNameOpexLine2Year1']);
		 $NoNameOpexLine2Year2        = str_replace(",","",$_POST['NoNameOpexLine2Year2']);
		 $NoNameOpexLine2Year3        = str_replace(",","",$_POST['NoNameOpexLine2Year3']);
		 
         $NoNameOpexDescLine1         = str_replace(",","",$_POST['NoNameOpexDescLine1']);
         $NoNameOpexDescLine2         = str_replace(",","",$_POST['NoNameOpexDescLine2']);

		 $OtherOpexYear0              = str_replace(",","",$_POST['OtherOpexYear0']);
		 $OtherOpexYear1              = str_replace(",","",$_POST['OtherOpexYear1']);
		 $OtherOpexYear2              = str_replace(",","",$_POST['OtherOpexYear2']);
		 $OtherOpexYear3              = str_replace(",","",$_POST['OtherOpexYear3']);
		
	     $NetFinanceCostsYear0        = str_replace(",","",$_POST['NetFinanceCostsYear0']);
	     $NetFinanceCostsYear1        = str_replace(",","",$_POST['NetFinanceCostsYear1']);
		 $NetFinanceCostsYear2        = str_replace(",","",$_POST['NetFinanceCostsYear2']);
		 $NetFinanceCostsYear3        = str_replace(",","",$_POST['NetFinanceCostsYear3']);
	     
		 $IncomeTaxYear0              = str_replace(",","",$_POST['IncomeTaxYear0']);
		 $IncomeTaxYear1              = str_replace(",","",$_POST['IncomeTaxYear1']);
		 $IncomeTaxYear2              = str_replace(",","",$_POST['IncomeTaxYear2']);
		 $IncomeTaxYear3              = str_replace(",","",$_POST['IncomeTaxYear3']);
		 
		 $DeferredTaxYear0            = str_replace(",","",$_POST['DeferredTaxYear0']);
		 $DeferredTaxYear1            = str_replace(",","",$_POST['DeferredTaxYear1']);
		 $DeferredTaxYear2            = str_replace(",","",$_POST['DeferredTaxYear2']);
		 $DeferredTaxYear3            = str_replace(",","",$_POST['DeferredTaxYear3']);
 	 	
		 $CosTotalYear0               = str_replace(",","",$_POST['CosTotalYear0']);
		 $CosTotalYear1               = str_replace(",","",$_POST['CosTotalYear1']);
		 $CosTotalYear2               = str_replace(",","",$_POST['CosTotalYear2']);
		 $CosTotalYear3               = str_replace(",","",$_POST['CosTotalYear3']);
		  
		 $GrossProfitYear0            = str_replace(",","",$_POST['GrossProfitYear0']);
		 $GrossProfitYear1            = str_replace(",","",$_POST['GrossProfitYear1']);
		 $GrossProfitYear2            = str_replace(",","",$_POST['GrossProfitYear2']);
		 $GrossProfitYear3            = str_replace(",","",$_POST['GrossProfitYear3']);
		  
		 $OtherIncomeTotalYear0       = str_replace(",","",$_POST['OtherIncomeTotalYear0']);
		 $OtherIncomeTotalYear1       = str_replace(",","",$_POST['OtherIncomeTotalYear1']);
		 $OtherIncomeTotalYear2       = str_replace(",","",$_POST['OtherIncomeTotalYear2']);
		 $OtherIncomeTotalYear3       = str_replace(",","",$_POST['OtherIncomeTotalYear3']);
		 
		 $DeferredTaxYear0            = str_replace(",","",$_POST['DeferredTaxYear0']);
		 $DeferredTaxYear1            = str_replace(",","",$_POST['DeferredTaxYear1']);
		 $DeferredTaxYear2            = str_replace(",","",$_POST['DeferredTaxYear2']);
		 $DeferredTaxYear3            = str_replace(",","",$_POST['DeferredTaxYear3']);
		 
	     $OpexTotalYear0              = str_replace(",","",$_POST['OpexTotalYear0']);
	     $OpexTotalYear1              = str_replace(",","",$_POST['OpexTotalYear1']);
		 $OpexTotalYear2              = str_replace(",","",$_POST['OpexTotalYear2']);
		 $OpexTotalYear3              = str_replace(",","",$_POST['OpexTotalYear3']);
				 
	     $EBITYear0                   = str_replace(",","",$_POST['EBITYear0']);
	     $EBITYear1                   = str_replace(",","",$_POST['EBITYear1']);
		 $EBITYear2                   = str_replace(",","",$_POST['EBITYear2']);
		 $EBITYear3                   = str_replace(",","",$_POST['EBITYear3']);
	     
	     $PBTYear0                    = str_replace(",","",$_POST['PBTYear0']);
	     $PBTYear1                    = str_replace(",","",$_POST['PBTYear1']);
		 $PBTYear2                    = str_replace(",","",$_POST['PBTYear2']);
		 $PBTYear3                    = str_replace(",","",$_POST['PBTYear3']);
		 
	     $TaxationYear0               = str_replace(",","",$_POST['TaxationYear0']);
	     $TaxationYear1               = str_replace(",","",$_POST['TaxationYear1']);
		 $TaxationYear2               = str_replace(",","",$_POST['TaxationYear2']);
		 $TaxationYear3               = str_replace(",","",$_POST['TaxationYear3']);
		 
	     $NetProfitYear0              = str_replace(",","",$_POST['NetProfitYear0']);
	     $NetProfitYear1              = str_replace(",","",$_POST['NetProfitYear1']);
		 $NetProfitYear2              = str_replace(",","",$_POST['NetProfitYear2']);
		 $NetProfitYear3              = str_replace(",","",$_POST['NetProfitYear3']);
		 	 
	     //OONNECT TO THE DATABASE AND SAVE ======================================================================================================
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
		$report_name = "IncomeStatement";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
		 //========================UPDATE THE PROGRESS TRACKER - Income Statement Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Income Statement' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		 //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   income_statement_pdate    = now(), 
		   income_statement_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
				 
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                             ,line_desc2                                         ,reporting_year     , amount)
			VALUES 
			  /** Income Statement **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetSales'                             ,'$blank'                                           ,'$reporting_year0' ,'$NetSalesYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetSales'                             ,'$blank'                                           ,'$reporting_year1' ,'$NetSalesYear1'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetSales'                             ,'$blank'                                           ,'$reporting_year2' ,'$NetSalesYear2'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetSales'                             ,'$blank'                                           ,'$reporting_year3' ,'$NetSalesYear3'),
              
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosDep'                               ,'$blank'                                           ,'$reporting_year0' ,'$CosDepYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosDep'                               ,'$blank'                                           ,'$reporting_year1' ,'$CosDepYear1'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosDep'                               ,'$blank'                                           ,'$reporting_year2' ,'$CosDepYear2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosDep'                               ,'$blank'                                           ,'$reporting_year3' ,'$CosDepYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosOther'                             ,'$blank'                                           ,'$reporting_year0' ,'$CosOtherYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosOther'                             ,'$blank'                                           ,'$reporting_year1' ,'$CosOtherYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosOther'                             ,'$blank'                                           ,'$reporting_year2' ,'$CosOtherYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosOther'                             ,'$blank'                                           ,'$reporting_year3' ,'$CosOtherYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','StaffCosts'                           ,'$blank'                                           ,'$reporting_year0' ,'$StaffCostsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','StaffCosts'                           ,'$blank'                                           ,'$reporting_year1' ,'$StaffCostsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','StaffCosts'                           ,'$blank'                                           ,'$reporting_year2' ,'$StaffCostsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','StaffCosts'                           ,'$blank'                                           ,'$reporting_year3' ,'$StaffCostsYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DirectorsFees'                        ,'$blank'                                           ,'$reporting_year0' ,'$DirectorsFeesYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DirectorsFees'                        ,'$blank'                                           ,'$reporting_year1' ,'$DirectorsFeesYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DirectorsFees'                        ,'$blank'                                           ,'$reporting_year2' ,'$DirectorsFeesYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DirectorsFees'                        ,'$blank'                                           ,'$reporting_year3' ,'$DirectorsFeesYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Depreciation'                     ,'$blank'                                           ,'$reporting_year0' ,'$DepreciationYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Depreciation'                     ,'$blank'                                           ,'$reporting_year1' ,'$DepreciationYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Depreciation'                     ,'$blank'                                           ,'$reporting_year2' ,'$DepreciationYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Depreciation'                     ,'$blank'                                           ,'$reporting_year3' ,'$DepreciationYear3'),
			    
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine1'                     ,'$OtherIncomeDescLine1'                            ,'$reporting_year0' ,'$OtherIncomeLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine1'                     ,'$OtherIncomeDescLine1'                            ,'$reporting_year1' ,'$OtherIncomeLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine1'                     ,'$OtherIncomeDescLine1'                            ,'$reporting_year2' ,'$OtherIncomeLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine1'                     ,'$OtherIncomeDescLine1'                            ,'$reporting_year3' ,'$OtherIncomeLine1Year3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine2'                     ,'$OtherIncomeDescLine2'                            ,'$reporting_year0' ,'$OtherIncomeLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine2'                     ,'$OtherIncomeDescLine2'                            ,'$reporting_year1' ,'$OtherIncomeLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine2'                     ,'$OtherIncomeDescLine2'                            ,'$reporting_year2' ,'$OtherIncomeLine2Year2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine2'                     ,'$OtherIncomeDescLine2'                            ,'$reporting_year3' ,'$OtherIncomeLine2Year3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine3'                     ,'$OtherIncomeDescLine3'                            ,'$reporting_year0' ,'$OtherIncomeLine3Year0'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine3'                     ,'$OtherIncomeDescLine3'                            ,'$reporting_year1' ,'$OtherIncomeLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine3'                     ,'$OtherIncomeDescLine3'                            ,'$reporting_year2' ,'$OtherIncomeLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine3'                     ,'$OtherIncomeDescLine3'                            ,'$reporting_year3' ,'$OtherIncomeLine3Year3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine4'                     ,'$OtherIncomeDescLine4'                            ,'$reporting_year0' ,'$OtherIncomeLine4Year0'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine4'                     ,'$OtherIncomeDescLine4'                            ,'$reporting_year1' ,'$OtherIncomeLine4Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine4'                     ,'$OtherIncomeDescLine4'                            ,'$reporting_year2' ,'$OtherIncomeLine4Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeLine4'                     ,'$OtherIncomeDescLine4'                            ,'$reporting_year3' ,'$OtherIncomeLine4Year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Amortisation'                         ,'$blank'                                           ,'$reporting_year0' ,'$AmortisationYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Amortisation'                         ,'$blank'                                           ,'$reporting_year1' ,'$AmortisationYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Amortisation'                         ,'$blank'                                           ,'$reporting_year2' ,'$AmortisationYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Amortisation'                         ,'$blank'                                           ,'$reporting_year3' ,'$AmortisationYear3'),
			  			  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine1'                      ,'$NoNameOpexDescLine1'                             ,'$reporting_year0' ,'$NoNameOpexLine1Year0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine1'                      ,'$NoNameOpexDescLine1'                             ,'$reporting_year1' ,'$NoNameOpexLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine1'                      ,'$NoNameOpexDescLine1'                             ,'$reporting_year2' ,'$NoNameOpexLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine1'                      ,'$NoNameOpexDescLine1'                             ,'$reporting_year3' ,'$NoNameOpexLine1Year3'),
			  			  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine2'                      ,'$NoNameOpexDescLine2'                             ,'$reporting_year0' ,'$NoNameOpexLine2Year0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine2'                      ,'$NoNameOpexDescLine2'                             ,'$reporting_year1' ,'$NoNameOpexLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine2'                      ,'$NoNameOpexDescLine2'                             ,'$reporting_year2' ,'$NoNameOpexLine2Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NoNameOpexLine2'                      ,'$NoNameOpexDescLine2'                             ,'$reporting_year3' ,'$NoNameOpexLine2Year3'),
						  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherOpex'                            ,'$blank'                                           ,'$reporting_year0' ,'$OtherOpexYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherOpex'                            ,'$blank'                                           ,'$reporting_year1' ,'$OtherOpexYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherOpex'                            ,'$blank'                                           ,'$reporting_year2' ,'$OtherOpexYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherOpex'                            ,'$blank'                                           ,'$reporting_year3' ,'$OtherOpexYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetFinanceCosts'                      ,'$blank'                                           ,'$reporting_year0' ,'$NetFinanceCostsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetFinanceCosts'                      ,'$blank'                                           ,'$reporting_year1' ,'$NetFinanceCostsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetFinanceCosts'                      ,'$blank'                                           ,'$reporting_year2' ,'$NetFinanceCostsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetFinanceCosts'                      ,'$blank'                                           ,'$reporting_year3' ,'$NetFinanceCostsYear3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IncomeTax'                            ,'$blank'                                           ,'$reporting_year0' ,'$IncomeTaxYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IncomeTax'                            ,'$blank'                                           ,'$reporting_year1' ,'$IncomeTaxYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IncomeTax'                            ,'$blank'                                           ,'$reporting_year2' ,'$IncomeTaxYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IncomeTax'                            ,'$blank'                                           ,'$reporting_year3' ,'$IncomeTaxYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosTotal'                             ,'$blank'                                           ,'$reporting_year0' ,'$CosTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosTotal'                             ,'$blank'                                           ,'$reporting_year1' ,'$CosTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosTotal'                             ,'$blank'                                           ,'$reporting_year2' ,'$CosTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','CosTotal'                             ,'$blank'                                           ,'$reporting_year3' ,'$CosTotalYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','GrossProfit'                          ,'$blank'                                           ,'$reporting_year0' ,'$GrossProfitYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','GrossProfit'                          ,'$blank'                                           ,'$reporting_year1' ,'$GrossProfitYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','GrossProfit'                          ,'$blank'                                           ,'$reporting_year2' ,'$GrossProfitYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','GrossProfit'                          ,'$blank'                                           ,'$reporting_year3' ,'$GrossProfitYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeTotal'                     ,'$blank'                                           ,'$reporting_year0' ,'$OtherIncomeTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeTotal'                     ,'$blank'                                           ,'$reporting_year1' ,'$OtherIncomeTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeTotal'                     ,'$blank'                                           ,'$reporting_year2' ,'$OtherIncomeTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherIncomeTotal'                     ,'$blank'                                           ,'$reporting_year3' ,'$OtherIncomeTotalYear3'),
				  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTax'                          ,'$blank'                                           ,'$reporting_year0' ,'$DeferredTaxYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTax'                          ,'$blank'                                           ,'$reporting_year1' ,'$DeferredTaxYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTax'                          ,'$blank'                                           ,'$reporting_year2' ,'$DeferredTaxYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTax'                          ,'$blank'                                           ,'$reporting_year3' ,'$DeferredTaxYear3'),
	   	      
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpexTotal'                            ,'$blank'                                           ,'$reporting_year0' ,'$OpexTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpexTotal'                            ,'$blank'                                           ,'$reporting_year1' ,'$OpexTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpexTotal'                            ,'$blank'                                           ,'$reporting_year2' ,'$OpexTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpexTotal'                            ,'$blank'                                           ,'$reporting_year3' ,'$OpexTotalYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','EBIT'                                 ,'$blank'                                           ,'$reporting_year0' ,'$EBITYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','EBIT'                                 ,'$blank'                                           ,'$reporting_year1' ,'$EBITYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','EBIT'                                 ,'$blank'                                           ,'$reporting_year2' ,'$EBITYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','EBIT'                                 ,'$blank'                                           ,'$reporting_year3' ,'$EBITYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PBT'                                  ,'$blank'                                           ,'$reporting_year0' ,'$PBTYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PBT'                                  ,'$blank'                                           ,'$reporting_year1' ,'$PBTYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PBT'                                  ,'$blank'                                           ,'$reporting_year2' ,'$PBTYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PBT'                                  ,'$blank'                                           ,'$reporting_year3' ,'$PBTYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Taxation'                             ,'$blank'                                           ,'$reporting_year0' ,'$TaxationYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Taxation'                             ,'$blank'                                           ,'$reporting_year1' ,'$TaxationYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Taxation'                             ,'$blank'                                           ,'$reporting_year2' ,'$TaxationYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Taxation'                             ,'$blank'                                           ,'$reporting_year3' ,'$TaxationYear3'),

			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                            ,'$blank'                                           ,'$reporting_year0' ,'$NetProfitYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                            ,'$blank'                                           ,'$reporting_year1' ,'$NetProfitYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                            ,'$blank'                                           ,'$reporting_year2' ,'$NetProfitYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','NetProfit'                            ,'$blank'                                           ,'$reporting_year3' ,'$NetProfitYear3')";
	
	    $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
 	      //========================UPDATE THE PROGRESS TRACKER - Income statement Data Section================================================================================
		  $progress_tracker_insert_query =  "INSERT IGNORE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username   , date_saved )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Income Statement' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
   	            
			 
			 
		   header("Location:CorporateAddMenu.php");
           exit;
			 
	      }
 ?>
</body>

</html>