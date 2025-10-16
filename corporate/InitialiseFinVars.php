<?php

		//1.NetSales
		$NetSales0=0;
		$NetSales1=0;
		$NetSales2=0;
		$NetSales3=0;
		
		//2.CostofSales-Depreciation
		$CosDep0=0;
		$CosDep1=0;
		$CosDep2=0;
		$CosDep3=0;
		
		//3.CostofSales-Other
		$CosOther0=0;
		$CosOther1=0;
		$CosOther2=0;
		$CosOther3=0;
		
		//4.OtherIncomeLine1
		$OtherIncomeLine1_0=0;
		$OtherIncomeLine1_1=0;
		$OtherIncomeLine1_2=0;
		$OtherIncomeLine1_3=0;
		
		//5.OtherIncomeLine2
		$OtherIncomeLine2_0=0;
		$OtherIncomeLine2_1=0;
		$OtherIncomeLine2_2=0;
		$OtherIncomeLine2_3=0;
		
		//6.OtherIncomeLine3
		$OtherIncomeLine3_0=0;
		$OtherIncomeLine3_1=0;
		$OtherIncomeLine3_2=0;
		$OtherIncomeLine3_3=0;
		
		//7.OtherIncomeLine4
		$OtherIncomeLine4_0=0;
		$OtherIncomeLine4_1=0;
		$OtherIncomeLine4_2=0;
		$OtherIncomeLine4_3=0;
		
		//8.OpexLine-StaffCosts
		$StaffCosts0=0;
		$StaffCosts1=0;
		$StaffCosts2=0;
		$StaffCosts3=0;

		//9.OpexLine-DirectorsFees
		$DirectorsFees0=0;
		$DirectorsFees1=0;
		$DirectorsFees2=0;
		$DirectorsFees3=0;
		
		//10.OpexLine-Depreciation
		$Depreciation0=0;
		$Depreciation1=0;
		$Depreciation2=0;
		$Depreciation3=0;
		
		//11.OpexLine-Amortisation
		$Amortisation0=0;
		$Amortisation1=0;
		$Amortisation2=0;
		$Amortisation3=0;
		
		//12.OpexLine-NoNameOpexLine1
		$NoNameOpexLine1_0=0;
		$NoNameOpexLine1_1=0;
		$NoNameOpexLine1_2=0;
		$NoNameOpexLine1_3=0;

		//13.OpexLine-NoNameOPexLine2
		$NoNameOpexLine2_0=0;
		$NoNameOpexLine2_1=0;
		$NoNameOpexLine2_2=0;
		$NoNameOpexLine2_3=0;
		
		//14.OpexLine-Other
		$OtherOpex0=0;
		$OtherOpex1=0;
		$OtherOpex2=0;
		$OtherOpex3=0;

		//15.NetFinanceCosts
		$NetFinanceCosts0=0;
		$NetFinanceCosts1=0;
		$NetFinanceCosts2=0;
		$NetFinanceCosts3=0;

		//16.IncomeTax
		$IncomeTax0=0;
		$IncomeTax1=0;
		$IncomeTax2=0;
		$IncomeTax3=0;

		//16.DeferredTax
		$DeferredTax0=0;
		$DeferredTax1=0;
		$DeferredTax2=0;
		$DeferredTax3=0;
				
		//17.OtherIncomeLineDescriptions
		$OtherIncomeDescLine1="";
		$OtherIncomeDescLine2="";
		$OtherIncomeDescLine3="";
		$OtherIncomeDescLine4="";
		//18.OtherOpexLineDescriptions
		$NoNameOpexDescLine1="";
		$NoNameOpexDescLine2="";
		//19.SavingtheCalculatedItems
		$CosTotal0=0;
		$CosTotal1=0;
		$CosTotal2=0;
		$CosTotal3=0;
		
		//2.GrossProfit
		$GrossProfit0=0;
		$GrossProfit1=0;
		$GrossProfit2=0;
		$GrossProfit3=0;
		
		//3.TotalOtherIncome
		$OtherIncomeTotal0=0;
		$OtherIncomeTotal1=0;
		$OtherIncomeTotal2=0;
		$OtherIncomeTotal3=0;
		
		//4.TotalOpex
		$OpexTotal0=0;
		$OpexTotal1=0;
		$OpexTotal2=0;
		$OpexTotal3=0;
		
		//5.EBIT
		$EBIT0=0;
		$EBIT1=0;
		$EBIT2=0;
		$EBIT3=0;
		
		//6.PBT
		$PBT0=0;
		$PBT1=0;
		$PBT2=0;
		$PBT3=0;
		//7.TaxationfortheYear
		$Taxation0=0;
		$Taxation1=0;
		$Taxation2=0;
		$Taxation3=0;
		//8.NetProfit
		$NetProfit0=0;
		$NetProfit1=0;
		$NetProfit2=0;
		$NetProfit3=0;


		//=====InitialiseInitialiseCurrentAssets()
		
		
		//CashBalances
		$CashBal0=0;
		$CashBal1=0;
		$CashBal2=0;
		$CashBal3=0;
		
		//SavingDebtors
    	$AccountsReceivable0=0;
		$AccountsReceivable1=0;
		$AccountsReceivable2=0;
		$AccountsReceivable3=0;
		//SavingPrepayments
		$Prepayments0=0;
		$Prepayments1=0;
		$Prepayments2=0;
		$Prepayments3=0;
		//SavingPrepaidTax
		$PrepaidTax0=0;
		$PrepaidTax1=0;
		$PrepaidTax2=0;
		$PrepaidTax3=0;
		//SavingInventory
		$Inventory0=0;
		$Inventory1=0;
		$Inventory2=0;
		$Inventory3=0;
		//SavingOtherCurrentAssetsNonInterCompanyLine1
		$OtherCurrentAssetsNonInterCompanyLine1_0=0;
		$OtherCurrentAssetsNonInterCompanyLine1_1=0;
		$OtherCurrentAssetsNonInterCompanyLine1_2=0;
		$OtherCurrentAssetsNonInterCompanyLine1_3=0;
		//SavingOtherCurrentAssetsNonInterCompanyLine2
		$OtherCurrentAssetsNonInterCompanyLine2_0=0;
		$OtherCurrentAssetsNonInterCompanyLine2_1=0;
		$OtherCurrentAssetsNonInterCompanyLine2_2=0;
		$OtherCurrentAssetsNonInterCompanyLine2_3=0;
		//SavingOtherCurrentAssetsNonInterCompanyLine3
		$OtherCurrentAssetsNonInterCompanyLine3_0=0;
		$OtherCurrentAssetsNonInterCompanyLine3_1=0;
		$OtherCurrentAssetsNonInterCompanyLine3_2=0;
		$OtherCurrentAssetsNonInterCompanyLine3_3=0;
		//SavingOtherCurrentAssetsInterCompanyLine1
		$OtherCurrentAssetsInterCompanyLine1_0=0;
		$OtherCurrentAssetsInterCompanyLine1_1=0;
		$OtherCurrentAssetsInterCompanyLine1_2=0;
		$OtherCurrentAssetsInterCompanyLine1_3=0;
		//SavingOtherCurrentAssetsInterCompanyLine2
		$OtherCurrentAssetsInterCompanyLine2_0=0;
		$OtherCurrentAssetsInterCompanyLine2_1=0;
		$OtherCurrentAssetsInterCompanyLine2_2=0;
		$OtherCurrentAssetsInterCompanyLine2_3=0;
		//SavingOtherCurrentAssetsInterCompanyLine3
		$OtherCurrentAssetsInterCompanyLine3_0=0;
		$OtherCurrentAssetsInterCompanyLine3_1=0;
		$OtherCurrentAssetsInterCompanyLine3_2=0;
		$OtherCurrentAssetsInterCompanyLine3_3=0;
		//SavingAdditionallinedescriptionsenteredbytheuser
		//LoadingOtherCurrentAssetsNonInterCompanyDescriptions
		$OtherCurrentAssetsNonInterCompanyDescLine1="";
		$OtherCurrentAssetsNonInterCompanyDescLine2="";
		$OtherCurrentAssetsNonInterCompanyDescLine3="";
		//LoadingOtherCurrentAssetsInterCompanyDescriptions
		$OtherCurrentAssetsInterCompanyDescLine1="";
		$OtherCurrentAssetsInterCompanyDescLine2="";
		$OtherCurrentAssetsInterCompanyDescLine3="";
	
	
		
		//Saving Totalof CurrentAssetsforexporttotheSummaryBalanceSheet
		$TotalCurrentAssets0=0;		
		$TotalCurrentAssets1=0;
		$TotalCurrentAssets2=0;
		$TotalCurrentAssets3=0;
		
		
		//=====InitialiseInitialiseCurrentLiabilities()

		//BankOverdraft
		$BankOverdraft0=0;
		$BankOverdraft1=0;
		$BankOverdraft2=0;
		$BankOverdraft3=0;
		
		//InitialisingDebtors
		$AccountsPayable0=0;
		$AccountsPayable1=0;
		$AccountsPayable2=0;
		$AccountsPayable3=0;
		//InitialisingAccruals
		$Accruals0=0;
		$Accruals1=0;
		$Accruals2=0;
		$Accruals3=0;
		//InitialisingPrepaidTax
		$TaxPayable0=0;
		$TaxPayable1=0;
		$TaxPayable2=0;
		$TaxPayable3=0;
		//InitialisingDividendsPayable
		$DividendsPayable0=0;
		$DividendsPayable1=0;
		$DividendsPayable2=0;
		$DividendsPayable3=0;
		//InitialisingCurrentPortionLongTermDebt
		$CurrentPortionLongTermDebt0=0;
		$CurrentPortionLongTermDebt1=0;
		$CurrentPortionLongTermDebt2=0;
		$CurrentPortionLongTermDebt3=0;
		//InitialisingOtherCurrentLiabilitiesNonInterCompanyLine1
		$OtherCurrentLiabilitiesNonInterCompanyLine1_0=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine1_1=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine1_2=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine1_3=0;
		//InitialisingOtherCurrentLiabilitiesNonInterCompanyLine2
		$OtherCurrentLiabilitiesNonInterCompanyLine2_0=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine2_1=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine2_2=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine2_3=0;
		//InitialisingOtherCurrentLiabilitiesNonInterCompanyLine3
		$OtherCurrentLiabilitiesNonInterCompanyLine3_0=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine3_1=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine3_2=0;
		$OtherCurrentLiabilitiesNonInterCompanyLine3_3=0;
		//InitialisingOtherCurrentLiabilitiesInterCompanyLine1
		$OtherCurrentLiabilitiesInterCompanyLine1_0=0;
        $OtherCurrentLiabilitiesInterCompanyLine1_1=0;
		$OtherCurrentLiabilitiesInterCompanyLine1_2=0;
		$OtherCurrentLiabilitiesInterCompanyLine1_3=0;
		//InitialisingOtherCurrentLiabilitiesInterCompanyLine2
		$OtherCurrentLiabilitiesInterCompanyLine2_0=0;
		$OtherCurrentLiabilitiesInterCompanyLine2_1=0;
		$OtherCurrentLiabilitiesInterCompanyLine2_2=0;
		$OtherCurrentLiabilitiesInterCompanyLine2_3=0;
		//InitialisingOtherCurrentLiabilitiesInterCompanyLine3
		$OtherCurrentLiabilitiesInterCompanyLine3_0=0;
		$OtherCurrentLiabilitiesInterCompanyLine3_1=0;
		$OtherCurrentLiabilitiesInterCompanyLine3_2=0;
		$OtherCurrentLiabilitiesInterCompanyLine3_3=0;
		//InitialisingAdditionallinedescriptionsenteredbytheuser
		//LoadingOtherCurrentLiabilitiesNonInterCompanyDescriptions
		$OtherCurrentLiabilitiesNonInterCompanyDescLine1="";
		$OtherCurrentLiabilitiesNonInterCompanyDescLine2="";
		$OtherCurrentLiabilitiesNonInterCompanyDescLine3="";
		//LoadingOtherCurrentLiabilitiesInterCompanyDescriptions
		$OtherCurrentLiabilitiesInterCompanyDescLine1="";
		$OtherCurrentLiabilitiesInterCompanyDescLine2="";
		$OtherCurrentLiabilitiesInterCompanyDescLine3="";
	
		//InitialisingaTotalofCurrentLiabilitiesforexporttotheSummaryBalanceSheet
		$TotalCurrentLiabilities0=0;		
		$TotalCurrentLiabilities1=0;
		$TotalCurrentLiabilities2=0;
		$TotalCurrentLiabilities3=0;
	
		//=====InitialiseInitialiseNonCurrentAssets()
		
		//InitialisingOpeningNBV
		$OpeningNBV0=0;
		$OpeningNBV1=0;
		$OpeningNBV2=0;
		$OpeningNBV3=0;
		
		//Additions
		$Additions0=0;
		$Additions1=0;
		$Additions2=0;
		$Additions3=0;
		//InitialisingRevaluation
		$Revaluation0=0;
		$Revaluation1=0;
		$Revaluation2=0;
		$Revaluation3=0;
		//InitialisingDepreciations-CostsOfSales
		$DepreciationCostOfSales0=0;
		$DepreciationCostOfSales1=0;
		$DepreciationCostOfSales2=0;
		$DepreciationCostOfSales3=0;
		//InitialisingDepreciation-Opex
		$DepreciationOpex0=0;
		$DepreciationOpex1=0;
		$DepreciationOpex2=0;
		$DepreciationOpex3=0;
		//InitialisingOtherMovementsPPE
		$OtherMovementsPPE0=0;
		$OtherMovementsPPE1=0;
		$OtherMovementsPPE2=0;
		$OtherMovementsPPE3=0;
	
		//InitialisingOtherNonCurrentAssetsLine1
		$OtherNonCurrentAssetsLine1_0=0;
		$OtherNonCurrentAssetsLine1_1=0;
		$OtherNonCurrentAssetsLine1_2=0;
		$OtherNonCurrentAssetsLine1_3=0;
		//InitialisingOtherNonCurrentAssetsLine2
		$OtherNonCurrentAssetsLine2_0=0;
		$OtherNonCurrentAssetsLine2_1=0;
		$OtherNonCurrentAssetsLine2_2=0;
		$OtherNonCurrentAssetsLine2_3=0;
		//InitialisingOtherNonCurrentAssetsLine3
	    $OtherNonCurrentAssetsLine3_0=0;
        $OtherNonCurrentAssetsLine3_1=0;
		$OtherNonCurrentAssetsLine3_2=0;
	    $OtherNonCurrentAssetsLine3_3=0;

		//InitialisingLandandBuildingsNBV
		$LandBuildingsNBV0=0;
		$LandBuildingsNBV1=0;
		$LandBuildingsNBV2=0;
		$LandBuildingsNBV3=0;
	
		//InitialisingInvestmentProperty
		$InvestmentProperty0=0;
		$InvestmentProperty1=0;
		$InvestmentProperty2=0;
		$InvestmentProperty3=0;
		//InitialisingIntangibleAssets
		$IntangibleAssets0=0;
		$IntangibleAssets1=0;
		$IntangibleAssets2=0;
		$IntangibleAssets3=0;

		//InitialisingOtherNonCurrentAssetsLine1
		$OtherNonCurrentAssetsLine1_0=0;
		$OtherNonCurrentAssetsLine1_1=0;
		$OtherNonCurrentAssetsLine1_2=0;
		$OtherNonCurrentAssetsLine1_3=0;
		//InitialisingOtherNonCurrentAssetsLine2
		$OtherNonCurrentAssetsLine2_0=0;
		$OtherNonCurrentAssetsLine2_1=0;
		$OtherNonCurrentAssetsLine2_2=0;
		$OtherNonCurrentAssetsLine2_3=0;
		//InitialisingOtherNonCurrentAssetsLine3
		$OtherNonCurrentAssetsLine3_0=0;
		$OtherNonCurrentAssetsLine3_1=0;
		$OtherNonCurrentAssetsLine3_2=0;
		$OtherNonCurrentAssetsLine3_3=0;
			
		//InitialisingAdditionallinedescriptionsenteredbytheuser
		//LoadingOtherNonCurrentAssetsDescriptions
		$OtherNonCurrentAssetsDescLine1="";
		$OtherNonCurrentAssetsDescLine2="";
		$OtherNonCurrentAssetsDescLine3="";
		//LoadingOtherNonCurrentAssetsComments
		$PPEComments="";
		$OtherNonCurrentAssetsComments="";
		
		//InitialisingaTotalofNonCurrentAssetsforexporttotheSummaryBalanceSheet
		$TotalNonCurrentAssets0=0;		
		$TotalNonCurrentAssets1=0;
		$TotalNonCurrentAssets2=0;
		$TotalNonCurrentAssets3=0;
		
		//=====InitialiseInitialiseNonCurrentLiabilities()
	
		
		//MortgageLoans
		$MortgageLoans0=0;
		$MortgageLoans1=0;
		$MortgageLoans2=0;
		$MortgageLoans3=0;
		
		//TermLoans
		$TermLoans0=0;
		$TermLoans1=0;
		$TermLoans2=0;
		$TermLoans3=0;
		//SavingBonds
		$Bonds0=0;
		$Bonds1=0;
		$Bonds2=0;
		$Bonds3=0;
		//SavingFinanceLeases
		$FinanceLeases0=0;
		$FinanceLeases1=0;
		$FinanceLeases2=0;
		$FinanceLeases3=0;
		//SavingOtherLongTermBorrowings
		$OtherLongTermBorrowings0=0;
		$OtherLongTermBorrowings1=0;
		$OtherLongTermBorrowings2=0;
		$OtherLongTermBorrowings3=0;
		//SavingShareholdersLoans
		$ShareholdersLoans0=0;
		$ShareholdersLoans1=0;
		$ShareholdersLoans2=0;
		$ShareholdersLoans3=0;
		//SavingInterCompanyyLoansLine1
		$InterCompanyLoansLine1_0=0;
		$InterCompanyLoansLine1_1=0;
		$InterCompanyLoansLine1_2=0;
		$InterCompanyLoansLine1_3=0;
		//SavingInterCompanyyLoansLine2
		$InterCompanyLoansLine2_0=0;
		$InterCompanyLoansLine2_1=0;
		$InterCompanyLoansLine2_2=0;
		$InterCompanyLoansLine2_3=0;
		//SavingInterCompanyyLoansLine3
		$InterCompanyLoansLine3_0=0;
		$InterCompanyLoansLine3_1=0;
		$InterCompanyLoansLine3_2=0;
		$InterCompanyLoansLine3_3=0;
		//SavingDeferredTaxBalance
		$DeferredTaxBalance0=0;
		$DeferredTaxBalance1=0;
		$DeferredTaxBalance2=0;
		$DeferredTaxBalance3=0;
		//SavingProvisions
		$Provisions0=0;
		$Provisions1=0;
		$Provisions2=0;
		$Provisions3=0;
		//SavingOtherNonCurrentLiabilitiesLine1
		$OtherNonCurrentLiabilitiesLine1_0=0;
		$OtherNonCurrentLiabilitiesLine1_1=0;
		$OtherNonCurrentLiabilitiesLine1_2=0;
		$OtherNonCurrentLiabilitiesLine1_3=0;
		//SavingOtherNonCurrentLiabilitiesLine2
		$OtherNonCurrentLiabilitiesLine2_0=0;
		$OtherNonCurrentLiabilitiesLine2_1=0;
		$OtherNonCurrentLiabilitiesLine2_2=0;
		$OtherNonCurrentLiabilitiesLine2_3=0;
		//SavingOtherNonCurrentLiabilitiesLine3
		$OtherNonCurrentLiabilitiesLine3_0=0;
		$OtherNonCurrentLiabilitiesLine3_1=0;
		$OtherNonCurrentLiabilitiesLine3_2=0;
		$OtherNonCurrentLiabilitiesLine3_3=0;
		//SavingAdditionallinedescriptionsenteredbytheuser
		//LoadingOtherCurrentAssetsNonInterCompanyDescriptions
		$InterCompanyLoansDescLine1="";
		$InterCompanyLoansDescLine2="";
		$InterCompanyLoansDescLine3="";
		//LoadingOtherCurrentAssetsInterCompanyDescriptions
		$OtherNonCurrentLiabilitiesDescLine1="";
		$OtherNonCurrentLiabilitiesDescLine2="";
		$OtherNonCurrentLiabilitiesDescLine3="";
	
	
		
		//SavingaTotalofCurrentAssetsforexporttotheSummaryBalanceSheet
		$TotalNonCurrentLiabilities0=0;		
		$TotalNonCurrentLiabilities1=0;
		$TotalNonCurrentLiabilities2=0;
		$TotalNonCurrentLiabilities3=0;
			
		
		//=====InitialiseInitialiseEquity()
		
		//InitialisingOpeningRetainedProfits
		$OpeningRetainedProfits0=0;
		$OpeningRetainedProfits1=0;
		$OpeningRetainedProfits2=0;
		$OpeningRetainedProfits3=0;
		//InitialisingClosingRetainedProfits
		$ClosingRetainedProfits0=0;
		$ClosingRetainedProfits1=0;
		$ClosingRetainedProfits2=0;
		$ClosingRetainedProfits3=0;
		
		//ShareCapital
		$ShareCapital0=0;
		$ShareCapital1=0;
		$ShareCapital2=0;
		$ShareCapital3=0;
		//InitialisingSharePremium
		$SharePremium0=0;
		$SharePremium1=0;
		$SharePremium2=0;
		$SharePremium3=0;
		//InitialisingRevaluationReserve
		$RevaluationReserve0=0;
		$RevaluationReserve1=0;
		$RevaluationReserve2=0;
		$RevaluationReserve3=0;
		//InitialisingNetProfit
		$NetProfit0=0;
		$NetProfit1=0;
		$NetProfit2=0;
		$NetProfit3=0;
		//InitialisingDividends
		$Dividends0=0;
		$Dividends1=0;
		$Dividends2=0;
		$Dividends3=0;
	
		//InitialisingOtherNonDistributableReservesLine1
		$OtherNonDistributableReservesLine1_0=0;
		$OtherNonDistributableReservesLine1_1=0;
		$OtherNonDistributableReservesLine1_2=0;
		$OtherNonDistributableReservesLine1_3=0;
		//InitialisingOtherNonDistributableReservesLine2
		$OtherNonDistributableReservesLine2_0=0;
		$OtherNonDistributableReservesLine2_1=0;
		$OtherNonDistributableReservesLine2_2=0;
		$OtherNonDistributableReservesLine2_3=0;
		//InitialisingOtherNonDistributableReservesLine3
		$OtherNonDistributableReservesLine3_0=0;
		$OtherNonDistributableReservesLine3_1=0;
		$OtherNonDistributableReservesLine3_2=0;
		$OtherNonDistributableReservesLine3_3=0;

		//InitialisingRetainedProfitsAdjustment
		$RetainedProfitsAdjustments0=0;
		$RetainedProfitsAdjustments1=0;
		$RetainedProfitsAdjustments2=0;
		$RetainedProfitsAdjustments3=0;
	
		//InitialisingOtherDistributableReservesLine1
		$OtherDistributableReservesLine1_0=0;
		$OtherDistributableReservesLine1_1=0;
		$OtherDistributableReservesLine1_2=0;
		$OtherDistributableReservesLine1_3=0;
		//InitialisingOtherDistributableReservesLine2
		$OtherDistributableReservesLine2_0=0;
		$OtherDistributableReservesLine2_1=0;
		$OtherDistributableReservesLine2_2=0;
		$OtherDistributableReservesLine2_3=0;
		//InitialisingOtherDistributableReservesLine3
		$OtherDistributableReservesLine3_0=0;
		$OtherDistributableReservesLine3_1=0;
		$OtherDistributableReservesLine3_2=0;
		$OtherDistributableReservesLine3_3=0;
			
		//InitialisingAdditionallinedescriptionsenteredbytheuser
		//InitialisingOtherNonDistributableReservesDescriptions
		$OtherNonDistributableReservesDescLine1="";
		$OtherNonDistributableReservesDescLine2="";
		$OtherNonDistributableReservesDescLine3="";
	
		//InitialisingDistributableReservesDescriptions
		$OtherDistributableReservesDescLine1="";
		$OtherDistributableReservesDescLine2="";
		$OtherDistributableReservesDescLine3="";
	
		//InitialisingaTotalofNonCurrentAssetsforexporttotheSummaryBalanceSheet
		$TotalEquity0=0;
		$TotalEquity1=0;
		$TotalEquity2=0;
		$TotalEquity3=0;
		
?>