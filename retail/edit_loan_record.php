
<HTML>
<HEAD>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
	<TITLE>CREDIT SCORING-Edit Loan Record</TITLE>
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
	<link   rel="stylesheet" href="assets/font-awesome-4.7.0/css/font-awesome.css">
	<link href="btu.css" rel="stylesheet" type="text/css">

    <style type="text/css" media = "print">
		.noPrint{
		  display: none;
		}
		@media print { body{ width:800px; } } 
		.bs-example{
            margin: 0px 0px 0px 0px;
        }
		 .box
		  {
		   max-width:1000px;
		   width:100%;
		   margin: 0 auto;
		   
		  }
		th { font-size: 10px; }
        td { font-size: 9px; }

		.modal .modal-body {    
		   overflow-y: auto;
		   max-height: 450px;
		}
	</style>

</HEAD>

<BODY BGCOLOR=#FFFFFF >
<script>
	function checkRecord() {
        
		if ($("#omang").val() == "" || $("#loan_number").val() == "") { 
		    swal('INCOMPLETE ENTRY','Please enter omang and loan number','error');
		} else {
			$.ajax({
				url:"ajaxScripts/get_customer_record.php",
				type: "POST",
				data: {
					omang: $('#omang').val(),
					loan_number: $('#loan_number').val() 
				},
				dataType : 'json',
				success: function(result){
					if (result.errorStatus == 'Error') {
						//alert (result.errorText);
						swal ('',result.errorText,'error'); 
						$("#edit_loan_record").attr('action', '');			
					} else {
						if (result.data.loan_category == 'mortgage') {
								//alert(document.getElementById('edit_loan_record').action);
							$("#edit_loan_record").attr('action', 'score_mortgage_loan_original.php');		 
								//swal (result.data.borrower_name,result.data.loan_category,'success');
								//form.action = 'viewform_mortgage.php';
						} else {
							$("#edit_loan_record").attr('action', 'score_unsecured_loan_original.php');
						}
						document.getElementById('edit_loan_record').submit();								
					}	   
				}
			});
		}	
	}
</script>
<?php
	include ("assets/includes/authenticate.php");        // Connect to server and select databse.
	include ("assets/includes/database_connection.php"); //Open database connection;
?>
<font class="s_text">
	<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" bgcolor="#ECF8FF">
		<tr>
			<td colspan="6" class="s_text">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" border = "10">
					<tr>
						<td width="1286">
							<table width="770" height="165">
								<td><img src="../assets/images/corporate_logo1.jpg" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
								<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="../assets/images/corporate_logo2.png" alt="nknk" name="g" width="385" height="208"/></font></td>
							</table>
						</td>
					</tr>
					<tr>
						<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING</font></td>
					</tr>
					<tr>
						<table class="s_text" bgcolor = "yellow">
							<tr bgcolor="#ECF8FF" class="s_text"> 
								<td>
									<p></br> 
									<form ACTION="" name="edit_loan_record" id="edit_loan_record" method="post">
										<td>
											<table>
												<tr>
													<td><p align="justify" class="s_text"><br>
														<table class="s_text">
															<tr>
																<td>
																	<strong><em><font color="#FF9900" size="1">&nbsp;&nbsp;&nbsp;&nbsp;Enter customer's Omang/passport number below</font></em>
																</td>
															</tr>
															<tr><td>&nbsp;&nbsp;&nbsp;Omang/Passport Number :&nbsp;&nbsp;
																	<input name="omang_no_to_edit" id = "omang" type="text" required >
																</td>
																<td>&nbsp;&nbsp;&nbsp;Loan Number</td>
																<td>
																	<SELECT name="loan_no_to_edit" id = "loan_number" required>
																		<option value=""> </option>
																		<option value="1">1 </option>
																		<option value="2">2</option>
																		<option value="3">3</option>
																		<option value="4">4</option>
																		<option value="5">5</option>
																		<option value="6">6</option>
																		<option value="7">7</option>
																		<option value="8">8</option>
																		<option value="9">9</option>
																		<option value="10">10</option>
																		<option value="11">11</option>
																		<option value="12">12</option>
																		<option value="13">13</option>
																		<option value="14">14</option>
																		<option value="15">15</option>
																		<option value="16">16</option>
																		<option value="17">17</option>
																		<option value="18">18</option>
																		<option value="19">19</option>
																		<option value="20">20</option>
																		<option value="21">21 </option>
																		<option value="22">22</option>
																		<option value="23">23</option>
																		<option value="24">24</option>
																		<option value="25">25</option>
																		<option value="26">26</option>
																		<option value="27">27</option>
																		<option value="28">28</option>
																		<option value="29">29</option>
																		<option value="30">30</option>
																		<option value="31">31</option>
																		<option value="32">32</option>
																		<option value="33">33</option>
																		<option value="34">34</option>
																		<option value="35">35</option>
																		<option value="36">36</option>
																		<option value="37">37</option>
																		<option value="38">38</option>
																		<option value="39">39</option>
																		<option value="40">40</option>
																		<option value="41">41</option>
																		<option value="42">42</option>
																		<option value="43">43</option>
																		<option value="44">44</option>
																		<option value="45">45</option>
																		<option value="46">46</option>
																		<option value="47">47</option>
																		<option value="48">48</option>
																		<option value="49">49</option>
																		<option value="50">50</option>
																		<option value="51">51</option>
																		<option value="52">52</option>
																		<option value="53">53</option>
																		<option value="54">54</option>
																		<option value="55">55</option>
																		<option value="56">56</option>
																		<option value="57">57</option>
																		<option value="58">58</option>
																		<option value="59">59</option>
																		<option value="60">60</option>
																	</SELECT>
																</td>
															</tr>

															<tr><td>&nbsp;  </td></tr>
															<tr>
																<td align="right">
																	<input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/>
																	<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
																	<input type="button" name="RATE"  class="btn" value="Submit" onclick = "checkRecord()" />  
																</td>
															</tr>
													</td>
												</tr>
											</table>
										</td>
									</form>
								</td>
							</tr>    
						</table> 
					</tr>
				</table>
			</td>
        </tr>			
	</table>
</font>
</BODY>
</HTML>