							   <?php 
							   `  include '../assets/includes/database_connection.php';
                                  $score_sql    = "SELECT * FROM creditscore
								                   WHERE omang_passport_num    = '32132474B77'   AND
												         loan_number           = 1";
					              $score_result = mysqli_query($connect,$score_sql);
								  while ($row = mysqli_fetch_array($score_result)) {
								  }
							   ?>
