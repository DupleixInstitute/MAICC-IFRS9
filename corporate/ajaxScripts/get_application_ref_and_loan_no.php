<?php
	$responce = [];
	include "../assets/includes/database_connection.php"; //Open database connection;

	$company_reg_no = 	$_POST['company_reg_no'];

	$sql			=	"SELECT * FROM loan_data where company_reg_no='$company_reg_no'";
	$result			=	(mysqli_query($connect,$sql));

	$next_count							=	mysqli_num_rows($result)+1;
	$Hold_Record_Query  			= 	"INSERT INTO loan_data 
	                                     SET company_reg_no = '$company_reg_no',
										     loan_number    = $next_count
                                         ON DUPLICATE KEY UPDATE
										    company_reg_no  = '$company_reg_no',
										    loan_number     = $next_count";
	$Hold_Record_Success			= 	mysqli_query($connect,$Hold_Record_Query); 
	
	if ($Hold_Record_Success)
	{
	   $responce['application_ref'] = 	mysqli_insert_id($connect);
    }
    $responce['loan_no'] 			= 	$next_count;

    echo json_encode($responce);
?>
