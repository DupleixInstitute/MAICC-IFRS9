	<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>PD DATA CALCULATIONS</title>
<!-- DataTables CSS library -->
<script src ='https://kit.fontawesome.com/a076d05399.js' crossorigin='anonymous'></script>
<link href  ="../assets/css/bootstrap/bootstrap.min.css" rel="stylesheet">
<script src ="../assets/js/jquery.min.js"></script>

<script src ="../assets/js/bootstrap/bootstrap.bundle.min.js"></script>
<!-- DataTables JS library -->
<script type="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/sweetalert.min.js"></script>
<!---
<script src="../assets/js/dataTables.buttons.min.js"></script>
<script src="../assets/js/jszip.min.js"></script>
<script src="../assets/js/pdfmake.min.js"></script>
<script src="../assets/js/vfs_fonts.js"></script>
<script src="../assets/js/buttons.html5.min.js"></script>
<script src="../assets/js/buttons.print.min.js"></script>
!-->
<script type="text/javascript" src="../assets/js/chart.bundle.min.js"></script>
<link   href="../assets/css/Dupleix.css" rel="stylesheet" type="text/css">
<link   rel="stylesheet" href="../../assets/font-awesome-4.7.0/css/font-awesome.css"">

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
		th { font-size: 12px; }
        td { font-size: 11px; }

		.modal .modal-body {    
		   overflow-y: auto;
		   max-height: 450px;
		}
    </style>
<?php
 /**
 session_start();
 if (isset($_POST['reporting_year'])) {
    $_SESSION['reporting_year']  = $_POST['reporting_year'];
    $_SESSION['reporting_month'] = $_POST['reporting_month'];
    $reporting_year  = $_SESSION['reporting_year'];
	$reporting_month = $_SESSION['reporting_month'];
                     				
 } else {
    $reporting_year  = $_SESSION['reporting_year'];
	$reporting_month = $_SESSION['reporting_month'];
 }
 if (isset($_POST['start_year'])) {
    $_SESSION['start_year']  = $_POST['start_year'];
    $_SESSION['start_month'] = $_POST['start_month'];
    $start_year  = $_SESSION['start_year'];
	$start_month = $_SESSION['start_month'];
 } else {
    $start_year  = $reporting_year - 1;
	$start_month = $reporting_month != 1 ? $reporting_month - 1 : 12;
 }
echo '<input id    = "_reporting_year" 
			 name  = "_reporting_year"
			 value = "'.$reporting_year.'" hidden >';

echo '<input id    = "_start_year" 
			 name  = "_start_year"
			 value = "'.$start_year.'" hidden >';
**/
?>    


<script>
   var addhtml_header = '<div class="modal fade" id="add-modal" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">';
   var addhtml_footer = '/div';
   var addhtml = '';
</script>

</head>
<body>
    <div class="bs-example" style = "margin-left:0px">
        <div class="container-fluid">
            <div class="row"> 
                <div class="col-md-12">
                    <div class="DivHeader">
                        <h3>RETAIL CREDIT APPLICATIONS</h3>
                        <!--
						<a href="javascript:void(0)" class="btn btn-primary float-right add-model"> Add Record</a>
--->						
				
						<button type="button"    data-bs-toggle="modal"   data-bs-target="#add-modal" data-backdrop="static" 
						        data-keyboard="false" class="btn btn-primary float-right"
								>Add Record
						</button>
						<button type="button"    data-bs-toggle="modal"   data-bs-target="#import-modal" data-backdrop="static" 
						        data-keyboard="false" class="btn btn-light float-right"
								>Import Records
						</button>
						<a href="javascript:void(0)" class="btn btn-light float-right clear-model">Clear All</a>                
                        <button type="button"    data-bs-toggle="modal"   data-bs-target="#update-loan-book-modal" data-backdrop="static" 
						        data-keyboard="false" class="btn btn-light float-right"
								>Update Loan Book
						</button>
                        <button type="button"    data-bs-toggle="modal"   data-bs-target="#formula-modal" data-backdrop="static" 
						        data-keyboard="false" class="btn btn-light float-right"
								><i  class="fas fa-binoculars" style="font-size:18px"></i>Column Formulas</a>
						</button>
						
						<!--
					    <a href="javascript:void(0)" class="btn btn-light float-right import-model">Import Records</a>                
						
							<a href="javascript:void(0)" class="btn btn-light float-right update-loan-book-model">Update Loan Book</a>                
                        <a href="javascript:void(0)" class="btn btn-light float-right formula-model">
						<i  class="fas fa-binoculars" style="font-size:18px"></i>Column Formulas</a>
						-->
	                    <p id = "status_message"></p>
					</div>
                     
                   <table id="pd_table" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th width = "1%" >Entry<br>No</th>
								<th width = "5%" >Omang No</th>
								<th width = "5%" >Loan No</th>
								<th width = "5%" >Loan Category</th>
								<th width = "5%" >Customer Name</th>
								<th width = "5%" >Loan Amount Requested</th>
								<th width = "5%" >Open Market Value</th>
								<th width = "1%" >LTV</th>
								<th width = "5%" >Loan Maturity Requested</th>
								<th width = "5%" >Rate Type</th>
								<th width = "5%" >Interest Rate</th>
								<th width = "5%" >Total Annual Household Revenues</th>
								<th width = "5%" >Affordability Ratio</th>
								<th width = "5%" >Marital<br>Status</th>
								<th width = "5%" >Created</th>
                                <th width = "20 %">Action</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th width = "1%" >Entry<br>No</th>
								<th width = "5%" >Omang No</th>
								<th width = "5%" >Loan No</th>
								<th width = "5%" >Loan Category</th>
								<th width = "5%" >Customer Name</th>
								<th width = "5%" >Loan Amount Requested</th>
								<th width = "5%" >Open Market Value</th>
								<th width = "1%" >LTV</th>
								<th width = "5%" >Loan Maturity Requested</th>
								<th width = "5%" >Rate Type</th>
								<th width = "5%" >Interest Rate</th>
								<th width = "5%" >Total Annual Household Revenues</th>
								<th width = "5%" >Affordability Ratio</th>
								<th width = "5%" >Marital<br>Status</th>
								<th width = "5%" >Created</th>
                                <th width = "20 %">Action</th>

                            </tr>
                           </tfoot>
                  </table>
                </div>
            </div>        
        </div>
    </div>


