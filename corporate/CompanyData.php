
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">

//<!-- Document.Ready to set up jquery.number.js -->

<script>
    function preventBack() 
	{
        
		alert ("SORRY! You cannot visit Company Data page with the Back button. Please edit data from the Add Menu ahead");
		window.history.forward();
    }

    setTimeout("preventBack()", 0);
    window.onunload = function() {
        null
    };
</script>
<?php
	    // echo <script> document.writeln(elem)

		 $company_reg_no = $_POST['company_reg_no'];
		 $username = $_POST['username'];
		 $pass= "";
         $host="localhost"; 
         $user="root"; 
			$db_name="corporatescoring"; 
                   
	               // GET HOST BY NAME ?
				   // Connect to server and select databse.
                   //$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
                   //echo $ip;

         //New PHP Version
         $connect=mysqli_connect($host,$user,$pass,$db_name); 
    
         if (!$connect) 
	      {
             mysqli_close($connect); 
             echo "Cannot connect to the database! Please Check your username and password."; 
             die;
			 //echo "<meta http-equiv=\"refresh\" content=\"0;URL=Retailuser_error.php\">";
		  }
         else 
		  {
 	          $RegNo_Query="select * from loan_data where company_reg_no ='$company_reg_no'";
  
              $resultm= mysqli_query($connect,$RegNo_Query); //interchanged by edward
              
			  if (!$resultm)
              {                                  
                 echo 'error with getting registration no'. mysql_error();
              }
              $rows=mysqli_fetch_array($resultm);

              $count=mysqli_num_rows($resultm);
              $Hold_Record_Query = "INSERT INTO loan_data SET company_reg_no = '$company_reg_no'";
 		      $Hold_Record_Success= mysqli_query($connect,$Hold_Record_Query); //interchanged by edward
              if ($Hold_Record_Success)
			  {
				 $application_ref = mysqli_insert_id($connect);
				 mysqli_close($connect);
		      }
          }
		  
		 //Old PHP Version
			       // mysqli_select_db( $connect,$db_name)  
                   // or die("Couldn't open $dbase: ".mysqli_error()
 ?>


