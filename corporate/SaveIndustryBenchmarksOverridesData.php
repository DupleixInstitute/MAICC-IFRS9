
<html>

<head>
<Title>Saving Industry Benchmarks Overrides Data</Title>
</head>

<body>
<?php

include 'InitialiseIndustryBenchmarksOverridesVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$application_ref                                  =  $_POST['application_ref'];
$company_reg_no                                   =  $_POST['company_reg_no'];
$loan_number                                      =  $_POST['loan_number'];

if (isset($_POST['CurrentRatioBenchmarkType_'])) {
$CurrentRatioBenchmarkType_                       =  $_POST['CurrentRatioBenchmarkType_'];}

if (isset($_POST['CurrentRatioBenchmarkValue_'])) {
$CurrentRatioPolicyBenchmarkValue_                =  $_POST['CurrentRatioPolicyBenchmarkValue_'];}

if (isset($_POST['CurrentRatioOverrideBenchmarkType_'])) {
$CurrentRatioOverrideBenchmarkType_               =  $_POST['CurrentRatioOverrideBenchmarkType_'];}

if (isset($_POST['CurrentRatioOverrideBenchmarkValue_'])) {
$CurrentRatioOverrideBenchmarkValue_              =  $_POST['CurrentRatioOverrideBenchmarkValue_'];}

if (isset($_POST['CurrentRatioOverrideComment_'])) {
$CurrentRatioOverrideComment_                     =  $_POST['CurrentRatioOverrideComment_'];}

if (isset($_POST['CurrentRatioBenchmarkFirstApproval_'])) {
$CurrentRatioBenchmarkFirstApproval_              =  $_POST['CurrentRatioBenchmarkFirstApproval_'];}

if (isset($_POST['CurrentRatioBenchmarkSecondApproval_'])) {
$CurrentRatioBenchmarkSecondApproval_             =  $_POST['CurrentRatioBenchmarkSecondApproval_'];}

if (isset($_POST['DebtorDaysBenchmarkType_'])) {
$DebtorDaysBenchmarkType_                         =  $_POST['DebtorDaysBenchmarkType_'];}

if (isset($_POST['DebtorDaysPolicyBenchmarkValue_'])) {
$DebtorDaysPolicyBenchmarkValue_                  =  $_POST['DebtorDaysPolicyBenchmarkValue_'];}

if (isset($_POST['DebtorDaysOverrideBenchmarkType_'])) {
$DebtorDaysOverrideBenchmarkType_                 =  $_POST['DebtorDaysOverrideBenchmarkType_'];}

if (isset($_POST['DebtorDaysOverrideBenchmarkValue_'])) {
$DebtorDaysOverrideBenchmarkValue_                =  $_POST['DebtorDaysOverrideBenchmarkValue_'];}

if (isset($_POST['DebtorDaysOverrideComment_'])) {
$DebtorDaysOverrideComment_                       =  $_POST['DebtorDaysOverrideComment_'];}

if (isset($_POST['DebtorDaysBenchmarkFirstApproval_'])) {
$DebtorDaysBenchmarkFirstApproval_                =  $_POST['DebtorDaysBenchmarkFirstApproval_'];}

if (isset($_POST['DebtorDaysBenchmarkSecondApproval_'])) {
$DebtorDaysBenchmarkSecondApproval_               =  $_POST['DebtorDaysBenchmarkSecondApproval_'];}

if (isset($_POST['TurnoverToWCBenchmarkType_'])) {
$TurnoverToWCBenchmarkType_                       =  $_POST['TurnoverToWCBenchmarkType_'];}

if (isset($_POST['TurnoverToWCPolicyBenchmarkValue_'])) {
$TurnoverToWCPolicyBenchmarkValue_                =  $_POST['TurnoverToWCPolicyBenchmarkValue_'];}

if (isset($_POST['TurnoverToWCOverrideBenchmarkType_'])) {
$TurnoverToWCOverrideBenchmarkType_               =  $_POST['TurnoverToWCOverrideBenchmarkType_'];}

if (isset($_POST['TurnoverToWCOverrideBenchmarkValue_'])) {
$TurnoverToWCOverrideBenchmarkValue_              =  $_POST['TurnoverToWCOverrideBenchmarkValue_'];}

if (isset($_POST['TurnoverToWCOverrideComment_'])) {
$TurnoverToWCOverrideComment_                     =  $_POST['TurnoverToWCOverrideComment_'];}

