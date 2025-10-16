
<?php
	
	include '../assets/includes/database_connection.php';
			
	$table_name           = $_POST['selected_table'];
 	$selected_start_date  = $_POST['selected_start_date'];
 	$selected_end_date    = $_POST['selected_end_date'];


	// BEGIN: Define some variables
	// INSTRUCTION: Specify your table name and the name of your export file.

	// The name of data table containing the data you wish to export

	// The filename you want your export file to be named
	$Filename = $table_name."_from ".$selected_start_date."to".$selected_end_date; 
	// END: Define some variables

	// *** No more configurable options below this point for this code to function on most servers ***
	// Fetch records from the database table specified in the variable $TableName
	$Output = "";
    // fetch file to download from database
    
	if ($table_name == 'approvals') {
		$sql = "SELECT * FROM ".$table_name." 
				WHERE date BETWEEN '".$selected_start_date."' AND '".$selected_end_date."'";		
	} else if ($table_name == 'approved') {
		$sql = "SELECT * FROM ".$table_name." 
				WHERE date BETWEEN '".$selected_start_date."' AND '".$selected_end_date."'";		
	}else if ($table_name == 'cust_credit_rating') {
		$sql = "SELECT * FROM ".$table_name." 
				WHERE date BETWEEN '".$selected_start_date."' AND '".$selected_end_date."'";		
	} else if ($table_name == 'cust_information') {
		$sql = "SELECT * FROM ".$table_name." 
				WHERE creation BETWEEN '".$selected_start_date."' AND '".$selected_end_date."'";		
	} else if ($table_name == 'returned') {
		$sql = "SELECT * FROM ".$table_name." 
				WHERE date BETWEEN '".$selected_start_date."' AND '".$selected_end_date."'";		
	} else {
		$sql = "SELECT * FROM ".$table_name;		
	}
    $sql_result = mysqli_query($connect, $sql);

	// If an error occurred while connecting to the database, display the error code and exit.
	if (!$connect) {
		echo "Error: Unable to connect to MySQL." . PHP_EOL;
		echo "Debugging error #: " . mysqli_connect_errno() . PHP_EOL;
		echo "Error description: " . mysqli_connect_error() . PHP_EOL;
		exit;
	} else {
		// Determine the number of data columns in the table
		$columns_total = mysqli_num_fields($sql_result);

		// Get the name of the data columns so it can be used in the header row of the export file.
		// Content of the export file is temporarily saved in the variable $Output
		for ($i = 0; $i < $columns_total; $i++) {
		  $Heading = mysqli_fetch_field_direct($sql_result, $i);
		  $Output .= '"' . $Heading->name . '",';
		}
		$Output .= "\n";		
		// The /n is the control code to go to a new line in the export file.

		// Loop through each record in the table and read the data value from each column.
		while ($row = mysqli_fetch_array($sql_result)) {
		  for ($i = 0; $i < $columns_total; $i++) {
			 $Output .= '"' . $row["$i"] . '",';
		  }
		  $Output .= "\n";
		}

		// Create the export file and name it with the name specified in variable $Filename
		// Also appends the current timestamp (in the format yyyymmddhhmmss) to the filename and give it a .CSV file extension.
		// The timestamp serves as a time reference to identify when the data was exported.
		//File is comma delimited with double-quote used a the text qualifier
		// Once  file is created, download of the file begins automatically (tested on Google Chrome).
		$TimeNow = date("YmdHis");
		$Filename .= $TimeNow . ".csv";
		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename=' . $Filename);
		echo $Output;
	}
	exit;
?>
