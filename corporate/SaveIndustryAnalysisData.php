
<html>

<head>
<Title>CORPORATE CREDIT SCORING - Saving Industry Analysis Data</Title>
</head>

<body>
<?php

include 'InitialiseIndustryAnalysisVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$IndustryCyclicality            =  $_POST['IndustryCyclicality'] ;
$IndustryPerformance            =  $_POST['IndustryPerformance'] ;
$ThreatsOfNewEntryRating        =  $_POST['ThreatsOfNewEntryRating'] ;
$ThreatsOfNewEntryScore         =  $_POST['ThreatsOfNewEntryScore'] ;
$EntryCostsRating               =  $_POST['EntryCostsRating'] ;
$EntryCostsScore                =  $_POST['EntryCostsScore'] ;
$EntryCostsComment              =  $_POST['EntryCostsComment'] ;
$SpecialistKnowledgeRating      =  $_POST['SpecialistKnowledgeRating'] ;
$SpecialKnowledgeScore          =  $_POST['SpecialKnowledgeScore'] ;
$SpecialistKnowledgeComment     =  $_POST['SpecialistKnowledgeComment'] ;
$EconomiesOfScaleRating         =  $_POST['EconomiesOfScaleRating'] ;
$EconomiesOfScaleScore          =  $_POST['EconomiesOfScaleScore'] ;
$EconomiesOfScaleComment        =  $_POST['EconomiesOfScaleComment'] ;
$CostAdvantagesRating           =  $_POST['CostAdvantagesRating'] ;
$CostAdvantagesScore            =  $_POST['CostAdvantagesScore'] ;
$CostAdvantagesComment          =  $_POST['CostAdvantagesComment'] ;
$TechnologyProtectionRating     =  $_POST['TechnologyProtectionRating'] ;
$TechnologyProtectionScore      =  $_POST['TechnologyProtectionScore'] ;
$TechnologyProtectionComment    =  $_POST['TechnologyProtectionComment'] ;
$BarriersToEntryRating          =  $_POST['BarriersToEntryRating'] ;
$BarriersToEntryScore           =  $_POST['BarriersToEntryScore'] ;
$BarriersToEntryComment         =  $_POST['BarriersToEntryComment'] ;
$CompetitiveRivalryRating       =  $_POST['CompetitiveRivalryRating'] ;
$CompetitiveRivalryScore        =  $_POST['CompetitiveRivalryScore'] ;
$NumberOfCompetitorsRating      =  $_POST['NumberOfCompetitorsRating'] ;
$NumberOfCompetitorsScore       =  $_POST['NumberOfCompetitorsScore'] ;
$NumberOfCompetitorsComment     =  $_POST['NumberOfCompetitorsComment'] ;
$QualityDifferencesRating       =  $_POST['QualityDifferencesRating'] ;
$QualityDifferencesScore        =  $_POST['QualityDifferencesScore'] ;
$QualityDifferencesComment      =  $_POST['QualityDifferencesComment'] ;
$OtherDifferencesRating         =  $_POST['OtherDifferencesRating'] ;
$OtherDifferencesScore          =  $_POST['OtherDifferencesScore'] ;
$OtherDifferencesComment        =  $_POST['OtherDifferencesComment'] ;
$SwitchingCostsRating           =  $_POST['SwitchingCostsRating'] ;
$SwitchingCostsScore            =  $_POST['SwitchingCostsScore'] ;
$SwitchingCostsComment          =  $_POST['SwitchingCostsComment'] ;
$CustomerLoyaltyRating          =  $_POST['CustomerLoyaltyRating'] ;
$CustomerLoyaktyScore           =  $_POST['CustomerLoyaktyScore'] ;
$CustomerLoyaktyComment         =  $_POST['CustomerLoyaktyComment'] ;
$SupplierPowerRating            =  $_POST['SupplierPowerRating'] ;
$SupplierPowerScore             =  $_POST['SupplierPowerScore'] ;
$NumberOfSuppliersrsRating      =  $_POST['NumberOfSuppliersrsRating'] ;
$NumberOfSuppliersOverallScore  =  $_POST['NumberOfSuppliersOverallScore'] ;
$NumberOfSuppliersComment       =  $_POST['NumberOfSuppliersComment'] ;
$SizeOfSuppliersRating          =  $_POST['SizeOfSuppliersRating'] ;
$SizeOfSuppliersOverallScore    =  $_POST['SizeOfSuppliersOverallScore'] ;
$SizeOfSuppliersComment         =  $_POST['SizeOfSuppliersComment'] ;
$UniquenessOfServiceRating      =  $_POST['UniquenessOfServiceRating'] ;
$UniquenessOfServiceScore       =  $_POST['UniquenessOfServiceScore'] ;
$UniquenessOfServiceComment     =  $_POST['UniquenessOfServiceComment'] ;
$CostsOfSupplierChangeRating    =  $_POST['CostsOfSupplierChangeRating'] ;
$CostsOfSupplierChangeScore     =  $_POST['CostsOfSupplierChangeScore'] ;
$CostsOfSupplierChangeComment   =  $_POST['CostsOfSupplierChangeComment'] ;
$SupplierSwitchingCostsRating   =  $_POST['SupplierSwitchingCostsRating'] ;
$SupplierSwitchingCostsScore    =  $_POST['SupplierSwitchingCostsScore'] ;
$SupplierSwitchingCostsComment  =  $_POST['SupplierSwitchingCostsComment'] ;
$ThreatsOfSubstitutionRating    =  $_POST['ThreatsOfSubstitutionRating'] ;
$ThreatsOfSubstitutionScore     =  $_POST['ThreatsOfSubstitutionScore'] ;
$SubstitutePerfomanceRating     =  $_POST['SubstitutePerfomanceRating'] ;
$SubstitutePerformanceScore     =  $_POST['SubstitutePerformanceScore'] ;
$SubstitutePerfomanceComment    =  $_POST['SubstitutePerfomanceComment'] ;
$CostsOfSubstitutionRating      =  $_POST['CostsOfSubstitutionRating'] ;
$CostsOfSubstitutionScore       =  $_POST['CostsOfSubstitutionScore'] ;
$CostsOfSubstitutionComment     =  $_POST['CostsOfSubstitutionComment'] ;
$BuyerPowerRating               =  $_POST['BuyerPowerRating'] ;
$BuyerPowerScore                =  $_POST['BuyerPowerScore'] ;
$NumberOfCustomersRating        =  $_POST['NumberOfCustomersRating'] ;
$NumberOfCCustomersScore        =  $_POST['NumberOfCCustomersScore'] ;
$NumberOfCustomersComment       =  $_POST['NumberOfCustomersComment'] ;
$SingleOrderSizeRating          =  $_POST['SingleOrderSizeRating'] ;
$SingleOrderSizeScore           =  $_POST['SingleOrderSizeScore'] ;
$SingleOrderSizeComment         =  $_POST['SingleOrderSizeComment'] ;
$CompetitorDifferencesRating    =  $_POST['CompetitorDifferencesRating'] ;
$CompetitorDifferencesScore     =  $_POST['CompetitorDifferencesScore'] ;
$CompetitorDifferencesComment   =  $_POST['CompetitorDifferencesComment'] ;
$PriceSensitivityRating         =  $_POST['PriceSensitivityRating'] ;
$PriceSensitivityScore          =  $_POST['PriceSensitivityScore'] ;
$PriceSensitivityComment        =  $_POST['PriceSensitivityComment'] ;
$AbilityToSubstituteRating      =  $_POST['AbilityToSubstituteRating'] ;
$AbilityToSubstituteScore       =  $_POST['AbilityToSubstituteScore'] ;
$AbilityToSubstituteComment     =  $_POST['AbilityToSubstituteComment'] ;
$CustomersSwitchingCostsRating  =  $_POST['CustomersSwitchingCostsRating'] ;
$CustomerSwitchingCostsScore    =  $_POST['CustomerSwitchingCostsScore'] ;
$CustomersSwitchingCostsComment =  $_POST['CustomersSwitchingCostsComment'] ;
$SummaryRating                  =  $_POST['SummaryRating'] ;
$SummaryScore                   =  $_POST['SummaryScore'] ;





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
		 //========================UPDATE THE PROGRESS TRACKER - Industry Analysis Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Industry Analysis' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD - Industry Analysis		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   industry_analysis_pdate    = now(), 
		   industry_analysis_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 	         
		 $insert_query  =  "REPLACE INTO industry_analysis
