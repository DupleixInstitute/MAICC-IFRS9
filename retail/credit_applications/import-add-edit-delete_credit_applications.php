<?php

// Database connection info 
include '../assets/includes/database_connection.php';
$statusMessage = [];
if ($_POST['mode'] === 'add_mortgage') {
try {	
		//initiliase customer information temporary variables
		include("../assets/includes/initialise_customer_information_variables.php");

		//extract customer information post variables
		include("../assets/includes/extract_customer_information_post_variables.php");

		//calculate age and total age
		$todayv   = getdate();

		$myyear	  = $todayv['year'];
		$mymonth  = $todayv['month'];

		if ($mymonth=='January'  )  {$mymonth=1;}
		if ($mymonth=='February' )  {$mymonth=2; }
		if ($mymonth=='March'    )  {$mymonth=3; }
		if ($mymonth=='April'    )  {$mymonth=4; }
		if ($mymonth=='May'      )  {$mymonth=5; }
		if ($mymonth=='June'     )  {$mymonth=6; }
		if ($mymonth=='July'     )  {$mymonth=7; }
		if ($mymonth=='August'   )  {$mymonth=8; }
		if ($mymonth=='September')  {$mymonth=9; }
		if ($mymonth=='October'  )  {$mymonth=10;}
		if ($mymonth=='November' )  {$mymonth=11;}
		if ($mymonth=='December' )  {$mymonth=12;}

		$age       = $myyear - $year;
		$total_age = $age    + $loan_maturity;

		if($mymonth < $month)
		//echo "am in if";
		$age=$age-1;

		include ("../assets/includes/determine_status_comments.php");
		//===============================================================================================================
		//VALIDATION CHECK - Ensure today's date <> spouse, borrower, wedding or divorce date
		//================================================================================================================

		//Get to day's date parts and combine
		$today1 = date("m");
		$today2 = date("j");
		$today3 = date("Y");
		$today=$today3.$today1.$today2;
		//Combine borrower date parts
		$birth=$year.$month.$day;
		//combine spouse date parts
		$spouse=$year2.$month2.$day2;
		//combine wedding date parts

		$wedding=$year22.$month22.$day22;
		//combine divorce date parts
		$divorce=$year23.$month23.$day23;
		//perform validation check and die/error message if failed
		$a=date($today);
		$b=date($birth);
		$c=date($spouse);
		$d=date($wedding);
		$e=date($divorce);

		if($a == $b  || $a==$c || $a==$d || $a==$e && $a <> 0 )  {
		   $statusMessage['text']       = "Special Marital Dates Cannot = Today's Date";
		   $statusMessage['errorStatus']= "error";
		   echo json_encode($statusMessage);
		   exit;
		}
	    $paid_debts_original=$paid_debts;
        if    ($paid_debts >= $default_data) 
			  $paid_debts=1;
        else  $paid_debts=0;

		try {	
            include ("../assets/includes/perform_rating.php");	    	
			include ("../assets/includes/save_credit_scores.php");
		    include ("../assets/includes/database_connection.php");		
		    include ("../assets/includes/save_customer_information.php");  
		} catch (Exception $e) {
		   $statusMessage['text']       = 'Database insert aborted: ' .$e->getMessage();
		   $statusMessage['errorStatus']= "error";
           exit;
		}

		$statusMessage['text']          = "Mortgage Loan Application successfully uploaded";
		$statusMessage['errorStatus']   = "success";
} catch (Error $e) {  
		$statusMessage['ErrorStatus']= "error";
  	    $statusMessage['Message'] = $e->getMessage();  
       
}  
   echo json_encode($statusMessage);
}  