<div class="modal fade" id="edit-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal"><strong>EDIT- PD Data</strong></h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="update-form" name="update-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="update">
                  <div class="form-group row">
                      <label for="statistic_code" class="col-sm-8 control-label"><strong>Statistic Code</strong></label>
                      <div class="col-sm-4">
                          <input type="text"  maxlength=15 class="form-control" id="add_statistic_code" name="statistic_code" value="" required="">
                      </div>
                  </div>
                  <div class="form-group row">
                      <label for="statistic_name" class="col-sm-2 control-label"><strong>Statistic Name:</strong></label>
                      <div class="col-sm-10">
                          <input type="text" class="form-control" id="statistic_name" name="statistic_name" placeholder="Enter Statistic Name/description" value="" required="">
                      </div>
                  </div>

				  <div class="form-group row">
                      <label for="reporting_year" class="col-sm-4 control-label"><strong>Reporting Year:</strong></label>
                      <div class="col-sm-1">
                          <input type="number" class="form-control" id="reporting_year" name="reporting_year" value="" required="">
                      </div>
 
                      <label for="reporting_month" class="col-sm-2 control-label"><strong>Reporting Month:</strong></label>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="reporting_month" name="reporting_month" value="" required="">
                      </div>
                      <label for="reporting_period" class="col-sm-2 control-label"><strong>Reporting Period:</strong></label>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="reporting_period" name="reporting_period" value="" required="" readonly>
                      </div>
                    </div>
				  
				  <div class="form-group row">
                      <label for="business_unit_long" class="col-sm-3 control-label"><strong>Data Type:</strong></label>
                      <div class="col-sm-3">
                          <select type="text" class="form-control" id="business_unit_long" name="business_unit_long" placeholder="Select Data Type" required="">
						    <option value = "Actual"  >Actual   </option>
						    <option value = "Forecast">Forecast </option>
						  </select>
                      </div>
                      <label for="opening_balance_stage3" class="col-sm-3 control-label"><strong>Periodic Value:</strong></label>
                      <div class="col-sm-3">
                          <input type="number" class="form-control" id="opening_balance_stage3" name="opening_balance_stage3" value="" required="" readonly>
                      </div>                 
				  </div>
                  <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Data Source:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="recovered_amount" name="recovered_amount" placeholder="Enter Data Source" value="" required="">
                      </div>
				 </div>	  
                 <div class="form-group row">
                      <label class="col-sm-4 control-label"><strong>Periodic closing_balance_stage3:</strong></label>
                      <div class="col-sm-8">
                          <input type="text" class="form-control" id="closing_balance_stage3" name="closing_balance_stage3" placeholder="Enter closing_balance_stage3" value="" required=""/>
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



