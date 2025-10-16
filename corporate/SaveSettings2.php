<html>

<head>
<Title>CORPORATE SCORING MODEL - Saving Model Calibration Settings2</Title>
</head>

<body>
    <?php
        /***************************************************************************************************************************************
	     STEP 1 : INITIALISE VARIABLES
         ****************************************************************************************************************************************
         MAIN SETTINGS WEIGHTS
		 1. Main Settings Table Weights - Big Firms
		**/ 
		
		 $bfFinancialAnalysisPercentage                   = null;
	     $bfManagementAnalysisPercentage                  = null;
	     $bfIndustryAnalysisPercentage                    = null;
		 $bfShareholderAnalysisPercentage                 = null;
		 $bfBehavioralAnalysisPercentage                  = null;
		              //2. Main Settings Table Weights - Small Firms
	     $sfFinancialAnalysisPercentage                   = null;
	     $sfManagementAnalysisPercentage                  = null;
	     $sfIndustryAnalysisPercentage                    = null;
		 $sfShareholderAnalysisPercentage                 = null;
		 $sfBehavioralAnalysisPercentage                  = null;
	                  //3. Main Settings Table Scores - Big Firms
		 $bfFinancialAnalysisScore                        = null;
	     $bfManagementAnalysisScore                       = null;
	     $bfIndustryAnalysisScore                         = null;
		 $bfShareholderAnalysisScore                      = null;
		 $bfBehavioralAnalysisScore                       = null;
		              //4. Main Settings Table Scores - Small Firms
	     $sfFinancialAnalysisScore                        = null;
	     $sfManagementAnalysisScore                       = null;
	     $sfIndustryAnalysisScore                         = null;
		 $sfShareholderAnalysisScore                      = null;
		 $sfBehavioralAnalysisScore                       = null;
		              //5. Main Settings Table Total Scores - Big and Small Firms
		 $bfTotalScore=null;
	     $sfTotalScore=null;
		
                      //6. TURNOVER THRESHOLD AND RATIO ANALYSIS SETTINGS
		 $TurnoverThreshold                               = null;  
         $RatioWeightYear1                                = null;  
       	 $RatioWeightYear2                                = null;  
       	 $RatioWeightYear3                                = null;  
                      // FINANCIAL ANALYSIS WEIGHTS=====================================================================================================	
	     
		              // A. ALL CATEGORIES
		              // 1. Financial Analysis Weights
		 $LiquidityCategoryWeight                         = null;
		 $ProfitabilityCategoryWeight                     = null;
		 $CapitalStructureCategoryWeight                  = null;
		 $DebtServiceCategoryWeight                       = null;
	     
		              // B. LIQUIDITY CATEGORY_____________________________________________________________________________________________________________
		 
		              // 1. Financial Analysis - Liquidity Category Weights
	     $CurrentRatioWeight                              = null;
		 $DebtorDaysWeight                                = null;
		 $TurnoverToWorkingCapitalWeight                  = null;
		              // 2. Financial Analysis - Liquidity Category Effective Weights : Big Firms
	     $bfCurrentRatioEffectiveWeight                   = null;
		 $bfDebtorDaysEffectiveWeight                     = null;
		 $bfTurnoverToWorkingCapitalEffectiveWeight       = null;
		              // 3. Financial Analysis - Liquidity Category Effective Weights : Small Firms
	     $sfCurrentRatioEffectiveWeight                   = null;
		 $sfDebtorDaysEffectiveWeight                     = null;
		 $sfTurnoverToWorkingCapitalEffectiveWeight       = null;
		              // 4. Financial Analysis - Liquidity Category Scores : Big Firms
	     $bfCurrentRatioScore                             = null;
		 $bfDebtorDaysScore                               = null;
		 $bfTurnoverToWorkingCapitalScore                 = null;
		              // 5. Financial Analysis - Liquidity Category Scores : Small Firms
	     $sfCurrentRatioScore                             = null;
		 $sfDebtorDaysScore                               = null;
		 $sfTurnoverToWorkingCapitalScore                 = null;
		 
		              // C. PROFITABILITY CATEGORY_______________________________________________________________________________________________________________
		 
		              // 1. Financial Analysis - Profitability Category Weights
	     $GrossProfitMarginWeight                         = null;
		 $OperatingProfitMarginWeight                     = null;
		 $TurnoverGrowthWeight                            = null;
		 $ReturnOnAssetsWeight                            = null;
		 $ReturnOnInvestmentsWeight                       = null;
		 $ReturnOnInvestmentsWeight                       = null;
		              // 2. Financial Analysis - Profitability Category Effective Weights : Big Firms
	     $bfGrossProfitMarginEffectiveWeight              = null;
		 $bfOperatingProfitMarginEffectiveWeight          = null;
		 $bfTurnoverGrowthEffectiveWeight                 = null;
		 $bfReturnOnAssetsEffectiveWeight                 = null;
		 $bfReturnOnInvestmentsEffectiveWeight            = null;
 		              // 3. Financial Analysis - Profitability Category Weights
	     $sfGrossProfitMarginEffectiveWeight              = null;
		 $sfOperatingProfitMarginEffectiveWeight          = null;
		 $sfTurnoverGrowthEffectiveWeight                 = null;
		 $sfReturnOnAssetsEffectiveWeight                 = null;
		 $sfReturnOnInvestmentsEffectiveWeight            = null;
 		              // 4. Financial Analysis - Profitability Category Weights
	     $bfGrossProfitMarginScore                        = null;
		 $bfOperatingProfitMarginScore                    = null;
		 $bfTurnoverGrowthScore                           = null;
		 $bfReturnOnAssetsScore                           = null;
		 $bfReturnOnInvestmentsScore                      = null;
 		              // 5. Financial Analysis - Profitability Category Weights
	     $sfGrossProfitMarginScore                        = null;
		 $sfOperatingProfitMarginScore                    = null;
		 $sfTurnoverGrowthScore                           = null;
		 $sfReturnOnAssetsScore                           = null;
		 $sfReturnOnInvestmentsScore                      = null;
 		
		             // D. CAPITAL STRUCTURE CATEGORY_______________________________________________________________________________________________________________
		
		             // 1. Financial Analysis - Capital Structure Category Weights
	     $DebtToEquityWeight                              = null;
		 $DebtToTangibleNetWorthWeight                    = null;
		 $ShareholdersFundsToTotalAssetsWeight            = null;
		             // 2. Financial Analysis - Capital Structure Category Effective Weights : Big Firms
	     $bfDebtToEquityEffectiveWeight                   = null;
		 $bfDebtToTangibleNetWorthEffectiveWeight         = null;
		 $bfShareholdersFundsToTotalAssetsEffectiveWeight = null;
		             // 3. Financial Analysis - Capital Structure Category Effective Weights  : Small Firms
	     $sfDebtToEquityEffectiveWeight                   = null;
		 $sfDebtToTangibleNetWorthEffectiveWeight         = null;
		 $sfShareholdersFundsToTotalAssetsEffectiveWeight = null;
	                 // 4. Financial Analysis - Capital Structure Category Scores : Big Firms
	     $bfDebtToEquityScore                             = null;
		 $bfDebtToTangibleNetWorthScore                   = null;
		 $bfShareholdersFundsToTotalAssetsScore           = null;
	                 // 5. Financial Analysis - Capital Structure Category Scores  : Small Firms
	     $sfDebtToEquityScore                             = null;
		 $sfDebtToTangibleNetWorthScore                   = null;
		 $sfShareholdersFundsToTotalAssetsScore           = null;

                     //E. DEBT SERVICE CATEGORY_____________________________________________________________________________________________________________

		             // 1. Financial Analysis - Debt Service Category Weights
	     $InterestCoverWeight                             = null;
		 $EBITDAToGrossIntDebtsWeight                     = null;
		             // 2. Financial Analysis - Debt Service Category Effective Weights: Big Firms
	     $bfInterestCoverEffectiveWeight                  = null;
		 $bfEBITDAToGrossIntDebtsEffectiveWeight          = null;
		             // 3. Financial Analysis - Debt Service Category Effective Weights: Small Firms
	     $sfInterestCoverEffectiveWeight                  = null;
		 $sfEBITDAToGrossIntDebtsEffectiveWeight          = null;
		             // 4. Financial Analysis - Debt Service Category Weights
	     $bfInterestCoverScore                            = null;
		 $bfEBITDAToGrossScore                            = null;
		             // 5. Financial Analysis - Debt Service Category Weights
	     $sfInterestCoverScore                            = null;
		 $sfEBITDAToGrossScore                            = null;
	    
   		 //MANAGEMENT ANALYSIS WEIGHTS            

          $CommitmentCategoryWeight                              =    null  ;  //  BehavioralAnalysis
          $IntegrityCategoryWeight                               =    null  ;  //  BehavioralAnalysis
          $InformationQualityCategoryWeight                      =    null  ;  //  BehavioralAnalysis
          $LeadershipCategoryWeight                              =    null  ;  //  BehavioralAnalysis
          $StrategyCategoryWeight                                =    null  ;  //  BehavioralAnalysis
          $StructureCategoryWeight                               =    null  ;  //  BehavioralAnalysis
          $ManagementCategoryWeight                              =    null  ;  //  BehavioralAnalysis
          $SuccessionPlanCategoryWeight                          =    null  ;  //  BehavioralAnalysis
          $OrganisationalDesignCategoryWeight                    =    null  ;  //  BehavioralAnalysis
          $BusinessCyclicalityWeight                             =    null  ;  //  BehavioralAnalysis
          $IndustryPerformanceWeight                             =    null  ;  //  BehavioralAnalysis
          $PortersWeight                                         =    null  ;  //  BehavioralAnalysis
          $OwnersPaidDebtExceedsDefaultsWeight                   =    null  ;  //  BehavioralAnalysis
          $OwnersNoOfJudgementsWeight                            =    null  ;  //  BehavioralAnalysis
          $OwnersNoOfDefaultsWeight                              =    null  ;  //  BehavioralAnalysis
          $OwnersNoOfTraceAlertsWeight                           =    null  ;  //  BehavioralAnalysis
          $LoanRateTypeWeight                                    =    null  ;  //  BehavioralAnalysis
          $LoanMaturityWeight                                    =    null  ;  //  BehavioralAnalysis
          $BBSBankingRelationshipYearsWeight                     =    null  ;  //  BehavioralAnalysis
          $BBSBankingProductsNoWeight                            =    null  ;  //  BehavioralAnalysis
          $PastYearArrearIncidentsNoWeight                       =    null  ;  //  BehavioralAnalysis
          $Past2YearsArrearLoansRenegotiatedNoWeight             =    null  ;  //  BehavioralAnalysis
          $PaidDebtExceedsDefaultsWeight                         =    null  ;  //  BehavioralAnalysis
          $NoOfJudgementsWeight                                  =    null  ;  //  BehavioralAnalysis
          $NoOfDefaultsWeight                                    =    null  ;  //  BehavioralAnalysis
          $NoOfTraceAlertsWeight                                 =    null  ;  //  BehavioralAnalysis
          $CommitmentCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $IntegrityCategoryEffectiveWeight                      =    null  ;  //  BehavioralAnalysis
          $InformationQualityCategoryEffectiveWeight             =    null  ;  //  BehavioralAnalysis
          $LeadershipCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $StrategyCategoryEffectiveWeight                       =    null  ;  //  IndustryAnalysis
          $StructureCategoryEffectiveWeight                      =    null  ;  //  IndustryAnalysis
          $ManagementCategoryEffectiveWeight                     =    null  ;  //  IndustryAnalysis
          $SuccessionPlanCategoryEffectiveWeight                 =    null  ;  //  IndustryAnalysis
          $OrganisationalDesignCategoryEffectiveWeight           =    null  ;  //  IndustryAnalysis
          $BusinessCyclicalityEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $IndustryPerformanceEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $PortersEffectiveWeight                                =    null  ;  //  IndustryAnalysis
          $OwnersPaidDebtExceedsDefaultsEffectiveWeight          =    null  ;  //  IndustryAnalysis
          $OwnersNoOfJudgementsEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $OwnersNoOfDefaultsEffectiveWeight                     =    null  ;  //  ManagementAnalysis
          $OwnersNoOfTraceAlertsEffectiveWeight                  =    null  ;  //  ManagementAnalysis
          $LoanRateTypeEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $LoanMaturityEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $BBSBankingRelationshipYearsEffectiveWeight            =    null  ;  //  ManagementAnalysis
          $BBSBankingProductsNoEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $PastYearArrearIncidentsNoEffectiveWeight              =    null  ;  //  ManagementAnalysis
          $Past2YearsArrearLoansRenegotiatedNoEffectiveWeight    =    null  ;  //  ManagementAnalysis
          $PaidDebtExceedsDefaultsEffectiveWeight                =    null  ;  //  ManagementAnalysis
          $NoOfJudgementsEffectiveWeight                         =    null  ;  //  ManagementAnalysis
          $NoOfDefaultsEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $NoOfTraceAlertsEffectiveWeight                        =    null  ;  //  ManagementAnalysis
          $CommitmentCategoryScore                               =    null  ;  //  ManagementAnalysis
          $IntegrityCategoryScore                                =    null  ;  //  ManagementAnalysis
          $InformationQualityCategoryScore                       =    null  ;  //  ManagementAnalysis
          $LeadershipCategoryScore                               =    null  ;  //  ManagementAnalysis
          $StrategyCategoryScore                                 =    null  ;  //  ManagementAnalysis
          $StructureCategoryScore                                =    null  ;  //  ManagementAnalysis
          $ManagementCategoryScore                               =    null  ;  //  ManagementAnalysis
          $SuccessionPlanCategoryScore                           =    null  ;  //  ManagementAnalysis
          $OrganisationalDesignCategoryScore                     =    null  ;  //  ManagementAnalysis
          $BusinessCyclicalityScore                              =    null  ;  //  ManagementAnalysis
          $IndustryPerformanceScore                              =    null  ;  //  ManagementAnalysis
          $PortersScore                                          =    null  ;  //  ManagementAnalysis
          $OwnersPaidDebtExceedsDefaultsScore                    =    null  ;  //  ManagementAnalysis
          $OwnersNoOfJudgementsScore                             =    null  ;  //  ManagementAnalysis
          $OwnersNoOfDefaultsScore                               =    null  ;  //  ShareholderAnalysis
          $OwnersNoOfTraceAlertsScore                            =    null  ;  //  ShareholderAnalysis
          $LoanRateTypeScore                                     =    null  ;  //  ShareholderAnalysis
          $LoanMaturityScore                                     =    null  ;  //  ShareholderAnalysis
          $BBSBankingRelationshipYearsScore                      =    null  ;  //  ShareholderAnalysis
          $BBSBankingProductsNoScore                             =    null  ;  //  ShareholderAnalysis
          $PastYearArrearIncidentsNoScore                        =    null  ;  //  ShareholderAnalysis
          $Past2YearsArrearLoansRenegotiatedNoScore              =    null  ;  //  ShareholderAnalysis
          $PaidDebtExceedsDefaultsScore                          =    null  ;  //  ShareholderAnalysis
          $NoOfJudgementsScore                                   =    null  ;  //  ShareholderAnalysis
          $NoOfDefaultsScore                                     =    null  ;  //  ShareholderAnalysis
          $NoOfTraceAlertsScore                                  =    null  ;  //  ShareholderAnalysis
    		 
		 
	    /***************************************************************************************************************************************
	     STEP 2 : ASSIGN THE VARIABLES WITH EXTRACTED VALUES FROM THE FORM
         ****************************************************************************************************************************************/
         
		              //1. Main Settings Table Weights - Big Firms
	 	 $bfFinancialAnalysisPercentage                = str_replace(",","",$_POST['bfFinancialAnalysisPercentage']);
	 	 $bfManagementAnalysisPercentage               = str_replace(",","",$_POST['bfManagementAnalysisPercentage']);
	 	 $bfIndustryAnalysisPercentage                 = str_replace(",","",$_POST['bfIndustryAnalysisPercentage']);
	 	 $bfShareholderAnalysisPercentage              = str_replace(",","",$_POST['bfShareholderAnalysisPercentage']);
	 	 $bfBehavioralAnalysisPercentage               = str_replace(",","",$_POST['bfBehavioralAnalysisPercentage']);

		              //2. Main Settings Table Weights - Small Firms
	 	 $sfFinancialAnalysisPercentage                = str_replace(",","",$_POST['sfFinancialAnalysisPercentage']);
	 	 $sfManagementAnalysisPercentage               = str_replace(",","",$_POST['sfManagementAnalysisPercentage']);
	 	 $sfIndustryAnalysisPercentage                 = str_replace(",","",$_POST['sfIndustryAnalysisPercentage']);
	 	 $sfShareholderAnalysisPercentage              = str_replace(",","",$_POST['sfShareholderAnalysisPercentage']);
	 	 $sfBehavioralAnalysisPercentage               = str_replace(",","",$_POST['sfBehavioralAnalysisPercentage']);

		              //3. Main Settings Table Scores - Big Firms
	 	 $bfFinancialAnalysisScores                    = str_replace(",","",$_POST['bfFinancialAnalysisScore']);
	 	 $bfManagementAnalysisScores                   = str_replace(",","",$_POST['bfManagementAnalysisScore']);
	 	 $bfIndustryAnalysisScores                     = str_replace(",","",$_POST['bfIndustryAnalysisScore']);
	 	 $bfShareholderAnalysisScores                  = str_replace(",","",$_POST['bfShareholderAnalysisScore']);
	 	 $bfBehavioralAnalysisScores                   = str_replace(",","",$_POST['bfBehavioralAnalysisScore']);
		
		             //4. Main Settings Table Scores - Small Firms
	 	 $sfFinancialAnalysisScores                    = str_replace(",","",$_POST['sfFinancialAnalysisScore']);
	 	 $sfManagementAnalysisScores                   = str_replace(",","",$_POST['sfManagementAnalysisScore']);
	 	 $sfIndustryAnalysisScores                     = str_replace(",","",$_POST['sfIndustryAnalysisScore']);
	 	 $sfShareholderAnalysisScores                  = str_replace(",","",$_POST['sfShareholderAnalysisScore']);
	 	 $sfBehavioralAnalysisScores                   = str_replace(",","",$_POST['sfBehavioralAnalysisScore']);
		
		 // EXTRACTING FINANCIAL ANALYSIS CATEGORIES SETTINGS VARIABLES FROM FORM============================================================
		 
		              // ALL CATEGORIES WEIGHTS
		              // (a) Weights
		 $LiquidityCategoryWeight                       = str_replace(",","",$_POST['LiquidityCategoryWeight']);
		 $ProfitabilityCategoryWeight                   = str_replace(",","",$_POST['ProfitabilityCategoryWeight']);
		 $CapitalStructureCategoryWeight                = str_replace(",","",$_POST['CapitalStructureCategoryWeight']);
		 $DebtServiceCategoryWeight                     = str_replace(",","",$_POST['DebtServiceCategoryWeight']);
		
		              //  (b) Effective Weights - Big Firms
		 $bfLiquidityCategoryEffectiveWeight                = str_replace(",","",$_POST['bfLiquidityCategoryEffectiveWeight']);
		 $bfProfitabilityCategoryEffectiveWeight            = str_replace(",","",$_POST['bfProfitabilityCategoryEffectiveWeight']);
		 $bfCapitalStructureCategoryEffectiveWeight         = str_replace(",","",$_POST['bfCapitalStructureCategoryEffectiveWeight']);
		 $bfDebtServiceCategoryEffectiveWeight              = str_replace(",","",$_POST['bfDebtServiceCategoryEffectiveWeight']);
		              //  (c) Effective Weights - Small Firms
		 $sfLiquidityCategoryEffectiveWeight                = str_replace(",","",$_POST['sfLiquidityCategoryEffectiveWeight']);
		 $sfProfitabilityCategoryEffectiveWeight            = str_replace(",","",$_POST['sfProfitabilityCategoryEffectiveWeight']);
		 $sfCapitalStructureCategoryEffectiveWeight         = str_replace(",","",$_POST['sfCapitalStructureCategoryEffectiveWeight']);
		 $sfDebtServiceCategoryEffectiveWeight              = str_replace(",","",$_POST['sfDebtServiceCategoryEffectiveWeight']);
		              //  (d) Scores - Big Firms
		 $bfLiquidityCategoryScore                          = str_replace(",","",$_POST['bfLiquidityCategoryScore']);
		 $bfProfitabilityCategoryScore                      = str_replace(",","",$_POST['bfProfitabilityCategoryScore']);
		 $bfCapitalStructureCategoryScore                   = str_replace(",","",$_POST['bfCapitalStructureCategoryScore']);
		 $bfDebtServiceCategoryScore                        = str_replace(",","",$_POST['bfDebtServiceCategoryScore']);
		              //  (e) Scores - Small Firms
		 $sfLiquidityCategoryScore                          = str_replace(",","",$_POST['sfLiquidityCategoryScore']);
		 $sfProfitabilityCategoryScore                      = str_replace(",","",$_POST['sfProfitabilityCategoryScore']);
		 $sfCapitalStructureCategoryScore                   = str_replace(",","",$_POST['sfCapitalStructureCategoryScore']);
		 $sfDebtServiceCategoryScore                        = str_replace(",","",$_POST['sfDebtServiceCategoryScore']);
	


		              // MADE UP OF:
		 
                      // A. LIQUIDITY CATEGORY:___________________________________________________________________________________________________________
	     
		              // 1.Financial Analysis - Liquidity Category Weights
	     $CurrentRatioWeight                              = str_replace(",","",$_POST['CurrentRatioWeight']);
		 $DebtorDaysWeight                                = str_replace(",","",$_POST['DebtorDaysWeight']);
		 $TurnoverToWorkingCapitalWeight                  = str_replace(",","",$_POST['TurnoverToWorkingCapitalWeight']);

		              // 2. Financial Analysis - Liquidity Category Effective Weights : Big Firms
	     $bfCurrentRatioEffectiveWeight                   = str_replace(",","",$_POST['bfCurrentRatioEffectiveWeight']);
		 $bfDebtorDaysEffectiveWeight                     = str_replace(",","",$_POST['bfDebtorDaysEffectiveWeight']);
		 $bfTurnoverToWorkingCapitalEffectiveWeight       = str_replace(",","",$_POST['bfTurnoverToWorkingCapitalEffectiveWeight']);
		 
		              // 3. Financial Analysis - Liquidity Category Effective Weights : Small Firms
	     $sfCurrentRatioEffectiveWeight                   = str_replace(",","",$_POST['sfCurrentRatioEffectiveWeight']);
		 $sfDebtorDaysEffectiveWeight                     = str_replace(",","",$_POST['sfDebtorDaysEffectiveWeight']);
		 $sfTurnoverToWorkingCapitalEffectiveWeight       = str_replace(",","",$_POST['sfTurnoverToWorkingCapitalEffectiveWeight']);
	
	                  // 4. Financial Analysis - Liquidity Category scores : Big Firms
	     $bfCurrentRatioScore                             = str_replace(",","",$_POST['bfCurrentRatioScore']);
		 $bfDebtorDaysScore                               = str_replace(",","",$_POST['bfDebtorDaysScore']);
		 $bfTurnoverToWorkingCapitalScore                 = str_replace(",","",$_POST['bfTurnoverToWorkingCapitalScore']);
		 
		              // 5. Financial Analysis - Liquidity Category scores : Small Firms
	     $sfCurrentRatioScore                             = str_replace(",","",$_POST['sfCurrentRatioScore']);
		 $sfDebtorDaysScore                               = str_replace(",","",$_POST['sfDebtorDaysScore']);
		 $sfTurnoverToWorkingCapitalScore                 = str_replace(",","",$_POST['sfTurnoverToWorkingCapitalScore']);
		 
		 
		              //  B. PROFITABILITY CATEGORY:______________________________________________________________________________________________________		 
		 
		              // 1. Financial Analysis - Profitability Category Weights
	     $GrossProfitMarginWeight                         = str_replace(",","",$_POST['GrossProfitMarginWeight']);
		 $OperatingProfitMarginWeight                     = str_replace(",","",$_POST['OperatingProfitMarginWeight']);
		 $TurnoverGrowthWeight                            = str_replace(",","",$_POST['TurnoverGrowthWeight']);
		 $ReturnOnAssetsWeight                            = str_replace(",","",$_POST['ReturnOnAssetsWeight']);
		 $ReturnOnInvestmentsWeight                       = str_replace(",","",$_POST['ReturnOnInvestmentsWeight']);
 		 
		              // 2. Financial Analysis - Profitability Category Effective Weights : Big Firms
	     $bfGrossProfitMarginEffectiveWeight              = str_replace(",","",$_POST['bfGrossProfitMarginEffectiveWeight']);
		 $bfOperatingProfitEffectiveMarginWeight          = str_replace(",","",$_POST['bfOperatingProfitMarginEffectiveWeight']);
		 $bfTurnoverGrowthEffectiveWeight                 = str_replace(",","",$_POST['bfTurnoverGrowthEffectiveWeight']);
		 $bfReturnOnAssetsEffectiveWeight                 = str_replace(",","",$_POST['bfReturnOnAssetsEffectiveWeight']);
		 $bfReturnOnInvestmentsEffectiveWeight            = str_replace(",","",$_POST['bfReturnOnInvestmentsEffectiveWeight']);
		 
		              // 3. Financial Analysis - Profitability Category Effective Weights : Small Firms
		 $sfGrossProfitMarginEffectiveWeight              = str_replace(",","",$_POST['sfGrossProfitMarginEffectiveWeight']);
		 $sfOperatingProfitEffectiveMarginWeight          = str_replace(",","",$_POST['sfOperatingProfitMarginEffectiveWeight']);
		 $sfTurnoverGrowthEffectiveWeight                 = str_replace(",","",$_POST['sfTurnoverGrowthEffectiveWeight']);
		 $sfReturnOnAssetsEffectiveWeight                 = str_replace(",","",$_POST['sfReturnOnAssetsEffectiveWeight']);
		 $sfReturnOnInvestmentsEffectiveWeight            = str_replace(",","",$_POST['sfReturnOnInvestmentsEffectiveWeight']);
			 
		              // 4. Financial Analysis - Profitability Category Scores : Big Firms
	     $bfGrossProfitMarginScore                        = str_replace(",","",$_POST['bfGrossProfitMarginScore']);
		 $bfOperatingProfitScore                          = str_replace(",","",$_POST['bfOperatingProfitMarginScore']);
		 $bfTurnoverGrowthScore                           = str_replace(",","",$_POST['bfTurnoverGrowthScore']);
		 $bfReturnOnAssetsScore                           = str_replace(",","",$_POST['bfReturnOnAssetsScore']);
		 $bfReturnOnInvestmentsScore                      = str_replace(",","",$_POST['bfReturnOnInvestmentsScore']);
		 
		              // 5. Financial Analysis - Profitability Category Scores : Small Firms
	     $sfGrossProfitMarginScore                        = str_replace(",","",$_POST['sfGrossProfitMarginScore']);
		 $sfOperatingProfitScore                          = str_replace(",","",$_POST['sfOperatingProfitMarginScore']);
		 $sfTurnoverGrowthScore                           = str_replace(",","",$_POST['sfTurnoverGrowthScore']);
		 $sfReturnOnAssetsScore                           = str_replace(",","",$_POST['sfReturnOnAssetsScore']);
		 $sfReturnOnInvestmentsScore                      = str_replace(",","",$_POST['sfReturnOnInvestmentsScore']);
			 
		              //  C. CAPITAL STRUCTURE CATEGORY:__________________________________________________________________________________________________		 
		 
		              // 1. Financial Analysis - Capital Structure Category Weights
	     $DebtToEquityWeight                              = str_replace(",","",$_POST['DebtToEquityWeight']);
		 $DebtToTangibleNetWorthWeight                    = str_replace(",","",$_POST['DebtToTangibleNetWorthWeight']);
		 $ShareholdersFundsToTotalAssetsWeight            = str_replace(",","",$_POST['ShareholdersFundsToTotalAssetsWeight']);
		              // 2. Financial Analysis - Capital Structure Category Effective Weights : Big Firms
	     $bfDebtToEquityEffectiveWeight                   = str_replace(",","",$_POST['bfDebtToEquityEffectiveWeight']);
		 $bfDebtToTangibleNetWorthEffectiveWeight         = str_replace(",","",$_POST['bfDebtToTangibleNetWorthEffectiveWeight']);
		 $bfShareholdersFundsToTotalAssetsEffectiveWeight = str_replace(",","",$_POST['bfShareholdersFundsToTotalAssetsEffectiveWeight']);
		              // 3. Financial Analysis - Capital Structure Category Effective Weights : Small Firms
	     $sfDebtToEquityEffectiveWeight                   = str_replace(",","",$_POST['sfDebtToEquityEffectiveWeight']);
		 $sfDebtToTangibleNetWorthEffectiveWeight         = str_replace(",","",$_POST['sfDebtToTangibleNetWorthEffectiveWeight']);
		 $sfShareholdersFundsToTotalAssetsEffectiveWeight = str_replace(",","",$_POST['sfShareholdersFundsToTotalAssetsEffectiveWeight']);

		              // 4. Financial Analysis - Capital Structure Category Scores : Big Firms
	     $bfDebtToEquityScore                               = str_replace(",","",$_POST['bfDebtToEquityScore']);
		 $bfDebtToTangibleNetWorthScore                     = str_replace(",","",$_POST['bfDebtToTangibleNetWorthScore']);
		 $bfShareholdersFundsToTotalAssetsScore             = str_replace(",","",$_POST['bfShareholdersFundsToTotalAssetsScore']);
	                  // 5. Financial Analysis - Capital Structure Category Scores : Small Firms
	     $sfDebtToEquityScore                               = str_replace(",","",$_POST['sfDebtToEquityScore']);
		 $sfDebtToTangibleNetWorthScore                     = str_replace(",","",$_POST['sfDebtToTangibleNetWorthScore']);
		 $sfShareholdersFundsToTotalAssetsScore             = str_replace(",","",$_POST['sfShareholdersFundsToTotalAssetsScore']);

		              //  D. DEBT SERVICE CATEGORY:____________________________________________________________________________________________________________		 
		 
		              //  1. Financial Analysis - Debt Service Category Weights
	     $InterestCoverWeight                             = str_replace(",","",$_POST['InterestCoverWeight']);
		 $EBITDAToGrossIntDebtsWeight                     = str_replace(",","",$_POST['EBITDAToGrossIntDebtsWeight']);
		              //  2. Financial Analysis - Debt Service Category Effective Weights : Big Firms
	     $bfInterestCoverEffectiveWeight                  = str_replace(",","",$_POST['bfInterestCoverEffectiveWeight']);
		 $bfEBITDAToGrossIntDebtsEffectiveWeight          = str_replace(",","",$_POST['bfEBITDAToGrossIntDebtsEffectiveWeight']);
		              //  3. Financial Analysis - Debt Service Category Effective Weights : Small Firms
	     $sfInterestCoverEffectiveWeight                  = str_replace(",","",$_POST['sfInterestCoverEffectiveWeight']);
		 $sfEBITDAToGrossIntDebtsEffectiveWeight          = str_replace(",","",$_POST['sfEBITDAToGrossIntDebtsEffectiveWeight']);
		              //  4. Financial Analysis - Debt Service Category Scores : Big Firms
	     $bfInterestCoverScore                            = str_replace(",","",$_POST['bfInterestCoverScore']);
		 $bfEBITDAToGrossIntDebtsScore                    = str_replace(",","",$_POST['bfEBITDAToGrossIntDebtsScore']);
		              //  5. Financial Analysis - Debt Service Category Scores  : Small Firms
	     $sfInterestCoverScore                            = str_replace(",","",$_POST['sfInterestCoverScore']);
		 $sfEBITDAToGrossIntDebtsScore                    = str_replace(",","",$_POST['sfEBITDAToGrossIntDebtsScore']);

		 
		              // ASSIGNING TURNOVER THRESHOLD AND RATIO WEIGHTS VARIABLES BEFORE SAVING ON DATABASE
	     $TurnoverThreshold                               = str_replace(",","",$_POST['TurnoverThreshold']);  
         $RatioWeightYear1                                = str_replace(",","",$_POST['RatioWeightYear1']);  
       	 $RatioWeightYear2                                = str_replace(",","",$_POST['RatioWeightYear2']);  
       	 $RatioWeightYear3                                = str_replace(",","",$_POST['RatioWeightYear3']);  
	    
         $CommitmentCategoryWeight                              =    null  ;  //  BehavioralAnalysis
  
 
 
 
 
 
 
 
 
 //1. MANAGEMENT ANALYSIS SubGroupWeights
