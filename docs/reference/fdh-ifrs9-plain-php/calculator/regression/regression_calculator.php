







































































































































































































































































































<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>REGRESSION ANALYSIS</title>
<!-- DataTables CSS library -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css"/>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

<!-- DataTables JS library -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/sweetalert.min.js"></script>
<script src="../../assets/js/Dupleix.js"></script>
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
                        <h3>REGRESSION ANALYSIS</h3>
                        <a href="javascript:void(0)" class="btn btn-primary float-right add-model"> Add Regression Definition</a>
						<a href="javascript:void(0)" class="btn btn-light float-right import-model">Import Regression Definitions</a>                
						<a href="javascript:void(0)" class="btn btn-light float-right clear-model">Clear All</a>                
                    </div>
                     
                   <table id="regression_results_table" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th width = "5%" >Entry<br>No</th>
                                <th width = "5%" >Statistic<br>Code</th>
								<th width = "5%" >Credit<br>Loss<br>Code</th>
								<th width = "5%">Start<br>Period</th>
								<th width = "5%">End<br>Period</th>
								<th width = "5%">Reporting<br>Period</th>
								<th width = "5%">Expected<br>CORR Sign<br>(Positive/<br>Negative)</th>
								<th width = "10%">CORR<br>Param<br>(R)</th>
								<th width = "10%">CORR<br>Param<br>(R2%)</th>
 								<th width = "5%">R2<br>Cut-off<br>(%)</th>
								<th Width = "10%">R2<br>Cut-off<br>Test</th>
								<th Width = "10%">R<br>Sign<br> Test</th>
							    <th Width = "10%"><br>Slope</th>
								<th Width = "10%"><br>Intercept</th>
								<th width = "10%">Created</th>
                                <th width = "10%">Action</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th width = "5%" >Entry<br>No</th>
                                <th width = "5%" >Statistic<br>Code</th>
								<th width = "5%" >Credit<br>Loss<br>Code</th>
								<th width = "5%" >Start<br>Period</th>
								<th width = "5%" >End<br>Period</th>
								<th width = "5%" >Reporting<br>Period</th>
								<th width = "5%" >Expected<br>CORR Sign<br>(Positive/<br>Negative)</th>
								<th width = "10%">CORR<br>Param<br>(R)</th>
								<th width = "10%">CORR<br>Param<br>(R2%)</th>
 								<th width = "5%" >R2<br>Cut-off<br>(%)</th>
								<th Width = "10%">R2<br>Cut-off<br>Test</th>
								<th Width = "10%">R<br>Sign<br> Test</th>
							    <th Width = "10%"><br>Slope</th>
								<th Width = "10%"><br>Intercept</th>
								<th width = "10%">Created</th>
                                <th width = "10%">Action</th>
                           </tr>
                           </tfoot>
                  </table>
                </div>
            </div>        
        </div>
    </div>
</body>

