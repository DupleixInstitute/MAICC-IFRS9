
<html>

<head>
<Title>Saving Non Current Assets Data</Title>
</head>

<body>
    <?php
      
	 
	 
	    //VARIABLE INITIALISATION===========================================================================================================
	     $OpeningNBVYear0= null;
	     $OpeningNBVYear1= null;
	     $OpeningNBVYear2= null;
	     $OpeningNBVYear3= null;
		 
		 $AdditionsYear0=null;
		 $AdditionsYear1=null;
		 $AdditionsYear2=null;
		 $AdditionsYear3=null;
		 
		 $RevaluationYear0 = null;
		 $RevaluationYear1 = null;
		 $RevaluationYear2 = null;
		 $RevaluationYear3 = null;
		 
		 $DepreciationTotalYear0 = null;
		 $DepreciationTotalYear1 = null;
		 $DepreciationTotalYear2 = null;
		 $DepreciationTotalYear3 = null;
		 
		 $DepreciationCostOfSalesYear0 = null;
		 $DepreciationCostOfSalesYear1 = null;
		 $DepreciationCostOfSalesYear2 = null;
		 $DepreciationCostOfSalesYear3 = null;

		 $DepreciationOpexYear0 = null;
		 $DepreciationOpexYear1 = null;
		 $DepreciationOpexYear2 = null;
		 $DepreciationOpexYear3 = null;

 	     $OtherNonCurrentAssetsLine1Year0 = null;
		 $OtherNonCurrentAssetsLine1Year1 = null;
		 $OtherNonCurrentAssetsLine1Year2 = null;
		 $OtherNonCurrentAssetsLine1Year3 = null;
		 
		 $OtherNonCurrentAssetsLine2Year0 = null;
		 $OtherNonCurrentAssetsLine2Year1 = null;
		 $OtherNonCurrentAssetsLine2Year2 = null;
		 $OtherNonCurrentAssetsLine2Year3 = null;

		 $OtherNonCurrentAssetsLine3Year0                           = null;
		 $OtherNonCurrentAssetsLine3Year1                           = null;
		 $OtherNonCurrentAssetsLine3Year2                           = null;
		 $OtherNonCurrentAssetsLine3Year3                           = null;
	     		 
		 $OtherNonCurrentAssetsDescLine0                            = null;
		 $OtherNonCurrentAssetsDescLine1                            = null;
		 $OtherNonCurrentAssetsDescLine2                            = null;
		 $OtherNonCurrentAssetsDescLine3                            = null;
		 
		 $OtherMovementsPPEYear0                                    = null;
		 $OtherMovementsPPEYear1                                    = null;
		 $OtherMovementsPPEYear2                                    = null;
		 $OtherMovementsPPEYear3                                    = null;
		 
		 $ClosingNBVYear0                                            = null;
		 $ClosingNBVYear1                                            = null;
		 $ClosingNBVYear2                                            = null;
		 $ClosingNBVYear3                                            = null;
		
	     $LandBuildingsNBVYear0                                     = null;
		 $LandBuildingsNBVYear1                                     = null;
		 $LandBuildingsNBVYear2                                     = null;
		 $LandBuildingsNBVYear3                                     = null;
		 
		 $TotalOtherPPEYear0                                        = null;
		 $TotalOtherPPEYear1                                        = null;
		 $TotalOtherPPEYear2                                        = null;
		 $TotalOtherPPEYear3                                        = null;
		 
		 $InvestmentPropertyYear0                                   = null;
		 $InvestmentPropertyYear1                                   = null;
		 $InvestmentPropertyYear2                                   = null;
		 $InvestmentPropertyYear3                                   = null;
 	 	
		 $IntangibleAssetsYear0                                     = null;
		 $IntangibleAssetsYear1                                     = null;
		 $IntangibleAssetsYear2                                     = null;
		 $IntangibleAssetsYear3                                     = null;
		 
		 $OtherNonCurrentAssetsTotalYear0                           = null;
		 $OtherNonCurrentAssetsTotalYear1                           = null;
		 $OtherNonCurrentAssetsTotalYear1                           = null;
		 $OtherNonCurrentAssetsTotalYear1                           = null;
		  
		 $OtherNonCurrentAssetsGrandTotalYear0                      = null;
		 $OtherNonCurrentAssetsGrandTotalYear1                      = null;
		 $OtherNonCurrentAssetsGrandTotalYear2                      = null;
		 $OtherNonCurrentAssetsGrandTotalYear3                      = null;
		 
	     $TotalNonCurrentAssetsYear0                                = null;
		 $TotalNonCurrentAssetsYear1                                = null;
		 $TotalNonCurrentAssetsYear2                                = null;
		 $TotalNonCurrentAssetsYear3                                = null;

         $PPEComments                                               = null;
		 $OtherNonCurrentAssetsComments                              = null;
		 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $OpeningNBVYear0                          = str_replace(",","",$_POST['OpeningNBVYear0']);
	     $OpeningNBVYear1                          = str_replace(",","",$_POST['OpeningNBVYear1']);
	     $OpeningNBVYear2                          = str_replace(",","",$_POST['OpeningNBVYear2']);
	     $OpeningNBVYear3                          = str_replace(",","",$_POST['OpeningNBVYear3']);
		
	   	 $AdditionsYear0                           = str_replace(",","",$_POST['AdditionsYear0']);
		 $AdditionsYear1                           = str_replace(",","",$_POST['AdditionsYear1']);
		 $AdditionsYear2                           = str_replace(",","",$_POST['AdditionsYear2']);
		 $AdditionsYear3                           = str_replace(",","",$_POST['AdditionsYear3']);
		 
		 $RevaluationYear0                         = str_replace(",","",$_POST['RevaluationYear0']);
		 $RevaluationYear1                         = str_replace(",","",$_POST['RevaluationYear1']);
		 $RevaluationYear2                         = str_replace(",","",$_POST['RevaluationYear2']);
		 $RevaluationYear3                         = str_replace(",","",$_POST['RevaluationYear3']);
		 
		 $DepreciationTotalYear0                   = str_replace(",","",$_POST['DepreciationTotalYear0']);
		 $DepreciationTotalYear1                   = str_replace(",","",$_POST['DepreciationTotalYear1']);
		 $DepreciationTotalYear2                   = str_replace(",","",$_POST['DepreciationTotalYear2']);
		 $DepreciationTotalYear3                   = str_replace(",","",$_POST['DepreciationTotalYear3']);
		 
		 $DepreciationCostOfSalesYear0             = str_replace(",","",$_POST['DepreciationCostOfSalesYear0']);
		 $DepreciationCostOfSalesYear1             = str_replace(",","",$_POST['DepreciationCostOfSalesYear1']);
		 $DepreciationCostOfSalesYear2             = str_replace(",","",$_POST['DepreciationCostOfSalesYear2']);
		 $DepreciationCostOfSalesYear3             = str_replace(",","",$_POST['DepreciationCostOfSalesYear3']);
		    
		 $DepreciationOpexYear0                    = str_replace(",","",$_POST['DepreciationOpexYear0']);
		 $DepreciationOpexYear1                    = str_replace(",","",$_POST['DepreciationOpexYear1']);
		 $DepreciationOpexYear2                    = str_replace(",","",$_POST['DepreciationOpexYear2']);
		 $DepreciationOpexYear3                    = str_replace(",","",$_POST['DepreciationOpexYear3']);
				
		 $OtherNonCurrentAssetsLine1Year0          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine1Year0']);
		 $OtherNonCurrentAssetsLine1Year1          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine1Year1']);
		 $OtherNonCurrentAssetsLine1Year2          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine1Year2']);
		 $OtherNonCurrentAssetsLine1Year3          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine1Year3']);
		 
	     $OtherNonCurrentAssetsLine2Year0          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine2Year0']);
		 $OtherNonCurrentAssetsLine2Year1          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine2Year1']);
		 $OtherNonCurrentAssetsLine2Year2          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine2Year2']);
		 $OtherNonCurrentAssetsLine2Year3          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine2Year3']);

		 $OtherNonCurrentAssetsLine3Year0          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine3Year0']);
		 $OtherNonCurrentAssetsLine3Year1          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine3Year1']);
		 $OtherNonCurrentAssetsLine3Year2          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine3Year2']);
		 $OtherNonCurrentAssetsLine3Year3          = str_replace(",","",$_POST['OtherNonCurrentAssetsLine3Year3']);
		 		 
		 $OtherNonCurrentAssetsDescLine1           = $_POST['OtherNonCurrentAssetsDescLine1'];
		 $OtherNonCurrentAssetsDescLine2           = $_POST['OtherNonCurrentAssetsDescLine2'];
		 $OtherNonCurrentAssetsDescLine3           = $_POST['OtherNonCurrentAssetsDescLine3'];
		 
		 $OtherMovementsPPEYear0                   = str_replace(",","",$_POST['OtherMovementsPPEYear0']);
		 $OtherMovementsPPEYear1                   = str_replace(",","",$_POST['OtherMovementsPPEYear1']);
		 $OtherMovementsPPEYear2                   = str_replace(",","",$_POST['OtherMovementsPPEYear2']);
		 $OtherMovementsPPEYear3                   = str_replace(",","",$_POST['OtherMovementsPPEYear3']);
		 
         $OtherMovementsPPEYear0                   = str_replace(",","",$_POST['OtherMovementsPPEYear0']);
		 $OtherMovementsPPEYear1                   = str_replace(",","",$_POST['OtherMovementsPPEYear1']);
		 $OtherMovementsPPEYear2                   = str_replace(",","",$_POST['OtherMovementsPPEYear2']);
		 $OtherMovementsPPEYear3                   = str_replace(",","",$_POST['OtherMovementsPPEYear3']);
		 
		 $ClosingNBVYear0                          = str_replace(",","",$_POST['ClosingNBVYear0']);
		 $ClosingNBVYear1                          = str_replace(",","",$_POST['ClosingNBVYear1']);
		 $ClosingNBVYear2                          = str_replace(",","",$_POST['ClosingNBVYear2']);
		 $ClosingNBVYear3                          = str_replace(",","",$_POST['ClosingNBVYear3']);
		
	     $LandBuildingsNBVYear0                    = str_replace(",","",$_POST['LandBuildingsNBVYear0']);
		 $LandBuildingsNBVYear1                    = str_replace(",","",$_POST['LandBuildingsNBVYear1']);
		 $LandBuildingsNBVYear2                    = str_replace(",","",$_POST['LandBuildingsNBVYear2']);
		 $LandBuildingsNBVYear3                    = str_replace(",","",$_POST['LandBuildingsNBVYear3']);
	     
		 $TotalOtherPPEYear0                       = str_replace(",","",$_POST['TotalOtherPPEYear0']);
		 $TotalOtherPPEYear1                       = str_replace(",","",$_POST['TotalOtherPPEYear1']);
		 $TotalOtherPPEYear2                       = str_replace(",","",$_POST['TotalOtherPPEYear2']);
		 $TotalOtherPPEYear3                       = str_replace(",","",$_POST['TotalOtherPPEYear3']);
		 
		 $InvestmentPropertyYear0                  = str_replace(",","",$_POST['InvestmentPropertyYear0']);
		 $InvestmentPropertyYear1                  = str_replace(",","",$_POST['InvestmentPropertyYear1']);
		 $InvestmentPropertyYear2                  = str_replace(",","",$_POST['InvestmentPropertyYear2']);
		 $InvestmentPropertyYear3                  = str_replace(",","",$_POST['InvestmentPropertyYear3']);
 	 	
		 $IntangibleAssetsYear0                    = str_replace(",","",$_POST['IntangibleAssetsYear0']);
		 $IntangibleAssetsYear1                    = str_replace(",","",$_POST['IntangibleAssetsYear1']);
		 $IntangibleAssetsYear2                    = str_replace(",","",$_POST['IntangibleAssetsYear2']);
		 $IntangibleAssetsYear3                    = str_replace(",","",$_POST['IntangibleAssetsYear3']);
		 
		 $OtherNonCurrentAssetsTotalYear0          = str_replace(",","",$_POST['OtherNonCurrentAssetsTotalYear0']);
		 $OtherNonCurrentAssetsTotalYear1          = str_replace(",","",$_POST['OtherNonCurrentAssetsTotalYear1']);
		 $OtherNonCurrentAssetsTotalYear2          = str_replace(",","",$_POST['OtherNonCurrentAssetsTotalYear2']);
		 $OtherNonCurrentAssetsTotalYear3          = str_replace(",","",$_POST['OtherNonCurrentAssetsTotalYear3']);
		  
		 $OtherNonCurrentAssetsGrandTotalYear0     = str_replace(",","",$_POST['OtherNonCurrentAssetsGrandTotalYear0']);
		 $OtherNonCurrentAssetsGrandTotalYear1     = str_replace(",","",$_POST['OtherNonCurrentAssetsGrandTotalYear1']);
		 $OtherNonCurrentAssetsGrandTotalYear2     = str_replace(",","",$_POST['OtherNonCurrentAssetsGrandTotalYear2']);
		 $OtherNonCurrentAssetsGrandTotalYear3     = str_replace(",","",$_POST['OtherNonCurrentAssetsGrandTotalYear3']);
		 
		 $InvestmentPropertyYear0                  = str_replace(",","",$_POST['InvestmentPropertyYear0']);
		 $InvestmentPropertyYear1                  = str_replace(",","",$_POST['InvestmentPropertyYear1']);
		 $InvestmentPropertyYear2                  = str_replace(",","",$_POST['InvestmentPropertyYear2']);
		 $InvestmentPropertyYear3                  = str_replace(",","",$_POST['InvestmentPropertyYear3']);
		 
	     $TotalNonCurrentAssetsYear0               = str_replace(",","",$_POST['TotalNonCurrentAssetsYear0']);
		 $TotalNonCurrentAssetsYear1               = str_replace(",","",$_POST['TotalNonCurrentAssetsYear1']);
		 $TotalNonCurrentAssetsYear2               = str_replace(",","",$_POST['TotalNonCurrentAssetsYear2']);
		 $TotalNonCurrentAssetsYear3               = str_replace(",","",$_POST['TotalNonCurrentAssetsYear3']);
				 
	     $PPEComments                              = $_POST['PPEComments'];
		 $OtherNonCurrentAssetsComments            = $_POST['OtherNonCurrentAssetsComments'];
		 

	 
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
        //$application_ref = $_POST['application_ref];
		//$username = $_POST['username'];
		//$company_reg_no = $_POST['company_reg_no'];
        
		$application_ref =$_POST['application_ref'];
		$username = $_POST['username'];
		$report_name = "NonCurrentAssets";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
			 
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                             ,line_desc2                                         ,reporting_year     , amount, line_comment)
			VALUES 
			  /** Non Current Assets **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningNBV'                           ,'$blank'                                           ,'$reporting_year0' ,'$OpeningNBVYear0','$blank'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningNBV'                           ,'$blank'                                           ,'$reporting_year1' ,'$OpeningNBVYear1','$blank'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningNBV'                           ,'$blank'                                           ,'$reporting_year2' ,'$OpeningNBVYear2','$blank'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OpeningNBV'                           ,'$blank'                                           ,'$reporting_year3' ,'$OpeningNBVYear3','$blank'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Additions'                            ,'$blank'                                           ,'$reporting_year0' ,'$AdditionsYear0','$blank'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Additions'                            ,'$blank'                                           ,'$reporting_year1' ,'$AdditionsYear1','$blank'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Additions'                            ,'$blank'                                           ,'$reporting_year2' ,'$AdditionsYear2','$blank'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Additions'                            ,'$blank'                                           ,'$reporting_year3' ,'$AdditionsYear3','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Revaluation'                          ,'$blank'                                           ,'$reporting_year0' ,'$RevaluationYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Revaluation'                          ,'$blank'                                           ,'$reporting_year1' ,'$RevaluationYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Revaluation'                          ,'$blank'                                           ,'$reporting_year2' ,'$RevaluationYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Revaluation'                          ,'$blank'                                           ,'$reporting_year3' ,'$RevaluationYear3','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationTotal'                    ,'$blank'                                           ,'$reporting_year0' ,'$DepreciationTotalYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationTotal'                    ,'$blank'                                           ,'$reporting_year1' ,'$DepreciationTotalYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationTotal'                    ,'$blank'                                           ,'$reporting_year2' ,'$DepreciationTotalYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationTotal'                    ,'$blank'                                           ,'$reporting_year3' ,'$DepreciationTotalYear3','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationCostOfSales'              ,'$blank'                                           ,'$reporting_year0' ,'$DepreciationCostOfSalesYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationCostOfSales'              ,'$blank'                                           ,'$reporting_year1' ,'$DepreciationCostOfSalesYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationCostOfSales'              ,'$blank'                                           ,'$reporting_year2' ,'$DepreciationCostOfSalesYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationCostOfSales'              ,'$blank'                                           ,'$reporting_year3' ,'$DepreciationCostOfSalesYear3','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationOpex'                     ,'$blank'                                           ,'$reporting_year0' ,'$DepreciationOpexYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationOpex'                     ,'$blank'                                           ,'$reporting_year1' ,'$DepreciationOpexYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationOpex'                     ,'$blank'                                           ,'$reporting_year2' ,'$DepreciationOpexYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DepreciationOpex'                     ,'$blank'                                           ,'$reporting_year3' ,'$DepreciationOpexYear3','$blank'),
			    
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine1'           ,'$OtherNonCurrentAssetsDescLine1'                  ,'$reporting_year0' ,'$OtherNonCurrentAssetsLine1Year0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine1'           ,'$OtherNonCurrentAssetsDescLine1'                  ,'$reporting_year1' ,'$OtherNonCurrentAssetsLine1Year1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine1'           ,'$OtherNonCurrentAssetsDescLine1'                  ,'$reporting_year2' ,'$OtherNonCurrentAssetsLine1Year2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine1'           ,'$OtherNonCurrentAssetsDescLine1'                  ,'$reporting_year3' ,'$OtherNonCurrentAssetsLine1Year3','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine2'           ,'$OtherNonCurrentAssetsDescLine2'                  ,'$reporting_year0' ,'$OtherNonCurrentAssetsLine2Year0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine2'           ,'$OtherNonCurrentAssetsDescLine2'                  ,'$reporting_year1' ,'$OtherNonCurrentAssetsLine2Year1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine2'           ,'$OtherNonCurrentAssetsDescLine2'                  ,'$reporting_year2' ,'$OtherNonCurrentAssetsLine2Year2','$blank'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine2'           ,'$OtherNonCurrentAssetsDescLine2'                  ,'$reporting_year3' ,'$OtherNonCurrentAssetsLine2Year3','$blank'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine3'           ,'$OtherNonCurrentAssetsDescLine3'                  ,'$reporting_year0' ,'$OtherNonCurrentAssetsLine3Year0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine3'           ,'$OtherNonCurrentAssetsDescLine3'                  ,'$reporting_year1' ,'$OtherNonCurrentAssetsLine3Year1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine3'           ,'$OtherNonCurrentAssetsDescLine3'                  ,'$reporting_year2' ,'$OtherNonCurrentAssetsLine3Year2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsLine3'           ,'$OtherNonCurrentAssetsDescLine3'                  ,'$reporting_year3' ,'$OtherNonCurrentAssetsLine3Year3','$blank'),
	   	      
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherMovementsPPE'                    ,'$blank'                                            ,'$reporting_year0' ,'$OtherMovementsPPEYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherMovementsPPE'                    ,'$blank'                                            ,'$reporting_year1' ,'$OtherMovementsPPEYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherMovementsPPE'                    ,'$blank'                                            ,'$reporting_year2' ,'$OtherMovementsPPEYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherMovementsPPE'                    ,'$blank'                                            ,'$reporting_year3' ,'$OtherMovementsPPEYear3','$blank'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingNBV'                           ,'$blank'                                            ,'$reporting_year0' ,'$ClosingNBVYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingNBV'                           ,'$blank'                                            ,'$reporting_year1' ,'$ClosingNBVYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingNBV'                           ,'$blank'                                            ,'$reporting_year2' ,'$ClosingNBVYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ClosingNBV'                           ,'$blank'                                            ,'$reporting_year3' ,'$ClosingNBVYear3','$blank'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LandBuildingsNBV'                     ,'$blank'                                           ,'$reporting_year0' ,'$LandBuildingsNBVYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LandBuildingsNBV'                     ,'$blank'                                           ,'$reporting_year1' ,'$LandBuildingsNBVYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LandBuildingsNBV'                     ,'$blank'                                           ,'$reporting_year2' ,'$LandBuildingsNBVYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LandBuildingsNBV'                     ,'$blank'                                           ,'$reporting_year3' ,'$LandBuildingsNBVYear3','$blank'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalOtherPPE'                        ,'$PPEComments'                                     ,'$reporting_year0' ,'$TotalOtherPPEYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalOtherPPE'                        ,'$PPEComments'                                     ,'$reporting_year1' ,'$TotalOtherPPEYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalOtherPPE'                        ,'$PPEComments'                                     ,'$reporting_year2' ,'$TotalOtherPPEYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalOtherPPE'                        ,'$PPEComments'                                     ,'$reporting_year3' ,'$TotalOtherPPEYear3','$blank'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IntangibleAssets'                     ,'$blank'                                           ,'$reporting_year0' ,'$IntangibleAssetsYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IntangibleAssets'                     ,'$blank'                                           ,'$reporting_year1' ,'$IntangibleAssetsYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IntangibleAssets'                     ,'$blank'                                           ,'$reporting_year2' ,'$IntangibleAssetsYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','IntangibleAssets'                     ,'$blank'                                           ,'$reporting_year3' ,'$IntangibleAssetsYear3','$blank'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsTotal'           ,'$OtherNonCurrentAssetsComments'                   ,'$reporting_year0' ,'$OtherNonCurrentAssetsTotalYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsTotal'           ,'$OtherNonCurrentAssetsComments'                   ,'$reporting_year1' ,'$OtherNonCurrentAssetsTotalYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsTotal'           ,'$OtherNonCurrentAssetsComments'                   ,'$reporting_year2' ,'$OtherNonCurrentAssetsTotalYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsTotal'           ,'$OtherNonCurrentAssetsComments'                   ,'$reporting_year3' ,'$OtherNonCurrentAssetsTotalYear3','$blank'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsGrandTotal'      ,'$blank'                                           ,'$reporting_year0' ,'$OtherNonCurrentAssetsGrandTotalYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsGrandTotal'      ,'$blank'                                           ,'$reporting_year1' ,'$OtherNonCurrentAssetsGrandTotalYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsGrandTotal'      ,'$blank'                                           ,'$reporting_year2' ,'$OtherNonCurrentAssetsGrandTotalYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsGrandTotal'      ,'$blank'                                           ,'$reporting_year3' ,'$OtherNonCurrentAssetsGrandTotalYear3','$blank'),
				  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InvestmentProperty'                   ,'$blank'                                           ,'$reporting_year0' ,'$InvestmentPropertyYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InvestmentProperty'                   ,'$blank'                                           ,'$reporting_year1' ,'$InvestmentPropertyYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InvestmentProperty'                   ,'$blank'                                           ,'$reporting_year2' ,'$InvestmentPropertyYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InvestmentProperty'                   ,'$blank'                                           ,'$reporting_year3' ,'$InvestmentPropertyYear3','$blank'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PPEComments'                          ,'$blank'                                           ,'$reporting_year0' ,0,'$PPEComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PPEComments'                          ,'$blank'                                           ,'$reporting_year1' ,0,'$PPEComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PPEComments'                          ,'$blank'                                           ,'$reporting_year2' ,0,'$PPEComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','PPEComments'                          ,'$blank'                                           ,'$reporting_year3' ,0,'$PPEComments'),
				  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsComments'        ,'$blank'                                           ,'$reporting_year0' ,0,'$OtherNonCurrentAssetsComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsComments'        ,'$blank'                                           ,'$reporting_year1' ,0,'$OtherNonCurrentAssetsComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsComments'        ,'$blank'                                           ,'$reporting_year2' ,0,'$OtherNonCurrentAssetsComments'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentAssetsComments'        ,'$blank'                                           ,'$reporting_year3' ,0,'$OtherNonCurrentAssetsComments'),

			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentAssets'                ,'$blank'                                           ,'$reporting_year0' ,'$TotalNonCurrentAssetsYear0','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentAssets'                ,'$blank'                                           ,'$reporting_year1' ,'$TotalNonCurrentAssetsYear1','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentAssets'                ,'$blank'                                           ,'$reporting_year2' ,'$TotalNonCurrentAssetsYear2','$blank'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentAssets'                ,'$blank'                                           ,'$reporting_year3' ,'$TotalNonCurrentAssetsYear3','$blank')";
	
	
	
	$resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
 		 //========================UPDATE THE PROGRESS TRACKER - Non Current Assets Section================================================================================
		  $progress_tracker_insert_query =  "INSERT IGNORE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username   , date_saved )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Non Current Assets' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 

		  if (!$resultm)
          {                                                    
             echo 'error with inserting non current assets record on progress tracker'. mysqli_error();
          }
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Non Current Assets		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   non_current_assets_pdate    = now(), 
		   non_current_assets_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================

		  header("Location:CorporateAddMenu.php");
          exit;		     echo 'Data successfully saved';
	      }
 ?>
</body>

</html>