<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>MACRO STATISTICS REGRESSION VARIABLE DEFINITIONS</title>
<!-- DataTables CSS library -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css"/>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

<!-- DataTables JS library -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/sweetalert.min.js"></script>
<script src="../../assets/js/dataTables.buttons.min.js"></script>
<script src="../../assets/js/jszip.min.js"></script>
<script src="../../assets/js/pdfmake.min.js"></script>
<script src="../../assets/js/vfs_fonts.js"></script>
<script src="../../assets/js/buttons.html5.min.js"></script>
<script src="../../assets/js/buttons.print.min.js"></script>
<link href="../../assets/css/Dupleix.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="../../assets/font-awesome-4.7.0/css/font-awesome.css"">

    <style type="text/css">
        .bs-example{
            margin: 0px 0px 0px 0px;
        }
		 .box
		  {
		   max-width:1000px;
		   width:100%;
		   margin: 0 auto;
		   
		  }
    </style>
</head>
<body>
    <div class="bs-example" style = "margin-left:0px">
        <div class="container-fluid">
            <div class="row"> 
                <div class="col-md-12">
                    <div class="DivHeader">
                        <h3>MACRO STATISTICS REGRESSION VARIABLE DEFINITIONS - Maintenance</h3>
                        <a href="javascript:void(0)" class="btn btn-primary float-right add-model"> Add Regression Definition</a>
						<a href="javascript:void(0)" class="btn btn-light float-right import-model">Import Regression Definitions</a>                
						<a href="javascript:void(0)" class="btn btn-light float-right clear-model">Clear All</a>                
                    </div>
                     
                   <table id="regression_definitions_table" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th width = "5%" >Entry<br>No</th>
                                <th width = "5%" >Statistic<br>Code</th>
								<th width = "15%">Statistic Name</th>
								<th width = "5%" >Credit<br>Loss<br>Code</th>
		                        <th Width = "15%">Credit<br>Loss<br>Name</th>
								<th width = "5%" >Expected<br>Correlation<br>Sign (Positive /Negative)</th>
 								<th width = "5%" >R2<br>Cut-off<br>(%)</th>
 								<th width = "20%">Explanatory Comment</th>
								<th width = "10%">Created</th>
                                <th width = "15%">Action</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th width = "5%" >Entry<br>No</th>
                                <th width = "5%" >Statistic<br>Code</th>
								<th width = "15%">Statistic Name</th>
								<th width = "5%" >Credit<br>Loss<br>Code</th>
		                        <th Width = "15%">Credit<br>Loss<br>Name</th>
								<th width = "5%" >Expected<br>Correlation<br>Sign (Positive /Negative)</th>
 								<th width = "5%" >R2<br>Cut-off<br>(%)</th>
 								<th width = "20%">Explanatory Comment</th>
								<th width = "10%">Created</th>
                                <th width = "15%">Action</th>
                           </tr>
                        </tfoot>
                  </table>
                </div>
            </div>        
        </div>
    </div>
</body>

<div class="modal fade" id="edit-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal"><strong>EDIT- Regression Definition</strong></h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="update-form" name="update-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="update">
                 <div class="form-group row">
                      <label for="statistic_code" class="col-sm-4 control-label"><strong>Statistic Code</strong></label>
                      <div class="col-sm-3">
                          <input type="text"  maxlength=15 class="form-control" id="edit_statistic_code" name="statistic_code" value="" placeholder = "statistic code" required="">
                      </div>
                  </div>
                 <div class="form-group row">
                      <label for="statistic_name" class="col-sm-4 control-label"><strong>Statistic Name:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="statistic_name" name="statistic_name" readonly placeholder="Enter Statistic Name/description" value="" required="">
                      </div>
                  </div>

				  <div class="form-group row">
                      <label for="credit_loss_code" class="col-sm-4 control-label"><strong>Credit Loss Code:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="edit_credit_loss_code" name="credit_loss_code" value="" required="">
                      </div>
                  </div>
				  
				  <div class="form-group row">
                      <label for="credit_loss_name" class="col-sm-4 control-label"><strong>Credit Loss Name:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="credit_loss_name" name="credit_loss_name" value="" placeholder="Enter Credit Loss Name" required="">
                      </div>
                  </div>

                  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Expected Correlation Sign:</strong></label>
                      <div class="col-sm-8">
                          <select type="text" class="form-control" id="expected_correlation_sign" name="expected_correlation_sign" value="" placeholder="Selected expected correlaiton sign" required="">
                            <option value = "positive">positive</option>
							<option value = "negative">negative</option>
					      </select>
					  </div>
                  </div> 
                 <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Correlation Parameter-R2 Cut-off:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="R2_cutoff" name="R2_cutoff" placeholder="Enter R2% Cut-off" value="" required=""/>
                      </div>
                 </div>
                 <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Explanatory Comments:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="comment" name="comment" placeholder="Enter explanatory comments" value="" required="">
                      </div>
				 </div>	  
                   <div class="col-sm-offset-2 col-sm-10">
                     <button type="submit" class="btn btn-primary" id="btn-save" value="create">Save changes</button>
                  </div>
              </form>
          </div>
          <div class="modal-footer">
             
          </div>
      </div>
  </div>
