<html>
<head>
    <title>Database Table Export - Csv File</title>
	<!-- DataTables CSS library -->
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css"/>
	<script src='https://kit.fontawesome.com/a076d05399.js' crossorigin='anonymous'></script>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables JS library -->
	<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
	<script src="../assets/js/sweetalert.min.js"></script>
	<script src="../assets/js/dataTables.buttons.min.js"></script>
	<script src="../assets/js/jszip.min.js"></script>
	<script src="../assets/js/pdfmake.min.js"></script>
	<script src="../assets/js/vfs_fonts.js"></script>
	<script src="../assets/js/buttons.html5.min.js"></script>
	<script src="../assets/js/buttons.print.min.js"></script>
	<link href ="../assets/css/Dupleix.css" rel="stylesheet" type="text/css">
	<link rel="stylesheet" href="../assets/font-awesome-4.7.0/css/font-awesome.css">

   
  <script> 
    var TableNames          = [];
	var ColumnNames         = [];
    var jsonDataHolder      = [];
	var HeaderRow           = 1;
	var SubmitButtonClicked = "";
	$(function(){
         $('body').on('click', '.download', function () {
		   var table_name = $(this).data('id');
		   console.log("table name = "+ table_name);
		   
		   //update hidden download_id with selected file id and submit for download
		   var start_date_id  = '#' + table_name + '_start_date';
		   var end_date_id = '#' + table_name + '_end_date';
		   
		   //reporting periods data
		   var start_date_value  = $(start_date_id).val();
		   var end_date_value    = $(end_date_id).val();
		   
		   $('#selected_table').val(table_name);
		   $('#selected_start_date').val(start_date_value); 
		   $('#selected_end_date').val(end_date_value);
		   
		   console.log("selected start date  = "+ start_date_value);
		   console.log("selected end date  = "+ end_date_value);
		   
		   $('#download-form').submit();
          		   
		});
 
		$('#upload_csv').on('submit', function(event){
		    // skip capturing of event if not the upload button pressed
			if (SubmitButtonClicked == "Upload") {
				  event.preventDefault();
				  $.ajax({
				   url:"import_general_csv_file.php",
				   method:"POST",
				   data:new FormData(this),
				   dataType:'json',
				   contentType: false,
				   cache:false,
				   processData:false,
				   error: function (jqXHR, exception) {
						var error_msg = '';
						if (jqXHR.status === 0) {
						error_msg = 'Not connect.\n Verify Network.';
						} else if (jqXHR.status == 404) {
						// 404 page error
						error_msg = 'Requested page not found. [404]';
						} else if (jqXHR.status == 500) {
						// 500 Internal Server error
						error_msg = 'Internal Server Error [500].';
						} else if (exception === 'parsererror') {
						// Requested JSON parse
						error_msg = 'Requested JSON parse failed.';
						} else if (exception === 'timeout') {
						// Time out error
						error_msg = 'Time out error.';
						} else if (exception === 'abort') {
						// request aborte
						error_msg = 'Ajax request aborted.';
						} else {
						error_msg = 'Uncaught Error.\n' + jqXHR.responseText;
						}
						// error alert message
						alert('error :: ' + error_msg);
					},
				   success:function(jsonData)
				   {
					//Copy the received jsonData into a global variable
					jsonDataHolder = jsonData;
					HeaderRow = $('#headerRow').val()-1;
					
					var col_read = jsonDataHolder[HeaderRow].length;
					if ($('#numcols').val() == 0) {$('#numcols').val(col_read)};
					//alert (HeaderRow);
					//alert (jsonDataHolder[HeaderRow][0]);			
					//alert (jsonData[2]['customer_name']);
					//$('#csv_file').val('');
					Updatetable();
				   }      // end of Ajax success event
				   }); //end of ajax event
		    }  // End if to check submit button clicked
            else { 
			   //later you decide you want to submit
            }			   
		});  //end of upload csv event
	});
	   
	
  </script>	

<BODY>

<form id="download-form" method="post" name = "download-form" enctype="multipart/form-data" action = "download_table.php">
 
