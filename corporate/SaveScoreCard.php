
<html>

<head>
<Title>CORPORATE CREDIT SCORING - Saving Score Card</Title>
</head>

<body>
<?php

include 'InitialiseScoreCardVars.php';
		 
// POPULATE THE VARIABLES WITH THE FORM CONTENTS========================================================================================   
$application_ref                             =  $_POST['application_ref'] ;
$company_reg_no                              =  $_POST['company_reg_no'] ;
$loan_number                                 =  $_POST['loan_number'] ;
$username                                    =  $_POST['username'] ;
$company_size                                =  $_POST['company_size'] ;
$industrial_sector                           =  $_POST['industrial_sector'] ;
$GrandTotalMaxScore                          =  $_POST['GrandTotalMaxScore'] ;
$GrandTotalScore                             =  $_POST['GrandTotalScore'] ;
$TotalLiquidityMaxScore                      =  $_POST['TotalLiquidityMaxScore'] ;
$TotalLiquidityScore                         =  $_POST['TotalLiquidityScore'] ;
$CurrentRatioBenchmarkType                   =  $_POST['CurrentRatioBenchmarkType'] ;
$CurrentRatioPolicyBenchmarkValue            =  $_POST['CurrentRatioPolicyBenchmarkValue'] ;
$CurrentRatioBenchmarkValue                  =  $_POST['CurrentRatioBenchmarkValue'] ;
$CurrentRatioAppliedBenchmarkValue           =  $_POST['CurrentRatioAppliedBenchmarkValue'] ;
$CurrentRatioValue                           =  $_POST['CurrentRatioValue'] ;
$CurrentRatioPass                            =  $_POST['CurrentRatioPass'] ;
$CurrentRatioMaxScore                        =  $_POST['CurrentRatioMaxScore'] ;
$CurrentRatioScore                           =  $_POST['CurrentRatioScore'] ;
$CurrentRatioComment                         =  $_POST['CurrentRatioComment'] ;
$DebtorDaysBenchmarkType                     =  $_POST['DebtorDaysBenchmarkType'] ;
$DebtorDaysPolicyBenchmarkValue              =  $_POST['DebtorDaysPolicyBenchmarkValue'] ;
$DebtorDaysBenchmarkValue                    =  $_POST['DebtorDaysBenchmarkValue'] ;
$DebtorDaysAppliedBenchmarkValue             =  $_POST['DebtorDaysAppliedBenchmarkValue'] ;
$DebtorDaysValue                             =  $_POST['DebtorDaysValue'] ;
$DebtorDaysPass                              =  $_POST['DebtorDaysPass'] ;
$DebtorDaysMaxScore                          =  $_POST['DebtorDaysMaxScore'] ;
$DebtorDaysScore                             =  $_POST['DebtorDaysScore'] ;
$DebtorDaysComment                           =  $_POST['DebtorDaysComment'] ;
$TurnoverToWCBenchmarkType                   =  $_POST['TurnoverToWCBenchmarkType'] ;
$TurnoverToWCPolicyBenchmarkValue            =  $_POST['TurnoverToWCPolicyBenchmarkValue'] ;
$TurnoverToWCBenchmarkValue                  =  $_POST['TurnoverToWCBenchmarkValue'] ;
$TurnoverToWCAppliedBenchmarkValue           =  $_POST['TurnoverToWCAppliedBenchmarkValue'] ;
$TurnoverToWCValue                           =  $_POST['TurnoverToWCValue'] ;
$TurnoverToWCPass                            =  $_POST['TurnoverToWCPass'] ;
$TurnoverToWCMaxScore                        =  $_POST['TurnoverToWCMaxScore'] ;
$TurnoverToWCScore                           =  $_POST['TurnoverToWCScore'] ;
$TurnoverToWCComment                         =  $_POST['TurnoverToWCComment'] ;
$TotalProfitabilityMaxScore                  =  $_POST['TotalProfitabilityMaxScore'] ;
$TotalProfitabilityScore                     =  $_POST['TotalProfitabilityScore'] ;
$GrossProfitMarginBenchmarkType              =  $_POST['GrossProfitMarginBenchmarkType'] ;
$GrossProfitMarginPolicyBenchmarkValue       =  $_POST['GrossProfitMarginPolicyBenchmarkValue'] ;
$GrossProfitMarginBenchmarkValue             =  $_POST['GrossProfitMarginBenchmarkValue'] ;
$GrossProfitMarginAppliedBenchmarkValue      =  $_POST['GrossProfitMarginAppliedBenchmarkValue'] ;
$GrossProfitMarginValue                      =  $_POST['GrossProfitMarginValue'] ;
$GrossProfitMarginPass                       =  $_POST['GrossProfitMarginPass'] ;
$GrossProfitMarginMaxScore                   =  $_POST['GrossProfitMarginMaxScore'] ;
$GrossProfitMarginScore                      =  $_POST['GrossProfitMarginScore'] ;
$GrossProfitMarginComment                    =  $_POST['GrossProfitMarginComment'] ;
$OperatingProfitMarginBenchmarkType          =  $_POST['OperatingProfitMarginBenchmarkType'] ;
$OperatingProfitMarginPolicyBenchmarkValue   =  $_POST['OperatingProfitMarginPolicyBenchmarkValue'] ;
$OperatingProfitMarginBenchmarkValue         =  $_POST['OperatingProfitMarginBenchmarkValue'] ;
$OperatingProfitMarginAppliedBenchmarkValue  =  $_POST['OperatingProfitMarginAppliedBenchmarkValue'] ;
$OperatingProfitMarginValue                  =  $_POST['OperatingProfitMarginValue'] ;
$OperatingProfitMarginPass                   =  $_POST['OperatingProfitMarginPass'] ;
$OperatingProfitMarginMaxScore               =  $_POST['OperatingProfitMarginMaxScore'] ;
$OperatingProfitMarginScore                  =  $_POST['OperatingProfitMarginScore'] ;
$OperatingProfitComment                      =  $_POST['OperatingProfitComment'] ;
$TurnoverGrowthBenchmarkType                 =  $_POST['TurnoverGrowthBenchmarkType'] ;
$TurnoverGrowthPolicyBenchmarkValue          =  $_POST['TurnoverGrowthPolicyBenchmarkValue'] ;
$TurnoverGrowthBenchmarkValue                =  $_POST['TurnoverGrowthBenchmarkValue'] ;
$TurnoverGrowthAppliedBenchmarkValue         =  $_POST['TurnoverGrowthAppliedBenchmarkValue'] ;
$TurnoverGrowthValue                         =  $_POST['TurnoverGrowthValue'] ;
$TurnoverGrowthPass                          =  $_POST['TurnoverGrowthPass'] ;
$TurnoverGrowthMaxScore                      =  $_POST['TurnoverGrowthMaxScore'] ;
$TurnoverGrowthScore                         =  $_POST['TurnoverGrowthScore'] ;
$TurnoverGrowthComment                       =  $_POST['TurnoverGrowthComment'] ;
$ROABenchmarkType                            =  $_POST['ROABenchmarkType'] ;
$ROAPolicyBenchmarkValue                     =  $_POST['ROAPolicyBenchmarkValue'] ;
$ROABenchmarkValue                           =  $_POST['ROABenchmarkValue'] ;
$ROAAppliedBenchmarkValue                    =  $_POST['ROAAppliedBenchmarkValue'] ;
$ROAValue                                    =  $_POST['ROAValue'] ;
$ROAPass                                     =  $_POST['ROAPass'] ;
$ROAMaxScore                                 =  $_POST['ROAMaxScore'] ;
$ROAScore                                    =  $_POST['ROAScore'] ;
$ROAComment                                  =  $_POST['ROAComment'] ;
$ROIBenchmarkType                            =  $_POST['ROIBenchmarkType'] ;
$ROIPolicyBenchmarkValue                     =  $_POST['ROIPolicyBenchmarkValue'] ;
$ROIBenchmarkValue                           =  $_POST['ROIBenchmarkValue'] ;
$ROIAppliedBenchmarkValue                    =  $_POST['ROIAppliedBenchmarkValue'] ;
$ROIValue                                    =  $_POST['ROIValue'] ;
$ROIPass                                     =  $_POST['ROIPass'] ;
$ROIMaxScore                                 =  $_POST['ROIMaxScore'] ;
$ROIScore                                    =  $_POST['ROIScore'] ;
$ROIComment                                  =  $_POST['ROIComment'] ;
$TotalCapitalStructureMaxScore               =  $_POST['TotalCapitalStructureMaxScore'] ;
$TotalCapitalStructureScore                  =  $_POST['TotalCapitalStructureScore'] ;
$LongtermDebtToEquityBenchmarkType           =  $_POST['LongtermDebtToEquityBenchmarkType'] ;
$LongtermDebtToEquityPolicyBenchmarkValue    =  $_POST['LongtermDebtToEquityPolicyBenchmarkValue'] ;
$LongtermDebtToEquityBenchmarkValue          =  $_POST['LongtermDebtToEquityBenchmarkValue'] ;
$LongtermDebtToEquityAppliedBenchmarkValue   =  $_POST['LongtermDebtToEquityAppliedBenchmarkValue'] ;
$LongtermDebtToEquityValue                   =  $_POST['LongtermDebtToEquityValue'] ;
$LongtermDebtToEquityPass                    =  $_POST['LongtermDebtToEquityPass'] ;
$LongtermDebtToEquityMaxScore                =  $_POST['LongtermDebtToEquityMaxScore'] ;
$LongtermDebtToEquityScore                   =  $_POST['LongtermDebtToEquityScore'] ;
$LongtermDebtComment                         =  $_POST['LongtermDebtComment'] ;
$DebtToTangibleNetWorthBenchmarkType         =  $_POST['DebtToTangibleNetWorthBenchmarkType'] ;
$DebtToTangibleNetWorthPolicyBenchmarkValue  =  $_POST['DebtToTangibleNetWorthPolicyBenchmarkValue'] ;
$DebtToTangibleNetWorthBenchmarkValue        =  $_POST['DebtToTangibleNetWorthBenchmarkValue'] ;
$DebtToTangibleNetWorthAppliedBenchmarkValue =  $_POST['DebtToTangibleNetWorthAppliedBenchmarkValue'] ;
$DebtToTangibleNetWorthValue                 =  $_POST['DebtToTangibleNetWorthValue'] ;
$DebtToTangibleNetWorthPass                  =  $_POST['DebtToTangibleNetWorthPass'] ;
$DebtToTangibleNetWorthMaxScore              =  $_POST['DebtToTangibleNetWorthMaxScore'] ;
$DebtToTangibleNetWorthScore                 =  $_POST['DebtToTangibleNetWorthScore'] ;
$DebtToTangibleNetWorthComment               =  $_POST['DebtToTangibleNetWorthComment'] ;
$EquityToTotalAssetsBenchmarkType            =  $_POST['EquityToTotalAssetsBenchmarkType'] ;
$EquityToTotalAssetsPolicyBenchmarkValue     =  $_POST['EquityToTotalAssetsPolicyBenchmarkValue'] ;
$EquityToTotalAssetsBenchmarkValue           =  $_POST['EquityToTotalAssetsBenchmarkValue'] ;
$EquityToTotalAssetsAppliedBenchmarkValue    =  $_POST['EquityToTotalAssetsAppliedBenchmarkValue'] ;
$EquityToTotalAssetsValue                    =  $_POST['EquityToTotalAssetsValue'] ;
$EquityToTotalAssetsPass                     =  $_POST['EquityToTotalAssetsPass'] ;
$EquityToTotalAssetsMaxScore                 =  $_POST['EquityToTotalAssetsMaxScore'] ;
$EquityToTotalAssetsScore                    =  $_POST['EquityToTotalAssetsScore'] ;
$EquityToTotalAssetsComment                  =  $_POST['EquityToTotalAssetsComment'] ;
$TotalDebtServiceMaxScore                    =  $_POST['TotalDebtServiceMaxScore'] ;
$TotalDebtServiceScore                       =  $_POST['TotalDebtServiceScore'] ;
$InterestCoverBenchmarkType                  =  $_POST['InterestCoverBenchmarkType'] ;
$InterestCoverPolicyBenchmarkValue           =  $_POST['InterestCoverPolicyBenchmarkValue'] ;
$InterestCoverBenchmarkValue                 =  $_POST['InterestCoverBenchmarkValue'] ;
$InterestCoverAppliedBenchmarkValue          =  $_POST['InterestCoverAppliedBenchmarkValue'] ;
$InterestCoverValue                          =  $_POST['InterestCoverValue'] ;
$InterestCoverPass                           =  $_POST['InterestCoverPass'] ;
$InterestCoverMaxScore                       =  $_POST['InterestCoverMaxScore'] ;
$InterestCoverScore                          =  $_POST['InterestCoverScore'] ;
$InterestCoverComment                        =  $_POST['InterestCoverComment'] ;
$EBITDAToDebtBenchmarkType                   =  $_POST['EBITDAToDebtBenchmarkType'] ;
$EBITDAToDebtPolicyBenchmarkValue            =  $_POST['EBITDAToDebtPolicyBenchmarkValue'] ;
$EBITDAToDebtBenchmarkValue                  =  $_POST['EBITDAToDebtBenchmarkValue'] ;
$EBITDAToDebtAppliedBenchmarkValue           =  $_POST['EBITDAToDebtAppliedBenchmarkValue'] ;
$EBITDAToDebtValue                           =  $_POST['EBITDAToDebtValue'] ;
$EBITDAToDebtPass                            =  $_POST['EBITDAToDebtPass'] ;
$EBITDAToDebtMaxScore                        =  $_POST['EBITDAToDebtMaxScore'] ;
$EBITDAToDebtScore                           =  $_POST['EBITDAToDebtScore'] ;
$EBITDAToDebtComment                         =  $_POST['EBITDAToDebtComment'] ;
$TotalFinancialCategoryMaxScore              =  $_POST['TotalFinancialCategoryMaxScore'] ;
$TotalFinancialCategoryScore                 =  $_POST['TotalFinancialCategoryScore'] ;
$CommitmentRating                            =  $_POST['CommitmentRating'] ;
$CommitmentMaxScore                          =  $_POST['CommitmentMaxScore'] ;
$CommitmentScore                             =  $_POST['CommitmentScore'] ;
$CommitmentComment                           =  $_POST['CommitmentComment'] ;
$IntegrityRating                             =  $_POST['IntegrityRating'] ;
$IntegrityMaxScore                           =  $_POST['IntegrityMaxScore'] ;
$IntegrityScore                              =  $_POST['IntegrityScore'] ;
$IntegrityComment                            =  $_POST['IntegrityComment'] ;
$InformationQualityRating                    =  $_POST['InformationQualityRating'] ;
$InformationQualityMaxScore                  =  $_POST['InformationQualityMaxScore'] ;
$InformationQualityScore                     =  $_POST['InformationQualityScore'] ;
$InformationQualityComment                   =  $_POST['InformationQualityComment'] ;
$LeadershipRating                            =  $_POST['LeadershipRating'] ;
$LeadershipMaxScore                          =  $_POST['LeadershipMaxScore'] ;
$LeadershipScore                             =  $_POST['LeadershipScore'] ;
$LeadershipComment                           =  $_POST['LeadershipComment'] ;
$StrategyRating                              =  $_POST['StrategyRating'] ;
$StrategyMaxScore                            =  $_POST['StrategyMaxScore'] ;
$StrategyScore                               =  $_POST['StrategyScore'] ;
$StrategyComment                             =  $_POST['StrategyComment'] ;
$StructureRating                             =  $_POST['StructureRating'] ;
$StructureMaxScore                           =  $_POST['StructureMaxScore'] ;
$StructureScore                              =  $_POST['StructureScore'] ;
$StructureComment                            =  $_POST['StructureComment'] ;
$ManagementRating                            =  $_POST['ManagementRating'] ;
$ManagementMaxScore                          =  $_POST['ManagementMaxScore'] ;
$ManagementScore                             =  $_POST['ManagementScore'] ;
$ManagementComment                           =  $_POST['ManagementComment'] ;
$SuccessionPlanningRating                    =  $_POST['SuccessionPlanningRating'] ;
$SuccessionPlanningMaxScore                  =  $_POST['SuccessionPlanningMaxScore'] ;
$SuccessionPlanningScore                     =  $_POST['SuccessionPlanningScore'] ;
$SuccessionPlanningComment                   =  $_POST['SuccessionPlanningComment'] ;
$OrganisationalPlanningRating                =  $_POST['OrganisationalPlanningRating'] ;
$OrganisationalPlanningMaxScore              =  $_POST['OrganisationalPlanningMaxScore'] ;
$OrganisationalPlanningScore                 =  $_POST['OrganisationalPlanningScore'] ;
$OrganisationalPlanningComment               =  $_POST['OrganisationalPlanningComment'] ;
$TotalManagementCategoryRating               =  $_POST['TotalManagementCategoryRating'] ;
$TotalManagementCategoryMaxScore             =  $_POST['TotalManagementCategoryMaxScore'] ;
$TotalManagementCategoryScore                =  $_POST['TotalManagementCategoryScore'] ;
$TotalManagementCategoryComment              =  $_POST['TotalManagementCategoryComment'] ;
$IndustryCyclicalityRating                   =  $_POST['IndustryCyclicalityRating'] ;
$IndustryCyclicalityMaxScore                 =  $_POST['IndustryCyclicalityMaxScore'] ;
$IndustryCyclicalityScore                    =  $_POST['IndustryCyclicalityScore'] ;
$IndustryCyclicalityComment                  =  $_POST['IndustryCyclicalityComment'] ;
$IndustryPerformanceRating                   =  $_POST['IndustryPerformanceRating'] ;
$IndustryPerformanceMaxScore                 =  $_POST['IndustryPerformanceMaxScore'] ;
$IndustryPerformanceScore                    =  $_POST['IndustryPerformanceScore'] ;
$IndustryPerformanceComment                  =  $_POST['IndustryPerformanceComment'] ;
$PortersRating                               =  $_POST['PortersRating'] ;
$PortersMaxScore                             =  $_POST['PortersMaxScore'] ;
$PortersScore                                =  $_POST['PortersScore'] ;
$PortersComment                              =  $_POST['PortersComment'] ;
$TotalIndustryCategoryRating                 =  $_POST['TotalIndustryCategoryRating'] ;
$TotalIndustryCategoryMaxScore               =  $_POST['TotalIndustryCategoryMaxScore'] ;
$TotalIndustryCategoryScore                  =  $_POST['TotalIndustryCategoryScore'] ;
$TotalIndustryCategoryComment                =  $_POST['TotalIndustryCategoryComment'] ;
$ShareholderPaidDebts1                       =  $_POST['ShareholderPaidDebts1'] ;
$ShareholderPaidDebts2                       =  $_POST['ShareholderPaidDebts2'] ;
$ShareholderPaidDebts3                       =  $_POST['ShareholderPaidDebts3'] ;
$ShareholderPaidDebts4                       =  $_POST['ShareholderPaidDebts4'] ;
$ShareholderPaidDebts5                       =  $_POST['ShareholderPaidDebts5'] ;
$ShareholderPaidDebtsAverage                 =  $_POST['ShareholderPaidDebtsAverage'] ;
$ShareholderPaidDebtsMaxScore                =  $_POST['ShareholderPaidDebtsMaxScore'] ;
$ShareholderPaidDebtsScore                   =  $_POST['ShareholderPaidDebtsScore'] ;
$ShareholderPaidDebtsComment                 =  $_POST['ShareholderPaidDebtsComment'] ;
$ShareholderDefaults1                        =  $_POST['ShareholderDefaults1'] ;
$ShareholderDefaults2                        =  $_POST['ShareholderDefaults2'] ;
$ShareholderDefaults3                        =  $_POST['ShareholderDefaults3'] ;
$ShareholderDefaults4                        =  $_POST['ShareholderDefaults4'] ;
$ShareholderDefaults5                        =  $_POST['ShareholderDefaults5'] ;
$ShareholderDefaultsAverage                  =  $_POST['ShareholderDefaultsAverage'] ;
$ShareholderDefaultsMaxScore                 =  $_POST['ShareholderDefaultsMaxScore'] ;
$ShareholderDefaultsScore                    =  $_POST['ShareholderDefaultsScore'] ;
$ShareholderDefaultsComment                  =  $_POST['ShareholderDefaultsComment'] ;
$ShareholderJudgements1                      =  $_POST['ShareholderJudgements1'] ;
$ShareholderJudgements2                      =  $_POST['ShareholderJudgements2'] ;
$ShareholderJudgements3                      =  $_POST['ShareholderJudgements3'] ;
$ShareholderJudgements4                      =  $_POST['ShareholderJudgements4'] ;
$ShareholderJudgements5                      =  $_POST['ShareholderJudgements5'] ;
$ShareholderJudgementsAverage                =  $_POST['ShareholderJudgementsAverage'] ;
$ShareholderJudgementsMaxScore               =  $_POST['ShareholderJudgementsMaxScore'] ;
$ShareholderJudgementsScore                  =  $_POST['ShareholderJudgementsScore'] ;
$ShareholderJudgementsComment                =  $_POST['ShareholderJudgementsComment'] ;
$ShareholderTraceAlerts1                     =  $_POST['ShareholderTraceAlerts1'] ;
$ShareholderTraceAlerts2                     =  $_POST['ShareholderTraceAlerts2'] ;
$ShareholderTraceAlerts3                     =  $_POST['ShareholderTraceAlerts3'] ;
$ShareholderTraceAlerts4                     =  $_POST['ShareholderTraceAlerts4'] ;
$ShareholderTraceAlerts5                     =  $_POST['ShareholderTraceAlerts5'] ;
$ShareholderTraceAlertsAverage               =  $_POST['ShareholderTraceAlertsAverage'] ;
$ShareholderTraceAlertsMaxScore              =  $_POST['ShareholderTraceAlertsMaxScore'] ;
$ShareholderTraceAlertsScore                 =  $_POST['ShareholderTraceAlertsScore'] ;
$ShareholderTraceAlertsComment               =  $_POST['ShareholderTraceAlertsComment'] ;
$ShareholderBlacklisted1                     =  $_POST['ShareholderBlacklisted1'] ;
$ShareholderBlacklisted2                     =  $_POST['ShareholderBlacklisted2'] ;
$ShareholderBlacklisted3                     =  $_POST['ShareholderBlacklisted3'] ;
$ShareholderBlacklisted4                     =  $_POST['ShareholderBlacklisted4'] ;
$ShareholderBlacklisted5                     =  $_POST['ShareholderBlacklisted5'] ;
$ShareholderBlacklistedComment               =  $_POST['ShareholderBlacklistedComment'] ;
$ShareholderFraudAlert1                      =  $_POST['ShareholderFraudAlert1'] ;
$ShareholderFraudAlert2                      =  $_POST['ShareholderFraudAlert2'] ;
$ShareholderFraudAlert3                      =  $_POST['ShareholderFraudAlert3'] ;
$ShareholderFraudAlert4                      =  $_POST['ShareholderFraudAlert4'] ;
$ShareholderFraudAlert5                      =  $_POST['ShareholderFraudAlert5'] ;
$ShareholderFraudAlertComment                =  $_POST['ShareholderFraudAlertComment'] ;
$TotalScoreShareholder1                      =  $_POST['TotalScoreShareholder1'] ;
$TotalScoreShareholder2                      =  $_POST['TotalScoreShareholder2'] ;
$TotalScoreShareholder3                      =  $_POST['TotalScoreShareholder3'] ;
$TotalScoreShareholder4                      =  $_POST['TotalScoreShareholder4'] ;
$TotalScoreShareholder5                      =  $_POST['TotalScoreShareholder5'] ;
$TotalScoreShareholderAverage                =  $_POST['TotalScoreShareholderAverage'] ;
$TotalScoreShareholderComment                =  $_POST['TotalScoreShareholderComment'] ;
$TotalShareholderCategoryMaxScore            =  $_POST['TotalShareholderCategoryMaxScore'] ;
$TotalShareholderCategoryScore               =  $_POST['TotalShareholderCategoryScore'] ;
$TotalShareholderCategoryComment             =  $_POST['TotalShareholderCategoryComment'] ;
$rate_typeRating                             =  $_POST['rate_typeRating'] ;
$rate_typeMaxScore                           =  $_POST['rate_typeMaxScore'] ;
$rate_typeScore                              =  $_POST['rate_typeScore'] ;
$rate_typeComment                            =  $_POST['rate_typeComment'] ;
$loan_maturityRating                         =  $_POST['loan_maturityRating'] ;
$loan_maturityMaxScore                       =  $_POST['loan_maturityMaxScore'] ;
$loan_maturityScore                          =  $_POST['loan_maturityScore'] ;
$loan_maturityComment                        =  $_POST['loan_maturityComment'] ;
$relationshipRating                          =  $_POST['relationshipRating'] ;
$relationshipMaxScore                        =  $_POST['relationshipMaxScore'] ;
$relationshipScore                           =  $_POST['relationshipScore'] ;
$total_bbs_productsRating                    =  $_POST['total_bbs_productsRating'] ;
$total_bbs_productsMaxScore                  =  $_POST['total_bbs_productsMaxScore'] ;
$total_bbs_productsScore                     =  $_POST['total_bbs_productsScore'] ;
$total_bbs_productsComment                   =  $_POST['total_bbs_productsComment'] ;
$loan_arrearsRating                          =  $_POST['loan_arrearsRating'] ;
$loan_arrearsMaxScore                        =  $_POST['loan_arrearsMaxScore'] ;
$loan_arrearsScore                           =  $_POST['loan_arrearsScore'] ;
$loan_arrearsComment                         =  $_POST['loan_arrearsComment'] ;
$renegotiateRating                           =  $_POST['renegotiateRating'] ;
$renegotiateMaxScore                         =  $_POST['renegotiateMaxScore'] ;
$renegotiateScore                            =  $_POST['renegotiateScore'] ;
$renegotiateComment                          =  $_POST['renegotiateComment'] ;
$paid_debtsRating                            =  $_POST['paid_debtsRating'] ;
$paid_debtsMaxScore                          =  $_POST['paid_debtsMaxScore'] ;
$paid_debtsScore                             =  $_POST['paid_debtsScore'] ;
$paid_debtsComment                           =  $_POST['paid_debtsComment'] ;
$judgementRating                             =  $_POST['judgementRating'] ;
$judgementMaxScore                           =  $_POST['judgementMaxScore'] ;
$judgementScore                              =  $_POST['judgementScore'] ;
$judgementComment                            =  $_POST['judgementComment'] ;
$default_dataRating                          =  $_POST['default_dataRating'] ;
$default_dataMaxScore                        =  $_POST['default_dataMaxScore'] ;
$default_dataScore                           =  $_POST['default_dataScore'] ;
$default_dataComment                         =  $_POST['default_dataComment'] ;
$trace_alertsRating                          =  $_POST['trace_alertsRating'] ;
$trace_alertsMaxScore                        =  $_POST['trace_alertsMaxScore'] ;
$trace_alertsScore                           =  $_POST['trace_alertsScore'] ;
$trace_alertsComment                         =  $_POST['trace_alertsComment'] ;
$TotalBehavioralCategoryRating               =  $_POST['TotalBehavioralCategoryRating'] ;
$TotalBehavioralCategoryMaxScore             =  $_POST['TotalBehavioralCategoryMaxScore'] ;
$TotalBehavioralCategoryScore                =  $_POST['TotalBehavioralCategoryScore'] ;
$TotalBehavioralCategoryComment              =  $_POST['TotalBehavioralCategoryComment'] ;
$ZScore1                                     =  $_POST['ZScore1'] ;
$ZScore2                                     =  $_POST['ZScore2'] ;
$ZScore3                                     =  $_POST['ZScore3'] ;
$ZScoreWeighted                              =  $_POST['ZScoreWeighted'] ;
$ZScoreComment                               =  $_POST['ZScoreComment'] ;
$ZScorePrime1                                =  $_POST['ZScorePrime1'] ;
$ZScorePrime2                                =  $_POST['ZScorePrime2'] ;
$ZScorePrime3                                =  $_POST['ZScorePrime3'] ;
$ZScorePrimeWeighted                         =  $_POST['ZScorePrimeWeighted'] ;
$ZScorePrimeComment                          =  $_POST['ZScorePrimeComment'] ;

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

         
		//$CurrentRatioBenchmark_DateUpdated = NULL; 
		 //========================UPDATE THE PROGRESS TRACKER - Loan Data Section================================================================================
		  $progress_tracker_insert_query =  "REPLACE INTO progress_tracker 
              (application_ref   ,  company_reg_no  , section       , username  )
			  VALUES
			  ('$application_ref','$company_reg_no' ,'Score Card' ,'$username')";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker'. mysqli_error();
          }
      
		  //================================================================================================================================
		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Loan Data		  //========================UPDATE THE PROGRESS TRACKER SINGLE RECORD- Income Statement Section================================================================================
		  $progress_tracker_single_record_insert_query =  
		  "UPDATE progress_tracker_single_record SET
		   score_card_pdate    = now(), 
		   score_card_username = '$username'
		   WHERE application_ref = $application_ref";
		  
		  $resultm= mysqli_query($connect,$progress_tracker_single_record_insert_query); 
		  
		  if (!$resultm)
          {                                                    
             echo 'error with inserting company data record on progress tracker single record'. mysqli_error();
          }
      
		 //================================================================================================================================
 	        
       $insert_query = "UPDATE scores SET
       application_ref                             = '$application_ref',
       company_reg_no                              = '$company_reg_no',
       loan_number                                 = '$loan_number',
       username                                    = '$username',
       company_size                                = '$company_size',
       industrial_sector                           = '$industrial_sector',
       GrandTotalMaxScore                          = '$GrandTotalMaxScore',
       GrandTotalScore                             = '$GrandTotalScore',
       TotalLiquidityMaxScore                      = '$TotalLiquidityMaxScore',
       TotalLiquidityScore                         = '$TotalLiquidityScore',
       CurrentRatioBenchmarkType                   = '$CurrentRatioBenchmarkType',
       CurrentRatioPolicyBenchmarkValue            = '$CurrentRatioPolicyBenchmarkValue',
       CurrentRatioBenchmarkValue                  = '$CurrentRatioBenchmarkValue',
       CurrentRatioAppliedBenchmarkValue           = '$CurrentRatioAppliedBenchmarkValue',
       CurrentRatioValue                           = '$CurrentRatioValue',
       CurrentRatioPass                            = '$CurrentRatioPass',
       CurrentRatioMaxScore                        = '$CurrentRatioMaxScore',
       CurrentRatioScore                           = '$CurrentRatioScore',
       CurrentRatioComment                         = '$CurrentRatioComment',
       DebtorDaysBenchmarkType                     = '$DebtorDaysBenchmarkType',
       DebtorDaysPolicyBenchmarkValue              = '$DebtorDaysPolicyBenchmarkValue',
       DebtorDaysBenchmarkValue                    = '$DebtorDaysBenchmarkValue',
       DebtorDaysAppliedBenchmarkValue             = '$DebtorDaysAppliedBenchmarkValue',
       DebtorDaysValue                             = '$DebtorDaysValue',
       DebtorDaysPass                              = '$DebtorDaysPass',
       DebtorDaysMaxScore                          = '$DebtorDaysMaxScore',
       DebtorDaysScore                             = '$DebtorDaysScore',
       DebtorDaysComment                           = '$DebtorDaysComment',
       
	   TurnoverToWCBenchmarkType                   = '$TurnoverToWCBenchmarkType',
       TurnoverToWCPolicyBenchmarkValue            = '$TurnoverToWCPolicyBenchmarkValue',
       TurnoverToWCBenchmarkValue                  = '$TurnoverToWCBenchmarkValue',
       TurnoverToWCAppliedBenchmarkValue           = '$TurnoverToWCAppliedBenchmarkValue',
       TurnoverToWCValue                           = '$TurnoverToWCValue',
       TurnoverToWCPass                            = '$TurnoverToWCPass',
       TurnoverToWCMaxScore                        = '$TurnoverToWCMaxScore',
       TurnoverToWCScore                           = '$TurnoverToWCScore',
       TurnoverToWCComment                         = '$TurnoverToWCComment',
       TotalProfitabilityMaxScore                  = '$TotalProfitabilityMaxScore',
       TotalProfitabilityScore                     = '$TotalProfitabilityScore',
       GrossProfitMarginBenchmarkType              = '$GrossProfitMarginBenchmarkType',
       GrossProfitMarginPolicyBenchmarkValue       = '$GrossProfitMarginPolicyBenchmarkValue',
       GrossProfitMarginBenchmarkValue             = '$GrossProfitMarginBenchmarkValue',
       GrossProfitMarginAppliedBenchmarkValue      = '$GrossProfitMarginAppliedBenchmarkValue',
       GrossProfitMarginValue                      = '$GrossProfitMarginValue',
       GrossProfitMarginPass                       = '$GrossProfitMarginPass',
       GrossProfitMarginMaxScore                   = '$GrossProfitMarginMaxScore',
       GrossProfitMarginScore                      = '$GrossProfitMarginScore',
       GrossProfitMarginComment                    = '$GrossProfitMarginComment',
       OperatingProfitMarginBenchmarkType          = '$OperatingProfitMarginBenchmarkType',
       OperatingProfitMarginPolicyBenchmarkValue   = '$OperatingProfitMarginPolicyBenchmarkValue',
       OperatingProfitMarginBenchmarkValue         = '$OperatingProfitMarginBenchmarkValue',
       OperatingProfitMarginAppliedBenchmarkValue  = '$OperatingProfitMarginAppliedBenchmarkValue',
       OperatingProfitMarginValue                  = '$OperatingProfitMarginValue',
       OperatingProfitMarginPass                   = '$OperatingProfitMarginPass',
       OperatingProfitMarginMaxScore               = '$OperatingProfitMarginMaxScore',
       OperatingProfitMarginScore                  = '$OperatingProfitMarginScore',
       OperatingProfitComment                      = '$OperatingProfitComment',
       TurnoverGrowthBenchmarkType                 = '$TurnoverGrowthBenchmarkType',
       TurnoverGrowthPolicyBenchmarkValue          = '$TurnoverGrowthPolicyBenchmarkValue',
       TurnoverGrowthBenchmarkValue                = '$TurnoverGrowthBenchmarkValue',
       TurnoverGrowthAppliedBenchmarkValue         = '$TurnoverGrowthAppliedBenchmarkValue',
       TurnoverGrowthValue                         = '$TurnoverGrowthValue',
       TurnoverGrowthPass                          = '$TurnoverGrowthPass',
       TurnoverGrowthMaxScore                      = '$TurnoverGrowthMaxScore',
       TurnoverGrowthScore                         = '$TurnoverGrowthScore',
       TurnoverGrowthComment                       = '$TurnoverGrowthComment',
       ROABenchmarkType                            = '$ROABenchmarkType',
       ROAPolicyBenchmarkValue                     = '$ROAPolicyBenchmarkValue',
       ROABenchmarkValue                           = '$ROABenchmarkValue',
       ROAAppliedBenchmarkValue                    = '$ROAAppliedBenchmarkValue',
       ROAValue                                    = '$ROAValue',
       ROAPass                                     = '$ROAPass',
       ROAMaxScore                                 = '$ROAMaxScore',
       ROAScore                                    = '$ROAScore',
       ROAComment                                  = '$ROAComment',
       ROIBenchmarkType                            = '$ROIBenchmarkType',
       ROIPolicyBenchmarkValue                     = '$ROIPolicyBenchmarkValue',
       ROIBenchmarkValue                           = '$ROIBenchmarkValue',
       ROIAppliedBenchmarkValue                    = '$ROIAppliedBenchmarkValue',
       ROIValue                                    = '$ROIValue',
       ROIPass                                     = '$ROIPass',
       ROIMaxScore                                 = '$ROIMaxScore',
       ROIScore                                    = '$ROIScore',
       ROIComment                                  = '$ROIComment',
       TotalCapitalStructureMaxScore               = '$TotalCapitalStructureMaxScore',
       TotalCapitalStructureScore                  = '$TotalCapitalStructureScore',
       LongtermDebtToEquityBenchmarkType           = '$LongtermDebtToEquityBenchmarkType',
       LongtermDebtToEquityPolicyBenchmarkValue    = '$LongtermDebtToEquityPolicyBenchmarkValue',
       LongtermDebtToEquityBenchmarkValue          = '$LongtermDebtToEquityBenchmarkValue',
       LongtermDebtToEquityAppliedBenchmarkValue   = '$LongtermDebtToEquityAppliedBenchmarkValue',
       LongtermDebtToEquityValue                   = '$LongtermDebtToEquityValue',
       LongtermDebtToEquityPass                    = '$LongtermDebtToEquityPass',
       LongtermDebtToEquityMaxScore                = '$LongtermDebtToEquityMaxScore',
       LongtermDebtToEquityScore                   = '$LongtermDebtToEquityScore',
       LongtermDebtComment                         = '$LongtermDebtComment',
       DebtToTangibleNetWorthBenchmarkType         = '$DebtToTangibleNetWorthBenchmarkType',
       DebtToTangibleNetWorthPolicyBenchmarkValue  = '$DebtToTangibleNetWorthPolicyBenchmarkValue',
       DebtToTangibleNetWorthBenchmarkValue        = '$DebtToTangibleNetWorthBenchmarkValue',
       DebtToTangibleNetWorthAppliedBenchmarkValue = '$DebtToTangibleNetWorthAppliedBenchmarkValue',
       DebtToTangibleNetWorthValue                 = '$DebtToTangibleNetWorthValue',
       DebtToTangibleNetWorthPass                  = '$DebtToTangibleNetWorthPass',
       DebtToTangibleNetWorthMaxScore              = '$DebtToTangibleNetWorthMaxScore',
       DebtToTangibleNetWorthScore                 = '$DebtToTangibleNetWorthScore',
       DebtToTangibleNetWorthComment               = '$DebtToTangibleNetWorthComment',
       EquityToTotalAssetsBenchmarkType            = '$EquityToTotalAssetsBenchmarkType',
       EquityToTotalAssetsPolicyBenchmarkValue     = '$EquityToTotalAssetsPolicyBenchmarkValue',
       EquityToTotalAssetsBenchmarkValue           = '$EquityToTotalAssetsBenchmarkValue',
       EquityToTotalAssetsAppliedBenchmarkValue    = '$EquityToTotalAssetsAppliedBenchmarkValue',
       EquityToTotalAssetsValue                    = '$EquityToTotalAssetsValue',
       EquityToTotalAssetsPass                     = '$EquityToTotalAssetsPass',
       EquityToTotalAssetsMaxScore                 = '$EquityToTotalAssetsMaxScore',
       EquityToTotalAssetsScore                    = '$EquityToTotalAssetsScore',
       EquityToTotalAssetsComment                  = '$EquityToTotalAssetsComment',
       TotalDebtServiceMaxScore                    = '$TotalDebtServiceMaxScore',
       TotalDebtServiceScore                       = '$TotalDebtServiceScore',
       InterestCoverBenchmarkType                  = '$InterestCoverBenchmarkType',
       InterestCoverPolicyBenchmarkValue           = '$InterestCoverPolicyBenchmarkValue',
       InterestCoverBenchmarkValue                 = '$InterestCoverBenchmarkValue',
       InterestCoverAppliedBenchmarkValue          = '$InterestCoverAppliedBenchmarkValue',
       InterestCoverValue                          = '$InterestCoverValue',
       InterestCoverPass                           = '$InterestCoverPass',
       InterestCoverMaxScore                       = '$InterestCoverMaxScore',
       InterestCoverScore                          = '$InterestCoverScore',
       InterestCoverComment                        = '$InterestCoverComment',
       EBITDAToDebtBenchmarkType                   = '$EBITDAToDebtBenchmarkType',
       EBITDAToDebtPolicyBenchmarkValue            = '$EBITDAToDebtPolicyBenchmarkValue',
       EBITDAToDebtBenchmarkValue                  = '$EBITDAToDebtBenchmarkValue',
       EBITDAToDebtAppliedBenchmarkValue           = '$EBITDAToDebtAppliedBenchmarkValue',
       EBITDAToDebtValue                           = '$EBITDAToDebtValue',
       EBITDAToDebtPass                            = '$EBITDAToDebtPass',
       EBITDAToDebtMaxScore                        = '$EBITDAToDebtMaxScore',
       EBITDAToDebtScore                           = '$EBITDAToDebtScore',
       EBITDAToDebtComment                         = '$EBITDAToDebtComment',
       TotalFinancialCategoryMaxScore              = '$TotalFinancialCategoryMaxScore',
       TotalFinancialCategoryScore                 = '$TotalFinancialCategoryScore',
       CommitmentRating                            = '$CommitmentRating',
       CommitmentMaxScore                          = '$CommitmentMaxScore',
       CommitmentScore                             = '$CommitmentScore',
       CommitmentComment                           = '$CommitmentComment',
       IntegrityRating                             = '$IntegrityRating',
       IntegrityMaxScore                           = '$IntegrityMaxScore',
       IntegrityScore                              = '$IntegrityScore',
       IntegrityComment                            = '$IntegrityComment',
       InformationQualityRating                    = '$InformationQualityRating',
       InformationQualityMaxScore                  = '$InformationQualityMaxScore',
       InformationQualityScore                     = '$InformationQualityScore',
       InformationQualityComment                   = '$InformationQualityComment',
       LeadershipRating                            = '$LeadershipRating',
       LeadershipMaxScore                          = '$LeadershipMaxScore',
       LeadershipScore                             = '$LeadershipScore',
       LeadershipComment                           = '$LeadershipComment',
       StrategyRating                              = '$StrategyRating',
       StrategyMaxScore                            = '$StrategyMaxScore',
       StrategyScore                               = '$StrategyScore',
       StrategyComment                             = '$StrategyComment',
       StructureRating                             = '$StructureRating',
       StructureMaxScore                           = '$StructureMaxScore',
       StructureScore                              = '$StructureScore',
       StructureComment                            = '$StructureComment',
       ManagementRating                            = '$ManagementRating',
       ManagementMaxScore                          = '$ManagementMaxScore',
       ManagementScore                             = '$ManagementScore',
       ManagementComment                           = '$ManagementComment',
       SuccessionPlanningRating                    = '$SuccessionPlanningRating',
       SuccessionPlanningMaxScore                  = '$SuccessionPlanningMaxScore',
       SuccessionPlanningScore                     = '$SuccessionPlanningScore',
       SuccessionPlanningComment                   = '$SuccessionPlanningComment',
       OrganisationalPlanningRating                = '$OrganisationalPlanningRating',
       OrganisationalPlanningMaxScore              = '$OrganisationalPlanningMaxScore',
       OrganisationalPlanningScore                 = '$OrganisationalPlanningScore',
       OrganisationalPlanningComment               = '$OrganisationalPlanningComment',
       TotalManagementCategoryRating               = '$TotalManagementCategoryRating',
       TotalManagementCategoryMaxScore             = '$TotalManagementCategoryMaxScore',
       TotalManagementCategoryScore                = '$TotalManagementCategoryScore',
       TotalManagementCategoryComment              = '$TotalManagementCategoryComment',
       IndustryCyclicalityRating                   = '$IndustryCyclicalityRating',
       IndustryCyclicalityMaxScore                 = '$IndustryCyclicalityMaxScore',
       IndustryCyclicalityScore                    = '$IndustryCyclicalityScore',
       IndustryCyclicalityComment                  = '$IndustryCyclicalityComment',
       IndustryPerformanceRating                   = '$IndustryPerformanceRating',
       IndustryPerformanceMaxScore                 = '$IndustryPerformanceMaxScore',
       IndustryPerformanceScore                    = '$IndustryPerformanceScore',
       IndustryPerformanceComment                  = '$IndustryPerformanceComment',
       PortersRating                               = '$PortersRating',
       PortersMaxScore                             = '$PortersMaxScore',
       PortersScore                                = '$PortersScore',
       PortersComment                              = '$PortersComment',
       TotalIndustryCategoryRating                 = '$TotalIndustryCategoryRating',
       TotalIndustryCategoryMaxScore               = '$TotalIndustryCategoryMaxScore',
       TotalIndustryCategoryScore                  = '$TotalIndustryCategoryScore',
       TotalIndustryCategoryComment                = '$TotalIndustryCategoryComment',
       ShareholderPaidDebts1                       = '$ShareholderPaidDebts1',
       ShareholderPaidDebts2                       = '$ShareholderPaidDebts2',
       ShareholderPaidDebts3                       = '$ShareholderPaidDebts3',
       ShareholderPaidDebts4                       = '$ShareholderPaidDebts4',
       ShareholderPaidDebts5                       = '$ShareholderPaidDebts5',
       ShareholderPaidDebtsAverage                 = '$ShareholderPaidDebtsAverage',
       ShareholderPaidDebtsMaxScore                = '$ShareholderPaidDebtsMaxScore',
       ShareholderPaidDebtsScore                   = '$ShareholderPaidDebtsScore',
       ShareholderPaidDebtsComment                 = '$ShareholderPaidDebtsComment',
       ShareholderDefaults1                        = '$ShareholderDefaults1',
       ShareholderDefaults2                        = '$ShareholderDefaults2',
       ShareholderDefaults3                        = '$ShareholderDefaults3',
       ShareholderDefaults4                        = '$ShareholderDefaults4',
       ShareholderDefaults5                        = '$ShareholderDefaults5',
       ShareholderDefaultsAverage                  = '$ShareholderDefaultsAverage',
       ShareholderDefaultsMaxScore                 = '$ShareholderDefaultsMaxScore',
       ShareholderDefaultsScore                    = '$ShareholderDefaultsScore',
       ShareholderDefaultsComment                  = '$ShareholderDefaultsComment',
       ShareholderJudgements1                      = '$ShareholderJudgements1',
       ShareholderJudgements2                      = '$ShareholderJudgements2',
       ShareholderJudgements3                      = '$ShareholderJudgements3',
       ShareholderJudgements4                      = '$ShareholderJudgements4',
       ShareholderJudgements5                      = '$ShareholderJudgements5',
       ShareholderJudgementsAverage                = '$ShareholderJudgementsAverage',
       ShareholderJudgementsMaxScore               = '$ShareholderJudgementsMaxScore',
       ShareholderJudgementsScore                  = '$ShareholderJudgementsScore',
       ShareholderJudgementsComment                = '$ShareholderJudgementsComment',
       ShareholderTraceAlerts1                     = '$ShareholderTraceAlerts1',
       ShareholderTraceAlerts2                     = '$ShareholderTraceAlerts2',
       ShareholderTraceAlerts3                     = '$ShareholderTraceAlerts3',
       ShareholderTraceAlerts4                     = '$ShareholderTraceAlerts4',
       ShareholderTraceAlerts5                     = '$ShareholderTraceAlerts5',
       ShareholderTraceAlertsAverage               = '$ShareholderTraceAlertsAverage',
       ShareholderTraceAlertsMaxScore              = '$ShareholderTraceAlertsMaxScore',
       ShareholderTraceAlertsScore                 = '$ShareholderTraceAlertsScore',
       ShareholderTraceAlertsComment               = '$ShareholderTraceAlertsComment',
       ShareholderBlacklisted1                     = '$ShareholderBlacklisted1',
       ShareholderBlacklisted2                     = '$ShareholderBlacklisted2',
       ShareholderBlacklisted3                     = '$ShareholderBlacklisted3',
       ShareholderBlacklisted4                     = '$ShareholderBlacklisted4',
       ShareholderBlacklisted5                     = '$ShareholderBlacklisted5',
       ShareholderBlacklistedComment               = '$ShareholderBlacklistedComment',
       ShareholderFraudAlert1                      = '$ShareholderFraudAlert1',
       ShareholderFraudAlert2                      = '$ShareholderFraudAlert2',
       ShareholderFraudAlert3                      = '$ShareholderFraudAlert3',
       ShareholderFraudAlert4                      = '$ShareholderFraudAlert4',
       ShareholderFraudAlert5                      = '$ShareholderFraudAlert5',
       ShareholderFraudAlertComment                = '$ShareholderFraudAlertComment',
       TotalScoreShareholder1                      = '$TotalScoreShareholder1',
       TotalScoreShareholder2                      = '$TotalScoreShareholder2',
       TotalScoreShareholder3                      = '$TotalScoreShareholder3',
       TotalScoreShareholder4                      = '$TotalScoreShareholder4',
       TotalScoreShareholder5                      = '$TotalScoreShareholder5',
       TotalScoreShareholderAverage                = '$TotalScoreShareholderAverage',
       TotalScoreShareholderComment                = '$TotalScoreShareholderComment',
       TotalShareholderCategoryMaxScore            = '$TotalShareholderCategoryMaxScore',
       TotalShareholderCategoryScore               = '$TotalShareholderCategoryScore',
       TotalShareholderCategoryComment             = '$TotalShareholderCategoryComment',
       rate_typeRating                             = '$rate_typeRating',
       rate_typeMaxScore                           = '$rate_typeMaxScore',
       rate_typeScore                              = '$rate_typeScore',
       rate_typeComment                            = '$rate_typeComment',
       loan_maturityRating                         = '$loan_maturityRating',
       loan_maturityMaxScore                       = '$loan_maturityMaxScore',
       loan_maturityScore                          = '$loan_maturityScore',
       loan_maturityComment                        = '$loan_maturityComment',
       relationshipRating                          = '$relationshipRating',
       relationshipMaxScore                        = '$relationshipMaxScore',
       relationshipScore                           = '$relationshipScore',
       total_bbs_productsRating                    = '$total_bbs_productsRating',
       total_bbs_productsMaxScore                  = '$total_bbs_productsMaxScore',
       total_bbs_productsScore                     = '$total_bbs_productsScore',
       total_bbs_productsComment                   = '$total_bbs_productsComment',
       loan_arrearsRating                          = '$loan_arrearsRating',
       loan_arrearsMaxScore                        = '$loan_arrearsMaxScore',
       loan_arrearsScore                           = '$loan_arrearsScore',
       loan_arrearsComment                         = '$loan_arrearsComment',
       renegotiateRating                           = '$renegotiateRating',
       renegotiateMaxScore                         = '$renegotiateMaxScore',
       renegotiateScore                            = '$renegotiateScore',
       renegotiateComment                          = '$renegotiateComment',
       paid_debtsRating                            = '$paid_debtsRating',
       paid_debtsMaxScore                          = '$paid_debtsMaxScore',
       paid_debtsScore                             = '$paid_debtsScore',
       paid_debtsComment                           = '$paid_debtsComment',
       judgementRating                             = '$judgementRating',
       judgementMaxScore                           = '$judgementMaxScore',
       judgementScore                              = '$judgementScore',
       judgementComment                            = '$judgementComment',
       default_dataRating                          = '$default_dataRating',
       default_dataMaxScore                        = '$default_dataMaxScore',
       default_dataScore                           = '$default_dataScore',
       default_dataComment                         = '$default_dataComment',
       trace_alertsRating                          = '$trace_alertsRating',
       trace_alertsMaxScore                        = '$trace_alertsMaxScore',
       trace_alertsScore                           = '$trace_alertsScore',
       trace_alertsComment                         = '$trace_alertsComment',
       TotalBehavioralCategoryRating               = '$TotalBehavioralCategoryRating',
       TotalBehavioralCategoryMaxScore             = '$TotalBehavioralCategoryMaxScore',
       TotalBehavioralCategoryScore                = '$TotalBehavioralCategoryScore',
       TotalBehavioralCategoryComment              = '$TotalBehavioralCategoryComment',
       ZScore1                                     = '$ZScore1',
       ZScore2                                     = '$ZScore2',
       ZScore3                                     = '$ZScore3',
       ZScoreWeighted                              = '$ZScoreWeighted',
       ZScoreComment                               = '$ZScoreComment',
       ZScorePrime1                                = '$ZScorePrime1',
       ZScorePrime2                                = '$ZScorePrime2',
       ZScorePrime3                                = '$ZScorePrime3',
       ZScorePrimeWeighted                         = '$ZScorePrimeWeighted',
       ZScorePrimeComment                          = '$ZScorePrimeComment'
       WHERE application_ref ='$application_ref'";