<div class="modal fade" id="add-modal" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-lg">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal"><strong>ADD - PD Data</strong></h4>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="add-form" name="add-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="add">
				  <div class="form-group row">
                      <label for="business_unit_long" class="col-sm-4 control-label"><strong>Business Unit:</strong></label>
                      <div class="col-sm-8">
                          <select type="text" class="form-control" id="business_unit_long" name="business_unit_long" placeholder="Select Data Type" required="">
						    <option value = "CORPORATE BANKING"  >CORPORATE BANKING</option>
						    <option value = "RETAIL BANKING">RETAIL BANKING</option>
						    <option value = "TRANSACTIONAL BANKING">TRANSACTIONAL BANKING</option>
						  </select>
                      </div>
                  </div>
 				  
				  <div class="form-group row">
                      <label for="reporting_year" class="col-sm-4 control-label">  <strong>Reporting Year:</strong></label>
                      <label for="reporting_month" class="col-sm-4 control-label"> <strong>Reporting Month:</strong></label>
                      <label hidden for="reporting_period" class="col-sm-4 control-label"><strong>Reporting Period:</strong></label>				  
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="reporting_year" 
						         name="reporting_year" value="<?php echo $reporting_year;?>" required="">
                      </div>
                      <div class="col-sm-2">
                      </div>
                       <div class="col-sm-2">
                          <input type="number" class="form-control" id="reporting_month" 
						         name="reporting_month" value="<?php echo $reporting_month;?>" required="">
                      </div>
                      <div class="col-sm-2">
                      </div>
                      <div class="col-sm-2">
                          <input hidden type="number" class="form-control" id="reporting_period" name="reporting_period" value="" required="" readonly>
                      </div>
                  </div>
				  <div class="form-group row">
                      <label for="start_year"   class="col-sm-4 control-label"> <strong>Start Year:</strong></label>
                      <label for="start_month"  class="col-sm-4 control-label"> <strong>Start Month:</strong></label>
                      <label hidden for="start_period" class="col-sm-4 control-label"> <strong>Start Period:</strong></label>				  
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="start_year" 
						         name="start_year" value="<?php echo $start_year;?>" required="">
                      </div>
                      <div class="col-sm-2">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="start_month" 
						         name="start_month" value="<?php echo $start_month;?>" required="">
                      </div>
                      <div class="col-sm-2">
                      </div>
                      <div class="col-sm-2">
                          <input hidden type="number" class="form-control" id="start_period" name="start_period" value="" required="" readonly>
                      </div>
                  </div>
				  
				  <hr>
				  
				  <div class="form-group row" style = "background-color:lightgrey">
                      <label class="col-sm-1 control-label"><strong>Start Stage</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>-1</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>1</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>2</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>3</strong></label>
                      <label class="col-sm-3 control-label" align = center><strong>End Stage<BR>Total</strong></label>
                  </div>

                  <!--Stage 1 balances-->

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>1Bal</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_1to0" name="opening_bal_1to0" 
						         placeholder="1to0 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_1to1" name="opening_bal_1to1" 
						         placeholder="1to1 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_1to2" name="opening_bal_1to2" 
						         placeholder="1to2 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_1to3" name="opening_bal_1to3" 
						         placeholder="1to3 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input readonly type="number" class="form-control" id="opening_bal_stage1_total" name="opening_bal_stage1_total" 
						         placeholder="Stage 1 Total" value="" required="" onchange = "update_totals()">
                      </div>
                  </div>

                  <!--Stage 2 balances-->
				  
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>2Bal</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_2to0" name="opening_bal_2to0" 
						  placeholder="2to-1 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_2to1" name="opening_bal_2to1" 
						  placeholder="2to1 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_2to2" name="opening_bal_2to2" 
						  placeholder="2to2 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_2to3" name="opening_bal_2to3" 
						  placeholder="2to3 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input readonly type="number" class="form-control" id="opening_bal_stage2_total" name="opening_bal_stage2_total" 
						  placeholder="Stage 2 Total" value="" required="" onchange = "update_totals()">
                      </div>
                 </div>

                  <!--Stage 3 balances-->

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>3Bal</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_3to0" name="opening_bal_3to0" 
						  placeholder="3to-1 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_3to1" name="opening_bal_3to1" 
						  placeholder="3to1 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_3to2" name="opening_bal_3to2" 
						  placeholder="3to2 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="number" class="form-control" id="opening_bal_3to3" name="opening_bal_3to3" 
						  placeholder="3to3 bal" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input readonly type="number" class="form-control" id="opening_bal_stage3_total" name="opening_bal_stage3_total" 
						         placeholder="Stage 3 Total" value="" required="">
                      </div>
                  </div>
				  				  
				  <div class="form-group row">
                      <div class="col-sm-9">
                         <label class="col-sm-9 control-label" align = right><strong>Total Portfolio</strong></label>
                      </div>
                      <div class="col-sm-3">
                          <input readonly type="number" class="form-control" id="portfolio_total" name="portfolio_total" placeholder="Portfolio Total" value="" required="">
                      </div>
                  </div>

                  <!--TRANSITIONAL MATRIX -->
				  <hr>
				  
				  <div class="form-group row" style = "background-color:lightgrey">
                      <label class="col-sm-1 control-label"><strong>This<BR>Period</strong></label>				  
                      <label class="col-sm-2 control-label"></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>1    <BR>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>2    <BR>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>3    <BR>%</strong></label>
                      <label class="col-sm-3 control-label" align = center><strong>End Stage<BR>TOTAL<BR>%</strong></label>
                  </div>
				  
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = ><strong>1-</strong></label>
                      </div>
                     <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = ><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_1to1" name="PD_1to1" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_1to2" name="PD_1to2" readonly
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_1to3" name="PD_1to3" readonly
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD1_total" name="PD1_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = center><strong>2-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_2to1" name="PD_2to1"     readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_2to2" name="PD_2to2"     readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_2to3" name="PD_2to3"     readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD2_total" name="PD2_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>3-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_3to1" name="PD_3to1"    readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_3to2" name="PD_3to2"    readonly
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_3to3" name="PD_3to3"    readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD3_total" name="PD3_total" value="" required="" total>
                      </div>
					  
					  <!--Hidden Columns for Totals 1 to 3 --> 
                      <input hidden type="text" style = "text-align:right" class="form-control" id="opening_bal_stage1_total_1to3" name="opening_bal_stage1_total_1to3" value="" required="" total>
                      <input hidden type="text" style = "text-align:right" class="form-control" id="opening_bal_stage2_total_1to3" name="opening_bal_stage2_total_1to3" value="" required="" total>
                      <input hidden type="text" style = "text-align:right" class="form-control" id="opening_bal_stage3_total_1to3" name="opening_bal_stage3_total_1to3" value="" required="" total>

                  </div>
				  				  
				  <!--THIS YEAR - ANNUAL AVERAGE--->
				  <hr>
				  
				  <div class="form-group row" style = "background-color:lightgrey">
                      <label class="col-sm-1 control-label"><strong>This<BR>Year<br>Average</strong></label>				  
                      <label class="col-sm-2 control-label"></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>1<br>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>2<br>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>3<br>%</strong></label>
                      <label class="col-sm-3 control-label" align = center><strong>End Stage<BR>TOTAL<br>%</strong></label>
                  </div>
				  
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = ><strong>1-</strong></label>
                      </div>
                     <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = ><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_annual_average_1to1" 
						         name="PD_annual_average_1to1"   
								 value=""       required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_1to2" 
						         name="PD_annual_average_1to2" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id  ="PD_annual_average_1to3" 
						         name="PD_annual_average_1to3" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD1_annual_average_total" 
						         name="PD1_annual_average_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = center><strong>2-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_2to1" name="PD_annual_average_2to1" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_annual_average_2to2" 
						         name="PD_annual_average_2to2" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_2to3" name="PD_annual_average_2to3"  
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD2_annual_average_total" name="PD2_annual_average_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>3-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_3to1" name="PD_annual_average_3to1"    
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_3to2" name="PD_annual_average_3to2"    
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_3to3" name="PD_annual_average_3to3"     
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD3_annual_average_total" name="PD3_annual_average_total" value="" required="">
                      </div>
                  </div>
				  <!--PRIOR YEAR - ANNUAL AVERAGE--->
				  <hr>
				  
				  <div class="form-group row" style = "background-color:lightgrey">
                      <label class="col-sm-1 control-label"><strong>Prior<BR>Year<br>Average</strong></label>				  
                      <label class="col-sm-2 control-label"></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>1<br>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>2<br>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>3<br>%</strong></label>
                      <label class="col-sm-3 control-label" align = center><strong>End Stage<BR>TOTAL<br>%</strong></label>
                  </div>
				  
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = ><strong>1-</strong></label>
                      </div>
                     <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = ><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_annual_average_prior_1to1" 
						         name="PD_annual_average_prior_1to1"    
								 value=""       required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_1to2" 
						         name="PD_annual_average_prior_1to2" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id  ="PD_annual_average_prior_1to3" 
						         name="PD_annual_average_prior_1to3" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD1_annual_average_prior_total" 
						         name="PD1_annual_average_prior_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = center><strong>2-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_2to1" name="PD_annual_average_prior_2to1"     
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_annual_average_prior_2to2" 
						         name="PD_annual_average_prior_2to2"     
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_2to3" name="PD_annual_average_prior_2to3"   
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD2_annual_average_prior_total" name="PD2_annual_average_prior_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>3-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_3to1" name="PD_annual_average_prior_3to1" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_3to2" name="PD_annual_average_prior_3to2"
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_annual_average_prior_3to3" name="PD_annual_average_prior_3to3" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" readonly
						         id="PD3_annual_average_prior_total" name="PD3_annual_average_prior_total" value="" required="">
                      </div>
                  </div>
				  <!--n YEARS - ANNUAL AVERAGE--->
				  <hr>
				  
				  <div class="form-group row" style = "background-color:lightgrey">
                      <label class="col-sm-1 control-label"><strong>n Years<BR>Annual<BR>Average</strong></label>				  
                      <label class="col-sm-2 control-label"></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>1<BR>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>2<BR>%</strong></label>
                      <label class="col-sm-2 control-label" align = center><strong>End Stage<BR>3<BR>%</strong></label>
                      <label class="col-sm-3 control-label" align = center><strong>End Stage<BR>TOTAL<BR>%</strong></label>
                  </div>
				  
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = ><strong>1-</strong></label>
                      </div>
                     <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = ><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_n_years_annual_average_1to1" 
						         name="PD_n_years_annual_average_1to1"   
								 value=""       required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_1to2" 
						         name="PD_n_years_annual_average_1to2" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id  ="PD_n_years_annual_average_1to3" 
						         name="PD_n_years_annual_average_1to3" value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" id="PD1_n_years_annual_average_total" 
						         name="PD1_n_years_annual_average_total" readonly style = "background-color:lightblue"
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>

				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-3 control-label" align = center><strong>2-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_2to1" name="PD_n_years_annual_average_2to1" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" id="PD_n_years_annual_average_2to2" 
						         name="PD_n_years_annual_average_2to2"  
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_2to3" name="PD_n_years_annual_average_2to3" 
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD2_n_years_annual_average_total" name="PD2_n_years_annual_average_total" readonly 
						         value="" required="" onchange = "update_totals()">
                      </div>
                  </div>
				  <div class="form-group row">
                      <div class="col-sm-1">
                         <label class="col-sm-1 control-label" align = center><strong>3-</strong></label>
                      </div>
                      <div class="col-sm-2">
                         <label class="col-sm-2 control-label" align = center><strong>Transition</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_3to1" name="PD_n_years_annual_average_3to1"     
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_3to2" name="PD_n_years_annual_average_3to2"    
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-2">
                          <input type="text" style = "text-align:right" class="form-control" 
						         id="PD_n_years_annual_average_3to3" name="PD_n_years_annual_average_3to3"  
						         value="" required="" onchange = "update_totals()">
                      </div>
                      <div class="col-sm-3">
                          <input type="text" style = "text-align:right" class="form-control" readonly
						         id="PD3_n_years_annual_average_total" name="PD3_n_years_annual_average_total" value="" required="">
                      </div>
                  </div>

                  <hr>
				  
				  <div class="form-group row" >
                      <div class="col-sm-3">
                         <label class="col-sm-3 control-label" align = center><strong>PD life Adj:</strong></label>
                      </div>
                      <div class="col-sm-2">
                          <input type="number" step = "0.01" style = "text-align:left" class="form-control" 
						         id="pd_life_adj" name="pd_life_adj" 
						         required="">
                      </div>
   	                  <div class="col-sm-2">
                         <label class="col-sm-3 control-label" align = center><strong>Comment:</strong></label>
                      </div>
                      <div class="col-sm-5">
                          <input type="text" style = "text-align:left" class="form-control" 
						         id="comment" name="comment" 
						         value="" required="">
                      </div>
   				   </div> 
				   <hr>
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

