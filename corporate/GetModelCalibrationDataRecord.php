<?php
 	  
	
		switch ($row["charecteristic"])
		{
		   
		   case "bfFinancialAnalysis":
		       $bfFinancialAnalysis 		= $row["weight_in_table"];
			   $bfFinancialAnalysisScore 	= $row["big_firm_max_score"];
		       break;
		   case "bfManagementAnalysis":
		       $bfManagementAnalysis 		= $row["weight_in_table"];
			   $bfManagementAnalysisScore 	= $row["big_firm_max_score"];
		       break;
		   case "bfIndustryAnalysis":
		       $bfIndustryAnalysis 			= $row["weight_in_table"];
			   $bfIndustryAnalysisScore 	= $row["big_firm_max_score"];
		       break;
		   case "bfShareholderAnalysis":
		       $bfShareholderAnalysis 		= $row["weight_in_table"];
			   $bfShareholderAnalysisScore 	= $row["big_firm_max_score"];
		       break;
		   case "bfBehavioralAnalysis":
		       $bfBehavioralAnalysis 		= $row["weight_in_table"];
			   $bfBehavioralAnalysisScore 	= $row["big_firm_max_score"];
		       break;
	       case "TotalScore":
			   $bfTotalScore                = $row["big_firm_max_score"];
			   $sfTotalScore                = $row["small_firm_max_score"];
			  break;  
	  	   case "sfFinancialAnalysis":
		       $sfFinancialAnalysis 		= $row["weight_in_table"];
			   $sfFinancialAnalysisScore 	= $row["small_firm_max_score"];
		       break;
		   case "sfManagementAnalysis":
 		       $sfManagementAnalysis 		= $row["weight_in_table"];
			   $sfManagementAnalysisScore 	= $row["small_firm_max_score"];
               break;		   
		   case "sfIndustryAnalysis":
  		       $sfIndustryAnalysis 			= $row["weight_in_table"];
			   $sfIndustryAnalysisScore 	= $row["small_firm_max_score"];
               break;		   
 	       case "sfShareholderAnalysis":
  		       $sfShareholderAnalysis 	    = $row["weight_in_table"];
			   $sfShareholderScore 			= $row["small_firm_max_score"];
               break;		   
           case "sfBehavioralAnalysis":
		       $sfBehavioralAnalysis 		= $row["weight_in_table"];
			   $sfBehavioralAnalysisScore 	= $row["big_firm_max_score"];
		       break;
	      case "TurnoverThreshold":
               $TurnoverThreshold           = $row["weight_in_table"];
		       break;
		   case "RatioWeightYear1":
               $RatioWeightYear1            = $row["weight_in_table"];
		       break;
		   case "RatioWeightYear2":
               $RatioWeightYear2            = $row["weight_in_table"];
		       break;
  		   case "RatioWeightYear3":
               $RatioWeightYear3            = $row["weight_in_table"];
		       break;
		   case "update_notes":
               $update_notes                =$row["update_notes"]; 
		       break;
		   case "LiquidityCategory":
               $LiquidityCategoryWeight     							=$row["weight_in_table"];
               $bfLiquidityCategoryEffectiveWeight     					=$row["big_firm_effective_weight"];		       
               $sfLiquidityCategoryEffectiveWeight     					=$row["small_firm_effective_weight"];		       
               $bfLiquidityCategoryScore     							=$row["big_firm_max_score"];		       
               $sfLiquidityCategoryScore     							=$row["small_firm_max_score"];		       
			   break;
		   case "ProfitabilityCategory":
               $ProfitabilityCategoryWeight     						=$row["weight_in_table"];
               $bfProfitabilityCategoryEffectiveWeight     				=$row["big_firm_effective_weight"];		       
               $sfProfitabilityCategoryEffectiveWeight     				=$row["small_firm_effective_weight"];		       
               $bfProfitabilityCategoryScore     						=$row["big_firm_max_score"];		       
               $sfProfitabilityCategoryScore     						=$row["small_firm_max_score"];		       
			   break;
		   case "CapitalStructureCategory":
               $CapitalStructureCategoryWeight     						=$row["weight_in_table"];
               $bfCapitalStructureCategoryEffectiveWeight   			=$row["big_firm_effective_weight"];		       
               $sfCapitalStructureCategoryEffectiveWeight   			=$row["small_firm_effective_weight"];		       
               $bfCapitalStructureCategoryScore     					=$row["big_firm_max_score"];		       
               $sfCapitalStructureCategoryScore     					=$row["small_firm_max_score"];		       
			   break;
		   case "DebtServiceCategory":
               $DebtServiceCategoryWeight     							=$row["weight_in_table"];
               $bfDebtServiceCategoryEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfDebtServiceCategoryEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfDebtServiceCategoryScore     							=$row["big_firm_max_score"];		       
               $sfDebtServiceCategoryScore								=$row["small_firm_max_score"];		       
			   break;
		   case "CurrentRatio":
              $CurrentRatioWeight     									=$row["weight_in_table"];
               $bfCurrentRatioEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfCurrentRatioEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfCurrentRatioScore     								=$row["big_firm_max_score"];		       
               $sfCurrentRatioScore										=$row["small_firm_max_score"];		       
			   break;
		   case "DebtorDays":
               $DebtorDaysWeight     									=$row["weight_in_table"];
               $bfDebtorDaysEffectiveWeight								=$row["big_firm_effective_weight"];		       
               $sfDebtorDaysEffectiveWeight								=$row["small_firm_effective_weight"];		       
               $bfDebtorDaysScore     									=$row["big_firm_max_score"];		       
               $sfDebtorDaysScore										=$row["small_firm_max_score"];		       
			   break;
		   case "TurnoverToWorkingCapital":
               $TurnoverToWorkingCapitalWeight     						=$row["weight_in_table"];
               $bfTurnoverToWorkingCapitalEffectiveWeight				=$row["big_firm_effective_weight"];		       
               $sfTurnoverToWorkingCapitalEffectiveWeight				=$row["small_firm_effective_weight"];		       
               $bfTurnoverToWorkingCapitalScore     					=$row["big_firm_max_score"];		       
               $sfTurnoverToWorkingCapitalScore							=$row["small_firm_max_score"];		       
			   break;
	
		   case "GrossProfitMargin":
               $GrossProfitMarginWeight     							=$row["weight_in_table"];
               $bfGrossProfitMarginEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfGrossProfitMarginEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfGrossProfitMarginScore     							=$row["big_firm_max_score"];		       
               $sfGrossProfitMarginScore								=$row["small_firm_max_score"];		       
			   break;
		   case "OperatingProfitMargin":
               $OperatingProfitMarginWeight     						=$row["weight_in_table"];
               $bfOperatingProfitMarginEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfOperatingProfitMarginEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfOperatingProfitMarginScore     						=$row["big_firm_max_score"];		       
               $sfOperatingProfitMarginScore							=$row["small_firm_max_score"];		       
			   break;
			   case "TurnoverGrowth":
               $TurnoverGrowthWeight     								=$row["weight_in_table"];
               $bfTurnoverGrowthEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfTurnoverGrowthEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfTurnoverGrowthScore     								=$row["big_firm_max_score"];		       
               $sfTurnoverGrowthScore									=$row["small_firm_max_score"];		       
			   break;
		   case "ReturnOnAssets":
               $ReturnOnAssetsWeight     								=$row["weight_in_table"];
               $bfReturnOnAssetsEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfReturnOnAssetsEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfReturnOnAssetsScore     								=$row["big_firm_max_score"];		       
               $sfReturnOnAssetsScore									=$row["small_firm_max_score"];		       
			   break;
	       case "ReturnOnInvestments":
               $ReturnOnInvestmentsWeight     									=$row["weight_in_table"];
               $bfReturnOnInvestmentsEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfReturnOnInvestmentsEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfReturnOnInvestmentsScore     							=$row["big_firm_max_score"];		       
               $sfReturnOnInvestmentsScore								=$row["small_firm_max_score"];		       
			   break;
		   case "DebtToEquity":
               $DebtToEquityWeight     									=$row["weight_in_table"];
               $bfDebtToEquityEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfDebtToEquityEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfDebtToEquityScore     								=$row["big_firm_max_score"];		       
               $sfDebtToEquityScore										=$row["small_firm_max_score"];		       
			   break;
	       case "DebtToTangibleNetWorth":
               $DebtToTangibleNetWorthWeight     						=$row["weight_in_table"];
               $bfDebtToTangibleNetWorthEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfDebtToTangibleNetWorthEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfDebtToTangibleNetWorthScore     						=$row["big_firm_max_score"];		       
               $sfDebtToTangibleNetWorthScore							=$row["small_firm_max_score"];		       
			   break;
		   case "ShareholdersFundsToTotalAssets":
               $ShareholdersFundsToTotalAssetsWeight     				=$row["weight_in_table"];
               $bfShareholdersFundsToTotalAssetsEffectiveWeight			=$row["big_firm_effective_weight"];		       
               $sfShareholdersFundsToTotalAssetsEffectiveWeight			=$row["small_firm_effective_weight"];		       
               $bfShareholdersFundsToTotalAssetsScore     				=$row["big_firm_max_score"];		       
               $sfShareholdersFundsToTotalAssetsScore					=$row["small_firm_max_score"];		       
			   break;
		   case "InterestCover":
               $InterestCoverWeight     								=$row["weight_in_table"];
               $bfInterestCoverEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfInterestCoverEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfInterestCoverScore     								=$row["big_firm_max_score"];		       
               $sfInterestCoverScore									=$row["small_firm_max_score"];		       
			   break;
		   case "EBITDAToGrossIntDebts":
               $EBITDAToGrossIntDebtsWeight     						=$row["weight_in_table"];
               $bfEBITDAToGrossIntDebtsEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfEBITDAToGrossIntDebtsEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfEBITDAToGrossIntDebtsScore     						=$row["big_firm_max_score"];		       
               $sfEBITDAToGrossIntDebtsScore							=$row["small_firm_max_score"];		       
			   break;
		   case "CommitmentCategory":
               $CommitmentCategoryWeight     							=$row["weight_in_table"];
               $bfCommitmentCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfCommitmentCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfCommitmentCategoryScore     							=$row["big_firm_max_score"];		       
               $sfCommitmentCategoryScore								=$row["small_firm_max_score"];		       
			   break;
		    case "IntegrityCategory":
               $IntegrityCategoryWeight     							=$row["weight_in_table"];
               $bfIntegrityCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfIntegrityCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfIntegrityCategoryScore     							=$row["big_firm_max_score"];		       
               $sfIntegrityCategoryScore								=$row["small_firm_max_score"];		       
			   break;
		   case "InformationQualityCategory":
               $InformationQualityCategoryWeight     					=$row["weight_in_table"];
               $bfInformationQualityCategoryEffectiveWeight				=$row["big_firm_effective_weight"];		       
               $sfInformationQualityCategoryEffectiveWeight				=$row["small_firm_effective_weight"];		       
               $bfInformationQualityCategoryScore     					=$row["big_firm_max_score"];		       
               $sfInformationQualityCategoryScore						=$row["small_firm_max_score"];		       
			   break;
		   case "LeadershipCategory":
               $LeadershipCategoryWeight     							=$row["weight_in_table"];
               $bfLeadershipCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfLeadershipCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfLeadershipCategoryScore     							=$row["big_firm_max_score"];		       
               $sfLeadershipCategoryScore								=$row["small_firm_max_score"];		       
			   break;
		   case "StrategyCategory":
               $StrategyCategoryWeight     								=$row["weight_in_table"];
               $bfStrategyCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfStrategyCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfStrategyCategoryScore     							=$row["big_firm_max_score"];		       
               $sfStrategyCategoryScore									=$row["small_firm_max_score"];		       
			   break;
		   case "StructureCategory":
               $StructureCategoryWeight     							=$row["weight_in_table"];
               $bfStructureCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfStructureCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfStructureCategoryScore     							=$row["big_firm_max_score"];		       
               $sfStructureCategoryScore								=$row["small_firm_max_score"];		       
			   break;
	  	   case "ManagementCategory":
               $ManagementCategoryWeight     							=$row["weight_in_table"];
               $bfManagementCategoryEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfManagementCategoryEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfManagementCategoryScore     							=$row["big_firm_max_score"];		       
               $sfManagementCategoryScore								=$row["small_firm_max_score"];		       
			   break;
	 	   case "SuccessionPlanCategory":
               $SuccessionPlanCategoryWeight     					=$row["weight_in_table"];
               $bfSuccessionPlanCategoryEffectiveWeight				=$row["big_firm_effective_weight"];		       
               $sfSuccessionPlanCategoryEffectiveWeight				=$row["small_firm_effective_weight"];		       
               $bfSuccessionPlanCategoryScore     					=$row["big_firm_max_score"];		       
               $sfSuccessionPlanCategoryScore						=$row["small_firm_max_score"];		       
			   break;
		   case "OrganisationalDesignCategory":
               $OrganisationalDesignCategoryWeight     					=$row["weight_in_table"];
               $bfOrganisationalDesignCategoryEffectiveWeight			=$row["big_firm_effective_weight"];		       
               $sfOrganisationalDesignCategoryEffectiveWeight			=$row["small_firm_effective_weight"];		       
               $bfOrganisationalDesignCategoryScore     				=$row["big_firm_max_score"];		       
               $sfOrganisationalDesignCategoryScore						=$row["small_firm_max_score"];		       
			   break;
		   case "BusinessCyclicality":
               $BusinessCyclicalityWeight     							=$row["weight_in_table"];
               $bfBusinessCyclicalityEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfBusinessCyclicalityEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfBusinessCyclicalityScore     							=$row["big_firm_max_score"];		       
               $sfBusinessCyclicalityScore								=$row["small_firm_max_score"];		       
			   break;
		   case "IndustryPerformance":
               $IndustryPerformanceWeight     							=$row["weight_in_table"];
               $bfIndustryPerformanceEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfIndustryPerformanceEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfIndustryPerformanceScore     							=$row["big_firm_max_score"];		       
               $sfIndustryPerformanceScore								=$row["small_firm_max_score"];		       
			   break;
		   case "Porters":
               $PortersWeight     										=$row["weight_in_table"];
               $bfPortersEffectiveWeight								=$row["big_firm_effective_weight"];		       
               $sfPortersEffectiveWeight								=$row["small_firm_effective_weight"];		       
               $bfPortersScore     										=$row["big_firm_max_score"];		       
               $sfPortersScore											=$row["small_firm_max_score"];		       
			   break;
		   case "OwnersPaidDebtExceedsDefaults":
               $OwnersPaidDebtExceedsDefaultsWeight     				=$row["weight_in_table"];
               $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight			=$row["big_firm_effective_weight"];		       
               $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight			=$row["small_firm_effective_weight"];		       
               $bfOwnersPaidDebtExceedsDefaultsScore     				=$row["big_firm_max_score"];		       
               $sfOwnersPaidDebtExceedsDefaultsScore					=$row["small_firm_max_score"];		       
			   break;
		   case "OwnersNoOfJudgements":
              $OwnersNoOfJudgementsWeight     							=$row["weight_in_table"];
               $bfOwnersNoOfJudgementsEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfOwnersNoOfJudgementsEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfOwnersNoOfJudgementsScore     						=$row["big_firm_max_score"];		       
               $sfOwnersNoOfJudgementsScore								=$row["small_firm_max_score"];		       
			   break;
		   case "OwnersNoOfDefaults":
               $OwnersNoOfDefaultsWeight     							=$row["weight_in_table"];
               $bfOwnersNoOfDefaultsEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfOwnersNoOfDefaultsEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfOwnersNoOfDefaultsScore     							=$row["big_firm_max_score"];		       
               $sfOwnersNoOfDefaultsScore								=$row["small_firm_max_score"];		       
			   break;
		   case "OwnersNoOfTraceAlerts":
               $OwnersNoOfTraceAlertsWeight     						=$row["weight_in_table"];
               $bfOwnersNoOfTraceAlertsEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfOwnersNoOfTraceAlertsEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfOwnersNoOfTraceAlertsScore     						=$row["big_firm_max_score"];		       
               $sfOwnersNoOfTraceAlertsScore							=$row["small_firm_max_score"];		       
			   break;
		   case "LoanRateType";
               $LoanRateTypeWeight     									=$row["weight_in_table"];
               $bfLoanRateTypeEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfLoanRateTypeEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfLoanRateTypeScore     								=$row["big_firm_max_score"];		       
               $sfLoanRateTypeScore										=$row["small_firm_max_score"];		       
			   break;
		   case "LoanMaturity":
               $LoanMaturityWeight     									=$row["weight_in_table"];
               $bfLoanMaturityEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfLoanMaturityEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfLoanMaturityScore     								=$row["big_firm_max_score"];		       
               $sfLoanMaturityScore										=$row["small_firm_max_score"];		       
			   break;
	  	   case "BBSBankingRelationshipYears":
               $BBSBankingRelationshipYearsWeight     					=$row["weight_in_table"];
               $bfBBSBankingRelationshipYearsEffectiveWeight			=$row["big_firm_effective_weight"];		       
               $sfBBSBankingRelationshipYearsEffectiveWeight			=$row["small_firm_effective_weight"];		       
               $bfBBSBankingRelationshipYearsScore     					=$row["big_firm_max_score"];		       
               $sfBBSBankingRelationshipYearsScore						=$row["small_firm_max_score"];		       
			   break;
		   case "BBSBankingProductsNo":
               $BBSBankingProductsNoWeight     							=$row["weight_in_table"];
               $bfBBSBankingProductsNoEffectiveWeight					=$row["big_firm_effective_weight"];		       
               $sfBBSBankingProductsNoEffectiveWeight					=$row["small_firm_effective_weight"];		       
               $bfBBSBankingProductsNoScore     						=$row["big_firm_max_score"];		       
               $sfBBSBankingProductsNoScore								=$row["small_firm_max_score"];		       
			   break;
		   case "PastYearArrearIncidentsNo":
               $PastYearArrearIncidentsNoWeight     					=$row["weight_in_table"];
               $bfPastYearArrearIncidentsNoEffectiveWeight				=$row["big_firm_effective_weight"];		       
               $sfPastYearArrearIncidentsNoEffectiveWeight				=$row["small_firm_effective_weight"];		       
               $bfPastYearArrearIncidentsNoScore     					=$row["big_firm_max_score"];		       
               $sfPastYearArrearIncidentsNoScore						=$row["small_firm_max_score"];		       
			   break;
		   case "Past2YearsArrearLoansRenegotiatedNo":
               $Past2YearsArrearLoansRenegotiatedNoWeight     			=$row["weight_in_table"];
               $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight	=$row["big_firm_effective_weight"];		       
               $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight	=$row["small_firm_effective_weight"];		       
               $bfPast2YearsArrearLoansRenegotiatedNoScore     			=$row["big_firm_max_score"];		       
               $sfPast2YearsArrearLoansRenegotiatedNoScore				=$row["small_firm_max_score"];		       
			   break;
		   case "PaidDebtExceedsDefaults":
               $PaidDebtExceedsDefaultsWeight     						=$row["weight_in_table"];
               $bfPaidDebtExceedsDefaultsEffectiveWeight				=$row["big_firm_effective_weight"];		       
               $sfPaidDebtExceedsDefaultsEffectiveWeight				=$row["small_firm_effective_weight"];		       
               $bfPaidDebtExceedsDefaultsScore     		     			=$row["big_firm_max_score"];		       
               $sfPaidDebtExceedsDefaultsScore							=$row["small_firm_max_score"];		       
			   break;
			   
		   case "NoOfJudgements":
               $NoOfJudgementsWeight     								=$row["weight_in_table"];
               $bfNoOfJudgementsEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfNoOfJudgementsEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfNoOfJudgementsScore     								=$row["big_firm_max_score"];		       
               $sfNoOfJudgementsScore									=$row["small_firm_max_score"];		       
			   break;
		   case "NoOfDefaults":
               $NoOfDefaultsWeight     									=$row["weight_in_table"];
               $bfNoOfDefaultsEffectiveWeight							=$row["big_firm_effective_weight"];		       
               $sfNoOfDefaultsEffectiveWeight							=$row["small_firm_effective_weight"];		       
               $bfNoOfDefaultsScore     								=$row["big_firm_max_score"];		       
               $sfNoOfDefaultsScore										=$row["small_firm_max_score"];		       
			   break;
	  	   case "NoOfTraceAlerts":
               $NoOfTraceAlertsWeight     								=$row["weight_in_table"];
               $bfNoOfTraceAlertsEffectiveWeight						=$row["big_firm_effective_weight"];		       
               $sfNoOfTraceAlertsEffectiveWeight						=$row["small_firm_effective_weight"];		       
               $bfNoOfTraceAlertsScore     								=$row["big_firm_max_score"];		       
               $sfNoOfTraceAlertsScore									=$row["small_firm_max_score"];		       
			   break;
	  	   case "PDParameter1":
               $PDLowerLimitPercentage1     						    =$row["weight_in_table"];
               $PDMaxScore1     								        =$row["big_firm_max_score"];		       
               $PDMinScore1									            =$row["small_firm_max_score"];		       
               $PDPercentage1                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter2":
               $PDLowerLimitPercentage2     						    =$row["weight_in_table"];
               $PDMaxScore2     								        =$row["big_firm_max_score"];		       
               $PDMinScore2									            =$row["small_firm_max_score"];		       
               $PDPercentage2                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter3":
               $PDLowerLimitPercentage3     						    =$row["weight_in_table"];
               $PDMaxScore3     								        =$row["big_firm_max_score"];		       
               $PDMinScore3									            =$row["small_firm_max_score"];		       
               $PDPercentage3                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter4":
               $PDLowerLimitPercentage4     						    =$row["weight_in_table"];
               $PDMaxScore4     								        =$row["big_firm_max_score"];		       
               $PDMinScore4									            =$row["small_firm_max_score"];		       
               $PDPercentage4                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter5":
               $PDLowerLimitPercentage5     						    =$row["weight_in_table"];
               $PDMaxScore5     								        =$row["big_firm_max_score"];		       
               $PDMinScore5									            =$row["small_firm_max_score"];		       
               $PDPercentage5                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter6":
               $PDLowerLimitPercentage6     						    =$row["weight_in_table"];
               $PDMaxScore6     								        =$row["big_firm_max_score"];		       
               $PDMinScore6									            =$row["small_firm_max_score"];		       
               $PDPercentage6                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter7":
               $PDLowerLimitPercentage7     						    =$row["weight_in_table"];
               $PDMaxScore7     								        =$row["big_firm_max_score"];		       
               $PDMinScore7									            =$row["small_firm_max_score"];		       
               $PDPercentage7                                           =$row["big_firm_effective_weight"];
               break;
	  	   case "PDParameter8":
               $PDLowerLimitPercentage8     						    =$row["weight_in_table"];
               $PDMaxScore8     								        =$row["big_firm_max_score"];		       
               $PDMinScore8									            =$row["small_firm_max_score"];		       
               $PDPercentage8                                           =$row["big_firm_effective_weight"];
               break;
   		}	
    
     
?>
