	<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>CREDIT APPLICATIONS</title>
<!-- DataTables CSS library -->
<script src='https://kit.fontawesome.com/a076d05399.js' crossorigin='anonymous'></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS library -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script type="assets/js/jquery.dataTables.min.js"></script>
<script src="assets/js/sweetalert.min.js"></script>

<script src="assets/js/dataTables.buttons.min.js"></script>
<script src="assets/js/jszip.min.js"></script>
<script src="assets/js/pdfmake.min.js"></script>
<script src="assets/js/vfs_fonts.js"></script>
<script src="assets/js/buttons.html5.min.js"></script>
<script src="assets/js/buttons.print.min.js"></script>
<!-----Added Dupleix.js library ---->
<script type="text/javascript" src="assets/js/Dupleix.js"></script>  
<script type="text/javascript" src="assets/js/chart.bundle.min.js"></script>
<link   href="assets/css/Dupleix.css" rel="stylesheet" type="text/css">
<link   rel="stylesheet" href="assets/font-awesome-4.7.0/css/font-awesome.css"">

    <style type="text/css" media = "print">
		.noPrint{
		  display: none;
		}
		@media print { body{ width:800px; } } 
		.bs-example{
            margin: 0px 0px 10px 10px;
        }
		 .box
		  {
		   max-width:1000px;
		   width:100%;
		   margin: 10px auto;
		   
		  }
		th { font-size: 12px; }
        td { font-size: 11px; }

		.modal .modal-body {    
		   overflow-y: auto;
		   max-height: 450px;
		}
   
	</style>