<div class="modal fade" id="annual_avg-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="annual_averageModalTitle">ANNUAL AVERAGE CALCULATION</h4>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form action = "import-add-edit-delete_pd_data.php" id="annual_average-form" method="POST" name="annual_average-form" class="form-horizontal" enctype="multipart/form-data">
                 <input type="hidden" class="form-control" id="mode" name="mode" value="annual_average">
				 <div class="bs-example" style = "margin-left:0px">
				    <div class="container-fluid">
                       <table>
                         <tr>
                            <td width = "30%">						 
								<table id = "annual_averageTable" class="table table-striped">
									  <thead>
										 <tr>
											<th width = "90%">Reporting Period</th>
											<th width = "10%">Periodic Value</th>
										 </tr>
									  </thead>
									  <tfoot>
										 <tr>
											<th width = "90%">Average</th>
											<th width = "10%"></th>
										 </tr>
									  </tfoot>					
								 </table>
							</td>
							<td width = "60%" rowspan = 14>______________________________________________________
							     <div class = "container-fluid"> 
								     <canvas id="averageChart"></canvas>
							     </div>
							 </td>
						  </tr>	
						 </table>
					 </div>
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
              <h4 class="modal-title" id="userCrudModal_import">IMPORT - PDs</h4>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form action = "import-add-edit-delete_pd_data.php" id="import-form" method="POST" name="import-form" class="form-horizontal" enctype="multipart/form-data">
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

<div class="modal fade" id="update-loan-book-modal" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal_update_loan_book"><strong>LOAN BOOK UPDATE</strong></h4>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form id="update-loan-book-form" name="update-loan-book-form" class="form-horizontal">
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" class="form-control" id="mode" name="mode" value="update-loan-book">
                 <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                     
					 <label for="reporting_year"  class="col-sm-5 control-label"><strong>Reporting Year</strong></label>
                     <label for="reporting_month" class="col-sm-5 control-label"><strong>Reporting Month</strong></label>
                  </div>
                  <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                      
                     <div class="col-sm-3">
                          <input type="number"         class="form-control" id="book_reporting_year" 
						         name="reporting_year" value="<?php echo $reporting_year;?>" required="" onchange = "show_start_and_end_year(this)">
                     </div>
                     <div class="col-sm-2">
                     </div>
                     <div class="col-sm-3">
                          <input type="number"          class="form-control" id="book_reporting_month" 
						         name="reporting_month" value="<?php echo $reporting_month;?>" required="" onchange = "show_start_and_end_month(this)">
                     </div>
                  </div>
                
				<hr class="hr hr-blurry" />
				<!--
				<div class="h-divider">
				  <div class="shadow"></div>
				  <div class="text"><i>something</i></div>
				</div>

				<div class="h-divider">
				  <div class="shadow"></div>
				  <div class="text2"><img src="https://t1.gstatic.com/images?q=tbn:ANd9GcQsmMfybMIwoE5etmOIAuvnFWdfv_8C1Bq15urJFqwhhI55FyYNP2YuUA" /></div>
				</div>
				  
				<div class="h-divider">
				  <div class="shadow"></div>
				</div>

				!-->
				
                <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                      
					 <label for="book_start_year"  class="col-sm-5 control-label"><strong>Start Year</strong></label>
                     <label for="book_start_month" class="col-sm-5 control-label"><strong>Start Month</strong></label>
                  </div>
                  <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                      
                     <div class="col-sm-3">
                          <input type="number"     class="form-control" id="book_start_year" 
						         name="start_year" value="<?php echo $start_year;?>" required="" onchange = "show_start_and_end_year(this)">
                     </div>
                     <div class="col-sm-2">
                     </div>
                     <div class="col-sm-3">
                          <input type="number"      class="form-control" id="book_start_month" 
						         name="start_month" value="<?php echo $start_month;?>" required="" onchange="show_start_and_end_month(this)">
                     </div>
                  </div>
                  <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                      
					 <label for="book_end_year"    class="col-sm-5 control-label"><strong>End Year</strong></label>
                     <label for="book_end_month"   class="col-sm-5 control-label"><strong>End Month</strong></label>
                  </div>
                  <div class="form-group row">
                     <div class="col-sm-1">
                     </div>                      
                     <div class="col-sm-3">
                          <input type="number"  class="form-control" id="book_end_year" name="end_year" 
						         value="<?php echo $reporting_year;?>" required="">
                     </div>
                     <div class="col-sm-2">
                     </div>
                     <div class="col-sm-3">
                          <input type="number"  class="form-control" id="book_end_month" name="end_month" 
						         value="<?php echo $reporting_month;?>" required="">
                     </div>
                  </div>
                  <div class="col-sm-offset-2 col-sm-10">
                     <button type="submit" class="btn btn-primary" id="btn-update-loan-book" value="create">Update</button>
                     <p id = "updating_loan_book_msg"></p>                  
				  </div>
              </form>
          </div>
          <div class="modal-footer">
             
          </div>
      </div>
  </div>
</div>