<div class="modal fade" id="edit-modal" aria-hidden="true">
  <div class="modal-dialolg-lg">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal"><strong>EDIT/COPY AND CREATE NEW- Regression Profile</strong></h4>
          </div>
          <div class="modal-body">
              <form id="update-form" name="update-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="update">
                  <div class="form-group row">
                      <label for="statistic_code" class="col-sm-4 control-label"><strong>Statistic Code(x variable):</strong></label>
                      <div class="col-sm-4">
                          <select type="text"  maxlength=15 class="form-control" id="update-form_statistic_code" name="statistic_code" value="" required="" onchange="get_oldest_static_period(this)">
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
					  <label for="oldest_statistic_period" class="col-sm-2 control-label">Oldest Period:</label>
					  <div class="col-sm-2">
					    <input readonly type="text" class="form-control" id="update-form_oldest_statistic_period" name="oldest_statistic_period" value="" placeholder = "yyyymm" required="">
					  </div>
				  </div>	  
				  <div class="form-group row">
                      <label for="credit_loss_code" class="col-sm-4 control-label"><strong>Credit Loss Code(y variable):</strong></label>
                      <div class="col-sm-4">
                          <select type="text" class="form-control" id="update-form_credit_loss_code" name="credit_loss_code" value="" onchange = "get_oldest_credit_loss_period(this)" required="">
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
					  <label for="oldest_credit_loss_period" class="col-sm-2 control-label">Oldest Period:</label>
				      <div class="col-sm-2">
					    <input readonly type="text" class="form-control" id="update-form_oldest_credit_loss_period" name="oldest_credit_loss_period" value="" placeholder = "yyyymm" required="">
					  </div>
				  </div>	  
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Regression Period:-Requested</strong></label>
                      <label for="start_period_requested" class="col-sm control-label"><strong>Start Period:</strong></label>
                      <div class="col-sm-2">
                         <input type="number" class="form-control" id="update-form_start_period_requested" name="start_period_requested" value="" placeholder = "yyyymm" required="">
                      </div>
                      <label for="end_period_requested" class="col-sm- control-label"><strong>End Period:</strong></label>
                      <div class="col-sm-2">
                         <input type="number" class="form-control" id="update-form_end_period_requested" name="end_period_requested" value="" placeholder = "yyyymm" required="">
                      </div>
                  </div>
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Regression Period:-Available</strong></label>
                      <label for="start_period_available" class="col-sm control-label"><strong>Start Period:</strong></label>
                      <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="update-form_start_period_available" name="start_period_available" value="" placeholder = "yyyymm" required="">
                      </div>
                      <label for="end_period_available" class="col-sm- control-label"><strong>End Period:</strong></label>
                      <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="update-form_end_period_available" name="end_period_available" value="" placeholder = "yyyymm" required="">
                      </div>
                  </div>
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Data Availability Comment:</strong></label>
                       <div class="col-sm-8">
                          <input readonly type="text" class="form-control" id="update-form_data_availability_comment" name="data_availability_comment" value=""  required="">
                      </div>
				  </div>	  
				  <div class="form-group row">
                       <label for="reporting_period" class="col-sm-4 control-label"><strong>Reporting Period:</strong></label>
                       <div class="col-sm-2">
                          <input type="number" class="form-control" id="update-form_reporting_period" name="reporting_period" value="" placeholder="yyyymm" required="">
                      </div>
                       <label for="n" class="col-sm-1 control-label"><strong>Total periods:</strong></label>
                       <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="update-form_total_periods" name="total_periods" value="" required="">
                      </div>
                      <label for="interval(months)" class="col-sm-1 control-label"><strong>Periodic Interval:</strong></label>
                       <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="update-form_periodic_interval" name="periodic_interval" value="" required="">
                      </div>
                  </div>

  
                  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Evaluation Thresholds:</strong></label>
                      <label class="col-sm-2 control-label"><strong>Expected Sign(R):</strong></label>
                      <div class="col-sm-2">
                        <input type="text" class="form-control" id="update-form_expected_correlation_sign" name="expected_correlation_sign" value="" required="">
 					  </div>
                      <label class="col-sm-2 control-label"><strong>R2% Cut-off:</strong></label>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="update-form_R2_cutoff" name="R2_cutoff" value="" required=""/>
                      </div>
                  </div>
 				  <div class="form-group row">
						  <label class="col-sm-1 control-label"><strong>R:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="update-form_correlation_parameter_R" name="correlation_parameter_R" value="" required="">
						  </div>
				          <label class="col-sm-1 control-label"><strong>R2%:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="update-form_correlation_parameter_R2_percent" name="correlation_parameter_R2_percent" value="" required="">
						  </div>
				          <label class="col-sm-1 control-label"><strong>Slope:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="update-form_slope" name="slope" value="" required="">
						  </div>
			              <label class="col-sm-1 control-label"><strong>Intercept:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="update-form_intercept" name="intercept" value="" required="">
						  </div>
                   </div>	  
					 
                   <div class="col-sm-offset-2 col-sm-10">
                     <button type="submit" class="btn btn-primary"   id="update-form_btn-save"    value="create">Save changes</button>
                     <button type="button" class="btn btn-secondary" id="add-form_btn-compute" value="create"
					         onclick = "ComputeRegression(this)">Compute
					 </button>
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
              <h4 class="modal-title" id="userCrudModal"><strong>ADD, CALCULATE AND SAVE - Regression Profile</strong></h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="add-form" name="add-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="add">
                  <div class="form-group row">
                      <label for="statistic_code" class="col-sm-4 control-label"><strong>Statistic Code(x variable):</strong></label>
                      <div class="col-sm-4">
                          <select type="text"  maxlength=15 class="form-control" id="add-form_statistic_code" name="statistic_code" value="" required="" onchange="get_oldest_static_period(this)">
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
					  <label for="oldest_statistic_period" class="col-sm-2 control-label">Oldest Period:</label>
					  <div class="col-sm-2">
					    <input readonly type="text" class="form-control" id="add-form_oldest_statistic_period" name="oldest_statistic_period" value="" placeholder = "yyyymm" required="">
					  </div>
				  </div>	  
				  <div class="form-group row">
                      <label for="credit_loss_code" class="col-sm-4 control-label"><strong>Credit Loss Code(y variable):</strong></label>
                      <div class="col-sm-4">
                          <select type="text" class="form-control" id="add-form_credit_loss_code" name="credit_loss_code" value="" onchange = "get_oldest_credit_loss_period(this)" required="">
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
					  <label for="oldest_credit_loss_period" class="col-sm-2 control-label">Oldest Period:</label>
				      <div class="col-sm-2">
					    <input readonly type="text" class="form-control" id="add-form_oldest_credit_loss_period" name="oldest_credit_loss_period" value="" placeholder = "yyyymm" required="">
					  </div>
				  </div>	  
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Regression Period:-Requested</strong></label>
                      <label for="start_period_requested" class="col-sm control-label"><strong>Start Period:</strong></label>
                      <div class="col-sm-2">
                         <input type="number" class="form-control" id="add-form_start_period_requested" name="start_period_requested" value="" placeholder = "yyyymm" required="">
                      </div>
                      <label for="end_period_requested" class="col-sm- control-label"><strong>End Period:</strong></label>
                      <div class="col-sm-2">
                         <input type="number" class="form-control" id="add-form_end_period_requested" name="end_period_requested" value="" placeholder = "yyyymm" required="">
                      </div>
                  </div>
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Regression Period:-Available</strong></label>
                      <label for="start_period_available" class="col-sm control-label"><strong>Start Period:</strong></label>
                      <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="add-form_start_period_available" name="start_period_available" value="" placeholder = "yyyymm" required="">
                      </div>
                      <label for="end_period_available" class="col-sm- control-label"><strong>End Period:</strong></label>
                      <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="add-form_end_period_available" name="end_period_available" value="" placeholder = "yyyymm" required="">
                      </div>
                  </div>
				  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Data Availability Comment:</strong></label>
                       <div class="col-sm-8">
                          <input readonly type="text" class="form-control" id="add-form_data_availability_comment" name="data_availability_comment" value=""  required="">
                      </div>
				  </div>	  
				  <div class="form-group row">
                       <label for="reporting_period" class="col-sm-4 control-label"><strong>Reporting Period:</strong></label>
                       <div class="col-sm-2">
                          <input type="number" class="form-control" id="add-form_reporting_period" name="reporting_period" value="" placeholder="yyyymm" required="">
                      </div>
                       <label for="n" class="col-sm-1 control-label"><strong>Total periods:</strong></label>
                       <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="add-form_total_periods" name="total_periods" value="" required="">
                      </div>
                      <label for="interval(months)" class="col-sm-1 control-label"><strong>Periodic Interval:</strong></label>
                       <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="add-form_periodic_interval" name="periodic_interval" value="" required="">
                      </div>
                   </div>

                  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Evaluation Thresholds:</strong></label>
                      <label class="col-sm-2 control-label"><strong>Expected Sign(R):</strong></label>
                      <div class="col-sm-2">
                        <input readonly type="text" class="form-control" id="add-form_expected_correlation_sign" name="expected_correlation_sign" value="" required="">
 					  </div>
                      <label class="col-sm-2 control-label"><strong>R2% Cut-off:</strong></label>
                      <div class="col-sm-2">
                          <input readonly type="number" class="form-control" id="add-form_R2_cutoff" name="R2_cutoff" value="" required=""/>
                      </div>
                  </div>
 				  <div class="form-group row">
						  <label class="col-sm-1 control-label"><strong>R:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="add-form_correlation_parameter_R" name="correlation_parameter_R" value="" required="">
						  </div>
				          <label class="col-sm-1 control-label"><strong>R2%:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="add-form_correlation_parameter_R2_percent" name="correlation_parameter_R2_percent" value="" required="">
						  </div>
				          <label class="col-sm-1 control-label"><strong>Slope:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="add-form_slope" name="slope" value="" required="">
						  </div>
			              <label class="col-sm-1 control-label"><strong>Intercept:</strong></label>
						  <div class="col-sm-2">
							  <input readonly type="text" class="form-control" id="add-form_intercept" name="intercept" value="" required="">
						  </div>
                   </div>	  
					 
                   <div class="col-sm-offset-2 col-sm-10">
                     <button type="submit" class="btn btn-primary"   id="add-form_btn-save"      value="create">Save changes</button>
                     <button type="button" class="btn btn-secondary" id="add-form_btn-compute" value="create"
					         onclick = "ComputeRegression(this)">Compute
					 </button>
                   </div>
              </form>
          </div> <!-- Modal Body-->
          <div class="modal-footer">
             
          </div> <!-- Modal Footer -->
      </div> <!-- Modal Content-->
  </div> <!-- Modal Dialog-->