if (isset($_POST['TurnoverToWCBenchmarkFirstApproval_'])) {
$TurnoverToWCBenchmarkFirstApproval_              =  $_POST['TurnoverToWCBenchmarkFirstApproval_'];}

if (isset($_POST['TurnoverToWCBenchmarkSecondApproval_'])) {
$TurnoverToWCBenchmarkSecondApproval_             =  $_POST['TurnoverToWCBenchmarkSecondApproval_'];}

if (isset($_POST['TurnoverGrowthBenchmarkType_'])) {
$TurnoverGrowthBenchmarkType_                     =  $_POST['TurnoverGrowthBenchmarkType_'];}

if (isset($_POST['TurnoverGrowthPolicyBenchmarkValue_'])) {
$TurnoverGrowthPolicyBenchmarkValue_              =  $_POST['TurnoverGrowthPolicyBenchmarkValue_'];}

if (isset($_POST['TurnoverGrowthOverrideBenchmarkType_'])) {
$TurnoverGrowthOverrideBenchmarkType_             =  $_POST['TurnoverGrowthOverrideBenchmarkType_'];}

if (isset($_POST['TurnoverGrowthOverrideBenchmarkValue_'])) {
$TurnoverGrowthOverrideBenchmarkValue_            =  $_POST['TurnoverGrowthOverrideBenchmarkValue_'];}

if (isset($_POST['TurnoverGrowthOverrideComment_'])) {
$TurnoverGrowthOverrideComment_                   =  $_POST['TurnoverGrowthOverrideComment_'];}

if (isset($_POST['TurnoverGrowthBenchmarkFirstApproval_'])) {
$TurnoverGrowthBenchmarkFirstApproval_            =  $_POST['TurnoverGrowthBenchmarkFirstApproval_'];}

if (isset($_POST['TurnoverGrowthBenchmarkSecondApproval_'])) {
$TurnoverGrowthBenchmarkSecondApproval_           =  $_POST['TurnoverGrowthBenchmarkSecondApproval_'];}

if (isset($_POST['GrossProfitMarginBenchmarkType_'])) {
$GrossProfitMarginBenchmarkType_                  =  $_POST['GrossProfitMarginBenchmarkType_'];}


$GrossProfitMarginPolicyBenchmarkValue_           =  $_POST['GrossProfitMarginPolicyBenchmarkValue_'];

if (isset($_POST['GrossProfitMarginOverrideBenchmarkType_'])) {
$GrossProfitMarginOverrideBenchmarkType_          =  $_POST['GrossProfitMarginOverrideBenchmarkType_'];}

if (isset($_POST['GrossProfitMarginOverrideBenchmarkValue_'])) {
$GrossProfitMarginOverrideBenchmarkValue_         =  $_POST['GrossProfitMarginOverrideBenchmarkValue_'];}


$GrossProfitMarginOverrideComment_                =  $_POST['GrossProfitMarginOverrideComment_'];


$GrossProfitMarginBenchmarkFirstApproval_         =  $_POST['GrossProfitMarginBenchmarkFirstApproval_'];


$GrossProfitMarginBenchmarkSecondApproval_        =  $_POST['GrossProfitMarginBenchmarkSecondApproval_'];


$OperatingProfitMarginBenchmarkType_              =  $_POST['OperatingProfitMarginBenchmarkType_'];

$OperatingProfitMarginPolicyBenchmarkValue_       =  $_POST['OperatingProfitMarginPolicyBenchmarkValue_'];

$OperatingProfitMarginOverrideBenchmarkType_      =  $_POST['OperatingProfitMarginOverrideBenchmarkType_'];

$OperatingProfitMarginOverrideBenchmarkValue_     =  $_POST['OperatingProfitMarginOverrideBenchmarkValue_'];

$OperatingProfitMarginOverrideComment_            =  $_POST['OperatingProfitMarginOverrideComment_'];

$OperatingProfitMarginBenchmarkFirstApproval_     =  $_POST['OperatingProfitMarginBenchmarkFirstApproval_'];

$OperatingProfitMarginBenchmarkSecondApproval_    =  $_POST['OperatingProfitMarginBenchmarkSecondApproval_'];
$ROABenchmarkType_                                =  $_POST['ROABenchmarkType_'];

$ROAPolicyBenchmarkValue_                         =  $_POST['ROAPolicyBenchmarkValue_'];

if (isset($_POST['ROAOverrideBenchmarkType_'])) {
$ROAOverrideBenchmarkType_                        =  $_POST['ROAOverrideBenchmarkType_'];}