<script type="text/javascript">

	
function SaveData()
{
 		 localStorage.application_ref                   = document.CompanyDataForm.application_ref.value;
	     localStorage.company_reg_no                    = document.CompanyDataForm.company_reg_no.value;
	     localStorage.CIF                               = document.CompanyDataForm.CIF.value;
		 localStorage.loan_number                       = document.CompanyDataForm.loan_number.value;
		 localStorage.username                          = document.CompanyDataForm.username.value;
		 localStorage.registration_year                 = document.CompanyDataForm.registration_year.value;
         localStorage.vat_reg_no                        = document.CompanyDataForm.vat_reg_no.value;
		 localStorage.customer_type                     = document.CompanyDataForm.customer_type.value;  
         localStorage.legal_name                        = document.CompanyDataForm.legal_name.value;
         localStorage.trading_name                      = document.CompanyDataForm.trading_name.value;
         localStorage.registration_country              = document.CompanyDataForm.registration_country.value;
         localStorage.financial_year0                   = document.CompanyDataForm.financial_year0.value;
         localStorage.financial_year1                   = document.CompanyDataForm.financial_year1.value;
         localStorage.financial_year2                   = document.CompanyDataForm.financial_year2.value;
         localStorage.financial_year3                   = document.CompanyDataForm.financial_year3.value;
         localStorage.reporting_month_year1             = document.CompanyDataForm.reporting_month_year1.value;
		 localStorage.reporting_month_year2             = document.CompanyDataForm.reporting_month_year2.value;
         localStorage.reporting_month_year3             = document.CompanyDataForm.reporting_month_year3.value;
         localStorage.audit_status_year1                = document.CompanyDataForm.audit_status_year1.value;
		 localStorage.audit_status_year2                = document.CompanyDataForm.audit_status_year2.value;
		 localStorage.audit_status_year3                = document.CompanyDataForm.audit_status_year3.value;
		 localStorage.real_inflation_year1              = document.CompanyDataForm.real_inflation_year1.value;
         localStorage.real_inflation_year2              = document.CompanyDataForm.real_inflation_year2.value;
         localStorage.real_inflation_year3              = document.CompanyDataForm.real_inflation_year3.value;
         localStorage.nominal_inflation_year1           = document.CompanyDataForm.nominal_inflation_year1.value;
         localStorage.nominal_inflation_year2           = document.CompanyDataForm.nominal_inflation_year2.value;
         localStorage.nominal_inflation_year3           = document.CompanyDataForm.nominal_inflation_year3.value;
         localStorage.industrial_sector                 = document.CompanyDataForm.industrial_sector.value;
         localStorage.borrower_present_address          = document.CompanyDataForm.borrower_present_address.value;
         localStorage.street_name_and_number            = document.CompanyDataForm.street_name_and_number.value;
         localStorage.years_at_present_address          = document.CompanyDataForm.years_at_present_address.value;
         localStorage.e_mail                            = document.CompanyDataForm.e_mail.value;
         localStorage.bond_plot                         = document.CompanyDataForm.bond_plot.value;
	     localStorage.location_of_acquired_real_estate  = document.CompanyDataForm.location_of_acquired_real_estate.value;
         localStorage.main_bank                         = document.CompanyDataForm.main_bank.value;
         localStorage.second_bank                       = document.CompanyDataForm.second_bank .value;
         localStorage.third_bank                        = document.CompanyDataForm.third_bank.value;
          
		 localStorage.username                          = document.CompanyDataForm.username.value;
   
         alert ("Company Data temporarilly saved(overwritten) to Local Machine");
}    



 
function ToNumber(nStr) 
{
  if   (typeof(nStr) == "undefined") {  return 0;}
  else  
  {
  if (nStr == "") {return 0};
  if (nStr != "") {return parseFloat(nStr.replace(/,/g,''))};
  } 
}  

function ValidateYear()
{
  var year0 = ToNumber(document.CompanyDataForm.financial_year0.value);
  document.CompanyDataForm.financial_year1.value = year0 + 1;
  document.CompanyDataForm.financial_year2.value = year0 + 2;
  document.CompanyDataForm.financial_year3.value = year0 + 3;
}


function CheckCompanyInDatabase(elem)

{
   
 
}


</script>

</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php


/**
//$username='root';
//$passwd='sefalana2008';


// Edward $username=$_POST['username'];

//echo  "BBS\ ";
$bbs='BBS\\';
/*
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


$username = $_POST['username'];
$password = $_POST['password'];
echo $username;
//echo $password; echo 'ttt';

$pass=$_POST['password'];
$host="localhost"; // Host name 
//$username="root"; // Mysql username 
//$password="sefalana2008"; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
// Edward $ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect("localhost","root","",$db_name); //updated by Edward
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password.";
  die(); 
}
else {
mysqli_select_db($connect,$db_name )     
or die("Couldn't open $dbase: ".mysql_error());  

}

**/
?>

<table width="1100" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" >
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>


