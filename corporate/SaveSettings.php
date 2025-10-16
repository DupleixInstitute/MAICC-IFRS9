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
          
		  //Big Business Effective weights and scores initialisation
		  $bfCommitmentCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $bfIntegrityCategoryEffectiveWeight                      =    null  ;  //  BehavioralAnalysis
          $bfInformationQualityCategoryEffectiveWeight             =    null  ;  //  BehavioralAnalysis
          $bfLeadershipCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $bfStrategyCategoryEffectiveWeight                       =    null  ;  //  IndustryAnalysis
          $bfStructureCategoryEffectiveWeight                      =    null  ;  //  IndustryAnalysis
          $bfManagementCategoryEffectiveWeight                     =    null  ;  //  IndustryAnalysis
          $bfSuccessionPlanCategoryEffectiveWeight                 =    null  ;  //  IndustryAnalysis
          $bfOrganisationalDesignCategoryEffectiveWeight           =    null  ;  //  IndustryAnalysis
          $bfBusinessCyclicalityEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $bfIndustryPerformanceEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $bfPortersEffectiveWeight                                =    null  ;  //  IndustryAnalysis
          $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight          =    null  ;  //  IndustryAnalysis
          $bfOwnersNoOfJudgementsEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $bfOwnersNoOfDefaultsEffectiveWeight                     =    null  ;  //  ManagementAnalysis
          $bfOwnersNoOfTraceAlertsEffectiveWeight                  =    null  ;  //  ManagementAnalysis
          $bfLoanRateTypeEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $bfLoanMaturityEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $bfBBSBankingRelationshipYearsEffectiveWeight            =    null  ;  //  ManagementAnalysis
          $bfBBSBankingProductsNoEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $bfPastYearArrearIncidentsNoEffectiveWeight              =    null  ;  //  ManagementAnalysis
          $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight    =    null  ;  //  ManagementAnalysis
          $bfPaidDebtExceedsDefaultsEffectiveWeight                =    null  ;  //  ManagementAnalysis
          $bfNoOfJudgementsEffectiveWeight                         =    null  ;  //  ManagementAnalysis
          $bfNoOfDefaultsEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $bfNoOfTraceAlertsEffectiveWeight                        =    null  ;  //  ManagementAnalysis
          $bfCommitmentCategoryScore                               =    null  ;  //  ManagementAnalysis
          $bfIntegrityCategoryScore                                =    null  ;  //  ManagementAnalysis
          $bfInformationQualityCategoryScore                       =    null  ;  //  ManagementAnalysis
          $bfLeadershipCategoryScore                               =    null  ;  //  ManagementAnalysis
          $bfStrategyCategoryScore                                 =    null  ;  //  ManagementAnalysis
          $bfStructureCategoryScore                                =    null  ;  //  ManagementAnalysis
          $bfManagementCategoryScore                               =    null  ;  //  ManagementAnalysis
          $bfSuccessionPlanCategoryScore                           =    null  ;  //  ManagementAnalysis
          $bfOrganisationalDesignCategoryScore                     =    null  ;  //  ManagementAnalysis
          $bfBusinessCyclicalityScore                              =    null  ;  //  ManagementAnalysis
          $bfIndustryPerformanceScore                              =    null  ;  //  ManagementAnalysis
          $bfPortersScore                                          =    null  ;  //  ManagementAnalysis
          $bfOwnersPaidDebtExceedsDefaultsScore                    =    null  ;  //  ManagementAnalysis
          $bfOwnersNoOfJudgementsScore                             =    null  ;  //  ManagementAnalysis
          $bfOwnersNoOfDefaultsScore                               =    null  ;  //  ShareholderAnalysis
          $bfOwnersNoOfTraceAlertsScore                            =    null  ;  //  ShareholderAnalysis
          $bfLoanRateTypeScore                                     =    null  ;  //  ShareholderAnalysis
          $bfLoanMaturityScore                                     =    null  ;  //  ShareholderAnalysis
          $bfBBSBankingRelationshipYearsScore                      =    null  ;  //  ShareholderAnalysis
          $bfBBSBankingProductsNoScore                             =    null  ;  //  ShareholderAnalysis
          $bfPastYearArrearIncidentsNoScore                        =    null  ;  //  ShareholderAnalysis
          $bfPast2YearsArrearLoansRenegotiatedNoScore              =    null  ;  //  ShareholderAnalysis
          $bfPaidDebtExceedsDefaultsScore                          =    null  ;  //  ShareholderAnalysis
          $bfNoOfJudgementsScore                                   =    null  ;  //  ShareholderAnalysis
          $bfNoOfDefaultsScore                                     =    null  ;  //  ShareholderAnalysis
          $bfNoOfTraceAlertsScore                                  =    null  ;  //  ShareholderAnalysis
    		 
          //Small Business Effective weights and scores initialisation
		  $sfCommitmentCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $sfIntegrityCategoryEffectiveWeight                      =    null  ;  //  BehavioralAnalysis
          $sfInformationQualityCategoryEffectiveWeight             =    null  ;  //  BehavioralAnalysis
          $sfLeadershipCategoryEffectiveWeight                     =    null  ;  //  BehavioralAnalysis
          $sfStrategyCategoryEffectiveWeight                       =    null  ;  //  IndustryAnalysis
          $sfStructureCategoryEffectiveWeight                      =    null  ;  //  IndustryAnalysis
          $sfManagementCategoryEffectiveWeight                     =    null  ;  //  IndustryAnalysis
          $sfSuccessionPlanCategoryEffectiveWeight                 =    null  ;  //  IndustryAnalysis
          $sfOrganisationalDesignCategoryEffectiveWeight           =    null  ;  //  IndustryAnalysis
          $sfBusinessCyclicalityEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $sfIndustryPerformanceEffectiveWeight                    =    null  ;  //  IndustryAnalysis
          $sfPortersEffectiveWeight                                =    null  ;  //  IndustryAnalysis
          $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight          =    null  ;  //  IndustryAnalysis
          $sfOwnersNoOfJudgementsEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $sfOwnersNoOfDefaultsEffectiveWeight                     =    null  ;  //  ManagementAnalysis
          $sfOwnersNoOfTraceAlertsEffectiveWeight                  =    null  ;  //  ManagementAnalysis
          $sfLoanRateTypeEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $sfLoanMaturityEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $sfBBSBankingRelationshipYearsEffectiveWeight            =    null  ;  //  ManagementAnalysis
          $sfBBSBankingProductsNoEffectiveWeight                   =    null  ;  //  ManagementAnalysis
          $sfPastYearArrearIncidentsNoEffectiveWeight              =    null  ;  //  ManagementAnalysis
          $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight    =    null  ;  //  ManagementAnalysis
          $sfPaidDebtExceedsDefaultsEffectiveWeight                =    null  ;  //  ManagementAnalysis
          $sfNoOfJudgementsEffectiveWeight                         =    null  ;  //  ManagementAnalysis
          $sfNoOfDefaultsEffectiveWeight                           =    null  ;  //  ManagementAnalysis
          $sfNoOfTraceAlertsEffectiveWeight                        =    null  ;  //  ManagementAnalysis
          $sfCommitmentCategoryScore                               =    null  ;  //  ManagementAnalysis
          $sfIntegrityCategoryScore                                =    null  ;  //  ManagementAnalysis
          $sfInformationQualityCategoryScore                       =    null  ;  //  ManagementAnalysis
          $sfLeadershipCategoryScore                               =    null  ;  //  ManagementAnalysis
          $sfStrategyCategoryScore                                 =    null  ;  //  ManagementAnalysis
          $sfStructureCategoryScore                                =    null  ;  //  ManagementAnalysis
          $sfManagementCategoryScore                               =    null  ;  //  ManagementAnalysis
          $sfSuccessionPlanCategoryScore                           =    null  ;  //  ManagementAnalysis
          $sfOrganisationalDesignCategoryScore                     =    null  ;  //  ManagementAnalysis
          $sfBusinessCyclicalityScore                              =    null  ;  //  ManagementAnalysis
          $sfIndustryPerformanceScore                              =    null  ;  //  ManagementAnalysis
          $sfPortersScore                                          =    null  ;  //  ManagementAnalysis
          $sfOwnersPaidDebtExceedsDefaultsScore                    =    null  ;  //  ManagementAnalysis
          $sfOwnersNoOfJudgementsScore                             =    null  ;  //  ManagementAnalysis
          $sfOwnersNoOfDefaultsScore                               =    null  ;  //  ShareholderAnalysis
          $sfOwnersNoOfTraceAlertsScore                            =    null  ;  //  ShareholderAnalysis
          $sfLoanRateTypeScore                                     =    null  ;  //  ShareholderAnalysis
          $sfLoanMaturityScore                                     =    null  ;  //  ShareholderAnalysis
          $sfBBSBankingRelationshipYearsScore                      =    null  ;  //  ShareholderAnalysis
          $sfBBSBankingProductsNoScore                             =    null  ;  //  ShareholderAnalysis
          $sfPastYearArrearIncidentsNoScore                        =    null  ;  //  ShareholderAnalysis
          $sfPast2YearsArrearLoansRenegotiatedNoScore              =    null  ;  //  ShareholderAnalysis
          $sfPaidDebtExceedsDefaultsScore                          =    null  ;  //  ShareholderAnalysis
          $sfNoOfJudgementsScore                                   =    null  ;  //  ShareholderAnalysis
          $sfNoOfDefaultsScore                                     =    null  ;  //  ShareholderAnalysis
          $sfNoOfTraceAlertsScore                                  =    null  ;  //  ShareholderAnalysis
    		 
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
	 	 $bfTotalScore                                 = str_replace(",","",$_POST['bfTotalScore']);
	 	 $sfTotalScore                                 = str_replace(",","",$_POST['sfTotalScore']);
	     $bfFinancialAnalysisScores                    = str_replace(",","",$_POST['bfFinancialAnalysisScore']);
	 	 $bfManagementAnalysisScores                   = str_replace(",","",$_POST['bfManagementAnalysisScore']);
	 	 $bfIndustryAnalysisScores                     = str_replace(",","",$_POST['bfIndustryAnalysisScore']);
	 	 $bfShareholderAnalysisScores                  = str_replace(",","",$_POST['bfShareholderAnalysisScore']);
	 	 $bfBehavioralAnalysisScores                   = str_replace(",","",$_POST['bfBehavioralAnalysisScore']);
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
		 $bfOperatingProfitMarginEffectiveWeight          = str_replace(",","",$_POST['bfOperatingProfitMarginEffectiveWeight']);
		 $bfTurnoverGrowthEffectiveWeight                 = str_replace(",","",$_POST['bfTurnoverGrowthEffectiveWeight']);
		 $bfReturnOnAssetsEffectiveWeight                 = str_replace(",","",$_POST['bfReturnOnAssetsEffectiveWeight']);
		 $bfReturnOnInvestmentsEffectiveWeight            = str_replace(",","",$_POST['bfReturnOnInvestmentsEffectiveWeight']);
		 
		              // 3. Financial Analysis - Profitability Category Effective Weights : Small Firms
		 $sfGrossProfitMarginEffectiveWeight              = str_replace(",","",$_POST['sfGrossProfitMarginEffectiveWeight']);
		 $sfOperatingProfitMarginEffectiveWeight          = str_replace(",","",$_POST['sfOperatingProfitMarginEffectiveWeight']);
		 $sfTurnoverGrowthEffectiveWeight                 = str_replace(",","",$_POST['sfTurnoverGrowthEffectiveWeight']);
		 $sfReturnOnAssetsEffectiveWeight                 = str_replace(",","",$_POST['sfReturnOnAssetsEffectiveWeight']);
		 $sfReturnOnInvestmentsEffectiveWeight            = str_replace(",","",$_POST['sfReturnOnInvestmentsEffectiveWeight']);
			 
		              // 4. Financial Analysis - Profitability Category Scores : Big Firms
	     $bfGrossProfitMarginScore                        = str_replace(",","",$_POST['bfGrossProfitMarginScore']);
		 $bfOperatingProfitMarginScore                    = str_replace(",","",$_POST['bfOperatingProfitMarginScore']);
		 $bfTurnoverGrowthScore                           = str_replace(",","",$_POST['bfTurnoverGrowthScore']);
		 $bfReturnOnAssetsScore                           = str_replace(",","",$_POST['bfReturnOnAssetsScore']);
		 $bfReturnOnInvestmentsScore                      = str_replace(",","",$_POST['bfReturnOnInvestmentsScore']);
		 
		              // 5. Financial Analysis - Profitability Category Scores : Small Firms
	     $sfGrossProfitMarginScore                        = str_replace(",","",$_POST['sfGrossProfitMarginScore']);
		 $sfOperatingProfitMarginScore                    = str_replace(",","",$_POST['sfOperatingProfitMarginScore']);
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
	    
          // EXTRACTING FORM DATA FOR NON-FINANCIAL DATA
		  $CommitmentCategoryWeight                              =    str_replace(",","",$_POST['CommitmentCategoryWeight'                          ]);  //  BehavioralAnalysis
          $IntegrityCategoryWeight                               =    str_replace(",","",$_POST['IntegrityCategoryWeight'                           ]);  //  BehavioralAnalysis
          $InformationQualityCategoryWeight                      =    str_replace(",","",$_POST['InformationQualityCategoryWeight'                  ]);  //  BehavioralAnalysis
          $LeadershipCategoryWeight                              =    str_replace(",","",$_POST['LeadershipCategoryWeight'                          ]);  //  BehavioralAnalysis
          $StrategyCategoryWeight                                =    str_replace(",","",$_POST['StrategyCategoryWeight'                            ]);  //  BehavioralAnalysis
          $StructureCategoryWeight                               =    str_replace(",","",$_POST['StructureCategoryWeight'                           ]);  //  BehavioralAnalysis
          $ManagementCategoryWeight                              =    str_replace(",","",$_POST['ManagementCategoryWeight'                          ]);  //  BehavioralAnalysis
          $SuccessionPlanCategoryWeight                          =    str_replace(",","",$_POST['SuccessionPlanCategoryWeight'                      ]);  //  BehavioralAnalysis
          $OrganisationalDesignCategoryWeight                    =    str_replace(",","",$_POST['OrganisationalDesignCategoryWeight'                ]);  //  BehavioralAnalysis
          $BusinessCyclicalityWeight                             =    str_replace(",","",$_POST['BusinessCyclicalityWeight'                         ]);  //  BehavioralAnalysis
          $IndustryPerformanceWeight                             =    str_replace(",","",$_POST['IndustryPerformanceWeight'                         ]);  //  BehavioralAnalysis
          $PortersWeight                                         =    str_replace(",","",$_POST['PortersWeight'                                     ]);  //  BehavioralAnalysis
          $OwnersPaidDebtExceedsDefaultsWeight                   =    str_replace(",","",$_POST['OwnersPaidDebtExceedsDefaultsWeight'               ]);  //  BehavioralAnalysis
          $OwnersNoOfJudgementsWeight                            =    str_replace(",","",$_POST['OwnersNoOfJudgementsWeight'                        ]);  //  BehavioralAnalysis
          $OwnersNoOfDefaultsWeight                              =    str_replace(",","",$_POST['OwnersNoOfDefaultsWeight'                          ]);  //  BehavioralAnalysis
          $OwnersNoOfTraceAlertsWeight                           =    str_replace(",","",$_POST['OwnersNoOfTraceAlertsWeight'                       ]);  //  BehavioralAnalysis
          $LoanRateTypeWeight                                    =    str_replace(",","",$_POST['LoanRateTypeWeight'                                ]);  //  BehavioralAnalysis
          $LoanMaturityWeight                                    =    str_replace(",","",$_POST['LoanMaturityWeight'                                ]);  //  BehavioralAnalysis
          $BBSBankingRelationshipYearsWeight                     =    str_replace(",","",$_POST['BBSBankingRelationshipYearsWeight'                 ]);  //  BehavioralAnalysis
          $BBSBankingProductsNoWeight                            =    str_replace(",","",$_POST['BBSBankingProductsNoWeight'                        ]);  //  BehavioralAnalysis
          $PastYearArrearIncidentsNoWeight                       =    str_replace(",","",$_POST['PastYearArrearIncidentsNoWeight'                   ]);  //  BehavioralAnalysis
          $Past2YearsArrearLoansRenegotiatedNoWeight             =    str_replace(",","",$_POST['Past2YearsArrearLoansRenegotiatedNoWeight'         ]);  //  BehavioralAnalysis
          $PaidDebtExceedsDefaultsWeight                         =    str_replace(",","",$_POST['PaidDebtExceedsDefaultsWeight'                     ]);  //  BehavioralAnalysis
          $NoOfJudgementsWeight                                  =    str_replace(",","",$_POST['NoOfJudgementsWeight'                              ]);  //  BehavioralAnalysis
          $NoOfDefaultsWeight                                    =    str_replace(",","",$_POST['NoOfDefaultsWeight'                                ]);  //  BehavioralAnalysis
          $NoOfTraceAlertsWeight                                 =    str_replace(",","",$_POST['NoOfTraceAlertsWeight'                             ]);  //  BehavioralAnalysis
          
		  //Big Business Form Data Extraction
		  $bfCommitmentCategoryEffectiveWeight                     =    str_replace(",","",$_POST['bfCommitmentCategoryEffectiveWeight'                 ]);  //  BehavioralAnalysis
          $bfIntegrityCategoryEffectiveWeight                      =    str_replace(",","",$_POST['bfIntegrityCategoryEffectiveWeight'                  ]);  //  BehavioralAnalysis
          $bfInformationQualityCategoryEffectiveWeight             =    str_replace(",","",$_POST['bfInformationQualityCategoryEffectiveWeight'         ]);  //  BehavioralAnalysis
          $bfLeadershipCategoryEffectiveWeight                     =    str_replace(",","",$_POST['bfLeadershipCategoryEffectiveWeight'                 ]);  //  BehavioralAnalysis
          $bfStrategyCategoryEffectiveWeight                       =    str_replace(",","",$_POST['bfStrategyCategoryEffectiveWeight'                   ]);  //  IndustryAnalysis
          $bfStructureCategoryEffectiveWeight                      =    str_replace(",","",$_POST['bfStructureCategoryEffectiveWeight'                  ]);  //  IndustryAnalysis
          $bfManagementCategoryEffectiveWeight                     =    str_replace(",","",$_POST['bfManagementCategoryEffectiveWeight'                 ]);  //  IndustryAnalysis
          $bfSuccessionPlanCategoryEffectiveWeight                 =    str_replace(",","",$_POST['bfSuccessionPlanCategoryEffectiveWeight'             ]);  //  IndustryAnalysis
          $bfOrganisationalDesignCategoryEffectiveWeight           =    str_replace(",","",$_POST['bfOrganisationalDesignCategoryEffectiveWeight'       ]);  //  IndustryAnalysis
          $bfBusinessCyclicalityEffectiveWeight                    =    str_replace(",","",$_POST['bfBusinessCyclicalityEffectiveWeight'                ]);  //  IndustryAnalysis
          $bfIndustryPerformanceEffectiveWeight                    =    str_replace(",","",$_POST['bfIndustryPerformanceEffectiveWeight'                ]);  //  IndustryAnalysis
          $bfPortersEffectiveWeight                                =    str_replace(",","",$_POST['bfPortersEffectiveWeight'                            ]);  //  IndustryAnalysis
          $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight          =    str_replace(",","",$_POST['bfOwnersPaidDebtExceedsDefaultsEffectiveWeight'      ]);  //  IndustryAnalysis
          $bfOwnersNoOfJudgementsEffectiveWeight                   =    str_replace(",","",$_POST['bfOwnersNoOfJudgementsEffectiveWeight'               ]);  //  ManagementAnalysis
          $bfOwnersNoOfDefaultsEffectiveWeight                     =    str_replace(",","",$_POST['bfOwnersNoOfDefaultsEffectiveWeight'                 ]);  //  ManagementAnalysis
          $bfOwnersNoOfTraceAlertsEffectiveWeight                  =    str_replace(",","",$_POST['bfOwnersNoOfTraceAlertsEffectiveWeight'              ]);  //  ManagementAnalysis
          $bfLoanRateTypeEffectiveWeight                           =    str_replace(",","",$_POST['bfLoanRateTypeEffectiveWeight'                       ]);  //  ManagementAnalysis
          $bfLoanMaturityEffectiveWeight                           =    str_replace(",","",$_POST['bfLoanMaturityEffectiveWeight'                       ]);  //  ManagementAnalysis
          $bfBBSBankingRelationshipYearsEffectiveWeight            =    str_replace(",","",$_POST['bfBBSBankingRelationshipYearsEffectiveWeight'        ]);  //  ManagementAnalysis
          $bfBBSBankingProductsNoEffectiveWeight                   =    str_replace(",","",$_POST['bfBBSBankingProductsNoEffectiveWeight'               ]);  //  ManagementAnalysis
          $bfPastYearArrearIncidentsNoEffectiveWeight              =    str_replace(",","",$_POST['bfPastYearArrearIncidentsNoEffectiveWeight'          ]);  //  ManagementAnalysis
          $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight    =    str_replace(",","",$_POST['bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight']);  //  ManagementAnalysis
          $bfPaidDebtExceedsDefaultsEffectiveWeight                =    str_replace(",","",$_POST['bfPaidDebtExceedsDefaultsEffectiveWeight'            ]);  //  ManagementAnalysis
          $bfNoOfJudgementsEffectiveWeight                         =    str_replace(",","",$_POST['bfNoOfJudgementsEffectiveWeight'                     ]);  //  ManagementAnalysis
          $bfNoOfDefaultsEffectiveWeight                           =    str_replace(",","",$_POST['bfNoOfDefaultsEffectiveWeight'                       ]);  //  ManagementAnalysis
          $bfNoOfTraceAlertsEffectiveWeight                        =    str_replace(",","",$_POST['bfNoOfTraceAlertsEffectiveWeight'                    ]);  //  ManagementAnalysis
          $bfCommitmentCategoryScore                               =    str_replace(",","",$_POST['bfCommitmentCategoryScore'                           ]);  //  ManagementAnalysis
          $bfIntegrityCategoryScore                                =    str_replace(",","",$_POST['bfIntegrityCategoryScore'                            ]);  //  ManagementAnalysis
          $bfInformationQualityCategoryScore                       =    str_replace(",","",$_POST['bfInformationQualityCategoryScore'                   ]);  //  ManagementAnalysis
          $bfLeadershipCategoryScore                               =    str_replace(",","",$_POST['bfLeadershipCategoryScore'                           ]);  //  ManagementAnalysis
          $bfStrategyCategoryScore                                 =    str_replace(",","",$_POST['bfStrategyCategoryScore'                             ]);  //  ManagementAnalysis
          $bfStructureCategoryScore                                =    str_replace(",","",$_POST['bfStructureCategoryScore'                            ]);  //  ManagementAnalysis
          $bfManagementCategoryScore                               =    str_replace(",","",$_POST['bfManagementCategoryScore'                           ]);  //  ManagementAnalysis
          $bfSuccessionPlanCategoryScore                           =    str_replace(",","",$_POST['bfSuccessionPlanCategoryScore'                       ]);  //  ManagementAnalysis
          $bfOrganisationalDesignCategoryScore                     =    str_replace(",","",$_POST['bfOrganisationalDesignCategoryScore'                 ]);  //  ManagementAnalysis
          $bfBusinessCyclicalityScore                              =    str_replace(",","",$_POST['bfBusinessCyclicalityScore'                          ]);  //  ManagementAnalysis
          $bfIndustryPerformanceScore                              =    str_replace(",","",$_POST['bfIndustryPerformanceScore'                          ]);  //  ManagementAnalysis
          $bfPortersScore                                          =    str_replace(",","",$_POST['bfPortersScore'                                      ]);  //  ManagementAnalysis
          $bfOwnersPaidDebtExceedsDefaultsScore                    =    str_replace(",","",$_POST['bfOwnersPaidDebtExceedsDefaultsScore'                ]);  //  ManagementAnalysis
          $bfOwnersNoOfJudgementsScore                             =    str_replace(",","",$_POST['bfOwnersNoOfJudgementsScore'                         ]);  //  ManagementAnalysis
          $bfOwnersNoOfDefaultsScore                               =    str_replace(",","",$_POST['bfOwnersNoOfDefaultsScore'                           ]);  //  ShareholderAnalysis
          $bfOwnersNoOfTraceAlertsScore                            =    str_replace(",","",$_POST['bfOwnersNoOfTraceAlertsScore'                        ]);  //  ShareholderAnalysis
          $bfLoanRateTypeScore                                     =    str_replace(",","",$_POST['bfLoanRateTypeScore'                                 ]);  //  ShareholderAnalysis
          $bfLoanMaturityScore                                     =    str_replace(",","",$_POST['bfLoanMaturityScore'                                 ]);  //  ShareholderAnalysis
          $bfBBSBankingRelationshipYearsScore                      =    str_replace(",","",$_POST['bfBBSBankingRelationshipYearsScore'                  ]);  //  ShareholderAnalysis
          $bfBBSBankingProductsNoScore                             =    str_replace(",","",$_POST['bfBBSBankingProductsNoScore'                         ]);  //  ShareholderAnalysis
          $bfPastYearArrearIncidentsNoScore                        =    str_replace(",","",$_POST['bfPastYearArrearIncidentsNoScore'                    ]);  //  ShareholderAnalysis
          $bfPast2YearsArrearLoansRenegotiatedNoScore              =    str_replace(",","",$_POST['bfPast2YearsArrearLoansRenegotiatedNoScore'          ]);  //  ShareholderAnalysis
          $bfPaidDebtExceedsDefaultsScore                          =    str_replace(",","",$_POST['bfPaidDebtExceedsDefaultsScore'                      ]);  //  ShareholderAnalysis
          $bfNoOfJudgementsScore                                   =    str_replace(",","",$_POST['bfNoOfJudgementsScore'                               ]);  //  ShareholderAnalysis
          $bfNoOfDefaultsScore                                     =    str_replace(",","",$_POST['bfNoOfDefaultsScore'                                 ]);  //  ShareholderAnalysis
          $bfNoOfTraceAlertsScore                                  =    str_replace(",","",$_POST['bfNoOfTraceAlertsScore'                              ]);  //  ShareholderAnalysis
 		  //Small Business Form Data Extraction
		  $sfCommitmentCategoryEffectiveWeight                     =    str_replace(",","",$_POST['sfCommitmentCategoryEffectiveWeight'                 ]);  //  BehavioralAnalysis
          $sfIntegrityCategoryEffectiveWeight                      =    str_replace(",","",$_POST['sfIntegrityCategoryEffectiveWeight'                  ]);  //  BehavioralAnalysis
          $sfInformationQualityCategoryEffectiveWeight             =    str_replace(",","",$_POST['sfInformationQualityCategoryEffectiveWeight'         ]);  //  BehavioralAnalysis
          $sfLeadershipCategoryEffectiveWeight                     =    str_replace(",","",$_POST['sfLeadershipCategoryEffectiveWeight'                 ]);  //  BehavioralAnalysis
          $sfStrategyCategoryEffectiveWeight                       =    str_replace(",","",$_POST['sfStrategyCategoryEffectiveWeight'                   ]);  //  IndustryAnalysis
          $sfStructureCategoryEffectiveWeight                      =    str_replace(",","",$_POST['sfStructureCategoryEffectiveWeight'                  ]);  //  IndustryAnalysis
          $sfManagementCategoryEffectiveWeight                     =    str_replace(",","",$_POST['sfManagementCategoryEffectiveWeight'                 ]);  //  IndustryAnalysis
          $sfSuccessionPlanCategoryEffectiveWeight                 =    str_replace(",","",$_POST['sfSuccessionPlanCategoryEffectiveWeight'             ]);  //  IndustryAnalysis
          $sfOrganisationalDesignCategoryEffectiveWeight           =    str_replace(",","",$_POST['sfOrganisationalDesignCategoryEffectiveWeight'       ]);  //  IndustryAnalysis
          $sfBusinessCyclicalityEffectiveWeight                    =    str_replace(",","",$_POST['sfBusinessCyclicalityEffectiveWeight'                ]);  //  IndustryAnalysis
          $sfIndustryPerformanceEffectiveWeight                    =    str_replace(",","",$_POST['sfIndustryPerformanceEffectiveWeight'                ]);  //  IndustryAnalysis
          $sfPortersEffectiveWeight                                =    str_replace(",","",$_POST['sfPortersEffectiveWeight'                            ]);  //  IndustryAnalysis
          $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight          =    str_replace(",","",$_POST['sfOwnersPaidDebtExceedsDefaultsEffectiveWeight'      ]);  //  IndustryAnalysis
          $sfOwnersNoOfJudgementsEffectiveWeight                   =    str_replace(",","",$_POST['sfOwnersNoOfJudgementsEffectiveWeight'               ]);  //  ManagementAnalysis
          $sfOwnersNoOfDefaultsEffectiveWeight                     =    str_replace(",","",$_POST['sfOwnersNoOfDefaultsEffectiveWeight'                 ]);  //  ManagementAnalysis
          $sfOwnersNoOfTraceAlertsEffectiveWeight                  =    str_replace(",","",$_POST['sfOwnersNoOfTraceAlertsEffectiveWeight'              ]);  //  ManagementAnalysis
          $sfLoanRateTypeEffectiveWeight                           =    str_replace(",","",$_POST['sfLoanRateTypeEffectiveWeight'                       ]);  //  ManagementAnalysis
          $sfLoanMaturityEffectiveWeight                           =    str_replace(",","",$_POST['sfLoanMaturityEffectiveWeight'                       ]);  //  ManagementAnalysis
          $sfBBSBankingRelationshipYearsEffectiveWeight            =    str_replace(",","",$_POST['sfBBSBankingRelationshipYearsEffectiveWeight'        ]);  //  ManagementAnalysis
          $sfBBSBankingProductsNoEffectiveWeight                   =    str_replace(",","",$_POST['sfBBSBankingProductsNoEffectiveWeight'               ]);  //  ManagementAnalysis
          $sfPastYearArrearIncidentsNoEffectiveWeight              =    str_replace(",","",$_POST['sfPastYearArrearIncidentsNoEffectiveWeight'          ]);  //  ManagementAnalysis
          $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight    =    str_replace(",","",$_POST['sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight']);  //  ManagementAnalysis
          $sfPaidDebtExceedsDefaultsEffectiveWeight                =    str_replace(",","",$_POST['sfPaidDebtExceedsDefaultsEffectiveWeight'            ]);  //  ManagementAnalysis
          $sfNoOfJudgementsEffectiveWeight                         =    str_replace(",","",$_POST['sfNoOfJudgementsEffectiveWeight'                     ]);  //  ManagementAnalysis
          $sfNoOfDefaultsEffectiveWeight                           =    str_replace(",","",$_POST['sfNoOfDefaultsEffectiveWeight'                       ]);  //  ManagementAnalysis
          $sfNoOfTraceAlertsEffectiveWeight                        =    str_replace(",","",$_POST['sfNoOfTraceAlertsEffectiveWeight'                    ]);  //  ManagementAnalysis
          $sfCommitmentCategoryScore                               =    str_replace(",","",$_POST['sfCommitmentCategoryScore'                           ]);  //  ManagementAnalysis
          $sfIntegrityCategoryScore                                =    str_replace(",","",$_POST['sfIntegrityCategoryScore'                            ]);  //  ManagementAnalysis
          $sfInformationQualityCategoryScore                       =    str_replace(",","",$_POST['sfInformationQualityCategoryScore'                   ]);  //  ManagementAnalysis
          $sfLeadershipCategoryScore                               =    str_replace(",","",$_POST['sfLeadershipCategoryScore'                           ]);  //  ManagementAnalysis
          $sfStrategyCategoryScore                                 =    str_replace(",","",$_POST['sfStrategyCategoryScore'                             ]);  //  ManagementAnalysis
          $sfStructureCategoryScore                                =    str_replace(",","",$_POST['sfStructureCategoryScore'                            ]);  //  ManagementAnalysis
          $sfManagementCategoryScore                               =    str_replace(",","",$_POST['sfManagementCategoryScore'                           ]);  //  ManagementAnalysis
          $sfSuccessionPlanCategoryScore                           =    str_replace(",","",$_POST['sfSuccessionPlanCategoryScore'                       ]);  //  ManagementAnalysis
          $sfOrganisationalDesignCategoryScore                     =    str_replace(",","",$_POST['sfOrganisationalDesignCategoryScore'                 ]);  //  ManagementAnalysis
          $sfBusinessCyclicalityScore                              =    str_replace(",","",$_POST['sfBusinessCyclicalityScore'                          ]);  //  ManagementAnalysis
          $sfIndustryPerformanceScore                              =    str_replace(",","",$_POST['sfIndustryPerformanceScore'                          ]);  //  ManagementAnalysis
          $sfPortersScore                                          =    str_replace(",","",$_POST['sfPortersScore'                                      ]);  //  ManagementAnalysis
          $sfOwnersPaidDebtExceedsDefaultsScore                    =    str_replace(",","",$_POST['sfOwnersPaidDebtExceedsDefaultsScore'                ]);  //  ManagementAnalysis
          $sfOwnersNoOfJudgementsScore                             =    str_replace(",","",$_POST['sfOwnersNoOfJudgementsScore'                         ]);  //  ManagementAnalysis
          $sfOwnersNoOfDefaultsScore                               =    str_replace(",","",$_POST['sfOwnersNoOfDefaultsScore'                           ]);  //  ShareholderAnalysis
          $sfOwnersNoOfTraceAlertsScore                            =    str_replace(",","",$_POST['sfOwnersNoOfTraceAlertsScore'                        ]);  //  ShareholderAnalysis
          $sfLoanRateTypeScore                                     =    str_replace(",","",$_POST['sfLoanRateTypeScore'                                 ]);  //  ShareholderAnalysis
          $sfLoanMaturityScore                                     =    str_replace(",","",$_POST['sfLoanMaturityScore'                                 ]);  //  ShareholderAnalysis
          $sfBBSBankingRelationshipYearsScore                      =    str_replace(",","",$_POST['sfBBSBankingRelationshipYearsScore'                  ]);  //  ShareholderAnalysis
          $sfBBSBankingProductsNoScore                             =    str_replace(",","",$_POST['sfBBSBankingProductsNoScore'                         ]);  //  ShareholderAnalysis
          $sfPastYearArrearIncidentsNoScore                        =    str_replace(",","",$_POST['sfPastYearArrearIncidentsNoScore'                    ]);  //  ShareholderAnalysis
          $sfPast2YearsArrearLoansRenegotiatedNoScore              =    str_replace(",","",$_POST['sfPast2YearsArrearLoansRenegotiatedNoScore'          ]);  //  ShareholderAnalysis
          $sfPaidDebtExceedsDefaultsScore                          =    str_replace(",","",$_POST['sfPaidDebtExceedsDefaultsScore'                      ]);  //  ShareholderAnalysis
          $sfNoOfJudgementsScore                                   =    str_replace(",","",$_POST['sfNoOfJudgementsScore'                               ]);  //  ShareholderAnalysis
          $sfNoOfDefaultsScore                                     =    str_replace(",","",$_POST['sfNoOfDefaultsScore'                                 ]);  //  ShareholderAnalysis
          $sfNoOfTraceAlertsScore                                  =    str_replace(",","",$_POST['sfNoOfTraceAlertsScore'                              ]);  //  ShareholderAnalysis
 

          //ECL parameters
		  $PDMinScore1                                             =   str_replace(",","",$_POST['PDMinScore1']);
		  $PDMinScore2                                             =   str_replace(",","",$_POST['PDMinScore2']);
		  $PDMinScore3                                             =   str_replace(",","",$_POST['PDMinScore3']);
		  $PDMinScore4                                             =   str_replace(",","",$_POST['PDMinScore4']);
		  $PDMinScore5                                             =   str_replace(",","",$_POST['PDMinScore5']);
		  $PDMinScore6                                             =   str_replace(",","",$_POST['PDMinScore6']);
		  $PDMinScore7                                             =   str_replace(",","",$_POST['PDMinScore7']);
		  $PDMinScore8                                             =   str_replace(",","",$_POST['PDMinScore8']);
 
		  $PDMaxScore1                                             =   str_replace(",","",$_POST['PDMaxScore1']);
		  $PDMaxScore2                                             =   str_replace(",","",$_POST['PDMaxScore2']);
		  $PDMaxScore3                                             =   str_replace(",","",$_POST['PDMaxScore3']);
		  $PDMaxScore4                                             =   str_replace(",","",$_POST['PDMaxScore4']);
		  $PDMaxScore5                                             =   str_replace(",","",$_POST['PDMaxScore5']);
		  $PDMaxScore6                                             =   str_replace(",","",$_POST['PDMaxScore6']);
		  $PDMaxScore7                                             =   str_replace(",","",$_POST['PDMaxScore7']);
		  $PDMaxScore8                                             =   str_replace(",","",$_POST['PDMaxScore8']);
 
		  $PDPercentage1                                           =   str_replace(",","",$_POST['PDPercentage1']);
		  $PDPercentage2                                           =   str_replace(",","",$_POST['PDPercentage2']);
		  $PDPercentage3                                           =   str_replace(",","",$_POST['PDPercentage3']);
		  $PDPercentage4                                           =   str_replace(",","",$_POST['PDPercentage4']);
		  $PDPercentage5                                           =   str_replace(",","",$_POST['PDPercentage5']);
		  $PDPercentage6                                           =   str_replace(",","",$_POST['PDPercentage6']);
		  $PDPercentage7                                           =   str_replace(",","",$_POST['PDPercentage7']);
		  $PDPercentage8                                           =   str_replace(",","",$_POST['PDPercentage8']);

		  $PDLowerLimitPercentage1                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage1']);
		  $PDLowerLimitPercentage2                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage2']);
		  $PDLowerLimitPercentage3                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage3']);
		  $PDLowerLimitPercentage4                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage4']);
		  $PDLowerLimitPercentage5                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage5']);
		  $PDLowerLimitPercentage6                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage6']);
		  $PDLowerLimitPercentage7                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage7']);
		  $PDLowerLimitPercentage8                                 =   str_replace(",","",$_POST['PDLowerLimitPercentage8']);
   
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
	    $blank = "";
		$insert_query = "REPLACE INTO model_calibration 