if (isset($_POST['ROAOverrideBenchmarkValue_'])) {
$ROAOverrideBenchmarkValue_                       =  $_POST['ROAOverrideBenchmarkValue_'];}

$ROAOverrideComment_                              =  $_POST['ROAOverrideComment_'];

$ROABenchmarkFirstApproval_                       =  $_POST['ROABenchmarkFirstApproval_'];

$ROABenchmarkSecondApproval_                      =  $_POST['ROABenchmarkSecondApproval_'];

$ROIBenchmarkType_                                =  $_POST['ROIBenchmarkType_'];

$ROIPolicyBenchmarkValue_                         =  $_POST['ROIPolicyBenchmarkValue_'];

if (isset($_POST['ROIOverrideBenchmarkType_'])) {
$ROIOverrideBenchmarkType_                        =  $_POST['ROIOverrideBenchmarkType_'];}

if (isset($_POST['ROIOverrideBenchmarkValue_'])) {
$ROIOverrideBenchmarkValue_                       =  $_POST['ROIOverrideBenchmarkValue_'];}

$ROIOverrideComment_                              =  $_POST['ROIOverrideComment_'];

$ROIBenchmarkFirstApproval_                       =  $_POST['ROIBenchmarkFirstApproval_'];

$ROIBenchmarkSecondApproval_                      =  $_POST['ROIBenchmarkSecondApproval_'];

$LongtermDebtToEquityBenchmarkType_               =  $_POST['LongtermDebtToEquityBenchmarkType_'];

$LongtermDebtToEquityPolicyBenchmarkValue_        =  $_POST['LongtermDebtToEquityPolicyBenchmarkValue_'];

if (isset($_POST['LongtermDebtToEquityOverrideBenchmarkType_'])) {
$LongtermDebtToEquityOverrideBenchmarkType_       =  $_POST['LongtermDebtToEquityOverrideBenchmarkType_'];}

if (isset($_POST['LongtermDebtToEquityOverrideBenchmarkValue_'])) {
$LongtermDebtToEquityOverrideBenchmarkValue_      =  $_POST['LongtermDebtToEquityOverrideBenchmarkValue_'];}

$LongtermDebtToEquityOverrideComment_             =  $_POST['LongtermDebtToEquityOverrideComment_'];

$LongtermDebtToEquityBenchmarkFirstApproval_      =  $_POST['LongtermDebtToEquityBenchmarkFirstApproval_'];

$LongtermDebtToEquityBenchmarkSecondApproval_     =  $_POST['LongtermDebtToEquityBenchmarkSecondApproval_'];

$DebtToTangibleNetWorthBenchmarkType_             =  $_POST['DebtToTangibleNetWorthBenchmarkType_'];

$DebtToTangibleNetWorthPolicyBenchmarkValue_      =  $_POST['DebtToTangibleNetWorthPolicyBenchmarkValue_'];

if (isset($_POST['DebtToTangibleNetWorthOverrideBenchmarkType_'])) {
$DebtToTangibleNetWorthOverrideBenchmarkType_     =  $_POST['DebtToTangibleNetWorthOverrideBenchmarkType_'];}

$DebtToTangibleNetWorthOverrideBenchmarkValue_    =  $_POST['DebtToTangibleNetWorthOverrideBenchmarkValue_'];

$DebtToTangibleNetWorthOverrideComment_           =  $_POST['DebtToTangibleNetWorthOverrideComment_'];

$DebtToTangibleNetWorthBenchmarkFirstApproval_    =  $_POST['DebtToTangibleNetWorthBenchmarkFirstApproval_'];

$DebtToTangibleNetWorthBenchmarkSecondApproval_   =  $_POST['DebtToTangibleNetWorthBenchmarkSecondApproval_'];

$EquityToTotalAssetsBenchmarkType_                =  $_POST['EquityToTotalAssetsBenchmarkType_'];

$EquityToTotalAssetsPolicyBenchmarkValue_         =  $_POST['EquityToTotalAssetsPolicyBenchmarkValue_'];

$EquityToTotalAssetsOverrideBenchmarkType_        =  $_POST['EquityToTotalAssetsOverrideBenchmarkType_'];

if (isset($_POST['EquityToTotalAssetsOverrideBenchmarkValue_'])) {
$EquityToTotalAssetsOverrideBenchmarkValue_       =  $_POST['EquityToTotalAssetsOverrideBenchmarkValue_'];}

$EquityToTotalAssetsOverrideComment_              =  $_POST['EquityToTotalAssetsOverrideComment_'];