</div>  <!-- Modal -->

<div class="modal fade" id="import-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal">IMPORT - Regression Definitions</h4>
          </div>
          <div class="modal-body">
              <form action = "import-add-edit-delete_regression_results.php" id="import-form" method="POST" name="import-form" class="form-horizontal" enctype="multipart/form-data">
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

function var_dump(obj) {
 var out = '';
 for (var i in obj) {
 out += i + ": " + obj[i] + "\n";
 }
alert(out);
}

function get_oldest_static_period(statistic_code) {
        	
	    var calling_form     = $(statistic_code).closest("form").attr("id");
	    //if called at load of modal
		if (typeof(calling_form)=="undefined"){	
		   calling_form =  statistic_code.id.replace("modal", "form");
		}	
		var id = "#"+calling_form+"_"+"oldest_statistic_period";
		
		$.ajax({
		url:"import-add-edit-delete_regression_results.php",
		type: "POST",
		data: {
			statistic_code: $("#"+calling_form+"_statistic_code").val(),
			mode          : 'get_oldest_static_period' 
		},
		dataType : 'json',
		success: function(result){
 			$(id).val(result);
    		if (result == 'No Data') {
			  $(id).css({"background-color":"red","color":"white"});
			} else {
			  $(id).css({"background-color":"white","color":"black"});
			}
			get_correlation_thresholds(calling_form);
		}
	 });
}

