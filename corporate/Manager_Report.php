<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>CORPORATE CREDIT SCORING - Manager Report</title>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="complaintstyle.css" rel="stylesheet" type="text/css">
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
<form  name = "ManagerReportForm" method="post">
 <input  style = "width:250px" type="submit" name="submit" class="btn" value="APPROVED VS DISAPPROVED&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='approvedVsDisapproved.php';"><br><br>
 <input  style = "width:250px" type="submit" name="submit" class="btn2" value="SCORED COUNTS FOR CONSULTANTS" onclick="form.action='cosultantCount1.php';"><br><br>
 <input  style = "width:250px" type="submit" name="submit" class="btn" value="CUSTOMER PORTFOLIO&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='scores1.php';" ><br><br>
 <input  style = "width:250px"  type="submit" name="submit" class="btn2" value="CHECK STATUS OF LOAN&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='checkstatus.php';" >
  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font color="#000000" face="Arial, Helvetica, sans-serif"><strong>Enter 
  Customer ID</strong></font>: 
  <input name="myid" type="text" size="20"><br><br>
   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
   <font color="#000000" face="Arial, Helvetica, sans-serif"><strong>Select 
Loan number</strong></font>: 
  <SELECT NAME="loan_number">
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
<br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn" value="RETURNED PER CONSULTANT&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='returned.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn2" value="STATUS REASONS&nbsp;&nbsp;&nbsp;" onclick="form.action='status_report.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn" value="APPROVALS BY LOCATIONS&nbsp;" onclick="form.action='locations.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn2" value="RATE/TYPE REQUESTED COUNTS" onclick="form.action='product_report.php';" ><br><br>
 <!--  
   <input style = "width:250px" type="submit" name="submit" class="btn" value="APPROVALS BY EMPLOYMENT CONTRACT" onclick="form.action='employment.php';" ><br><br>
 !-->  
   <input style = "width:250px" type="submit" name="submit" class="btn2" value="APPROVALS BY RATE TYPE" onclick="form.action='ratet_report.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn" value="APPROVALS BY CONSULANT" onclick="form.action='consultant_report.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn2" value="LOAN REQUESTS BY SECTOR" onclick="form.action='sector_report.php';" ><br><br>
    <input style = "width:250px" type="submit" name="submit" class="btn" value="APPROVALS BY SECTOR" onclick="form.action='sector_approval.php';" ><br><br>

  
<!--
<input type="submit" name="submit" class="btn2" value="MANAGER REPORTS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='manager_report.php';" ><br><br>
<input type="submit" name="submit" class="btn" value="RESET PASSWORD&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onclick="form.action='resetpassword.html';" >
-->
<table>
<tr><td><input type="hidden" name="mydate"    value="<?php echo $mydate; ?>"/></td></tr>
<tr><td><input type="hidden" name="mytime"    value="<?php echo $mytime; ?>"/></td></tr>
<tr><td><input type="hidden" name="username"  value="<?php echo $username;?>"/></td></tr>
<tr><td><input type="hidden" name="password"  value="<?php echo $password; ?>"/></td></tr></table>
</form>
<script>
  //alert (document.ManagerReportForm.password.value);
</script>
</body>
</html>