$EquityToTotalAssetsBenchmarkFirstApproval_       =  $_POST['EquityToTotalAssetsBenchmarkFirstApproval_'];

$EquityToTotalAssetsBenchmarkSecondApproval_      =  $_POST['EquityToTotalAssetsBenchmarkSecondApproval_'];

$InterestCoverBenchmarkType_                      =  $_POST['InterestCoverBenchmarkType_'];

$InterestCoverPolicyBenchmarkValue_               =  $_POST['InterestCoverPolicyBenchmarkValue_'];

if (isset($_POST['InterestCoverOverrideBenchmarkType_'])) {
$InterestCoverOverrideBenchmarkType_              =  $_POST['InterestCoverOverrideBenchmarkType_'];}

if (isset($_POST['InterestCoverOverrideBenchmarkValue_'])) {
$InterestCoverOverrideBenchmarkValue_             =  $_POST['InterestCoverOverrideBenchmarkValue_'];}

$InterestCoverOverrideComment_                    =  $_POST['InterestCoverOverrideComment_'];

$InterestCoverBenchmarkFirstApproval_             =  $_POST['InterestCoverBenchmarkFirstApproval_'];

$InterestCoverBenchmarkSecondApproval_            =  $_POST['InterestCoverBenchmarkSecondApproval_'];

$EBITDAToDebtBenchmarkType_                       =  $_POST['EBITDAToDebtBenchmarkType_'];

$EBITDAToDebtPolicyBenchmarkValue_                =  $_POST['EBITDAToDebtPolicyBenchmarkValue_'];

if (isset($_POST['EBITDAToDebtOverrideBenchmarkType_'])) {
$EBITDAToDebtOverrideBenchmarkType_               =  $_POST['EBITDAToDebtOverrideBenchmarkType_'];}

if (isset($_POST['EBITDAToDebtOverrideBenchmarkValue_'])) {
$EBITDAToDebtOverrideBenchmarkValue_              =  $_POST['EBITDAToDebtOverrideBenchmarkValue_'];}

$EBITDAToDebtOverrideComment_                     =  $_POST['EBITDAToDebtOverrideComment_'];

$EBITDAToDebtBenchmarkFirstApproval_              =  $_POST['EBITDAToDebtBenchmarkFirstApproval_'];

$EBITDAToDebtBenchmarkSecondApproval_             =  $_POST['EBITDAToDebtBenchmarkSecondApproval_'];

$BenchmarkOverrideFirstReviewerComment_           =  $_POST['BenchmarkOverrideFirstReviewerComment_'];

$BenchmarkOverrideSecondReviewerComment_          =  $_POST['BenchmarkOverrideSecondReviewerComment_'];

