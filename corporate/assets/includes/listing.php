<?php
	$username                           = isset($_POST['username'])?
	                                      $_POST['username']:"";
	$loan_number                        = $_POST['loan_number'];
	$password                           = isset($_POST['password'])?
	                                      $_POST['password']:"";
	$omang_passport_num					= $_POST['one'];
	$loan_category                      = $_POST['loan_category'];
	$installment1                       = $_POST['installment1'];
	$ainstallment1                      = $_POST['ainstallment1'];
	$ainstallment2                      = $_POST['ainstallment2'];
	$binstallment1                      = $_POST['binstallment1'];
	$binstallment2                      = $_POST['binstallment2'];
	$binstallment3                      = $_POST['binstallment3'];
	$cinstallment1                      = $_POST['cinstallment1'];
	$cinstallment2                      = $_POST['cinstallment2'];
	$cinstallment3                      = $_POST['cinstallment3'];
	$cinstallment4                      = $_POST['cinstallment4'];

	$dinstallment1                      = $_POST['dinstallment1'];
	$dinstallment2                      = $_POST['dinstallment2'];
	$dinstallment3                      = $_POST['dinstallment3'];
	$dinstallment4                      = $_POST['dinstallment4'];
	$dinstallment5                      = $_POST['dinstallment5'];

	$einstallment1                      = $_POST['einstallment1'];
	$einstallment2                      = $_POST['einstallment2'];
	$einstallment3                      = $_POST['einstallment3'];
	$einstallment4                      = $_POST['einstallment4'];
	$einstallment5                      = $_POST['einstallment5'];
	$einstallment6                      = $_POST['einstallment6'];

	$finstallment1						= $_POST['finstallment1'];
	$finstallment2						= $_POST['finstallment2'];
	$finstallment3						= $_POST['finstallment3'];
	$finstallment4						= $_POST['finstallment4'];
	$finstallment5						= $_POST['finstallment5'];
	$finstallment6						= $_POST['finstallment6'];
	$finstallment7						= $_POST['finstallment7'];


	$ginstallment1						= $_POST['ginstallment1'];
	$ginstallment2						= $_POST['ginstallment2'];
	$ginstallment3						= $_POST['ginstallment3'];
	$ginstallment4						= $_POST['ginstallment4'];
	$ginstallment5						= $_POST['ginstallment5'];
	$ginstallment6						= $_POST['ginstallment6'];
	$ginstallment7						= $_POST['ginstallment7'];
	$ginstallment8						= $_POST['ginstallment8'];

	$hinstallment1						= $_POST['hinstallment1'];
	$hinstallment2						= $_POST['hinstallment2'];
	$hinstallment3						= $_POST['hinstallment3'];
	$hinstallment4						= $_POST['hinstallment4'];
	$hinstallment5						= $_POST['hinstallment5'];
	$hinstallment6						= $_POST['hinstallment6'];
	$hinstallment7						= $_POST['hinstallment7'];
	$hinstallment8						= $_POST['hinstallment8'];
	$hinstallment9						= $_POST['hinstallment9'];

	$cif								= $_POST['cif'];
	$perm_res							= $_POST['perm_res'];
	$gender								= $_POST['gender'];
	//echo $gender;
	$loan_amount						= $_POST['loan_amount'];
	//echo 'ttmm'; echo $loan_amount;
	$property_type						= isset($_POST['property_type']) ? 
	                                      $_POST['property_type']:'NA';
	$open_market_value					= $_POST['open_market_value'];
	$loan_type							= $_POST['loan_type'];
	$loan_maturity						= $_POST['loan_maturity'];
	$rate_type							= $_POST['rate_type'];
	$irate								= $_POST['irate'];
	$insurance_replacement				= isset($_POST['insurance_replacement'])?
	                                      $_POST['insurance_replacement']:0;
	$life_cover_amount				    = isset($_POST['life_cover_amount'])?
	                                      $_POST['life_cover_amount']:0;
	$insurance_premium					= $_POST['insurance_premium'];
	$loan_installment					= $_POST['loan_installment'];
	$loanandinsurance					= $_POST['loanandinsurance'];
	$customer_type						= $_POST['customer_type'];
	$rev4afford							= $_POST['rev4afford'];
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
	$location							= isset($_POST['location']) ? 
	                                      $_POST['location']:'Semi Urban A';

	//Borrower's date of birth=============================================
	
	$day								= isset($_POST['day'])?
	                                      $_POST['day']:
										  date('d',strtotime($_POST['borrower_birth_date']));
	$month								= isset($_POST['month'])?
	                                      $_POST['month']:
										  date('F',strtotime($_POST['borrower_birth_date']));
	$year								= isset($_POST['year'])?
                                          $_POST['year']:
										  date('Y',strtotime($_POST['borrower_birth_date']));
	$tax                                = $_POST['tax'];
	$tax2                               = $_POST['tax2'];

	//Spouse's date of birth=============================================
	$day2								= isset($_POST['day2'])?
	                                      $_POST['day2']:
										  date('d',strtotime($_POST['spouse_birth_date']));
	$month2								= isset($_POST['month2'])?
	                                      $_POST['month2']:
										  date('F',strtotime($_POST['spouse_birth_date']));
	$year2								= isset($_POST['year2'])?
                                          $_POST['year2']:
										  date('Y',strtotime($_POST['spouse_birth_date']));

	//Wedding date of birth=============================================
	$day22								= isset($_POST['day22'])?
	                                      $_POST['day22']:
										  date('d',strtotime($_POST['wedding_date']));
	$month22							= isset($_POST['month22'])?
	                                      $_POST['month22']:
										  date('F',strtotime($_POST['wedding_date']));
	$year22								= isset($_POST['year22'])?
                                          $_POST['year22']:
										  date('Y',strtotime($_POST['wedding_date']));

	//Divorce date of birth=============================================
	$day23								= isset($_POST['day23'])?
	                                      $_POST['day23']:
										  date('d',strtotime($_POST['divorce_date']));
	$month23							= isset($_POST['month23'])?
	                                      $_POST['month23']:
										  date('F',strtotime($_POST['divorce_date']));
	$year23								= isset($_POST['year23'])?
                                          $_POST['year23']:
										  date('Y',strtotime($_POST['divorce_date']));

	$nationality                        = $_POST['nationality'];
	$marital_status                     = $_POST['marital_status'];
	$marital_contract_type              = $_POST['marital_contract_type'];
	$spouse_borrowing_realtionship      = isset($_POST['spouse_borrowing_relationship'])?
	                                      $_POST['spouse_borrowing_relationship']:0;
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
	$contractual_years_remaining        = isset($_POST['contractual_years_remaining'])?
	                                      $_POST['contractual_years_remaining']:0;
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
	//$number_of_dependants				= $_POST['number_of_dependants'];
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