<div class="modal fade" id="formula-modal" aria-hidden="true">
  <div class="modal-dialog modal-lg">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title" id="userCrudModal_formual-modal">COLUMN FORMULA AUDIT</h4>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
              <form action = "import-add-edit-delete_pd_data.php" id="formula-form" method="POST" name="formula-form" class="form-horizontal" enctype="multipart/form-data">
                 <input type="hidden" class="form-control" id="mode" name="mode" value="formula">
                  <div class="form-group">
                  <table id = "formulaTable" class="table table-striped" style="width:100%">
				  <thead>
				     <tr>
					    <th width = "10%">Column</th>
					    <th width = "10%">Data Type</th>
						<th width = "80%">Table Column Formula</th>
					 </tr>
				  </thead>
				  <tbody>
				  <?php
					include '../assets/includes/connection_db.php';
					$tablename = 'pd_transition_matrices';
					$sql = "SELECT COLUMN_NAME, DATA_TYPE,GENERATION_EXPRESSION  FROM INFORMATION_SCHEMA.COLUMNS 
							WHERE TABLE_SCHEMA = '$db'              AND
                                  LENGTH(GENERATION_EXPRESSION) > 0 AND							
									TABLE_NAME   = '$tablename'";
					$showcolumns= mysqli_query($connect, $sql);
					while($columns = mysqli_fetch_array($showcolumns)) { 
					   $data[] = $columns;
					   echo "<tr>
					            <td>".$columns['COLUMN_NAME']."</td>
					            <td>".$columns['DATA_TYPE']."</td>
					            <td>".$columns['GENERATION_EXPRESSION']."</td>
							 </tr>";
				   }
					mysqli_close($connect);
                  ?>

                  </tbody>				
				  </div>

              </form>
          </div>
          <div class="modal-footer">
             
          </div>
      </div>
  </div>
</div>


<script>
var modalTitle = "";
var x = [];
var y = [];
function show_start_and_end_year(element) {
 
 reporting_year  = parseInt($('#book_reporting_year').val());
 reporting_month = parseInt($('#book_reporting_month').val());
 start_year  = parseInt($('#book_start_year').val());
 start_month = parseInt($('#book_start_month').val());
 end_year  = parseInt($('#book_end_year').val());
 end_month = parseInt($('#book_end_month').val());
 if (element.id == 'book_reporting_year') {
     var book_start_year = parseInt(element.value) - 1;
     $('#book_start_year').val(book_start_year);
     $('#book_end_year').val(element.value);
  }
  if (element.id == 'book_reporting_month') {
	 var book_start_month = parseInt(element.value)!=12 ? parseInt(element.value) + 1:1;
     
	 $('#book_start_month').val(book_start_month);
     $('#book_end_month').val(element.value);
  }
  if (element.id == 'book_start_year') {
     $('#book_end_year').val(parseInt(element.value)+1);
  }
   
}