</tr>
<tr>
<td width="1700" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="6" face="Arial Narrow">COMPANY DATA</font></td><tr>
      </tr>
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text"> 
          <td>
        <p></br> 
        <form ACTION="SaveCompanyData.php" name="CompanyDataForm" method="post"  onClick="return displ();">
            <td><table>
		   <tr>
		       <td><p align="justify" class="s_text"><table class="s_text">
           <tr>
               <td><strong><font color="#FF0000">**</font></strong> <em><font color="#FF9900" size="1">System Generated Data</font></em></td>
          </tr>
          <tr>
              <td><strong><font color="#FF0000">**</font><font color="#000000">Loan Application No (Overall)</font></strong></td>
              <td></td>
			  <td><input type="text" name="application_ref" style = "background-color:powderblue" 
			                         value = "<?php echo $application_ref; ?>" readonly></td> 
		      <td colspan = 2><font color="#FF0000">**</font><strong>Loan Count (Company Specific)</strong></td>
			  <td colspan = 2><input type="text" name="loan_number" size = 4 style ="background-color:powderblue" 
			             value = "<?php echo $count+1 ; ?>" readonly></td> 
		  </tr>
		  <tr><td colspan = 5>__________________________________________<strong>COMPANY REGISTRATION DETAILS  :</strong>_____________________________</td></tr>
 
		  <tr>
              <td><strong><font color="#000000">Certificate Of Registratio No</font></strong></td>
              <td></td>
			  <td  colspan = 2><input required type="text" name="company_reg_no" value = "<?php echo $company_reg_no ; ?>" 
			                          onblur = "CheckCompanyInDatabase(this.value)" onchange = "CheckCompanyInDatabase(this.value)"
									  style = "background-color:powderblue" readonly>
               </td> 
		  </tr>

          <tr>
              <td><strong><font color="#000000">VAT Registratio No</font></strong></td>
              <td></td>
			  <td  colspan = 2><input type="text" name="vat_reg_no"></td> 
		  </tr>
          <tr>
              <td colspan = 2><strong><font color="#000000">Customer CIF <font color="#FF0000">(If existing BBS Customer)</font></font></strong></td>
              
			  <td><input type="text" name="CIF"></td> 
		  </tr>
          <tr>
               <td>Year Of Registration (4 Digits)</td>
	           <td></td>
	           <td><input required name ="registration_year"  type ="text" size = 1 ></td>
          	   <td colspan = 2><font color="#FF0000">**</font>Years In Business</td>
			   <td><input required name ="years_in_business"  type ="text" size = 1 style = "background-color:powderblue" readonly ></td>
		  </tr>
		  <tr><td>Customer Legal Type:</td><td width="10">&nbsp;</td>
	          <td><SELECT required  NAME="customer_type" > 
                     <option value=""> </option>
                     <option value="Pty Ltd">Pty Ltd</option>
                     <option value="Ltd">Ltd</option>
                  </SELECT>
              </td>
           </tr>

   <tr><td>Legal Name (Without Legal Type Suffix Above) :</td><td width="60">&nbsp;</td><td>
            <input required name="legal_name" type="text">
       </td>
   </tr>
   </tr>
   <tr><td>Trading Name :</td><td width="60">&nbsp;</td><td>
             <input required name="trading_name" type="text">
       </td>
   </tr>
   <tr></tr>
   <tr><td>Country Of Registration:</td><td width="60">&nbsp;</td><td>
           <SELECT required NAME="registration_country" >
              <option value="Botswana">Botswana </option>
              <option value="Foreign">Foreign </option>
           </SELECT>
       </td>
   <tr><td colspan = 5>__________________________________________<strong>FINANCIAL PERIODS SETTINGS  :</strong>_______________________________</td></tr>
   <tr>
       <td></td>
	   <!--<td width="60">&nbsp;</td>-->
       <td></td>
	   <td bgcolor = "powderblue" style = "color:black"><strong>Year 0</strong></td>

	   <td bgcolor = "powderblue" style = "color:black"><strong><font color="#FF0000">**</font>Year 1</strong></td>
	   <td bgcolor = "powderblue" style = "color:black"><strong><font color="#FF0000">**</font>Year 2</strong></td>
       <td bgcolor = "powderblue" style = "color:black"><strong><font color="#FF0000">**</font>Year 3</strong></td>
   </tr>  
   <tr>
       <td>Financial Year (4 Digits)</td>
	   <td></td>
       <td><input required  name ="financial_year0"  type ="text" size = 1 style = "text-align:right" onchange ="ValidateYear()"></td>
	   <td><input required name ="financial_year1"  type ="text" size = 1 style = "text-align:right;background-color:powderblue"></td>
       <td><input required name ="financial_year2"  type ="text" size = 1 style = "text-align:right;background-color:powderblue"></td>
	   <td><input required name ="financial_year3"  type ="text" size = 1 style = "text-align:right;background-color:powderblue"></td>
   </tr>	   
   <tr>
       <td>Reporting Month (1-12)  :</td>
	   <td></td>
	   <td> <SELECT required NAME="reporting_month_year0" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
           </SELECT></td>
       <td> <SELECT required NAME="reporting_month_year1" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
           </SELECT></td>
       <td> <SELECT required NAME="reporting_month_year2" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
           </SELECT></td>
       <td> <SELECT required NAME="reporting_month_year3" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
           </SELECT></td>
     </tr>
	 <tr>
       <td>No of Months In Year (1-15)  :</td>
	   <td></td>
       <td> <SELECT required NAME="months_in_year0" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
              <option value="13">13</option>
              <option value="14">14</option>
              <option value="15">15</option>
            </SELECT></td>
     
	   <td> <SELECT required NAME="months_in_year1" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
              <option value="13">13</option>
              <option value="14">14</option>
              <option value="15">15</option>
            </SELECT></td>
       <td> <SELECT required NAME="months_in_year2" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
              <option value="13">13</option>
              <option value="14">14</option>
              <option value="15">15</option>
           </SELECT></td>
       <td> <SELECT required NAME="months_in_year3" >
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
              <option value="11">11</option>
              <option value="12" selected>12</option>
              <option value="13">13</option>
              <option value="14">14</option>
              <option value="15">15</option>
           </SELECT></td>
     </tr>  
	<tr>
       <td>Audit Status  :</td>
	   <td></td>
       <td> <SELECT required NAME="audit_status_year0" >
              <option value="Audited">Audited</option>
              <option value="Registered Accountant">Registered Accountant</option>
			  <option value="Management Accounts">Management Accounts</option>
            </SELECT></td>
    
	   <td> <SELECT required NAME="audit_status_year1" >
              <option value="Audited">Audited</option>
              <option value="Registered Accountant">Registered Accountant</option>
			  <option value="Management Accounts">Management Accounts</option>
            </SELECT></td>
       <td> <SELECT required  NAME="audit_status_year2" >
              <option value="Audited">Audited</option>
              <option value="Registered Accountant">Registered Accountant</option>
			  <option value="Management Accounts">Management Accounts</option>
            </SELECT></td>
       <td> <SELECT required NAME="audit_status_year3" >
              <option value="Audited">Audited</option>
              <option value="Registered Accountant">Registered Accountant</option>
			  <option value="Management Accounts">Management Accounts</option>
            </SELECT></td>
     </tr> 
	 <tr>
       <td>Annual Inflation Rate - Real</td>
	   <td></td>
	   <td><input name ="real_inflation_year0"  type ="text" size = 1></td>
  	   <td><input name ="real_inflation_year1"  type ="text" size = 1></td>
       <td><input name ="real_inflation_year2"  type ="text" size = 1></td>
	   <td><input name ="real_inflation_year3"  type ="text" size = 1></td>
   </tr>
   	 <tr>
       <td>Annual Inflation Rate - Norminal</td>
	   <td></td>
	   <td><input name ="nominal_inflation_year0"  type ="text" size = 1></td>
 	   <td><input name ="nominal_inflation_year1"  type ="text" size = 1></td>
       <td><input name ="nominal_inflation_year2"  type ="text" size = 1></td>
	   <td><input name ="nominal_inflation_year3"  type ="text" size = 1></td>
   </tr>
   <tr><td colspan = 5>__________________________________________________________________________________________________________</td></tr>
  
