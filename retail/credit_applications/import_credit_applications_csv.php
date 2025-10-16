<?php

// Import template header VALIDATION LIST 
$headerLIST = 
			[
				'EntryNo',
				'End-Period',
				'Start-Period',
				'BU',
				'Opening Balance - Stage 3',
				'Less Recovered Amount',
				'Closing Balance - Stage 3',
				'Cured Amount - Stage -1',
				'Cured Amount - Stage 1',
				'Cured Amount - Stage 2',
				'Cured Amount - Total',
				'Recovery Rate',
				'Cure Rate',
				'RR-Annual Avg',
				'CR-Annual Avg',
				'RR-n years Avg',
				'CR-n years Avg',
				'n years count',
				'Created',
				'Action'
			];

if(!empty($_FILES['file']['name']))
{
 
 $file_name='../../import/uploads/'.basename($_FILES['file']['name']);

 if(move_uploaded_file($_FILES['file']['tmp_name'],$file_name)) {
	//header("Content-Length: ".filesize($file_name));
   $file_data = fopen($file_name, 'r');
 }
 $SkipLines = 1;
 $RowNo = 0;
 $LineNo = 0;
 //$handle = fgetcsv($file_data);
 while(($row = fgetcsv($file_data,1000,",")) !==FALSE)
 {	
	if ($RowNo == 0)  {
	   $data['ErrorMessage'] = '';	   
	   //**PERFORM VALIDATION CHECKS ON THE HEADER COLUMNS***************************************************
	   //check if the import template headers are correct as per the import template 
	   //if incorrect then store the alert message in $Data[0]['collateral_notes'] for ajax response
	   
	   //check if the count of header columns matches the correct count on top of this script
	   if (count($row) !== count($headerLIST)) {
		   $data['ErrorMessage'] = "Header columns count must be ".count($headerLIST)." and not ".count($row);
	   } else {
		   //check with a foreach loop if the import template header names match the correct names on start of script
		   $colINDEX = 0;
		   foreach ($row as $header) {
			   if ($header !== $headerLIST[$colINDEX]) {
				  $headerCol = $colINDEX + 1;
				  {$data['ErrorMessage'] = "Header Column No ".$headerCol." must be '".$headerLIST[$colINDEX]."'";}
			   }
			   $colINDEX++;
		   }
	   }
       //if there is a validation error then json-encode the last error message and exit
	   if ($data['ErrorMessage'] !== '') {
		  echo (json_encode($data));
		  exit;
       }		   
	   $data = [];
	}
	
	
	//Increase the line count only after the header columns
    $RowNo++;
	if ($RowNo > $SkipLines) {$LineNo++;}
    ;
	//extract rows after the header columns and add to associative array $data
    If ($LineNo !== 0){
		//VALIDATION CHECKS - Abort if required columns are blank 
		if ($row[0] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Entry No must not be blank - Count from 1";}
		if ($row[1] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": End period is reporting period and must not be blank";}
		if ($row[2] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Start period must not be blank";}
		if ($row[3] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Business Unit must not be blank/zero";}
		if ($row[11] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Recovery Rate for the reporting period must not be blank";}
		if ($row[12] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Cure Rate for the reporting period must not be blank";}
		if ($row[13] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Recovery Rate, annual average for the reporting year must not be blank";}
		if ($row[14] == '') {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Cure Rate, annual average for the reporting for the reporting year must not be blank";}
		
		
		//*********************validate the reporting and start periods**********************************
		//1. length must be in the format yyyymm which is a length of 6
		if (strlen((string)$row[1]) !== 6) {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": End period is reporting period and must be 6 digits";}
		if (strlen((string)$row[2]) !== 6) {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Start period is must 6 be digits";}
		//2. End period must be greater than start period
		if ($row[2] > $row[1]) {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": Start period cannot be greater than end period";}
		//3. The month represented by the last 2 digits must be between 01 and 12
		if (substr($row[1],4,2) > 12) {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": The reporting month cannot be greater than 12";}
		if (substr($row[2],4,2) > 12) {
		   $data['ErrorMessage'] = "Line No ".$LineNo.": The start month cannot be greater than 12";}
		//******************************************************************************************************
		//if there is a validation error then json-encode the last error message and exit
	   if (isset($data['ErrorMessage'])) {
 		   echo (json_encode($data));
		   exit;
        }	
		$data[] = 
		array(
				'reporting_period'                => $row[1], 
				'start_period'                    => $row[2], 
				'business_unit'                   => $row[3], 
				'opening_balance_stage3'          => $row[4], 
				'recovered_amount'                => $row[5], 
				'closing_balance_stage3'          => $row[6], 
				'cure_amount_stage0'              => $row[7], 
				'cure_amount_stage1'              => $row[8], 
				'cure_amount_stage2'              => $row[9], 
				'cure_amount_total'               => $row[10], 
				'recovery_rate'                   => $row[11],
				'cure_rate'                       => $row[12],
				'recovery_rate_annual_average'    => $row[13],
				'cure_rate_annual_average'        => $row[14],
				'recovery_rate_average_n_years'   => $row[15],
				'cure_rate_average_n_years'       => $row[16],
				'n_years_count'                   => $row[17]
			 );
			  
    } //end if
	if (!isset($data['ErrorMessage'])) {
	}   else  {
		$imported_lines = 0;
	}			
   
 }// end while


} //if IISSET

?> 