( application_ref, company_reg_no, loan_number, 
IndustryCyclicality,
IndustryPerformance,
ThreatsOfNewEntryRating,
ThreatsOfNewEntryScore,
EntryCostsRating,
EntryCostsScore,
EntryCostsComment,
SpecialistKnowledgeRating,
SpecialKnowledgeScore,
SpecialistKnowledgeComment,
EconomiesOfScaleRating,
EconomiesOfScaleScore,
EconomiesOfScaleComment,
CostAdvantagesRating,
CostAdvantagesScore,
CostAdvantagesComment,
TechnologyProtectionRating,
TechnologyProtectionScore,
TechnologyProtectionComment,
BarriersToEntryRating,
BarriersToEntryScore,
BarriersToEntryComment,
CompetitiveRivalryRating,
CompetitiveRivalryScore,
NumberOfCompetitorsRating,
NumberOfCompetitorsScore,
NumberOfCompetitorsComment,
QualityDifferencesRating,
QualityDifferencesScore,
QualityDifferencesComment,
OtherDifferencesRating,
OtherDifferencesScore,
OtherDifferencesComment,
SwitchingCostsRating,
SwitchingCostsScore,
SwitchingCostsComment,
CustomerLoyaltyRating,
CustomerLoyaktyScore,
CustomerLoyaktyComment,
SupplierPowerRating,
SupplierPowerScore,
NumberOfSuppliersrsRating,
NumberOfSuppliersOverallScore,
NumberOfSuppliersComment,
SizeOfSuppliersRating,
SizeOfSuppliersOverallScore,
SizeOfSuppliersComment,
UniquenessOfServiceRating,
UniquenessOfServiceScore,
UniquenessOfServiceComment,
CostsOfSupplierChangeRating,
CostsOfSupplierChangeScore,
CostsOfSupplierChangeComment,
SupplierSwitchingCostsRating,
SupplierSwitchingCostsScore,
SupplierSwitchingCostsComment,
ThreatsOfSubstitutionRating,
ThreatsOfSubstitutionScore,
SubstitutePerfomanceRating,
SubstitutePerformanceScore,
SubstitutePerfomanceComment,
CostsOfSubstitutionRating,
CostsOfSubstitutionScore,
CostsOfSubstitutionComment,
BuyerPowerRating,
BuyerPowerScore,
NumberOfCustomersRating,
NumberOfCCustomersScore,
NumberOfCustomersComment,
SingleOrderSizeRating,
SingleOrderSizeScore,
SingleOrderSizeComment,
CompetitorDifferencesRating,
CompetitorDifferencesScore,
CompetitorDifferencesComment,
PriceSensitivityRating,
PriceSensitivityScore,
PriceSensitivityComment,
AbilityToSubstituteRating,
AbilityToSubstituteScore,
AbilityToSubstituteComment,
CustomersSwitchingCostsRating,
CustomerSwitchingCostsScore,

CustomersSwitchingCostsComment,
SummaryRating,
SummaryScore)