</div>

<div class="modal fade" id="add-modal" aria-hidden="true">
  <div class="modal-dialog modal-lg">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal"><strong>ADD - Regression Definition</strong></h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="add-form" name="add-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="add">
                  <div class="form-group row">
                      <label for="statistic_code" class="col-sm-4 control-label"><strong>Statistic Code</strong></label>
                      <div class="col-sm-6">
                          <select type="text"  maxlength=15 class="form-control" id="add_statistic_code" name="statistic_code" value="" placeholder = "statistic code" required="">
                          <?php
                              include '../../assets/includes/connection_db.php';
                              $sql = "SELECT statistic_code FROM macro_statistics";
							  $result = mysqli_query($connect,$sql);
							  while ($option = mysqli_fetch_assoc($result)) 	{
							    echo '<option value ="'.$option['statistic_code'].'">'.$option['statistic_code']."</option>";
							  }
							  mysqli_close($connect);
                          ?>						  
					      </select>
					  </div>
                  </div>
                 <div class="form-group row">
                      <label for="statistic_name" class="col-sm-4 control-label"><strong>Statistic Name:</strong></label>
                      <div class="col-sm-8">
                          <input readonly type="text" class="form-control" id="add_statistic_name" name="statistic_name" placeholder="Enter Statistic Name/description" value="" required="">
                      </div>
                  </div>

				  <div class="form-group row">
                      <label for="credit_loss_code" class="col-sm-4 control-label"><strong>Credit Loss Code:</strong></label>
                      <div class="col-sm-8">
                          <select type="text" class="form-control" id="add_credit_loss_code" name="credit_loss_code" value="" required="">
                          <?php
                              include '../../assets/includes/connection_db.php';
                              $sql = "SELECT credit_loss_code FROM credit_loss_proxy_definitions";
							  $result = mysqli_query($connect,$sql);
							  while ($option = mysqli_fetch_assoc($result)) 	{
							    echo '<option value ="'.$option['credit_loss_code'].'">'.$option['credit_loss_code']."</option>";
							  }
							  mysqli_close($connect);
                          ?>						  
					      </select>
                      </div>
                  </div>
				  
				  <div class="form-group row">
                      <label for="credit_loss_name" class="col-sm-4 control-label"><strong>Credit Loss Name:</strong></label>
                      <div class="col-sm-8">
                          <input readonly type="text" class="form-control" id="add_credit_loss_name" name="credit_loss_name" value="" placeholder="Enter Credit Loss Name" required="">
                      </div>
                  </div>

                  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Expected Correlation Sign:</strong></label>
                      <div class="col-sm-8">
                          <select type="text" class="form-control" id="expected_correlation_sign" name="expected_correlation_sign" value="" placeholder="Selected expected correlaiton sign" required="">
                            <option value = "positive">positive</option>
							<option value = "negative">negative</option>
					      </select>
					  </div>
                  </div> 
                 <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Correlation Parameter-R2 Cut-off:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="R2_cutoff" name="R2_cutoff" placeholder="Enter R2% Cut-off" value="" required=""/>
                      </div>
                 </div>
                 <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Explanatory Comments:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="comment" name="comment" placeholder="Enter explanatory comments" value="" required="">
                      </div>
				 </div>	  
                   <div class="col-sm-offset-2 col-sm-10">
                     <button type="submit" class="btn btn-primary" id="btn-save" value="create">Save changes</button>
                  </div>
              </form>
          </div>
          <div class="modal-footer">
             
          </div>
      </div>
  </div>
</div>

<div class="modal fade" id="import-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal">IMPORT - Regression Definitions</h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form action = "import-add-edit-delete_regression_definitions.php" id="import-form" method="POST" name="import-form" class="form-horizontal" enctype="multipart/form-data">
                 <input type="hidden" class="form-control" id="mode" name="mode" value="import">
                  <div class="form-group">
                      <label for="file" class="col-sm control-label">Select CSV file to import</label>
                      <div class="col-sm-12">
                          <input class=".form-control-file" type="file" class name="csv_file" id="csv_file" accept=".csv" style="margin-top:15px;" />
                      </div>
                  </div>

                  <div class="col-sm-offset-2 col-sm-10">
                   <button class  ="btn btn-primary" 
						   type   ="submit"
						   id     ="btn-import" 
						   value  ="create">Import CSV File    
                   </button>
                  </div>
              </form>
          </div>
          <div class="modal-footer">
             
          </div>
      </div>
  </div>
</div>


<script>
$(document).ready(function(){
	
	$('#regression_definitions_table').DataTable({
		dom: 'Bfrtip',
		buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
	    "processing": true,
        "serverSide": true,
        "order": [],
        "ajax": "fetch_regression_definitions.php"
    });
});


  /*  add risk model */