<div class = "DivHeader">
  <h3> DATABASE TABLE EXPORT : Select Table </h3>
</div>

<div class="bs-example" style = "margin-left:0px">
<div class="container-fluid">

<div class="table-responsive">
  <table class="table table-striped table-bordered" id="data-table">
     <thead>
      <tr>
       <th width = "5%" >Table No</th>
       <th width = "20%">Table Name</th>
       <th width = "5%" >Start Date</th>
       <th width = "5%" >End Date</th>
       <th width = "10%">Action</th>
      </tr>
     </thead>
	 <tbody>
		<?php
			//*******************************************************************************************************************************
			//*Name of the script       :table_dump.php
			//*Calling Script           :index.php
			//*Action Script            :
			//*Purpose of the script    :List the table names and allow export of data for a selected period in csv format
			//*Algorithm Steps          :1.  List the database tables
			//                           2.  
			//                           2.1 
			//                           2.2 
			//                           3.  
			//                           4.  
			//                           4.1 
			//                           4.2 
			//                           5   
			//                           5.1  
			//                           5.3  
			//                           5.4  
			//                             
			//******************************************************************************************************************************8*
			
			//** STEP 1  OBTAIN THE REPORTING PERIOD FROM THE PREVIOUS SCRIPT
			/**
			$reporting_year       = $_POST['reporting_year'];
			$reporting_month      = $_POST['reporting_month'];
			$primary_key          = $_POST['primary_key'];
			$portfolio_group      = $_POST['portfolio_group'];
			$source_file_template = $_POST['source_file_template'];
			$table_name           = $_POST['table_name'];
		     **/
			//** STEP 2.1 CONNECTING TO THE DATABASE TO READ THE TABLE COLUMN NAMES**********************************************************
			include '../assets/includes/database_connection.php';
			if (!$connect) {
			  mysqli_close($connect); 
			  echo "Cannot connect to the database! Please Check your username and password."; 
			  //exit;
			}
			
			// obtain table column names form database and load into $table_names array
			$sql = "SHOW TABLES FROM creditscoring";
			$table_list  = [];
			$showtables  = mysqli_query($connect, $sql);
			while($table = mysqli_fetch_array($showtables)) { 
			   $table_list[] = $table[0];
			}
			//sort table names;
			sort($table_list);
			mysqli_close($connect);
            $table_no = 0;
		    
			for ($index = 0; $index < count($table_list); $index++) {
			   $table_no = $index + 1;
			   echo '<tr><td>'.$table_no.'</td>';
			   echo '<td>'.$table_list[$index].'</td>';
			   echo '<td><input  type = "date"  
			                     id   = "'.$table_list[$index].'_start_date"  
			                     name = "'.$table_list[$index].'_start_date"  
					></td>';
			   echo '<td><input  type = "date"  
			                     id   = "'.$table_list[$index].'_end_date"  
			                     name = "'.$table_list[$index].'_end_date" 
                                 value=  localtime()								 
					></td>';
			   echo '<td><button type   = "button" 
		                         data-id= "'.$table_list[$index].'"	            
          						 class  = "download" 
								 id      = "'.$table_list[$index].'">
								 Download
						</button>,</tr>';
			}  // end for for table names iterations				
            
			
			
			// Create hidden data fields for the selected table which is clicked
			echo '<input type="text" name="selected_table" id="selected_table" />';									 
			echo '<input type="date" name="selected_start_date"  id="selected_start_date"/>';									 
			echo '<input type="date" name="selected_end_date"    id="selected_end_date"/>';									 
			
	
      ?>
     </tbody>	  
 	<tfoot>
	<tr>
      <td></td>
	  <td></td>
      <td>	<input type="submit" name="display" id="display" value="Display Data" style="margin-top:10px;" class="btn btn-info"  />
	  </td> 
      <td></td>
	  <td></td>
	</tr>
	</tfoot>
  </table>
</div>
</div>
</div>
  
       
  
  
  </form>
</body>
</head>
</html>