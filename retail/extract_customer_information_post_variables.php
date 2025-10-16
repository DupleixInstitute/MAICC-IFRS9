<?php
	$username                           = $_POST['username'];
	$loan_number                        = $_POST['loan_number'];
	$password                           = $_POST['password'];
	$omang_passport_num					= $_POST['one'];
	$installment1                       = floatval($_POST['installment1']);
	$ainstallment1                      = floatval($_POST['ainstallment1']);
	$ainstallment2                      = floatval($_POST['ainstallment2']);
	$binstallment1                      = floatval($_POST['binstallment1']);
	$binstallment2                      = floatval($_POST['binstallment2']);
	$binstallment3                      = floatval($_POST['binstallment3']);
	$cinstallment1                      = floatval($_POST['cinstallment1']);
	$cinstallment2                      = floatval($_POST['cinstallment2']);
	$cinstallment3                      = floatval($_POST['cinstallment3']);
	$cinstallment4                      = floatval($_POST['cinstallment4']);

	$dinstallment1                      = floatval($_POST['dinstallment1']);
	$dinstallment2                      = floatval($_POST['dinstallment2']);
	$dinstallment3                      = floatval($_POST['dinstallment3']);
	$dinstallment4                      = floatval($_POST['dinstallment4']);
	$dinstallment5                      = floatval($_POST['dinstallment5']);

	$einstallment1                      = floatval($_POST['einstallment1']);
	$einstallment2                      = floatval($_POST['einstallment2']);
	$einstallment3                      = floatval($_POST['einstallment3']);
	$einstallment4                      = floatval($_POST['einstallment4']);
	$einstallment5                      = floatval($_POST['einstallment5']);
	$einstallment6                      = floatval($_POST['einstallment6']);

	$finstallment1						= floatval($_POST['finstallment1']);
	$finstallment2						= floatval($_POST['finstallment2']);
	$finstallment3						= floatval($_POST['finstallment3']);
	$finstallment4						= floatval($_POST['finstallment4']);
	$finstallment5						= floatval($_POST['finstallment5']);
	$finstallment6						= floatval($_POST['finstallment6']);
	$finstallment7						= floatval($_POST['finstallment7']);


	$ginstallment1						= floatval($_POST['ginstallment1']);
	$ginstallment2						= floatval($_POST['ginstallment2']);
	$ginstallment3						= floatval($_POST['ginstallment3']);
	$ginstallment4						= floatval($_POST['ginstallment4']);
	$ginstallment5						= floatval($_POST['ginstallment5']);
	$ginstallment6						= floatval($_POST['ginstallment6']);
	$ginstallment7						= floatval($_POST['ginstallment7']);
	$ginstallment8						= floatval($_POST['ginstallment8']);

	$hinstallment1						= floatval($_POST['hinstallment1']);
	$hinstallment2						= floatval($_POST['hinstallment2']);
	$hinstallment3						= floatval($_POST['hinstallment3']);
	$hinstallment4						= floatval($_POST['hinstallment4']);
	$hinstallment5						= floatval($_POST['hinstallment5']);
	$hinstallment6						= floatval($_POST['hinstallment6']);
	$hinstallment7						= floatval($_POST['hinstallment7']);
	$hinstallment8						= floatval($_POST['hinstallment8']);
	$hinstallment9						= floatval($_POST['hinstallment9']);

	$cif								= $_POST['cif'];
	$perm_res							= $_POST['perm_res'];
	$gender								= $_POST['gender'];
	//echo $gender;
	$loan_amount						= floatval($_POST['loan_amount']);
	//echo 'ttmm'; echo $loan_amount;
	$property_type						= $_POST['property_type'];
	$open_market_value					= $_POST['open_market_value'];
	$loan_type							= $_POST['loan_type'];
	$loan_maturity						= $_POST['loan_maturity'];
	$rate_type							= $_POST['rate_type'];
	$irate								= $_POST['irate'];
	$insurance_replacement				= $_POST['insurance_replacement'];
	$insurance_premium					= $_POST['insurance_premium'];
	$loan_installment					= $_POST['loan_installment'];
	$loanandinsurance					= floatval($_POST['loanandinsurance']);
	echo "here";
	var_dump($loanandinsurance);
	
	$customer_type						= $_POST['customer_type'];
	$rev4afford							= floatval($_POST['rev4afford']);
	$name								= $_POST['name'];
	$surname							= $_POST['surname'];
	$spouse_name						= $_POST['spouse_name'];
	$spouse_surname						= $_POST['spouse_surname'];
	$address							= $_POST['address'];
	$email								= $_POST['email'];
	$street_add							= $_POST['street_add'];
	$town								= $_POST['town'];
	$country							= $_POST['country'];
	$permanent_residence				= $_POST['permanent_residence'];
	$location							= $_POST['location'];

	//Borrower's date of birth=============================================
	$day								= $_POST['day'];
	$month								= $_POST['month'];
	$year								= $_POST['year'];

	$tax                                = $_POST['tax'];
	$tax2                               = $_POST['tax2'];

	//Spouse's date of birth=============================================
	$day2                               = $_POST['day2'];
	$month2                             = $_POST['month2'];
	$year2                              = $_POST['year2'];

	//Wedding date of birth=============================================
	$day22                              = $_POST['day22'];
	$month22                            = $_POST['month22'];
	$year22                             = $_POST['year22'];

	//Divorce date of birth=============================================
	$day23                              = $_POST['day23'];
	$month23                            = $_POST['month23'];
	$year23                             = $_POST['year23'];

	$nationality                        = $_POST['nationality'];
	$marital_status                     = $_POST['marital_status'];
	$marital_contract_type              = $_POST['marital_contract_type'];
	$spouse_borrowing_realtionship      = $_POST['spouse_borrowing_relationship'];
	$no_of_children                     = $_POST['no_of_children'];
	$to18                               = $_POST['to18'];
	$to26                               = $_POST['to26'];
	$to12                               = $_POST['to12'];
	$aunts_uncles_cousins               = $_POST['aunts_uncles_cousins'];
	$other                              = $_POST['other'];
	$years_at_address                   = $_POST['years_at_address'];
	$rent                               = $_POST['rent'];
	$education                          = $_POST['education'];
	$professional                       = $_POST['Professional'];
	$employment                         = $_POST['employment'];
	$years_at_job                       = $_POST['years_at_job'];
	//echo $years_at_job;
	$revenues                           = $_POST['revenues'];
	$annual_sal                         = $_POST['annual_sal'];
	$fixed_perm_allowances              = $_POST['fixed_perm_allowances'];
	$total_rev_for_afford               = $_POST['total_rev_for_afford'];
	$hp_annual_sal                      = $_POST['hp_annual_sal'];
	$hp_fixed_perm_allowances           = $_POST['hp_fixed_perm_allowances'];
	$hp_total_rev_for_afford            = $_POST['hp_total_rev_for_afford'];
	$total_allowable_household_revenues = $_POST['total_allowable_household_revenues'];
	$mainbank							= $_POST['mainbank'];
	$secondbank							= $_POST['secondbank'];
	$thirdbank							= $_POST['thirdbank'];
	$Savings							= $_POST['Savings'];
	$Deposit							= $_POST['Deposit'];
	$Share								= $_POST['Share'];
	$bond_plot							= $_POST['bond_plot'];
	$ST									= $_POST['ST'];
	$Mortgages							= $_POST['Mortgages'];
	$why_renegotiate					= $_POST['why_renogotiation'];
	$itc_ref							= $_POST['itc_ref'];
	//$itc_sub_no							= $_POST['itc_sub_no'];
	$paid_debts							= $_POST['paid_debts'];
	$judgement							= $_POST['judgement'];
	$default_data						= $_POST['default_data'];
	$trace_alerts						= $_POST['trace_alerts'];
	$number_of_dependants				= $_POST['number_of_dependants'];
	$dependants_at_home					= $_POST['dependants_at_home'];
	$relationship						= $_POST['relationship'];
	$total_bbs_products                 = $_POST['total_bbs_products'];
	$renegotiate                        = $_POST['renegotiate'];
	$loan_arrears                       = $_POST['loan_arrears'];
	$cards_held                         = $_POST['cards_held'];
	$card_held_since                    = $_POST['card_held_since'];
	$loans_outstanding                  = $_POST['loans_outstanding'];
	$affordability_ratio                = $_POST['affordability_ratio'];
	$blacklisted                        = $_POST['blacklisted'];
	$fraud_alert                        = $_POST['fraud_alert'];
	$ltv_policy                         = $_POST['ltv_policy'];
	$affordability_policy               = $_POST['affordability_policy'];
	$ltv                                = $_POST['ltv'];
	$other_names                        = $_POST['other_name'];
	$grandparents                       = $_POST['grandparents'];
	$deduct                             = $_POST['deduct'];
    $numloans			                = $_POST['loans_outstanding'];
?>