$('.add-model').click(function () {
    $('#add-modal').modal('show');
});

/*  import risk model */
$('.import-model').click(function () {
    $('#import-modal').modal('show');
});

// add form submit
$('#add-form').submit(function(e){
    e.preventDefault();
    // ajax
    $.ajax({
        url:"import-add-edit-delete_regression_definitions.php",
        method: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        cache: false,
        processData: false,
        dataType: 'json',           // <-- what to expect back from the PHP script, if anything
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
		success: function(result){
			var oTable = $('#regression_definitions_table').dataTable(); 
            oTable.fnDraw(false);
            $('#add-modal').modal('hide');
            $('#add-form').trigger("reset");
 			if (result.ErrorMessage !='') {
			  swal("Not Found",result.ErrorMessage,"error");
			}
			if (result.ErrorMessage =='') {
			  swal("Good Job!",'Regression Definition Inserted',"success");
			}
       }
    });
});  

// import file import via ajax
$('#import-form').submit(function(e){
     e.preventDefault();
    var file_data = $('#csv_file').prop('files')[0];   
    var form_data = new FormData();                  
    form_data.append('file', file_data);
    form_data.append('mode', 'import');
                        
    $.ajax({
        url: "import-add-edit-delete_regression_definitions.php", // <-- point to server-side PHP script 
        dataType: 'json',           // <-- what to expect back from the PHP script, if anything
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,                         
        method: 'POST',
        success: function(php_script_response){
	        var oTable = $('#regression_definitions_table').dataTable(); 
            oTable.fnDraw(false);
            $('#import-modal').modal('hide'); 
            $('#import-form').trigger("reset");
            
			if (php_script_response.ErrorMessage != '') {
			   swal("Import-Failed", php_script_response.ErrorMessage, "error");
            } else {
			   swal("Import-Success", "Total rows imported => "+php_script_response.length, "success");
			}
        }
     });
});


/* edit user function */
$('body').on('click', '.btn-edit', function () {
    var id = $(this).data('id');
     $.ajax({
        url:"import-add-edit-delete_regression_definitions.php",
        type: "POST",
        data: {
            id: id,
            mode: 'edit' 
        },
        dataType : 'json',
        success: function(result){
          $('#id').val(result.id);
          $('#edit_statistic_code'           ).val(result.statistic_code);
          $('#statistic_name'           ).val(result.statistic_name);
          $('#edit_credit_loss_code'         ).val(result.credit_loss_code);
          $('#credit_loss_name'         ).val(result.credit_loss_name);
          $('#expected_correlation_sign').val(result.expected_correlation_sign);
          $('#R2_cutoff'                ).val(result.R2_cutoff);
          $('#comment'                  ).val(result.comment);
          $('#edit-modal').modal('show');
        }
    });
});

// add form submit
$('#update-form').submit(function(e){
    e.preventDefault();
    $.ajax({
        url:"import-add-edit-delete_regression_definitions.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(){
            var oTable = $('#regression_definitions_table').dataTable(); 
            oTable.fnDraw(false);
            $('#edit-modal').modal('hide');
            $('#update-form').trigger("reset");
        }
    });
});  

/* DELETE FUNCTION */
$('body').on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    if (confirm("Are you sure want to delete !")) {
     $.ajax({
        url:"import-add-edit-delete_regression_definitions.php",
        type: "POST",
        data: {
            id: id,
            mode: 'delete' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#regression_definitions_table').dataTable(); 
            oTable.fnDraw(false);
        }
     });
    } 
    return false;
});
/* CLEAR ALL FUNCTION */
$('.clear-model').click(function () {
    var id = $(this).data('id');
    if (confirm("Are you sure want to clear all !")) {
     $.ajax({
        url:"import-add-edit-delete_regression_definitions.php",
        type: "POST",
        data: {
            id: id,
            mode: 'clear' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#regression_definitions_table').dataTable(); 
            oTable.fnDraw(false);
        }
     });
    } 
    return false;
});
/* GET STATISTIC NAME SELECTED */
$('#add_statistic_code').click(function () {
	 $.ajax({
		url:"import-add-edit-delete_regression_definitions.php",
		type: "POST",
		data: {
			statistic_code: this.value,
			mode: 'get_statistic_name' 
		},
		dataType : 'json',
		success: function(result){
			$('#add_statistic_name').val(result);
		}
	 });
    return false;
});
/* GET CREDIT LOSS NAME SELECTED */
$('#add_credit_loss_code').click(function () {
	 $.ajax({
		url:"import-add-edit-delete_regression_definitions.php",
		type: "POST",
		data: {
			credit_loss_code: this.value,
			mode: 'get_credit_loss_name' 
		},
		dataType : 'json',
		success: function(result){
			$('#add_credit_loss_name').val(result);
		}
	 });
    return false;
});
</script>
</html>