<?php

/** The file allocates each record to the appropriate holding variable in each loop pass.
    The first If structure ensures that an update_type which is not Application is ignored
	eg. updating financials for an existing customer.
**/

//In case the query used company_reg_no and loan_number composite key, get the application_ref

$application_ref = $row["application_ref"];

if ($row["update_type"] == "Application")
{	
		
		//Capture custom line descriptions and comments covering all financial periods

		switch ($row["line_desc1"])
		{
			//Income Statement Other Line Descriptions-Other Income
			case "OtherIncomeLine1":
				  $OtherIncomeDescLine1=$row["line_desc2"];
				  break;
			case "OtherIncomeLine2":
				  $OtherIncomeDescLine2=$row["line_desc2"];
				  break;
			case "OtherIncomeLine3":
				  $OtherIncomeDescLine3=$row["line_desc2"];
				  break;
			case "OtherIncomeLine4":
				  $OtherIncomeDescLine4=$row["line_desc2"];
				  break;
			//Income Statement Other Line Descriptions-No Name Opex
			case "NoNameOpexLine1":
				  $NoNameOpexDescLine1=$row["line_desc2"];
				  break;
			case "NoNameOpexLine2":
				  $NoNameOpexDescLine2=$row["line_desc2"];
				  break;
			//Current Assets Other Line Descriptions-Other Current Assets Non InterCompany
			case "OtherCurrentAssetsNonIntercompanyLine1":
				  $OtherCurrentAssetsNonInterCompanyDescLine1=$row["line_desc2"];
				  break;
			case "OtherCurrentAssetsNonIntercompanyLine2":
				  $OtherCurrentAssetsNonInterCompanyDescLine2=$row["line_desc2"];
				  break;
			case "OtherCurrentAssetsNonIntercompanyLine3":
				  $OtherCurrentAssetsNonInterCompanyDescLine3=$row["line_desc2"];
				  break;
			//Current Assets Other Line Descriptions-Other Current Assets InterCompany
			case "OtherCurrentAssetsIntercompanyLine1":
				  $OtherCurrentAssetsInterCompanyDescLine1=$row["line_desc2"];
				  break;
			case "OtherCurrentAssetsIntercompanyLine2":
				  $OtherCurrentAssetsInterCompanyDescLine2=$row["line_desc2"];
				  break;
			case "OtherCurrentAssetsIntercompanyLine3":
				  $OtherCurrentAssetsInterCompanyDescLine3=$row["line_desc2"];
				  break;
			//Current Liabilities Other Line Descriptions-Other Current Liabilities Non InterCompany
			case "OtherCurrentLiabilitiesNonIntercompanyLine1":
				  $OtherCurrentLiabilitiesNonInterCompanyDescLine1=$row["line_desc2"];
				  break;
			case "OtherCurrentLiabilitiesNonIntercompanyLine2":
				  $OtherCurrentLiabilitiesNonInterCompanyDescLine2=$row["line_desc2"];
				  break;
			case "OtherCurrentLiabilitiesNonIntercompanyLine3":
				  $OtherCurrentLiabilitiesNonInterCompanyDescLine3=$row["line_desc2"];
				  break;
			//Current Liabilities Other Line Descriptions-Other Current Liabilities InterCompany
			case "OtherCurrentLiabilitiesIntercompanyLine1":
				  $OtherCurrentLiabilitiesInterCompanyDescLine1=$row["line_desc2"];
				  break;
			case "OtherCurrentLiabilitiesIntercompanyLine2":
				  $OtherCurrentLiabilitiesInterCompanyDescLine2=$row["line_desc2"];
				  break;
			case "OtherCurrentLiabilitiesIntercompanyLine3":
				  $OtherCurrentLiabilitiesInterCompanyDescLine3=$row["line_desc2"];
				  break;
			//Non Current Liabilities Other Line Descriptions-Other Non Current Liabilities 
			case "OtherNonCurrentLiabilitiesLine1":
				  $OtherNonCurrentLiabilitiesDescLine1=$row["line_desc2"];
				  break;
			case "OtherNonCurrentLiabilitiesLine2":
				  $OtherNonCurrentLiabilitiesDescLine2=$row["line_desc2"];
				  break;
			case "OtherNonCurrentLiabilitiesLine3":
				  $OtherNonCurrentLiabilitiesDescLine3=$row["line_desc2"];
				  break;
			//Non Current Liabilities Other Line Descriptions-Inter Company Loans 
			case "InterCompanyLoansLine1":
				  $InterCompanyLoansDescLine1=$row["line_desc2"];
				  break;
			case "InterCompanyLoansLine2":
				  $InterCompanyLoansDescLine2=$row["line_desc2"];
				  break;
			case "InterCompanyLoansLine3":
				  $InterCompanyLoansDescLine3=$row["line_desc2"];
				  break;
			//Non Current Assets Other Line Descriptions- Other Non Current Assets 
			case "OtherNonCurrentAssetsLine1":
				  $OtherNonCurrentAssetsDescLine1=$row["line_desc2"];
				  break;
			case "OtherNonCurrentAssetsLine2":
				  $OtherNonCurrentAssetsDescLine2=$row["line_desc2"];
				  break;
			case "OtherNonCurrentAssetsLine3":
				  $OtherNonCurrentAssetsDescLine3=$row["line_desc2"];
				  break;
			
			case "PPEComments":
			      $PPEComments = $row["line_comment"];
				  break;
			case "OtherNonCurrentAssetsComments":
                  $OtherNonCurrentAssetsComments = $row["line_comment"];
                  break;				   
			//Equity Other Line Descriptions-Other Non Distributable Reserves
			case "OtherNonDistributableReservesLine1":
				  $OtherNonDistributableReservesDescLine1=$row["line_desc2"];
				  break;
			case "OtherNonDistributableReservesLine2":
				  $OtherNonDistributableReservesDescLine2=$row["line_desc2"];
				  break;
			case "OtherNonDistributableReservesDescLine3":
				  $OtherNonDistributableReservesDescLine3=$row["line_desc2"];
				  break;
			//Equity Other Line Descriptions-Other Distributable Reserves
			case "OtherDistributableReservesLine1":
				  $OtherDistributableReservesDescLine1=$row["line_desc2"];
				  break;
			case "OtherDistributableReservesLine2":
				  $OtherDistributableReservesDescLine2=$row["line_desc2"];
				  break;
			case "OtherDistributableReservesLine3":
				  $OtherDistributableReservesDescLine3=$row["line_desc2"];
				  break;
		
		}
		switch ($row["reporting_year"])
		{
		   
		   case $financial_year0:
	  	      //1. CAPTURING INCOME STATEMENT DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "NetSales")              {$NetSales0              	= $row["amount"];}
		      if ($row["line_desc1"]               == "CosDep")                {$CosDep0                	= $row["amount"];}
 		      if ($row["line_desc1"]               == "CosOther")              {$CosOther0              	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine1")      {$OtherIncomeLine1_0     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine2")      {$OtherIncomeLine2_0     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine3")      {$OtherIncomeLine3_0     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine4")      {$OtherIncomeLine4_0     	= $row["amount"];}
		      if ($row["line_desc1"]               == "StaffCosts")            {$StaffCosts0            	= $row["amount"];}
		      if ($row["line_desc1"]               == "DirectorsFees")         {$DirectorsFees0         	= $row["amount"];}
		      if ($row["line_desc1"]               == "Depreciation")		   {$Depreciation0          	= $row["amount"];}
		      if ($row["line_desc1"]               == "Amortisation")		   {$Amortisation0          	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine1")       {$NoNameOpexLine1_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine2")       {$NoNameOpexLine2_0       	    = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherOpex")		       {$OtherOpex0             	= $row["amount"];}
              if ($row["line_desc1"]               == "NetFinanceCosts")       {$NetFinanceCosts0       	= $row["amount"];}
              if ($row["line_desc1"]               == "IncomeTax")             {$IncomeTax0             	= $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTax")           {$DeferredTax0           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine1")  {$OtherIncomeDescLine1_0   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine2")  {$OtherIncomeDescLine2_0   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine3")  {$OtherIncomeDescLine3_0   	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine4")  {$OtherIncomeDescLine4_0   	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine1")   {$NoNameOpexDescLine1_0  	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine2")   {$NoNameOpexDescLine2_0  	= $row["amount"];}
  		      if ($row["line_desc1"]               == "CosTotal")              {$CosTotal0              	= $row["amount"];}
		      if ($row["line_desc1"]               == "GrossProfit")           {$GrossProfit0           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeTotal")      {$OtherIncomeTotal0      	= $row["amount"];}
		      if ($row["line_desc1"]               == "OpexTotal")             {$OpexTotal0             	= $row["amount"];}
	          if ($row["line_desc1"]               == "EBIT")                  {$EBIT0                  	= $row["amount"];}
  	          if ($row["line_desc1"]               == "PBT")                   {$PBT0                   	= $row["amount"];}
	          if ($row["line_desc1"]               == "Taxation")              {$Taxation0              	= $row["amount"];}
	          if ($row["line_desc1"]               == "NetProfit")             {$NetProfit0             	= $row["amount"];}
              
  	         //2. CAPTURING CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "CashBal")               							{$CashBal0               						  = $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsReceivable")     						{$AccountsReceivable0     						  = $row["amount"];}
 		      if ($row["line_desc1"]               == "Prepayments")           							{$Prepayments0           						  = $row["amount"];}
		      if ($row["line_desc1"]               == "PrepaidTax")            							{$PrepaidTax0            						  = $row["amount"];}
		      if ($row["line_desc1"]               == "Inventory")             							{$Inventory0             						  = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine1")         {$OtherCurrentAssetsNonInterCompanyLine1_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine2")         {$OtherCurrentAssetsNonInterCompanyLine2_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine3")         {$OtherCurrentAssetsNonInterCompanyLine3_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine1")            {$OtherCurrentAssetsInterCompanyLine1_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine2")		    {$OtherCurrentAssetsInterCompanyLine2_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine3")		    {$OtherCurrentAssetsInterCompanyLine3_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine1")     {$OtherCurrentAssetsNonInterCompanyDescLine1_0    = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine2")     {$OtherCurrentAssetsNonInterCompanyDescLine2_0     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine3")		{$OtherCurrentAssetsNonInterCompanyDescLine3_0    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine1")        {$OtherCurrentAssetsInterCompanyDescLine1_0       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine2")        {$OtherCurrentAssetsInterCompanyDescLine2_0       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine3")        {$OtherCurrentAssetsInterCompanyDescLine3_0       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyTotal")         {$OtherCurrentAssetsNonInterCompanyTotal0         = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyTotal")            {$OtherCurrentAssetsInterCompanyTotal0            = $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentAssets")                             {$TotalCurrentAssets0                             = $row["amount"];}
		      
			  //3. CAPTURING CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "BankOverdraft")                  					 {$BankOverdraft0                     					= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsPayable")                					 {$AccountsPayable0                   					= $row["amount"];}
 		      if ($row["line_desc1"]               == "Accruals")                       					 {$Accruals0                          					= $row["amount"];}
		      if ($row["line_desc1"]               == "TaxPayable")                     					 {$TaxPayable0                        					= $row["amount"];}
		      if ($row["line_desc1"]               == "DividendsPayable")               					 {$DividendsPayable0                  					= $row["amount"];}
		      if ($row["line_desc1"]               == "CurrentPortionLongTermDebt")     					 {$CurrentPortionLongTermDebt0        					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine1")         {$OtherCurrentLiabilitiesNonInterCompanyLine1_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine2")         {$OtherCurrentLiabilitiesNonInterCompanyLine2_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine3")         {$OtherCurrentLiabilitiesNonInterCompanyLine3_0        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine1")		     {$OtherCurrentLiabilitiesInterCompanyLine1_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine2")		     {$OtherCurrentLiabilitiesInterCompanyLine2_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine3")            {$OtherCurrentLiabilitiesInterCompanyLine3_0           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine1")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine1_0     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine2")	 {$OtherCurrentLiabilitiesNonInterCompanyDescLine2_0    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine3")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine3_0    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine1")        {$OtherCurrentLiabilitiesInterCompanyDescLine1_0       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine2")        {$OtherCurrentLiabilitiesInterCompanyDescLine2_0       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine3")        {$OtherCurrentLiabilitiesInterCompanyDescLine3_0       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyTotal")         {$OtherCurrentLiabilitiesNonInterCompanyTotal0         = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyTotal")            {$OtherCurrentLiabilitiesInterCompanyTotal0            = $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentLiabilities")                             {$TotalCurrentLiabilities0                             = $row["amount"];}
		      
			  //4. CAPTURING NON CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "MortgageLoans")                       	{$MortgageLoans0               				= $row["amount"];}
		      if ($row["line_desc1"]               == "TermLoans")                           	{$TermLoans0     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Bonds")           						{$Bonds0          							= $row["amount"];}
		      if ($row["line_desc1"]               == "FinanceLeases")            				{$FinanceLeases0          					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherLongTermBorrowings")             	{$OtherLongTermBorrowings0             		= $row["amount"];}
		      if ($row["line_desc1"]               == "ShareholdersLoans")         				{$ShareholdersLoans0        				= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine1")         		{$InterCompanyLoansLine1_0        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine2")         		{$InterCompanyLoansLine2_0        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine3")           		{$InterCompanyLoansLine3_0           		= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine1")		{$OtherNonCurrentLiabilitiesLine1_0         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine2")		{$OtherNonCurrentLiabilitiesLine2_0         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine3")     	{$OtherNonCurrentLiabilitiesLine3_0    		= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine1")     		{$InterCompanyLoansDescLine1_0     			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine2")				{$InterCompanyLoansDescLine2_0    			= $row["amount"];}
              if ($row["line_desc1"]               == "InterCompanyLoansDescLine3")        		{$InterCompanyLoansDescLine3_0       		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine1")    {$OtherNonCurrentLiabilitiesDescLine1_0     = $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine2")    {$OtherNonCurrentLiabilitiesDescLine2_0     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine3")    {$OtherNonCurrentLiabilitiesDescLine3_0     = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsNonInterCompantTotal") {$LongTermBorrowingsNonInterCompantTotal0   = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsInterCompanyTotal")    {$LongTermBorrowingsInterCompanyTotal0      = $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansTotal")					{$InterCompanyLoansTotal0    				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalLongTermBorrowings")        		{$TotalLongTermBorrowings0       			= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesGrandTotal")   {$OtherNonCurrentLiabilitiesGrandTotal0     = $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTaxBalance")        				{$DeferredTaxBalance0       				= $row["amount"];}
		      if ($row["line_desc1"]               == "Provisions")         					{$Provisions0         						= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesTotal")        {$OtherNonCurrentLiabilitiesTotal0          = $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentLiabilities")             {$TotalNonCurrentLiabilities0               = $row["amount"];}
		      
			  //5. CAPTURING NON CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "OpeningNBV")               			{$OpeningNBV0               			= $row["amount"];}
		      if ($row["line_desc1"]               == "Additions")     						{$Additions0     						= $row["amount"];}
 		      if ($row["line_desc1"]               == "Revaluation")           				{$Revaluation0           				= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationTotal")            		{$DepreciationTotal0            		= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationCostOfSales")            {$DepreciationCostOfSales0             	= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationOpex")         			{$DepreciationOpex0        				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine1")         {$OtherNonCurrentAssetsLine1_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine2")         {$OtherNonCurrentAssetsLine2_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine3")         {$OtherNonCurrentAssetsLine3_0          = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine1")		{$OtherNonCurrentAssetsDescLine1_0      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine2")		{$OtherNonCurrentAssetsDescLine2_0      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine3")     {$OtherNonCurrentAssetsDescLine3_0    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherMovementsPPE")     				{$OtherMovementsPPE0     				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingNBV")							{$ClosingNBV0    						= $row["amount"];}
              if ($row["line_desc1"]               == "LandBuildingsNBV")        			{$LandBuildingsNBV0       				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalOtherPPE")        				{$TotalOtherPPE0       					= $row["amount"];}
              if ($row["line_desc1"]               == "IntangibleAssets")        			{$IntangibleAssets0       				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsTotal")         {$OtherNonCurrentAssetsTotal0         	= $row["amount"];}
              if ($row["line_desc1"]               == "InvestmentProperty")            		{$InvestmentProperty0            		= $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentAssets")              {$TotalNonCurrentAssets0                = $row["amount"];}
		      
			  //6. CAPTURING EQUITY DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "ShareCapital")               				{$ShareCapital0               					= $row["amount"];}
		      if ($row["line_desc1"]               == "SharePremium")     							{$SharePremium0     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "RevaluationReserve")           				{$RevaluationReserve0           				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingRetainedProfits")            			{$ClosingRetainedProfits0            			= $row["amount"];}
		      if ($row["line_desc1"]               == "OpeningRetainedProfits")             		{$OpeningRetainedProfits0             			= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine1")         {$OtherNonDistributableReservesLine1_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine2")         {$OtherNonDistributableReservesLine2_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine3")         {$OtherNonDistributableReservesLine3_0        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine1")            {$OtherDistributableReservesLine1_0           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine2")		    {$OtherDistributableReservesLine2_0           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine3")		    {$OtherDistributableReservesLine3_0           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine1")     {$OtherNonDistributableReservesDescLine1_0    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine2")     {$OtherNonDistributableReservesDescLine2_0     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine3")		{$OtherNonDistributableReservesDescLine3_0    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine1")        {$OtherDistributableReservesDescLine1_0       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine2")        {$OtherDistributableReservesDescLine2_0       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine3")        {$OtherDistributableReservesDescLine3_0       	= $row["amount"];}
		      if ($row["line_desc1"]               == "NetProfit")         							{$NetProfit0         							= $row["amount"];}
              if ($row["line_desc1"]               == "Dividends")           						{$Dividends0            						= $row["amount"];}
              if ($row["line_desc1"]               == "RetainedProfitsAdjustments")                 {$RetainedProfitsAdjustments0                   = $row["amount"];}
		 	  if ($row["line_desc1"]               == "OtherDistributableReservesTotal")         	{$OtherDistributableReservesTotal0         		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonDistributableReservesTotal")         {$OtherNonDistributableReservesTotal0           = $row["amount"];}
              if ($row["line_desc1"]               == "TotalEquity")                             	{$TotalEquity0                             		= $row["amount"];}
		 	  break;
		   
		   case $financial_year1:
			  //1. CAPTURING INCOME STATEMENT DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "NetSales")              {$NetSales1              	= $row["amount"];}
		      if ($row["line_desc1"]               == "CosDep")                {$CosDep1                	= $row["amount"];}
 		      if ($row["line_desc1"]               == "CosOther")              {$CosOther1              	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine1")      {$OtherIncomeLine1_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine2")      {$OtherIncomeLine2_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine3")      {$OtherIncomeLine3_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine4")      {$OtherIncomeLine4_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "StaffCosts")            {$StaffCosts1            	= $row["amount"];}
		      if ($row["line_desc1"]               == "DirectorsFees")         {$DirectorsFees1         	= $row["amount"];}
		      if ($row["line_desc1"]               == "Depreciation")		   {$Depreciation1          	= $row["amount"];}
		      if ($row["line_desc1"]               == "Amortisation")		   {$Amortisation1          	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine1")       {$NoNameOpexLine1_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine2")       {$NoNameOpexLine2_1       	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherOpex")		       {$OtherOpex1             	= $row["amount"];}
              if ($row["line_desc1"]               == "NetFinanceCosts")       {$NetFinanceCosts1       	= $row["amount"];}
              if ($row["line_desc1"]               == "IncomeTax")             {$IncomeTax1             	= $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTax")           {$DeferredTax1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine1")  {$OtherIncomeDescLine1_1   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine2")  {$OtherIncomeDescLine2_1   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine3")  {$OtherIncomeDescLine3_1   	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine4")  {$OtherIncomeDescLine4_1   	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine1")   {$NoNameOpexDescLine1_1  	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine2")   {$NoNameOpexDescLine2_1  	= $row["amount"];}
  		      if ($row["line_desc1"]               == "CosTotal")              {$CosTotal1              	= $row["amount"];}
		      if ($row["line_desc1"]               == "GrossProfit")           {$GrossProfit1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeTotal")      {$OtherIncomeTotal1      	= $row["amount"];}
		      if ($row["line_desc1"]               == "OpexTotal")             {$OpexTotal1             	= $row["amount"];}
	          if ($row["line_desc1"]               == "EBIT")                  {$EBIT1                  	= $row["amount"];}
  	          if ($row["line_desc1"]               == "PBT")                   {$PBT1                   	= $row["amount"];}
	          if ($row["line_desc1"]               == "Taxation")              {$Taxation1              	= $row["amount"];}
	          if ($row["line_desc1"]               == "NetProfit")             {$NetProfit1             	= $row["amount"];}
              
  	         //2. CAPTURING CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "CashBal")               							{$CashBal1               							= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsReceivable")     						{$AccountsReceivable1     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Prepayments")           							{$Prepayments1           							= $row["amount"];}
		      if ($row["line_desc1"]               == "PrepaidTax")            							{$PrepaidTax1            							= $row["amount"];}
		      if ($row["line_desc1"]               == "Inventory")             							{$Inventory1             							= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine1")         {$OtherCurrentAssetsNonInterCompanyLine1_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine2")         {$OtherCurrentAssetsNonInterCompanyLine2_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine3")         {$OtherCurrentAssetsNonInterCompanyLine3_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine1")            {$OtherCurrentAssetsInterCompanyLine1_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine2")		    {$OtherCurrentAssetsInterCompanyLine2_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine3")		    {$OtherCurrentAssetsInterCompanyLine3_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine1")     {$OtherCurrentAssetsNonInterCompanyDescLine1_1    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine2")     {$OtherCurrentAssetsNonInterCompanyDescLine2_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine3")		{$OtherCurrentAssetsNonInterCompanyDescLine3_1    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine1")        {$OtherCurrentAssetsInterCompanyDescLine1_1       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine2")        {$OtherCurrentAssetsInterCompanyDescLine2_1       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine3")        {$OtherCurrentAssetsInterCompanyDescLine3_1       	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyTotal")         {$OtherCurrentAssetsNonInterCompanyTotal1         	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyTotal")            {$OtherCurrentAssetsInterCompanyTotal1            	= $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentAssets")                             {$TotalCurrentAssets1                             	= $row["amount"];}
		      
			  //3. CAPTURING CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "BankOverdraft")                  					 {$BankOverdraft1                     					= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsPayable")                					 {$AccountsPayable1                   					= $row["amount"];}
 		      if ($row["line_desc1"]               == "Accruals")                       					 {$Accruals1                          					= $row["amount"];}
		      if ($row["line_desc1"]               == "TaxPayable")                     					 {$TaxPayable1                        					= $row["amount"];}
		      if ($row["line_desc1"]               == "DividendsPayable")               					 {$DividendsPayable1                  					= $row["amount"];}
		      if ($row["line_desc1"]               == "CurrentPortionLongTermDebt")     					 {$CurrentPortionLongTermDebt1        					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine1")         {$OtherCurrentLiabilitiesNonInterCompanyLine1_1        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine2")         {$OtherCurrentLiabilitiesNonInterCompanyLine2_1        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine3")         {$OtherCurrentLiabilitiesNonInterCompanyLine3_1        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine1")		     {$OtherCurrentLiabilitiesInterCompanyLine1_1           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine2")		     {$OtherCurrentLiabilitiesInterCompanyLine2_1           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine3")            {$OtherCurrentLiabilitiesInterCompanyLine3_1           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine1")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine1_1     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine2")	 {$OtherCurrentLiabilitiesNonInterCompanyDescLine2_1    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine3")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine3_1    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine1")        {$OtherCurrentLiabilitiesInterCompanyDescLine1_1       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine2")        {$OtherCurrentLiabilitiesInterCompanyDescLine2_1       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine3")        {$OtherCurrentLiabilitiesInterCompanyDescLine3_1       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyTotal")         {$OtherCurrentLiabilitiesNonInterCompanyTotal1         = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyTotal")            {$OtherCurrentLiabilitiesInterCompanyTotal1            = $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentLiabilities")                             {$TotalCurrentLiabilities1                             = $row["amount"];}
		      
			  //4. CAPTURING NON CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "MortgageLoans")                       	{$MortgageLoans1               				= $row["amount"];}
		      if ($row["line_desc1"]               == "TermLoans")                           	{$TermLoans1     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Bonds")           						{$Bonds1          							= $row["amount"];}
		      if ($row["line_desc1"]               == "FinanceLeases")            				{$FinanceLeases1          					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherLongTermBorrowings")             	{$OtherLongTermBorrowings1             		= $row["amount"];}
		      if ($row["line_desc1"]               == "ShareholdersLoans")         				{$ShareholdersLoans1        				= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine1")         		{$InterCompanyLoansLine1_1        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine2")         		{$InterCompanyLoansLine2_1        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine3")           		{$InterCompanyLoansLine3_1           		= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine1")		{$OtherNonCurrentLiabilitiesLine1_1         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine2")		{$OtherNonCurrentLiabilitiesLine2_1         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine3")     	{$OtherNonCurrentLiabilitiesLine3_1    		= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine1")     		{$InterCompanyLoansDescLine1_1     			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine2")				{$InterCompanyLoansDescLine2_1    			= $row["amount"];}
              if ($row["line_desc1"]               == "InterCompanyLoansDescLine3")        		{$InterCompanyLoansDescLine3_1       		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine1")    {$OtherNonCurrentLiabilitiesDescLine1_1     = $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine2")    {$OtherNonCurrentLiabilitiesDescLine2_1     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine3")    {$OtherNonCurrentLiabilitiesDescLine3_1     = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsNonInterCompantTotal") {$LongTermBorrowingsNonInterCompantTotal1   = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsInterCompanyTotal")    {$LongTermBorrowingsInterCompanyTotal1      = $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansTotal")					{$InterCompanyLoansTotal1    				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalLongTermBorrowings")        		{$TotalLongTermBorrowings1       			= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesGrandTotal")   {$OtherNonCurrentLiabilitiesGrandTotal1    = $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTaxBalance")        				{$DeferredTaxBalance1       				= $row["amount"];}
		      if ($row["line_desc1"]               == "Provisions")         					{$Provisions1         						= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesTotal")        {$OtherNonCurrentLiabilitiesTotal1          = $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentLiabilities")             {$TotalNonCurrentLiabilities1               = $row["amount"];}
		      
			  //5. CAPTURING NON CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "OpeningNBV")               			{$OpeningNBV1               			= $row["amount"];}
		      if ($row["line_desc1"]               == "Additions")     						{$Additions1     						= $row["amount"];}
 		      if ($row["line_desc1"]               == "Revaluation")           				{$Revaluation1           				= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationTotal")            		{$DepreciationTotal1            		= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationCostOfSales")            {$DepreciationCostOfSales1             	= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationOpex")         			{$DepreciationOpex1        				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine1")         {$OtherNonCurrentAssetsLine1_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine2")         {$OtherNonCurrentAssetsLine2_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine3")         {$OtherNonCurrentAssetsLine3_1          = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine1")		{$OtherNonCurrentAssetsDescLine1_1      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine2")		{$OtherNonCurrentAssetsDescLine2_1      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine3")     {$OtherNonCurrentAssetsDescLine3_1    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherMovementsPPE")     				{$OtherMovementsPPE1     				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingNBV")							{$ClosingNBV1    						= $row["amount"];}
              if ($row["line_desc1"]               == "LandBuildingsNBV")        			{$LandBuildingsNBV1       				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalOtherPPE")        				{$TotalOtherPPE1       					= $row["amount"];}
              if ($row["line_desc1"]               == "IntangibleAssets")        			{$IntangibleAssets1       				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsTotal")         {$OtherNonCurrentAssetsTotal1         	= $row["amount"];}
              if ($row["line_desc1"]               == "InvestmentProperty")            		{$InvestmentProperty1            		= $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentAssets")              {$TotalNonCurrentAssets1                = $row["amount"];}
		      
			  //6. CAPTURING EQUITY DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "ShareCapital")               				{$ShareCapital1               					= $row["amount"];}
		      if ($row["line_desc1"]               == "SharePremium")     							{$SharePremium1     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "RevaluationReserve")           				{$RevaluationReserve1           				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingRetainedProfits")            			{$ClosingRetainedProfits1            			= $row["amount"];}
		      if ($row["line_desc1"]               == "OpeningRetainedProfits")             		{$OpeningRetainedProfits1             			= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine1")         {$OtherNonDistributableReservesLine1_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine2")         {$OtherNonDistributableReservesLine2_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine3")         {$OtherNonDistributableReservesLine3_1        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine1")            {$OtherDistributableReservesLine1_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine2")		    {$OtherDistributableReservesLine2_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine3")		    {$OtherDistributableReservesLine3_1           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine1")     {$OtherNonDistributableReservesDescLine1_1    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine2")     {$OtherNonDistributableReservesDescLine2_1     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine3")		{$OtherNonDistributableReservesDescLine3_1    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine1")        {$OtherDistributableReservesDescLine1_1       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine2")        {$OtherDistributableReservesDescLine2_1       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine3")        {$OtherDistributableReservesDescLine3_1       	= $row["amount"];}
		      if ($row["line_desc1"]               == "NetProfit")         							{$NetProfit1         							= $row["amount"];}
              if ($row["line_desc1"]               == "Dividends")           						{$Dividends1            						= $row["amount"];}
              if ($row["line_desc1"]               == "RetainedProfitsAdjustments")                 {$RetainedProfitsAdjustments1                   = $row["amount"];}
		 	  if ($row["line_desc1"]               == "OtherDistributableReservesTotal")         	{$OtherDistributableReservesTotal1         		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonDistributableReservesTotal")         {$OtherNonDistributableReservesTotal1           = $row["amount"];}
              if ($row["line_desc1"]               == "TotalEquity")                             	{$TotalEquity1                             		= $row["amount"];}
		 	  break;
			  
		   case $financial_year2:
		   //1. CAPTURING INCOME STATEMENT DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "NetSales")              {$NetSales2              	= $row["amount"];}
		      if ($row["line_desc1"]               == "CosDep")                {$CosDep2                	= $row["amount"];}
 		      if ($row["line_desc1"]               == "CosOther")              {$CosOther2              	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine1")      {$OtherIncomeLine1_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine2")      {$OtherIncomeLine2_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine3")      {$OtherIncomeLine3_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine4")      {$OtherIncomeLine4_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "StaffCosts")            {$StaffCosts2            	= $row["amount"];}
		      if ($row["line_desc1"]               == "DirectorsFees")         {$DirectorsFees2         	= $row["amount"];}
		      if ($row["line_desc1"]               == "Depreciation")		   {$Depreciation2          	= $row["amount"];}
		      if ($row["line_desc1"]               == "Amortisation")		   {$Amortisation2          	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine1")       {$NoNameOpexLine1_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine2")       {$NoNameOpexLine2_2       	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherOpex")		       {$OtherOpex2             	= $row["amount"];}
              if ($row["line_desc1"]               == "NetFinanceCosts")       {$NetFinanceCosts2       	= $row["amount"];}
              if ($row["line_desc1"]               == "IncomeTax")             {$IncomeTax2             	= $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTax")           {$DeferredTax2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine1")  {$OtherIncomeDescLine1_2   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine2")  {$OtherIncomeDescLine2_2   	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine3")  {$OtherIncomeDescLine3_2   	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine4")  {$OtherIncomeDescLine4_2   	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine1")   {$NoNameOpexDescLine1_2  	= $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine2")   {$NoNameOpexDescLine2_2  	= $row["amount"];}
  		      if ($row["line_desc1"]               == "CosTotal")              {$CosTotal2              	= $row["amount"];}
		      if ($row["line_desc1"]               == "GrossProfit")           {$GrossProfit2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeTotal")      {$OtherIncomeTotal2      	= $row["amount"];}
		      if ($row["line_desc1"]               == "OpexTotal")             {$OpexTotal2             	= $row["amount"];}
	          if ($row["line_desc1"]               == "EBIT")                  {$EBIT2                  	= $row["amount"];}
  	          if ($row["line_desc1"]               == "PBT")                   {$PBT2                   	= $row["amount"];}
	          if ($row["line_desc1"]               == "Taxation")              {$Taxation2              	= $row["amount"];}
	          if ($row["line_desc1"]               == "NetProfit")             {$NetProfit2             	= $row["amount"];}
              
  	         //2. CAPTURING CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "CashBal")               							{$CashBal2               							= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsReceivable")     						{$AccountsReceivable2     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Prepayments")           							{$Prepayments2           							= $row["amount"];}
		      if ($row["line_desc1"]               == "PrepaidTax")            							{$PrepaidTax2            							= $row["amount"];}
		      if ($row["line_desc1"]               == "Inventory")             							{$Inventory2             							= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine1")         {$OtherCurrentAssetsNonInterCompanyLine1_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine2")         {$OtherCurrentAssetsNonInterCompanyLine2_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine3")         {$OtherCurrentAssetsNonInterCompanyLine3_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine1")            {$OtherCurrentAssetsInterCompanyLine1_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine2")		    {$OtherCurrentAssetsInterCompanyLine2_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine3")		    {$OtherCurrentAssetsInterCompanyLine3_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine1")     {$OtherCurrentAssetsNonInterCompanyDescLine1_2    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine2")     {$OtherCurrentAssetsNonInterCompanyDescLine2_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine3")		{$OtherCurrentAssetsNonInterCompanyDescLine3_2    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine1")        {$OtherCurrentAssetsInterCompanyDescLine1_2       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine2")        {$OtherCurrentAssetsInterCompanyDescLine2_2       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine3")        {$OtherCurrentAssetsInterCompanyDescLine3_2       	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyTotal")         {$OtherCurrentAssetsNonInterCompanyTotal2         	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyTotal")            {$OtherCurrentAssetsInterCompanyTotal2            	= $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentAssets")                             {$TotalCurrentAssets2                             	= $row["amount"];}
		      
			  //3. CAPTURING CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "BankOverdraft")                  					 {$BankOverdraft2                     					= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsPayable")                					 {$AccountsPayable2                   					= $row["amount"];}
 		      if ($row["line_desc1"]               == "Accruals")                       					 {$Accruals2                          					= $row["amount"];}
		      if ($row["line_desc1"]               == "TaxPayable")                     					 {$TaxPayable2                        					= $row["amount"];}
		      if ($row["line_desc1"]               == "DividendsPayable")               					 {$DividendsPayable2                  					= $row["amount"];}
		      if ($row["line_desc1"]               == "CurrentPortionLongTermDebt")     					 {$CurrentPortionLongTermDebt2        					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine1")         {$OtherCurrentLiabilitiesNonInterCompanyLine1_2        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine2")         {$OtherCurrentLiabilitiesNonInterCompanyLine2_2        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine3")         {$OtherCurrentLiabilitiesNonInterCompanyLine3_2        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine1")		     {$OtherCurrentLiabilitiesInterCompanyLine1_2           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine2")		     {$OtherCurrentLiabilitiesInterCompanyLine2_2           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine3")            {$OtherCurrentLiabilitiesInterCompanyLine3_2           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine1")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine1_2     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine2")	 {$OtherCurrentLiabilitiesNonInterCompanyDescLine2_2    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine3")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine3_2    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine1")        {$OtherCurrentLiabilitiesInterCompanyDescLine1_2       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine2")        {$OtherCurrentLiabilitiesInterCompanyDescLine2_2       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine3")        {$OtherCurrentLiabilitiesInterCompanyDescLine3_2       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyTotal")         {$OtherCurrentLiabilitiesNonInterCompanyTotal2         = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyTotal")            {$OtherCurrentLiabilitiesInterCompanyTotal2            = $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentLiabilities")                             {$TotalCurrentLiabilities2                             = $row["amount"];}
		      
			  //4. CAPTURING NON CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "MortgageLoans")                       	{$MortgageLoans2               				= $row["amount"];}
		      if ($row["line_desc1"]               == "TermLoans")                           	{$TermLoans2     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Bonds")           						{$Bonds2          							= $row["amount"];}
		      if ($row["line_desc1"]               == "FinanceLeases")            				{$FinanceLeases2          					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherLongTermBorrowings")             	{$OtherLongTermBorrowings2             		= $row["amount"];}
		      if ($row["line_desc1"]               == "ShareholdersLoans")         				{$ShareholdersLoans2        				= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine1")         		{$InterCompanyLoansLine1_2        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine2")         		{$InterCompanyLoansLine2_2        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine3")           		{$InterCompanyLoansLine3_2           		= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine1")		{$OtherNonCurrentLiabilitiesLine1_2         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine2")		{$OtherNonCurrentLiabilitiesLine2_2         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine3")     	{$OtherNonCurrentLiabilitiesLine3_2    		= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine1")     		{$InterCompanyLoansDescLine1_2     			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine2")				{$InterCompanyLoansDescLine2_2    			= $row["amount"];}
              if ($row["line_desc1"]               == "InterCompanyLoansDescLine3")        		{$InterCompanyLoansDescLine3_2       		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine1")    {$OtherNonCurrentLiabilitiesDescLine1_2     = $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine2")    {$OtherNonCurrentLiabilitiesDescLine2_2     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine3")    {$OtherNonCurrentLiabilitiesDescLine3_2     = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsNonInterCompantTotal") {$LongTermBorrowingsNonInterCompantTotal2   = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsInterCompanyTotal")    {$LongTermBorrowingsInterCompanyTotal2      = $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansTotal")					{$InterCompanyLoansTotal2    				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalLongTermBorrowings")        		{$TotalLongTermBorrowings2       			= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesGrandTotal")   {$OtherNonCurrentLiabilitiesGrandTotal2    = $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTaxBalance")        				{$DeferredTaxBalance2       				= $row["amount"];}
		      if ($row["line_desc1"]               == "Provisions")         					{$Provisions2         						= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesTotal")        {$OtherNonCurrentLiabilitiesTotal2          = $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentLiabilities")             {$TotalNonCurrentLiabilities2               = $row["amount"];}
		      
			  //5. CAPTURING NON CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "OpeningNBV")               			{$OpeningNBV2               			= $row["amount"];}
		      if ($row["line_desc1"]               == "Additions")     						{$Additions2     						= $row["amount"];}
 		      if ($row["line_desc1"]               == "Revaluation")           				{$Revaluation2           				= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationTotal")            		{$DepreciationTotal2            		= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationCostOfSales")            {$DepreciationCostOfSales2             	= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationOpex")         			{$DepreciationOpex2        				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine1")         {$OtherNonCurrentAssetsLine1_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine2")         {$OtherNonCurrentAssetsLine2_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine3")         {$OtherNonCurrentAssetsLine3_2          = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine1")		{$OtherNonCurrentAssetsDescLine1_2      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine2")		{$OtherNonCurrentAssetsDescLine2_2      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine3")     {$OtherNonCurrentAssetsDescLine3_2    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherMovementsPPE")     				{$OtherMovementsPPE2     				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingNBV")							{$ClosingNBV2    						= $row["amount"];}
              if ($row["line_desc1"]               == "LandBuildingsNBV")        			{$LandBuildingsNBV2       				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalOtherPPE")        				{$TotalOtherPPE2       					= $row["amount"];}
              if ($row["line_desc1"]               == "IntangibleAssets")        			{$IntangibleAssets2       				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsTotal")         {$OtherNonCurrentAssetsTotal2         	= $row["amount"];}
              if ($row["line_desc1"]               == "InvestmentProperty")            		{$InvestmentProperty2            		= $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentAssets")              {$TotalNonCurrentAssets2                = $row["amount"];}
		      
			  //6. CAPTURING EQUITY DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "ShareCapital")               				{$ShareCapital2               					= $row["amount"];}
		      if ($row["line_desc1"]               == "SharePremium")     							{$SharePremium2     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "RevaluationReserve")           				{$RevaluationReserve2           				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingRetainedProfits")            			{$ClosingRetainedProfits2            			= $row["amount"];}
		      if ($row["line_desc1"]               == "OpeningRetainedProfits")             		{$OpeningRetainedProfits2             			= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine1")         {$OtherNonDistributableReservesLine1_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine2")         {$OtherNonDistributableReservesLine2_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine3")         {$OtherNonDistributableReservesLine3_2        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine1")            {$OtherDistributableReservesLine1_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine2")		    {$OtherDistributableReservesLine2_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine3")		    {$OtherDistributableReservesLine3_2           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine1")     {$OtherNonDistributableReservesDescLine1_2    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine2")     {$OtherNonDistributableReservesDescLine2_2     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine3")		{$OtherNonDistributableReservesDescLine3_2    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine1")        {$OtherDistributableReservesDescLine1_2       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine2")        {$OtherDistributableReservesDescLine2_2       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine3")        {$OtherDistributableReservesDescLine3_2       	= $row["amount"];}
		      if ($row["line_desc1"]               == "NetProfit")         							{$NetProfit2         							= $row["amount"];}
              if ($row["line_desc1"]               == "Dividends")           						{$Dividends2            						= $row["amount"];}
              if ($row["line_desc1"]               == "RetainedProfitsAdjustments")                 {$RetainedProfitsAdjustments2                   = $row["amount"];}
		 	  if ($row["line_desc1"]               == "OtherDistributableReservesTotal")         	{$OtherDistributableReservesTotal2         		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonDistributableReservesTotal")         {$OtherNonDistributableReservesTotal2           = $row["amount"];}
              if ($row["line_desc1"]               == "TotalEquity")                             	{$TotalEquity2                             		= $row["amount"];}
		 	  break;
			  
		   case $financial_year3:
			  
			  if ($row["line_desc1"]               == "NetSales")              {$NetSales3              = $row["amount"];}
		      if ($row["line_desc1"]               == "CosDep")                {$CosDep3                = $row["amount"];}
 		      if ($row["line_desc1"]               == "CosOther")              {$CosOther3              = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine1")      {$OtherIncomeLine1_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine2")      {$OtherIncomeLine2_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine3")      {$OtherIncomeLine3_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeLine4")      {$OtherIncomeLine4_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "StaffCosts")            {$StaffCosts3            = $row["amount"];}
		      if ($row["line_desc1"]               == "DirectorsFees")         {$DirectorsFees3         = $row["amount"];}
		      if ($row["line_desc1"]               == "Depreciation")		   {$Depreciation3          = $row["amount"];}
		      if ($row["line_desc1"]               == "Amortisation")		   {$Amortisation3          = $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine1")       {$NoNameOpexLine1_3        = $row["amount"];}
		      if ($row["line_desc1"]               == "NoNameOpexLine2")       {$NoNameOpexLine2_3       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherOpex")		       {$OtherOpex3             = $row["amount"];}
              if ($row["line_desc1"]               == "NetFinanceCosts")       {$NetFinanceCosts3       = $row["amount"];}
              if ($row["line_desc1"]               == "IncomeTax")             {$IncomeTax3             = $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTax")           {$DeferredTax3           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine1")  {$OtherIncomeDescLine1   = $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine2")  {$OtherIncomeDescLine2   = $row["amount"];}
              if ($row["line_desc1"]               == "OtherIncomeDescLine3")  {$OtherIncomeDescLine3   = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeDescLine4")  {$OtherIncomeDescLine4   = $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine1")   {$NoNameOpexDescLine1_3  = $row["amount"];}
              if ($row["line_desc1"]               == "NoNameOpexDescLine2")   {$NoNameOpexDescLine2_3  = $row["amount"];}
  		      if ($row["line_desc1"]               == "CosTotal")              {$CosTotal3              = $row["amount"];}
		      if ($row["line_desc1"]               == "GrossProfit")           {$GrossProfit3           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherIncomeTotal")      {$OtherIncomeTotal3      = $row["amount"];}
		      if ($row["line_desc1"]               == "OpexTotal")             {$OpexTotal3             = $row["amount"];}
	          if ($row["line_desc1"]               == "EBIT")                  {$EBIT3                  = $row["amount"];}
  	          if ($row["line_desc1"]               == "PBT")                   {$PBT3                   = $row["amount"];}
	          if ($row["line_desc1"]               == "Taxation")              {$Taxation3              = $row["amount"];}
	          if ($row["line_desc1"]               == "NetProfit")             {$NetProfit3             = $row["amount"];}
			  
			       //2. CAPTURING CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "CashBal")               							{$CashBal3               							= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsReceivable")     						{$AccountsReceivable3     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Prepayments")           							{$Prepayments3           							= $row["amount"];}
		      if ($row["line_desc1"]               == "PrepaidTax")            							{$PrepaidTax3            							= $row["amount"];}
		      if ($row["line_desc1"]               == "Inventory")             							{$Inventory3             							= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine1")         {$OtherCurrentAssetsNonInterCompanyLine1_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine2")         {$OtherCurrentAssetsNonInterCompanyLine2_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyLine3")         {$OtherCurrentAssetsNonInterCompanyLine3_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine1")            {$OtherCurrentAssetsInterCompanyLine1_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine2")		    {$OtherCurrentAssetsInterCompanyLine2_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyLine3")		    {$OtherCurrentAssetsInterCompanyLine3_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine1")     {$OtherCurrentAssetsNonInterCompanyDescLine1_3    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine2")     {$OtherCurrentAssetsNonInterCompanyDescLine2_3     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyDescLine3")		{$OtherCurrentAssetsNonInterCompanyDescLine3_3    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine1")        {$OtherCurrentAssetsInterCompanyDescLine1_3       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine2")        {$OtherCurrentAssetsInterCompanyDescLine2_3       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyDescLine3")        {$OtherCurrentAssetsInterCompanyDescLine3_3       	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentAssetsNonIntercompanyTotal")         {$OtherCurrentAssetsNonInterCompanyTotal3         	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentAssetsIntercompanyTotal")            {$OtherCurrentAssetsInterCompanyTotal3            	= $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentAssets")                             {$TotalCurrentAssets3                             	= $row["amount"];}
		      
			  //3. CAPTURING CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "BankOverdraft")                  					 {$BankOverdraft3                     					= $row["amount"];}
		      if ($row["line_desc1"]               == "AccountsPayable")                					 {$AccountsPayable3                   					= $row["amount"];}
 		      if ($row["line_desc1"]               == "Accruals")                       					 {$Accruals3                          					= $row["amount"];}
		      if ($row["line_desc1"]               == "TaxPayable")                     					 {$TaxPayable3                        					= $row["amount"];}
		      if ($row["line_desc1"]               == "DividendsPayable")               					 {$DividendsPayable3                  					= $row["amount"];}
		      if ($row["line_desc1"]               == "CurrentPortionLongTermDebt")     					 {$CurrentPortionLongTermDebt3        					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine1")         {$OtherCurrentLiabilitiesNonInterCompanyLine1_3        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine2")         {$OtherCurrentLiabilitiesNonInterCompanyLine2_3        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyLine3")         {$OtherCurrentLiabilitiesNonInterCompanyLine3_3        = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine1")		     {$OtherCurrentLiabilitiesInterCompanyLine1_3           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine2")		     {$OtherCurrentLiabilitiesInterCompanyLine2_3           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyLine3")            {$OtherCurrentLiabilitiesInterCompanyLine3_3           = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine1")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine1_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine2")	 {$OtherCurrentLiabilitiesNonInterCompanyDescLine2_3    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyDescLine3")     {$OtherCurrentLiabilitiesNonInterCompanyDescLine3_3    = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine1")        {$OtherCurrentLiabilitiesInterCompanyDescLine1_3       = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine2")        {$OtherCurrentLiabilitiesInterCompanyDescLine2_3       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyDescLine3")        {$OtherCurrentLiabilitiesInterCompanyDescLine3_3       = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherCurrentLiabilitiesNonIntercompanyTotal")         {$OtherCurrentLiabilitiesNonInterCompanyTotal3         = $row["amount"];}
              if ($row["line_desc1"]               == "OtherCurrentLiabilitiesIntercompanyTotal")            {$OtherCurrentLiabilitiesInterCompanyTotal3            = $row["amount"];}
              if ($row["line_desc1"]               == "TotalCurrentLiabilities")                             {$TotalCurrentLiabilities3                             = $row["amount"];}
		      
			  //4. CAPTURING NON CURRENT LIABILITIES DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "MortgageLoans")                       	{$MortgageLoans3               				= $row["amount"];}
		      if ($row["line_desc1"]               == "TermLoans")                           	{$TermLoans3     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "Bonds")           						{$Bonds3          							= $row["amount"];}
		      if ($row["line_desc1"]               == "FinanceLeases")            				{$FinanceLeases3          					= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherLongTermBorrowings")             	{$OtherLongTermBorrowings3             		= $row["amount"];}
		      if ($row["line_desc1"]               == "ShareholdersLoans")         				{$ShareholdersLoans3        				= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine1")         		{$InterCompanyLoansLine1_3        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine2")         		{$InterCompanyLoansLine2_3        			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansLine3")           		{$InterCompanyLoansLine3_3           		= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine1")		{$OtherNonCurrentLiabilitiesLine1_3         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine2")		{$OtherNonCurrentLiabilitiesLine2_3         = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesLine3")     	{$OtherNonCurrentLiabilitiesLine3_3    		= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine1")     		{$InterCompanyLoansDescLine1_3     			= $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansDescLine2")				{$InterCompanyLoansDescLine2_3    			= $row["amount"];}
              if ($row["line_desc1"]               == "InterCompanyLoansDescLine3")        		{$InterCompanyLoansDescLine3_3       		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine1")    {$OtherNonCurrentLiabilitiesDescLine1_3     = $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine2")    {$OtherNonCurrentLiabilitiesDescLine2_3     = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesDescLine3")    {$OtherNonCurrentLiabilitiesDescLine3_3     = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsNonInterCompantTotal") {$LongTermBorrowingsNonInterCompantTotal3   = $row["amount"];}
              if ($row["line_desc1"]               == "LongTermBorrowingsInterCompanyTotal")    {$LongTermBorrowingsInterCompanyTotal3      = $row["amount"];}
		      if ($row["line_desc1"]               == "InterCompanyLoansTotal")					{$InterCompanyLoansTotal3    				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalLongTermBorrowings")        		{$TotalLongTermBorrowings3       			= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesGrandTotal")   {$OtherNonCurrentLiabilitiesGrandTotal3    = $row["amount"];}
              if ($row["line_desc1"]               == "DeferredTaxBalance")        				{$DeferredTaxBalance3       				= $row["amount"];}
		      if ($row["line_desc1"]               == "Provisions")         					{$Provisions3         						= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonCurrentLiabilitiesTotal")        {$OtherNonCurrentLiabilitiesTotal3          = $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentLiabilities")             {$TotalNonCurrentLiabilities3               = $row["amount"];}
		      
			  //5. CAPTURING NON CURRENT ASSETS DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "OpeningNBV")               			{$OpeningNBV3               			= $row["amount"];}
		      if ($row["line_desc1"]               == "Additions")     						{$Additions3     						= $row["amount"];}
 		      if ($row["line_desc1"]               == "Revaluation")           				{$Revaluation3           				= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationTotal")            		{$DepreciationTotal3            		= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationCostOfSales")            {$DepreciationCostOfSales3             	= $row["amount"];}
		      if ($row["line_desc1"]               == "DepreciationOpex")         			{$DepreciationOpex3        				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine1")         {$OtherNonCurrentAssetsLine1_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine2")         {$OtherNonCurrentAssetsLine2_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsLine3")         {$OtherNonCurrentAssetsLine3_3          = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine1")		{$OtherNonCurrentAssetsDescLine1_3      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine2")		{$OtherNonCurrentAssetsDescLine2_3      = $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsDescLine3")     {$OtherNonCurrentAssetsDescLine3_3    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherMovementsPPE")     				{$OtherMovementsPPE3     				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingNBV")							{$ClosingNBV3    						= $row["amount"];}
              if ($row["line_desc1"]               == "LandBuildingsNBV")        			{$LandBuildingsNBV3       				= $row["amount"];}
              if ($row["line_desc1"]               == "TotalOtherPPE")        				{$TotalOtherPPE3       					= $row["amount"];}
              if ($row["line_desc1"]               == "IntangibleAssets")        			{$IntangibleAssets3       				= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonCurrentAssetsTotal")         {$OtherNonCurrentAssetsTotal3         	= $row["amount"];}
              if ($row["line_desc1"]               == "InvestmentProperty")            		{$InvestmentProperty3            		= $row["amount"];}
              if ($row["line_desc1"]               == "TotalNonCurrentAssets")              {$TotalNonCurrentAssets3                = $row["amount"];}
		      
			  //6. CAPTURING EQUITY DATA IN EACH RECORD PASS=========================================================================================
			  if ($row["line_desc1"]               == "ShareCapital")               				{$ShareCapital3               					= $row["amount"];}
		      if ($row["line_desc1"]               == "SharePremium")     							{$SharePremium3     							= $row["amount"];}
 		      if ($row["line_desc1"]               == "RevaluationReserve")           				{$RevaluationReserve3           				= $row["amount"];}
		      if ($row["line_desc1"]               == "ClosingRetainedProfits")            			{$ClosingRetainedProfits3            			= $row["amount"];}
		      if ($row["line_desc1"]               == "OpeningRetainedProfits")             		{$OpeningRetainedProfits3             			= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine1")         {$OtherNonDistributableReservesLine1_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine2")         {$OtherNonDistributableReservesLine2_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesLine3")         {$OtherNonDistributableReservesLine3_3        	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine1")            {$OtherDistributableReservesLine1_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine2")		    {$OtherDistributableReservesLine2_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherDistributableReservesLine3")		    {$OtherDistributableReservesLine3_3           	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine1")     {$OtherNonDistributableReservesDescLine1_3    	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine2")     {$OtherNonDistributableReservesDescLine2_3     	= $row["amount"];}
		      if ($row["line_desc1"]               == "OtherNonDistributableReservesDescLine3")		{$OtherNonDistributableReservesDescLine3_3    	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine1")        {$OtherDistributableReservesDescLine1_3       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine2")        {$OtherDistributableReservesDescLine2_3       	= $row["amount"];}
              if ($row["line_desc1"]               == "OtherDistributableReservesDescLine3")        {$OtherDistributableReservesDescLine3_3       	= $row["amount"];}
		      if ($row["line_desc1"]               == "NetProfit")         							{$NetProfit3         							= $row["amount"];}
              if ($row["line_desc1"]               == "Dividends")           						{$Dividends3            						= $row["amount"];}
              if ($row["line_desc1"]               == "RetainedProfitsAdjustments")                 {$RetainedProfitsAdjustments3                   = $row["amount"];}
		 	  if ($row["line_desc1"]               == "OtherDistributableReservesTotal")         	{$OtherDistributableReservesTotal3         		= $row["amount"];}
              if ($row["line_desc1"]               == "OtherNonDistributableReservesTotal")         {$OtherNonDistributableReservesTotal3           = $row["amount"];}
              if ($row["line_desc1"]               == "TotalEquity")                             	{$TotalEquity3                             		= $row["amount"];}
		 	  break;
			  
		
             
  	
		}	
    
}   // closing If update_type = Application     
?>
