
<HTML>
<HEAD>
<!--
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
-->
<TITLE>CREDIT SCORING - Get New Company Registration Number</TITLE>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css"/>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

<!-- DataTables JS library -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="assets/js/sweetalert.min.js"></script>
<script src="assets/js/dataTables.buttons.min.js"></script>
<script src="assets/js/jszip.min.js"></script>
<script src="assets/js/pdfmake.min.js"></script>
<script src="assets/js/vfs_fonts.js"></script>
<script src="assets/js/buttons.html5.min.js"></script>
<script src="assets/js/buttons.print.min.js"></script>
<link href="assets/css/Dupleix.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="assets/font-awesome-4.7.0/css/font-awesome.css"">

<!--
<link href="btu.css" rel="stylesheet" type="text/css">
-->
<script>
	function assign_application_ref_and_loan_no() {
		
		var company_reg_no       = document.GetRegNo.company_reg_no.value;
		console.log ("Company Registration Number is       "+company_reg_no);
		if (company_reg_no != '') {
			$.ajax({
				url:"ajaxScripts/get_application_ref_and_loan_no.php",
				type: "POST",
				data: {
					company_reg_no: company_reg_no
				},
				dataType : 'json',
				success: function(result){						
						document.GetRegNo.application_ref_no.value  = result.application_ref;
						document.GetRegNo.loan_no.value 			= result.loan_no;
						document.getElementById('GetRegNo').submit();
				}
			});
		}
	}	
    function preventBack() 
	{
        
		console.log ("SORRY! You cannot visit Company Data page with the Back button. Please edit data from the Add Menu ahead");
		window.history.forward();
    }

    setTimeout("preventBack()", 0);
    window.onunload = function() {
        null
    };
</script>

</HEAD>

<BODY BGCOLOR=#FFFFFF >
<!--
<font class="s_text">
-->
<?php
error_reporting(0);
//$username='root';
//$passwd='sefalana2008';
$username=$_POST['username'];
//echo  "BBS\ ";
/*
$bbs='BBS\\';

$new=$bbs.$username;
$ldappass=$_POST['password'];
//echo $new.$ldappass;

$ldaprdn  = $new;     // ldap rdn or dn
$ldappass = $ldappass;  // associated password

// connect to ldap server
$ldapconn = ldap_connect("10.0.9.10")
    or die("Could not connect to LDAP server.");

if ($ldapconn) {

    // binding to ldap server
    $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);

    // verify binding
    if ($ldapbind) {
        echo "";
    } else {
	
     echo "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
		

    }

}

*/
  echo $username;


$pass= "";//edward "sefalana2008";
$host="localhost"; // Host name 
$user="root"; // Mysql username 
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
//edward $ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); 
//echo $pass;
  if (!$connect) {
  mysqli_close($connect,$db_name); 
  echo "Cannot connect to the database! Please Check your username and password."; 
  die();
}
else {

 
}


?>

<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" bgcolor="#ECF8FF">
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="../assets/images/corporate_logo1.jpg" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="../assets/images/corporate_logo2.png" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>


</tr>
<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CORPORATE CREDIT SCORING</font></td><tr>
      </tr>
   <table  >
        <tr bgcolor="#ECF8FF" > 
          <td>
        <p></br> 
        <form ACTION="CompanyDataFirst.php" name="GetRegNo" id="GetRegNo" method="post">
		
			<div class="form-group row">
				<label for="company_reg_no" class="col-sm-7 control-label"><strong>Certificate Of Registratio No:</strong></label>
				<div class="col-sm-5">
					  <input type="text" class="form-control" id="company_reg_no" name="company_reg_no" placeholder="Certificate Of Reg. No" value="" required>
				</div>
			</div>
			<div class="form-group row">
				<label for="application_ref_no" class="col-sm-7 control-label"><strong>Application Reference No:</strong></label>
				<div class="col-sm-5">
					  <input readonly type="text" class="form-control" id="application_ref_no" name="application_ref_no" placeholder="Application Ref. No" value="" required>
				</div>
			</div>
			<div class="form-group row">
				<label for="loan_no" class="col-sm-7 control-label"><strong>Loan No:</strong></label>
				<div class="col-sm-5">
					  <input type="text" class="form-control" id="loan_no" name="loan_no" placeholder="Loan No" value="" required readonly>
				</div>
			</div>


</tr>

<tr><td>&nbsp;  </td></tr>
<tr> 
<td align="right">
<input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/>
<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
<input type="button" name="RATE"  class="btn" onclick = "assign_application_ref_and_loan_no()" value="Submit"/>  


</tr>

</form>



</td></tr>
</table>
</td>
</tr>    
 </table> 
</table>



</table>
</font>
</BODY>
</HTML>