<?php
  $company_data_pdate                               =  $row["company_data_pdate"];
  $company_data_username                            =  $row["company_data_username"];
  $loan_data_pdate                                  =  $row["loan_data_pdate"];
  $loan_data_username                               =  $row["loan_data_username"];
  $income_statement_pdate                           =  $row["income_statement_pdate"];
  $income_statement_username                        =  $row["income_statement_username"];
  $current_assets_pdate                             =  $row["current_assets_pdate"];
  $current_assets_username                          =  $row["current_assets_username"];
  $non_current_assets_pdate                         =  $row["non_current_assets_pdate"];
  $non_current_assets_username                      =  $row["non_current_assets_username"];
  $current_liabilities_pdate                        =  $row["current_liabilities_pdate"];
  $current_liabilities_username                     =  $row["current_liabilities_username"];
  $non_current_liabilities_pdate                    =  $row["non_current_liabilities_pdate"];
  $non_current_liabilities_username                 =  $row["non_current_liabilities_username"];
  $equity_pdate                                     =  $row["equity_pdate"];
  $equity_username                                  =  $row["equity_username"];
  $management_analysis_pdate                        =  $row["management_analysis_pdate"];
  $management_analysis_username                     =  $row["management_analysis_username"];
  $industry_analysis_pdate                          =  $row["industry_analysis_pdate"];
  $industry_analysis_username                       =  $row["industry_analysis_username"];
  $shareholder_analysis_pdate                       =  $row["shareholder_analysis_pdate"];
  $shareholder_analysis_username                    =  $row["shareholder_analysis_username"];
  $behavioral_analysis_pdate                        =  $row["behavioral_analysis_pdate"];
  $behavioral_analysis_username                     =  $row["behavioral_analysis_username"];
  $industry_benchmarks_overrides_pdate              =  $row["industry_benchmarks_overrides_pdate"];
  $industry_benchmarks_overrides_username           =  $row["industry_benchmarks_overrides_username"];

?>
<script>
 localStorage.CompanyDataTrackerDateSaved                = "<?php echo $company_data_pdate?>" ;
 localStorage.CompanyDataTrackerSavedBy                  = "<?php echo $company_data_username?>" ;
 localStorage.LoanDataTrackerDateSaved                   = "<?php echo $loan_data_pdate?>" ;
 localStorage.LoanDataTrackerSavedBy                     = "<?php echo $loan_data_username?>" ;
 localStorage.IncomeStatementTrackerDateSaved            = "<?php echo $income_statement_pdate?>" ;
 localStorage.IncomeStatementTrackerSavedBy              = "<?php echo $income_statement_username?>" ;
 localStorage.CurrentAssetsTrackerDateSaved              = "<?php echo $current_assets_pdate?>" ;
 localStorage.CurrentAssetsTrackerSavedBy                = "<?php echo $current_assets_username?>" ;
 localStorage.NonCurrentAssetsTrackerDateSaved           = "<?php echo $non_current_assets_pdate?>" ;
 localStorage.NonCurrentAssetsTrackerSavedBy             = "<?php echo $non_current_assets_username?>" ;
 localStorage.CurrentLiabilitiesTrackerDateSaved         = "<?php echo $current_assets_pdate?>" ;
 localStorage.CurrentLiabilitiesTrackerSavedBy           = "<?php echo $current_assets_username?>" ;
 localStorage.NonCurrentLiabilitiesTrackerDateSaved      = "<?php echo $non_current_assets_pdate?>" ;
 localStorage.NonCurrentLiabilitiesTrackerSavedBy        = "<?php echo $non_current_assets_username?>" ;
 localStorage.EquityTrackerDateSaved                     = "<?php echo $equity_pdate?>" ;
 localStorage.EquityTrackerSavedBy                       = "<?php echo $equity_username?>" ;
 localStorage.ManagementAnalysisTrackerDateSaved         = "<?php echo $management_analysis_pdate?>" ;
 localStorage.ManagementAnalysisTrackerSavedBy           = "<?php echo $management_analysis_username?>" ;
 localStorage.IndustryAnalysisTrackerDateSaved           = "<?php echo $industry_analysis_pdate?>" ;
 localStorage.IndustryAnalysisTrackerSavedBy             = "<?php echo $industry_analysis_username?>" ;
 localStorage.ShareholderAnalysisTrackerDateSaved        = "<?php echo $shareholder_analysis_pdate?>" ;
 localStorage.ShareholderAnalysisTrackerSavedBy          = "<?php echo $shareholder_analysis_username?>" ;
 localStorage.BehavioralAnalysisTrackerDateSaved         = "<?php echo $shareholder_analysis_pdate?>" ;
 localStorage.BehavioralAnalysisTrackerSavedBy           = "<?php echo $industry_analysis_username?>" ;
 localStorage.IndustryBenchmarksOverridesTrackerDateSaved  = "<?php echo $industry_analysis_pdate?>" ;
 localStorage.IndustryBenchmarksOverridesTrackerSavedBy  = "<?php echo $industry_analysis_username?>" ;
		 
 alert ("Progress Tracker records read from database"); 
         
</script>