(charecteristic                        ,table_name                ,weight_in_table                            ,big_firm_effective_weight                              ,small_firm_effective_weight                            ,big_firm_max_score                           ,small_firm_max_score                         ,username  ,update_notes)
VALUES
('bfFinancialAnalysis'                ,'GlobalSettings'          ,'$bfFinancialAnalysisPercentage'            ,'null'                                                 ,'null'                                                 ,'$bfFinancialAnalysisScore'                   ,'null'                                       ,'$username','$blank'),
('bfManagementAnalysis'               ,'GlobalSettings'          ,'$bfManagementAnalysisPercentage'           ,'null'                                                 ,'null'                                                 ,'$bfManagementAnalysisScore'                  ,'null'                                       ,'$username','$blank'),
('bfIndustryAnalysis'                 ,'GlobalSettings'          ,'$bfIndustryAnalysisPercentage'             ,'null'                                                 ,'null'                                                 ,'$bfIndustryAnalysisScore'                    ,'null'                                       ,'$username','$blank'),
('bfShareholderAnalysis'              ,'GlobalSettings'          ,'$bfShareholderAnalysisPercentage'          ,'null'                                                 ,'null'                                                 ,'$bfShareholderAnalysisScore'                 ,'null'                                       ,'$username','$blank'),
('bfBehavioralAnalysis'               ,'GlobalSettings'          ,'$bfBehavioralAnalysisPercentage'           ,'null'                                                 ,'null'                                                 ,'$bfBehavioralAnalysisScore'                  ,'null'                                       ,'$username','$blank'),
('TotalScore'                         ,'GlobalSettings'          ,'null'                                      ,'null'                                                 ,'null'                                                 ,'$bfTotalScore'                               ,'$sfTotalScore'                              ,'$username','$blank'),
('sfFinancialAnalysis'                ,'GlobalSettings'          ,'$sfFinancialAnalysisPercentage'            ,'null'                                                 ,'null'                                                 ,'null'                                        ,'sfFinancialAnalysisScore'                   ,'$username','$blank'),
('sfManagementAnalysis'               ,'GlobalSettings'          ,'$sfManagementAnalysisPercentage'           ,'null'                                                 ,'null'                                                 ,'null'                                        ,'$sfManagementAnalysisScore'                 ,'$username','$blank'),
('sfIndustryAnalysis'                 ,'GlobalSettings'          ,'$sfIndustryAnalysisPercentage'             ,'null'                                                 ,'null'                                                 ,'null'                                        ,'$sfIndustryAnalysisScore'                   ,'$username','$blank'),
('sfShareholderAnalysis'              ,'GlobalSettings'          ,'$sfShareholderAnalysisPercentage'          ,'null'                                                 ,'null'                                                 ,'null'                                        ,'$sfShareholderAnalysisScore'                ,'$username','$blank'),
('sfBehavioralAnalysis'               ,'GlobalSettings'          ,'$sfBehavioralAnalysisPercentage'           ,'null'                                                 ,'null'                                                 ,'null'                                        ,'$sfBehavioralAnalysisScore$'                ,'$username','$blank'),
('TurnoverThreshold'                  ,'GlobalSettings'          ,'$TurnoverThreshold'                        ,'null'                                                 ,'null'                                                 ,'null'                                        ,'null'                                       ,'$username','$blank'),
('RatioWeightYear1'                   ,'GlobalSettings'          ,'$RatioWeightYear1'                         ,'null'                                                 ,'null'                                                 ,'null'                                        ,'null'                                       ,'$username','$blank'),
('RatioWeightYear2'                   ,'GlobalSettings'          ,'$RatioWeightYear2'                         ,'null'                                                 ,'null'                                                 ,'null'                                        ,'null'                                       ,'$username','$blank'),
('RatioWeightYear3'                   ,'GlobalSettings'          ,'$RatioWeightYear3'                         ,'null'                                                 ,'null'                                                 ,'null'                                        ,'null'                                       ,'$username','$blank'),
('PDParameter1'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage1'                  ,'$PDPercentage1'                                       ,'null'                                                 ,'$PDMaxScore1'                                ,'$PDMinScore1'                               ,'$username','$blank'),
('PDParameter2'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage2'                  ,'$PDPercentage2'                                       ,'null'                                                 ,'$PDMaxScore2'                                ,'$PDMinScore2'                               ,'$username','$blank'),
('PDParameter3'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage3'                  ,'$PDPercentage3'                                       ,'null'                                                 ,'$PDMaxScore3'                                ,'$PDMinScore3'                               ,'$username','$blank'),
('PDParameter4'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage4'                  ,'$PDPercentage4'                                       ,'null'                                                 ,'$PDMaxScore4'                                ,'$PDMinScore4'                               ,'$username','$blank'),
('PDParameter5'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage5'                  ,'$PDPercentage5'                                       ,'null'                                                 ,'$PDMaxScore5'                                ,'$PDMinScore5'                               ,'$username','$blank'),
('PDParameter6'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage6'                  ,'$PDPercentage6'                                       ,'null'                                                 ,'$PDMaxScore6'                                ,'$PDMinScore6'                               ,'$username','$blank'),
('PDParameter7'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage7'                  ,'$PDPercentage7'                                       ,'null'                                                 ,'$PDMaxScore7'                                ,'$PDMinScore7'                               ,'$username','$blank'),
('PDParameter8'                       ,'GlobalSettings'          ,'$PDLowerLimitPercentage8'                  ,'$PDPercentage8'                                       ,'null'                                                 ,'$PDMaxScore8'                                ,'$PDMinScore8'                               ,'$username','$blank'),
('update_notes'                       ,'GlobalSettings'          ,'$update_notes'                             ,'null'                                                 ,'null'                                                 ,'null'                                        ,'null'                                       ,'$username','$update_notes'),
('LiquidityCategory'                  ,'FinancialSettings'       ,'$LiquidityCategoryWeight'                  ,'$bfLiquidityCategoryEffectiveWeight'                  ,'$sfLiquidityCategoryEffectiveWeight'                  ,'$bfLiquidityCategoryScore'                  ,'$sfLiquidityCategoryScore'                  ,'$username','$blank'),
('ProfitabilityCategory'              ,'FinancialSettings'       ,'$ProfitabilityCategoryWeight'              ,'$bfProfitabilityCategoryEffectiveWeight'              ,'$sfProfitabilityCategoryEffectiveWeight'              ,'$bfProfitabilityCategoryScore'              ,'$sfProfitabilityCategoryScore'              ,'$username','$blank'),
('CapitalStructureCategory'           ,'FinancialSettings'       ,'$CapitalStructureCategoryWeight'           ,'$bfCapitalStructureCategoryEffectiveWeight'           ,'$sfCapitalStructureCategoryEffectiveWeight'           ,'$bfCapitalStructureCategoryScore'           ,'$sfCapitalStructureCategoryScore'           ,'$username','$blank'),
('DebtServiceCategory'                ,'FinancialSettings'       ,'$DebtServiceCategoryWeight'                ,'$bfDebtServiceCategoryEffectiveWeight'                ,'$sfDebtServiceCategoryEffectiveWeight'                ,'$bfDebtServiceCategoryScore'                ,'$sfDebtServiceCategoryScore'                ,'$username','$blank'),
('CurrentRatio'                       ,'LiquiditySettings'       ,'$CurrentRatioWeight'                       ,'$bfCurrentRatioEffectiveWeight'                       ,'$sfCurrentRatioEffectiveWeight'                       ,'$bfCurrentRatioScore'                       ,'$sfCurrentRatioScore'                       ,'$username','$blank'),
('DebtorDays'                         ,'LiquiditySettings'       ,'$DebtorDaysWeight'                         ,'$bfDebtorDaysEffectiveWeight'                         ,'$sfDebtorDaysEffectiveWeight'                         ,'$bfDebtorDaysScore'                         ,'$sfDebtorDaysScore'                         ,'$username','$blank'),
('TurnoverToWorkingCapital'           ,'LiquiditySettings'       ,'$TurnoverToWorkingCapitalWeight'           ,'$bfTurnoverToWorkingCapitalEffectiveWeight'           ,'$sfTurnoverToWorkingCapitalEffectiveWeight'           ,'$bfTurnoverToWorkingCapitalScore'           ,'$sfTurnoverToWorkingCapitalScore'           ,'$username','$blank'),
('GrossProfitMargin'                  ,'ProfitabilitySettings'   ,'$GrossProfitMarginWeight'                  ,'$bfGrossProfitMarginEffectiveWeight'                  ,'$sfGrossProfitMarginEffectiveWeight'                  ,'$bfGrossProfitMarginScore'                  ,'$sfGrossProfitMarginScore'                  ,'$username','$blank'),
('OperatingProfitMargin'              ,'ProfitabilitySettings'   ,'$OperatingProfitMarginWeight'              ,'$bfOperatingProfitMarginEffectiveWeight'              ,'$sfOperatingProfitMarginEffectiveWeight'              ,'$bfOperatingProfitMarginScore'              ,'$sfOperatingProfitMarginScore'              ,'$username','$blank'),
('TurnoverGrowth'                     ,'ProfitabilitySettings'   ,'$TurnoverGrowthWeight'                     ,'$bfTurnoverGrowthEffectiveWeight'                     ,'$sfTurnoverGrowthEffectiveWeight'                     ,'$bfTurnoverGrowthScore'                     ,'$sfTurnoverGrowthScore'                     ,'$username','$blank'),
('ReturnOnAssets'                     ,'ProfitabilitySettings'   ,'$ReturnOnAssetsWeight'                     ,'$bfReturnOnAssetsEffectiveWeight'                     ,'$sfReturnOnAssetsEffectiveWeight'                     ,'$bfReturnOnAssetsScore'                     ,'$sfReturnOnAssetsScore'                     ,'$username','$blank'),
('ReturnOnInvestments'                ,'ProfitabilitySettings'   ,'$ReturnOnInvestmentsWeight'                ,'$bfReturnOnInvestmentsEffectiveWeight'                ,'$sfReturnOnInvestmentsEffectiveWeight'                ,'$bfReturnOnInvestmentsScore'                ,'$sfReturnOnInvestmentsScore'                ,'$username','$blank'),
('DebtToEquity'                       ,'CapitalStructureSettings','$DebtToEquityWeight'                       ,'$bfDebtToEquityEffectiveWeight'                       ,'$sfDebtToEquityEffectiveWeight'                       ,'$bfDebtToEquityScore'                       ,'$sfDebtToEquityScore'                       ,'$username','$blank'),
('DebtToTangibleNetWorth'             ,'CapitalStructureSettings','$DebtToTangibleNetWorthWeight'             ,'$bfDebtToTangibleNetWorthEffectiveWeight'             ,'$sfDebtToTangibleNetWorthEffectiveWeight'             ,'$bfDebtToTangibleNetWorthScore'             ,'$sfDebtToTangibleNetWorthScore'             ,'$username','$blank'),
('ShareholdersFundsToTotalAssets'     ,'CapitalStructureSettings','$ShareholdersFundsToTotalAssetsWeight'     ,'$bfShareholdersFundsToTotalAssetsEffectiveWeight'     ,'$sfShareholdersFundsToTotalAssetsEffectiveWeight'     ,'$bfShareholdersFundsToTotalAssetsScore'     ,'$sfShareholdersFundsToTotalAssetsScore'     ,'$username','$blank'),
('InterestCover'                      ,'DebtServiceSettings'     ,'$InterestCoverWeight'                      ,'$bfInterestCoverEffectiveWeight'                      ,'$sfInterestCoverEffectiveWeight'                      ,'$bfInterestCoverScore'                      ,'$sfInterestCoverScore'                      ,'$username','$blank'),
('EBITDAToGrossIntDebts'              ,'DebtServiceSettings'     ,'$EBITDAToGrossIntDebtsWeight'              ,'$bfEBITDAToGrossIntDebtsEffectiveWeight'              ,'$sfEBITDAToGrossIntDebtsEffectiveWeight'              ,'$bfEBITDAToGrossIntDebtsScore'              ,'$sfEBITDAToGrossIntDebtsScore'              ,'$username','$blank'),
('CommitmentCategory'                 ,'ManagementAnalysis'      ,'$CommitmentCategoryWeight'                 ,'$bfCommitmentCategoryEffectiveWeight'                 ,'$sfCommitmentCategoryEffectiveWeight'                 ,'$bfCommitmentCategoryScore'                 ,'$sfCommitmentCategoryScore'                 ,'$username','$blank'),
('IntegrityCategory'                  ,'ManagementAnalysis'      ,'$IntegrityCategoryWeight'                  ,'$bfIntegrityCategoryEffectiveWeight'                  ,'$sfIntegrityCategoryEffectiveWeight'                  ,'$bfIntegrityCategoryScore'                  ,'$sfIntegrityCategoryScore'                  ,'$username','$blank'),
('InformationQualityCategory'         ,'ManagementAnalysis'      ,'$InformationQualityCategoryWeight'         ,'$bfInformationQualityCategoryEffectiveWeight'         ,'$sfInformationQualityCategoryEffectiveWeight'         ,'$bfInformationQualityCategoryScore'         ,'$sfInformationQualityCategoryScore'         ,'$username','$blank'),
('LeadershipCategory'                 ,'ManagementAnalysis'      ,'$LeadershipCategoryWeight'                 ,'$bfLeadershipCategoryEffectiveWeight'                 ,'$sfLeadershipCategoryEffectiveWeight'                 ,'$bfLeadershipCategoryScore'                 ,'$sfLeadershipCategoryScore'                 ,'$username','$blank'),
('StrategyCategory'                   ,'ManagementAnalysis'      ,'$StrategyCategoryWeight'                   ,'$bfStrategyCategoryEffectiveWeight'                   ,'$sfStrategyCategoryEffectiveWeight'                   ,'$bfStrategyCategoryScore'                   ,'$sfStrategyCategoryScore'                   ,'$username','$blank'),
('StructureCategory'                  ,'ManagementAnalysis'      ,'$StructureCategoryWeight'                  ,'$bfStructureCategoryEffectiveWeight'                  ,'$sfStructureCategoryEffectiveWeight'                  ,'$bfStructureCategoryScore'                  ,'$sfStructureCategoryScore'                  ,'$username','$blank'),
('ManagementCategory'                 ,'ManagementAnalysis'      ,'$ManagementCategoryWeight'                 ,'$bfManagementCategoryEffectiveWeight'                 ,'$sfManagementCategoryEffectiveWeight'                 ,'$bfManagementCategoryScore'                 ,'$sfManagementCategoryScore'                 ,'$username','$blank'),
('SuccessionPlanCategory'             ,'ManagementAnalysis'      ,'$SuccessionPlanCategoryWeight'             ,'$bfSuccessionPlanCategoryEffectiveWeight'             ,'$sfSuccessionPlanCategoryEffectiveWeight'             ,'$bfSuccessionPlanCategoryScore'             ,'$sfSuccessionPlanCategoryScore'             ,'$username','$blank'),
('OrganisationalDesignCategory'       ,'ManagementAnalysis'      ,'$OrganisationalDesignCategoryWeight'       ,'$bfOrganisationalDesignCategoryEffectiveWeight'       ,'$sfOrganisationalDesignCategoryEffectiveWeight'       ,'$bfOrganisationalDesignCategoryScore'       ,'$sfOrganisationalDesignCategoryScore'       ,'$username','$blank'),
('BusinessCyclicality'                ,'IndustrialAnalysis'      ,'$BusinessCyclicalityWeight'                ,'$bfBusinessCyclicalityEffectiveWeight'                ,'$sfBusinessCyclicalityEffectiveWeight'                ,'$bfBusinessCyclicalityScore'                ,'$sfBusinessCyclicalityScore'                ,'$username','$blank'),
('IndustryPerformance'                ,'IndustrialAnalysis'      ,'$IndustryPerformanceWeight'                ,'$bfIndustryPerformanceEffectiveWeight'                ,'$sfIndustryPerformanceEffectiveWeight'                ,'$bfIndustryPerformanceScore'                ,'$sfIndustryPerformanceScore'                ,'$username','$blank'),
('Porters'                            ,'IndustrialAnalysis'      ,'$PortersWeight'                            ,'$bfPortersEffectiveWeight'                            ,'$sfPortersEffectiveWeight'                            ,'$bfPortersScore'                            ,'$sfPortersScore'                            ,'$username','$blank'),
('OwnersPaidDebtExceedsDefaults'      ,'ShareholderAnalysis'     ,'$OwnersPaidDebtExceedsDefaultsWeight'      ,'$bfOwnersPaidDebtExceedsDefaultsEffectiveWeight'      ,'$sfOwnersPaidDebtExceedsDefaultsEffectiveWeight'      ,'$bfOwnersPaidDebtExceedsDefaultsScore'      ,'$sfOwnersPaidDebtExceedsDefaultsScore'      ,'$username','$blank'),
('OwnersNoOfJudgements'               ,'ShareholderAnalysis'     ,'$OwnersNoOfJudgementsWeight'               ,'$bfOwnersNoOfJudgementsEffectiveWeight'               ,'$sfOwnersNoOfJudgementsEffectiveWeight'               ,'$bfOwnersNoOfJudgementsScore'               ,'$sfOwnersNoOfJudgementsScore'               ,'$username','$blank'),
('OwnersNoOfDefaults'                 ,'ShareholderAnalysis'     ,'$OwnersNoOfDefaultsWeight'                 ,'$bfOwnersNoOfDefaultsEffectiveWeight'                 ,'$sfOwnersNoOfDefaultsEffectiveWeight'                 ,'$bfOwnersNoOfDefaultsScore'                 ,'$sfOwnersNoOfDefaultsScore'                 ,'$username','$blank'),
('OwnersNoOfTraceAlerts'              ,'ShareholderAnalysis'     ,'$OwnersNoOfTraceAlertsWeight'              ,'$bfOwnersNoOfTraceAlertsEffectiveWeight'              ,'$sfOwnersNoOfTraceAlertsEffectiveWeight'              ,'$bfOwnersNoOfTraceAlertsScore'              ,'$sfOwnersNoOfTraceAlertsScore'              ,'$username','$blank'),
('LoanRateType'                       ,'BehavioralAnalysis'      ,'$LoanRateTypeWeight'                       ,'$bfLoanRateTypeEffectiveWeight'                       ,'$sfLoanRateTypeEffectiveWeight'                       ,'$bfLoanRateTypeScore'                       ,'$sfLoanRateTypeScore'                       ,'$username','$blank'),
('LoanMaturity'                       ,'BehavioralAnalysis'      ,'$LoanMaturityWeight'                       ,'$bfLoanMaturityEffectiveWeight'                       ,'$sfLoanMaturityEffectiveWeight'                       ,'$bfLoanMaturityScore'                       ,'$sfLoanMaturityScore'                       ,'$username','$blank'),
('BBSBankingRelationshipYears'        ,'BehavioralAnalysis'      ,'$BBSBankingRelationshipYearsWeight'        ,'$bfBBSBankingRelationshipYearsEffectiveWeight'        ,'$sfBBSBankingRelationshipYearsEffectiveWeight'        ,'$bfBBSBankingRelationshipYearsScore'        ,'$sfBBSBankingRelationshipYearsScore'        ,'$username','$blank'),
('BBSBankingProductsNo'               ,'BehavioralAnalysis'      ,'$BBSBankingProductsNoWeight'               ,'$bfBBSBankingProductsNoEffectiveWeight'               ,'$sfBBSBankingProductsNoEffectiveWeight'               ,'$bfBBSBankingProductsNoScore'               ,'$sfBBSBankingProductsNoScore'               ,'$username','$blank'),
('PastYearArrearIncidentsNo'          ,'BehavioralAnalysis'      ,'$PastYearArrearIncidentsNoWeight'          ,'$bfPastYearArrearIncidentsNoEffectiveWeight'          ,'$sfPastYearArrearIncidentsNoEffectiveWeight'          ,'$bfPastYearArrearIncidentsNoScore'          ,'$sfPastYearArrearIncidentsNoScore'          ,'$username','$blank'),
('Past2YearsArrearLoansRenegotiatedNo','BehavioralAnalysis'      ,'$Past2YearsArrearLoansRenegotiatedNoWeight','$bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight','$sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight','$bfPast2YearsArrearLoansRenegotiatedNoScore','$sfPast2YearsArrearLoansRenegotiatedNoScore','$username','$blank'),
('PaidDebtExceedsDefaults'            ,'BehavioralAnalysis'      ,'$PaidDebtExceedsDefaultsWeight'            ,'$bfPaidDebtExceedsDefaultsEffectiveWeight'            ,'$sfPaidDebtExceedsDefaultsEffectiveWeight'            ,'$bfPaidDebtExceedsDefaultsScore'            ,'$sfPaidDebtExceedsDefaultsScore'            ,'$username','$blank'),
('NoOfJudgements'                     ,'BehavioralAnalysis'      ,'$NoOfJudgementsWeight'                     ,'$bfNoOfJudgementsEffectiveWeight'                     ,'$sfNoOfJudgementsEffectiveWeight'                     ,'$bfNoOfJudgementsScore'                     ,'$sfNoOfJudgementsScore'                     ,'$username','$blank'),
('NoOfDefaults'                       ,'BehavioralAnalysis'      ,'$NoOfDefaultsWeight'                       ,'$bfNoOfDefaultsEffectiveWeight'                       ,'$sfNoOfDefaultsEffectiveWeight'                       ,'$bfNoOfDefaultsScore'                       ,'$sfNoOfDefaultsScore'                       ,'$username','$blank'),
('NoOfTraceAlerts'                    ,'BehavioralAnalysis'      ,'$NoOfTraceAlertsWeight'                    ,'$bfNoOfTraceAlertsEffectiveWeight'                    ,'$sfNoOfTraceAlertsEffectiveWeight'                    ,'$bfNoOfTraceAlertsScore'                    ,'$sfNoOfTraceAlertsScore'                    ,'$username','$blank')";
	
	
	
	
 
        $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting data'. mysqli_error();
          }
        else
		  {
            header("Location:index.php");
            exit;		     		 
     		echo 'Data successfully saved';
	      }
		  
 ?>
</body>

</html>