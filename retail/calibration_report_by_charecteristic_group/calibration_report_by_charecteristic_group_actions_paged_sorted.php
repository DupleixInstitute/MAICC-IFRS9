<?php

try
{
	//Open database connection
	include ("../assets/includes/database_connection.php");
	//$con = mysqli_connect("localhost","root","","creditscoring");

	//Getting records (listAction)
	if($_GET["action"] == "list")
	{
		//Get record count
		//$result      = mysqli_query($connect,"SELECT COUNT(*) AS RecordCount FROM model_calibration");
		//$row         = mysqli_fetch_array($result,MYSQLI_ASSOC);
		$recordCount = 15;//$row['RecordCount'];

		//Get records from database
		$result = mysqli_query
		($connect,
		     "WITH scores AS
             (SELECT 
				charecteristic,
                charecteristic_group as charecteristic_group,
				score_option_upper,
				min(mortgage_effective_score) as min_score,
				max(mortgage_effective_score) as max_score,
				round(sum(min(mortgage_effective_score)) over(),2) as total_min_scores,
				round(sum(max(mortgage_effective_score)) over(),2) as total_max_scores,
				concat(round(max(mortgage_effective_score) /
				sum(max(mortgage_effective_score)) over() *100,2),'%') as effective_weight
			
			 FROM model_calibration

			 GROUP BY charecteristic)


			SELECT
			   charecteristic_group,
			   round(sum(min_score),2) as min_score,
			   round(sum(max_score) ,2) as max_score,
			   total_min_scores,
			   total_max_scores,
			   round(sum(max_score)/total_max_scores * 100 ,2) as group_weight_no_percentage,
			   concat(round(sum(max_score)/total_max_scores * 100 ,2),'%') as group_weight_with_percentage
			   	   
			from scores

			group by charecteristic_group

			order by group_weight_no_percentage DESC;");
		
		//Add all records to an array
		$rows = array();
		while($row = mysqli_fetch_array($result,MYSQLI_ASSOC))
		{
		    $rows[] = $row;
		}

		//Return result to jTable
		$jTableResult = array();
		$jTableResult['Result'] = "OK";
		$jTableResult['TotalRecordCount'] = $recordCount;
		$jTableResult['Records'] = $rows;
		print json_encode($jTableResult);
	}
	//Creating a new record (createAction)
	else if($_GET["action"] == "create")
	{
		//Insert record into database
	}
	//Updating a record (updateAction)
	else if($_GET["action"] == "update")
	{
		//Update record in database

	}
	//Deleting a record (deleteAction)
	else if($_GET["action"] == "delete")
	{
	}

	//Close database connection
	mysqli_close($connect);

}
catch(Exception $ex)
{
    //Return error messrealisation_period
	$jTableResult = array();
	$jTableResult['Result'] = "ERROR";
	$jTableResult['Message'] = $ex->getMessage();
	print json_encode($jTableResult);
}
	
?>