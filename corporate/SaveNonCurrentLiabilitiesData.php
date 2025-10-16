
<html>

<head>
<Title>Saving Non Current Liabilities Data</Title>
</head>

<body>
    <?php
      
	 
	 
	    //VARIABLE INITIALISATION===========================================================================================================
	     $MortgageLoansYear0= null;
	     $MortgageLoansYear1= null;
	     $MortgageLoansYear2= null;
	     $MortgageLoansYear3= null;
		 
		 $TermLoansYear0=null;
		 $TermLoansYear1=null;
		 $TermLoansYear2=null;
		 $TermLoansYear3=null;
		 
		 $BondsYear0 = null;
		 $BondsYear1 = null;
		 $BondsYear2 = null;
		 $BondsYear3 = null;
		 
		 $FinanceLeasesYear0 = null;
		 $FinanceLeasesYear1 = null;
		 $FinanceLeasesYear2 = null;
		 $FinanceLeasesYear3 = null;
		 
		 $OtherLongTermBorrowingsYear0 = null;
		 $OtherLongTermBorrowingsYear1 = null;
		 $OtherLongTermBorrowingsYear2 = null;
		 $OtherLongTermBorrowingsYear3 = null;

		 $ShareholdersLoansYear0 = null;
		 $ShareholdersLoansYear1 = null;
		 $ShareholdersLoansYear2 = null;
		 $ShareholdersLoansYear3 = null;

		 $InterCompanyLoansLine1Year0 = null;
		 $InterCompanyLoansLine1Year1 = null;
		 $InterCompanyLoansLine1Year2 = null;
		 $InterCompanyLoansLine1Year3 = null;
		 
		 $InterCompanyLoansLine2Year0 = null;
		 $InterCompanyLoansLine2Year1 = null;
		 $InterCompanyLoansLine2Year2 = null;
		 $InterCompanyLoansLine2Year3 = null;

		 $InterCompanyLoansLine3Year0 = null;
		 $InterCompanyLoansLine3Year1 = null;
		 $InterCompanyLoansLine3Year2 = null;
		 $InterCompanyLoansLine3Year3 = null;
 
 	     $OtherNonCurrentLiabilitiesLine1Year0 = null;
		 $OtherNonCurrentLiabilitiesLine1Year1 = null;
		 $OtherNonCurrentLiabilitiesLine1Year2 = null;
		 $OtherNonCurrentLiabilitiesLine1Year3 = null;
		 
		 $OtherNonCurrentLiabilitiesLine2Year0 = null;
		 $OtherNonCurrentLiabilitiesLine2Year1 = null;
		 $OtherNonCurrentLiabilitiesLine2Year2 = null;
		 $OtherNonCurrentLiabilitiesLine2Year3 = null;

		 $OtherNonCurrentLiabilitiesLine3Year0 = null;
		 $OtherNonCurrentLiabilitiesLine3Year1 = null;
		 $OtherNonCurrentLiabilitiesLine3Year2 = null;
		 $OtherNonCurrentLiabilitiesLine3Year3 = null;
	     
		 $InterCompanyLoansDescLine0 = null;
		 $InterCompanyLoansDescLine1 = null;
		 $InterCompanyLoansDescLine2 = null;
		 $InterCompanyLoansDescLine3 = null;
		 
		 $OtherNonCurrentLiabilitiesDescLine0 = null;
		 $OtherNonCurrentLiabilitiesDescLine1 = null;
		 $OtherNonCurrentLiabilitiesDescLine2 = null;
		 $OtherNonCurrentLiabilitiesDescLine3 = null;
		 
		 $LongTermBorrowingsNonInterCompanyTotalYear0 = null;
		 $LongTermBorrowingsNonInterCompanyTotalYear1 = null;
		 $LongTermBorrowingsNonInterCompanyTotalYear2 = null;
		 $LongTermBorrowingsNonInterCompanyTotalYear3 = null;
		 
		 $LongTermBorrowingsInterCompanyTotalYear0 = null;
		 $LongTermBorrowingsInterCompanyTotalYear1 = null;
		 $LongTermBorrowingsInterCompanyTotalYear2 = null;
		 $LongTermBorrowingsInterCompanyTotalYear3 = null;
		
	     $InterCompanyLoansTotalYear0 = null;
		 $InterCompanyLoansTotalYear1 = null;
		 $InterCompanyLoansTotalYear2 = null;
		 $InterCompanyLoansTotalYear3 = null;
		 
		 $TotalLongTermBorrowingsYear0 = null;
		 $TotalLongTermBorrowingsYear1 = null;
		 $TotalLongTermBorrowingsYear2 = null;
		 $TotalLongTermBorrowingsYear3 = null;
		 
		 $OtherNonCurrentLiabilitiesTotalYear0 = null;
		 $OtherNonCurrentLiabilitiesTotalYear1 = null;
		 $OtherNonCurrentLiabilitiesTotalYear2 = null;
		 $OtherNonCurrentLiabilitiesTotalYear3 = null;
 	 	
		 $OtherNonCurrentLiabilitiesGrandTotalYear0 = null;
		 $OtherNonCurrentLiabilitiesGrandTotalYear1 = null;
		 $OtherNonCurrentLiabilitiesGrandTotalYear2 = null;
		 $OtherNonCurrentLiabilitiesGrandTotalYear3 = null;
		 
		 $DeferredTaxBalanceYear0              = null;
		 $DeferredTaxBalanceYear1              = null;
		 $DeferredTaxBalanceYear2              = null;
		 $DeferredTaxBalanceYear3              = null;
		  
		 $ProvisionsYear0                      = null;
		 $ProvisionsYear1                      = null;
		 $ProvisionsYear2                      = null;
		 $ProvisionsYear3                      = null;
		 
		 $OtherNonCurrentLiabilitiesTotalYear0 = null;
		 $OtherNonCurrentLiabilitiesTotalYear1 = null;
		 $OtherNonCurrentLiabilitiesTotalYear2 = null;
		 $OtherNonCurrentLiabilitiesTotalYear3 = null;
		 
	     $TotalNonCurrentLiabilitiesYear0      = null;
		 $TotalNonCurrentLiabilitiesYear1      = null;
		 $TotalNonCurrentLiabilitiesYear2      = null;
		 $TotalNonCurrentLiabilitiesYear3      = null;
		 
	     // POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
	 	 $MortgageLoansYear0                                = str_replace(",","",$_POST['MortgageLoansYear0']);
	     $MortgageLoansYear1                                = str_replace(",","",$_POST['MortgageLoansYear1']);
	     $MortgageLoansYear2                                = str_replace(",","",$_POST['MortgageLoansYear2']);
	     $MortgageLoansYear3                                = str_replace(",","",$_POST['MortgageLoansYear3']);
		
	   	 $TermLoansYear0                                    = str_replace(",","",$_POST['TermLoansYear0']);
		 $TermLoansYear1                                    = str_replace(",","",$_POST['TermLoansYear1']);
		 $TermLoansYear2                                    = str_replace(",","",$_POST['TermLoansYear2']);
		 $TermLoansYear3                                    = str_replace(",","",$_POST['TermLoansYear3']);
		 
		 $BondsYear0                                        = str_replace(",","",$_POST['BondsYear0']);
		 $BondsYear1                                        = str_replace(",","",$_POST['BondsYear1']);
		 $BondsYear2                                        = str_replace(",","",$_POST['BondsYear2']);
		 $BondsYear3                                        = str_replace(",","",$_POST['BondsYear3']);
		 
		 $FinanceLeasesYear0                                = str_replace(",","",$_POST['FinanceLeasesYear0']);
		 $FinanceLeasesYear1                                = str_replace(",","",$_POST['FinanceLeasesYear1']);
		 $FinanceLeasesYear2                                = str_replace(",","",$_POST['FinanceLeasesYear2']);
		 $FinanceLeasesYear3                                = str_replace(",","",$_POST['FinanceLeasesYear3']);
		 
		 $OtherLongTermBorrowingsYear0                      = str_replace(",","",$_POST['OtherLongTermBorrowingsYear0']);
		 $OtherLongTermBorrowingsYear1                      = str_replace(",","",$_POST['OtherLongTermBorrowingsYear1']);
		 $OtherLongTermBorrowingsYear2                      = str_replace(",","",$_POST['OtherLongTermBorrowingsYear2']);
		 $OtherLongTermBorrowingsYear3                      = str_replace(",","",$_POST['OtherLongTermBorrowingsYear3']);
		    
		 $ShareholdersLoansYear0                            = str_replace(",","",$_POST['ShareholdersLoansYear0']);
		 $ShareholdersLoansYear1                            = str_replace(",","",$_POST['ShareholdersLoansYear1']);
		 $ShareholdersLoansYear2                            = str_replace(",","",$_POST['ShareholdersLoansYear2']);
		 $ShareholdersLoansYear3                            = str_replace(",","",$_POST['ShareholdersLoansYear3']);
				
		 $InterCompanyLoansLine1Year0                       = str_replace(",","",$_POST['InterCompanyLoansLine1Year0']);
		 $InterCompanyLoansLine1Year1                       = str_replace(",","",$_POST['InterCompanyLoansLine1Year1']);
		 $InterCompanyLoansLine1Year2                       = str_replace(",","",$_POST['InterCompanyLoansLine1Year2']);
		 $InterCompanyLoansLine1Year3                       = str_replace(",","",$_POST['InterCompanyLoansLine1Year3']);
		 
	     $InterCompanyLoansLine2Year0                       = str_replace(",","",$_POST['InterCompanyLoansLine2Year0']);
		 $InterCompanyLoansLine2Year1                       = str_replace(",","",$_POST['InterCompanyLoansLine2Year1']);
		 $InterCompanyLoansLine2Year2                       = str_replace(",","",$_POST['InterCompanyLoansLine2Year2']);
		 $InterCompanyLoansLine2Year3                       = str_replace(",","",$_POST['InterCompanyLoansLine2Year3']);

		 $InterCompanyLoansLine3Year0                       = str_replace(",","",$_POST['InterCompanyLoansLine3Year0']);
		 $InterCompanyLoansLine3Year1                       = str_replace(",","",$_POST['InterCompanyLoansLine3Year1']);
		 $InterCompanyLoansLine3Year2                       = str_replace(",","",$_POST['InterCompanyLoansLine3Year2']);
		 $InterCompanyLoansLine3Year3                       = str_replace(",","",$_POST['InterCompanyLoansLine3Year3']);
		 
		 $OtherNonCurrentLiabilitiesLine1Year0              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine1Year0']);
		 $OtherNonCurrentLiabilitiesLine1Year1              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine1Year1']);
		 $OtherNonCurrentLiabilitiesLine1Year2              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine1Year2']);
		 $OtherNonCurrentLiabilitiesLine1Year3              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine1Year3']);
		 
	     $OtherNonCurrentLiabilitiesLine2Year0              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine2Year0']);
		 $OtherNonCurrentLiabilitiesLine2Year1              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine2Year1']);
		 $OtherNonCurrentLiabilitiesLine2Year2              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine2Year2']);
		 $OtherNonCurrentLiabilitiesLine2Year3              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine2Year3']);

		 $OtherNonCurrentLiabilitiesLine3Year0              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine3Year0']);
		 $OtherNonCurrentLiabilitiesLine3Year1              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine3Year1']);
		 $OtherNonCurrentLiabilitiesLine3Year2              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine3Year2']);
		 $OtherNonCurrentLiabilitiesLine3Year3              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesLine3Year3']);
		 
		 $InterCompanyLoansDescLine1                        = $_POST['InterCompanyLoansDescLine1'];
		 $InterCompanyLoansDescLine2                        = $_POST['InterCompanyLoansDescLine2'];
		 $InterCompanyLoansDescLine3                        = $_POST['InterCompanyLoansDescLine3'];
		 
		 $OtherNonCurrentLiabilitiesDescLine1               = $_POST['OtherNonCurrentLiabilitiesDescLine1'];
		 $OtherNonCurrentLiabilitiesDescLine2               = $_POST['OtherNonCurrentLiabilitiesDescLine2'];
		 $OtherNonCurrentLiabilitiesDescLine3               = $_POST['OtherNonCurrentLiabilitiesDescLine3'];
		 
		 $LongTermBorrowingsNonInterCompanyTotalYear0       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear0']);
		 $LongTermBorrowingsNonInterCompanyTotalYear1       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear1']);
		 $LongTermBorrowingsNonInterCompanyTotalYear2       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear2']);
		 $LongTermBorrowingsNonInterCompanyTotalYear3       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear3']);
		 
         $LongTermBorrowingsNonInterCompanyTotalYear0       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear0']);
		 $LongTermBorrowingsNonInterCompanyTotalYear1       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear1']);
		 $LongTermBorrowingsNonInterCompanyTotalYear2       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear2']);
		 $LongTermBorrowingsNonInterCompanyTotalYear3       = str_replace(",","",$_POST['LongTermBorrowingsNonInterCompanyTotalYear3']);
		 
		 $LongTermBorrowingsInterCompanyTotalYear0          = str_replace(",","",$_POST['LongTermBorrowingsInterCompanyTotalYear0']);
		 $LongTermBorrowingsInterCompanyTotalYear1          = str_replace(",","",$_POST['LongTermBorrowingsInterCompanyTotalYear1']);
		 $LongTermBorrowingsInterCompanyTotalYear2          = str_replace(",","",$_POST['LongTermBorrowingsInterCompanyTotalYear2']);
		 $LongTermBorrowingsInterCompanyTotalYear3          = str_replace(",","",$_POST['LongTermBorrowingsInterCompanyTotalYear3']);
		
	     $InterCompanyLoansTotalYear0                       = str_replace(",","",$_POST['InterCompanyLoansTotalYear0']);
		 $InterCompanyLoansTotalYear1                       = str_replace(",","",$_POST['InterCompanyLoansTotalYear1']);
		 $InterCompanyLoansTotalYear2                       = str_replace(",","",$_POST['InterCompanyLoansTotalYear2']);
		 $InterCompanyLoansTotalYear3                       = str_replace(",","",$_POST['InterCompanyLoansTotalYear3']);
	     
		 $TotalLongTermBorrowingsYear0                      = str_replace(",","",$_POST['TotalLongTermBorrowingsYear0']);
		 $TotalLongTermBorrowingsYear1                      = str_replace(",","",$_POST['TotalLongTermBorrowingsYear1']);
		 $TotalLongTermBorrowingsYear2                      = str_replace(",","",$_POST['TotalLongTermBorrowingsYear2']);
		 $TotalLongTermBorrowingsYear3                      = str_replace(",","",$_POST['TotalLongTermBorrowingsYear3']);
		 
		 $OtherNonCurrentLiabilitiesTotalYear0              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear0']);
		 $OtherNonCurrentLiabilitiesTotalYear1              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear1']);
		 $OtherNonCurrentLiabilitiesTotalYear2              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear2']);
		 $OtherNonCurrentLiabilitiesTotalYear3              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear3']);
 	 	
		 $OtherNonCurrentLiabilitiesGrandTotalYear0         = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesGrandTotalYear0']);
		 $OtherNonCurrentLiabilitiesGrandTotalYear1         = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesGrandTotalYear1']);
		 $OtherNonCurrentLiabilitiesGrandTotalYear2         = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesGrandTotalYear2']);
		 $OtherNonCurrentLiabilitiesGrandTotalYear3         = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesGrandTotalYear3']);
		 
		 $DeferredTaxBalanceYear0                           = str_replace(",","",$_POST['DeferredTaxBalanceYear0']);
		 $DeferredTaxBalanceYear1                           = str_replace(",","",$_POST['DeferredTaxBalanceYear1']);
		 $DeferredTaxBalanceYear2                           = str_replace(",","",$_POST['DeferredTaxBalanceYear2']);
		 $DeferredTaxBalanceYear3                           = str_replace(",","",$_POST['DeferredTaxBalanceYear3']);
		  
		 $ProvisionsYear0                                   = str_replace(",","",$_POST['ProvisionsYear0']);
		 $ProvisionsYear1                                   = str_replace(",","",$_POST['ProvisionsYear1']);
		 $ProvisionsYear2                                   = str_replace(",","",$_POST['ProvisionsYear2']);
		 $ProvisionsYear3                                   = str_replace(",","",$_POST['ProvisionsYear3']);
		 
		 $OtherNonCurrentLiabilitiesTotalYear0              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear0']);
		 $OtherNonCurrentLiabilitiesTotalYear1              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear1']);
		 $OtherNonCurrentLiabilitiesTotalYear2              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear2']);
		 $OtherNonCurrentLiabilitiesTotalYear3              = str_replace(",","",$_POST['OtherNonCurrentLiabilitiesTotalYear3']);
		 
	     $TotalNonCurrentLiabilitiesYear0                   = str_replace(",","",$_POST['TotalNonCurrentLiabilitiesYear0']);
		 $TotalNonCurrentLiabilitiesYear1                   = str_replace(",","",$_POST['TotalNonCurrentLiabilitiesYear1']);
		 $TotalNonCurrentLiabilitiesYear2                   = str_replace(",","",$_POST['TotalNonCurrentLiabilitiesYear2']);
		 $TotalNonCurrentLiabilitiesYear3                   = str_replace(",","",$_POST['TotalNonCurrentLiabilitiesYear3']);
				 
	 

	 
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
			line_desc = 'MortgageLoansYear1',
			reporting_year = '$MortgageLoansYear1',
			amount = '$MortgageLoansYear1',
			comment = '$OtherNonCurrentLiabilitiesComments'
		    ";
  **/
        //$application_ref = $_POST['application_ref];
		//$username = $_POST['username'];
		//$company_reg_no = $_POST['company_reg_no'];
        
		$application_ref =$_POST['application_ref'];
		$username = $_POST['username'];
		$report_name = "NonCurrentLiabilities";
		$company_reg_no = $_POST['company_reg_no'];
		$blank = null;
		$update_type = "Application";
		$reporting_year0 = $_POST['financial_year0'];
		$reporting_year1 = $_POST['financial_year1'];
		$reporting_year2 = $_POST['financial_year2'];
		$reporting_year3 = $_POST['financial_year3'];
	 
		 $insert_query =  "REPLACE INTO accounts 
              (application_ref   ,company_reg_no    ,report_name  , update_type  , username  ,line_desc1                                ,line_desc2                                         ,reporting_year     , amount)
			VALUES 
			  /** Non Current Liabilities **/
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','MortgageLoans'                           ,'$blank'                                           ,'$reporting_year0' ,'$MortgageLoansYear0'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','MortgageLoans'                           ,'$blank'                                           ,'$reporting_year1' ,'$MortgageLoansYear1'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','MortgageLoans'                           ,'$blank'                                           ,'$reporting_year2' ,'$MortgageLoansYear2'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','MortgageLoans'                           ,'$blank'                                           ,'$reporting_year3' ,'$MortgageLoansYear3'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TermLoans'                               ,'$blank'                                           ,'$reporting_year0' ,'$TermLoansYear0'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TermLoans'                               ,'$blank'                                           ,'$reporting_year1' ,'$TermLoansYear1'),
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TermLoans'                               ,'$blank'                                           ,'$reporting_year2' ,'$TermLoansYear2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TermLoans'                               ,'$blank'                                           ,'$reporting_year3' ,'$TermLoansYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Bonds'                                   ,'$blank'                                           ,'$reporting_year0' ,'$BondsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Bonds'                                   ,'$blank'                                           ,'$reporting_year1' ,'$BondsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Bonds'                                   ,'$blank'                                           ,'$reporting_year2' ,'$BondsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Bonds'                                   ,'$blank'                                           ,'$reporting_year3' ,'$BondsYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','FinanceLeases'                           ,'$blank'                                           ,'$reporting_year0' ,'$FinanceLeasesYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','FinanceLeases'                           ,'$blank'                                           ,'$reporting_year1' ,'$FinanceLeasesYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','FinanceLeases'                           ,'$blank'                                           ,'$reporting_year2' ,'$FinanceLeasesYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','FinanceLeases'                           ,'$blank'                                           ,'$reporting_year3' ,'$FinanceLeasesYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year0' ,'$OtherLongTermBorrowingsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year1' ,'$OtherLongTermBorrowingsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year2' ,'$OtherLongTermBorrowingsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year3' ,'$OtherLongTermBorrowingsYear3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareholdersLoans'                       ,'$blank'                                           ,'$reporting_year0' ,'$ShareholdersLoansYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareholdersLoans'                       ,'$blank'                                           ,'$reporting_year1' ,'$ShareholdersLoansYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareholdersLoans'                       ,'$blank'                                           ,'$reporting_year2' ,'$ShareholdersLoansYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','ShareholdersLoans'                       ,'$blank'                                           ,'$reporting_year3' ,'$ShareholdersLoansYear3'),
			  
			  
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine1'                  ,'$InterCompanyLoansDescLine1'                      ,'$reporting_year0' ,'$InterCompanyLoansLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine1'                  ,'$InterCompanyLoansDescLine1'                      ,'$reporting_year1' ,'$InterCompanyLoansLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine1'                  ,'$InterCompanyLoansDescLine1'                      ,'$reporting_year2' ,'$InterCompanyLoansLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine1'                  ,'$InterCompanyLoansDescLine1'                      ,'$reporting_year3' ,'$InterCompanyLoansLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine2'                  ,'$InterCompanyLoansDescLine2'                      ,'$reporting_year0' ,'$InterCompanyLoansLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine2'                  ,'$InterCompanyLoansDescLine2'                      ,'$reporting_year1' ,'$InterCompanyLoansLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine2'                  ,'$InterCompanyLoansDescLine2'                      ,'$reporting_year2' ,'$InterCompanyLoansLine2Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine2'                  ,'$InterCompanyLoansDescLine2'                      ,'$reporting_year3' ,'$InterCompanyLoansLine2Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine3'                  ,'$InterCompanyLoansDescLine3'                      ,'$reporting_year0' ,'$InterCompanyLoansLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine3'                  ,'$InterCompanyLoansDescLine3'                      ,'$reporting_year1' ,'$InterCompanyLoansLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine3'                  ,'$InterCompanyLoansDescLine3'                      ,'$reporting_year2' ,'$InterCompanyLoansLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansLine3'                  ,'$InterCompanyLoansDescLine3'                      ,'$reporting_year3' ,'$InterCompanyLoansLine3Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine1'         ,'$OtherNonCurrentLiabilitiesDescLine1'             ,'$reporting_year0' ,'$OtherNonCurrentLiabilitiesLine1Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine1'         ,'$OtherNonCurrentLiabilitiesDescLine1'             ,'$reporting_year1' ,'$OtherNonCurrentLiabilitiesLine1Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine1'         ,'$OtherNonCurrentLiabilitiesDescLine1'             ,'$reporting_year2' ,'$OtherNonCurrentLiabilitiesLine1Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine1'         ,'$OtherNonCurrentLiabilitiesDescLine1'             ,'$reporting_year3' ,'$OtherNonCurrentLiabilitiesLine1Year3'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine2'         ,'$OtherNonCurrentLiabilitiesDescLine2'             ,'$reporting_year0' ,'$OtherNonCurrentLiabilitiesLine2Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine2'         ,'$OtherNonCurrentLiabilitiesDescLine2'             ,'$reporting_year1' ,'$OtherNonCurrentLiabilitiesLine2Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine2'         ,'$OtherNonCurrentLiabilitiesDescLine2'             ,'$reporting_year2' ,'$OtherNonCurrentLiabilitiesLine2Year2'),
		      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine2'         ,'$OtherNonCurrentLiabilitiesDescLine2'             ,'$reporting_year3' ,'$OtherNonCurrentLiabilitiesLine2Year3'),
	   	      ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine3'         ,'$OtherNonCurrentLiabilitiesDescLine3'             ,'$reporting_year0' ,'$OtherNonCurrentLiabilitiesLine3Year0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine3'         ,'$OtherNonCurrentLiabilitiesDescLine3'             ,'$reporting_year1' ,'$OtherNonCurrentLiabilitiesLine3Year1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine3'         ,'$OtherNonCurrentLiabilitiesDescLine3'             ,'$reporting_year2' ,'$OtherNonCurrentLiabilitiesLine3Year2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesLine3'         ,'$OtherNonCurrentLiabilitiesDescLine3'             ,'$reporting_year3' ,'$OtherNonCurrentLiabilitiesLine3Year3'),
	   	      
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'  ,'$blank'                                            ,'$reporting_year0' ,'$LongTermBorrowingsNonInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'  ,'$blank'                                            ,'$reporting_year1' ,'$LongTermBorrowingsNonInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'  ,'$blank'                                            ,'$reporting_year2' ,'$LongTermBorrowingsNonInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsNonInterCompanyTotal'  ,'$blank'                                            ,'$reporting_year3' ,'$LongTermBorrowingsNonInterCompanyTotalYear3'),
			  			  
						  
              ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsInterCompanyTotal'     ,'$blank'                                            ,'$reporting_year0' ,'$LongTermBorrowingsInterCompanyTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsInterCompanyTotal'     ,'$blank'                                            ,'$reporting_year1' ,'$LongTermBorrowingsInterCompanyTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsInterCompanyTotal'     ,'$blank'                                            ,'$reporting_year2' ,'$LongTermBorrowingsInterCompanyTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','LongTermBorrowingsInterCompanyTotal'     ,'$blank'                                            ,'$reporting_year3' ,'$LongTermBorrowingsInterCompanyTotalYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansTotal'                  ,'$blank'                                           ,'$reporting_year0' ,'$InterCompanyLoansTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansTotal'                  ,'$blank'                                           ,'$reporting_year1' ,'$InterCompanyLoansTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansTotal'                  ,'$blank'                                           ,'$reporting_year2' ,'$InterCompanyLoansTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','InterCompanyLoansTotal'                  ,'$blank'                                           ,'$reporting_year3' ,'$InterCompanyLoansTotalYear3'),
	   	      
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year0' ,'$TotalLongTermBorrowingsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year1' ,'$TotalLongTermBorrowingsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year2' ,'$TotalLongTermBorrowingsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalLongTermBorrowings'                 ,'$blank'                                           ,'$reporting_year3' ,'$TotalLongTermBorrowingsYear3'),
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'    ,'$blank'                                           ,'$reporting_year0' ,'$OtherNonCurrentLiabilitiesGrandTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'    ,'$blank'                                           ,'$reporting_year1' ,'$OtherNonCurrentLiabilitiesGrandTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'    ,'$blank'                                           ,'$reporting_year2' ,'$OtherNonCurrentLiabilitiesGrandTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesGrandTotal'    ,'$blank'                                           ,'$reporting_year3' ,'$OtherNonCurrentLiabilitiesGrandTotalYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTaxBalance'                      ,'$blank'                                           ,'$reporting_year0' ,'$DeferredTaxBalanceYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTaxBalance'                      ,'$blank'                                           ,'$reporting_year1' ,'$DeferredTaxBalanceYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTaxBalance'                      ,'$blank'                                           ,'$reporting_year2' ,'$DeferredTaxBalanceYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','DeferredTaxBalance'                      ,'$blank'                                           ,'$reporting_year3' ,'$DeferredTaxBalanceYear3'),
	
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Provisions'                              ,'$blank'                                           ,'$reporting_year0' ,'$ProvisionsYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Provisions'                              ,'$blank'                                           ,'$reporting_year1' ,'$ProvisionsYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Provisions'                              ,'$blank'                                           ,'$reporting_year2' ,'$ProvisionsYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','Provisions'                              ,'$blank'                                           ,'$reporting_year3' ,'$ProvisionsYear3'),
				  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesTotal'         ,'$blank'                                           ,'$reporting_year0' ,'$OtherNonCurrentLiabilitiesTotalYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesTotal'         ,'$blank'                                           ,'$reporting_year1' ,'$OtherNonCurrentLiabilitiesTotalYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesTotal'         ,'$blank'                                           ,'$reporting_year2' ,'$OtherNonCurrentLiabilitiesTotalYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','OtherNonCurrentLiabilitiesTotal'         ,'$blank'                                           ,'$reporting_year3' ,'$OtherNonCurrentLiabilitiesTotalYear3'),
	   	      
			  
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentLiabilities'              ,'$blank'                                           ,'$reporting_year0' ,'$TotalNonCurrentLiabilitiesYear0'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentLiabilities'              ,'$blank'                                           ,'$reporting_year1' ,'$TotalNonCurrentLiabilitiesYear1'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentLiabilities'              ,'$blank'                                           ,'$reporting_year2' ,'$TotalNonCurrentLiabilitiesYear2'),
			  ('$application_ref','$company_reg_no','$report_name','$update_type','$username','TotalNonCurrentLiabilities'              ,'$blank'                                           ,'$reporting_year3' ,'$TotalNonCurrentLiabilitiesYear3')";
	
	
	
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
			  ('$application_ref','$company_reg_no' ,'Non Current Liabilities' ,'$username'  ,  now()     )";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 

		  if (!$resultm)
          {                                                    
             echo 'error with inserting non current liabilities record on progress tracker'. mysqli_error();
          }		
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Non Current Liabilities		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   non_current_liabilities_pdate    = now(), 
		   non_current_liabilities_username = '$username'
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