
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CORPORATE CREDIT SCORING - Summary Results</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<form action="FinalApproval.php" method="POST">
<font class="s_text">
<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" >
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></tr>
<tr>


</tr>

<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING:&nbsp;</font><font color="#FFFFFF" size="6" face="Arial Narrow">LOAN APPLICATION SUMMARY</font></td><tr>
      </tr>
   <table class="stext2" bgcolor="#FFFFFF"  >
       <tr>
	   <td>
	   
<?php

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
//echo $username;


$application_ref=$_POST['application_ref'];
$company_reg_no=$_POST['company_reg_no'];


$loan_number=$_POST['loan_number'];


// Connect to server and select databse.
$pass="";
$user="root";
$host="localhost"; // Host name 

$db_name="corporatescoring"; // Database name 


$connect=mysqli_connect($host,$user,$pass,$db_name); 
  
if (!$connect) {
   mysqli_close($connect); 
   echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
   mysqli_select_db($connect,$db_name)
   or die("Couldn't open $dbase: ".mysqli_error());
}


//Depending on which of the two search keys used, construct a select query

//1. When Search Key = Application Reference Number
if ($application_ref !== "") {
   $selectO="SELECT loan_data.application_ref,
                    loan_data.company_reg_no,
                    loan_data.loan_number,
					loan_data.legal_name,
					loan_data.customer_type,
                    loan_data.trading_name,
                    loan_data.street_name_and_number as street,
                    loan_data.debtserviceratio as debt,
		            loan_data.bond_plot as bond,
		            loan_data.loan_to_value as ltv, 
		            loan_data.loan_amount_requested as loan,
		            loan_data.current_interest_rate as interest,
		            loan_data.loan_maturity_requested as maturity,
		            loan_data.estimated_installment as installment, 
		            cust_credit_rating.rate
		     FROM   loan_data,
			    	scores,
				    cust_credit_rating 
				 
		     WHERE	loan_data.company_reg_no  = cust_credit_rating.company_reg_no  and 
				    loan_data.company_reg_no  = scores.company_reg_no         and 
				    loan_data.loan_number     = scores.loan_number            and 
				    loan_data.loan_number     = cust_credit_rating.loan_number     and 
				    loan_data.application_ref = '$application_ref'";
}

// When Composite Search Key = Company Registration Number + Loan Number
else {	
 	                //loan_data.affordability_ratio as aff,
  
   $selectO="SELECT loan_data.application_ref,
                    loan_data.company_reg_no,s
                    loan_data.loan_number,
					loan_data.legal_name,
					loan_data.customer_type,
					loan_data.customer_type,
                    loan_data.trading_name,
                    loan_data.street_name_and_number as street,
                    loan_data.debtserviceratio as debt,
		            loan_data.bond_plot as bond,
		            loan_data.loan_to_value as ltv, 
		            loan_data.loan_amount_requested as loan,
		            loan_data.current_interest_rate as interest,
		            loan_data.loan_maturity_requested as maturity,
		            loan_data.estimated_installment as installment 
		  
		     FROM   loan_data,
			    	scores,
				    cust_credit_rating 
				 
		     WHERE	loan_data.company_reg_no  = cust_credit_rating.company_reg_no  and 
				    loan_data.company_reg_no  = scores.company_reg_no         and 
				    loan_data.loan_number     = scores.loan_number            and 
				    loan_data.loan_number     = cust_credit_rating.loan_number     and 
				    loan_data.company_reg_no  = '$company_reg_no'                  and 
				    loan_data.loan_number     = $loan_number";
}

$result1=mysqli_query($connect,$selectO);

if(!$result1)
{
   echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed1.php\">";
   
}

$mycounts=0;
while($temp_rows=mysqli_fetch_array($result1)){
   $mycounts++;
   $rows1 = $temp_rows;
}
if ($mycounts<1) {
  echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed1.php\">";
  die();
}

//Ensuring we have all the search keys variables before the report, regardless of which one used
$application_ref    = $rows1['application_ref'];
$company_reg_no     = $rows1['company_reg_no'];

$loan_number        = $rows1["loan_number"];
$trading_name       = $rows1['trading_name'];
if ($trading_name == "") {$full_customer_name = $rows1['legal_name']." ".$rows1['customer_type'];}
else {$full_customer_name = $rows1['legal_name']." ".$rows1['customer_type']." TRADING AS ".$trading_name;}
?>

<tr  bgcolor="#FFFFFF"><td ><?php echo "LOAN APPLICATION NUMBER:"    ;?></td><td ></td><td><?php echo $application_ref      ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "COMPANY REGISTRATION NUMBER:";?></td><td ></td><td><?php echo $company_reg_no       ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "LOAN NUMBER: "               ;?></td><td ></td><td><?php echo $loan_number          ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "CUSTOMER NAME: "             ;?></td><td ></td><td><?php echo $full_customer_name   ;?></td></tr>

<tr  bgcolor="#FFFFFF"><td ><?php echo "PLOT NUMBER: "               ;?></td><td ></td><td><?php echo $rows1['street']      ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "LTV: "                       ;?></td><td ></td><td><?php echo $rows1['ltv']         ;?></td></tr>

<!--tr  bgcolor="#FFFFFF"><td ><?php echo "AFFORDABILITY RATIO:"     ;?></td><td ></td><td><?php echo $rows1['aff']         ;?></td></tr>-->

<tr  bgcolor="#FFFFFF"><td ><?php echo "DEBT SERVICE RATIO:"         ;?></td><td ></td><td><?php echo $rows1['debt']        ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "LOAN AMOUNT"                 ;?></td><td ></td><td><?php echo $rows1['loan']        ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "INTEREST RATE: "             ;?></td><td ></td><td><?php echo $rows1['interest']    ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "YEARS TO MATURITY: "         ;?></td><td ></td><td><?php echo $rows1['maturity']    ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "REPAYMENT AMOUNT: "          ;?></td><td ></td><td><?php echo $rows1['installment'] ;?></td></tr>
<tr  bgcolor="#FFFFFF"><td ><?php echo "BOND SECURITY PLOT No: "     ;?></td><td ></td><td><?php echo $rows1['bond']        ;?></td></tr>


</table>
</br></br>
<input type="hidden" name="application_ref" value="<?php echo $application_ref;?>" />
<input type="hidden" name="full_customer_name" value="<?php echo $full_customer_name;?>" />
<input type="hidden" name="company_reg_no" value="<?php echo $company_reg_no;?>" />
<input type="hidden" name="loan_number" value="<?php echo $loan_number;?>" />
<input type="hidden" name="username" value="<?php echo $username;?>" />
<input type="hidden" name="password" value="<?php echo $password;?>" />

   &nbsp;&nbsp;<input type="submit" value="SUBMIT" class="btn" />
 </table> 
</table>
</form>
<?php
?>
</BODY>
</HTML>