function update_totals() {
	
   //hold the stage 1 opening balances in short temporary variables
   var sum_1to0 = $('#opening_bal_1to0').val() == "" ? 0:parseFloat($('#opening_bal_1to0').val());
   var sum_1to1 = $('#opening_bal_1to1').val() == "" ? 0:parseFloat($('#opening_bal_1to1').val());
   var sum_1to2 = $('#opening_bal_1to2').val() == "" ? 0:parseFloat($('#opening_bal_1to2').val());
   var sum_1to3 = $('#opening_bal_1to3').val() == "" ? 0:parseFloat($('#opening_bal_1to3').val());
   //add up the stage 1 opening balances and update the total readonly inout control
   var stage1_total = sum_1to0 + sum_1to1 + sum_1to2 + sum_1to3;
   var PD_1to1   = (sum_1to1/(stage1_total - sum_1to0)*100);
   var PD_1to2   = (sum_1to2/(stage1_total - sum_1to0)*100);
   var PD_1to3   = (sum_1to3/(stage1_total - sum_1to0)*100);
   var PD1_Total = (PD_1to1+PD_1to2+PD_1to3).toFixed(2);
   $('#PD_1to1').val(PD_1to1.toFixed(2));
   $('#PD_1to2').val(PD_1to2.toFixed(2));
   $('#PD_1to3').val(PD_1to3.toFixed(2));
   $('#PD1_total').val(PD1_Total);
   $('#opening_bal_stage1_total').val(stage1_total);

   //hold the stage 2 opening balances in short temporary variables
   var sum_2to0 = $('#opening_bal_2to0').val() == "" ? 0:parseFloat($('#opening_bal_2to0').val());
   var sum_2to1 = $('#opening_bal_2to1').val() == "" ? 0:parseFloat($('#opening_bal_2to1').val());
   var sum_2to2 = $('#opening_bal_2to2').val() == "" ? 0:parseFloat($('#opening_bal_2to2').val());
   var sum_2to3 = $('#opening_bal_2to3').val() == "" ? 0:parseFloat($('#opening_bal_2to3').val());
   //add up the stage 2 opening balances and update the total readonly inout control
   var stage2_total = sum_2to0 + sum_2to1 + sum_2to2 + sum_2to3
   var PD_2to1   = (sum_2to1/(stage2_total - sum_2to0)*100);
   var PD_2to2   = (sum_2to2/(stage2_total - sum_2to0)*100);
   var PD_2to3   = (sum_2to3/(stage2_total - sum_2to0)*100);
   var PD2_Total = (PD_2to1+PD_2to2+PD_2to3).toFixed(2);
   $('#PD_2to1').val(PD_2to1.toFixed(2));
   $('#PD_2to2').val(PD_2to2.toFixed(2));
   $('#PD_2to3').val(PD_2to3.toFixed(2));
   $('#PD2_total').val(PD2_Total);
   $('#opening_bal_stage2_total').val(stage2_total);

   //hold the stage 3 opening balances in short temporary variables
   
   //sum of opening balances
   var sum_3to0 = $('#opening_bal_3to0').val() == "" ? 0:parseFloat($('#opening_bal_3to0').val());
   var sum_3to1 = $('#opening_bal_3to1').val() == "" ? 0:parseFloat($('#opening_bal_3to1').val());
   var sum_3to2 = $('#opening_bal_3to2').val() == "" ? 0:parseFloat($('#opening_bal_3to2').val());
   var sum_3to3 = $('#opening_bal_3to3').val() == "" ? 0:parseFloat($('#opening_bal_3to3').val());
   
   
   //add up the stage 3 opening balances and update the total readonly inout control
   var stage3_total = sum_3to0 + sum_3to1 + sum_3to2 + sum_3to3;
   var PD_3to1   = (sum_3to1/(stage3_total - sum_3to0)*100);
   var PD_3to2   = (sum_3to2/(stage3_total - sum_3to0)*100);
   var PD_3to3   = (sum_3to3/(stage3_total - sum_3to0)*100);
   var PD3_Total = (PD_3to1+PD_3to2+PD_3to3).toFixed(2);
   $('#PD_3to1').val(PD_3to1.toFixed(2));
   $('#PD_3to2').val(PD_3to2.toFixed(2));
   $('#PD_3to3').val(PD_3to3.toFixed(2));
   $('#PD3_total').val(PD3_Total);

   //populate the hidden total 1-3 input controls
   $('#opening_bal_stage1_total_1to3').val(stage1_total - sum_1to0);
   $('#opening_bal_stage2_total_1to3').val(stage2_total - sum_2to0);
   $('#opening_bal_stage3_total_1to3').val(stage3_total - sum_3to0);
   
   //************************This Year Annual Average**************************************************************************
   var avg_1to1 = $('#PD_annual_average_1to1').val() == "" ? 0:parseFloat($('#PD_annual_average_1to1').val());
   var avg_1to2 = $('#PD_annual_average_1to2').val() == "" ? 0:parseFloat($('#PD_annual_average_1to2').val());
   var avg_1to3 = $('#PD_annual_average_1to3').val() == "" ? 0:parseFloat($('#PD_annual_average_1to3').val());
   var PD1_annual_average_total = avg_1to1+avg_1to2+avg_1to3;   
  
   var avg_2to1 = $('#PD_annual_average_2to1').val() == "" ? 0:parseFloat($('#PD_annual_average_2to1').val());
   var avg_2to2 = $('#PD_annual_average_2to2').val() == "" ? 0:parseFloat($('#PD_annual_average_2to2').val());
   var avg_2to3 = $('#PD_annual_average_2to3').val() == "" ? 0:parseFloat($('#PD_annual_average_2to3').val());
   var PD2_annual_average_total = avg_2to1+avg_2to2+avg_2to3;

   var avg_3to1 = $('#PD_annual_average_3to1').val() == "" ? 0:parseFloat($('#PD_annual_average_3to1').val());
   var avg_3to2 = $('#PD_annual_average_3to2').val() == "" ? 0:parseFloat($('#PD_annual_average_3to2').val());
   var avg_3to3 = $('#PD_annual_average_3to3').val() == "" ? 0:parseFloat($('#PD_annual_average_3to3').val());
   var PD3_annual_average_total = avg_3to1+avg_3to2+avg_3to3;

 	
   $('#PD1_annual_average_total').val(PD1_annual_average_total.toFixed(2));
   $('#PD2_annual_average_total').val(PD2_annual_average_total.toFixed(2));
   $('#PD3_annual_average_total').val(PD3_annual_average_total.toFixed(2));
   
   //************************Prior Year Annual Average**************************************************************************
   var avg_1to1 = $('#PD_annual_average_prior_1to1').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_1to1').val());
   var avg_1to2 = $('#PD_annual_average_prior_1to2').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_1to2').val());
   var avg_1to3 = $('#PD_annual_average_prior_1to3').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_1to3').val());
   var PD1_annual_average_prior_total = avg_1to1+avg_1to2+avg_1to3;   
  
   var avg_2to1 = $('#PD_annual_average_prior_2to1').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_2to1').val());
   var avg_2to2 = $('#PD_annual_average_prior_2to2').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_2to2').val());
   var avg_2to3 = $('#PD_annual_average_prior_2to3').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_2to3').val());
   var PD2_annual_average_prior_total = avg_2to1+avg_2to2+avg_2to3;

   var avg_3to1 = $('#PD_annual_average_prior_3to1').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_3to1').val());
   var avg_3to2 = $('#PD_annual_average_prior_3to2').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_3to2').val());
   var avg_3to3 = $('#PD_annual_average_prior_3to3').val() == "" ? 0:parseFloat($('#PD_annual_average_prior_3to3').val());
   var PD3_annual_average_prior_total = avg_3to1+avg_3to2+avg_3to3;

 	
   $('#PD1_annual_average_prior_total').val(PD1_annual_average_prior_total.toFixed(2));
   $('#PD2_annual_average_prior_total').val(PD2_annual_average_prior_total.toFixed(2));
   $('#PD3_annual_average_prior_total').val(PD3_annual_average_prior_total.toFixed(2));
   
   //************************sum of PD_n_years_annual_average**************************************************************************
   var n_years_avg_1to1 = $('#PD_n_years_annual_average_1to1').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_1to1').val());
   var n_years_avg_1to2 = $('#PD_n_years_annual_average_1to2').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_1to2').val());
   var n_years_avg_1to3 = $('#PD_n_years_annual_average_1to3').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_1to3').val());
   var PD1_n_years_annual_average_total = n_years_avg_1to1+n_years_avg_1to2+n_years_avg_1to3;   
  
   var n_years_avg_2to1 = $('#PD_n_years_annual_average_2to1').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_2to1').val());
   var n_years_avg_2to2 = $('#PD_n_years_annual_average_2to2').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_2to2').val());
   var n_years_avg_2to3 = $('#PD_n_years_annual_average_2to3').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_2to3').val());
   var PD2_n_years_annual_average_total = n_years_avg_2to1+n_years_avg_2to2+n_years_avg_2to3;

   var n_years_avg_3to1 = $('#PD_n_years_annual_average_3to1').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_3to1').val());
   var n_years_avg_3to2 = $('#PD_n_years_annual_average_3to2').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_3to2').val());
   var n_years_avg_3to3 = $('#PD_n_years_annual_average_3to3').val() == "" ? 0:parseFloat($('#PD_n_years_annual_average_3to3').val());
   var PD3_n_years_annual_average_total = n_years_avg_3to1+n_years_avg_3to2+n_years_avg_3to3;

   var pd_life_adj = (n_years_avg_1to2 + n_years_avg_2to3)/2;    
	
   $('#PD1_n_years_annual_average_total').val(PD1_n_years_annual_average_total.toFixed(2));
   $('#PD2_n_years_annual_average_total').val(PD2_n_years_annual_average_total.toFixed(2));
   $('#PD3_n_years_annual_average_total').val(PD3_n_years_annual_average_total.toFixed(2));
   
   $('#pd_life_adj').val(pd_life_adj.toFixed(2));


  $('#opening_bal_stage3_total').val(stage3_total);
   
   // add up and update the portfolio total
   $('#portfolio_total').val(stage1_total+stage3_total+stage3_total);
   
   //calculate the pd_life_adj as an average of 1to2 and 2to3   
   var pd_life_adj = ((n_years_avg_1to2+n_years_avg_2to3)/2);
   $('#pd_life_adj').val(parseFloat(pd_life_adj));
}

function show_start_year(book_reporting_year) {
  $('#book_start_year').val(parseInt(book_reporting_year)-1);
}

