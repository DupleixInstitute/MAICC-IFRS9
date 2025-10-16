<?php
	$town_sql = "SELECT * FROM town order by town asc" ;
	try {
	  $town_sql_result = mysql_query($town_sql,$connect);
	  echo'<select name="town" id ="town">';
	  while($row = mysql_fetch_assoc($town_sql_result)) { 
		 echo '<option value="'. $row['town'] .'">' . $row['town'] . '</option>';    
	  }
	  echo '</select>';
	}
	catch (Error $e) {
	  $town_sql_result = mysqli_query($connect,$town_sql);
	  echo'<select name="town" id="town" class ="form-select">';
	  while($row = mysqli_fetch_assoc($town_sql_result)) { 
		echo '<option value="'. $row['town'] .'">' . $row['town'] . '</option>';    
	  }
	  echo '</select>';
	}
?>