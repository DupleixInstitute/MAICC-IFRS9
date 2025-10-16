<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>Untitled Document</title>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="complaintstyle.css" rel="stylesheet" type="text/css">
<script src="assets/js/sweetalert.min.js"></script>
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/credit_scoring.js"></script>

</head>

<body>
<?php
$username = $_POST['username'];
$password = $_POST['password'];
//echo $username;
//echo $password;
//$username='root';
//$passwd='sefalana2008';
$mydate=date('Y-m-d');
$mytime=date("H.i");
//echo $mytime;echo $mydate;
//echo 'ttt';
//echo $mytime;j
?>

<table><tr><td><img src="img1.gif" width="385" height="208" border="0" ></td><td><img src="img2.jpg" width="375" height="208" border="0" ></td></tr>
</table>
<form  id = "manager_report" method="post">
<table name = "manager_report">
  <tr><td>
         <input type="submit" name="submit" class="btn"  
		        value   = "APPROVED VS DISAPPROVED"       
				onclick = "form.action='approvedVsDisapproved.php';"><br><br>
	  </td>
	  <td>
         <font color="#000000" face="Arial, Helvetica, sans-serif"><strong>Enter Customer ID</strong></font>: 
      </td>
      <td>	  
         <input id = "myid" name="myid" type="text" size="20" value = 1>
		 <font color="#000000" face="Arial, Helvetica, sans-serif"><strong>Select Loan number</strong></font>: 
            <SELECT NAME="loan_number" id = "loan_number">
				<option value=""> </option>
				<option selected value="1">1 </option>
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
				<option value="21">21</option>
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
  <tr><td><input type="submit" name="submit" class="btn2" value="SCORED COUNTS FOR CONSULTANTS" onclick="form.action='cosultantCount1.php';">
      </td>
	  <td>
		  <font color="#000000" face="Arial, Helvetica, sans-serif"><strong>Select Loan Category</strong></font>: 
	  </td>
      <td>	  
		  <SELECT NAME="loan_category">
			 <option value="mortgage"      >mortgage      </option>
			 <option value="unsecured_loan">unsecured_loan</option>
		  </SELECT>
	  </td>
  </tr>
  <tr></tr><tr></tr><tr></tr><tr></tr><tr></tr><tr></tr>
  <tr><td><input type   ="submit"      name ="customer_portfolio" 
                 class  ="btn"         value="CUSTOMER PORTFOLIO"            
				 onclick="manager_reports_menu_validation(this.name);" >
		  <br><br> 
	  </td>
  </tr>
  
  
  <tr><td>
          <input type     ="submit" name="check_status" class="btn2" 
                 value    ="CHECK STATUS OF LOAN"          
				 onclick  ="manager_reports_menu_validation(this.name);"
				 > 
	  </td>
  </tr>
</table>
<br>
    <input type    ="submit" name ="returned_per_consultant"  class="btn"    
		   value   ="RETURNED PER CONSULTANT&nbsp;&nbsp;&nbsp;&nbsp;" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>

    <input type    ="submit" name="status_reasons" class="btn2" 
	       value   ="STATUS REASONS&nbsp;&nbsp;&nbsp;" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>

    <input type    ="submit" name="approvals_by_location" class="btn" 
	       value   ="APPROVALS BY LOCATIONS&nbsp;" 
		   onclick ="manager_reports_menu_validation(this.name);"><br><br>
					
    <input type    ="submit"    name="rate_type_requested_counts" class="btn2" 
	       value   ="RATE/TYPE REQUESTED COUNTS" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>
    
	<input type    ="submit"     name="approvals_by_employment_contract" class="btn" 
	       value   ="APPROVALS BY EMPLOYMENT CONTRACT" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>
		   
    <input type    ="submit"     name="approvals_by_rate_type" class="btn2" 
	       value   ="APPROVALS BY RATE TYPE" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>
		   
    <input type    ="submit"     name="approvals_by_consultant"    class="btn" 
	       value   ="APPROVALS BY CONSULANT" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>
 
    <input type    ="submit"     name="table_dump"    class="btn" 
	       value   ="DOWNLOAD TABLE" 
		   onclick ="manager_reports_menu_validation(this.name);" ><br><br>
     
	<input type="submit" name="submit" class="btn2" value="LOAN REQUESTS BY GENDER" onclick="form.action='gender_report.php';" ><br><br>
    <input type="submit" name="submit" class="btn" value="APPROVALS BY GENDER" onclick="form.action='gendert_approval.php';" ><br><br>

  
<!--
<input type="submit" name="submit" class="btn2" value="MANAGER REPORTS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='manager_report.php';" ><br><br>
<input type="submit" name="submit" class="btn" value="RESET PASSWORD&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='resetpassword.html';" >
-->
<table>
<tr><td><input type="hidden" name="mydate"  value="<?php echo $mydate; ?>"/></td></tr>
<tr><td><input type="hidden" name="mytime"  value="<?php echo $mytime; ?>"/></td></tr>
<tr><td><input type="hidden" name="username"  value="<?php echo $username;?>"/></td></tr>
<tr><td><input type="hidden" name="password"  value="<?php echo $password; ?>"/></td></tr></table>
</form>
</body>
</html>
