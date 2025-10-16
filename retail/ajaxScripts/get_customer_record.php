<?php
try {
	include ("../assets/includes/database_connection.php"); //Open database connection;
	
	$omang_passport_num = $_POST['omang'];
	$loan_number        = $_POST['loan_number'];

	$sql="SELECT * FROM cust_information where omang_passport_num='$omang_passport_num' AND loan_number=$loan_number";
	$result=(mysqli_query($connect,$sql));

	$count    = 0;
	$responce = [];
	while($row=mysqli_fetch_array($result)){
		$count++; 
	    $responce['errorStatus']  = 'Success';
	    $responce['data']         = $row;
	}
	if ($count ==0) {
	   $responce['errorStatus']   = 'Error';
	   $responce['errorText']     = 'No customer record found';
	}
} catch(Exception $e) {
	$responce['errorStatus']      = 'Error';
	$responce['errorText']        = $e->getMessage();
}

echo json_encode($responce);
?>