$CommitmentCategoryWeight=str_replace(",",$_POST['CommitmentCategoryWeight'];
$IntegrityCategoryWeight=str_replace(",",$_POST['IntegrityCategoryWeight'];
$InformationQualityCategoryWeight=str_replace(",",$_POST['InformationQualityCategoryWeight'];
$LeadershipCategoryWeight=str_replace(",",$_POST['LeadershipCategoryWeight'];
$StrategyCategoryWeight=str_replace(",",$_POST['StrategyCategoryWeight'];
$StructureCategoryWeight=str_replace(",",$_POST['StructureCategoryWeight'];
$SuccessionPlanCategoryWeight=str_replace(",",$_POST['SuccessionPlanCategoryWeight'];
$OrganisationalDesignCategoryWeight=str_replace(",",$_POST['OrganisationalDesignCategoryWeight'];

$//2.EffectiveWeights=str_replace(",",$_POST['//2.EffectiveWeights'];

$//1.BigFirms=str_replace(",",$_POST['//1.BigFirms'];
$CommitmentCategoryEffectiveWeight=str_replace(",",$_POST['CommitmentCategoryEffectiveWeight'];
$IntegrityCategoryEffectiveWeight=str_replace(",",$_POST['IntegrityCategoryEffectiveWeight'];
$InformationQualityCategoryEffectiveWeight=str_replace(",",$_POST['InformationQualityCategoryEffectiveWeight'];
$LeadershipCategoryEffectiveWeight=str_replace(",",$_POST['LeadershipCategoryEffectiveWeight'];
$StrategyCategoryEffectiveWeight=str_replace(",",$_POST['StrategyCategoryEffectiveWeight'];
$StructureCategoryEffectiveWeight=str_replace(",",$_POST['StructureCategoryEffectiveWeight'];
$ManagementCategoryEffectiveWeight=str_replace(",",$_POST['ManagementCategoryEffectiveWeight'];
$SuccessionPlanCategoryEffectiveWeight=str_replace(",",$_POST['SuccessionPlanCategoryEffectiveWeight'];
$OrganisationalDesignCategoryEffectiveWeight=str_replace(",",$_POST['OrganisationalDesignCategoryEffectiveWeight'];
$//2.BigFirms=str_replace(",",$_POST['//2.BigFirms'];
$CommitmentCategoryEffectiveWeight=str_replace(",",$_POST['CommitmentCategoryEffectiveWeight'];
$IntegrityCategoryEffectiveWeight=str_replace(",",$_POST['IntegrityCategoryEffectiveWeight'];
$InformationQualityCategoryEffectiveWeight=str_replace(",",$_POST['InformationQualityCategoryEffectiveWeight'];
$LeadershipCategoryEffectiveWeight=str_replace(",",$_POST['LeadershipCategoryEffectiveWeight'];
$StrategyCategoryEffectiveWeight=str_replace(",",$_POST['StrategyCategoryEffectiveWeight'];
$StructureCategoryEffectiveWeight=str_replace(",",$_POST['StructureCategoryEffectiveWeight'];
$ManagementCategoryEffectiveWeight=str_replace(",",$_POST['ManagementCategoryEffectiveWeight'];
$SuccessionPlanCategoryEffectiveWeight=str_replace(",",$_POST['SuccessionPlanCategoryEffectiveWeight'];
$OrganisationalDesignCategoryEffectiveWeight=str_replace(",",$_POST['OrganisationalDesignCategoryEffectiveWeight'];

$//SAVINGINDUSTRYANALYSISMETRICS=str_replace(",",$_POST['//SAVINGINDUSTRYANALYSISMETRICS'];
$//1.SubGroupWeights=str_replace(",",$_POST['//1.SubGroupWeights'];
$BusinessCyclicalityWeight=str_replace(",",$_POST['BusinessCyclicalityWeight'];
$IndustryPerformanceWeight=str_replace(",",$_POST['IndustryPerformanceWeight'];
$PortersWeight=str_replace(",",$_POST['PortersWeight'];
$//2.EffectiveWeights=str_replace(",",$_POST['//2.EffectiveWeights'];
$//BigFirms=str_replace(",",$_POST['//BigFirms'];
$BusinessCyclicalityEffectiveWeight=str_replace(",",$_POST['BusinessCyclicalityEffectiveWeight'];
$IndustryPerformanceEffectiveWeight=str_replace(",",$_POST['IndustryPerformanceEffectiveWeight'];
$PortersEffectiveWeight=str_replace(",",$_POST['PortersEffectiveWeight'];
$//SmallFirms=str_replace(",",$_POST['//SmallFirms'];
$BusinessCyclicalityEffectiveWeight=str_replace(",",$_POST['BusinessCyclicalityEffectiveWeight'];
$IndustryPerformanceEffectiveWeight=str_replace(",",$_POST['IndustryPerformanceEffectiveWeight'];
$PortersEffectiveWeight=str_replace(",",$_POST['PortersEffectiveWeight'];

$//SAVINGSHAREHOLDERANALYSISMETRICS=str_replace(",",$_POST['//SAVINGSHAREHOLDERANALYSISMETRICS'];
$//1.SubGroupWeights=str_replace(",",$_POST['//1.SubGroupWeights'];
$OwnersPaidDebtExceedsDefaultsWeight=str_replace(",",$_POST['OwnersPaidDebtExceedsDefaultsWeight'];
$OwnersNoOfJudgementsWeight=str_replace(",",$_POST['OwnersNoOfJudgementsWeight'];
$OwnersNoOfDefaultsWeight=str_replace(",",$_POST['OwnersNoOfDefaultsWeight'];
$OwnersNoOfTraceAlertsWeight=str_replace(",",$_POST['OwnersNoOfTraceAlertsWeight'];
$//2.EffectiveWeights=str_replace(",",$_POST['//2.EffectiveWeights'];
$//BigFirms=str_replace(",",$_POST['//BigFirms'];
$OwnersPaidDebtExceedsDefaultsEffectiveWeight=str_replace(",",$_POST['OwnersPaidDebtExceedsDefaultsEffectiveWeight'];
$OwnersNoOfJudgementsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfJudgementsEffectiveWeight'];
$OwnersNoOfDefaultsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfDefaultsEffectiveWeight'];
$OwnersNoOfTraceAlertsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfTraceAlertsEffectiveWeight'];
$//SmallFirms=str_replace(",",$_POST['//SmallFirms'];
$OwnersPaidDebtExceedsDefaultsEffectiveWeight=str_replace(",",$_POST['OwnersPaidDebtExceedsDefaultsEffectiveWeight'];
$OwnersNoOfJudgementsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfJudgementsEffectiveWeight'];
$OwnersNoOfDefaultsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfDefaultsEffectiveWeight'];
$OwnersNoOfTraceAlertsEffectiveWeight=str_replace(",",$_POST['OwnersNoOfTraceAlertsEffectiveWeight'];

$//SAVINGBEHAVIORALANDPRODUCTSANALYSIS=str_replace(",",$_POST['//SAVINGBEHAVIORALANDPRODUCTSANALYSIS'];
$//1.SubGroupWeights=str_replace(",",$_POST['//1.SubGroupWeights'];
$LoanRateTypeWeight=str_replace(",",$_POST['LoanRateTypeWeight'];
$LoanMaturityWeight=str_replace(",",$_POST['LoanMaturityWeight'];
$BBSBankingRelationshipYearsWeight=str_replace(",",$_POST['BBSBankingRelationshipYearsWeight'];
$BBSBankingProductsNoWeight=str_replace(",",$_POST['BBSBankingProductsNoWeight'];
$PastYearArrearIncidentsNoWeight=str_replace(",",$_POST['PastYearArrearIncidentsNoWeight'];
$Past2YearsArrearLoansRenegotiatedNoWeight=str_replace(",",$_POST['Past2YearsArrearLoansRenegotiatedNoWeight'];
$PaidDebtExceedsDefaultsWeight=str_replace(",",$_POST['PaidDebtExceedsDefaultsWeight'];
$NoOfJudgementsWeight=str_replace(",",$_POST['NoOfJudgementsWeight'];
$NoOfDefaultsWeight=str_replace(",",$_POST['NoOfDefaultsWeight'];
$NoOfTraceAlertsWeight=str_replace(",",$_POST['NoOfTraceAlertsWeight'];
$//2.EffectiveWeights=str_replace(",",$_POST['//2.EffectiveWeights'];
$//BigFirms=str_replace(",",$_POST['//BigFirms'];
$LoanRateTypeEffectiveWeight=str_replace(",",$_POST['LoanRateTypeEffectiveWeight'];
$LoanMaturityEffectiveWeight=str_replace(",",$_POST['LoanMaturityEffectiveWeight'];
$BBSBankingRelationshipYearsEffectiveWeight=str_replace(",",$_POST['BBSBankingRelationshipYearsEffectiveWeight'];
$BBSBankingProductsNoEffectiveWeight=str_replace(",",$_POST['BBSBankingProductsNoEffectiveWeight'];
$PastYearArrearIncidentsNoEffectiveWeight=str_replace(",",$_POST['PastYearArrearIncidentsNoEffectiveWeight'];
$Past2YearsArrearLoansRenegotiatedNoEffectiveWeight=str_replace(",",$_POST['Past2YearsArrearLoansRenegotiatedNoEffectiveWeight'];
$PaidDebtExceedsDefaultsEffectiveWeight=str_replace(",",$_POST['PaidDebtExceedsDefaultsEffectiveWeight'];
$NoOfJudgementsEffectiveWeight=str_replace(",",$_POST['NoOfJudgementsEffectiveWeight'];
$NoOfDefaultsEffectiveWeight=str_replace(",",$_POST['NoOfDefaultsEffectiveWeight'];
$NoOfTraceAlertsEffectiveWeight=str_replace(",",$_POST['NoOfTraceAlertsEffectiveWeight'];

$//SmallFirms=str_replace(",",$_POST['//SmallFirms'];
$LoanRateTypeEffectiveWeight=str_replace(",",$_POST['LoanRateTypeEffectiveWeight'];
$LoanMaturityEffectiveWeight=str_replace(",",$_POST['LoanMaturityEffectiveWeight'];
$BBSBankingRelationshipYearsEffectiveWeight=str_replace(",",$_POST['BBSBankingRelationshipYearsEffectiveWeight'];
$BBSBankingProductsNoEffectiveWeight=str_replace(",",$_POST['BBSBankingProductsNoEffectiveWeight'];
$PastYearArrearIncidentsNoEffectiveWeight=str_replace(",",$_POST['PastYearArrearIncidentsNoEffectiveWeight'];
$Past2YearsArrearLoansRenegotiatedNoEffectiveWeight=str_replace(",",$_POST['Past2YearsArrearLoansRenegotiatedNoEffectiveWeight'];
$PaidDebtExceedsDefaultsEffectiveWeight=str_replace(",",$_POST['PaidDebtExceedsDefaultsEffectiveWeight'];
$NoOfJudgementsEffectiveWeight=str_replace(",",$_POST['NoOfJudgementsEffectiveWeight'];
$NoOfDefaultsEffectiveWeight=str_replace(",",$_POST['NoOfDefaultsEffectiveWeight'];
$NoOfTraceAlertsEffectiveWeight=str_replace(",",$_POST['NoOfTraceAlertsEffectiveWeight'];
  		
		
		
		
		
		
		
		
		
		
		
		
		
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


                      // GET DUMMY USERNAME AND UPDATE NOTES - NEED TO ADJUST FORM TO GET THE PASSWORD
     
        $username = $_POST['username'];
        $update_notes = "";	 	
	
		$insert_query = "INSERT IGNORE INTO model_calibration 

(charecteristic                        ,table_name                ,weight_in_table                             ,big_firm_effective_weight                              ,small_firm_effective_weight                           ,big_firm_max_score                           ,small_firm_max_score                         ,username  ,update_notes)
VALUES
('$bfFinancialAnalysis                ','GlobalSettings          ','bfFinancialAnalysisPercentage             ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$bfManagementAnalysis               ','GlobalSettings          ','bfManagementAnalysisPercentage            ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$bfIndustryAnalysis                 ','GlobalSettings          ','bfIndustryAnalysisPercentage              ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$bfShareholderAnalysis              ','GlobalSettings          ','bfShareholderAnalysisPercentage           ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$TotalScore                         ','GlobalSettings          ','null                                      ','null                                                 ',null                                                 ,'$bfTotalScore                              ','$sfTotalScore                              ',$username','$blank'),
('$sfFinancialAnalysis                ','GlobalSettings          ','sfFinancialAnalysisPercentage             ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$sfManagementAnalysis               ','GlobalSettings          ','sfManagementAnalysisPercentage            ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$sfIndustryAnalysis                 ','GlobalSettings          ','sfIndustryAnalysisPercentage              ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$sfShareholderAnalysis              ','GlobalSettings          ','sfShareholderAnalysisPercentage           ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$TurnoverThreshold                  ','GlobalSettings          ','$TurnoverThreshold                        ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$RatioWeightYear1                   ','GlobalSettings          ','$RatioWeightYear1                         ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$RatioWeightYear2                   ','GlobalSettings          ','$RatioWeightYear2                         ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$RatioWeightYear3                   ','GlobalSettings          ','$RatioWeightYear3                         ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$blank'),
('$update_notes                       ','GlobalSettings          ','$update_notes                             ','null                                                 ',null                                                 ,'null                                       ','null                                       ',$username','$update_notes'),
('$LiquidityCategory                  ','FinancialSettings       ','$LiquidityCategoryWeight                  ','$bfLiquidityCategoryEffectiveWeight                  ',$sfLiquidityCategoryEffectiveWeight                  ,'$bfLiquidityCategoryScore                  ','$sfLiquidityCategoryScore                  ',$username','$blank'),
('$ProfitabilityCategory              ','FinancialSettings       ','$ProfitabilityCategoryWeight              ','$bfProfitabilityCategoryEffectiveWeight              ',$sfProfitabilityCategoryEffectiveWeight              ,'$bfProfitabilityCategoryScore              ','$sfProfitabilityCategoryScore              ',$username','$blank'),
('$CapitalStructureCategory           ','FinancialSettings       ','$CapitalStructureCategoryWeight           ','$bfCapitalStructureCategoryEffectiveWeight           ',$sfCapitalStructureCategoryEffectiveWeight           ,'$bfCapitalStructureCategoryScore           ','$sfCapitalStructureCategoryScore           ',$username','$blank'),
('$DebtServiceCategory                ','FinancialSettings       ','$DebtServiceCategoryWeight                ','$bfDebtServiceCategoryEffectiveWeight                ',$sfDebtServiceCategoryEffectiveWeight                ,'$bfDebtServiceCategoryScore                ','$sfDebtServiceCategoryScore                ',$username','$blank'),
('$CurrentRatio                       ','LiquiditySettings       ','$CurrentRatioWeight                       ','$bfCurrentRatioEffectiveWeight                       ',$sfCurrentRatioEffectiveWeight                       ,'$bfCurrentRatioScore                       ','$sfCurrentRatioScore                       ',$username','$blank'),
('$DebtorDays                         ','LiquiditySettings       ','$DebtorDaysWeight                         ','$bfDebtorDaysEffectiveWeight                         ',$sfDebtorDaysEffectiveWeight                         ,'$bfDebtorDaysScore                         ','$sfDebtorDaysScore                         ',$username','$blank'),
('$TurnoverToWorkingCapital           ','LiquiditySettings       ','$TurnoverToWorkingCapitalWeight           ','$bfTurnoverToWorkingCapitalEffectiveWeight           ',$sfTurnoverToWorkingCapitalEffectiveWeight           ,'$bfTurnoverToWorkingCapitalScore           ','$sfTurnoverToWorkingCapitalScore           ',$username','$blank'),
('$GrossProfitMargin                  ','ProfitabilitySettings   ','$GrossProfitMarginWeight                  ','$bfGrossProfitMarginEffectiveWeight                  ',$sfGrossProfitMarginEffectiveWeight                  ,'$bfGrossProfitMarginScore                  ','$sfGrossProfitMarginScore                  ',$username','$blank'),
('$OperatingProfit                    ','ProfitabilitySettings   ','$OperatingProfitWeight                    ','$bfOperatingProfitEffectiveWeight                    ',$sfOperatingProfitEffectiveWeight                    ,'$bfOperatingProfitScore                    ','$sfOperatingProfitScore                    ',$username','$blank'),
('$TurnoverGrowth                     ','ProfitabilitySettings   ','$TurnoverGrowthWeight                     ','$bfTurnoverGrowthEffectiveWeight                     ',$sfTurnoverGrowthEffectiveWeight                     ,'$bfTurnoverGrowthScore                     ','$sfTurnoverGrowthScore                     ',$username','$blank'),
('$ReturnOnAssets                     ','ProfitabilitySettings   ','$ReturnOnAssetsWeight                     ','$bfReturnOnAssetsEffectiveWeight                     ',$sfReturnOnAssetsEffectiveWeight                     ,'$bfReturnOnAssetsScore                     ','$sfReturnOnAssetsScore                     ',$username','$blank'),
('$ReturnOnInvestments                ','ProfitabilitySettings   ','$ReturnOnInvestmentsWeight                ','$bfReturnOnInvestmentsEffectiveWeight                ',$sfReturnOnInvestmentsEffectiveWeight                ,'$bfReturnOnInvestmentsScore                ','$sfReturnOnInvestmentsScore                ',$username','$blank'),
('$DebtToEquity                       ','CapitalStructureSettings','$DebtToEquityWeight                       ','$bfDebtToEquityEffectiveWeight                       ',$sfDebtToEquityEffectiveWeight                       ,'$bfDebtToEquityScore                       ','$sfDebtToEquityScore                       ',$username','$blank'),
('$DebtToTangibleNetWorth             ','CapitalStructureSettings','$DebtToTangibleNetWorthWeight             ','$bfDebtToTangibleNetWorthEffectiveWeight             ',$sfDebtToTangibleNetWorthEffectiveWeight             ,'$bfDebtToTangibleNetWorthScore             ','$sfDebtToTangibleNetWorthScore             ',$username','$blank'),
('$ShareholdersFundsToTotalAssets     ','CapitalStructureSettings','$ShareholdersFundsToTotalAssetsWeight     ','$bfShareholdersFundsToTotalAssetsEffectiveWeight     ',$sfShareholdersFundsToTotalAssetsEffectiveWeight     ,'$bfShareholdersFundsToTotalAssetsScore     ','$sfShareholdersFundsToTotalAssetsScore     ',$username','$blank'),
('$InterestCover                      ','DebtServiceSettings     ','$InterestCoverWeight                      ','$bfInterestCoverEffectiveWeight                      ',$sfInterestCoverEffectiveWeight                      ,'$bfInterestCoverScore                      ','$sfInterestCoverScore                      ',$username','$blank'),
('$EBITDAToGrossIntDebts              ','DebtServiceSettings     ','$EBITDAToGrossIntDebtsWeight              ','$bfEBITDAToGrossIntDebtsEffectiveWeight              ',$sfEBITDAToGrossIntDebtsEffectiveWeight              ,'$bfEBITDAToGrossIntDebtsScore              ','$sfEBITDAToGrossIntDebtsScore              ',$username','$blank'),
('$CommitmentCategory                 ','ManagementAnalysis      ','$CommitmentCategoryWeight                 ','$bfCommitmentCategoryEffectiveWeight                 ',$sfCommitmentCategoryEffectiveWeight                 ,'$bfCommitmentCategoryScore                 ','$sfCommitmentCategoryScore                 ',$username','$blank'),
('$IntegrityCategory                  ','ManagementAnalysis      ','$IntegrityCategoryWeight                  ','$bfIntegrityCategoryEffectiveWeight                  ',$sfIntegrityCategoryEffectiveWeight                  ,'$bfIntegrityCategoryScore                  ','$sfIntegrityCategoryScore                  ',$username','$blank'),
('$InformationQualityCategory         ','ManagementAnalysis      ','$InformationQualityCategoryWeight         ','$bfInformationQualityCategoryEffectiveWeight         ',$sfInformationQualityCategoryEffectiveWeight         ,'$bfInformationQualityCategoryScore         ','$sfInformationQualityCategoryScore         ',$username','$blank'),
('$LeadershipCategory                 ','ManagementAnalysis      ','$LeadershipCategoryWeight                 ','$bfLeadershipCategoryEffectiveWeight                 ',$sfLeadershipCategoryEffectiveWeight                 ,'$bfLeadershipCategoryScore                 ','$sfLeadershipCategoryScore                 ',$username','$blank'),
('$StrategyCategory                   ','ManagementAnalysis      ','$StrategyCategoryWeight                   ','$bfStrategyCategoryEffectiveWeight                   ',$sfStrategyCategoryEffectiveWeight                   ,'$bfStrategyCategoryScore                   ','$sfStrategyCategoryScore                   ',$username','$blank'),
('$StructureCategory                  ','ManagementAnalysis      ','$StructureCategoryWeight                  ','$bfStructureCategoryEffectiveWeight                  ',$sfStructureCategoryEffectiveWeight                  ,'$bfStructureCategoryScore                  ','$sfStructureCategoryScore                  ',$username','$blank'),
('$ManagementCategory                 ','ManagementAnalysis      ','$ManagementCategoryWeight                 ','$bfManagementCategoryEffectiveWeight                 ',$sfManagementCategoryEffectiveWeight                 ,'$bfManagementCategoryScore                 ','$sfManagementCategoryScore                 ',$username','$blank'),
('$SuccessionPlanCategory             ','ManagementAnalysis      ','$SuccessionPlanCategoryWeight             ','$bfSuccessionPlanCategoryEffectiveWeight             ',$sfSuccessionPlanCategoryEffectiveWeight             ,'$bfSuccessionPlanCategoryScore             ','$sfSuccessionPlanCategoryScore             ',$username','$blank'),
('$OrganisationalDesignCategory       ','ManagementAnalysis      ','$OrganisationalDesignCategoryWeight       ','$bfOrganisationalDesignCategoryEffectiveWeight       ',$sfOrganisationalDesignCategoryEffectiveWeight       ,'$bfOrganisationalDesignCategoryScore       ','$sfOrganisationalDesignCategoryScore       ',$username','$blank'),
('$BusinessCyclicality                ','IndustrialAnalysis      ','$BusinessCyclicalityWeight                ','$bfBusinessCyclicalityEffectiveWeight                ',$sfBusinessCyclicalityEffectiveWeight                ,'$bfBusinessCyclicalityScore                ','$sfBusinessCyclicalityScore                ',$username','$blank'),
('$IndustryPerformance                ','IndustrialAnalysis      ','$IndustryPerformanceWeight                ','$bfIndustryPerformanceEffectiveWeight                ',$sfIndustryPerformanceEffectiveWeight                ,'$bfIndustryPerformanceScore                ','$sfIndustryPerformanceScore                ',$username','$blank'),
('$Porters                            ','IndustrialAnalysis      ','$PortersWeight                            ','$bfPortersEffectiveWeight                            ',$sfPortersEffectiveWeight                            ,'$bfPortersScore                            ','$sfPortersScore                            ',$username','$blank'),
('$OwnersPaidDebtExceedsDefaults      ','ShareholderAnalysis     ','$OwnersPaidDebtExceedsDefaultsWeight      ','$bfOwnersPaidDebtExceedsDefaultsEffectiveWeight      ',$sfOwnersPaidDebtExceedsDefaultsEffectiveWeight      ,'$bfOwnersPaidDebtExceedsDefaultsScore      ','$sfOwnersPaidDebtExceedsDefaultsScore      ',$username','$blank'),
('$OwnersNoOfJudgements               ','ShareholderAnalysis     ','$OwnersNoOfJudgementsWeight               ','$bfOwnersNoOfJudgementsEffectiveWeight               ',$sfOwnersNoOfJudgementsEffectiveWeight               ,'$bfOwnersNoOfJudgementsScore               ','$sfOwnersNoOfJudgementsScore               ',$username','$blank'),
('$OwnersNoOfDefaults                 ','ShareholderAnalysis     ','$OwnersNoOfDefaultsWeight                 ','$bfOwnersNoOfDefaultsEffectiveWeight                 ',$sfOwnersNoOfDefaultsEffectiveWeight                 ,'$bfOwnersNoOfDefaultsScore                 ','$sfOwnersNoOfDefaultsScore                 ',$username','$blank'),
('$OwnersNoOfTraceAlerts              ','ShareholderAnalysis     ','$OwnersNoOfTraceAlertsWeight              ','$bfOwnersNoOfTraceAlertsEffectiveWeight              ',$sfOwnersNoOfTraceAlertsEffectiveWeight              ,'$bfOwnersNoOfTraceAlertsScore              ','$sfOwnersNoOfTraceAlertsScore              ',$username','$blank'),
('$LoanRateType                       ','BehavioralAnalysis      ','$LoanRateTypeWeight                       ','$bfLoanRateTypeEffectiveWeight                       ',$sfLoanRateTypeEffectiveWeight                       ,'$bfLoanRateTypeScore                       ','$sfLoanRateTypeScore                       ',$username','$blank'),
('$LoanMaturity                       ','BehavioralAnalysis      ','$LoanMaturityWeight                       ','$bfLoanMaturityEffectiveWeight                       ',$sfLoanMaturityEffectiveWeight                       ,'$bfLoanMaturityScore                       ','$sfLoanMaturityScore                       ',$username','$blank'),
('$BBSBankingRelationshipYears        ','BehavioralAnalysis      ','$BBSBankingRelationshipYearsWeight        ','$bfBBSBankingRelationshipYearsEffectiveWeight        ',$sfBBSBankingRelationshipYearsEffectiveWeight        ,'$bfBBSBankingRelationshipYearsScore        ','$sfBBSBankingRelationshipYearsScore        ',$username','$blank'),
('$BBSBankingProductsNo               ','BehavioralAnalysis      ','$BBSBankingProductsNoWeight               ','$bfBBSBankingProductsNoEffectiveWeight               ',$sfBBSBankingProductsNoEffectiveWeight               ,'$bfBBSBankingProductsNoScore               ','$sfBBSBankingProductsNoScore               ',$username','$blank'),
('$PastYearArrearIncidentsNo          ','BehavioralAnalysis      ','$PastYearArrearIncidentsNoWeight          ','$bfPastYearArrearIncidentsNoEffectiveWeight          ',$sfPastYearArrearIncidentsNoEffectiveWeight          ,'$bfPastYearArrearIncidentsNoScore          ','$sfPastYearArrearIncidentsNoScore          ',$username','$blank'),
('$Past2YearsArrearLoansRenegotiatedNo','BehavioralAnalysis      ','$Past2YearsArrearLoansRenegotiatedNoWeight','$bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight',$sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight,'$bfPast2YearsArrearLoansRenegotiatedNoScore','$sfPast2YearsArrearLoansRenegotiatedNoScore',$username','$blank'),
('$PaidDebtExceedsDefaults            ','BehavioralAnalysis      ','$PaidDebtExceedsDefaultsWeight            ','$bfPaidDebtExceedsDefaultsEffectiveWeight            ',$sfPaidDebtExceedsDefaultsEffectiveWeight            ,'$bfPaidDebtExceedsDefaultsScore            ','$sfPaidDebtExceedsDefaultsScore            ',$username','$blank'),
('$NoOfJudgements                     ','BehavioralAnalysis      ','$NoOfJudgementsWeight                     ','$bfNoOfJudgementsEffectiveWeight                     ',$sfNoOfJudgementsEffectiveWeight                     ,'$bfNoOfJudgementsScore                     ','$sfNoOfJudgementsScore                     ',$username','$blank'),
('$NoOfDefaults                       ','BehavioralAnalysis      ','$NoOfDefaultsWeight                       ','$bfNoOfDefaultsEffectiveWeight                       ',$sfNoOfDefaultsEffectiveWeight                       ,'$bfNoOfDefaultsScore                       ','$sfNoOfDefaultsScore                       ',$username','$blank'),
('$NoOfTraceAlerts                    ','BehavioralAnalysis      ','$NoOfTraceAlertsWeight                    ','$bfNoOfTraceAlertsEffectiveWeight                    ',$sfNoOfTraceAlertsEffectiveWeight                    ,'$bfNoOfTraceAlertsScore                    ','$sfNoOfTraceAlertsScore                    ',$username','$blank')"";
	
	
	
	
 
        $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting data'. mysqli_error();
          }
        else
		  {
		     echo 'Data successfully saved';
	      }
		  
 ?>
</body>

</html>