VALUES

('$application_ref','$company_reg_no','$loan_number','$IndustryCyclicality',
'$IndustryPerformance',
'$ThreatsOfNewEntryRating',
'$ThreatsOfNewEntryScore',
'$EntryCostsRating',
'$EntryCostsScore',
'$EntryCostsComment',
'$SpecialistKnowledgeRating',
'$SpecialKnowledgeScore',
'$SpecialistKnowledgeComment',
'$EconomiesOfScaleRating',
'$EconomiesOfScaleScore',
'$EconomiesOfScaleComment',
'$CostAdvantagesRating',
'$CostAdvantagesScore',
'$CostAdvantagesComment',
'$TechnologyProtectionRating',
'$TechnologyProtectionScore',
'$TechnologyProtectionComment',
'$BarriersToEntryRating',
'$BarriersToEntryScore',
'$BarriersToEntryComment',
'$CompetitiveRivalryRating',
'$CompetitiveRivalryScore',
'$NumberOfCompetitorsRating',
'$NumberOfCompetitorsScore',
'$NumberOfCompetitorsComment',
'$QualityDifferencesRating',
'$QualityDifferencesScore',
'$QualityDifferencesComment',
'$OtherDifferencesRating',
'$OtherDifferencesScore',
'$OtherDifferencesComment',
'$SwitchingCostsRating',
'$SwitchingCostsScore',
'$SwitchingCostsComment',
'$CustomerLoyaltyRating',
'$CustomerLoyaktyScore',
'$CustomerLoyaktyComment',
'$SupplierPowerRating',
'$SupplierPowerScore',
'$NumberOfSuppliersrsRating',
'$NumberOfSuppliersOverallScore',
'$NumberOfSuppliersComment',
'$SizeOfSuppliersRating',
'$SizeOfSuppliersOverallScore',
'$SizeOfSuppliersComment',
'$UniquenessOfServiceRating',
'$UniquenessOfServiceScore',
'$UniquenessOfServiceComment',
'$CostsOfSupplierChangeRating',
'$CostsOfSupplierChangeScore',
'$CostsOfSupplierChangeComment',
'$SupplierSwitchingCostsRating',
'$SupplierSwitchingCostsScore',
'$SupplierSwitchingCostsComment',
'$ThreatsOfSubstitutionRating',
'$ThreatsOfSubstitutionScore',
'$SubstitutePerfomanceRating',
'$SubstitutePerformanceScore',
'$SubstitutePerfomanceComment',
'$CostsOfSubstitutionRating',
'$CostsOfSubstitutionScore',
'$CostsOfSubstitutionComment',
'$BuyerPowerRating',
'$BuyerPowerScore',
'$NumberOfCustomersRating',
'$NumberOfCCustomersScore',
'$NumberOfCustomersComment',
'$SingleOrderSizeRating',
'$SingleOrderSizeScore',
'$SingleOrderSizeComment',
'$CompetitorDifferencesRating',
'$CompetitorDifferencesScore',
'$CompetitorDifferencesComment',
'$PriceSensitivityRating',
'$PriceSensitivityScore',
'$PriceSensitivityComment',
'$AbilityToSubstituteRating',
'$AbilityToSubstituteScore',
'$AbilityToSubstituteComment',
'$CustomersSwitchingCostsRating',
'$CustomerSwitchingCostsScore',
'$CustomersSwitchingCostsComment',
'$SummaryRating',
'$SummaryScore')";



	
	
	$resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
 		    

		   header("Location:CorporateAddMenu.php");
           exit;		     echo 'Data successfully saved';
	      }
 ?>
</body>f

</html>