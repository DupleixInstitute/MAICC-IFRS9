<?php			
	$save_sql=
    "INSERT INTO cust_credit_rating
	(   
	    application_ref,
		loan_category,
		omang_passport_num,
		CIF,loan_number,
		pd,
		loan_amount,
		loan_percentage,
		installment,
		installment_percentage,
		cost_estimation,
		cost_estimation_percentage,
		estimated_EAD,
		estimated_EAD_percentage,
		estimated_OMV,
		estimated_OMV_percentage,
		haircut,
		haircut_percentage,
		estimated_LGD,
		estimated_LGD_percentage,
		Expected_loss,
		expected_loss_percentage, 
		ltv, 
		affordabilty, 
		rate,
		date)

	VALUES 
	(
		'$application_ref',
		'$loan_category',
		'$omang_passport_num',
		'$cif',
		$loan_number,
		'$pd',
		'$loan_amount',
		100,
		'$install',
		'$instalp',
		'$gcost',
		5,
		'$ead',
		'$eadp',
		'$open_market_value',
		'$omv',
		'$haircut', 
		'$hc',
		'$lgd',
		'$h2',
		'$el2',
		'$llpfinal', 
		'$ltv', 
		'$affordability_ratio', 
		'$rate',
		LOCALTIME()
	)";
   try {  
	  if (!mysql_query($save_sql,$connect)){
	     die('Error2ERR: ' . mysql_error());
	  }
	}catch (Error $e) {
	  if (!mysqli_query($connect,$save_sql,)){
	     die('Error2ERR: ' . mysqli_error());
	  }	
    }	  
?>
