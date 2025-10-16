<?php
		 $application_ref                   = $row["application_ref"];
	     $company_reg_no                    = $row["company_reg_no"];
	     $CIF                               = $row["CIF"];
		 $loan_number                       = $row["loan_number"];
		 $registration_year                 = $row["registration_year"];
         $vat_reg_no                        = $row["vat_reg_no"];
		 $customer_type                     = $row["customer_type"];  
         $legal_name                        = $row["legal_name"];
         $trading_name                      = $row["trading_name"];
         $registration_country              = $row["registration_country"];
         $financial_year0                   = $row["financial_year0"];
         $financial_year1                   = $row["financial_year1"];
         $financial_year2                   = $row["financial_year2"];
         $financial_year3                   = $row["financial_year3"];
         $reporting_month_year0             = $row["reporting_month_year0"];
         $reporting_month_year1             = $row["reporting_month_year1"];
		 $reporting_month_year2             = $row["reporting_month_year2"];
         $reporting_month_year3             = $row["reporting_month_year3"];
         $audit_status_year0                = $row["audit_status_year0"];
         $audit_status_year1                = $row["audit_status_year1"];
		 $audit_status_year2                = $row["audit_status_year2"];
		 $audit_status_year3                = $row["audit_status_year3"];
		 $real_inflation_year0              = $row["real_inflation_year0"];
		 $real_inflation_year1              = $row["real_inflation_year1"];
         $real_inflation_year2              = $row["real_inflation_year2"];
         $real_inflation_year3              = $row["real_inflation_year3"];
         $nominal_inflation_year0           = $row["nominal_inflation_year0"];
         $nominal_inflation_year1           = $row["nominal_inflation_year1"];
         $nominal_inflation_year2           = $row["nominal_inflation_year2"];
         $nominal_inflation_year3           = $row["nominal_inflation_year3"];
         $industrial_sector                 = $row["industrial_sector"];
         $borrower_present_address          = $row["borrower_present_address"];
         $street_name_and_number            = $row["street_name_and_number"];
         $years_at_present_address          = $row["years_at_present_address"];
         $e_mail                            = $row["e_mail"];
         $bond_plot                         = $row["bond_plot"];
	     $location_of_acquired_real_estate  = $row["location_of_acquired_real_estate"];
         $main_bank                         = $row["main_bank"];
         $second_bank                       = $row["second_bank"];
         $third_bank                        = $row["third_bank"];
 

         //LOAN DATA IN SAME TABLE ==================================================================================
         $loan_type                            =  $row["loan_type"];
         $loan_amount_requested                =  $row["loan_amount_requested"];
         $property_type                        =  $row["property_type"];
         $open_market_value                    =  $row["open_market_value"];
         $loan_maturity_requested              =  $row["loan_maturity_requested"];
         $rate_type_requested                  =  $row["rate_type_requested"];
         $current_interest_rate                =  $row["current_interest_rate"];
         $insurance_replacement_cost           =  $row["insurance_replacement_cost"];
         $estimated_insurance_premium          =  $row["estimated_insurance_premium"];
         $estimated_installment                =  $row["estimated_installment"];
         $estimated_installment_insurance      =  $row["estimated_installment_insurance"];
         $loan_to_value_policy                 =  $row["loan_to_value_policy"];
         $loan_to_value                        =  $row["loan_to_value"];
         $monthly_payment                      =  $row["monthly_payment"];
         $age_of_relationship                  =  $row["age_of_relationship"];
         $savings_Account                      =  $row["savings_Account"];
         $deposit_Account                      =  $row["deposit_Account"];
         $share_Account                        =  $row["share_Account"];
         $ST_Loans                             =  $row["ST_Loans"];
         $mortgages                            =  $row["mortgages"];
         $total_bbs_products                   =  $row["total_bbs_products"];
         $bbs_arrears_12months                 =  $row["bbs_arreas_12months"];
         $renegotiated                         =  $row["renegotiated"];
         $why_renegotiated                     =  $row["why_renegotiated"];
         $loan_name1                           =  $row["loan_name1"];
         $loan_name2                           =  $row["loan_name2"];
         $loan_name3                           =  $row["loan_name3"];
         $loan_name4                           =  $row["loan_name4"];
         $loan_name5                           =  $row["loan_name5"];
         $loan_name6                           =  $row["loan_name6"];
         $loan_name7                           =  $row["loan_name7"];
         $loan_name8                           =  $row["loan_name8"];
         $loan_name9                           =  $row["loan_name9"];
         $loan_instalment1                     =  $row["loan_instalment1"];
         $loan_instalment2                     =  $row["loan_instalment2"];
         $loan_instalment3                     =  $row["loan_instalment3"];
         $loan_instalment4                     =  $row["loan_instalment4"];
         $loan_instalment5                     =  $row["loan_instalment5"];
         $loan_instalment6                     =  $row["loan_instalment6"];
         $loan_instalment7                     =  $row["loan_instalment7"];
         $loan_instalment8                     =  $row["loan_instalment8"];
         $loan_instalment9                     =  $row["loan_instalment9"];
         $TotalInstalments                     =  $row["total_instalments"];
         $number_of_loans_outstanding          =  $row["number_of_loans_outstanding"];
         $ITC_REF                              =  $row["ITC_REF"];
         $paid_debt                            =  $row["paid_debt"];
         $judgement                            =  $row["judgment"];
         $default_data                         =  $row["default_data"];
         $trace_alerts                         =  $row["trace_alerts"];
         $blacklist_flag                       =  $row["blacklist_flag"];
         $fraud_alerts                         =  $row["fraud_alerts"];
         $deduct                               =  $row["deduct"];
			 
		 //$username                             = $row["username"];    
  ?>
  
  <script>
		 //1. COMPANY DATA
		 
		 localStorage.application_ref                   = "<?php echo $application_ref; ?>";		 
	     localStorage.company_reg_no                    = "<?php echo $company_reg_no; ?>";
	     localStorage.CIF                               = "<?php echo $CIF; ?>";
		 localStorage.loan_number                       = "<?php echo $loan_number; ?>";
		 localStorage.registration_year                 = "<?php echo $registration_year; ?>";        
		 localStorage.vat_reg_no                       = "<?php echo $vat_reg_no; ?>";        
		 localStorage.customer_type                     = "<?php echo $customer_type; ?>";  
         localStorage.legal_name                        = "<?php echo $legal_name; ?>";
		 localStorage.trading_name                      = "<?php echo $trading_name ?>";
         localStorage.registration_country              = "<?php echo $registration_country ?>";
         localStorage.financial_year0                   = "<?php echo $financial_year0 ?>";
         localStorage.financial_year1                   = "<?php echo $financial_year1 ?>";      
		 localStorage.financial_year2                   = "<?php echo $financial_year2 ?>";
         localStorage.financial_year3                   = "<?php echo $financial_year3 ?>";
         localStorage.reporting_month_year0             = "<?php echo $reporting_month_year0 ?>";
         localStorage.reporting_month_year1             = "<?php echo $reporting_month_year1 ?>";
		 localStorage.reporting_month_year2             = "<?php echo $reporting_month_year2 ?>";
         localStorage.reporting_month_year3             = "<?php echo $reporting_month_year3 ?>";
         localStorage.audit_status_year0                = "<?php echo $audit_status_year0 ?>";
         localStorage.audit_status_year1                = "<?php echo $audit_status_year1 ?>";
		 localStorage.audit_status_year2                = "<?php echo $audit_status_year2 ?>";
		 localStorage.audit_status_year3                = "<?php echo $audit_status_year3 ?>";
		 localStorage.real_inflation_year0              = "<?php echo $real_inflation_year0 ?>";
 		 localStorage.real_inflation_year1              = "<?php echo $real_inflation_year1 ?>";
         localStorage.real_inflation_year2              = "<?php echo $real_inflation_year2 ?>";
         localStorage.real_inflation_year3              = "<?php echo $real_inflation_year3 ?>";
         localStorage.nominal_inflation_year0           = "<?php echo $nominal_inflation_year0 ?>";
         localStorage.nominal_inflation_year1           = "<?php echo $nominal_inflation_year1 ?>";
         localStorage.nominal_inflation_year2           = "<?php echo $nominal_inflation_year2 ?>";
         localStorage.nominal_inflation_year3           = "<?php echo $nominal_inflation_year3 ?>";
         localStorage.industrial_sector                 = "<?php echo $industrial_sector ?>";
         localStorage.borrower_present_address          = "<?php echo $borrower_present_address ?>";
         localStorage.street_name_and_number            = "<?php echo $street_name_and_number ?>";
         localStorage.years_at_present_address          = "<?php echo $years_at_present_address ?>";
         localStorage.e_mail                            = "<?php echo $e_mail ?>";
         localStorage.bond_plot                         = "<?php echo $bond_plot ?>";
	     localStorage.location_of_acquired_real_estate  = "<?php echo $location_of_acquired_real_estate ?>";
         localStorage.main_bank                         = "<?php echo $main_bank ?>";
         localStorage.second_bank                       = "<?php echo $second_bank  ?>";
         localStorage.third_bank                        = "<?php echo $third_bank ?>";
          
		 //localStorage.username                          = "<?php echo $username ?>";
		
		 //alert("Testing");
	     
		 //LOAN DATA IN SAME TABLE =========================================================================================
         localStorage.loan_type             = "<?php echo $loan_type?>" ;
         localStorage.loan_amount           = "<?php echo $loan_amount_requested?>" ;
         console.log("Loan Amount = "+localStorage.loan_amount);
		 
		 localStorage.property_type         = "<?php echo $property_type?>" ;
         localStorage.open_market_value     = "<?php echo $open_market_value?>" ;
         localStorage.loan_maturity         = "<?php echo $loan_maturity_requested?>" ;
         localStorage.rate_type             = "<?php echo $rate_type_requested?>" ;
         localStorage.irate                 = "<?php echo $current_interest_rate?>" ;
         localStorage.insurance_replacement = "<?php echo $insurance_replacement_cost?>" ;
         localStorage.insurance_premium     = "<?php echo $estimated_insurance_premium?>" ;
         localStorage.loan_installment      = "<?php echo $estimated_installment?>" ;
         localStorage.loanandinsurance      = "<?php echo $estimated_installment_insurance?>" ;
         localStorage.ltv_policy            = "<?php echo $loan_to_value_policy?>" ;
         localStorage.ltv                   = "<?php echo $loan_to_value?>" ;
         localStorage.rent                  = "<?php echo $monthly_payment?>" ;
         
		 localStorage.relationship          = "<?php echo $age_of_relationship?>" ;
         localStorage.Savings               = "<?php echo $savings_Account?>" ;
         localStorage.Deposit               = "<?php echo $deposit_Account?>" ;
         localStorage.Share                 = "<?php echo $share_Account?>" ;
         localStorage.ST                    = "<?php echo $ST_Loans?>" ;
         
		 localStorage.Mortgages             = "<?php echo $mortgages?>" ;
         
		 localStorage.total_bbs_products    = "<?php echo $total_bbs_products?>" ;
    
		 localStorage.loan_arrears          = "<?php echo $bbs_arrears_12months?>" ;
    
		 localStorage.renegotiate           = "<?php echo $renegotiated?>" ;
        
		 localStorage.why_renogotiation     = "<?php echo $why_renegotiated?>" ;      
	     
		 localStorage.LoanName1             = "<?php echo $loan_name1?>" ;
         localStorage.LoanName2             = "<?php echo $loan_name2?>" ;
         localStorage.LoanName3             = "<?php echo $loan_name3?>" ;
         localStorage.LoanName4             = "<?php echo $loan_name4?>" ;
         localStorage.LoanName5             = "<?php echo $loan_name5?>" ;
         localStorage.LoanName6             = "<?php echo $loan_name6?>" ;
         localStorage.LoanName7             = "<?php echo $loan_name7?>" ;
         localStorage.LoanName8             = "<?php echo $loan_name8?>" ;
         localStorage.LoanName9             = "<?php echo $loan_name9?>" ;
         
		 localStorage.LoanInstalment1       = "<?php echo $loan_instalment1?>" ;
         localStorage.LoanInstalment2       = "<?php echo $loan_instalment2?>" ;
        
		 localStorage.LoanInstalment3       = "<?php echo $loan_instalment3?>" ;
         localStorage.LoanInstalment4       = "<?php echo $loan_instalment4?>" ;
         localStorage.LoanInstalment5       = "<?php echo $loan_instalment5?>" ;
         localStorage.LoanInstalment6       = "<?php echo $loan_instalment6?>" ;
         localStorage.LoanInstalment7       = "<?php echo $loan_instalment7?>" ;        
		 localStorage.LoanInstalment8       = "<?php echo $loan_instalment8?>" ;
         localStorage.LoanInstalment9       = "<?php echo $loan_instalment9?>" ;
 
         localStorage.TotalInstalments      = "<?php echo $TotalInstalments?>" ;
         localStorage.loans_outstanding     = "<?php echo $number_of_loans_outstanding?>" ;
         localStorage.itc_ref               = "<?php echo $ITC_REF?>" ;
         localStorage.paid_debts            = "<?php echo $paid_debt?>" ;
         localStorage.judgement             = "<?php echo $judgement?>" ;
         localStorage.default_data          = "<?php echo $default_data?>" ;
         localStorage.trace_alerts          = "<?php echo $trace_alerts?>" ;
         localStorage.blacklisted           = "<?php echo $blacklist_flag?>" ;
         localStorage.fraud_alert           = "<?php echo $fraud_alerts?>" ;
         localStorage.deduct                = "<?php echo $deduct?>" ;
		 
		 alert ("Loan data loaded - Press OK to continue"); 
         
</script>