function show_start_and_end_month(element) {	
  if (element.id == 'book_reporting_month') {
     var book_start_month  = parseInt(element.value) - 1;
     if (book_start_month  == 0) {book_start_month = 12;}  
     $('#book_start_month').val(book_start_month);
     $('#book_end_month').val(element.value);
  }
  if (element.id == 'book_start_month') {
	 var book_end_month = parseInt(element.value)!=1 ? parseInt(element.value) - 1:12;
     $('#book_end_month').val(book_end_month);
  }}

$(document).ready(function(){
	lert("Edd");
	swal("testing","","success");
	var numberRenderer = $.fn.dataTable.render.number( ',', '.', 2, '').display;

	$('#pd_table').DataTable({
		dom: 'Bfrtip',
		buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
	    "processing": true,
        "serverSide": true,
        "order": [],
        "ajax": "fetch_credit_applications.php",
        "columnDefs": 
		[
		 {  
            "render": function ( data, type, row, meta ) {
				return '<a href="javascript:void(0)" class="average_annual" data-id= "'+row[0]+'">'+data.toFixed(2)+'%</a>';
			  },
			 "sClass": "accounting",
			 "targets": [8]
          },		 
		 {
            "render": $.fn.dataTable.render.number( ',', '.', 2, '','%' ),"sClass": "accounting",
            "targets": [5,6,11]
          },
		 {  
            "render": function ( data, type, row, meta ) {
				return '<a href="javascript:void(0)" class="average_annual" data-id= "'+row[0]+'">'+data.toFixed(2)+'%</a>';
			  },
			 "sClass": "accounting",
			 "targets": [10]
          },		 
         {
            "render": function ( data, type, row, meta ) {
				return '<a href="javascript:void(0)" class="average_annual" data-id= "'+row[0]+'">'+data.toFixed(0)+'</a>';
			  },
           "targets": [9]
          },
         {
            "render": $.fn.dataTable.render.number( ',', '.', 2, '','%' ),"sClass": "accounting",
            "targets": [10,12]
          }
        ]  
    });
    function commaSeparateNumber(val) {
       while (/(\d+)(\d{3})/.test(val.toString())) {
         val = val.toString().replace(/(\d+)(\d{3})/, '$1' + ',' + '$2');
       }
       return val;
    }
});
  /*  add risk model */
/**
$('.add-model').click(function () {
    $( "body" ).append(addhtml);
	$('#add-modal').html(addhtml);

    $('#add-modal').modal('show');
});
  /*  update loan-book model */
/**
$('.update-loan-book-model').click(function () {
    $('#update-loan-book-modal').modal('show');
});
/*  import risk model */
/**
$('.import-model').click(function () {
    $('#import-modal').modal('show');
});

/*  formula model */
/**
$('.formula-model').click(function () {
    //$('#add-modal').remove();
    $('#formula-modal').modal('show');
});
**/
$('#formula-modal').on('shown.bs.modal', function (e) {
	var dTable = $('#formulaTable').DataTable({
	   dom         : 'Bfrtip',
	   buttons     :['copy', 'csv', 'excel', 'pdf', 'print'],
	});
   
});
$('#formula-modal').on('hidden.bs.modal', function (e) {
   $('#formula-form').trigger("reset");
   //prevent re-initialisation of download table
   var dTable = $('#formulaTable').DataTable();
   dTable.destroy();
   $('#formula-modal').modal('hide'); 
});

// add form submit
$('#add-form').submit(function(e){

    e.preventDefault();

    // ajax
    $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(){
			var oTable = $('#pd_table').dataTable(); 
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
        url: "import-add-edit-delete_pd_data.php", // <-- point to server-side PHP script 
        dataType: 'json',           // <-- what to expect back from the PHP script, if anything
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,                         
        method: 'POST',
        success: function(php_script_response){
	        var oTable = $('#pd_table').dataTable(); 
            oTable.fnDraw(false);
            $('#import-modal').modal('hide'); 
            $('#import-form').trigger("reset");
            
			if (php_script_response.ErrorMessage != '') {
			   swal("Import-Failed", php_script_response.ErrorMessage, "error");
            } else {
			   swal("Import-Success", "Total rows imported => "+php_script_response.imported_records, "success");
			}
        }
     });
});

/* edit user function */
$('body').on('click', '.edit-icon', function () {
    var id = $(this).data('id');
     $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: {
            id: id,
            mode: 'average_annual' 
        },
        dataType : 'json',
        success: function(result){
          /**
		  $('#record_id').val(result.id);
          $('#month_actual1' ).val(result.month_actual1);
          $('#month_actual2' ).val(result.month_actual2);
          $('#month_actual3' ).val(result.month_actual3);
          $('#month_actual4' ).val(result.month_actual4);
          $('#month_actual5' ).val(result.month_actual5);
          $('#month_actual6' ).val(result.month_actual6);
          $('#month_actual7' ).val(result.month_actual7);
          $('#month_actual8' ).val(result.month_actual8);
          $('#month_actual9' ).val(result.month_actual9);
          $('#month_actual10').val(result.month_actual10);
          $('#month_actual11').val(result.month_actual11);
          $('#month_actual12').val(result.month_actual12);
          $('#annual_average').val(result.annual_average);
          **/
          $('#annual_average-modal').modal('show');
		
        }
    });
});

// annnual average modal hidden event
$('#annual_avg-modal').on('hidden.bs.modal', function (e) {
   $('#annual_average-form').trigger("reset");
   //prevent re-initialisation of download table
   var dTable = $('#annual_averageTable').DataTable();
   dTable.destroy();
   $('#annual_avg-modal').modal('hide'); 
});

// annnual average modal shown event
$('#annual_avg-modal').on('shown.bs.modal', function (e) {
   $('#annual_averageModalTitle').text(modalTitle);
});