<tr><td>Industrial Sector:</td><td width="60">&nbsp;</td><td  colspan = 2><SELECT required NAME="industrial_sector" > 
<option value="Trade">Trade </option>
<option value="Finance And Business Services">Finance & Business Services</option>
<option value="Real Estate">RealEstate</option>
<option value="Manufacturing">Manufacturing</option>
<option value="Construction">Construction</option>
<option value="Agriculture">Agriculture</option>
<option value="Parastatals">Parastatals</option>
<option value="Transport And Communications">Transport & Communications</option>
<option value="Mining">Mining</option>

</SELECT></td></tr>

<tr><td>Company Present Address :</td><td width="60">&nbsp;</td><td>
<input name="borrower_present_address" required type="text">
</td></tr>
<tr><td>Street Name and Number :</td><td width="60">&nbsp;</td><td>
<input name="street_name_and_number" required type="text">
</td></tr>
<tr><td>Years At Present Address:</td><td width="60"></td><td> <SELECT  required NAME="years_at_present_address" >
<option value="0 to 3">0 to 3</option>
<option value="3 to 5">3 to 5</option>
<option value="5 to 10">5 to 10</option>
<option value="+10">+10</option>
</SELECT></td></tr>
</td></tr>
<tr><td>Company E_mail Address :</td><td width="60">&nbsp;</td><td>
<input name="e_mail" type="text">
</td></tr>
<tr><td>Plot No.of bonded security :</td><td width="60">&nbsp;</td><td>
<input name="bond_plot" type="text">
</td></tr>

 &nbsp;&nbsp;&nbsp;