function get_correlation_thresholds(calling_form) {
  $.ajax({
	url:"import-add-edit-delete_regression_results.php",
	type: "POST",
	data: {
		statistic_code  : $("#"+calling_form+"_statistic_code").val(),
		credit_loss_code: $("#"+calling_form+"_credit_loss_code").val(),
		mode            : 'get_correlation_thresholds' 
	},
	dataType : 'json',
	success: function(result){
  		$("#"+calling_form+"_expected_correlation_sign").val(result.expected_correlation_sign);
		$("#"+calling_form+"_R2_cutoff").val(result.R2_cutoff);
		if (result.expected_correlation_sign == 'N/A') {
		  $("#"+calling_form+"_expected_correlation_sign").css({"background-color":"red","color":"white"});
		  $("#"+calling_form+"_R2_cutoff").css({"background-color":"red","color":"white"});
		} else {
		  $("#"+calling_form+"_expected_correlation_sign").css({"background-color":"white","color":"black"});
		  $("#"+calling_form+"_R2_cutoff").css({"background-color":"white","color":"black"});
		  //$(id).css({"background-color":"green","color":"white"});
		}
	}
 });
}
function get_oldest_credit_loss_period(credit_loss_code) {
	 var calling_form     = $(credit_loss_code).closest("form").attr("id");
	 //if called at load of modal
	 if (typeof(calling_form)=="undefined"){	
		calling_form =  credit_loss_code.id.replace("modal", "form");
	 }	
	 var id = "#"+calling_form+"_"+"oldest_credit_loss_period";
	 $.ajax({
		url:"import-add-edit-delete_regression_results.php",
		type: "POST",
		data: {
			credit_loss_code: $("#"+calling_form+"_credit_loss_code").val(),
			mode            : 'get_oldest_credit_loss_period' 
		},
		dataType : 'json',
		success: function(result){
			$(id).val(result);
			if (result == 'No Data') {
			  $(id).css({"background-color":"red","color":"white"});
			} else {
			  $(id).css({"background-color":"white","color":"black"});
			}
			get_correlation_thresholds(calling_form);
		}
	 });
}
$(document).ready(function(){
	
	$('#regression_results_table').DataTable({
		dom: 'Bfrtip',
		buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
	    "processing": true,
        "serverSide": true,
        "order": [],
        "ajax": "fetch_regression_results.php"
    });
});

