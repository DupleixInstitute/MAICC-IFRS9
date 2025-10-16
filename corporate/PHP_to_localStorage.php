
          function Load_Model_Settings_FromDbase_Into_LocalStorage()

		  {		  
	          console.log ("Starting Local Storage Assignment");             
			
			 // Saving Big Firms Data
			  localStorage.bfFinancialAnalysis = "<php? echo $bfFinancialAnalysis; ?>" ;
			  localStorage.bfManagementAnalysis = "<php? echo $bfManagementAnalysis; ?>" ;
			  localStorage.bfIndustryAnalysis = "<php? echo $bfIndustryAnalysis; ?>" ;
			  localStorage.bfShareholderAnalysis = "<php? echo $bfShareholderAnalysis; ?>" ;
			  localStorage.bfBehavioralAnalysis = "<php? echo $bfBehavioralAnalysis; ?>" ;
		      
			 
			  // Saving Small Firms Data
			  localStorage.sfFinancialAnalysis = "<php? echo $sfFinancialAnalysis; ?>" ;
			  localStorage.sfManagementAnalysis = "<php? echo $sfManagementAnalysis; ?>" ;
			  localStorage.sfIndustryAnalysis = "<php? echo $sfIndustryAnalysis; ?>" ;
			  localStorage.sfShareholderAnalysis = "<php? echo $sfShareholderAnalysis; ?>" ;
			  localStorage.sfBehavioralAnalysis = "<php? echo $sfBehavioralAnalysis; ?>" ;
			  localStorage.bfTotalScore = "<php? echo $bfTotalScore; ?>" ;
			  localStorage.sfTotalScore = "<php? echo $sfTotalScore; ?>" ;
			  
			  console.log ("Starting Local Storage Assignment - Financial Analysis");
			  
			  //Saving Financial Analysis Category Weights  
			  localStorage.LiquidityCategoryWeight = "<php? echo $LiquidityCategoryWeight; ?>" ;
		      localStorage.ProfitabilityCategoryWeight = "<php? echo $ProfitabilityCategoryWeight; ?>" ;
			  localStorage.CapitalStructureCategoryWeight = "<php? echo $CapitalStructureCategoryWeight; ?>" ;
			  localStorage.DebtServiceCategoryWeight = "<php? echo $DebtServiceCategoryWeight; ?>" ;
			  
			  //Saving Liquidity Metrics
			  localStorage.CurrentRatioWeight = "<php? echo $CurrentRatioWeight; ?>" ;
		      localStorage.DebtorDaysWeight = "<php? echo $DebtorDaysWeight; ?>" ;
		      localStorage.TurnoverToWorkingCapitalWeight = "<php? echo $TurnoverToWorkingCapitalWeight; ?>" ;
            //Saving Profitability Metrics
			  localStorage.GrossProfitMarginWeight = "<php? echo $GrossProfitMarginWeight; ?>" ;
		      localStorage.OperatingProfitMarginWeight = "<php? echo $OperatingProfitMarginWeight; ?>" ;
		      localStorage.TurnoverGrowthWeight = "<php? echo $TurnoverGrowthWeight; ?>" ;
		      localStorage.ReturnOnAssetsWeight = "<php? echo $ReturnOnAssetsWeight; ?>" ;
		      localStorage.ReturnOnInvestmentsWeight = "<php? echo $ReturnOnInvestmentsWeight; ?>" ;
              //Saving Capital Structure Metrics
			  localStorage.DebtToEquityWeight = "<php? echo $DebtToEquityWeight; ?>" ;
		      localStorage.DebtToTangibleNetWorthWeight = "<php? echo $DebtToTangibleNetWorthWeight; ?>" ;
		 	  localStorage.ShareholdersFundsToTotalAssetsWeight = "<php? echo $ShareholdersFundsToTotalAssetsWeight; ?>" ;  
              //Saving Debt Service Metrics 
			  localStorage.InterestCoverWeight = "<php? echo $InterestCoverWeight; ?>" ;
		      localStorage.EBITDAToGrossIntDebtsWeight = "<php? echo $EBITDAToGrossIntDebtsWeight; ?>" ;  
 			  //Saving Turnover Threshold and Ratio Weights
			  localStorage.TurnoverThreshold = "<php? echo $TurnoverThreshold; ?>" ;  
        	  localStorage.RatioWeightYear1 = "<php? echo $RatioWeightYear1; ?>" ;  
       	      localStorage.RatioWeightYear2 = "<php? echo $RatioWeightYear2; ?>" ;  
       	      localStorage.RatioWeightYear3 = "<php? echo $RatioWeightYear3; ?>" ;  
             //alert successfull save operation
		      
			  
			  
			  console.log ("Starting Local Storage Assignment - Management Analysis");
			  
	
  			  //SAVING MANAGEMENT ANALYSIS CATEGORY WEIGHTS 
			  //1. Sub Group Weights
			  localStorage.CommitmentCategoryWeight = "<php? echo $CommitmentCategoryWeight; ?>" ;
		      localStorage.IntegrityCategoryWeight = "<php? echo $IntegrityCategoryWeight; ?>" ;
			  localStorage.InformationQualityCategoryWeight = "<php? echo $InformationQualityCategoryWeight; ?>" ;
			  localStorage.LeadershipCategoryWeight = "<php? echo $LeadershipCategoryWeight; ?>" ;
			  localStorage.StrategyCategoryWeight = "<php? echo $StrategyCategoryWeight; ?>" ;
			  localStorage.StructureCategoryWeight = "<php? echo $StructureCategoryWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryWeight = "<php? echo $SuccessionPlanCategoryWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryWeight = "<php? echo $OrganisationalDesignCategoryWeight; ?>" ;
              //2. Effective Weights
			  
			  //1.Big Firms
			  localStorage.CommitmentCategoryEffectiveWeight = "<php? echo $bfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.IntegrityCategoryEffectiveWeight = "<php? echo $bfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.InformationQualityCategoryEffectiveWeight = "<php? echo $bfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.LeadershipCategoryEffectiveWeight = "<php? echo $bfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.StrategyCategoryEffectiveWeight = "<php? echo $bfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.StructureCategoryEffectiveWeight = "<php? echo $bfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.ManagementCategoryEffectiveWeight = "<php? echo $bfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryEffectiveWeight = "<php? echo $bfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryEffectiveWeight = "<php? echo $bfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
 			  //2.Big Firms
			  localStorage.CommitmentCategoryEffectiveWeight = "<php? echo $sfCommitmentCategoryEffectiveWeight; ?>" ;
		      localStorage.IntegrityCategoryEffectiveWeight = "<php? echo $sfIntegrityCategoryEffectiveWeight; ?>" ;
			  localStorage.InformationQualityCategoryEffectiveWeight = "<php? echo $sfInformationQualityCategoryEffectiveWeight; ?>" ;
			  localStorage.LeadershipCategoryEffectiveWeight = "<php? echo $sfLeadershipCategoryEffectiveWeight; ?>" ;
			  localStorage.StrategyCategoryEffectiveWeight = "<php? echo $sfStrategyCategoryEffectiveWeight; ?>" ;
			  localStorage.StructureCategoryEffectiveWeight = "<php? echo $sfStructureCategoryEffectiveWeight; ?>" ;
			  localStorage.ManagementCategoryEffectiveWeight = "<php? echo $sfManagementCategoryEffectiveWeight; ?>" ;
			  localStorage.SuccessionPlanCategoryEffectiveWeight = "<php? echo $sfSuccessionPlanCategoryEffectiveWeight; ?>" ;
			  localStorage.OrganisationalDesignCategoryEffectiveWeight = "<php? echo $sfOrganisationalDesignCategoryEffectiveWeight; ?>" ;
		  
		  
		      console.log ("Starting Local Storage Assignment - Industry Analysis");
			  
	   	  			
			 //SAVING INDUSTRY ANALYSIS METRICS
			  //1. Sub Group Weights
			  localStorage.BusinessCyclicalityWeight = "<php? echo $BusinessCyclicalityWeight; ?>" ;
			  localStorage.IndustryPerformanceWeight = "<php? echo $IndustryPerformanceWeight; ?>" ;
		      localStorage.PortersWeight = "<php? echo $PortersWeight; ?>" ;
              //2. Effective Weights
			  //Big Firms
			  localStorage.BusinessCyclicalityEffectiveWeight = "<php? echo $bfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.IndustryPerformanceEffectiveWeight = "<php? echo $bfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.PortersEffectiveWeight = "<php? echo $bfPortersEffectiveWeight; ?>" ;
 			 //Small Firms
			  localStorage.BusinessCyclicalityEffectiveWeight = "<php? echo $sfBusinessCyclicalityEffectiveWeight; ?>" ;
		      localStorage.IndustryPerformanceEffectiveWeight = "<php? echo $sfIndustryPerformanceEffectiveWeight; ?>" ;
		      localStorage.PortersEffectiveWeight = "<php? echo $sfPortersEffectiveWeight; ?>" ;
 		  
		  
		      console.log ("Starting Local Storage Assignment - Shareholder Analysis");
			  
				  
			  //SAVING SHAREHOLDER ANALYSIS METRICS
			  //1. Sub Group Weights
			  localStorage.OwnersPaidDebtExceedsDefaultsWeight = "<php? echo $OwnersPaidDebtExceedsDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsWeight = "<php? echo $OwnersNoOfJudgementsWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsWeight = "<php? echo $OwnersNoOfDefaultsWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsWeight = "<php? echo $OwnersNoOfTraceAlertsWeight; ?>" ;  
              //2. Effective Weights
 			  //Big Firms
			  localStorage.OwnersPaidDebtExceedsDefaultsEffectiveWeight = "<php? echo $bfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsEffectiveWeight = "<php? echo $bfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsEffectiveWeight = "<php? echo $bfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsEffectiveWeight = "<php? echo $bfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;  
              //Small Firms
			  localStorage.OwnersPaidDebtExceedsDefaultsEffectiveWeight = "<php? echo $sfOwnersPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfJudgementsEffectiveWeight = "<php? echo $sfOwnersNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfDefaultsEffectiveWeight = "<php? echo $sfOwnersNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.OwnersNoOfTraceAlertsEffectiveWeight = "<php? echo $sfOwnersNoOfTraceAlertsEffectiveWeight; ?>" ;
           
			  //SAVING BEHAVIORAL AND PRODUCTS ANALYSIS
			  //1. Sub Group Weights
			  localStorage.LoanRateTypeWeight = "<php? echo $LoanRateTypeWeight; ?>" ;
			  localStorage.LoanMaturityWeight = "<php? echo $LoanMaturityWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsWeight = "<php? echo $BBSBankingRelationshipYearsWeight; ?>" ;
			  localStorage.BBSBankingProductsNoWeight = "<php? echo $BBSBankingProductsNoWeight; ?>" ;
			  localStorage.PastYearArrearIncidentsNoWeight = "<php? echo $PastYearArrearIncidentsNoWeight; ?>" ;
			  localStorage.Past2YearsArrearLoansRenegotiatedNoWeight = "<php? echo $Past2YearsArrearLoansRenegotiatedNoWeight; ?>" ;
			  localStorage.PaidDebtExceedsDefaultsWeight = "<php? echo $PaidDebtExceedsDefaultsWeight; ?>" ;
		      localStorage.NoOfJudgementsWeight = "<php? echo $NoOfJudgementsWeight; ?>" ;
		      localStorage.NoOfDefaultsWeight = "<php? echo $NoOfDefaultsWeight; ?>" ;
		      localStorage.NoOfTraceAlertsWeight = "<php? echo $NoOfTraceAlertsWeight; ?>" ;  
              //2. Effective Weights
			  //Big Firms
			  localStorage.LoanRateTypeEffectiveWeight = "<php? echo $bfLoanRateTypeEffectiveWeight; ?>" ;
			  localStorage.LoanMaturityEffectiveWeight = "<php? echo $bfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsEffectiveWeight = "<php? echo $bfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;
			  localStorage.BBSBankingProductsNoEffectiveWeight = "<php? echo $bfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.PastYearArrearIncidentsNoEffectiveWeight = "<php? echo $bfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.Past2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<php? echo $bfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;
			  localStorage.PaidDebtExceedsDefaultsEffectiveWeight = "<php? echo $bfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfJudgementsEffectiveWeight = "<php? echo $bfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.NoOfDefaultsEffectiveWeight = "<php? echo $bfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfTraceAlertsEffectiveWeight = "<php? echo $bfNoOfTraceAlertsEffectiveWeight; ?>" ;  
   			  
			  //Small Firms
			  localStorage.LoanRateTypeEffectiveWeight = "<php? echo $sfLoanRateTypeEffectiveWeight; ?>" ;
			  localStorage.LoanMaturityEffectiveWeight = "<php? echo $sfLoanMaturityEffectiveWeight; ?>" ;
			  localStorage.BBSBankingRelationshipYearsEffectiveWeight = "<php? echo $sfBBSBankingRelationshipYearsEffectiveWeight; ?>" ;
			  localStorage.BBSBankingProductsNoEffectiveWeight = "<php? echo $sfBBSBankingProductsNoEffectiveWeight; ?>" ;
			  localStorage.PastYearArrearIncidentsNoEffectiveWeight = "<php? echo $sfPastYearArrearIncidentsNoEffectiveWeight; ?>" ;
			  localStorage.Past2YearsArrearLoansRenegotiatedNoEffectiveWeight = "<php? echo $sfPast2YearsArrearLoansRenegotiatedNoEffectiveWeight; ?>" ;
			  localStorage.PaidDebtExceedsDefaultsEffectiveWeight = "<php? echo $sfPaidDebtExceedsDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfJudgementsEffectiveWeight = "<php? echo $sfNoOfJudgementsEffectiveWeight; ?>" ;
		      localStorage.NoOfDefaultsEffectiveWeight = "<php? echo $sfNoOfDefaultsEffectiveWeight; ?>" ;
		      localStorage.NoOfTraceAlertsEffectiveWeight = "<php? echo $sfNoOfTraceAlertsEffectiveWeight; ?>" ;  
  
              console.log ("Finished Local Storage Assignment - Model Settings");
			  
		  }	  
 
          function Load_Industry_Benchmarks_FromPHP_Into_LocalStorage()

		  {		  
             console.log ("Starting to transfer to local storage");
			 localStorage.CurrentRatioBenchmarkType                     = '<php? echo $CurrentRatioBenchmarkType?>' ;
             localStorage.CurrentRatioGlobalAverage                     = '<php? echo $CurrentRatioGlobalAverage?>' ;
             localStorage.CurrentRatioBenchmark_Trade                   = '<php? echo $CurrentRatioBenchmark_Trade?>' ;
             localStorage.CurrentRatioBenchmark_Finance                 = '<php? echo $CurrentRatioBenchmark_Finance?>' ;
             localStorage.CurrentRatioBenchmark_RealEstate              = '<php? echo $CurrentRatioBenchmark_RealEstate?>' ;
             localStorage.CurrentRatioBenchmark_Manufacturing           = '<php? echo $CurrentRatioBenchmark_Manufacturing?>' ;
             localStorage.CurrentRatioBenchmark_Construction            = '<php? echo $CurrentRatioBenchmark_Construction?>' ;
             localStorage.CurrentRatioBenchmark_Agriculture             = '<php? echo $CurrentRatioBenchmark_Agriculture?>' ;
             localStorage.CurrentRatioBenchmark_Parastatals             = '<php? echo $CurrentRatioBenchmark_Parastatals?>' ;
             localStorage.CurrentRatioBenchmark_Transport               = '<php? echo $CurrentRatioBenchmark_Transport?>' ;
             localStorage.CurrentRatioBenchmark_Mining                  = '<php? echo $CurrentRatioBenchmark_Mining?>' ;
             localStorage.CurrentRatioBenchmark_DateUpdated             = '<php? echo $CurrentRatioBenchmark_DateUpdated?>' ;
             localStorage.CurrentRatioBenchmarkComment                  = '<php? echo $CurrentRatioBenchmarkComment?>' ;
             localStorage.QuickRatioBenchmarkType                       = '<php? echo $QuickRatioBenchmarkType?>' ;
             localStorage.QuickRatioGlobalAverage                       = '<php? echo $QuickRatioGlobalAverage?>' ;
             localStorage.QuickRatioBenchmark_Trade                     = '<php? echo $QuickRatioBenchmark_Trade?>' ;
             localStorage.QuickRatioBenchmark_Finance                   = '<php? echo $QuickRatioBenchmark_Finance?>' ;
             localStorage.QuickRatioBenchmark_RealEstate                = '<php? echo $QuickRatioBenchmark_RealEstate?>' ;
             localStorage.QuickRatioBenchmark_Manufacturing             = '<php? echo $QuickRatioBenchmark_Manufacturing?>' ;
             localStorage.QuickRatioBenchmark_Construction              = '<php? echo $QuickRatioBenchmark_Construction?>' ;
             localStorage.QuickRatioBenchmark_Agriculture               = '<php? echo $QuickRatioBenchmark_Agriculture?>' ;
             localStorage.QuickRatioBenchmark_Parastatals               = '<php? echo $QuickRatioBenchmark_Parastatals?>' ;
             localStorage.QuickRatioBenchmark_Transport                 = '<php? echo $QuickRatioBenchmark_Transport?>' ;
             localStorage.QuickRatioBenchmark_Mining                    = '<php? echo $QuickRatioBenchmark_Mining?>' ;
             localStorage.QuickRatioBenchmark_DateUpdated               = '<php? echo $QuickRatioBenchmark_DateUpdated?>' ;
             localStorage.QuickRatioBenchmarkComment                    = '<php? echo $QuickRatioBenchmarkComment?>' ;
             localStorage.DebtorDaysBenchmarkType                       = '<php? echo $DebtorDaysBenchmarkType?>' ;
             localStorage.DebtorDaysGlobalAverage                       = '<php? echo $DebtorDaysGlobalAverage?>' ;
             localStorage.DebtorDaysBenchmark_Trade                     = '<php? echo $DebtorDaysBenchmark_Trade?>' ;
             localStorage.DebtorDaysBenchmark_Finance                   = '<php? echo $DebtorDaysBenchmark_Finance?>' ;
             localStorage.DebtorDaysBenchmark_RealEstate                = '<php? echo $DebtorDaysBenchmark_RealEstate?>' ;
             localStorage.DebtorDaysBenchmark_Manufacturing             = '<php? echo $DebtorDaysBenchmark_Manufacturing?>' ;
             localStorage.DebtorDaysBenchmark_Construction              = '<php? echo $DebtorDaysBenchmark_Construction?>' ;
             localStorage.DebtorDaysBenchmark_Agriculture               = '<php? echo $DebtorDaysBenchmark_Agriculture?>' ;
             localStorage.DebtorDaysBenchmark_Parastatals               = '<php? echo $DebtorDaysBenchmark_Parastatals?>' ;
             localStorage.DebtorDaysBenchmark_Transport                 = '<php? echo $DebtorDaysBenchmark_Transport?>' ;
             localStorage.DebtorDaysBenchmark_Mining                    = '<php? echo $DebtorDaysBenchmark_Mining?>' ;
             localStorage.DebtorDaysBenchmark_DateUpdated               = '<php? echo $DebtorDaysBenchmark_DateUpdated?>' ;
             localStorage.CurrentRatioBenchmarkComment                  = '<php? echo $CurrentRatioBenchmarkComment?>' ;
             localStorage.CreditorDaysBenchmarkType                     = '<php? echo $CreditorDaysBenchmarkType?>' ;
             localStorage.CreditorDaysGlobalAverage                     = '<php? echo $CreditorDaysGlobalAverage?>' ;
             localStorage.CreditorDaysBenchmark_Trade                   = '<php? echo $CreditorDaysBenchmark_Trade?>' ;
             localStorage.CreditorDaysBenchmark_Finance                 = '<php? echo $CreditorDaysBenchmark_Finance?>' ;
             localStorage.CreditorDaysBenchmark_RealEstate              = '<php? echo $CreditorDaysBenchmark_RealEstate?>' ;
             localStorage.CreditorDaysBenchmark_Manufacturing           = '<php? echo $CreditorDaysBenchmark_Manufacturing?>' ;
             localStorage.CreditorDaysBenchmark_Construction            = '<php? echo $CreditorDaysBenchmark_Construction?>' ;
             localStorage.CreditorDaysBenchmark_Agriculture             = '<php? echo $CreditorDaysBenchmark_Agriculture?>' ;
             localStorage.CreditorDaysBenchmark_Parastatals             = '<php? echo $CreditorDaysBenchmark_Parastatals?>' ;
             localStorage.CreditorDaysBenchmark_Transport               = '<php? echo $CreditorDaysBenchmark_Transport?>' ;
             localStorage.CreditorDaysBenchmark_Mining                  = '<php? echo $CreditorDaysBenchmark_Mining?>' ;
             localStorage.CreditorDaysBenchmark_DateUpdated             = '<php? echo $CreditorDaysBenchmark_DateUpdated?>' ;
             localStorage.CreditorDaysBenchmarkComment                  = '<php? echo $CreditorDaysBenchmarkComment?>' ;
             localStorage.TurnoverToWCBenchmarkType                     = '<php? echo $TurnoverToWCBenchmarkType?>' ;
             localStorage.TurnoverToWCGlobalAverage                     = '<php? echo $TurnoverToWCGlobalAverage?>' ;
             localStorage.TurnoverToWCBenchmark_Trade                   = '<php? echo $TurnoverToWCBenchmark_Trade?>' ;
             localStorage.TurnoverToWCBenchmark_Finance                 = '<php? echo $TurnoverToWCBenchmark_Finance?>' ;
             localStorage.TurnoverToWCBenchmark_RealEstate              = '<php? echo $TurnoverToWCBenchmark_RealEstate?>' ;
             localStorage.TurnoverToWCBenchmark_Manufacturing           = '<php? echo $TurnoverToWCBenchmark_Manufacturing?>' ;
             localStorage.TurnoverToWCBenchmark_Construction            = '<php? echo $TurnoverToWCBenchmark_Construction?>' ;
             localStorage.TurnoverToWCBenchmark_Agriculture             = '<php? echo $TurnoverToWCBenchmark_Agriculture?>' ;
             localStorage.TurnoverToWCBenchmark_Parastatals             = '<php? echo $TurnoverToWCBenchmark_Parastatals?>' ;
             localStorage.TurnoverToWCBenchmark_Transport               = '<php? echo $TurnoverToWCBenchmark_Transport?>' ;
             localStorage.TurnoverToWCBenchmark_Mining                  = '<php? echo $TurnoverToWCBenchmark_Mining?>' ;
             localStorage.TurnoverToWCBenchmark_DateUpdated             = '<php? echo $TurnoverToWCBenchmark_DateUpdated?>' ;
             localStorage.TurnoverToWCBenchmarkComment                  = '<php? echo $TurnoverToWCBenchmarkComment?>' ;
             localStorage.TurnoverGrowthBenchmarkType                   = '<php? echo $TurnoverGrowthBenchmarkType?>' ;
             localStorage.TurnoverGrowthGlobalAverage                   = '<php? echo $TurnoverGrowthGlobalAverage?>' ;
             localStorage.TurnoverGrowthBenchmark_Trade                 = '<php? echo $TurnoverGrowthBenchmark_Trade?>' ;
             localStorage.TurnoverGrowthBenchmark_Finance               = '<php? echo $TurnoverGrowthBenchmark_Finance?>' ;
             localStorage.TurnoverGrowthBenchmark_RealEstate            = '<php? echo $TurnoverGrowthBenchmark_RealEstate?>' ;
             localStorage.TurnoverGrowthBenchmark_Manufacturing         = '<php? echo $TurnoverGrowthBenchmark_Manufacturing?>' ;
             localStorage.TurnoverGrowthBenchmark_Construction          = '<php? echo $TurnoverGrowthBenchmark_Construction?>' ;
             localStorage.TurnoverGrowthBenchmark_Agriculture           = '<php? echo $TurnoverGrowthBenchmark_Agriculture?>' ;
             localStorage.TurnoverGrowthBenchmark_Parastatals           = '<php? echo $TurnoverGrowthBenchmark_Parastatals?>' ;
             localStorage.TurnoverGrowthBenchmark_Transport             = '<php? echo $TurnoverGrowthBenchmark_Transport?>' ;
             localStorage.TurnoverGrowthBenchmark_Mining                = '<php? echo $TurnoverGrowthBenchmark_Mining?>' ;
             localStorage.TurnoverGrowthBenchmark_DateUpdated           = '<php? echo $TurnoverGrowthBenchmark_DateUpdated?>' ;
             localStorage.CurrentRatioBenchmarkComment                  = '<php? echo $CurrentRatioBenchmarkComment?>' ;
             localStorage.GrossProfitMarginBenchmarkType                = '<php? echo $GrossProfitMarginBenchmarkType?>' ;
             localStorage.GrossProfitMarginGlobalAverage                = '<php? echo $GrossProfitMarginGlobalAverage?>' ;
             localStorage.GrossProfitMarginBenchmark_Trade              = '<php? echo $GrossProfitMarginBenchmark_Trade?>' ;
             localStorage.GrossProfitMarginBenchmark_Finance            = '<php? echo $GrossProfitMarginBenchmark_Finance?>' ;
             localStorage.GrossProfitMarginBenchmark_RealEstate         = '<php? echo $GrossProfitMarginBenchmark_RealEstate?>' ;
             localStorage.GrossProfitMarginBenchmark_Manufacturing      = '<php? echo $GrossProfitMarginBenchmark_Manufacturing?>' ;
             localStorage.GrossProfitMarginBenchmark_Construction       = '<php? echo $GrossProfitMarginBenchmark_Construction?>' ;
             localStorage.GrossProfitMarginBenchmark_Agriculture        = '<php? echo $GrossProfitMarginBenchmark_Agriculture?>' ;
             localStorage.GrossProfitMarginBenchmark_Parastatals        = '<php? echo $GrossProfitMarginBenchmark_Parastatals?>' ;
             localStorage.GrossProfitMarginBenchmark_Transport          = '<php? echo $GrossProfitMarginBenchmark_Transport?>' ;
             localStorage.GrossProfitMarginBenchmark_Mining             = '<php? echo $GrossProfitMarginBenchmark_Mining?>' ;
             localStorage.GrossProfitMarginBenchmark_DateUpdated        = '<php? echo $GrossProfitMarginBenchmark_DateUpdated?>' ;
             localStorage.GrossProfitMarginBenchmarkComment             = '<php? echo $GrossProfitMarginBenchmarkComment?>' ;
             localStorage.OperatingProfitMarginBenchmarkType            = '<php? echo $OperatingProfitMarginBenchmarkType?>' ;
             localStorage.OperatingProfitMarginGlobalAverage            = '<php? echo $OperatingProfitMarginGlobalAverage?>' ;
             localStorage.OperatingProfitMarginBenchmark_Trade          = '<php? echo $OperatingProfitMarginBenchmark_Trade?>' ;
             localStorage.OperatingProfitMarginBenchmark_Finance        = '<php? echo $OperatingProfitMarginBenchmark_Finance?>' ;
             localStorage.OperatingProfitMarginBenchmark_RealEstate     = '<php? echo $OperatingProfitMarginBenchmark_RealEstate?>' ;
             localStorage.OperatingProfitMarginBenchmark_Manufacturing  = '<php? echo $OperatingProfitMarginBenchmark_Manufacturing?>' ;
             localStorage.OperatingProfitMarginBenchmark_Construction   = '<php? echo $OperatingProfitMarginBenchmark_Construction?>' ;
             localStorage.OperatingProfitMarginBenchmark_Agriculture    = '<php? echo $OperatingProfitMarginBenchmark_Agriculture?>' ;
             localStorage.OperatingProfitMarginBenchmark_Parastatals    = '<php? echo $OperatingProfitMarginBenchmark_Parastatals?>' ;
             localStorage.OperatingProfitMarginBenchmark_Transport      = '<php? echo $OperatingProfitMarginBenchmark_Transport?>' ;
             localStorage.OperatingProfitMarginBenchmark_Mining         = '<php? echo $OperatingProfitMarginBenchmark_Mining?>' ;
             localStorage.OperatingProfitMarginBenchmark_DateUpdated    = '<php? echo $OperatingProfitMarginBenchmark_DateUpdated?>' ;
             localStorage.OperatingProfitMarginBenchmarkComment         = '<php? echo $OperatingProfitMarginBenchmarkComment?>' ;
             localStorage.NetProfitMarginBenchmarkType                  = '<php? echo $NetProfitMarginBenchmarkType?>' ;
             localStorage.NetProfitMarginGlobalAverage                  = '<php? echo $NetProfitMarginGlobalAverage?>' ;
             localStorage.NetProfitMarginBenchmark_Trade                = '<php? echo $NetProfitMarginBenchmark_Trade?>' ;
             localStorage.NetProfitMarginBenchmark_Finance              = '<php? echo $NetProfitMarginBenchmark_Finance?>' ;
             localStorage.NetProfitMarginBenchmark_RealEstate           = '<php? echo $NetProfitMarginBenchmark_RealEstate?>' ;
             localStorage.NetProfitMarginBenchmark_Manufacturing        = '<php? echo $NetProfitMarginBenchmark_Manufacturing?>' ;
             localStorage.NetProfitMarginBenchmark_Construction         = '<php? echo $NetProfitMarginBenchmark_Construction?>' ;
             localStorage.NetProfitMarginBenchmark_Agriculture          = '<php? echo $NetProfitMarginBenchmark_Agriculture?>' ;
             localStorage.NetProfitMarginBenchmark_Parastatals          = '<php? echo $NetProfitMarginBenchmark_Parastatals?>' ;
             localStorage.NetProfitMarginBenchmark_Transport            = '<php? echo $NetProfitMarginBenchmark_Transport?>' ;
             localStorage.NetProfitMarginBenchmark_Mining               = '<php? echo $NetProfitMarginBenchmark_Mining?>' ;
             localStorage.NetProfitMarginBenchmark_DateUpdated          = '<php? echo $NetProfitMarginBenchmark_DateUpdated?>' ;
             localStorage.NetProfitMarginBenchmarkComment               = '<php? echo $NetProfitMarginBenchmarkComment?>' ;
             localStorage.ROEBenchmarkType                              = '<php? echo $ROEBenchmarkType?>' ;
             localStorage.ROEGlobalAverage                              = '<php? echo $ROEGlobalAverage?>' ;
             localStorage.ROEBenchmark_Trade                            = '<php? echo $ROEBenchmark_Trade?>' ;
             localStorage.ROEBenchmark_Finance                          = '<php? echo $ROEBenchmark_Finance?>' ;
             localStorage.ROEBenchmark_RealEstate                       = '<php? echo $ROEBenchmark_RealEstate?>' ;
             localStorage.ROEBenchmark_Manufacturing                    = '<php? echo $ROEBenchmark_Manufacturing?>' ;
             localStorage.ROEBenchmark_Construction                     = '<php? echo $ROEBenchmark_Construction?>' ;
             localStorage.ROEBenchmark_Agriculture                      = '<php? echo $ROEBenchmark_Agriculture?>' ;
             localStorage.ROEBenchmark_Parastatals                      = '<php? echo $ROEBenchmark_Parastatals?>' ;
             localStorage.ROEBenchmark_Transport                        = '<php? echo $ROEBenchmark_Transport?>' ;
             localStorage.ROEBenchmark_Mining                           = '<php? echo $ROEBenchmark_Mining?>' ;
             localStorage.ROEBenchmark_DateUpdated                      = '<php? echo $ROEBenchmark_DateUpdated?>' ;
             localStorage.CurrentRatioBenchmarkComment                  = '<php? echo $CurrentRatioBenchmarkComment?>' ;
             localStorage.ROABenchmarkType                              = '<php? echo $ROABenchmarkType?>' ;
             localStorage.ROAGlobalAverage                              = '<php? echo $ROAGlobalAverage?>' ;
             localStorage.ROABenchmark_Trade                            = '<php? echo $ROABenchmark_Trade?>' ;
             localStorage.ROABenchmark_Finance                          = '<php? echo $ROABenchmark_Finance?>' ;
             localStorage.ROABenchmark_RealEstate                       = '<php? echo $ROABenchmark_RealEstate?>' ;
             localStorage.ROABenchmark_Manufacturing                    = '<php? echo $ROABenchmark_Manufacturing?>' ;
             localStorage.ROABenchmark_Construction                     = '<php? echo $ROABenchmark_Construction?>' ;
             localStorage.ROABenchmark_Agriculture                      = '<php? echo $ROABenchmark_Agriculture?>' ;
             localStorage.ROABenchmark_Parastatals                      = '<php? echo $ROABenchmark_Parastatals?>' ;
             localStorage.ROABenchmark_Transport                        = '<php? echo $ROABenchmark_Transport?>' ;
             localStorage.ROABenchmark_Mining                           = '<php? echo $ROABenchmark_Mining?>' ;
             localStorage.ROABenchmark_DateUpdated                      = '<php? echo $ROABenchmark_DateUpdated?>' ;
             localStorage.ROABenchmarkComment                           = '<php? echo $ROABenchmarkComment?>' ;
             localStorage.ROIBenchmarkType                              = '<php? echo $ROIBenchmarkType?>' ;
             localStorage.ROIGlobalAverage                              = '<php? echo $ROIGlobalAverage?>' ;
             localStorage.ROIBenchmark_Trade                            = '<php? echo $ROIBenchmark_Trade?>' ;
             localStorage.ROIBenchmark_Finance                          = '<php? echo $ROIBenchmark_Finance?>' ;
             localStorage.ROIBenchmark_RealEstate                       = '<php? echo $ROIBenchmark_RealEstate?>' ;
             localStorage.ROIBenchmark_Manufacturing                    = '<php? echo $ROIBenchmark_Manufacturing?>' ;
             localStorage.ROIBenchmark_Construction                     = '<php? echo $ROIBenchmark_Construction?>' ;
             localStorage.ROIBenchmark_Agriculture                      = '<php? echo $ROIBenchmark_Agriculture?>' ;
             localStorage.ROIBenchmark_Parastatals                      = '<php? echo $ROIBenchmark_Parastatals?>' ;
             localStorage.ROIBenchmark_Transport                        = '<php? echo $ROIBenchmark_Transport?>' ;
             localStorage.ROIBenchmark_Mining                           = '<php? echo $ROIBenchmark_Mining?>' ;
             localStorage.ROIBenchmark_DateUpdated                      = '<php? echo $ROIBenchmark_DateUpdated?>' ;
             localStorage.ROIBenchmarkComment                           = '<php? echo $ROIBenchmarkComment?>' ;
             localStorage.GearingRatioBenchmarkType                     = '<php? echo $GearingRatioBenchmarkType?>' ;
             localStorage.GearingRatioGlobalAverage                     = '<php? echo $GearingRatioGlobalAverage?>' ;
             localStorage.GearingRatioBenchmark_Trade                   = '<php? echo $GearingRatioBenchmark_Trade?>' ;
             localStorage.GearingRatioBenchmark_Finance                 = '<php? echo $GearingRatioBenchmark_Finance?>' ;
             localStorage.GearingRatioBenchmark_RealEstate              = '<php? echo $GearingRatioBenchmark_RealEstate?>' ;
             localStorage.GearingRatioBenchmark_Manufacturing           = '<php? echo $GearingRatioBenchmark_Manufacturing?>' ;
             localStorage.GearingRatioBenchmark_Construction            = '<php? echo $GearingRatioBenchmark_Construction?>' ;
             localStorage.GearingRatioBenchmark_Agriculture             = '<php? echo $GearingRatioBenchmark_Agriculture?>' ;
             localStorage.GearingRatioBenchmark_Parastatals             = '<php? echo $GearingRatioBenchmark_Parastatals?>' ;
             localStorage.GearingRatioBenchmark_Transport               = '<php? echo $GearingRatioBenchmark_Transport?>' ;
             localStorage.GearingRatioBenchmark_Mining                  = '<php? echo $GearingRatioBenchmark_Mining?>' ;
             localStorage.GearingRatioBenchmark_DateUpdated             = '<php? echo $GearingRatioBenchmark_DateUpdated?>' ;
             localStorage.GearingRatioBenchmarkComment                  = '<php? echo $GearingRatioBenchmarkComment?>' ;
             localStorage.LongtermDebtToEquityBenchmarkType             = '<php? echo $LongtermDebtToEquityBenchmarkType?>' ;
             localStorage.LongtermDebtToEquityGlobalAverage             = '<php? echo $LongtermDebtToEquityGlobalAverage?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Trade           = '<php? echo $LongtermDebtToEquityBenchmark_Trade?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Finance         = '<php? echo $LongtermDebtToEquityBenchmark_Finance?>' ;
             localStorage.LongtermDebtToEquityBenchmark_RealEstate      = '<php? echo $LongtermDebtToEquityBenchmark_RealEstate?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Manufacturing   = '<php? echo $LongtermDebtToEquityBenchmark_Manufacturing?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Construction    = '<php? echo $LongtermDebtToEquityBenchmark_Construction?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Agriculture     = '<php? echo $LongtermDebtToEquityBenchmark_Agriculture?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Parastatals     = '<php? echo $LongtermDebtToEquityBenchmark_Parastatals?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Transport       = '<php? echo $LongtermDebtToEquityBenchmark_Transport?>' ;
             localStorage.LongtermDebtToEquityBenchmark_Mining          = '<php? echo $LongtermDebtToEquityBenchmark_Mining?>' ;
             localStorage.LongtermDebtToEquityBenchmark_DateUpdated     = '<php? echo $LongtermDebtToEquityBenchmark_DateUpdated?>' ;
             localStorage.LongtermDebtToEquityBenchmarkComment          = '<php? echo $LongtermDebtToEquityBenchmarkComment?>' ;
             localStorage.DebtToTangibleNetWorthBenchmarkType           = '<php? echo $DebtToTangibleNetWorthBenchmarkType?>' ;
             localStorage.DebtToTangibleNetWorthGlobalAverage           = '<php? echo $DebtToTangibleNetWorthGlobalAverage?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Trade         = '<php? echo $DebtToTangibleNetWorthBenchmark_Trade?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Finance       = '<php? echo $DebtToTangibleNetWorthBenchmark_Finance?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_RealEstate    = '<php? echo $DebtToTangibleNetWorthBenchmark_RealEstate?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Manufacturing = '<php? echo $DebtToTangibleNetWorthBenchmark_Manufacturing?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Construction  = '<php? echo $DebtToTangibleNetWorthBenchmark_Construction?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Agriculture   = '<php? echo $DebtToTangibleNetWorthBenchmark_Agriculture?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Parastatals   = '<php? echo $DebtToTangibleNetWorthBenchmark_Parastatals?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Transport     = '<php? echo $DebtToTangibleNetWorthBenchmark_Transport?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_Mining        = '<php? echo $DebtToTangibleNetWorthBenchmark_Mining?>' ;
             localStorage.DebtToTangibleNetWorthBenchmark_DateUpdated   = '<php? echo $DebtToTangibleNetWorthBenchmark_DateUpdated?>' ;
             localStorage.DebtToTangibleNetWorthBenchmarkComment        = '<php? echo $DebtToTangibleNetWorthBenchmarkComment?>' ;
             localStorage.EquityToTotalAssetsBenchmarkType              = '<php? echo $EquityToTotalAssetsBenchmarkType?>' ;
             localStorage.EquityToTotalAssetsGlobalAverage              = '<php? echo $EquityToTotalAssetsGlobalAverage?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Trade            = '<php? echo $EquityToTotalAssetsBenchmark_Trade?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Finance          = '<php? echo $EquityToTotalAssetsBenchmark_Finance?>' ;
             localStorage.EquityToTotalAssetsBenchmark_RealEstate       = '<php? echo $EquityToTotalAssetsBenchmark_RealEstate?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Manufacturing    = '<php? echo $EquityToTotalAssetsBenchmark_Manufacturing?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Construction     = '<php? echo $EquityToTotalAssetsBenchmark_Construction?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Agriculture      = '<php? echo $EquityToTotalAssetsBenchmark_Agriculture?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Parastatals      = '<php? echo $EquityToTotalAssetsBenchmark_Parastatals?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Transport        = '<php? echo $EquityToTotalAssetsBenchmark_Transport?>' ;
             localStorage.EquityToTotalAssetsBenchmark_Mining           = '<php? echo $EquityToTotalAssetsBenchmark_Mining?>' ;
             localStorage.EquityToTotalAssetsBenchmark_DateUpdated      = '<php? echo $EquityToTotalAssetsBenchmark_DateUpdated?>' ;
             localStorage.EquityToTotalAssetsBenchmarkComment           = '<php? echo $EquityToTotalAssetsBenchmarkComment?>' ;
             localStorage.SolvencyBenchmarkType                         = '<php? echo $SolvencyBenchmarkType?>' ;
             localStorage.SolvencyGlobalAverage                         = '<php? echo $SolvencyGlobalAverage?>' ;
             localStorage.SolvencyBenchmark_Trade                       = '<php? echo $SolvencyBenchmark_Trade?>' ;
             localStorage.SolvencyBenchmark_Finance                     = '<php? echo $SolvencyBenchmark_Finance?>' ;
             localStorage.SolvencyBenchmark_RealEstate                  = '<php? echo $SolvencyBenchmark_RealEstate?>' ;
             localStorage.SolvencyBenchmark_Manufacturing               = '<php? echo $SolvencyBenchmark_Manufacturing?>' ;
             localStorage.SolvencyBenchmark_Construction                = '<php? echo $SolvencyBenchmark_Construction?>' ;
             localStorage.SolvencyBenchmark_Agriculture                 = '<php? echo $SolvencyBenchmark_Agriculture?>' ;
             localStorage.SolvencyBenchmark_Parastatals                 = '<php? echo $SolvencyBenchmark_Parastatals?>' ;
             localStorage.SolvencyBenchmark_Transport                   = '<php? echo $SolvencyBenchmark_Transport?>' ;
             localStorage.SolvencyBenchmark_Mining                      = '<php? echo $SolvencyBenchmark_Mining?>' ;
             localStorage.SolvencyBenchmark_DateUpdated                 = '<php? echo $SolvencyBenchmark_DateUpdated?>' ;
             localStorage.SolvencyBenchmarkComment                      = '<php? echo $SolvencyBenchmarkComment?>' ;
             localStorage.InterestCoverBenchmarkType                    = '<php? echo $InterestCoverBenchmarkType?>' ;
             localStorage.InterestCoverGlobalAverage                    = '<php? echo $InterestCoverGlobalAverage?>' ;
             localStorage.InterestCoverBenchmark_Trade                  = '<php? echo $InterestCoverBenchmark_Trade?>' ;
             localStorage.InterestCoverBenchmark_Finance                = '<php? echo $InterestCoverBenchmark_Finance?>' ;
             localStorage.InterestCoverBenchmark_RealEstate             = '<php? echo $InterestCoverBenchmark_RealEstate?>' ;
             localStorage.InterestCoverBenchmark_Manufacturing          = '<php? echo $InterestCoverBenchmark_Manufacturing?>' ;
             localStorage.InterestCoverBenchmark_Construction           = '<php? echo $InterestCoverBenchmark_Construction?>' ;
             localStorage.InterestCoverBenchmark_Agriculture            = '<php? echo $InterestCoverBenchmark_Agriculture?>' ;
             localStorage.InterestCoverBenchmark_Parastatals            = '<php? echo $InterestCoverBenchmark_Parastatals?>' ;
             localStorage.InterestCoverBenchmark_Transport              = '<php? echo $InterestCoverBenchmark_Transport?>' ;
             localStorage.InterestCoverBenchmark_Mining                 = '<php? echo $InterestCoverBenchmark_Mining?>' ;
             localStorage.InterestCoverBenchmark_DateUpdated            = '<php? echo $InterestCoverBenchmark_DateUpdated?>' ;
             localStorage.InterestCoverBenchmarkComment                 = '<php? echo $InterestCoverBenchmarkComment?>' ;
             localStorage.EBITDAToDebtBenchmarkType                     = '<php? echo $EBITDAToDebtBenchmarkType?>' ;
             localStorage.EBITDAToDebtGlobalAverage                     = '<php? echo $EBITDAToDebtGlobalAverage?>' ;
             localStorage.EBITDAToDebtBenchmark_Trade                   = '<php? echo $EBITDAToDebtBenchmark_Trade?>' ;
             localStorage.EBITDAToDebtBenchmark_Finance                 = '<php? echo $EBITDAToDebtBenchmark_Finance?>' ;
             localStorage.EBITDAToDebtBenchmark_RealEstate              = '<php? echo $EBITDAToDebtBenchmark_RealEstate?>' ;
             localStorage.EBITDAToDebtBenchmark_Manufacturing           = '<php? echo $EBITDAToDebtBenchmark_Manufacturing?>' ;
             localStorage.EBITDAToDebtBenchmark_Construction            = '<php? echo $EBITDAToDebtBenchmark_Construction?>' ;
             localStorage.EBITDAToDebtBenchmark_Agriculture             = '<php? echo $EBITDAToDebtBenchmark_Agriculture?>' ;
             localStorage.EBITDAToDebtBenchmark_Parastatals             = '<php? echo $EBITDAToDebtBenchmark_Parastatals?>' ;
             localStorage.EBITDAToDebtBenchmark_Transport               = '<php? echo $EBITDAToDebtBenchmark_Transport?>' ;
             localStorage.EBITDAToDebtBenchmark_Mining                  = '<php? echo $EBITDAToDebtBenchmark_Mining?>' ;
             localStorage.EBITDAToDebtBenchmark_DateUpdated             = '<php? echo $EBITDAToDebtBenchmark_DateUpdated?>' ;
             localStorage.EBITDAToDebtBenchmarkComment                  = '<php? echo $EBITDAToDebtBenchmarkComment?>' ;
             localStorage.TotalAssetsTurnoverBenchmarkType              = '<php? echo $TotalAssetsTurnoverBenchmarkType?>' ;
             localStorage.TotalAssetsTurnoverGlobalAverage              = '<php? echo $TotalAssetsTurnoverGlobalAverage?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Trade            = '<php? echo $TotalAssetsTurnoverBenchmark_Trade?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Finance          = '<php? echo $TotalAssetsTurnoverBenchmark_Finance?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_RealEstate       = '<php? echo $TotalAssetsTurnoverBenchmark_RealEstate?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Manufacturing    = '<php? echo $TotalAssetsTurnoverBenchmark_Manufacturing?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Construction     = '<php? echo $TotalAssetsTurnoverBenchmark_Construction?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Agriculture      = '<php? echo $TotalAssetsTurnoverBenchmark_Agriculture?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Parastatals      = '<php? echo $TotalAssetsTurnoverBenchmark_Parastatals?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Transport        = '<php? echo $TotalAssetsTurnoverBenchmark_Transport?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_Mining           = '<php? echo $TotalAssetsTurnoverBenchmark_Mining?>' ;
             localStorage.TotalAssetsTurnoverBenchmark_DateUpdated      = '<php? echo $TotalAssetsTurnoverBenchmark_DateUpdated?>' ;
             localStorage.TotalAssetsTurnoverBenchmarkComment           = '<php? echo $TotalAssetsTurnoverBenchmarkComment?>' ;
             localStorage.FixedlAssetsTurnoverBenchmarkType             = '<php? echo $FixedlAssetsTurnoverBenchmarkType?>' ;
             localStorage.FixedlAssetsTurnoverGlobalAverage             = '<php? echo $FixedlAssetsTurnoverGlobalAverage?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Trade           = '<php? echo $FixedlAssetsTurnoverBenchmark_Trade?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Finance         = '<php? echo $FixedlAssetsTurnoverBenchmark_Finance?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_RealEstate      = '<php? echo $FixedlAssetsTurnoverBenchmark_RealEstate?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Manufacturing   = '<php? echo $FixedlAssetsTurnoverBenchmark_Manufacturing?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Construction    = '<php? echo $FixedlAssetsTurnoverBenchmark_Construction?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Agriculture     = '<php? echo $FixedlAssetsTurnoverBenchmark_Agriculture?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Parastatals     = '<php? echo $FixedlAssetsTurnoverBenchmark_Parastatals?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Transport       = '<php? echo $FixedlAssetsTurnoverBenchmark_Transport?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_Mining          = '<php? echo $FixedlAssetsTurnoverBenchmark_Mining?>' ;
             localStorage.FixedlAssetsTurnoverBenchmark_DateUpdated     = '<php? echo $FixedlAssetsTurnoverBenchmark_DateUpdated?>' ;
             localStorage.FixedlAssetsTurnoverBenchmarkComment          = '<php? echo $FixedlAssetsTurnoverBenchmarkComment?>' ;
		  
		     console.log("Finished transferring to Local Storage");
		  }

