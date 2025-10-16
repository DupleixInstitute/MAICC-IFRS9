<?php
	/**Status Comments==================================================================
	 1. ltv Comment           - Reject if > policy
	 2. Affordability comment - Reject if < Policy
	 3. Loan Type             - OK for both Guaranteed and Non-guaranteed???????
	 4. Blacklisted           - Reject if True
	 5. Fraud alert           - Reject if True
	 6. Customer Type         - Reject if legal entity
	 7. Nationatility         - Reject if foreigner and permanent resident ? is it and or??
	 8. Age                   - Reject if > 65
	 9. Total age             - Reject if > 65
	**/
	$ltvcomment           = ($ltv > $ltv_policy)?"REJECT":"OK";
	$affordabilitycomment = ($affordability_ratio > $affordability_policy)
	                        ?"REJECT"
							:"OK";

	//confirm the rule
	$affordabilitycomment  = ($loan_type=="Guaranteed Scheme"     || 
	                          $loan_type=="Non-Guaranteed Scheme")
	                          ?"OK"
							  :$affordabilitycomment;

	$blacklistedcomment    = ($blacklisted=="True") ? "REJECT":"OK";

	$fraud_alert_comment   = ($fraud_alert=="True") ? "REJECT":"OK";

	$customer_type_comment = ($customer_type=="Legal Entity") ? "REJECT":"OK";

	//confirm the rule?
	$nationality_comment   = ($nationality=="Foreigner" and $perm_res=="Yes") ? "REJECT":"OK";

    $age_comment           = ($age>65      )? "REJECT":"OK";  

    $total_age_comment     = ($total_age>65)? "REJECT":"OK";  

	//Calculate Debt service ratios depending on the number of loans=====================================
	//and determine the dsr comments
	if ($numloans==0)
	{
		$debtserviceratio=($loanandinsurance / $rev4afford)*100;
	}

	else if ($numloans==1)
	{
	   $debtserviceratio=(($installment1 +  $loanandinsurance )/ $rev4afford )*100;
	}
	else if ($numloans==2)
	{
	   $debtserviceratio=(($ainstallment1 + $ainstallment2 + $loanandinsurance )/ $rev4afford )*100;
	}

	if ($numloans==3)
	{
		$debtserviceratio=(($installment1 + $binstallment2 + $binstallment3 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==4)
	{
	//echo "chose 4";
	$debtserviceratio=(($cinstallment1 + $cinstallment2 + $cinstallment3 + $cinstallment4 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==5)
	{
	//echo "chose 4";
	$debtserviceratio=(($dinstallment1 + $dinstallment2 + $dinstallment3 + $dinstallment4 + $dinstallment5 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==6)
	{
	//echo "chose 4";
	$debtserviceratio=(($einstallment1 + $einstallment2 + $einstallment3 + $einstallment4 + $einstallment5 + $einstallment6 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==7)
	{
	//echo "chose 4";
	$debtserviceratio=(($finstallment1 + $finstallment2 + $finstallment3 + $finstallment4 + $finstallment5 + $finstallment6 + $finstallment7 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==8)
	{
	//echo "chose 4";
	$debtserviceratio=(($ginstallment1 + $ginstallment2 + $ginstallment3 + $ginstallment4 + $ginstallment5 + $ginstallment6 + $ginstallment7 + $ginstallment8 + $loanandinsurance )/ $rev4afford )*100;
	}
	if ($numloans==9)
	{
	//echo "chose 4";
	$debtserviceratio=(($hinstallment1 + $hinstallment2 + $hinstallment3 + $hinstallment4 + $hinstallment5 + $hinstallment6 + $hinstallment7 + $hinstallment8 + $hinstallment9 + $loanandinsurance )/ $rev4afford )*100;
	}


	$dsr=number_format($debtserviceratio, 2, '.', '');
	if ($debtserviceratio > 80){
	   $dsr_score=100;
	   $dsr_comment="REJECT";
	}
	else{
	   $dsr_score=400;
	   $dsr_comment="OK";
	}
	   $dsr_w_score=$dsr_score * 0.03;
?>