if ($_POST['mode'] === 'edit') {
    
    $result = mysqli_query($connect,
	                       "SELECT * FROM pd_transition_matrices 
						    WHERE id='" . $_POST['id'] . "'"
						   );
    $row= mysqli_fetch_array($result);

     echo json_encode($row);
}   
if ($_POST['mode'] === 'update-loan-book') {

	$reporting_year       = $_POST['reporting_year'];
	$reporting_month      = $_POST['reporting_month'] < 12?'0'.$_POST['reporting_month']:$_POST['reporting_month'];  ;   
    $reporting_period     = $reporting_year.$reporting_month;
	$start_year           = $_POST['start_year'];   
    $start_month          = $_POST['start_month'] < 12?'0'.$_POST['start_month']:$_POST['start_month'];   
    $start_period         = $start_year.$start_month;
	$end_year             = $_POST['end_year'];   
    $end_month            = $_POST['end_month'] <12?'0'.$_POST['end_month']:$_POST['end_month'];  ;   
    $end_period           = $end_year.$end_month;
    $pd_period            = $start_period.'-'.$end_period;
	$update_sql ="UPDATE loan_book_backup
				  SET pd_12_current_preFLI_individual      = (
						SELECT pd_12_individual FROM pd_transition_matrices
						WHERE  pd_transition_matrices.reporting_period   = '$reporting_period'                       AND
							   pd_transition_matrices.start_period       = '$start_period'                           AND
							   pd_transition_matrices.business_unit_long = loan_book_backup.business_unit_long       AND
							   pd_transition_matrices.start_stage        = loan_book_backup.IFRS9_stage_current_year AND
							   pd_transition_matrices.end_stage          = 'PD3'),
				  
				      pd_12_current_preFLI_portfolio = (
						SELECT pd_12_portfolio FROM pd_transition_matrices
						WHERE  pd_transition_matrices.reporting_period   = '$reporting_period'                 AND
							   pd_transition_matrices.start_period       = '$start_period'                     AND
							   pd_transition_matrices.business_unit_long = loan_book_backup.business_unit_long AND
							   pd_transition_matrices.start_stage        = loan_book_backup.IFRS9_stage_current_year AND
							   pd_transition_matrices.end_stage          = 'PD3'),
				     
					 pd_life_current_preFLI_individual      = (
						SELECT pd_life_individual FROM pd_transition_matrices
						WHERE  pd_transition_matrices.reporting_period   = '$reporting_period'                       AND
							   pd_transition_matrices.start_period       = '$start_period'                           AND
							   pd_transition_matrices.business_unit_long = loan_book_backup.business_unit_long       AND
							   pd_transition_matrices.start_stage        = loan_book_backup.IFRS9_stage_current_year AND
							   pd_transition_matrices.end_stage          = 'PD3'),
				  
				      pd_life_current_preFLI_portfolio = (
						SELECT pd_life_portfolio FROM pd_transition_matrices
						WHERE  pd_transition_matrices.reporting_period   = '$reporting_period'                 AND
							   pd_transition_matrices.start_period       = '$start_period'                     AND
							   pd_transition_matrices.business_unit_long = loan_book_backup.business_unit_long AND
							   pd_transition_matrices.start_stage        = loan_book_backup.IFRS9_stage_current_year AND
							   pd_transition_matrices.end_stage          = 'PD3'),
				   
			          pd_period = $pd_period

				  WHERE  loan_book_backup.reporting_period  = $reporting_period";

    $update_result =  mysqli_query($connect,$update_sql);
    $updated_rows  =  mysqli_affected_rows($connect);

    //get the total rows in the loan book
     $count_result  = mysqli_query($connect,
	                          "SELECT count(account_number) as n 
	                           FROM   loan_book_backup 
							   WHERE  reporting_period = $reporting_period"); 
    
	//calculate the update ratio and create the AJAX update message 
	$total_rows        = mysqli_fetch_array($count_result);
	$update_percentage = round($updated_rows/$total_rows['n']*100,0);
    $status_message    = $update_percentage."% records successfully updated (".$updated_rows." of ".$total_rows['n'].")";	
	echo json_encode($status_message);
}   
if ($_POST['mode'] === 'view_scores') {
 	
    //get the omang and loan number chosen id
	$result = mysqli_query($connect,
	                       "SELECT omang_passport_num, 
						           loan_number 
						    FROM   cust_information 
				            WHERE  application_ref='" . $_POST['application_ref'] . "'"
						   );
    $row= mysqli_fetch_array($result);
	$score_sql    = "SELECT * FROM creditscore
				     WHERE omang_passport_num    = '".$row['omang_passport_num']."'  AND
						   loan_number           = '".$row['loan_number']."'";
	$score_result = mysqli_query($connect,$score_sql);
	$row          = mysqli_fetch_array($score_result);
	
	$personal_data_score 	= 	$row['marital_status_score'		]	+
								$row['age_score'				]	+
								$row['no_of_children_score'		]	+
								$row['dependants_at_home'		]	+
								$row['education_level_score'	]	+
								$row['professional_score'		]	+
								$row['employment_score'			]	+
								$row['years_at_job_score'		]	+
								$row['revenues_score'			];

	$w_personal_data_score 	= 	$row['w_marital_status_score'	]	+
								$row['w_age_score'				]	+
								$row['w_no_of_children_score'	]	+
								$row['w_dependants_at_home'		]	+
								$row['w_education_level_score'	]	+
								$row['w_professional_score'		]	+
								$row['w_employment_score'		]	+
								$row['w_years_at_job_score'		]	+
								$row['w_revenues_score'			];
							
							
	$html = '<thead>
	            <tr>
				    <th width = "40%">Scoring Attribute</th>
					<th width = "10%">Scored Points-Unweighted</th>
					<th width = "10%">Scored Points-Weighted</th>
					<th width = "40%">Scoring Comments</th>
				</tr>
			 </thead>
			 <tbody id = "scores">				   
				<tr>
				    <td>Marital Status</td>
					<td>'.$row['marital_status_score'].'</td>
					<td>'.$row['w_marital_status_score'].'</td>
				    <td></td>
				</tr>
				
				<tr>
				    <td>Age</td>
					<td>'.$row['age_score'].'</td>
					<td>'.$row['w_age_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Number of Children</td>
					<td>'.$row['no_of_children_score'].'</td>
					<td>'.$row['w_no_of_children_score'].'</td>
				    <td></td>
				</tr>
				<tr><td>Dependants At Home</td>
					<td>'.$row['dependants_at_home'].'</td>
					<td>'.$row['w_dependants_at_home'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Education Level</td>
					<td>'.$row['education_level_score'].'</td>
					<td>'.$row['w_education_level_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Profession</td>
					<td>'.$row['professional_score'].'</td>
					<td>'.$row['w_professional_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Employment</td>
					<td>'.$row['employment_score'].'</td>
					<td>'.$row['w_employment_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Years At Job</td>
					<td>'.$row['years_at_job_score'].'</td>
					<td>'.$row['w_years_at_job_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <td>Single or Double Salary</td>
					<td>'.$row['revenues_score'].'</td>
					<td>'.$row['w_revenues_score'].'</td>
				    <td></td>
				</tr>
				<tr>
				    <th>Total Personal Data</th>
					<th>'.$personal_data_score.'</th>
					<th>'.$w_personal_data_score.'</th>
				    <th></th>
				</tr>
			</tbody>
			<tfoot>
				<tr>
				    <th width = "40%">Scoring Attribute</th>
					<th width = "10%">Scored Points-Unweighted</th>
					<th width = "10%">Scored Points-Weighted</th>
					<th width = "40%">Scoring Comments</th>
				</tr>
			</tfoot>';					
    //$html = str_replace(array("\r", "\n"), '', $html);
    //get the records for all the reporting periods in the year before the reporting period selected	
    echo $html;
}   

if ($_POST['mode'] === 'average_annual_n_years') {
    //get the reporting period, reporting year and monthly actual for the chosen id
	$result = mysqli_query($connect,
	                       "SELECT reporting_period, 
						           reporting_year,
                                   business_unit,
                                   stage_transition,								   
								   value_month_actual,
                                   transition_years								   
						    FROM   pd_transition_matrices 
				            WHERE  rec_no='" . $_POST['id'] . "'"
						   );
    $row= mysqli_fetch_array($result);
    //get the records for all the reporting periods in the year before the reporting period selected
	
	$reporting_year    = $row['reporting_year'];
	$reporting_yearMIN = $reporting_year - 3;
	$reporting_period  = $row['reporting_period'];
	
	$result1 = mysqli_query($connect,
	                       "SELECT    reporting_period, 
								      value_annual_average
						    FROM      pd_transition_matrices 
						    WHERE     reporting_year   BETWEEN '".$reporting_yearMIN."' AND '".$reporting_year."'  AND
							          reporting_period <= '".$row['reporting_period']."' AND
								      business_unit     = '".$row['business_unit']."'    AND
								      stage_transition  = '".$row['stage_transition']."' AND
									  transition_years  = '".$row['transition_years']."'
						    ORDER BY  reporting_period DESC"
						   );
 
   $data = []; $json = []; 
	while ($line = mysqli_fetch_array($result1)) {
	   $json['reporting_period']   = $line['reporting_period'];
       $json['value_month_actual'] = $line['value_annual_average'] * 100;
	   $data[] = $json; 
	}
    echo json_encode($data);
}   

if ($_POST['mode'] === 'average_annual_n_years_count') {
    //get the reporting period, reporting year and monthly actual for the chosen id
	$result = mysqli_query($connect,
	                       "SELECT reporting_period, 
						           reporting_year,
                                   business_unit,
                                   stage_transition,								   
								   value_month_actual,
                                   transition_years								   
						    FROM pd_transition_matrices 
				            WHERE rec_no='" . $_POST['id'] . "'"
						   );
    $row= mysqli_fetch_array($result);
    //get the records for all the reporting periods in the year before the reporting period selected
	
	$reporting_year    = $row['reporting_year'];
	$reporting_yearMIN = $reporting_year - 3;
	$reporting_period  = $row['reporting_period'];
	
	$result1 = mysqli_query($connect,
	                       "SELECT DISTINCT reporting_year 
						    FROM pd_transition_matrices
							WHERE     reporting_year   BETWEEN  $reporting_yearMIN AND $reporting_year   AND
		    					      reporting_period <= '".$row['reporting_period']."'                 AND
								      business_unit     = '".$row['business_unit']."'                    AND
								      stage_transition  = '".$row['stage_transition']."'                 AND
									  transition_years  = '".$row['transition_years']."'
						    ORDER BY  reporting_year DESC"
                            );
   $data = []; $json = []; 
	while ($line = mysqli_fetch_array($result1)) {
	   $json['reporting_period']   = $line['reporting_year'];
       $json['value_month_actual'] = '';
	   $data[] = $json; 
	}
    echo json_encode($data);
}   


/** RECALCULATE THE RECOVERY AND CURE RATES FOLLOWING THE FOLLOWING STEPS
    STEP 1  -  Extract the account number, portfolio group and reporting period from the pd table
    STEP 2  -  Update write-off accounts balances on the loan_book_backup table
    STEP 3  -  Recalculate the recovery and cure rates from the loan_book_backup table 
    STEP 4  -  Save the recovery and cure rates into the recovery and cure rates table
    STEP 5  -  Update the recovery and cure rate averages

**/
if ($_POST['mode'] === 'calculator-icon') {
	
   //--------------------------------------------------------------------------------------------------------	
   //STEP 1 - Extract the account number, portfolio group and reporting period from the pd table
   //---------------------------------------------------------------------------------------------------------
	$extract_result = mysqli_query($connect,
	                       "SELECT 
						           reporting_year,
								   reporting_month,
								   reporting_period,
								   start_year,
								   start_month,
						           start_period,
								   business_unit_long,
                                   pd_12_individual as pd_mid,
								   pd_12_portfolio as cure_rate								   
						    FROM pd_transition_matrices 
						    WHERE id='" . $_POST['id'] . "'"
						   );
    $row= mysqli_fetch_array($extract_result);
    $start_year        = $row['start_year'];
	$start_month       = $row['start_month'];
	$start_period      = $row['start_period'];
    $reporting_year    = $row['reporting_year'];
	$reporting_month   = $row['reporting_month'];
	$reporting_period  = $row['reporting_period'];   
   //------------------------------------------------------------------------------------------------------
   //STEP 2  -  Update write-off accounts balances on the loan_book_backup table
   //-------------------------------------------------------------------------------------------------------
	$write_off_sql =  
	"UPDATE loan_book_backup
	 SET    
	    write_off_calc_period  = '".$reporting_period."',
        write_off_total_amount 
		= (SELECT SUM(write_off_amount)
           FROM write_offs
           WHERE 
	         loan_book_backup.account_number = write_offs.account_number AND 
             reporting_period between '".$start_period."' AND '".$reporting_period."')                 								  
	 WHERE reporting_period = '".$start_period."'" ;					   
	
	$write_off_sql_result = mysqli_query($connect,$write_off_sql); 
	
   //-------------------------------------------------------------------------------------------------------
   //STEP 3  -  Recalculate the recovery and cure rates from the loan_book_backup table
   //----------------------------------------------------------------------------------------------------------
	$pd_sql ="
	SELECT if(business_unit_long <=> null,'TOTAL BANK',business_unit_long) as 'Total-Bank' , 		            
			round(SUM(balance_local_current_year),2)  as 'Opening-Balance-Stage3',
			round(SUM(pay_down_next_year_discounted),2) as 'Recovered-amount',
		    round(SUM(balance_local_next_year),2)  as 'Closing-Balance-Stage3',
			round(SUM(CASE WHEN IFRS9_stage_next_period =  0 
					  THEN balance_local_current_year ELSE 0 END ),2) AS 'Cure Amount -Stage -1', 
			round(SUM(CASE WHEN IFRS9_stage_next_period =  1 
					  THEN balance_local_current_year ELSE 0 END ),2) AS 'Cure Amount -Stage 1', 
			round(SUM(CASE WHEN IFRS9_stage_next_period =  2 
					  THEN balance_local_current_year ELSE 0 END ),2) AS 'Cure Amount -Stage 2', 
			round(SUM(CASE WHEN IFRS9_stage_next_period IN (1,2) 
					  THEN balance_local_current_year ELSE 0 END),2) AS 'Cure Amount -Total', 
			round(SUM(pay_down_next_year_discounted),2)/    
			round(SUM(balance_local_current_year),2) as 'Recovery-Rate',
		 
			round(SUM(CASE WHEN IFRS9_stage_next_period IN (1,2) 
					  THEN balance_local_current_year ELSE 0 END ),2)/
			round(SUM(balance_local_current_year),2) as 'Cure-Rate'
					          
	FROM     loan_book_backup
	WHERE    reporting_period         = $start_period      and 
			 IFRS9_stage_current_year = 3                  and
			 write_off_total_amount is null
	GROUP BY business_unit_long WITH ROLLUP";	

	$pd_sql_result = mysqli_query($connect, $pd_sql);
	
   //-------------------------------------------------------------------------------------------------------
   //STEP 4  -  Save the recovery and cure rates into the recovery and cure rates table
   //----------------------------------------------------------------------------------------------------------
	//$data = [];
	while ($data_row = mysqli_fetch_assoc($pd_sql_result)) {
		$business_unit_long              = mysqli_real_escape_string($connect,$row['business_unit_long']);
		$value_annual_average            = floatval(mysqli_real_escape_string($connect,$data_row['Opening-Balance-Stage3']));
		$business_unit                   = mysqli_real_escape_string($connect,$data_row['Recovered-amount']);
		$value_month_actual              = floatval(mysqli_real_escape_string($connect,$data_row['Closing-Balance-Stage3']));
		$value_annual_average_prior_year = floatval(mysqli_real_escape_string($connect,$data_row['Cure Amount -Stage -1']));
		$value_average_n_years           = floatval(mysqli_real_escape_string($connect,$data_row['Cure Amount -Stage 1']));
		$pd_lower                        = floatval(mysqli_real_escape_string($connect,$data_row['Cure Amount -Stage 2']));
		$pd_upper                        = floatval(mysqli_real_escape_string($connect,$data_row['Cure Amount -Total']));
		$pd_mid                          = floatval(mysqli_real_escape_string($connect,$data_row['Recovery-Rate']));
		$cure_rate                       = floatval(mysqli_real_escape_string($connect,$data_row['Cure-Rate']));

		$BU_code = 'BANK';
		if ($business_unit_long == 'CORPORATE BANKING') 	{$BU_code = "CIB";}
		if ($business_unit_long == 'RETAIL BANKING') 		{$BU_code = "PBB";}
		if ($business_unit_long == 'TRANSACTIONAL BANKING') {$BU_code = "GIO";}												
		
		$pd_sql =  
		         "INSERT INTO pd_transition_matrices 
				 ( reporting_year,
				   reporting_month,
				   start_year,
                   start_month,
                   business_unit,
                   business_unit_long,				   
				   business_unit,
				   value_annual_average,
				   closing_balance_stage3,
				   value_annual_average_prior_year,
				   value_average_n_years,
                   pd_lower,
				   pd_upper,
				   pd_mid,
				   cure_rate
				  )
				  VALUES 
				 ( '$reporting_year',
				   '$reporting_month',
				   '$start_year',
                   '$start_month',
                   '$BU_code',
                   '$business_unit_long',				   
				   '$business_unit',
				   '$value_annual_average',
				   '$closing_balance_stage3',
				   '$value_annual_average_prior_year',
				   '$value_average_n_years',
                   '$pd_lower',
				   '$pd_upper',
				   '$pd_mid',
				   '$cure_rate'
				  )
				  ON DUPLICATE KEY UPDATE
					reporting_year   	   = '".$reporting_year."',
					reporting_month  	   = '".$reporting_month."',
					start_year             = '".$start_year."',
					start_month            = '".$start_month."',
					business_unit          = '".$BU_code."',
					business_unit_long     = '".$business_unit_long."',
					value_annual_average = '".$value_annual_average."',
					business_unit       = '".$business_unit."',
					value_month_actual= '".$closing_balance_stage3."',
					value_annual_average_prior_year     = '".$value_annual_average_prior_year."',
					value_average_n_years     = '".$value_average_n_years."',
					pd_lower     = '".$pd_lower."',
					pd_upper      = '".$pd_upper."',
					pd_mid          = '".$pd_mid."',
					cure_rate              = '".$cure_rate."'";

        $result = mysqli_query($connect, $pd_sql);
   
   //-------------------------------------------------------------------------------------------------------
   //STEP 5  -  Update the recovery and cure rate averages
   //----------------------------------------------------------------------------------------------------------
			
		$reporting_yearMIN   = $reporting_year - 3;
		// update the annual average, annual average prior year and value_average_pd_life_individual
		$avg_sql = "UPDATE pd_transition_matrices 
					SET pd_mgt_overlay = 
					   (SELECT avg(pd_mid) FROM pd_transition_matrices
						WHERE reporting_year     = $reporting_year        and
							  business_unit      = '".$BU_code."'         and
							  business_unit_long = '".$business_unit_long."'),

						pd_qualitative_factors = 
					   (SELECT avg(cure_rate) FROM pd_transition_matrices
						WHERE reporting_year     = $reporting_year        and
							  business_unit      = '".$BU_code."'         and
							  business_unit_long = '".$business_unit_long."')
					
					WHERE     reporting_year     = $reporting_year        and
							  reporting_month    = $reporting_month       and
							  start_year         = $start_year            and
							  start_month        = $start_month           and
							  business_unit      = '".$BU_code."'         and
							  business_unit_long = '".$business_unit_long."'";
								
		$result1  = mysqli_query($connect, $avg_sql);
												
		//update the average_n_years by calculating annual averages between years set on the settings
		$avgN_sql = "UPDATE pd_transition_matrices 
					 SET 
					   pd_12_individual = 
					  (SELECT avg(pd_mgt_overlay) FROM pd_transition_matrices
					   WHERE  reporting_year  BETWEEN  $reporting_yearMIN AND $reporting_year    and
						  business_unit      = "."'$BU_code'"."       and
						  business_unit_long = "."'$business_unit_long'"."),

					   pd_12_portfolio = 
					  (SELECT avg(pd_qualitative_factors) FROM pd_transition_matrices
					   WHERE  reporting_year  BETWEEN  $reporting_yearMIN AND $reporting_year    and
						  business_unit      = "."'$BU_code'"."       and
						  business_unit_long = "."'$business_unit_long'"."),
						
					   pd_life_individual = 
					  (SELECT DISTINCT count(reporting_year) FROM pd_transition_matrices
					   WHERE  reporting_year  BETWEEN  $reporting_yearMIN AND $reporting_year    and
							  business_unit      = "."'$BU_code'"."       and
							  business_unit_long = "."'$business_unit_long'".")

					WHERE reporting_year     = $reporting_year        and
						  reporting_month    = $reporting_month       and
						  business_unit      = "."'$BU_code'"."       and
						  business_unit_long = "."'$business_unit_long'";
		
		$result2  = mysqli_query($connect, $avgN_sql);									
 	}
	
   $response_message = "Total updated records = ".mysqli_affected_rows($connect);
				  
   mysqli_close($connect);
   
   echo json_encode($response_message);

}

if ($_POST['mode'] === 'book-icon') {
    
	//extract the account number, portfolio group and reporting period from the pd table
	$extract_result = mysqli_query($connect,
	                       "SELECT reporting_period,
						           start_period,
								   business_unit_long,
                                   pd_12_individual as pd_mid,
								   pd_12_portfolio as cure_rate								   
						    FROM pd_transition_matrices 
						    WHERE id='" . $_POST['id'] . "'"
						   );
    $row= mysqli_fetch_array($extract_result);
    $pd_period = $row['start_period'].'-'.$row['reporting_period'];
    
	//echo $pd_period;
	$update_sql = "UPDATE loan_book_backup
	               SET    pd_mid        = '".$row['pd_mid']."',
				          cure_rate            = '".$row['cure_rate']."',
                          pd_period           = '".$pd_period."' 				   
				   WHERE  reporting_period     = '".$row['reporting_period']."'    AND
				          business_unit_long   = '".$row['business_unit_long']."'";
	
	$update_result  = mysqli_query($connect,$update_sql);
    $updated_rows   = mysqli_affected_rows($connect);
    $json_message   = 'Total updated records = '.$updated_rows;
    //echo $json_message;
	echo json_encode($json_message);
}   




if ($_POST['mode'] === 'update') {

    mysqli_query($connect,
	             "UPDATE pd_transition_matrices 
				  SET  reporting_period             ='" . $_POST['reporting_period'] . "',
					   start_period                 ='" . $_POST['start_period'] . "',				 
					   business_unit             ='" . $_POST['business_unit'] . "',
					   value_annual_average       ='" . $_POST['value_annual_average'] . "',
					   value_month_actual      ='" . $_POST['closing_balance_stage3'] . "',
					   value_annual_average_prior_year           ='" . $_POST['value_annual_average_prior_year'] . "',
					   value_average_n_years           ='" . $_POST['value_average_n_years'] . "',
					   pd_lower           ='" . $_POST['pd_lower'] . "',
					   pd_upper            ='" . $_POST['pd_upper'] . "',
					   pd_mid                ='" . $_POST['pd_mid'] . "',
                       pd_mgt_overlay ='" . $_POST['pd_mgt_overlay'] . "',
                       pd_qualitative_factors     ='" . $_POST['pd_qualitative_factors'] . "',
                       pd_12_individual='" . $_POST['pd_12_individual'] . "',
                       pd_12_portfolio    ='" . $_POST['pd_12_portfolio'] . "',
                       pd_life_individual                ='" . $_POST['pd_life_individual'] . "'
				  
				  
				  WHERE id='" . $_POST['id'] . "'"
				);
    echo json_encode(true);
}  

if ($_POST['mode'] === 'delete') {

     mysqli_query($connect, 
	             "DELETE FROM pd_transition_matrices 
				  WHERE  id='" . $_POST["id"] . "'"
				  );
     echo json_encode(true);
}  

if ($_POST['mode'] === 'clear') {

     mysqli_query($connect,"DELETE FROM pd_transition_matrices");
     echo json_encode(true);
}  
if ($_POST['mode'] === 'import') {
 	 $imported_lines = 0;
	 $data =[];
	 include 'import_pd_data_csv.php';
	 if (count($data) == 0) { 
	     $data['ErrorMessage'] = 'No Data';
		 echo json_encode($data);
		 exit;
	 }
	for ($x=0; $x < count($data); $x++) { 
	    $reporting_period             = $data[$x]['reporting_period'];
		$start_period                 = $data[$x]['start_period'];   
		$value_annual_average       = $data[$x]['value_annual_average'];
		$business_unit             = $data[$x]['business_unit'];
		$value_month_actual      = $data[$x]['closing_balance_stage3'];
		$value_annual_average_prior_year           = $data[$x]['value_annual_average_prior_year'];
		$value_average_n_years           = $data[$x]['value_average_n_years'];
		$pd_lower           = $data[$x]['pd_lower'];
		$pd_upper            = $data[$x]['pd_upper'];
		$pd_mid                = $data[$x]['pd_mid'];
		$cure_rate                    = $data[$x]['cure_rate'];
		$pd_mgt_overlay = $data[$x]['pd_mgt_overlay'];
		$pd_qualitative_factors     = $data[$x]['pd_qualitative_factors'];
		$pd_12_individual= $data[$x]['pd_12_individual'];
		$pd_12_portfolio    = $data[$x]['pd_12_portfolio'];
		$pd_life_individual                = $data[$x]['pd_life_individual'];
		$business_unit                = $data[$x]['business_unit'];
        $business_unit_long = 'TOTAL BANK';
		if ($business_unit == 'CIB') {
            $business_unit_long = 'CORPORATE BANKING';
        }			
        if ($business_unit == 'PBB') {
            $business_unit_long = 'RETAIL BANKING';
        }			
        if ($business_unit == 'GIO') {
            $business_unit_long = 'TRANSACTIONAL BANKING';
        }			
		$reporting_year               = substr($reporting_period,0,4);
		$start_year                   = substr($start_period,0,4);
		$reporting_month              = substr($reporting_period,4,2);
		$start_month                  = substr($start_period,4,2);
		
		mysqli_query($connect, 
				 "INSERT INTO pd_transition_matrices 
				 ( reporting_year,
                   reporting_month,
				   start_year,
                   start_month,				   
				   business_unit,
				   business_unit_long,
				   business_unit,
				   value_annual_average,
				   closing_balance_stage3,
				   value_annual_average_prior_year,
				   value_average_n_years,
                   pd_lower,
				   pd_upper,
				   pd_mid,
				   cure_rate,
				   pd_mgt_overlay,
				   pd_qualitative_factors,
				   pd_12_individual,
				   pd_12_portfolio,
				   pd_life_individual
				  )
				  VALUES 
				 ( '$reporting_year',
                   '$reporting_month',
                   '$start_year',				 
                   '$start_month',				 
				   '$business_unit',
				   '$business_unit_long',
				   '$business_unit',
				   '$value_annual_average',
				   '$closing_balance_stage3',
				   '$value_annual_average_prior_year',
				   '$value_average_n_years',
                   '$pd_lower',
				   '$pd_upper',
				   '$pd_mid',
				   '$cure_rate',
				   '$pd_mgt_overlay',
				   '$pd_qualitative_factors',
				   '$pd_12_individual',
				   '$pd_12_portfolio',
				   '$pd_life_individual'
				  )
				  ON DUPLICATE KEY UPDATE
				   reporting_year               = '".$reporting_year."',
				   reporting_month              = '".$reporting_month."',
				   start_year                   = '".$start_year."',				 
				   start_month                  = '".$start_month."',				 
				   business_unit                = '".$business_unit."',
				   business_unit_long           = '".$business_unit_long."',
				   business_unit             = '".$business_unit."',
				   value_annual_average       = '".$value_annual_average."',
				   value_month_actual      = '".$closing_balance_stage3."',
				   value_annual_average_prior_year           = '".$value_annual_average_prior_year."',
				   value_average_n_years           = '".$value_average_n_years."',
				   pd_lower           = '".$pd_lower."',
				   pd_upper            = '".$pd_upper."',
				   pd_mid                = '".$pd_mid."',
				   cure_rate                    = '".$cure_rate."',
				   pd_mgt_overlay = '".$pd_mgt_overlay."',
				   pd_qualitative_factors     = '".$pd_qualitative_factors."',
				   pd_12_individual= '".$pd_12_individual."',
				   pd_12_portfolio    = '".$pd_12_portfolio."',
				   pd_life_individual                = '".$pd_life_individual."'"
				 );		 
	 } //end for
	 $data['ErrorMessage'] = '';
	 $data['imported_records'] = count($data)-1;
     echo json_encode($data);
}  



?>