$resultm= mysqli_query($connect,$insert_query); 
if (!$resultm)
{                                  
  echo 'error with inserting data'. mysqli_error();
}
else
{
  echo 'Data successfully saved';	    

   $PDRiskGroup                  = $_POST['PDRiskGroup'];
   $pd                           = str_replace("%","",$_POST['PD']);  //remove the % sign before saving
   $open_market_value            = str_replace(",","",$_POST['open_market_value']);
   $haircut_percentage           = $_POST['Haircut'];
   $haircut                      = $haircut_percentage * $open_market_value/100;
   $LGD                          = str_replace(",","",$_POST['LGD']);
   $lgd_to_loan_amount           = str_replace(",","",$_POST['lgd_to_loan_amount']);
   $loan_amount                  = str_replace(",","",$_POST['loan_amount']);
   $general_costs                = str_replace(",","",$_POST['general_costs']);
   $general_costs_to_loan_amount = $general_costs/$loan_amount * 100;
   $installments_3months         = str_replace(",","",$_POST['installments_3months']);
   $installments_percentage      = $installments_3months/$loan_amount * 100;
   $EAD                          = str_replace(",","",$_POST['EAD']);
   $ead_to_loan_amount           = str_replace(",","",$_POST['ead_to_loan_amount']);
   $omv_to_loan_amount           = str_replace(",","",$_POST['omv_to_loan_amount']);
   $ECL                          = str_replace(",","",$_POST['ECL']);
   $loan_to_value                = $_POST['loan_to_value'];
   $ecl_to_loan_amount           = $_POST['ecl_to_loan_amount'];
    
   $insert_rating_query = "REPLACE INTO cust_credit_rating 
      (application_ref,              
       company_reg_no,
       loan_number,
       username,
       pd,
       loan_amount,
       loan_percentage,	   
	   installment,
       installment_percentage,
       cost_estimation,
	   cost_estimation_percentage,
	   estimated_EAD,
	   estimated_EAD_percentage,
	   estimated_OMV,
	   estimated_OMV_percentage,
	   haircut,
       haircut_percentage, 
	   estimated_LGD,
	   estimated_LGD_percentage,
	   Expected_loss,
	   Expected_loss_percentage,
	   ltv,
	   rate,
	   date)
    VALUES 
      ('$application_ref',
       '$company_reg_no',
       '$loan_number',
       '$username',
       '$pd',
       '$loan_amount',
       100,	   
	   'installments_3months',
       '$installments_percentage',
       '$general_costs',
	   '$general_costs_to_loan_amount',
	   '$EAD',
	   '$ead_to_loan_amount',
	   '$open_market_value',
	   '$omv_to_loan_amount',
	   '$haircut',
       '$haircut_percentage', 
	   '$LGD',
	   '$lgd_to_loan_amount',
	   '$ECL',
	   '$ecl_to_loan_amount',
	   '$loan_to_value',
	   '$PDRiskGroup',
	    now())";

 $resultm= mysqli_query($connect,$insert_rating_query); 
   if (!$resultm)
   {                                  
      echo 'error with inserting data'. mysqli_error();
   }	   
}
//header("Location:CorporateAddMenu.php");
//exit;		     

 ?>
</body>

</html>