$("#add-modal").on('shown.bs.modal', function(){
    get_oldest_static_period(this);
	get_oldest_credit_loss_period(this);
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
    $.ajax({
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(){
			var oTable = $('#regression_results_table').dataTable(); 
            oTable.fnDraw(false);
            $('#add-modal').modal('hide');
            $('#add-form').trigger("reset");
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
        url: "import-add-edit-delete_regression_results.php", // <-- point to server-side PHP script 
        dataType: 'json',           // <-- what to expect back from the PHP script, if anything
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,                         
        method: 'POST',
        success: function(php_script_response){
	        var oTable = $('#regression_results_table').dataTable(); 
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
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: {
            id: id,
            mode: 'edit' 
        },
        dataType : 'json',
        success: function(result){
          $('#id').val(result.id);
          $('#update-form_statistic_code'                  ).val(result.statistic_code);
          $('#update-form_credit_loss_code'                ).val(result.credit_loss_code);
          $('#update-form_oldest_statistic_period'         ).val(result.oldest_statistic_period);
          $('#update-form_oldest_credit_loss_period'       ).val(result.oldest_credit_loss_period);
          $('#update-form_start_period_requested'          ).val(result.start_period_requested);
          $('#update-form_end_period_requested'            ).val(result.end_period_requested);
          $('#update-form_start_period_available'          ).val(result.start_period);
          $('#update-form_end_period_available'            ).val(result.end_period);
          $('#update-form_reporting_period'                ).val(result.reporting_period);
          $('#update-form_expected_correlation_sign'       ).val(result.expected_correlation_sign);          
          $('#update-form_correlation_parameter_R'         ).val(result.correlation_parameter_R);          
          $('#update-form_correlation_parameter_R2_percent').val(result.correlation_parameter_R2_percent);          
		  $('#update-form_R2_cutoff'                       ).val(result.R2_cutoff);
		  $('#update-form_slope'                           ).val(result.slope);
		  $('#update-form_intercept'                       ).val(result.intercept);
		  $('#update-form_total_periods'                   ).val(result.total_periods);
		  $('#update-form_periodic_interval'               ).val(result.periodic_interval);
		  $('#update-form_data_availability_comment'       ).val(result.data_availability_comment);
          $('#edit-modal').modal('show');
        }
    });
});

// add form submit
$('#update-form').submit(function(e){

    e.preventDefault();
       
    // ajax
    $.ajax({
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(){
            var oTable = $('#regression_results_table').dataTable(); 
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
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: {
            id: id,
            mode: 'delete' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#regression_results_table').dataTable(); 
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
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: {
            id   : id,
            mode : 'clear' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#regression_results_table').dataTable(); 
            oTable.fnDraw(false);
        }
     });
    } 
    return false;
});


// Regression Calculations
function ComputeRegression (btn_compute) {
	 //abort if start_period is empty
	 var calling_form     = $(btn_compute).closest("form").attr("id");
	 var id_prefix = "#"+calling_form+"_";
 	 if ($(id_prefix+'start_period_requested').val()==''){
		$(id_prefix+'start_period_requested').focus();
		swal('Required Field',"-Start Period","error");
		return false;
	 }
     //abort if end period is empty
	 if ($(id_prefix+'end_period_requested').val()==''){
		$(id_prefix+'end_period_requested').focus();
		swal('Required Field',"-End Period","error");
		return false;
	 }
     //abort if start period is > reporting period
	 if ($(id_prefix+'start_period_requested').val() > $(id_prefix+'reporting_period').val()){
		$(id_prefix+'start_period_requested').focus();
		swal('Invalid Regression Period',"Start Period cannot be later than Reporting Period","error");
		return false;
	 }
     //abort if start period is > end period or reporting period
	 if ($(id_prefix+'start_period_requested').val() > $(id_prefix+'end_period').val()){
		$(id_prefix+'start_period_requested').focus();
		swal('Invalid Regression Period',"Start Period cannot be later than End Period","error");
		return false;
	 }
	 
	 //
	 $.ajax({
        url:"import-add-edit-delete_regression_results.php",
        type: "POST",
        data: {
            statistic_code          :$(id_prefix+'statistic_code').val(),
            credit_loss_code        :$(id_prefix+'credit_loss_code').val(),
			start_period_requested  :$(id_prefix+'start_period_requested').val(),
			end_period_requested    :$(id_prefix+'end_period_requested').val(),
			mode: 'compute' 
        },
        dataType : 'json',
        success: function(result){
           //var_dump(result);
		   //result set has correlation data points and results on the correlation results on the footer
		   var comment = '';
		   var correlation_coefficient_sample = result[result.length-1]['correlation_coefficient_sample'];
		   var slope                          = result[result.length-1]['slope'];
		   var intercept                      = result[result.length-1]['intercept'];
		   var start_period_available         = result[result.length-1]['start_period_available'];
		   var end_period_available           = result[result.length-1]['end_period_available'];
           var total_periods                  = result[result.length-1]['total_periods']; 
           var average_interval               = result[result.length-1]['average_interval']; 
           var theoretical_interval           = result[result.length-1]['theoretical_interval']; 
		   
		   var R2 = round(correlation_coefficient_sample * correlation_coefficient_sample*100,2);
		   $(id_prefix+'correlation_parameter_R').val(correlation_coefficient_sample);
           $(id_prefix+'correlation_parameter_R2_percent').val(R2);		   

           // IF there is no regression profile defined yet, issue a reminder
  		   if ($(id_prefix+"expected_correlation_sign").val()== 'N/A') {
		      comment += '- REGRESSION PROFILE NOT YET DEFINED';
		   // Perform correlation sign and threshold tests according to the regression profiles defined.
		   } else {
			   //Checking the correlation cut-off test
			   if ($(id_prefix+'correlation_parameter_R2_percent').val()>= $(id_prefix+'R2_cutoff').val()) {
				  $(id_prefix+'correlation_parameter_R2_percent').css({"background-color":"green","color":"white"});
				  comment += '- Passed R2 cutoff test';
			   }else {
				  $(id_prefix+'correlation_parameter_R2_percent').css({"background-color":"red","color":"white"});
				  comment += '- (Weak correlation!) - Failed R2 cutoff test';
			   }
			   //checking the correlation sign test where positive correlation is expected
			   if ($(id_prefix+'expected_correlation_sign').val().toUpperCase()== "POSITIVE") {
				  if ($(id_prefix+'correlation_parameter_R')<0) {
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"red","color":"white"});
					  comment += '- Failed expected correlation sign test';
				  } else if ($(id_prefix+'correlation_parameter_R')==0) {
					  comment += '- No correlation exists';
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"orange","color":"white"});
				  } else {
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"green","color":"white"});
					  comment += '- Passed expected correlation sign test';			  
				  }
			   }
			   //checking the correlation sign test where negative correlation is expected		   
			   if ($(id_prefix+'expected_correlation_sign').val().toUpperCase()== "NEGATIVE") {
				  if ($(id_prefix+'correlation_parameter_R')>0) {
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"red","color":"white"});
					  comment += '- Failed expected correlation sign test';
				  } else if ($(id_prefix+'correlation_parameter_R')==0) {
					  comment += '- No correlation exists';
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"orange","color":"white"});
				  } else {
					  $(id_prefix+'correlation_parameter_R').css({"background-color":"green","color":"white"});
					  comment += '- Passed expected correlation sign test';			  
				  }
			   }  // end if for testing negative correlation test evaluation
		   }  //end if for regression profiles definition test
		   
           
		   $(id_prefix+'slope').val(slope);		   
           $(id_prefix+'intercept').val(intercept);		   
           $(id_prefix+'start_period_available').val(start_period_available);		   
           $(id_prefix+'end_period_available').val(end_period_available);		   
           $(id_prefix+'total_periods').val(total_periods);		   
           $(id_prefix+'periodic_interval').val(average_interval);		   
		   $(id_prefix+'data_availability_comment').val(comment);		   
        }
     });
}
</script>
</html>