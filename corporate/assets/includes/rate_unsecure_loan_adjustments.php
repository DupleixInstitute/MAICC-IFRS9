<?php
	/** DATA BASE ALTERATIONS TO BE DONE
	====================================================================================================================
	1. Advise the old PHP version being used for the credit scoring model so that I develop in the same old environment.
	
	2. Add the following columns in the cust_information table
	   (a) loan_category             
	   (b) contractual_years_remaining
           
		   @Credit Department:
		   -Discuss the score weight for 
		       > contractual_years_remaining
			   > life_cover_amount
			   
		   -What happens with the weights for irrelavant fields dropped eg
		       > location
			   > partnership revenues
			   
	   
	   These are new fields required for the unsecured loans platform
		
	3. Backup the current database
	
	4. Make a copy of the credit_scoring and sour databases for test purposes and call them credit_scoring_test and sour_test respec.tively
	
	5. Make a copy folder of the current scripts.
	
	6. Create a temporary folder for the new scripts and call it new_credit_scoring_2023 and provide a link
	
	7. Send the current scripts via WeTransfer the current scripts
	   email me the link on edwardmbombo@gmail.com
	   
	8. Export the current data base with a .sql extenstion and email   
	

	**/
	
	//include this just after the post variables
	//property_type has no effect on score
	//loan_category
	$loan_category                         =NULL;
	$loan_category                         =_POST['loan_category'];
    
	if ($loan_category == "unsecured loan") {	
		//do script to add column to data_base and to save
		$property_type		               =NULL;
		
		$open_market_value	               =NULL;
		$insurance_replacement             =NULL;
		$spouse_borrowing_realtionship     =NULL;
		$spouse_name                       =NULL;
		$spouse_surname                    =NULL;
		$location                          =NULL;
		$ltv                               =NULL;
		$ltv_policy                        =NULL;

		//household partner revenues
		$hp_annual_sal                     =NULL;
		$hp_fixed_perm_allowances          =NULL;
		$hp_total_rev_for_afford           =NULL;
		$tax2                              =NULL;
		//$total_allowable_household_revenues=NULL;	
		//added fields and rating ***********************************************************************************
		$contractual_years_remaining       =NULL;		
		$contractual_years_remaining       =_POST['contractual_years_remaining'];		

		$life_cover_amount                 =NULL;		
		$life_cover_amount                 =_POST['life_cover_amount'];		
		
		//adjusting location score => maximum was 400 for urban
		$location_score                    =0;
		$w_location_score                  =$location_score*0.02;
		
		//?????????adjusting revenues score => maximum = 500 for double
		$revenues                          =0;
		$w_revenues_score                  =$revenues_score*0.03;
	}

?>