<?php
 
 session_start();
 $_SESSION['reporting_year'] = 2023;
 $_SESSION['reporting_month']=1;
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
$reporting_year = 2022;
$reporting_month= 1;
/**
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
   var score_results = [];
   var myscores = [];
</script>

</head>
<body>
<div class="bs-example" style = "margin-left:20px;margin-left:10px">
	<div class="container-fluid">
		<div class="modal-header">
			<h4 class="modal-title" id="userCrudModal"><strong>ADD - Mortgage Application</strong></h4>
		   <button type="button" onclick = "functionPrint()">
				Print
			</button>
			<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
			<img src   ="assets/icons/mortgage_loan1.png" alt   ="nknk" id    ="loan_category_icon" 
			   name  ="loan_category_icon" width ="150" height="100"/>

		</div>
		<div class="modal-body">
			<form id="add_mortgage_form" name="add_mortgage_form" class="form-horizontal" 
				  onclick = "ReCalculateForm()" action = "rate.php">
				<input type="hidden" name="id" id="id">
				<input type="hidden" name="username" id="username" value = "<?php echo $_POST['username'];?>">
				<input type="hidden" name="loan_category" id="loan_category" value = "mortgage">
				<input type="hidden" class="form-control" id="mode" name="mode" value="add_mortgage">
	<!-- Addition------------------------------------------------------------------------------------------>
				<div class="form-group row">
					<label for="one" class="col-sm-3 control-label">
						<strong><font color="#000000">Loan ID:</font></strong>
					</label>                 
					<label for="one" class="col-sm-3 control-label">
						<strong><font color="#000000">Omang/Passport:</font></strong>
					</label>                 
					<label for="loan_number" class="col-sm-3 control-label">
						<strong><font color="#000000">Loan Application No</font>:</strong>
					</label>                 
					<label for="cif" class="col-sm-3 control-label"> 
						<strong><font color="#000000">Customer CIF<br></font> 
								<font color="#FF0000">(Existing Customer)</font>
						</strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3">
						<input type="text" class="form-control" id="application_ref" name="application_ref" 
							   placeholder="Loan ID" value="" required onchange = "get_loan_number" disabled>
					</div>
					<div class="col-sm-3">
						<input type="text" class="form-control" id="one" name="one" 
							   placeholder="omang/passport" value="" required onchange = "get_loan_number">
					</div>
					<div class="col-sm-3">
						<SELECT NAME="loan_number" id="loan_number" class="form-select" placeholder="loan number">
							<?php 
							   for ($option = 1;$option <= 32; $option++){
								  echo '<option value="'.$option.'">'.$option.'</option>';
							   }	  
							?>   
						</SELECT>                    
					</div>
					<div class="col-sm-3">
						<input type="text" class="form-control" id="cif" name="cif" 
							   placeholder="CIF" value="" required>
					</div>
				</div> <!-- End form group Passport CIF and Loan Number -->
	<hr>
	<!-------------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">                  
					<label for="loan_type" class="col-sm-3 control-label" >
						<strong><font color="#000000">Loan Type:</font></strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-6">
						<SELECT name="loan_type"     id="loan_type" placeholder = "Loan Type" 
								class="form-select" onchange="displaya(this,'77', '78', '79');">
							<option value=""> </option>
							<option value="Standard to Batswana">Standard to Batswana </option>
							<option value="Guaranteed Scheme">Guaranteed Scheme</option>
							<option value="Non-Guaranteed Scheme">Non-Guaranteed Scheme</option>
							<option value="Standard to Non-Citizens">Standard to Non-Citizens </option>
							<option value="Second Time Home Owner">Second Time Home Owner</option>
							<option value="77">Further Advance</option>
						</SELECT>
					</div>
				</div>
				<div class="form-group row">
					<label for="one" class="col-sm-3 control-label" id="77a" style="display: none;">
						<strong><em>Original Loan Type:</em></strong>
					</label>                 
					<label for="loan_number" class="col-sm-3 control-label" id="78a" style="display: none;"/>
						<strong><em>Principal Outstanding in Original Loan:</em></strong>
					</label>                 
					<label for="cif" class="col-sm-3 control-label" id="79a" style="display: none;"> 
						<strong><em>New FA Requested:</em></strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3" id="77" style="display: none;">
						<SELECT class="form-select" name="o_loan_type" id="o_loan_type" placeholder = "OG Loan Type" >
							<option value="Variable">Variable</option>
							<option value="Fixed">Fixed </option>
						</SELECT>
					</div>
					<div class="col-sm-3" id="78" style="display: none;">
						<input type="text" class="form-control" id="o_bal" name="o_bal" 
							   placeholder="Original Balance" value="0" 
							   required onchange = "return new_loan()">
					</div>
					<div class="col-sm-3" id="79" style="display: none;">
						<input type="text" class="form-control" id="newloan" name="newloan" 
							   placeholder="New Loan" value="0" 
							   required onchange = "return new_loan()"/>
					</div>
				</div> <!-- End form group Loan Type, OG Loan Type, OG Balance and New Loan -->
	<hr>
	<!-------------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for="loan_amount" class="col-sm-4 control-label">
						<strong><font color="#000000">Loan Amount Requested:</font></strong>
					</label>                 
					<label for="one" class="col-sm-4 control-label">
						<strong><font color="#000000">Open Market Value:</font></strong>
					</label>                 
					<label for="cif" class="col-sm-4 control-label"> 
						<strong><font color="#000000">Insurance Replacement Cost</font></strong>
					</label>                 
				</div>

				<div class="form-group row">                  
					<div class="col-sm-4">
						<input type="number" class="form-control" id="loan_amount" name="loan_amount" 
							   placeholder="Loan Amount" value="" 
							   required onchange = "return instal()" onFocus="this.blur"/>
					</div>
					<div class="col-sm-4">
					   <input type="number" class="form-control" id="open_market_value" name="open_market_value" 
							   placeholder="Open Market Value" value="" 
							   required onchange ="loantoval()" onfocus = "loantoval()"/>
					</div>
					<div class="col-sm-4">
						<input type="number" class="form-control" id="insurance_replacement" name="insurance_replacement" 
							   placeholder="Insurance Replacement Cost"
							   required onchange = "premium()" onfocus="premium()"/>
					</div>
				</div> <!-- End form group Loan Amount, Open Market Value, LTV and Insurance Replacement Value -->
	<hr>
	<!-------------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for="ltv_policy" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Loan To Value Policy</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
					<label for="ltv" class="col-sm-6 control-label">
						<strong>
							<font color="#000000">Loan To Value</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3">
						<input type="text" class="form-control w-auto" size = 1 id="ltv_policy" name="ltv_policy" 
							   placeholder="LTV Policy" value="70%" 
							   required onClick="myltvpolicy()" onfocus="loantoval()"/>
					</div>
					<div class="col-sm-3">
					   <input type="text" class="form-control w-auto" size = 4 id="ltv" name="ltv" readonly
							   placeholder="LTV" value="0" 
							   required onClick = "loantoval()" onfocus="loantoval()"/>
					</div>
				</div> <!-- End form group Loan Amount, Open Market Value, LTV and Insurance Replacement Value -->
	<hr>
	<!-------------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for="property_type" class="col-sm-3 control-label">
						<strong><font color="#000000">Property Type:</font></strong>
					</label>                 
					<label for="one" class="col-sm-3 control-label">
						<strong><font color="#000000">Loan Maturity Requested:</font></strong>
					</label>                 
					<label for="loan_number" class="col-sm-3 control-label">
						<strong><font color="#000000">Rate Type Requested:</font></strong>
					</label>                 
					<label for="cif" class="col-sm-3 control-label"> 
						<strong><font color="#000000">Interest Rate pa Offered:</font></strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3">
						<SELECT name="property_type"     id="property_type" placeholder = "Property Type" 
								class="form-select"     onchange="return instal()">
							<option value="Residential" selected>Residential </option>
							<option value="Commercial">Residential</option>
							<option value="Thatched_Residential">Thatched_Residential </option>
							<option value="Thatched_Commercial">Thatched_Commercial</option>
						</SELECT>
					</div>
					<div class="col-sm-3">
						<SELECT name="loan_maturity"     id="loan_maturity" placeholder = "Loan Maturity" 
								class="form-select"     onchange="return instal()" type = "number">
							<?php 
							   for ($option = 1;$option <= 30; $option++){
								  echo '<option value="'.$option.'">'.$option.'</option>';
							   }	  
							?>   
						</SELECT>
					</div>
					<div class="col-sm-3">
						<SELECT name="rate_type"     id="rate_type" placeholder = "Rate Type" 
								class="form-select">
							<option value=""></option>
							<option value="Fixed">Fixed </option>
							<option value="Floating">Floating</option>
							<option value="Variable">Variable</option>						    
						</SELECT>
					</div>
					<div class="col-sm-3">
						<input type="text" class="form-control" id="irate" name="irate" 
							   step = "0.01"  placeholder="Interest Rate pa" 
							   required onchange = "return instal()">
					</div>
				</div> <!-- End form group Property Type, Loan Maturity, Rate Type, and interest rate -->
	<hr>
	<!-------------------------------------------------------------------------------------------------------------->	
				<div class="form-group row">
					<label for="insurance_premium" class="col-sm-4 form-label">
						<strong>
							<font color="#000000">Estimated Insurance Premium</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
					<label for="one" class="col-sm-4 control-label">
						<strong>
							<font color="#000000">Estimated Loan Instalment</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
					<label for="loanandinsurance" class="col-sm-4 control-label">
						<strong>
							<font color="#000000">Estimated Loan Instalment + Insurance premium</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-4">
						<input readonly type="number"            class="form-control" 
							   id           ="insurance_premium" name ="insurance_premium" 
							   placeholder  ="Insurance Premium" 
							   required onfocus = "premium()">
					</div>
				   <div class="col-sm-4">
						<input readonly type="number"            class="form-control" 
							   id           ="loan_installment"  name ="loan_installment" 
							   placeholder  ="Loan Instalment" 
							   onfocus = "this.blur();"/>
					</div>
					<div class="col-sm-4">
						<input readonly type="number"            class="form-control" 
							   id           ="loanandinsurance"  name ="loanandinsurance" 
							   placeholder  ="Loan and Insurance premium" 
							   onfocus = "this.blur();"/>
					</div>
				</div> <!-- End form group Loan Insurance premiums -->
	<hr>
	<!---------------------------PROFESSIONAL REVENUES-------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-6 control-label">
						<strong>
							<font color="#000066">PROFESSIONAL REVENUES (Gross 
												  amounts)<BR>Borrower:
							</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for="annual_sal" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Gross Salary(pa)</font>
						</strong>
					</label>                 
					<label for="fixed_perm_allowances" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Other Income(pa)</font>
						</strong>
					</label>                 
				   <label for="tax" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Tax(pa)</font>
						</strong>
					</label>                 
				   <label for="total_rev_for_afford" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Revenue After Tax(pa)</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3">
						<input type             ="number"                 class="form-control" 
							   id               ="annual_sal"             name ="annual_sal" 
							   placeholder      ="Gross Annual Salary"    step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input type             ="number"                 class="form-control" 
							   id               ="fixed_perm_allowances"  name ="fixed_perm_allowances" 
							   placeholder      ="Other Income(pa)"       step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input type             ="number"                 class="form-control" 
							   id               ="tax"                    name ="tax" 
							   placeholder      ="Tax(pa)"       step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input readonly type    ="number"                    class="form-control" 
							   id               ="total_rev_for_afford"      name ="total_rev_for_afford" 
							   placeholder      ="Net Annual Revenues" 
							   onfocus          = "this.blur();">
					</div>
				</div> <!-- End form group Revenues for affordability-->
	<hr>
	<!---------------------------PROFESSIONAL REVENUES - PARTNER-------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">HouseHold Partner Revenues:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for="hp_annual_sal" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Gross Salary(pa)</font>
						</strong>
					</label>                 
					<label for="hp_fixed_perm_allowances" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Other Income(pa)</font>
						</strong>
					</label>                 
				   <label for="tax2" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Tax(pa)</font>
						</strong>
					</label>                 
				   <label for="hp_total_rev_for_afford" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Revenue After Tax(pa)</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
				</div>
				<div class="form-group row">                  
					<div class="col-sm-3">
						<input type             ="number"                      class="form-control" 
							   id               ="hp_annual_sal"               name ="hp_annual_sal" 
							   placeholder      ="Gross Salary(pa)-Partner"    step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input type             ="number"                      class="form-control" 
							   id               ="hp_fixed_perm_allowances"    name ="hp_fixed_perm_allowances" 
							   placeholder      ="Other Income(pa)-Partner"    step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input type             ="number"                 class="form-control" 
							   id               ="tax2"                   name ="tax2" 
							   placeholder      ="Tax(pa)"                step = "0.01"
							   required onChange="return calcTotal()">
					</div>
					<div class="col-sm-3">
						<input readonly type="number"                    class="form-control" 
							   id           ="hp_total_rev_for_afford"   name ="hp_total_rev_for_afford" 
							   placeholder  ="Net Annual Revenues-HP" 
							   onfocus      ="this.blur();">
					</div>
				</div> <!-- End form group Loan Insurance premiums -->
	<HR>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">Grand Total HouseHold Revenues - Borrower + Partner:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">                  
				   <label for="total_allowable_household_revenues" class="col-sm-3 control-label">
						<strong>
							<font color="#000000">TOTAL Allowable Household Revenues:</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 
				   <label for="rev4afford"           class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Total revenues for affordability:</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>
				   <label for="affordability_policy" class="col-sm-3 control-label">
						<strong>
							<font color="#000000"><br>Affordability Policy:</font>
						</strong>
					</label>
				   <label for="affordability_ratio" class="col-sm-3 control-label">
						<strong>
							<font color="#000000"><br>Affordability Ratio:</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>
				</div>
				<div class="form-group row">                  					
					<div class="col-sm-3">
						<input readonly
							   type         ="number"                      
							   class        ="form-control" 
							   id           ="total_allowable_household_revenues"               
							   name         ="total_allowable_household_revenues" 						           placeholder  ="Total Revenue -Affordability" 
						/>
					</div>
					<div class="col-sm-3">
						<input readonly
							   type         ="number"                      
							   class        ="form-control" 
							   id           ="rev4afford"               
							   name         ="rev4afford" 
						/>
					</div>
					<div class="col-sm-3">
						<SELECT name="affordability_policy" id="affordability_policy" class="form-control">
							<option value="70">70%</option>
						</SELECT>                           
					</div>
					<div class="col-sm-3">
						<input readonly
							   type         ="number"                      
							   class        ="form-control" 
							   id           ="affordability_ratio"               
							   name         ="affordability_ratio" 
						/>
					</div>
				</div><!-- End form group Grand Total Revenues -->
	<hr>
	<!---------------------------LEGAL NAMES-------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">OTHER MONTHLY EXPENSES AND DEDUCTION INSTRUCTION STATUS:</font>							
						</strong>
					</label>
				</div>						
				<div class="form-group row">
					<label for = "rent"                           class="col-sm-6 control-label">
						<strong><font color="#000000">Monthly payment(rental/instalment):</font></strong>
					</label>                 					
					<label for = "deduct"        class="col-sm-6 control-label">
						<strong><font color="#000000">Deduction from source?:</font></strong>
					</label>
				</div>
				<div class="form-group row">
					<div class="col-sm-6">
						<input type      ="text"                          
							   class     ="form-control" 
							   id        ="rent"      
							   name      ="rent"
					   />
					</div>
					<div class="col-sm-3">
						<SELECT name        ="deduct"                id   ="deduct" 
								placeholder ="Deduct At Source"      class="form-select">
							<option selected value="No">No</option>
							<option value="Yes">Yes</option>
						</SELECT>
					</div>
				</div>

	<hr>
	<!---------------------------LEGAL NAMES-------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">PERSONAL INFORMATION-Borrower:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "name" class="col-sm-4 control-label">
						<strong><font color="#000000">Borrower Name:</font></strong>
					</label>                 					
					<label for = "other_name" class="col-sm-4 control-label">
						<strong><font color="#000000">Other Names:</font></strong>
					</label>                 					
					<label for = "surname" class="col-sm-4 control-label">
						<strong><font color="#000000">Surname:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<input type ="text"       class ="form-control" 
							   id   ="name"       name  ="name" 
						/>
					</div>
					<div class="col-sm-4">
						<input type ="text"       class ="form-control" 
							   id   ="other_name" name  ="other_name" 
						/>
					</div>
					<div class="col-sm-4">
						<input type ="text"       class ="form-control" 
							   id   ="surname"    name  ="surname" 
						/>
					</div>
				</div>

	<!------------------------------------------------------------------------------------------------------------------>
	<hr>
	<!---------------------------OTHER PERSONAL DETAILS-------------------------------------------------------------->
				<div class="form-group row">
					<label for = "customer_type" class="col-sm-4 control-label">
						<strong><font color="#000000">Customer Type:</font></strong>
					</label>                 					
					<label for = "marital_status" class="col-sm-4 control-label">
						<strong><font color="#000000">Marital Status:</font></strong>
					</label>                 					
					<label for = "gender" class="col-sm-4 control-label">
						<strong><font color="#000000">Gender:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<SELECT name="customer_type"     id="customer_type" placeholder = "Customer Type" 
								class="form-select">
							<option value="Physical" selected>Physical</option>
							<option value="Legal Entity"     >Legal Entity </option>
						</SELECT>	
					</div>
					<div class="col-sm-4">
						<SELECT name="marital_status"     id="marital_status" placeholder = "Marital Status" 
								class="form-select">
							<option value="Single">Single</option>
							<option value="Married">Married </option>
							<option value="Divorced">Divorced </option>
							<option value="Widowed">Widowed </option>
							<option value="Other">Other</option>
						</SELECT>	
					</div>
					<div class="col-sm-4">
						<SELECT name="gender"     id="gender" placeholder = "gender" 
								class="form-select">
								<option value="M">M</option>
								<option value="F">F</option>
						</SELECT>	
					</div>
				</div>
	<hr>
	<!---------------------------MARITAL DETAILS-------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">MARITAL INFORMATION:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "marital_contract_type"      class="col-sm-4 control-label">
						<strong><font color="#000000">Marital Contract Type:</font></strong>
					</label>                 					
					<label for = "spouse_borrowing_relationship"  class="col-sm-4 control-label">
						<strong><font color="#000000">Spouse Borrowing Relationship:</font></strong>
					</label>                 					
					<label for = "contractual_years_remaining"    class="col-sm-4 control-label">
						<strong><font color="#000000">Household Professional Revenues:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<SELECT name        ="marital_contract_type"            id   ="marital_contract_type" 
								placeholder ="marital contract type"            class="form-select">
								<option value="Community of Property">Community of Property</option>
								<option value="Other marital Contract">Other marital Contract </option>
								<option value="na">na </option>	    
						</SELECT>
					</div>
					<div class="col-sm-4">
						<SELECT name        ="spouse_borrowing_relationship"     id   ="marital_contract_type" 
								placeholder ="spouse_borrowing_relationship"     class="form-select">
								<option value="Co-borrower/Guanrator">Co-borrower/Guarantor</option>
								<option value="Other">Other </option>
								<option value="na">na</option>
						</SELECT>
					</div>
					<div class="col-sm-4">
						<SELECT name        ="revenues"                        id   ="revenues" 
								placeholder ="Household Professional Revenues" class="form-select">
							<option value="Single">Single</option>
							<option value="Double">Double</option>
						</SELECT>	
					</div>
				</div>
	<!------------------------------------------------------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">Legal Names-Spouse:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "spouse_name" class="col-sm-6 control-label">
						<strong><font color="#000000">Spouse,Partner,Co-Borrower Name:</font></strong>
					</label>                 					
					<label for = "spouse_name" class="col-sm-6 control-label">
						<strong><font color="#000000">Spouse Surname:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-6">
						<input type ="text"           class ="form-control" 
							   id   ="spouse_name"    name  ="spouse_name" 
						/>
					</div>
					<div class="col-sm-6">
						<input type ="text"           class ="form-control" 
							   id   ="spouse_surname" name  ="spouse_surname" 
						/>
					</div>
				</div>
	<hr>
	<!---------------------------------SPECIAL MARITAL DATES------------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">Special Marital Dates:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "borrower_birth_date" class="col-sm-3 control-label">
						<strong><font color="#000000">Borrower Birthdate:</font></strong>
					</label>                 					
					<label for = "spouse_birth_date"      class="col-sm-3 control-label">
						<strong><font color="#000000">Spouse BirthDate:</font></strong>
					</label>                 					
					<label for = "wedding_date"  class="col-sm-3 control-label">
						<strong><font color="#000000">Wedding Date:</font></strong>
					</label>                 					
					<label for = "divorce_date"    class="col-sm-3 control-label">
						<strong><font color="#000000">Divorce Date:</font></strong>
					</label>                 					
				</div>
				<!--Special Dates Input Controls-->
				<div class="form-group row">
					<div class="col-sm-3">
						<input type      ="date"                          
							   class     ="form-control" 
							   id        ="borrower_birth_date"      
							   name      ="borrower_birth_date"
						 />
					</div>
					<div class="col-sm-3">
						<input type      ="date"                          
							   class     ="form-control" 
							   id        ="spouse_birth_date"      
							   name      ="spouse_birth_date"
						/>
					</div>
					<div class="col-sm-3">
						 <input type     ="date"                          
							   class     ="form-control" 
							   id        ="wedding_date"      
							   name      ="wedding_date"
						/>
					</div>
					<div class="col-sm-3">
						<input type      ="date"                          
							   class     ="form-control" 
							   id        ="divorce_date"      
							   name      ="divorce_date"
						/>
					</div>
				</div>					
				
				<!------Check boxes  ----->
				<div class="form-group row">
					<div class="col-sm-3">
						<input type      ="checkbox"                          
							   class     ="form-check-input" 
							   id        ="borrower_birth_date_checkbox"      
							   name      ="borrower_birth_date_checkbox"
							   onclick   ="borrower_birth_date.disabled=true"
							   onDblClick="borrower_birth_date.disabled=false"							   
						/>
					</div>
					<div class="col-sm-3">
						<input type      ="checkbox"                          
							   class     ="form-check-input" 
							   id        ="spouse_birth_date_checkbox"      
							   name      ="spouse_birth_date_checkbox"
							   onclick   ="spouse_birth_date.disabled=true"
							   onDblClick="spouse_birth_date.disabled=false"							   
						/>
					</div>
					<div class="col-sm-3">
						 <input type      ="checkbox"                          
							   class     ="form-check-input" 
							   id        ="wedding_date_checkbox"      
							   name      ="wedding_date_checkbox"
							   onclick   ="wedding_date.disabled=true"
							   onDblClick="wedding_date.disabled=false"							   
						/>
					</div>
					<div class="col-sm-3">
						<input type      ="checkbox"                          
							   class     ="form-check-input" 
							   id        ="divorce_date_checkbox"      
							   name      ="divorce_date_checkbox"
							   onclick   ="divorce_date.disabled=true"
							   onDblClick="divorce_date.disabled=false"							   
						/>
					</div>
				</div>					
	<!---------------------------------------------------------------------------------------------------------------->
	<hr>
	<!-----------------------NATIONALITY AND PERMANENT RESIDENCE----------------------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">NATIONALITY AND PERMANENT RESIDENCY:</font>							
						</strong>
					</label>                 					
				</div>
				<br>
				<div class="form-group row">
					<label for = "nationality" class="col-sm-4 control-label">
						<strong><font color="#000000">Nationality:</font></strong>
					</label>                 					
					<label for = "permanent_residence" class="col-sm-4 control-label">
						<strong><font color="#000000">Permanent Country of Residence:</font></strong>
					</label>                 					
					<label for = "perm_res" class="col-sm-4 control-label">
						<strong><font color="#000000">Permanent Residence Permit?:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<SELECT name="nationality"     id="nationality" placeholder = "Nationality" 
								class="form-select">
							<option value="Motswana" selected>Motswana</option>
							<option value="Foreigner">Foreigner</option>
						</SELECT>	
					</div>
					<div class="col-sm-4">
						<SELECT name        ="permanent_residence"          id="permanent_residence" 
								placeholder = "Permanent Residence Country" class="form-select">
							<option value="Botswana">Botswana </option>
							<option value="Other"   >Other    </option>
						</SELECT>	
					</div>
					<div class="col-sm-4">
						<SELECT name="perm_res"     id="perm_res" placeholder = "Permanent Residency?" 
								class="form-select">
								<option value="NA">NA</option>
								<option value="Yes">Yes</option>
								<option value="No">No</option>
						</SELECT>	
					</div>
				</div>


	<hr>
	<!---------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">RESIDENTIAL AND PROPERTY ADDRESSES:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "address" class="col-sm-4 control-label">
						<strong><font color="#000000">Borrower Present Address:</font></strong>
					</label>                 					
					<label for = "years_at_address"               class="col-sm-4 control-label">
						<strong><font color="#000000">Years At Present Address:</font></strong>
					</label>                 					
					<label for = "email" class="col-sm-4 control-label">
						<strong><font color="#000000">Email Address:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<input type ="text"           class ="form-control" 
							   id   ="address"        name  ="address" 
						/>
					</div>
					<div class="col-sm-4">
						<SELECT name        ="years_at_address"            id   ="years_at_address" 
								placeholder ="Years At Address"            class="form-select">
								<option value="0 to 3">0 to 3</option>
								<option value="3 to 5">3 to 5</option>
								<option value="5 to 10">5 to 10</option>
								<option value="+10">+10</option>							
						</SELECT>
					</div>							
					<div class="col-sm-4">
						<input type ="text"           class ="form-control" 
							   id   ="email"          name  ="email" 
						/>
					</div>
				</div>
	<hr>
	<!-------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for = "street_add" class="col-sm-4 control-label">
						<strong><font color="#000000">Street Name and Number:</font></strong>
					</label>                 					
					<label for = "email"      class="col-sm-4 control-label">
						<strong><font color="#000000">Town:</font></strong>
					</label>                 					
					<label for = "country"    class="col-sm-4 control-label">
						<strong><font color="#000000">Country:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<input type ="text"           class ="form-control" 
							   id   ="street_add"     name  ="street_add" 
						/>
					</div>
					<div class="col-sm-4">
						<?php
						  include("assets/includes/database_connection.php");
						  include("assets/includes/list_towns_on_select_tag.php");
						?>
					</div>
					<div class="col-sm-4">
						<input type ="text"           class ="form-control" 
							   id   ="country"        name  ="country" 
						/>
					</div>
				</div>

	<hr>
	<!---------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for = "location" class="col-sm-4 control-label">
						<strong><font color="#000000">Acquired Property Location:</font></strong>
					</label>                 					
					<label for = "bond_plot"  class="col-sm-4 control-label">
						<strong><font color="#000000">Plot No.of bonded security:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-4">
						<SELECT name        ="location"          id="location" 
								placeholder ="Property location" class="form-select">
							<option value ="Urban A"     >Urban A      </option>
							<option value ="Urban B"     >Urban B      </option>
							<option value ="Semi Urban A">Semi Urban A </option>
							<option value ="Semi Urban B">Semi Urban B </option>
							<option value ="Rural"       >Rural        </option>
						</SELECT>	
					</div>
					<div class="col-sm-4">
						<input type ="text"           class ="form-control" 
							   id   ="bond_plot"        name  ="bond_plot" 
						/>
					</div>
				</div>
	<hr>
	<!------------------------------------CHILDREN--------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">CHILDREN AND DEPENDANTS AT HOME:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000066">Total Number of Children at Home:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "to12" class="col-sm-3 control-label">
						<strong><font color="#000000">0to12 years old :</font></strong>
					</label>                 					
					<label for = "to18"      class="col-sm-3 control-label">
						<strong><font color="#000000">13to18 years old:</font></strong>
					</label>                 					
					<label for = "to26"      class="col-sm-3 control-label">
						<strong><font color="#000000">19to26 years old:</font></strong>
					</label>                 					
					<label for = "no_of_children"    class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Total Children:</font>
							<font color="#FF0000">**</font></strong>							
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-3">
						<SELECT name        ="to12"          id="to12" 
								placeholder ="0to12"         class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="to18"          id="to18" 
								placeholder ="13to18"        class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="to26"          id="to26" 
								placeholder ="19to26"        class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<input type      ="text"                          
							   class     ="form-control" 
							   id        ="no_of_children"      
							   name      ="no_of_children"
							   onFocus   ="this.blur();"
							   readonly						   
						/>
					</div>
				</div>
	<hr>
	<!------------------------------------OTHER DEPENDANTS--------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000066">Other dependants Living in Household:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "to12"                      class="col-sm-3 control-label">
						<strong><font color="#000000">GrandParents :</font></strong>
					</label>                 					
					<label for = "aunts_uncles_cousins"      class="col-sm-3 control-label">
						<strong><font color="#000000">Aunts/Uncles/Cousins:</font></strong>
					</label>                 					
					<label for = "other"      class="col-sm-3 control-label">
						<strong><font color="#000000">Other:</font></strong>
					</label>                 					
					<label for = "no_of_children"    class="col-sm-3 control-label">
						<strong>
							<font color="#000000">Total Dependants:</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-3">
						<SELECT name        ="grandparents"          id   ="grandparents" 
								onChange    ="return total_dependents()"
								placeholder ="Grand Parents"         class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="aunts_uncles_cousins"         id   ="aunts_uncles_cousins" 
								onChange    ="return total_dependents()"
								placeholder ="Aunts/Uncles/Cousins"         class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="other"                   id   ="other" 
								onChange    ="return total_dependents()"
								placeholder ="Other dependants"        class="form-select">
							<option value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
							<option value="10">10</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<input type      ="text"                          
							   class     ="form-control" 
							   id        ="dependants_at_home"      
							   name      ="dependants_at_home"
							   onFocus   ="this.blur();"
							   readonly						   
						/>
					</div>
				</div>
	<hr>
	<!-------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">EDUCATION AND EMPLOYMENT INFORMATION:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "borrower_education"             class="col-sm-3 control-label">
						<strong><font color="#000000">Borrower Education:</font></strong>
					</label>                 					
					<label for = "Professional"                   class="col-sm-3 control-label">
						<strong><font color="#000000">Professional Category:</font></strong>
					</label>                 					
					<label for = "employment"                     class="col-sm-3 control-label">
						<strong><font color="#000000">Employment Contract:</font></strong>
					</label>                 					
					<label for = "contractual_years_remaining"    class="col-sm-3 control-label">
						<strong><font color="#000000">Contractual Years Remaining:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-3">
						<SELECT name        ="education"                   id   ="education" 
								placeholder ="Borrower education"          class="form-select">
								<option value="Primary Education">Primary Education</option>
								<option value="Secondary Education">Secondary School</option>
								<option value="Certificate">Certificate</option>
								<option value="Diploma">Diploma</option>
								<option value="Degree">Degree</option>
								<option value="Masters Degree">Masters Degree</option>
								<option value="PHD">PHD</option>
								<option value="Professional Course">Professional Course</option>
								<option value="None">None</option>
						</SELECT>
					</div>							
					<div class="col-sm-3">
						<SELECT name        ="Professional"                id   ="Professional" 
								placeholder ="Professional Category"       class="form-select">
								<option value="Self Employed">Self Employed</option>
								<option value="Civil Servant">Civil Servant</option>
								<option value="Employee">Employee</option>
								<option value="Other">Other</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="employment"                  id   ="employment" 
								placeholder ="Employment Contract"         class="form-select">
								<option value="Permanent">Permanent</option>
								<option value="Contractual">Contractual</option>
								<option value="Other">Other</option>
						</SELECT>	
					</div>
					<div class="col-sm-3">
						<SELECT name        ="contractual_years_remaining" id   ="contractual_years_remaining" 
								placeholder ="Contractual Years Remaining" class="form-select">
								<option value="0 to 1">0 to 1</option>
								<option value="1 to 2">1 to 2</option>
								<option value="2 to 3">2 to 3</option>
								<option value="3 to 5">3 to 5</option>
								<option value="5 to 20">5 to 20</option>
								<option value="20 and more">20 and more</option>
						</SELECT>	
					</div>
				</div>
	<hr>
	<!-------------------------------------------------------------------------------------------------------------->
				<div class="form-group row">
					<label for = "years_at_job"                   class="col-sm-3 control-label">
						<strong><font color="#000000">No Of Years at Present Job:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-3">
						<SELECT name        ="years_at_job"                id   ="years_at_job" 
								placeholder ="Years At Job"                class="form-select">
								<option value="0 to 1">0 to 1</option>
								<option value="1 to 5">1 to 5</option>
								<option value="5 to 20">5 to 20</option>
								<option value="20 and more">20 and more</option>							
						</SELECT>	
					</div>
				</div>
	<!------------------------------------------BANKING RELATIONSHIPS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong>
							<font color="#000066">BANKING RELATIONSHIPS:</font>							
						</strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "mainbank"               class="col-sm-3 control-label">
						<strong><font color="#000000">Main Bank:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="mainbank"                    id   ="mainbank" 
								placeholder ="Main Bank"                   class="form-select">
							<option value="BBS">BBS</option>
							<option value="BSB">BSB</option>
							<option value="NDB">NDB</option>
							<option value="FNBB">FNBB</option>
							<option value="S&C">S&C </option>
							<option value="Barclays">Barclays</option>
							<option value="Stanbic">Stanbic</option>
							<option value="Bank Gaborone">Bank Gaborone</option>
							<option value="Bank ABC">Bank ABC</option>
							<option value="Bank Of Baroda">Bank Of Baroda</option>
							<option value="Capital Bank">Capital Bank</option>
							<option value="State Bank of India">State Bank of India</option>
						</SELECT>
					</div>							
				</div>
				<div class="form-group row">
					<label for = "secondbank"             class="col-sm-3 control-label">
						<strong><font color="#000000">Second Bank:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="secondbank"                    id   ="secondbank" 
								placeholder ="Second Bank"                   class="form-select">
							<option value="NA">Not Applicable</option>
							<option value="BBS">BBS</option>
							<option value="BSB">BSB</option>
							<option value="NDB">NDB</option>
							<option value="FNBB">FNBB</option>
							<option value="S&C">S&C </option>
							<option value="Barclays">Barclays</option>
							<option value="Stanbic">Stanbic</option>
							<option value="Bank Gaborone">Bank Gaborone</option>
							<option value="Bank ABC">Bank ABC</option>
							<option value="Bank Of Baroda">Bank Of Baroda</option>
							<option value="Capital Bank">Capital Bank</option>
							<option value="State Bank of India">State Bank of India</option>
						</SELECT>
					</div>
				</div>
				<div class="form-group row">
					<label for = "thirdbank"              class="col-sm-3 control-label">
						<strong><font color="#000000">Third Bank:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="thirdbank"                    id   ="thirdbank" 
								placeholder ="Third Bank"                   class="form-select">
							<option value="NA">Not Applicable</option>
							<option value="BBS">BBS</option>
							<option value="BSB">BSB</option>
							<option value="NDB">NDB</option>
							<option value="FNBB">FNBB</option>
							<option value="S&C">S&C </option>
							<option value="Barclays">Barclays</option>
							<option value="Stanbic">Stanbic</option>
							<option value="Bank Gaborone">Bank Gaborone</option>
							<option value="Bank ABC">Bank ABC</option>
							<option value="Bank Of Baroda">Bank Of Baroda</option>
							<option value="Capital Bank">Capital Bank</option>
							<option value="State Bank of India">State Bank of India</option>
						</SELECT>
					</div>
				</div>
				<div class="form-group row">
					<label for = "relationship"           class="col-sm-3 control-label">
						<strong><font color="#000000">Age Of Relationshp with BBS:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="relationship"                    id   ="relationship" 
								placeholder ="BBS Relationship Length"         class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>
						</SELECT>	
					</div>
				</div>
	<!------------------------------------------TOTAL BBS PRODUCTS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#00066">Total BBS Products:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "Savings"             class="col-sm-3 control-label">
						<strong><font color="#000000">Savings Account:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="Savings"                     id   ="Savings" 
								placeholder ="No of Saving A/cs"           class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>						    
						</SELECT>
					</div>							
				</div>							
				<div class="form-group row">
					<label for = "Deposit"             class="col-sm-3 control-label">
						<strong><font color="#000000">Deposit Account:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="Deposit"                     id   ="Deposit" 
								placeholder ="No of Deposit A/cs"          class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>						    
						</SELECT>
					</div>
				</div>							
				<div class="form-group row">
					<label for = "Share"               class="col-sm-3 control-label">
						<strong><font color="#000000">Share Account:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="Share"                       id   ="Share" 
								placeholder ="No of Share A/cs"            class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>						    
						</SELECT>
					</div>
				</div>							
				<div class="form-group row">
					<label for = "Mortgages"           class="col-sm-3 control-label">
						<strong><font color="#000000">Mortgages:</font></strong>
					</label>
					<div class="col-sm-4">
						<SELECT name        ="Mortgages"                   id   ="Mortgages" 
								placeholder ="No of Mortgage Loans"        class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>						    
						</SELECT>
					</div>
				</div>						
				<div class="form-group row">
					<label for = "ST"                  class="col-sm-3 control-label">
						<strong><font color="#000000">ST Loans:</font></strong>
					</label>                 					
					<div class="col-sm-4">
						<SELECT name        ="ST"                          id   ="ST" 
								placeholder ="No of ST Loans"              class="form-select">
							<option value="NA">NA</option>
							<option value="Since less than 1 year">Since less than 1 year</option>
							<option value="Since 1 to 5 years">Since 1 to 5 years</option>
							<option value="More than 5 years">More than 5 years</option>						    
						</SELECT>
					</div>
				</div>							
				<div class="form-group row">
					<label for = "total_bbs_products"  class="col-sm-3 control-label">
						<strong>
							<font color="#000066">TOTAL BBS Products:</font>
							<font color="#FF0000">**</font>
						</strong>
					</label>                 					
					<div class="col-sm-4">
						<input type      ="number"                          
							   class     ="form-control" 
							   id        ="total_bbs_products"      
							   name      ="total_bbs_products"
							   onclick   = "displ()"
							   value     = "0"
							   readonly						   
						/>
					</div>
				</div>

	<!------------------------------------------LOAN ARREARS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000000">LOAN ARREARS HISTORY:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "loan_arrears"        class="col-sm-6 control-label">
						<strong><font color="#000000">BBS arrears for over 30days in last 12mnths?</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="loan_arrears"                 id   ="loan_arrears" 
								placeholder ="Loan Arrears History"         class="form-select">
							<option selected value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
						</SELECT>
					</div>
				</div>						
	<!------------------------------------------RENOGOTIATED LOANS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000000">Renegotiated Loans History:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "renegotiate"        class="col-sm-6 control-label">
						<strong><font color="#000000">Renegotiated loans with arreas in past 24 months?:</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="renegotiate"                 id   ="renegotiate" 
								placeholder ="Renegotiated Loans History"  class="form-select">
							<option value=""></option>
							<option selected value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>							
						</SELECT>
					</div>
				</div>						
				<div class="form-group row">
					<label for = "why_renogotiation"        class="col-sm-6 control-label">
						<strong><font color="#000000">Why was this needed ?:</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="why_renogotiation"           id   ="why_renogotiation" 
								placeholder ="Renegotiation Reason"        class="form-select">
							<option value="0">Unexpected expenses</option>
							<option value="Increased instalments">Increased instalments</option>
							<option value="Reduced revenues">Reduced revenues</option>
							<option value="other">other</option>
							<option selected value="N/A">N/A</option>						    
						</SELECT>
					</div>
				</div>						
	<!------------------------------------------CREDIT CARDS HISTORY--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000066">Credit Cards Held:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "renegotiate"        class="col-sm-6 control-label">
						<strong><font color="#000000">No of credit card personally held:</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="cards_held"                  id   ="cards_held" 
								placeholder ="No of cards held"            class="form-select">
							<option value=""></option>
							<option selected value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>						    
						</SELECT>
					</div>
				</div>						
				<div class="form-group row">
					<label for = "card_held_since"        class="col-sm-6 control-label">
						<strong><font color="#000000">Card held since (in years):</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="card_held_since"             id   ="card_held_since" 
								placeholder ="card_held duration"         class="form-select">
							<option value=""></option>
							<option selected value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4 and above</option>
						</SELECT>
					</div>
				</div>						
	<!------------------------------------------NO OF OUTSTANDING LOANS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label for = "renegotiate"        class="col-sm-6 control-label">
						<strong><font color="#000066">Number of loans presently outstanding:</font></strong>
					</label>
					<div class="col-sm-3">
						<SELECT name        ="loans_outstanding"           id   ="loans_outstanding" 
								onchange    ="display(this,'1','2','3','4','5','6','7','8','9');"
								placeholder ="loans_outstanding"           class="form-select">
							<option selected value="0">0</option>
							<option value="1">1</option>
							<option value="2">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
							<option value="5">5</option>
							<option value="6">6</option>
							<option value="7">7</option>
							<option value="8">8</option>
							<option value="9">9</option>
						</SELECT>
					</div>
				</div>						
	<!------------------------------------------------------------------------------------------------------------------->					
	<hr>					
				<Table>
					<tbody id="1" style="display: none;">
						<tr>
							<td class="title">loan installment:</td>
							<td class="field"><input type="text" name="installment1" size="8" maxlength="7" /></td>
						</tr>
					</tbody>
					<tbody id="2" style="display: none;">
					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field"><input type="text" name="ainstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field"><input type="text" name="ainstallment2" size="8" /></td>
					</tr>

					</tbody>

					<tbody id="3" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment :</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment :</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment3" size="8" maxlength="7" /></td>
					</tr>
					</tbody>
					<tbody id="4" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment4" size="8" maxlength="7" /></td>
					</tr>
					</tbody>

					<tbody id="5" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment5" size="8" maxlength="7" /></td>
					</tr>
					</tbody>

					<tbody id="6" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment5" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 6 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="einstallment6" size="8" maxlength="7" /></td>
					</tr>
					</tbody>


					<tbody id="5" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="dinstallment5" size="8" maxlength="7" /></td>
					</tr>
					</tbody>

					<tbody id="7" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment5" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 6 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment6" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 7 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="finstallment7" size="8" maxlength="7" /></td>
					</tr>
					</tbody>
					<tbody id="8" style="display: none;">

					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment5" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 6 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment6" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 7 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment7" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 8 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="ginstallment8" size="8" maxlength="7" /></td>
					</tr>
					</tbody>

					<tbody id="9" style="display: none;">
					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment5" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 6 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment6" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 7 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment7" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 8 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment8" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 9 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="hinstallment9" size="8" maxlength="7" /></td>
					</tr>
					</tbody>

					<tbody id="10" style="display: none;">
					<tr>
					<td class="title">loan 1 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment1" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 2 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment2" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 3 installment</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment3" size="8" /></td>
					</tr>
					<tr>
					<td class="title">loan 4 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment4" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 5 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment5" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 6 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment6" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 7 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment7" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 8 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment8" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 9 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment9" size="8" maxlength="7" /></td>
					</tr>
					<tr>
					<td class="title">loan 10 installment:</td>
					<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="iinstallment10" size="8" maxlength="7" /></td>
					</tr>
					</tbody>
				</table>
	<!------------------------------------------ITC REPORTS--------------------------------------------------------------------------->
	<hr>
				<div class="form-group row">
					<label class="col-sm-12 control-label">
						<strong><font color="#000066">ITC REPORTS:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<label for = "itc_ref"             class="col-sm-2 control-label">
						<strong><font color="#000000">ITC REF NO:</font></strong>
					</label>                 					
					<div class="col-sm-6">
					   <input  type      ="number"                          
							   class     ="form-control" 
							   id        ="itc_ref"      
							   name      ="itc_ref"
							   value     ="0"
							   readonly						   
						/>
					</div>							
				</div>
				<div class="form-group row">
					<label for = "paid_debts"          class="col-sm-2 control-label">
						<strong><font color="#000000">Paid Debt:</font></strong>
					</label>                 					
					<label for = "Share"               class="col-sm-2 control-label">
						<strong><font color="#000000">Judgement:</font></strong>
					</label>                 					
					<label for = "default_data"        class="col-sm-2 control-label">
						<strong><font color="#000000">Default Data:</font></strong>
					</label>                 					
					<label for = "trace_alerts"        class="col-sm-2 control-label">
						<strong><font color="#000000">Trace Alerts:</font></strong>
					</label>                 					
					<label for = "blacklisted"        class="col-sm-2 control-label">
						<strong><font color="#000000">Blacklisted?:</font></strong>
					</label>                 					
					<label for = "fraud_alert"        class="col-sm-2 control-label">
						<strong><font color="#000000">Fraud Alert?:</font></strong>
					</label>                 					
				</div>
				<div class="form-group row">
					<div class="col-sm-2">
						<SELECT name        ="paid_debts"                     id   ="paid_debts" 
								placeholder ="Paid Debts"                     class="form-select">
								<option value="0">0</option>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="5">5</option>
								<option value="6">6</option>
								<option value="7">7</option>
								<option value="8">8</option>							
						</SELECT>
					</div>
					<div class="col-sm-2">
						<SELECT name        ="judgement"                   id   ="judgement" 
								placeholder ="Judgement"                   class="form-select">
								<option value="0">0</option>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="5">5</option>
								<option value="6">6</option>
								<option value="7">7</option>
								<option value="8">8</option>							
						</SELECT>
					</div>
					<div class="col-sm-2">
						<SELECT name        ="default_data"                id   ="default_data" 
								placeholder ="Default Data"                class="form-select">
								<option value="0">0</option>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="5">5</option>
								<option value="6">6</option>
								<option value="7">7</option>
								<option value="8">8</option>							
						</SELECT>
					</div>
					<div class="col-sm-2">
						<SELECT name        ="trace_alerts"                id   ="trace_alerts" 
								placeholder ="trace_alerts"                class="form-select">
								<option value="0">0</option>
								<option value="1">1</option>
								<option value="2">2</option>
								<option value="3">3</option>
								<option value="4">4</option>
								<option value="5">5</option>
								<option value="6">6</option>
								<option value="7">7</option>
								<option value="8">8</option>							
						</SELECT>
					</div>
					<div class="col-sm-2">
						<SELECT name        ="blacklisted"                 id   ="blacklisted" 
								placeholder ="Black Listed?"               class="form-select">
								<option value="No" >No</option>
								<option value="Yes">Yes</option>
						</SELECT>
					</div>
					<div class="col-sm-2">
						<SELECT name        ="fraud_alert"                 id   ="fraud_alert" 
								placeholder ="Fraud Alert?"                class="form-select">
								<option value="No" >No</option>
								<option value="Yes">Yes</option>
						</SELECT>
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
    
<script>
 
function functionPrint() {
	//$('#add-modal').css('height', '');
	document.body.style.overflow = "hidden";
	//document.body.style.height = null;//css('height', 'auto');
	document.getElementById("nonPrintable").className += "noPrint";
	window.print();
	document.body.style.overflow = "auto";
}
function printDiv(elem){
      var mywindow = window.open();
      var content = document.getElementById(elem).innerHTML;
      var realContent = document.body.innerHTML;
      mywindow.document.write(content);
      mywindow.document.close(); // necessary for IE >= 10
      mywindow.focus(); // necessary for IE >= 10*/
      mywindow.print();
      document.body.innerHTML = realContent;
      mywindow.close();
      return true;
}
var modalTitle = "";

function update_totals() {
	
 }


$(document).ready(function(){
	load_random();
	ReCalculateForm();
});


// add form submit
$('#add_mortgage_form').submit(function(e){
    e.preventDefault();

    // ajax
    $.ajax({
        url:"credit_applications/import-add-edit-delete_credit_applications.php",
        type: "POST",
        data: $(this).serialize(), // get all form field value in serialize form
        dataType: 'json',           // <-- what to expect back from the PHP script, if anything
        success: function(message){
			swal("Mortgage Loan Application Submission",message.text,message.errorStatus);
			//var oTable = $('#credit_application_table').dataTable(); 
            //oTable.fnDraw(false);
            //$('#add-modal').modal('hide');
           // $('#add_mortgage_form').trigger("reset");
        }
    });
});  



</script>
</body>

</html>