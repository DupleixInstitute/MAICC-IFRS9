<?php
	  $town_sql_result = mysqli_query($connect,$town_sql);
	  echo'<select name="town" id="town" class ="form-select">';
	  while($row = mysqli_fetch_assoc($town_sql_result)) { 
		echo '<option value="'. $row['town'] .'">' . $row['town'] . '</option>';    
	  }
	  echo '</select>';
?>