$username                                         =  $_POST['username'];



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
		 //========================UPDATE THE PROGRESS TRACKER - Industry Benchmarks Overrides Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Industry Benchmarks Overrides' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Non Current AAssets		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   industry_benchmarks_overrides_pdate    = now(), 
		   industry_benchmarks_overrides_username = '$username'
		   
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 	        
	   $insert_query  =  "REPLACE INTO industry_ratio_benchmarks_overrides
     ( application_ref,
       company_reg_no,
       loan_number,
       current_ratio_bench_mark_type,
       current_ratio_policy_benchmark_value,
       current_ratio_override_benchmark_type,
       current_ratio_override_benchmark_value,
       current_ratio_override_comment,
       current_ratio_first_approval,
       current_ratio_second_approval,
       debtor_days_bench_mark_type,
       debtor_days_policy_benchmark_value,
       debtor_days_override_benchmark_type,
       debtor_days_override_benchmark_value,
       debtor_days_override_comment,
       debtor_days_first_approval,
       debtor_days_second_approval,
       turnover_ToWC_bench_mark_type,
       turnover_ToWC_policy_benchmark_value,
       turnover_ToWC_override_benchmark_type,
       turnover_ToWC_override_benchmark_value,
       turnover_ToWC_override_comment,
       turnover_ToWC_first_approval,
       turnover_ToWC_second_approval,
       turnover_growth_bench_mark_type,
       turnover_growth_policy_benchmark_value,
       turnover_growth_override_benchmark_type,
       turnover_growth_override_benchmark_value,
       turnover_growth_override_comment,
       turnover_growth_first_approval,
       turnover_growth_second_approval,
       gross_profit_margin_bench_mark_type,
       gross_profit_margin_policy_benchmark_value,
       gross_profit_margin_override_benchmark_type,
       gross_profit_margin_override_benchmark_value,
       gross_profit_margin_override_comment,
       gross_profit_margin_first_approval,
       gross_profit_margin_second_approval,
       operating_profit_margin_bench_mark_type,
       operating_profit_margin_policy_benchmark_value,
       operating_profit_margin_override_benchmark_type,
       operating_profit_margin_override_benchmark_value,
       operating_profit_margin_override_comment,
       operating_profit_margin_first_approval,
       operating_profit_margin_second_approval,
       ROA_bench_mark_type,
       ROA_policy_benchmark_value,
       ROA_override_benchmark_type,
       ROA_override_benchmark_value,
       ROA_override_comment,
       ROA_first_approval,
       ROA_second_approval,
       ROI_bench_mark_type,
       ROI_policy_benchmark_value,
       ROI_override_benchmark_type,
       ROI_override_benchmark_value,
       ROI_override_comment,
       ROI_first_approval,
       ROI_second_approval,
       longterm_debt_ToEquity_bench_mark_type,
       longterm_debt_ToEquity_policy_benchmark_value,
       longterm_debt_ToEquity_override_benchmark_type,
       longterm_debt_ToEquity_override_benchmark_value,
       longterm_debt_ToEquity_override_comment,
       longterm_debt_ToEquity_first_approval,
       longterm_debt_ToEquity_second_approval,
       debt_ToTangible_net_worth_bench_mark_type,
       debt_ToTangible_net_worth_policy_benchmark_value,
       debt_ToTangible_net_worth_override_benchmark_type,
       debt_ToTangible_net_worth_override_benchmark_value,
       debt_ToTangible_net_worth_override_comment,
       debt_ToTangible_net_worth_first_approval,
       debt_ToTangible_net_worth_second_approval,
       equity_ToTotal_assets_bench_mark_type,
       equity_ToTotal_assets_policy_benchmark_value,
       equity_ToTotal_assets_override_benchmark_type,
       equity_ToTotal_assets_override_benchmark_value,
       equity_ToTotal_assets_override_comment,
       equity_ToTotal_assets_first_approval,
       equity_ToTotal_assets_second_approval,
       interest_cover_bench_mark_type,
       interest_cover_policy_benchmark_value,
       interest_cover_override_benchmark_type,
       interest_cover_override_benchmark_value,
       interest_cover_override_comment,
       interest_cover_first_approval,
       interest_cover_second_approval,
       EBITDA_ToDebt_bench_mark_type,
       EBITDA_ToDebt_policy_benchmark_value,
       EBITDA_ToDebt_override_benchmark_type,
       EBITDA_ToDebt_override_benchmark_value,
       EBITDA_ToDebt_override_comment,
       EBITDA_ToDebt_first_approval,
       EBITDA_ToDebt_second_approval,
       benchmark_override_first_reviewer_comment,
       benchmark_override_second_reviewer_comment,
       username)
     
         VALUES
       ( '$application_ref',
       '$company_reg_no',
       '$loan_number',
       '$CurrentRatioBenchmarkType_',
       '$CurrentRatioPolicyBenchmarkValue_',
       '$CurrentRatioOverrideBenchmarkType_',
       '$CurrentRatioOverrideBenchmarkValue_',
       '$CurrentRatioOverrideComment_',
       '$CurrentRatioBenchmarkFirstApproval_',
       '$CurrentRatioBenchmarkSecondApproval_',
       '$DebtorDaysBenchmarkType_',
       '$DebtorDaysPolicyBenchmarkValue_',
       '$DebtorDaysOverrideBenchmarkType_',
       '$DebtorDaysOverrideBenchmarkValue_',
       '$DebtorDaysOverrideComment_',
       '$DebtorDaysBenchmarkFirstApproval_',
       '$DebtorDaysBenchmarkSecondApproval_',
       '$TurnoverToWCBenchmarkType_',
       '$TurnoverToWCPolicyBenchmarkValue_',
       '$TurnoverToWCOverrideBenchmarkType_',
       '$TurnoverToWCOverrideBenchmarkValue_',
       '$TurnoverToWCOverrideComment_',
       '$TurnoverToWCBenchmarkFirstApproval_',
       '$TurnoverToWCBenchmarkSecondApproval_',
       '$TurnoverGrowthBenchmarkType_',
       '$TurnoverGrowthPolicyBenchmarkValue_',
       '$TurnoverGrowthOverrideBenchmarkType_',
       '$TurnoverGrowthOverrideBenchmarkValue_',
       '$TurnoverGrowthOverrideComment_',
       '$TurnoverGrowthBenchmarkFirstApproval_',
       '$TurnoverGrowthBenchmarkSecondApproval_',
       '$GrossProfitMarginBenchmarkType_',
       '$GrossProfitMarginPolicyBenchmarkValue_',
       '$GrossProfitMarginOverrideBenchmarkType_',
       '$GrossProfitMarginOverrideBenchmarkValue_',
       '$GrossProfitMarginOverrideComment_',
       '$GrossProfitMarginBenchmarkFirstApproval_',
       '$GrossProfitMarginBenchmarkSecondApproval_',
       '$OperatingProfitMarginBenchmarkType_',
       '$OperatingProfitMarginPolicyBenchmarkValue_',
       '$OperatingProfitMarginOverrideBenchmarkType_',
       '$OperatingProfitMarginOverrideBenchmarkValue_',
       '$OperatingProfitMarginOverrideComment_',
       '$OperatingProfitMarginBenchmarkFirstApproval_',
       '$OperatingProfitMarginBenchmarkSecondApproval_',
       '$ROABenchmarkType_',
       '$ROAPolicyBenchmarkValue_',
       '$ROAOverrideBenchmarkType_',
       '$ROAOverrideBenchmarkValue_',
       '$ROAOverrideComment_',
       '$ROABenchmarkFirstApproval_',
       '$ROABenchmarkSecondApproval_',
       '$ROIBenchmarkType_',
       '$ROIPolicyBenchmarkValue_',
       '$ROIOverrideBenchmarkType_',
       '$ROIOverrideBenchmarkValue_',
       '$ROIOverrideComment_',
       '$ROIBenchmarkFirstApproval_',
       '$ROIBenchmarkSecondApproval_',
       '$LongtermDebtToEquityBenchmarkType_',
       '$LongtermDebtToEquityPolicyBenchmarkValue_',
       '$LongtermDebtToEquityOverrideBenchmarkType_',
       '$LongtermDebtToEquityOverrideBenchmarkValue_',
       '$LongtermDebtToEquityOverrideComment_',
       '$LongtermDebtToEquityBenchmarkFirstApproval_',
       '$LongtermDebtToEquityBenchmarkSecondApproval_',
       '$DebtToTangibleNetWorthBenchmarkType_',
       '$DebtToTangibleNetWorthPolicyBenchmarkValue_',
       '$DebtToTangibleNetWorthOverrideBenchmarkType_',
       '$DebtToTangibleNetWorthOverrideBenchmarkValue_',
       '$DebtToTangibleNetWorthOverrideComment_',
       '$DebtToTangibleNetWorthBenchmarkFirstApproval_',
       '$DebtToTangibleNetWorthBenchmarkSecondApproval_',
       '$EquityToTotalAssetsBenchmarkType_',
       '$EquityToTotalAssetsPolicyBenchmarkValue_',
       '$EquityToTotalAssetsOverrideBenchmarkType_',
       '$EquityToTotalAssetsOverrideBenchmarkValue_',
       '$EquityToTotalAssetsOverrideComment_',
       '$EquityToTotalAssetsBenchmarkFirstApproval_',
       '$EquityToTotalAssetsBenchmarkSecondApproval_',
       '$InterestCoverBenchmarkType_',
       '$InterestCoverPolicyBenchmarkValue_',
       '$InterestCoverOverrideBenchmarkType_',
       '$InterestCoverOverrideBenchmarkValue_',
       '$InterestCoverOverrideComment_',
       '$InterestCoverBenchmarkFirstApproval_',
       '$InterestCoverBenchmarkSecondApproval_',
       '$EBITDAToDebtBenchmarkType_',
       '$EBITDAToDebtPolicyBenchmarkValue_',
       '$EBITDAToDebtOverrideBenchmarkType_',
       '$EBITDAToDebtOverrideBenchmarkValue_',
       '$EBITDAToDebtOverrideComment_',
       '$EBITDAToDebtBenchmarkFirstApproval_',
       '$EBITDAToDebtBenchmarkSecondApproval_',
       '$BenchmarkOverrideFirstReviewerComment_',
       '$BenchmarkOverrideSecondReviewerComment_',
       '$username')"; 
	    
		//WHERE application_ref = '$application_ref')";
	
	    $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                  
             echo 'error with inserting data'. mysqli_error();
          }
        else
	      {
 		    

		header("Location:CorporateAddMenu.php");
        exit;		     
		echo 'Data successfully saved';
	      }
 ?>
</body>

</html>