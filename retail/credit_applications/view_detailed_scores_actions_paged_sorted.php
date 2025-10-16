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
		$result = mysqli_query($connect,"SELECT COUNT(*) AS RecordCount FROM creditscore");
		$row    = mysqli_fetch_array($result,MYSQLI_ASSOC);
		$recordCount = $row['RecordCount'];

		//Get records from database
		$result = mysqli_query
		($connect,
		    "SELECT * FROM creditscore 
			 ORDER BY " . $_GET["jtSorting"] . " LIMIT " . $_GET["jtStartIndex"] . "," . $_GET["jtPageSize"] . ";");
		
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
		$result = mysqli_query
		($connect,
			"INSERT INTO creditscore
			(
			charecteristic, 
			charecteristic_group, 
			score_option, 
			reject_threshold,
			total_scores,
			mortgage_max_score,
			mortgage_score_adj,
			unsecured_loan_max_score,
			unsecured_loan_score_adj
			) 
			VALUES
			(
			'" . $_POST["charecteristic"          ]."', 
			'" . $_POST["charecteristic_group"    ]."',
			'" . $_POST["score_option"            ]."',
			 " . $_POST["reject_threshold"        ].",
			 " . $_POST["total_scores"            ]. ",
			 " . $_POST["mortgage_max_score"      ].",
			 " . $_POST["mortgage_score_adj"      ].",
			 " . $_POST["unsecured_loan_max_score"].",
			 " . $_POST["unsecured_loan_score_adj"]."
			);"
		);
			
		//Get last inserted record (to return to jTable)
		$result = mysqli_query
		($connect,
		      "SELECT * 
			   FROM 
			   creditscore 
			   WHERE 
			        id = LAST_INSERT_ID()");
		$row = mysqli_fetch_array($result,MYSQLI_ASSOC);

		//Return result to jTable
		$jTableResult = array();
		$jTableResult['Result'] = "OK";
		$jTableResult['Record'] = $row;
		print json_encode($jTableResult);
	}
	//Updating a record (updateAction)
	else if($_GET["action"] == "update")
	{
		//Update record in database
		$result = mysqli_query
		($connect,
		    "UPDATE creditscore 
			 SET    
			        charecteristic           = '" . $_POST["charecteristic"   ] . "', 
			        charecteristic_group     = '" . $_POST["charecteristic_group"   ] . "', 
				    score_option_upper       =  " . $_POST["score_option_upper"] . ", 
				    reject_threshold         =  " . $_POST["reject_threshold"] . ",
				    total_scores             =  " . $_POST["total_scores"] . ",
					mortgage_max_score       =  " . $_POST["mortgage_max_score"] . ",
					mortgage_score_adj       =  " . $_POST["mortgage_score_adj"] . ",
					unsecured_loan_max_score =  " . $_POST["unsecured_loan_max_score"] . ",
					unsecured_loan_score_adj =  " . $_POST["unsecured_loan_score_adj"] . "

					
			 WHERE  id                       =  " . $_POST["id"     ] . ";"
		);

		//Return result to jTable
		$jTableResult = array();
		$jTableResult['Result'] = "OK";
		print json_encode($jTableResult);
	}
	//Deleting a record (deleteAction)
	else if($_GET["action"] == "delete")
	{
		//Delete from database
		$result = mysqli_query
		($connect,
		   "DELETE FROM creditscore 
		    WHERE  id = " . $_POST["id"] . ";"
		 );

		//Return result to jTable
		$jTableResult = array();
		$jTableResult['Result'] = "OK";
		print json_encode($jTableResult);
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