<tr><td>Location of Acquired Real Estate:</td><td width="60">&nbsp;</td><td> <SELECT NAME="location_of_acquired_real_estate" >
<option value="Urban A">Urban A</option>
<option value="Urban B">Urban B</option>
<option value="Semi Urban A">Semi Urban A </option>
<option value="Semi Urban B">Semi Urban B </option>
<option value="Rural">Rural </option>
</SELECT>
</td></tr>

</td></tr>


<tr><td>Main Bank:</td><td width="10"></td><td> <SELECT NAME="main_bank" >

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


</SELECT></td></tr>


<tr><td>Second Bank: </td><td width="10"></td><td><SELECT NAME="second_bank" >
<option value="NA">Not Applicable</option>
<option value="BBS">BBS</option>
<option value="BSB">BSB</option>
<option value="NDB">NDB</option>
<option value="FNBB">FNBB</option>
<option value="S&C">S&C </option>
<option value="Barclays">Barclays</option>
<option value="Stanbic Bank">Stanbic Bank</option>
<option value="Bank Gaborone">Bank Gaborone</option>
<option value="Bank ABC">Bank ABC</option>
<option value="Bank Of Baroda">Bank Of Baroda</option>
<option value="Capital Bank">Capital Bank</option>
<option value="State Bank of India">State Bank of India</option>
</SELECT></td></tr>

<tr><td>Third Bank: </td><td width="10"></td><td><SELECT NAME="third_bank" >
<option value="NA">Not Applicable</option>
<option value="BBS">BBS</option>
<option value="BSB">BSB</option>
<option value="NDB">NDB</option>
<option value="FNBB">FNBB</option>
<option value="S&C">S&C </option>
<option value="Barclays">Barclays</option>
<option value="Stanbic Bank">Stanbic Bank</option>
<option value="Bank Gaborone">Bank Gaborone</option>
<option value="Bank ABC">Bank ABC</option>
<option value="Bank Of Baroda">Bank Of Baroda</option>
<option value="Capital Bank">Capital Bank</option>
<option value="State Bank of India">State Bank of India</option>

</SELECT></td></tr>

<tr><td>
<input type = "hidden" name="username"  value="<?php echo $username;?>"/>
<input type = "hidden" name="password"  value="<?php echo $password; ?>"/>

<input type="SUBMIT" name="SubmitCompnayData"  class="btn" value="Submit" onclick = "SaveData()"> 
<tr><td>&nbsp;</td></tr>

</td></tr>


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