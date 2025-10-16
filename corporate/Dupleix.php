header('Content-Type', 'text/javascript');

          function Load_Model_Settings_FromDbase_Into_LocalStorage()

		  {		  
	          console.log ("Starting Local Storage Assignment");             
				localStorage.bf = "<?php echo $bfFinancialAnalysis; ?>" ;
                alert(localStorage.bf);
			 // Saving Big Firms Data
			  localStorage.bfFinancialAnalysis = "<?php echo $bfFinancialAnalysis; ?>" ;
			  localStorage.bfManagementAnalysis = "<?php echo $bfManagementAnalysis; ?>" ;
			  localStorage.bfIndustryAnalysis = "<?php echo $bfIndustryAnalysis; ?>" ;
			  localStorage.bfShareholderAnalysis = "<?php echo $bfShareholderAnalysis; ?>" ;
			  localStorage.bfBehavioralAnalysis = "<?php echo $bfBehavioralAnalysis; ?>" ;
		      
			   alert (localStorage.bfShareholderAnalysis);
			  // Saving Small Firms Data
			  localStorage.sfFinancialAnalysis = "<?php echo $sfFinancialAnalysis; ?>" ;
			  localStorage.sfManagementAnalysis = "<?php echo $sfManagementAnalysis; ?>" ;
			  localStorage.sfIndustryAnalysis = "<?php echo $sfIndustryAnalysis; ?>" ;
			  localStorage.sfShareholderAnalysis = "<?php echo $sfShareholderAnalysis; ?>" ;
			  localStorage.sfBehavioralAnalysis = "<?php echo $sfBehavioralAnalysis; ?>" ;
			  localStorage.bfTotalScore = "<?php echo $bfTotalScore; ?>" ;
			  localStorage.sfTotalScore = "<?php echo $sfTotalScore; ?>" ;
			  
			  console.log ("Starting Local Storage Assignment - Financial Analysis");
			  
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
 			  //Saving Turnover Threshold and Ratio Weights
			  localStorage.TurnoverThreshold = "<?php echo $TurnoverThreshold; ?>" ;  
        	  localStorage.RatioWeightYear1 = "<?php echo $RatioWeightYear1; ?>" ;  
       	      localStorage.RatioWeightYear2 = "<?php echo $RatioWeightYear2; ?>" ;  
       	      localStorage.RatioWeightYear3 = "<?php echo $RatioWeightYear3; ?>" ;  
             //alert successfull save operation
		      
			  
			  
			  console.log ("Starting Local Storage Assignment - Management Analysis");
			  
	
  			  //SAVING MANAGEMENT ANALYSIS CATEGORY WEIGHTS 
			  //1. Sub Group Weights
			  localStorage.CommitmentCategoryWeight = "<?php echo $CommitmentCategoryWeight; ?>" ;
		      localStorage.IntegrityCategoryWeight = "<?php echo $IntegrityCategoryWeight; ?>" ;
			  localStorage.InformationQualityCategoryWeight = "<?php echo $InformationQualityCategoryWeight; ?>" ;
			  localStorage.LeadershipCategoryWeight = "<?php echo $LeadershipCategoryWeight; ?>" ;
			  localStorage.StrategyCategoryWeight = "<?php echo $StrategyCategoryWeight; ?>" ;
			  localStorage.StructureCategoryWeight = "<?php echo $StructureCategoryWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryWeight = "<?php echo $SuccessionPlanCategoryWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryWeight = "<?php echo $OrganisationalDesignCategoryWeight; ?>" ;
              //2. Effective Weights
			  
			  //1.Big Firms
			  localStorage.CommitmentCategoryEffectiveWeight = "<?php echo $bfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.IntegrityCategoryEffectiveWeight = "<?php echo $bfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.InformationQualityCategoryEffectiveWeight = "<?php echo $bfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.LeadershipCategoryEffectiveWeight = "<?php echo $bfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.StrategyCategoryEffectiveWeight = "<?php echo $bfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.StructureCategoryEffectiveWeight = "<?php echo $bfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.ManagementCategoryEffectiveWeight = "<?php echo $bfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryEffectiveWeight = "<?php echo $bfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryEffectiveWeight = "<?php echo $bfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
 			  //2.Big Firms
			  localStorage.CommitmentCategoryEffectiveWeight = "<?php echo $sfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.IntegrityCategoryEffectiveWeight = "<?php echo $sfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.InformationQualityCategoryEffectiveWeight = "<?php echo $sfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.LeadershipCategoryEffectiveWeight = "<?php echo $sfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.StrategyCategoryEffectiveWeight = "<?php echo $sfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.StructureCategoryEffectiveWeight = "<?php echo $sfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.ManagementCategoryEffectiveWeight = "<?php echo $sfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryEffectiveWeight = "<?php echo $sfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryEffectiveWeight = "<?php echo $sfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
		  
		  
		      console.log ("Starting Local Storage Assignment - Industry Analysis");
			  
	   	  			
			 //SAVING INDUSTRY ANALYSIS METRICS
			  //1. Sub Group Weights
			  localStorage.BusinessCyclicalityWeight = "<?php echo $BusinessCyclicalityWeight; ?>" ;
			  localStorage.IndustryPerformanceWeight = "<?php echo $IndustryPerformanceWeight; ?>" ;
		      localStorage.PortersWeight = "<?php echo $PortersWeight; ?>" ;
              //2. Effective Weights
			  //Big Firms
			  localStorage.BusinessCyclicalityEffectiveWeight = "<?php echo $bfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.IndustryPerformanceEffectiveWeight = "<?php echo $bfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.PortersEffectiveWeight = "<?php echo $bfPortersEffectiveWeight; ?>" ;
 			 //Small Firms
			  localStorage.BusinessCyclicalityEffectiveWeight = "<?php echo $sfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.IndustryPerformanceEffectiveWeight = "<?php echo $sfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.PortersEffectiveWeight = "<?php echo $sfPortersEffectiveWeight; ?>" ;
 		  
		  
		      console.log ("Starting Local Storage Assignment - Shareholder Analysis");
			  
				  
			  //SAVING SHAREHOLDER ANALYSIS METRICS
			  //1. Sub Group Weights
			  localStorage.OwnersPaidDebtExceedsDefaultsWeight = "<?php echo $OwnersPaidDebtExceedsDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsWeight = "<?php echo $OwnersNoOfJudgementsWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsWeight = "<?php echo $OwnersNoOfDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsWeight = "<?php echo $OwnersNoOfTraceAlertsWeight; ?>" ;  
              //2. Effective Weights
 			  //Big Firms
			  localStorage.OwnersPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsEffectiveWeight = "<?php echo $bfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsEffectiveWeight = "<?php echo $bfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsEffectiveWeight = "<?php echo $bfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;  
              //Small Firms
			  localStorage.OwnersPaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsEffectiveWeight = "<?php echo $sfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsEffectiveWeight = "<?php echo $sfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsEffectiveWeight = "<?php echo $sfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;
           
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
			  localStorage.LoanRateTypeEffectiveWeight = "<?php echo $bfLoanRateTypeEffectiveWeight; ?>" ;
			  localStorage.LoanMaturityEffectiveWeight = "<?php echo $bfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsEffectiveWeight = "<?php echo $bfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;
			  localStorage.BBSBankingProductsNoEffectiveWeight = "<?php echo $bfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.PastYearArrearIncidentsNoEffectiveWeight = "<?php echo $bfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.Past2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<?php echo $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;
			  localStorage.PaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $bfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfJudgementsEffectiveWeight = "<?php echo $bfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.NoOfDefaultsEffectiveWeight = "<?php echo $bfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfTraceAlertsEffectiveWeight = "<?php echo $bfNoOfTraceAlertsEffectiveWeight; ?>" ;  
   			  
			  //Small Firms
			  localStorage.LoanRateTypeEffectiveWeight = "<?php echo $sfLoanRateTypeEffectiveWeight; ?>" ;
			  localStorage.LoanMaturityEffectiveWeight = "<?php echo $sfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsEffectiveWeight = "<?php echo $sfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;
			  localStorage.BBSBankingProductsNoEffectiveWeight = "<?php echo $sfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.PastYearArrearIncidentsNoEffectiveWeight = "<?php echo $sfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.Past2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<?php echo $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;
			  localStorage.PaidDebtExceedsDefaultsEffectiveWeight = "<?php echo $sfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfJudgementsEffectiveWeight = "<?php echo $sfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.NoOfDefaultsEffectiveWeight = "<?php echo $sfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfTraceAlertsEffectiveWeight = "<?php echo $sfNoOfTraceAlertsEffectiveWeight; ?>" ;  
  
              console.log ("Finished Local Storage Assignment - Model Settings");
			  
		  }	  
 
 
