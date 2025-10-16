<script>
  
  //include 'LoadModelCalibrationVars.php';
	//localStorage.bfFinancialAnalysis = "<?php echo $bfFinancialAnalysis; ?>" ;
	//alert (localStorage.bfFinancialAnalysis);		
      
	          console.log ("Username at start of Loading Model Settings = "+localStorage.username);
			  
			  console.log ("Starting Local Storage Assignment");             
		       // Saving Big Firms Data
			  localStorage.bfFinancialAnalysis = "<?php echo $bfFinancialAnalysis; ?>" ;
		  
			
			  localStorage.bfManagementAnalysis = "<?php echo $bfManagementAnalysis; ?>" ;
              localStorage.bfIndustryAnalysis = "<?php echo $bfIndustryAnalysis; ?>" ;
			  localStorage.bfShareholderAnalysis = "<?php echo $bfShareholderAnalysis; ?>" ;
 		  	  localStorage.bfBehavioralAnalysis = "<?php echo $bfBehavioralAnalysis; ?>" ;
	
			// Saving Small Firms Data
			  localStorage.sfFinancialAnalysis = "<?php echo $sfFinancialAnalysis; ?>" ;
			  localStorage.sfManagementAnalysis = "<?php echo $sfManagementAnalysis; ?>" ;
			  localStorage.sfIndustryAnalysis = "<?php echo $sfIndustryAnalysis; ?>" ;
			  localStorage.sfShareholderAnalysis = "<?php echo $sfShareholderAnalysis; ?>" ;
			  localStorage.sfBehavioralAnalysis = "<?php echo $sfBehavioralAnalysis; ?>" ;
			  localStorage.bfTotalScore = "<?php echo $bfTotalScore; ?>" ;
			  localStorage.sfTotalScore = "<?php echo $sfTotalScore; ?>" ;
			 
			  console.log ("Starting Local Storage Assignment - Financial Analysis");
			  
			  //FINANCIAL ANALYSIS - WEIGHTS=======================================================================
			  //Saving Financial Analysis Category Weights  
			  localStorage.LiquidityCategoryWeight = "<?php echo $LiquidityCategoryWeight; ?>" ;
		      localStorage.ProfitabilityCategoryWeight = "<?php echo $ProfitabilityCategoryWeight; ?>" ;
			  localStorage.CapitalStructureCategoryWeight = "<?php echo $CapitalStructureCategoryWeight; ?>" ;
			  localStorage.DebtServiceCategoryWeight = "<?php echo $DebtServiceCategoryWeight; ?>" ;
			  //Saving Liquidity Metrics
			  localStorage.CurrentRatioWeight = "<?php echo $CurrentRatioWeight; ?>" ;
		      localStorage.DebtorDaysWeight = "<?php echo $DebtorDaysWeight; ?>" ;
		      localStorage.TurnoverToWorkingCapitalWeight = "<?php echo $TurnoverToWorkingCapitalWeight; ?>" ;
            //Saving Profitability Metrics
		      localStorage.GrossProfitMarginWeight = "<?php echo $GrossProfitMarginWeight; ?>" ;
	          localStorage.OperatingProfitMarginWeight = "<?php echo $OperatingProfitMarginWeight; ?>" ;
	          localStorage.TurnoverGrowthWeight = "<?php echo $TurnoverGrowthWeight; ?>" ;
		      localStorage.ReturnOnAssetsWeight = "<?php echo $ReturnOnAssetsWeight; ?>" ;
	          localStorage.ReturnOnInvestmentsWeight = "<?php echo $ReturnOnInvestmentsWeight; ?>" ;
              //Saving Capital Structure Metrics
			  localStorage.DebtToEquityWeight = "<?php echo $DebtToEquityWeight; ?>" ;
		      localStorage.DebtToTangibleNetWorthWeight = "<?php echo $DebtToTangibleNetWorthWeight; ?>" ;
		 	  localStorage.ShareholdersFundsToTotalAssetsWeight = "<?php echo $ShareholdersFundsToTotalAssetsWeight; ?>" ;  
              //Saving Debt Service Metrics 
			  localStorage.InterestCoverWeight = "<?php echo $InterestCoverWeight; ?>" ;
		      localStorage.EBITDAToGrossIntDebtsWeight = "<?php echo $EBITDAToGrossIntDebtsWeight; ?>" ;  
 			  
			  //===========================================================================================================
			  //FINANCIAL ANALYSIS - SCORE FOR BIG FIRMS
			  //Saving Financial Analysis Category Weights  
			  localStorage.bfLiquidityCategoryScore = "<?php echo $bfLiquidityCategoryScore; ?>" ;
		      localStorage.bfProfitabilityCategoryScore = "<?php echo $bfProfitabilityCategoryScore; ?>" ;
			  localStorage.bfCapitalStructureCategoryScore = "<?php echo $bfCapitalStructureCategoryScore; ?>" ;
			  localStorage.bfDebtServiceCategoryScore = "<?php echo $bfDebtServiceCategoryScore; ?>" ;
	
            	//Saving Liquidity Metrics
			  localStorage.bfCurrentRatioScore = "<?php echo $bfCurrentRatioScore; ?>" ;
		      localStorage.bfDebtorDaysScore = "<?php echo $bfDebtorDaysScore; ?>" ;
		      localStorage.bfTurnoverToWorkingCapitalScore = "<?php echo $bfTurnoverToWorkingCapitalScore; ?>" ;
            //Saving Profitability Metrics
		      localStorage.bfGrossProfitMarginScore = "<?php echo $bfGrossProfitMarginScore; ?>" ;
	          localStorage.bfOperatingProfitMarginScore = "<?php echo $bfOperatingProfitMarginScore; ?>" ;
	          localStorage.bfTurnoverGrowthScore = "<?php echo $bfTurnoverGrowthScore; ?>" ;
		      localStorage.bfReturnOnAssetsScore = "<?php echo $bfReturnOnAssetsScore; ?>" ;
	          localStorage.bfReturnOnInvestmentsScore = "<?php echo $bfReturnOnInvestmentsScore; ?>" ;
              //Saving Capital Structure Metrics
			  localStorage.bfDebtToEquityScore = "<?php echo $bfDebtToEquityScore; ?>" ;
		      localStorage.bfDebtToTangibleNetWorthScore = "<?php echo $bfDebtToTangibleNetWorthScore; ?>" ;
		 	  localStorage.bfShareholdersFundsToTotalAssetsScore = "<?php echo $bfShareholdersFundsToTotalAssetsScore; ?>" ;  
              //Saving Debt Service Metrics 
			  localStorage.bfInterestCoverScore = "<?php echo $bfInterestCoverScore; ?>" ;
		      localStorage.bfEBITDAToGrossIntDebtsScore = "<?php echo $bfEBITDAToGrossIntDebtsScore; ?>" ;  
 			  //===========================================================================================================
		     //===========================================================================================================
			  //FINANCIAL ANALYSIS - SCORE FOR SMALL FIRMS
			  //Saving Financial Analysis Category Weights  
			  localStorage.sfLiquidityCategoryScore = "<?php echo $sfLiquidityCategoryScore; ?>" ;
		      localStorage.sfProfitabilityCategoryScore = "<?php echo $sfProfitabilityCategoryScore; ?>" ;
			  localStorage.sfCapitalStructureCategoryScore = "<?php echo $sfCapitalStructureCategoryScore; ?>" ;
			  localStorage.sfDebtServiceCategoryScore = "<?php echo $sfDebtServiceCategoryScore; ?>" ;
	
            	//Saving Liquidity Metrics
			  localStorage.sfCurrentRatioScore = "<?php echo $sfCurrentRatioScore; ?>" ;
		      localStorage.sfDebtorDaysScore = "<?php echo $sfDebtorDaysScore; ?>" ;
		      localStorage.sfTurnoverToWorkingCapitalScore = "<?php echo $sfTurnoverToWorkingCapitalScore; ?>" ;
            //Saving Profitability Metrics
		      localStorage.sfGrossProfitMarginScore = "<?php echo $sfGrossProfitMarginScore; ?>" ;
	          localStorage.sfOperatingProfitMarginScore = "<?php echo $sfOperatingProfitMarginScore; ?>" ;
	          localStorage.sfTurnoverGrowthScore = "<?php echo $sfTurnoverGrowthScore; ?>" ;
		      localStorage.sfReturnOnAssetsScore = "<?php echo $sfReturnOnAssetsScore; ?>" ;
	          localStorage.sfReturnOnInvestmentsScore = "<?php echo $sfReturnOnInvestmentsScore; ?>" ;
              //Saving Capital Structure Metrics
			  localStorage.sfDebtToEquityScore = "<?php echo $sfDebtToEquityScore; ?>" ;
		      localStorage.sfDebtToTangibleNetWorthScore = "<?php echo $sfDebtToTangibleNetWorthScore; ?>" ;
		 	  localStorage.sfShareholdersFundsToTotalAssetsScore = "<?php echo $sfShareholdersFundsToTotalAssetsScore; ?>" ;  
              //Saving Debt Service Metrics 
			  localStorage.sfInterestCoverScore = "<?php echo $sfInterestCoverScore; ?>" ;
		      localStorage.sfEBITDAToGrossIntDebtsScore = "<?php echo $sfEBITDAToGrossIntDebtsScore; ?>" ;  
 			  //===========================================================================================================
			 
			 //Saving Turnover Threshold and Ratio Weights
			  localStorage.TurnoverThreshold = "<?php echo $TurnoverThreshold; ?>" ;  
        	  localStorage.RatioWeightYear1 = "<?php echo $RatioWeightYear1; ?>" ;  
       	      localStorage.RatioWeightYear2 = "<?php echo $RatioWeightYear2; ?>" ;  
       	      localStorage.RatioWeightYear3 = "<?php echo $RatioWeightYear3; ?>" ;  
             //alert successfull save operation
		      
			 console.log ("Starting Local Storage Assignment - Management Analysis");
			
	
  			 //SAVING MANAGEMENT ANALYSIS CATEGORY WEIGHTS================================================================= 
			 //1. Sub Group Weights
			 localStorage.CommitmentCategoryWeight = "<?php echo $CommitmentCategoryWeight; ?>" ;
		     localStorage.IntegrityCategoryWeight = "<?php echo $IntegrityCategoryWeight; ?>" ;
		
			 localStorage.InformationQualityCategoryWeight = "<?php echo $InformationQualityCategoryWeight; ?>" ;
			 localStorage.LeadershipCategoryWeight = "<?php echo $LeadershipCategoryWeight; ?>" ;
		     localStorage.StrategyCategoryWeight = "<?php echo $StrategyCategoryWeight; ?>" ;

		     localStorage.StructureCategoryWeight = "<?php echo $StructureCategoryWeight; ?>" ;	
		     localStorage.ManagementCategoryWeight = "<?php echo $ManagementCategoryWeight; ?>" ;	
			 localStorage.SuccessionPlanCategoryWeight = "<?php echo $SuccessionPlanCategoryWeight; ?>" ;
            	
		     localStorage.OrganisationalDesignCategoryWeight = "<?php echo $OrganisationalDesignCategoryWeight; ?>" ;
			  //2. Effective Weights
			  //a.Big Firms
			  localStorage.bfCommitmentCategoryEffectiveWeight = "<?php echo $bfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.bfIntegrityCategoryEffectiveWeight = "<?php echo $bfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.bfInformationQualityCategoryEffectiveWeight = "<?php echo $bfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.bfLeadershipCategoryEffectiveWeight = "<?php echo $bfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.bfStrategyCategoryEffectiveWeight = "<?php echo $bfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.bfStructureCategoryEffectiveWeight = "<?php echo $bfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.bfManagementCategoryEffectiveWeight = "<?php echo $bfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.bfSuccessionPlanCategoryEffectiveWeight = "<?php echo $bfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.bfOrganisationalDesignCategoryEffectiveWeight = "<?php echo $bfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
 			  //b.Big Firms
	 	  	  localStorage.sfCommitmentCategoryEffectiveWeight = "<?php echo $sfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.sfIntegrityCategoryEffectiveWeight = "<?php echo $sfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.sfInformationQualityCategoryEffectiveWeight = "<?php echo $sfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.sfLeadershipCategoryEffectiveWeight = "<?php echo $sfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.sfStrategyCategoryEffectiveWeight = "<?php echo $sfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.sfStructureCategoryEffectiveWeight = "<?php echo $sfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.sfManagementCategoryEffectiveWeight = "<?php echo $sfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.sfSuccessionPlanCategoryEffectiveWeight = "<?php echo $sfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.sfOrganisationalDesignCategoryEffectiveWeight = "<?php echo $sfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
		  
			  //2. SCORES - Management Analysis============================================================================
			  //a.Big Firms
			  localStorage.bfCommitmentCategoryScore = "<?php echo $bfCommitmentCategoryScore; ?>" ;
		      localStorage.bfIntegrityCategoryScore = "<?php echo $bfIntegrityCategoryScore; ?>" ;
			  localStorage.bfInformationQualityCategoryScore = "<?php echo $bfInformationQualityCategoryScore; ?>" ;
			  localStorage.bfLeadershipCategoryScore = "<?php echo $bfLeadershipCategoryScore; ?>" ;
			  localStorage.bfStrategyCategoryScore = "<?php echo $bfStrategyCategoryScore; ?>" ;
			  localStorage.bfStructureCategoryScore = "<?php echo $bfStructureCategoryScore; ?>" ;
			  localStorage.bfManagementCategoryScore = "<?php echo $bfManagementCategoryScore; ?>" ;
			  localStorage.bfSuccessionPlanCategoryScore = "<?php echo $bfSuccessionPlanCategoryScore; ?>" ;
			  localStorage.bfOrganisationalDesignCategoryScore = "<?php echo $bfOrganisationalDesignCategoryScore; ?>" ;
 			  //b.Big Firms
	 	  	  localStorage.sfCommitmentCategoryScore = "<?php echo $sfCommitmentCategoryScore; ?>" ;
		      localStorage.sfIntegrityCategoryScore = "<?php echo $sfIntegrityCategoryScore; ?>" ;
			  localStorage.sfInformationQualityCategoryScore = "<?php echo $sfInformationQualityCategoryScore; ?>" ;
			  localStorage.sfLeadershipCategoryScore = "<?php echo $sfLeadershipCategoryScore; ?>" ;
			  localStorage.sfStrategyCategoryScore = "<?php echo $sfStrategyCategoryScore; ?>" ;
			  localStorage.sfStructureCategoryScore = "<?php echo $sfStructureCategoryScore; ?>" ;
			  localStorage.sfManagementCategoryScore = "<?php echo $sfManagementCategoryScore; ?>" ;
			  localStorage.sfSuccessionPlanCategoryScore = "<?php echo $sfSuccessionPlanCategoryScore; ?>" ;
			  localStorage.sfOrganisationalDesignCategoryScore = "<?php echo $sfOrganisationalDesignCategoryScore; ?>" ;
			  
		      console.log ("Starting Local Storage Assignment - Industry Analysis");
			  
	   	 	      	  	 
				
			 //SAVING INDUSTRY ANALYSIS METRICS==========================================================================
			  //1. Sub Group Weights
			  localStorage.BusinessCyclicalityWeight = "<?php echo $BusinessCyclicalityWeight; ?>" ;
			  localStorage.IndustryPerformanceWeight = "<?php echo $IndustryPerformanceWeight; ?>" ;
		      localStorage.PortersWeight = "<?php echo $PortersWeight; ?>" ;
              //2. Effective Weights
			  //Big Firms  
			  localStorage.bfBusinessCyclicalityEffectiveWeight = "<?php echo $bfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.bfIndustryPerformanceEffectiveWeight = "<?php echo $bfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.bfPortersEffectiveWeight = "<?php echo $bfPortersEffectiveWeight; ?>" ;
 			 //Small Firms
			  localStorage.sfBusinessCyclicalityEffectiveWeight = "<?php echo $sfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.sfIndustryPerformanceEffectiveWeight = "<?php echo $sfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.sfPortersEffectiveWeight = "<?php echo $sfPortersEffectiveWeight; ?>" ;
 		      
              //3. SCORES - Industry Analysis==========================================================================
			  //Big Firms  
			  localStorage.bfBusinessCyclicalityScore = "<?php echo $bfBusinessCyclicalityScore; ?>" ;
		      localStorage.bfIndustryPerformanceScore = "<?php echo $bfIndustryPerformanceScore; ?>" ;
		      localStorage.bfPortersScore = "<?php echo $bfPortersScore; ?>" ;
 			 //Small Firms
			  localStorage.sfBusinessCyclicalityScore = "<?php echo $sfBusinessCyclicalityScore; ?>" ;
		      localStorage.sfIndustryPerformanceScore = "<?php echo $sfIndustryPerformanceScore; ?>" ;
		      localStorage.sfPortersScore = "<?php echo $sfPortersScore; ?>" ;
 	             			  	  
		      console.log ("Starting Local Storage Assignment - Shareholder Analysis");
			  		
			  
			  //SAVING SHAREHOLDER ANALYSIS METRICS
			  //1. Sub Group Weights
			  localStorage.OwnersPaidDebtExceedsDefaultsWeight = "<?php echo $OwnersPaidDebtExceedsDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsWeight = "<?php echo $OwnersNoOfJudgementsWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsWeight = "<?php echo $OwnersNoOfDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsWeight = "<?php echo $OwnersNoOfTraceAlertsWeight; ?>" ;  
              //2. Effective Weights
 			  //Big Firms
			  localStorage.bfOwnersPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.bfOwnersNoOfJudgementsEffectiveWeight = "<?php echo $bfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.bfOwnersNoOfDefaultsEffectiveWeight = "<?php echo $bfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.bfOwnersNoOfTraceAlertsEffectiveWeight = "<?php echo $bfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;  
              //Small Firms
			  localStorage.sfOwnersPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.sfOwnersNoOfJudgementsEffectiveWeight = "<?php echo $sfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.sfOwnersNoOfDefaultsEffectiveWeight = "<?php echo $sfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.sfOwnersNoOfTraceAlertsEffectiveWeight = "<?php echo $sfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;
             //3. SCORE -Shareholder Analysis
 			  //Big Firms
			  localStorage.bfOwnersPaidDebtExceedsDefaultsScore = "<?php echo $bfOwnersPaidDebtExceedsDefaultsScore; ?>" ;
		      localStorage.bfOwnersNoOfJudgementsScore = "<?php echo $bfOwnersNoOfJudgementsScore; ?>" ;
		      localStorage.bfOwnersNoOfDefaultsScore = "<?php echo $bfOwnersNoOfDefaultsScore; ?>" ;
		      localStorage.bfOwnersNoOfTraceAlertsScore = "<?php echo $bfOwnersNoOfTraceAlertsScore; ?>" ;  
              //Small Firms
			  localStorage.sfOwnersPaidDebtExceedsDefaultsScore = "<?php echo $sfOwnersPaidDebtExceedsDefaultsScore; ?>" ;
		      localStorage.sfOwnersNoOfJudgementsScore = "<?php echo $sfOwnersNoOfJudgementsScore; ?>" ;
		      localStorage.sfOwnersNoOfDefaultsScore = "<?php echo $sfOwnersNoOfDefaultsScore; ?>" ;
		      localStorage.sfOwnersNoOfTraceAlertsScore = "<?php echo $sfOwnersNoOfTraceAlertsScore; ?>" ;
           
			  //SAVING BEHAVIORAL AND PRODUCTS ANALYSIS
			  //1. Sub Group Weights
			  localStorage.LoanRateTypeWeight = "<?php echo $LoanRateTypeWeight; ?>" ;
			  localStorage.LoanMaturityWeight = "<?php echo $LoanMaturityWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsWeight = "<?php echo $BBSBankingRelationshipYearsWeight; ?>" ;
			  localStorage.BBSBankingProductsNoWeight = "<?php echo $BBSBankingProductsNoWeight; ?>" ;
			  
			  localStorage.PastYearArrearIncidentsNoWeight = "<?php echo $PastYearArrearIncidentsNoWeight; ?>" ;
			  
			  localStorage.Past2YearsArrearLoansRenegotiatedNoWeight = "<?php echo $Past2YearsArrearLoansRenegotiatedNoWeight; ?>" ;
			  
			  localStorage.PaidDebtExceedsDefaultsWeight = "<?php echo $PaidDebtExceedsDefaultsWeight; ?>" ;

		      localStorage.NoOfJudgementsWeight = "<?php echo $NoOfJudgementsWeight; ?>" ;
		      localStorage.NoOfDefaultsWeight = "<?php echo $NoOfDefaultsWeight; ?>" ;

		      localStorage.NoOfTraceAlertsWeight = "<?php echo $NoOfTraceAlertsWeight; ?>" ;  
             
			  //2. Effective Weights
			  //Big Firms
			  localStorage.bfLoanRateTypeEffectiveWeight = "<?php echo $bfLoanRateTypeEffectiveWeight; ?>" ;

			  localStorage.bfLoanMaturityEffectiveWeight = "<?php echo $bfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.bfBBSBankingRelationshipYearsEffectiveWeight = "<?php echo $bfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;

			  localStorage.bfBBSBankingProductsNoEffectiveWeight = "<?php echo $bfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.bfPastYearArrearIncidentsNoEffectiveWeight = "<?php echo $bfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<?php echo $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;

			  localStorage.bfPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $bfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;		      
			  localStorage.bfNoOfJudgementsEffectiveWeight = "<?php echo $bfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.bfNoOfDefaultsEffectiveWeight = "<?php echo $bfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.bfNoOfTraceAlertsEffectiveWeight = "<?php echo $bfNoOfTraceAlertsEffectiveWeight; ?>" ;  
   			  
			  //Small Firms
			  localStorage.sfLoanRateTypeEffectiveWeight = "<?php echo $sfLoanRateTypeEffectiveWeight; ?>" ;
			  localStorage.sfLoanMaturityEffectiveWeight = "<?php echo $sfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.sfBBSBankingRelationshipYearsEffectiveWeight = "<?php echo $sfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;
			  localStorage.sfBBSBankingProductsNoEffectiveWeight = "<?php echo $sfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.sfPastYearArrearIncidentsNoEffectiveWeight = "<?php echo $sfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<?php echo $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;
			  localStorage.sfPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $sfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.sfNoOfJudgementsEffectiveWeight = "<?php echo $sfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.sfNoOfDefaultsEffectiveWeight = "<?php echo $sfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.sfNoOfTraceAlertsEffectiveWeight = "<?php echo $sfNoOfTraceAlertsEffectiveWeight; ?>" ;  
  
			 //3. SCORES - Behavioral Analysis		  
			 
			  //Big Firms
			  localStorage.bfLoanRateTypeScore = "<?php echo $bfLoanRateTypeScore; ?>" ;
			  localStorage.bfLoanMaturityScore = "<?php echo $bfLoanMaturityScore; ?>" ;
			  localStorage.bfBBSBankingRelationshipYearsScore = "<?php echo $bfBBSBankingRelationshipYearsScore; ?>" ;

			  localStorage.bfBBSBankingProductsNoScore = "<?php echo $bfBBSBankingProductsNoScore; ?>" ;
			  localStorage.bfPastYearArrearIncidentsNoScore = "<?php echo $bfPastYearArrearIncidentsNoScore; ?>" ;
			  localStorage.bfPast2YearsArrearLoansRenegotiatedNoScore = "<?php echo $bfPast2YearsArrearLoansRenegotiatedNoScore; ?>" ;

			  localStorage.bfPaidDebtExceedsDefaultsScore = "<?php echo $bfPaidDebtExceedsDefaultsScore; ?>" ;		      
			  localStorage.bfNoOfJudgementsScore = "<?php echo $bfNoOfJudgementsScore; ?>" ;
		      localStorage.bfNoOfDefaultsScore = "<?php echo $bfNoOfDefaultsScore; ?>" ;
		      localStorage.bfNoOfTraceAlertsScore = "<?php echo $bfNoOfTraceAlertsScore; ?>" ;  
   			  
			  //Small Firms
			  localStorage.sfLoanRateTypeScore = "<?php echo $sfLoanRateTypeScore; ?>" ;
			  localStorage.sfLoanMaturityScore = "<?php echo $sfLoanMaturityScore; ?>" ;
			  localStorage.sfBBSBankingRelationshipYearsScore = "<?php echo $sfBBSBankingRelationshipYearsScore; ?>" ;
			  localStorage.sfBBSBankingProductsNoScore = "<?php echo $sfBBSBankingProductsNoScore; ?>" ;
			  localStorage.sfPastYearArrearIncidentsNoScore = "<?php echo $sfPastYearArrearIncidentsNoScore; ?>" ;
			  localStorage.sfPast2YearsArrearLoansRenegotiatedNoScore = "<?php echo $sfPast2YearsArrearLoansRenegotiatedNoScore; ?>" ;
			  localStorage.sfPaidDebtExceedsDefaultsScore = "<?php echo $sfPaidDebtExceedsDefaultsScore; ?>" ;
		      localStorage.sfNoOfJudgementsScore = "<?php echo $sfNoOfJudgementsScore; ?>" ;
		      localStorage.sfNoOfDefaultsScore = "<?php echo $sfNoOfDefaultsScore; ?>" ;
		      localStorage.sfNoOfTraceAlertsScore = "<?php echo $sfNoOfTraceAlertsScore; ?>" ;  
              
			  //PD Lower Limit Percentages
			  localStorage.PDLowerLimitPercentage1 = "<?php echo $PDLowerLimitPercentage1; ?>";
			  localStorage.PDLowerLimitPercentage2 = "<?php echo $PDLowerLimitPercentage2; ?>";
			  localStorage.PDLowerLimitPercentage3 = "<?php echo $PDLowerLimitPercentage3; ?>";
			  localStorage.PDLowerLimitPercentage4 = "<?php echo $PDLowerLimitPercentage4; ?>";
			  localStorage.PDLowerLimitPercentage5 = "<?php echo $PDLowerLimitPercentage5; ?>";
			  localStorage.PDLowerLimitPercentage6 = "<?php echo $PDLowerLimitPercentage6; ?>";
			  localStorage.PDLowerLimitPercentage7 = "<?php echo $PDLowerLimitPercentage7; ?>";
			  localStorage.PDLowerLimitPercentage8 = "<?php echo $PDLowerLimitPercentage8; ?>";
			  //PD Percentages
			  localStorage.PDPercentage1           = "<?php echo $PDPercentage1; ?>";
			  localStorage.PDPercentage2           = "<?php echo $PDPercentage2; ?>";
			  localStorage.PDPercentage3           = "<?php echo $PDPercentage3; ?>";
			  localStorage.PDPercentage4           = "<?php echo $PDPercentage4; ?>";
			  localStorage.PDPercentage5           = "<?php echo $PDPercentage5; ?>";
			  localStorage.PDPercentage6           = "<?php echo $PDPercentage6; ?>";
			  localStorage.PDPercentage7           = "<?php echo $PDPercentage7; ?>";
			  localStorage.PDPercentage8           = "<?php echo $PDPercentage8; ?>";
			  //PD Max Scores
			  localStorage.PDMaxScore1             = "<?php echo $PDMaxScore1; ?>";
			  localStorage.PDMaxScore2             = "<?php echo $PDMaxScore2; ?>";
			  localStorage.PDMaxScore3             = "<?php echo $PDMaxScore3; ?>";
			  localStorage.PDMaxScore4             = "<?php echo $PDMaxScore4; ?>";
			  localStorage.PDMaxScore5             = "<?php echo $PDMaxScore5; ?>";
			  localStorage.PDMaxScore6             = "<?php echo $PDMaxScore6; ?>";
			  localStorage.PDMaxScore7             = "<?php echo $PDMaxScore7; ?>";
			  localStorage.PDMaxScore8             = "<?php echo $PDMaxScore8; ?>";
			  //PD Min Scores
			  localStorage.PDMinScore1             = "<?php echo $PDMinScore1; ?>";
			  localStorage.PDMinScore2             = "<?php echo $PDMinScore2; ?>";
			  localStorage.PDMinScore3             = "<?php echo $PDMinScore3; ?>";
			  localStorage.PDMinScore4             = "<?php echo $PDMinScore4; ?>";
			  localStorage.PDMinScore5             = "<?php echo $PDMinScore5; ?>";
			  localStorage.PDMinScore6             = "<?php echo $PDMinScore6; ?>";
			  localStorage.PDMinScore7             = "<?php echo $PDMinScore7; ?>";
			  localStorage.PDMinScore8             = "<?php echo $PDMinScore8; ?>";
			  
			  
			  
			  console.log ("Finished Local Storage Assignment - Model Settings");
			  
			  alert ("Finished loading Model Scoring Settings - Press OK to Continue");
              
</script>


 