$('body').on('click', '.average_annual', function (e) {
     
	 var id = $(this).data('id');
	 const colIndex  = e.target.closest('td').cellIndex;  // index 8 is for Average_Annual, 10 for Average_n_years
     
	 //set-up the mode
	 if (colIndex == 8 ) { var mode       = "average_annual";}
	 if (colIndex == 10) { var mode       = "average_annual_n_years";}
	 if (colIndex == 11) { var mode       = "average_annual_n_years_count";}
	 
	 //set up the title
	 if (colIndex == 8 ) { modalTitle = "CALCULATION BREAKDOWN-Annual Average";}
	 if (colIndex == 10) { modalTitle = "CALCULATION BREAKDOWN-Annual Average N Years";}
	 if (colIndex == 11) { modalTitle = "AVAILABLE YEARS LISTING -Annual Average N Years";}
 	
	 var numberRenderer = $.fn.dataTable.render.number( ',', '.', 2, '').display;
     $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: {
            id: id,
            mode: mode 
        },
        dataType : 'json',
        success: function(result){
                    //populate the x and y variables for graphing
					
					x = []; y = [];
					for (let i = 0; i < result.length; i++) {
					  n = result.length - i-1;   //reverse the data sets
					  x.push(result[n]['reporting_period']);
					  y.push(result[n]['value_month_actual']);
					}
					
					//populate an averages data table
					$('#annual_averageTable').DataTable({
						 scrollX: true,
						 
						 //Initialisation to display the pagination control at the top and bottom of page
						 dom: 'lpftrip',
						 
						 //Initialisation to export data table to files
						 dom: 'Bfrtip',
						 buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
						 data  :  result,
						 
						 //FootCall back function to calculate footer totals
						 footerCallback: function( tfoot, data, start, end, display ) {
								var api = this.api();
								//calculate footer totals
								monthlyTotal   = api.column(1).data().reduce(function ( a, b ) {return a + b;}, 0);
								annual_average = monthlyTotal/result.length;
								
								//update footers
								$(api.column(1).footer()).html(numberRenderer(annual_average)+'%');
							},
						//Initialisation of columns	
						 columns :  [
							 { data : "reporting_period" },
							 { data : "value_month_actual"     ,
									render: $.fn.dataTable.render.number( ',', '.', 2, '','%' ),"sClass": "accounting"}
	                             ]
					});    // end of data table parameters					
 
			  //*****************************************************************************************************************************
			  // Display the average chart
			  //******************************************************************************************************************************
			  
			  var Colors   = ['purple','purple','purple','purple'];
			  
			  var ctx = document.getElementById('averageChart').getContext('2d');
			  
			  //Global Options
			  //Chart.defaults.global.defaultFontFamily ='Lato';
			  //Chart.defaults.global.defaultFontSize  = 18;
			 /// Chart.defaults.global.defaultFontColor = 'lightgrey';
			  
			  var chart = new Chart(ctx, {
			  // The type of chart we want to create
			  type: 'line',   //ine pie  bar

			  // The data for our dataset
			  data: {
				  labels: x,
				  datasets: [{
					  label: '',
					  backgroundColor: Colors,
					  borderColor: 'blue',
					  data:y,
					  borderWidth:2,
					  hoverBorderWidth: 3,
					  hoverBorderColor: 'black'
				  }]
			  },
			 // Configuration options go here
			  options: {
				 title:{
					display:true,
					text   :'Average Trend',
					fontSize:18
				 },
				 legend:{
					display  : false,
					position : 'right',
					fontColor: 'black'
				 },
				 layout:{
					padding:{
					  left:10,
					  right:0,
					  bottom:0,
					  top:0
					}
				 },
				 tooltips:{
				   enabled:true
				 }
			  }
			 });
             
          	 $('#annual_avg-modal').modal('show');
		     
        }
    });

});

// update form submit
$('#update-form').submit(function(e){

    e.preventDefault();
       
    // ajax
    $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(){
            var oTable = $('#pd_table').dataTable(); 
            oTable.fnDraw(false);
            $('#edit-modal').modal('hide');
            $('#update-form').trigger("reset");
        }
    });
});  

// update form submit
$('#update-loan-book-form').submit(function(e){    
	var blink = document.getElementById('updating_loan_book_msg');
	blink.style.color = "red";
	blink.innerHTML = "UPDATING-Wait!";
	blinkInterval = setInterval(function () {
		 blink.style.opacity = (blink.style.opacity == 0 ? 1 : 0);
	}, 1000); 
	

	
	e.preventDefault();
       
    // ajax
    $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        success: function(result){
			blink.style.color = "green";
			blink.innerHTML = "";

            var oTable = $('#pd_table').dataTable(); 
            oTable.fnDraw(false);
            $('#edit-modal').modal('hide');
            $('#update-form').trigger("reset");
			swal('Update Complete!',result,'success');
        }
    });
});  


/* DELETE FUNCTION */
$('body').on('click', '.delete-icon', function () {
    var id = $(this).data('id');
    if (confirm("Are you sure want to delete !")) {
     $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: {
            id: id,
            mode: 'delete' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#pd_table').dataTable(); 
            oTable.fnDraw(false);
        }
     });
    } 
    return false;
});

/* CALCULATE FUNCTION*/

$('body').on('click', '.calculator-icon', function () {
    var id = $(this).data('id');
    swal({
	  title: "Proceed to recalculate recovery rates?",
	  text: "A recalculation may produce different results to current!",
	  icon: "warning",
	  buttons: true,
	  dangerMode: true,
	})
	.then((yes) => {
	  if (yes) {
		var blink = document.getElementById('status_message');
		blink.style.color = "red";
        blink.innerHTML = "CALCULATING-Wait!";
        blinkInterval = setInterval(function () {
             blink.style.opacity = 
            (blink.style.opacity == 0 ? 1 : 0);
        }, 1000); 
		 $.ajax({
			url:"import-add-edit-delete_pd_data.php",
			type: "POST",
			data: {
				id: id,
				mode: 'calculator-icon' 
			},
			dataType : 'json',
			success: function(result){
				swal('Recalculation Complete!',result,'success');
		        var oTable = $('#pd_table').dataTable(); 
				oTable.fnDraw(false);
			}
		 });
	  } else {
		swal("Saving aborted!");
	  }
   });

});

/* UPDATE BOOK FUNCTION*/

$('body').on('click', '.book-icon', function () {
    var id = $(this).data('id');
	swal({
	  title: "Proceed to update recovery rates for the portfolio?",
	  text: "An update will overwrite current data!",
	  icon: "warning",
	  buttons: true,
	  dangerMode: true,
	})
	.then((yes) => {
	  if (yes) {
		var blink = document.getElementById('status_message');
		blink.style.color = "red";
        blink.innerHTML = "UPDATING BOOK-Wait!";
        blinkInterval = setInterval(function () {
             blink.style.opacity = 
            (blink.style.opacity == 0 ? 1 : 0);
        }, 1000); 
		 $.ajax({
			url:"import-add-edit-delete_pd_data.php",
			type: "POST",
			data: {
				id: id,
				mode: 'book-icon' 
			},
			dataType : 'json',
			success: function(result){
				swal('Update Complete!',result,'success');
				var oTable = $('#pd_table').dataTable(); 
				oTable.fnDraw(false);
			}
		 });
	  } else {
		swal("Saving aborted!");
	  }
   });   
});


/* CLEAR ALL FUNCTION */
$('.clear-model').click(function () {
    var id = $(this).data('id');
    if (confirm("Are you sure want to clear all !")) {
     $.ajax({
        url:"import-add-edit-delete_pd_data.php",
        type: "POST",
        data: {
            id: id,
            mode: 'clear' 
        },
        dataType : 'json',
        success: function(result){
            var oTable = $('#pd_table').dataTable(); 
            oTable.fnDraw(false);
        }
     });
    } 
    return false;
});

</script>
</body>

</html>