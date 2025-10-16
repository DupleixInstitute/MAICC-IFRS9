
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">

<script type="text/javascript">
function total_kids()
{

var children1=document.total_revenue_for_Affordability.to12.options[document.total_revenue_for_Affordability.to12.selectedIndex].value;
 var children11= parseFloat(children1);



var children2=document.total_revenue_for_Affordability.to18.options[document.total_revenue_for_Affordability.to18.selectedIndex].value;
 var children22= parseFloat(children2);


var children3=document.total_revenue_for_Affordability.to26.options[document.total_revenue_for_Affordability.to26.selectedIndex].value;
 var children33= parseFloat(children3);



var total_dependents=children11 + children22 + children33;



document.total_revenue_for_Affordability.no_of_children.value=total_dependents;

return true;
}



function total_dependents()
{


var aunts=document.total_revenue_for_Affordability.aunts_uncles_cousins.options[document.total_revenue_for_Affordability.aunts_uncles_cousins.selectedIndex].value;
 var aunts1= parseFloat(aunts);


var others=document.total_revenue_for_Affordability.other.options[document.total_revenue_for_Affordability.other.selectedIndex].value;
 var others1= parseFloat(others);



var grandparent=document.total_revenue_for_Affordability.grandparents.options[document.total_revenue_for_Affordability.grandparents.selectedIndex].value;
 var grandparents1= parseFloat(grandparent);

var total_dependents= aunts1 + others1 + grandparents1;



document.total_revenue_for_Affordability.dependants_at_home.value=total_dependents;

return true;
}





function displ()
{
var savings=document.total_revenue_for_Affordability.Savings.options[document.total_revenue_for_Affordability.Savings.selectedIndex].value;
var Deposit=document.total_revenue_for_Affordability.Deposit.options[document.total_revenue_for_Affordability.Deposit.selectedIndex].value;
var Share=document.total_revenue_for_Affordability.Share.options[document.total_revenue_for_Affordability.Share.selectedIndex].value;
var ST=document.total_revenue_for_Affordability.ST.options[document.total_revenue_for_Affordability.ST.selectedIndex].value;
var Mortgages=document.total_revenue_for_Affordability.Mortgages.options[document.total_revenue_for_Affordability.Mortgages.selectedIndex].value;
var x=0;

  if(savings !== "NA") {
  x=x + 1;
  }
 
 
 if(Deposit !== "NA") {
  x=x + 1;
  }
  
  if(Share !== "NA") {
  x=x + 1;
  }
  
  
  if(ST !== "NA") {
  x=x + 1;
  }  
  
  if(Mortgages !== "NA") {
  x=x + 1;
  }
  
  document.total_revenue_for_Affordability.total_bbs_products.value =x;
  
    return true;

}


function loantoval()
{
var loan=document.total_revenue_for_Affordability.loan_amount.value;
var omv=document.total_revenue_for_Affordability.open_market_value.value;
 var loan1 = parseFloat(loan);
 var omv1 = parseFloat(omv);
 
var loantovalue= loan1 / omv1;
var loantovalue3= loantovalue*100;
var loantovalue2= loantovalue3.toFixed(0);
document.total_revenue_for_Affordability.ltv.value =loantovalue2;
	return true;
}


function mortinstal()
{
var instal=document.total_revenue_for_Affordability.loan_installment.value;
var insure=document.total_revenue_for_Affordability.insurance_premium.value;
var oloan=document.total_revenue_for_Affordability.o_loan_type.value;
 var add2 = parseFloat(instal);
 var add1 = parseFloat(insure);
 
var finalinstal= add1 + add2;

 if(oloan == "Fixed") {
var finalinstal= add2;
  }  
var finalinstalment= finalinstal.toFixed(2)
document.total_revenue_for_Affordability.loanandinsurance.value = finalinstalment;
	return true;
}



function myltvpolicy()
{

var test2=document.total_revenue_for_Affordability.loan_type.options[document.total_revenue_for_Affordability.loan_type.selectedIndex].value;
var val= String(test2);

if (val == "Standard to Batswana")
{
var ltvratio=90;
}

if (val == "Guaranteed Scheme")
{
var ltvratio=100;
}

if (val == "Standard to Non-Citizens")
{
var ltvratio=90;
}

if (val == "Second Time Home Owner")
{
var ltvratio=90;
}
if (val == "Non-Guaranteed Scheme")
{
var ltvratio=90;
}
if (val == "77")
{
var ltvratio=90;
}
document.total_revenue_for_Affordability.ltv_policy.value = ltvratio;
	return true;
}




function premium()
{
var myinsure=document.total_revenue_for_Affordability.insurance_replacement.value;
var test=document.total_revenue_for_Affordability.property_type.options[document.total_revenue_for_Affordability.property_type.selectedIndex].value;
var val= String(test);

if (val == "Residential")
{
var premiumpercent=0.07;
//alert("yes1");
}
if (val == "Commercial")
{
var premiumpercent=0.125;
//alert("yes2");
}
if (val == "Thatched_Residential")
{
var premiumpercent=0.6;
//alert("yes3");
}
if (val == "Thatched_Commercial")
{
//alert("yes4");
var premiumpercent=0.9;
}

var mypremium= (myinsure * premiumpercent/12)/100;
var finalpremium= mypremium.toFixed(2);
document.total_revenue_for_Affordability.insurance_premium.value = finalpremium;
	return true;
}



function ltv()
{
var amt=document.total_revenue_for_Affordability.loan_amount.value;
var val=document.total_revenue_for_Affordability.open_market_value.value;
var loanvale= amt/val;
var myltv= loanval.toFixed(2);
document.total_revenue_for_Affordability.insurance_replacement.value = myltv;
	return true;
}




function insure()
{
var opv=document.total_revenue_for_Affordability.open_market_value.value;
var insurance= opv * 1.4;
var ins= insurance.toFixed(2);
document.total_revenue_for_Affordability.insurance_replacement.value = ins;
	return true;
}



function instal()
{
var rate=document.total_revenue_for_Affordability.irate.value;
var amount =document.total_revenue_for_Affordability.loan_amount.value;
var maturity =document.total_revenue_for_Affordability.loan_maturity.value;

if (isNaN(rate) || isNaN(amount) || isNaN(maturity)) {
		alert("You can only enter numbers in the Loan Amout and Rate fields!");	
}
maturity=maturity*12;
rate=rate / 12;
rate=rate / 100;

var x= 1+ rate;

var y =Math.pow(x, maturity);
var b =Math.pow(x, maturity);




var val = rate * b; 
//var valaa= val.toFixed(2);


var valb =  y - 1; 
//var valbb = val.toFixed(2);

var pmt = amount * (val / valb); 

var dd= pmt.toFixed(2);
document.total_revenue_for_Affordability.loan_installment.value = dd;
	return true;
 }




function checkRates(irate)
{
var mm =document.total_revenue_for_Affordability.irate.value;
var mm2 =document.total_revenue_for_Affordability.loan_amount.value;
var mm3 =document.total_revenue_for_Affordability.loan_maturity.value;
var numString = String(mm);
var numString2 = String(mm2);
var numString3 = String(mm3);

var papi = String("papiso");
newstring= papi.concat(numString);
newstring2= papi.concat(numString2);
newstring3= papi.concat(numString3);

if(newstring.length == 6 || newstring2.length == 6 || newstring3.length == 6 ) 
   {
      alert("Ensure that the loan amount, loan maturity and interest are all filled to get a correct installment amount");
	     return false;
   }
 }

function calcTotal() {

var addaa = document.total_revenue_for_Affordability.tax.value
    var addaa = parseFloat(addaa, 10)
    addaa = (isNaN(addaa))?0:addaa;
	
	var addbb = document.total_revenue_for_Affordability.tax2.value
    var addbb = parseFloat(addbb, 10)
    addbb = (isNaN(addbb))?0:addbb;
	
	
var add1 = document.total_revenue_for_Affordability.annual_sal.value
    var add1 = parseFloat(add1, 10)
    add1 = (isNaN(add1))?0:add1;
    
    //ADD SECOND INPUT VALUE
    var add2 = document.total_revenue_for_Affordability.fixed_perm_allowances.value
    var add2 = parseFloat(add2, 10)
    add2 = (isNaN(add2))?0:add2;
    
	var firstItem = add1 + add2 - addaa ;
	
	var add3 = document.total_revenue_for_Affordability.hp_annual_sal.value
    var add3 = parseFloat(add3, 10)
    add3 = (isNaN(add3))?0:add3;
    
    //ADD SECOND INPUT VALUE
    var add4 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value
    var add4 = parseFloat(add4, 10)
    add4 = (isNaN(add4))?0:add4;
	
	var secondItem = add3 + add4 - addbb;
	
	
	if (isNaN(firstItem) || isNaN(secondItem)) {
		alert("You can only enter numbers in the Quantity and Rate fields!");
		return false;
	}
	else {
		document.total_revenue_for_Affordability.total_rev_for_afford.value = firstItem;
		document.total_revenue_for_Affordability.hp_total_rev_for_afford.value = secondItem;
		
		
		/*document.forms[0].amount6.value = sixthItem;*/
		var grandTotal = firstItem + secondItem ;
		document.total_revenue_for_Affordability.total_allowable_household_revenues.value = grandTotal;
		var tt=grandTotal/12;
		var tt2= tt.toFixed(2);
			document.total_revenue_for_Affordability.rev4afford.value = tt2;
			
		return true;
	}
}




function Affordability_Ratio(){
var installment=document.total_revenue_for_Affordability.loanandinsurance.value;
var monthsal =document.total_revenue_for_Affordability.rev4afford.value;    


var ratio= installment/monthsal;
var ratio2=ratio*100;
var finalratio= ratio2.toFixed(2);
document.total_revenue_for_Affordability.affordability_ratio.value=finalratio;

}

function new_loan(){
var installment=document.total_revenue_for_Affordability.o_bal.value;
 var installment = parseFloat(installment, 10)
    installment = (isNaN(installment))?0:installment;
var monthsal =document.total_revenue_for_Affordability.newloan.value;    
 var monthsal = parseFloat(monthsal, 10)
    monthsal = (isNaN(monthsal))?0:monthsal;
	


var newtotalloan= installment + monthsal;
var newtotalloan2= newtotalloan.toFixed(2);
document.total_revenue_for_Affordability.loan_amount.value= newtotalloan2;
return true;
}

function isEmpty(strfield1, strfield2, strfield3) {


//change "field1, field2 and field3" to your field names

function check(total_revenue_for_Affordability){
if (isEmpty(total_revenue_for_Affordability.loan_amount)){
  if (isEmpty(total_revenue_for_Affordability.property_type)){
    if (isEmpty(total_revenue_for_Affordability.loan_type)){
	
		  return true;
		}
	
  }
}
return false;
}





function double()
{
var revenues = document.total_revenue_for_Affordability.revenues.options[document.total_revenue_for_Affordability.revenues.selectedIndex].value;
var strfield11 = document.total_revenue_for_Affordability.hp_annual_sal.value;
var strfield12 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value;


  if(revenues == "Double" && (strfield11 == "" || strfield11 == null || strfield12 == "" || strfield12 == null)) 
 
 { 
alert("PLEASE ENTER ALL DETAILS FOR HouseHold Partner Revenues:")   
 return false;
}
	return true;
}





function enable_text(status)
{
status=!status; 
document.total_revenue_for_Affordability.one.readonly="YES" = status;
}

function enable_text2(status)
{
status=!status; 
document.total_revenue_for_Affordability.pone.readonly="YES" = status;
}
// <![CDATA[
function display(obj,id1,id2,id3,id4) {
txt = obj.options[obj.selectedIndex].value;
document.getElementById(id1).style.display = 'none';
document.getElementById(id2).style.display = 'none';
document.getElementById(id3).style.display = 'none';
document.getElementById(id4).style.display = 'none';

	
		
if ( txt.match(id1) ) {
document.getElementById(id1).style.display = 'block';
}
if ( txt.match(id2) ) {
document.getElementById(id2).style.display = 'block';
}
if ( txt.match(id3) ) {
document.getElementById(id3).style.display = 'block';
}
if ( txt.match(id4) ) {
document.getElementById(id4).style.display = 'block';
}

}


function displaya(obj,a,b,c) {
txt = obj.options[obj.selectedIndex].value;
document.getElementById(a).style.display = 'none';
document.getElementById(b).style.display = 'none';
document.getElementById(c).style.display = 'none';
if ( txt.match(a) ) {
document.getElementById(a).style.display = 'block';
document.getElementById(b).style.display = 'block';
document.getElementById(c).style.display = 'block';
}
}


function setReadOnly(obj)
{
if (obj.options[obj.selectedIndex].value == "Single" )
{
document.total_revenue_for_Affordability.day2.readOnly = 1;
document.total_revenue_for_Affordability.month2.readOnly = 1;
document.total_revenue_for_Affordability.year2.readOnly = 1;
} 
}

</script>

</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php
function calc_payment($pv, $payno, $int, $accuracy) 
{ 
// check that required values have been supplied 
if (empty($pv)) { 
   echo "<p class='error'>a value for PRINCIPAL is required</p>"; 
   exit; 
} // if 
if (empty($payno)) { 
   echo "<p class='error'>a value for NUMBER of PAYMENTS is required</p>"; 
   exit; 
} // if 
if (empty($int)) { 
   echo "<p class='error'>a value for INTEREST RATE is required</p>"; 
   exit; 
} // if 

// now do the calculation using this formula: 

//****************************************** 
//            INT * ((1 + INT) ** PAYNO) 
// PMT = PV * -------------------------- 
//             ((1 + INT) ** PAYNO) - 1 
//****************************************** 

$int    = $int / 100;    // convert to a percentage 
$value1 = $int * pow((1 + $int), $payno); 
$value2 = pow((1 + $int), $payno) - 1; 
$pmt    = $pv * ($value1 / $value2); 
// $accuracy specifies the number of decimal places required in the result 
$pmt    = number_format($pmt, $accuracy, ".", ""); 

return $pmt; 

} // calc_payment ==================================================================== 


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


$pass="sefalana2008";
$user="root";
//$password=$_POST['password'];
$host="localhost"; // Host name 
//$username="root"; // Mysql username 
//$password="sefalana2008"; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$omang_passport_num=$_POST['omang'];
$loan_number=$_POST['loan_number'];
$loan_number=$_POST['loan_number'];
//echo  $omang_passport_num;

$connect=mysql_connect($host,$user,$pass); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());
}


$selectO="SELECT omang_passport_num FROM cust_information where omang_passport_num='$omang_passport_num' and loan_number=$loan_number";
$result1=(mysql_query($selectO));
if($result1)
{
//echo "omand done";
}

$mycounts=0;
while($rows1=mysql_fetch_array($result1)){
$mycounts++; 
//echo "the omang is ".$rows1['omang_passport_num'];
//echo "di kana ".$mycounts;
}
if ($mycounts<1)
{

echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed1.php\">";
die();
}
else
{





$insert="SELECT * FROM cust_information where omang_passport_num='$omang_passport_num' AND loan_number=$loan_number";
$result=(mysql_query($insert));
if($result)
{
//echo "done";
}

$mycount=0;
while($rows=mysql_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>


<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" >
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>


</tr>
<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING</font></td><tr>
      </tr>
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text"> 
          <td>
        <p></br> 
        <form ACTION="detailscore.php" name="total_revenue_for_Affordability" method="POST"  onClick="return displ();" onsubmit="return check(this);">
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">
<tr>
                <td><strong><font color="#FF0000">**</font></strong> <em><font color="#FF9900" size="1">System 
                  Generated Data</font></em></td>
              </tr>
 <tr>
          <td><strong><font color="#000000">Omang/Passport</font></strong></td>
          <td></td><td>
<input type="text" name="one" value="<?php echo $rows['omang_passport_num']; ?>" readonly="YES"></td> </tr>
 <tr>
          <td><font color="#000000">Loan Application Number</font></td>
          <td></td><td>
<input type="text" name="loan_number" value="<?php echo $loan_number; ?>" readonly="YES"></td> </tr>

<tr><td>Loan Type</td><td width="60">&nbsp;</td><td>
<SELECT NAME="loan_type" onchange="displaya(this,'77', '78', '79');" readonly="YES">
<option value="<?php echo $rows['loan_type'];?>"><?php
 if ($rows['loan_type']=="77")
{
echo "Further Advance";
}
else{
echo $rows['loan_type']; 
 }?> </option>
<option value="Standard to Batswana">Standard to Batswana </option>
<option value="Guaranteed Scheme">Guaranteed Scheme</option>
<option value="Non-Guaranteed Scheme">Non-Guaranteed Scheme</option>
<option value="Standard to Non-Citizens">Standard to Non-Citizens </option>
<option value="Second Time Home Owner">Second Time Home Owner</option>
<option value="77">Further Advance</option>
</SELECT>
</td></tr>

</thead>
<tfoot>

</tfoot>
<tbody id="77" style="display: none;">
<tr>
            <td class="title"><strong><em>Original Loan Type:</em></strong></td>
<td class="field"><SELECT NAME="o_loan_type" readonly="YES">
<option value="">"<?php echo $rows['loan_type']; ?> </option>
<option value="Fixed">Fixed </option>
<option value="Variable">Variable</option>
</SELECT></td>
</tr>
</tbody>
<tbody id="78" style="display: none;">
<tr>
            <td class="title"><strong><em>Principal Outsanding in Original Loan:</em></strong></td>
<td class="field"><input name="o_bal" type="text" onchange ="return new_loan()"></td>
</tr>
</tbody>
<tbody id="79" style="display: none;">
<tr>
            <td class="title"><strong><em>New FA Requested:</em></strong></td>
<td class="field"><input name="newloan" type="text" onchange="return new_loan()"></td>
</tr>
</tbody>
<tr><td>Loan Amount Rquested :</td><td width="60">&nbsp;</td><td>
<input name="loan_amount" type="text" onchange="return instal()" onFocus="this.blur" value="<?php echo $rows['loan_amount_requested']; ?>" readonly="YES">
</td></tr>

<tr><td>Property Type:</td><td width="60">&nbsp;</td><td><SELECT NAME="property_type"  readonly="YES">
<option value=""><?php echo $rows['property_type']; ?></option>
<option value="Residential">Residential </option>
<option value="Commercial">Commercial</option>
<option value="Thatched_Residential">Thatched_Residential </option>
<option value="Thatched_Commercial">Thatched_Commercial</option>
</SELECT>
</td></tr>

<tr><td>Open Market Value:</td><td width="60">&nbsp;</td><td><input name="open_market_value" type="text" value="<?php echo $rows['open_market_value']; ?>">
</td></tr>


<tr><td>Loan Maturity Requested</td><td width="60"></td><td><SELECT NAME="loan_maturity" onchange="return instal()" readonly="YES">
<option value="<?php echo $rows['loan_maturity_requested']; ?>"><?php echo $rows['loan_maturity_requested']; ?></option>
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
</SELECT>
</td>
<tr><td>Rate Type Requested:</td><td width="60">&nbsp;</td><td><SELECT NAME="rate_type" readonly="YES">
<option value="<?php echo $rows['rate_type_requested']; ?>"><?php echo $rows['rate_type_requested']; ?></option>
<option value="Fixed">Fixed </option>
<option value="Floating">Floating</option>
<option value="Variable">Variable</option>
</SELECT></td>
<tr><td>Estimated Loan Current (Offered) Interest Rate pa</td><td width="60">&nbsp;</td><td>
<input name="irate" readonly="YES" type="text" onchange="return instal()" value="<?php echo $rows['current_interest_rate']; ?>">
</td></tr>
 
<tr><td>Insurance Replacement Cost</td><td width="60">&nbsp;</td><td>
<input name="insurance_replacement" type="text" value="<?php echo $rows['insurance_replacement_cost']; ?>" readonly="YES">
</td></tr>


<tr>
                <td>Estimated Insurance Premium <strong><font color="#FF0000">**</font></strong></td>
                <td width="60">&nbsp;</td><td>
<input name="insurance_premium" type="text"  readonly="true" onFocus="premium()" value="<?php echo $rows['estimated_insurance_premium']; ?>" readonly="YES">
</td></tr>

<tr><td>Estimated Instalment of the requested Loan <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="loan_installment" type="text"  readonly="true" onFocus="this.blur();" value="<?php echo $rows['estimated_installment']; ?>" readonly="YES">
</td></tr>

<tr><td>Estimated Instalment + Insurance Premium  <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="loanandinsurance" type="text"  readonly="true" onClick="mortinstal()" value="<?php echo $rows['estimated_installment_insurance']; ?>" readonly="YES">
</td></tr>


<tr><td>Loan To Value Policy <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="ltv_policy" type="text" readonly="true" onClick="myltvpolicy()" value="<?php echo $rows['loan_to_value_policy']; ?>" readonly="YES">
</td></tr>

<tr><td>Loan To Value <strong><font color="#FF0000">**</font></strong> </td><td width="60">&nbsp;</td><td>
<input name="ltv" type="text" onClick="loantoval()" readonly="true" value="<?php echo $rows['loan_to_value']; ?>" readonly="YES">
</td></tr>

<tr>
        <td><strong><font color="#000066">Professional Revenues (Gross 
                  amounts) Borrower:</font></strong> </td>
                <td width="60"></td><td></td></tr>

<tr><td>Annual Salary Amount (Gross): </td><td width="60"></td><td><input type="text" name="annual_sal" onchange="return calcTotal()" value="<?php echo $rows['annual_salary']; ?>" readonly="YES"></td></tr>

<tr><td>Other Income:</td><td width="60"></td><td> <input type="text" name="fixed_perm_allowances" onchange="return calcTotal()" value="<?php echo $rows['fixed_permanent_allowances']; ?>" readonly="YES"></td></tr>

<tr><td>Tax </td><td width="60"></td><td> <input type="text" name="tax" onchange="return calcTotal()" value="<?php echo $rows['tax']; ?>"></td></tr>

<tr><td>Total Revenues for affordability: <strong><font color="#FF0000">**</font></strong> </td><td width="60"></td><td><input type="text" name="total_rev_for_afford" onFocus="this.blur();" value="<?php echo $rows['total_revenue_for_affordability']; ?>"></td></tr>

<tr>
                <td><font color="#000066"><strong>HouseHold Partner Revenues:</strong></font></td>
                <td width="60"></td><td> </td></tr>

<tr><td>Household Partner- Annual Salary Amount (Gross): </td><td width="60"></td><td><input type="text" name="hp_annual_sal" onchange="return calcTotal()" value="<?php echo $rows['partner_annual_salary']; ?>" readonly="YES"></td></tr>

<tr><td>Household Partner- Other Income:</td><td width="60"></td><td> <input type="text" name="hp_fixed_perm_allowances" onchange="return calcTotal()" value="<?php echo $rows['partner_permanent_allowances']; ?>" readonly="YES"></td></tr>

<tr><td>Household Partner- Tax :</td><td width="60"></td><td> <input type="text" name="tax2" onchange="return calcTotal()" value="<?php echo $rows['tax2']; ?>"></td></tr>

<tr><td>Household Partner- Total Revenues for affordability: <strong><font color="#FF0000">**</font></strong> </td><td width="60"></td><td><input type="text" name="hp_total_rev_for_afford" onFocus="this.blur();" value="<?php echo $rows['partner_revenue_for_affordability']; ?>" readonly="YES"></td></tr>

<tr><td>TOTAL Allowable Household Revenues: <strong><font color="#FF0000">**</font></strong> </td><td width="10"></td><td><input type="text" name="total_allowable_household_revenues" value="<?php echo $rows['total_allowable_household_revenues']; ?>" readonly="YES"></td></tr>



<tr><td>Total revenues for affordability <strong><font color="#FF0000">**</font></strong> </td><td width="60">&nbsp;</td><td>
<input name="rev4afford"  readonly="true" type="text" value="<?php echo $rows['total_revenue_for_affordability'];?>" >
</td></tr>


<tr><td>Affordability Policy</td><td width="60">&nbsp;</td><td>
<SELECT NAME="affordability_policy" readonly="YES">
<!--<option value="35">35%</option>-->
<option value="40">40%</option>
<!--<option value="45">45%</option>-->
<!--<option value="50">50%</option> </select>-->
</select>
</td></tr>

<tr><td>Affordability Ratio <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="affordability_ratio" type="text" onClick="Affordability_Ratio()"  readonly="YES" value="<?php echo $rows['affordability_ratio']; ?>">
</td></tr>

<tr><td></td><td width="60">&nbsp;</td><td></td></tr>
<tr><td></td><td width="60">&nbsp;</td><td></td></tr>

<tr><td>Customer Type:</td><td width="60">&nbsp;</td><td><SELECT NAME="customer_type" readonly="YES">
<option value="<?php echo $rows['cutomer_type']; ?>"><?php echo $rows['cutomer_type']; ?> </option>
<option value="Legal Entity">Legal Entity </option>
<option value="Physical">Physical</option>
</SELECT></td>
</tr>
<tr><td>Borrower Name :</td><td width="60">&nbsp;</td><td>
<input name="name" type="text" value="<?php echo $rows['borrower_name']; ?>" readonly="YES">
</td></tr>
<tr><td>Nationality:</td><td width="60">&nbsp;</td><td>
  <SELECT NAME="nationality" readonly="YES">
 <option value="<?php echo $rows['nationality']; ?>"><?php echo $rows['nationality']; ?></option> 
<option value="Motswana">Motswana </option>
<option value="Foreigner">Foreigner </option>
</SELECT>
</td>
</tr>







<tr><td>Other NAmes :</td><td width="60">&nbsp;</td><td>
<input name="other_name" type="text" value="<?php echo $rows['other_names']; ?>" readonly="YES">
</td></tr>

<tr><td>Surname :</td><td width="60">&nbsp;</td><td>
<input name="surname" type="text" value="<?php echo $rows['surname']; ?>" readonly="YES">
</td></tr>

<tr><td>Spouse,Partner,Co-Borrower Name :</td><td width="60">&nbsp;</td><td>
<input name="spouse_name" type="text" value="<?php echo $rows['spouse_partner_co_borrower_name']; ?>" readonly="YES">
</td></tr>

<tr><td>Spouse Surname:</td><td width="60">&nbsp;</td><td>
<input name="spouse_surname" type="text" value=" <?php echo $rows['spouse_surname']; ?>" readonly="YES">
</td></tr>

<tr><td>Borrower Present Address :</td><td width="60">&nbsp;</td><td>
<input name="address" type="text" value="<?php echo $rows['borrower_present_address']; ?>" readonly="YES">
</td></tr>

<tr><td>Street Name and Number :</td><td width="60">&nbsp;</td><td>
<input name="street_add" type="text" value="<?php echo $rows['Street_name_and_number']; ?>" readonly="YES">
</td></tr><?php
$con = mysql_connect("localhost","root","sefalana2008");
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }
  mysql_select_db("creditscoring", $con);
?>
<tr><td>Town:</td><td width="60">&nbsp;</td>
<td>

<input readonly="YES" type="text" value="<?php echo $rows['town']; ?>" readonly="YES" /></td>

</tr> &nbsp;&nbsp;&nbsp;
<tr><td>Country:</td><td width="60">&nbsp;</td><td>
<input name="country" type="text" value="<?php echo $rows['country']; ?>" readonly="YES">
</td></tr>

<tr><td>Permanent Country of Residence :</td><td width="60">&nbsp;</td><td><SELECT name="permanent_residence" readonly="YES">
<option value="<?php echo $rows['permanent_country_of_residence']; ?>"><?php echo $rows['permanent_country_of_residence']; ?></option>
<option value="Botswana">Botswana </option>
<option value="Other">Other </option>
</SELECT>

</td>
</tr>
</br>
</br>
    





<tr><td>Location of Acquired REal Estate:</td><td width="60">&nbsp;</td><td> <SELECT NAME="location" readonly="YES">
<option value="<?php echo $rows['location_of_acquired_real_estate']; ?>"><?php echo $rows['location_of_acquired_real_estate']; ?></option>
<option value="Major Urban">Major Urban </option>
<option value="Minor Urban">Minor Urban </option>
<option value="Rural">Rural </option>
</SELECT>
</td>

</tr>

</td></tr>
<tr><td>Marital Status</td><td width="60"></td><td> <SELECT NAME="marital_status" onclick="setReadOnly(this, 'Single')"  readonly="YES"> 
<option value="<?php echo $rows['marital_status']; ?>"><?php echo $rows['marital_status']; ?></option>
<option value="Single">Single</option>
<option value="Married">Married </option>
<option value="Divorced">Divorced </option>
<option value="Widowed">Widowed </option>
<option value="Other">Other</option>
</SELECT> </td>

</tr>

<tr><td>Borrower Birthdate</td><td width="60">&nbsp;</td>
           
<?php
$year = $rows['borrower_birth'];
$y = date('Y',strtotime($year));
$m = date('M',strtotime($year)); 
echo  $m." month ";






?>
<td><input readonly="YES" type="text" value="<?php echo $rows['borrower_birth']; ?>" /></td>
</tr>

 <tr><td>
Spouse BirthDate: ;</td><td width="60">&nbsp;</td>
            
<td><input readonly="YES" type="text" value="<?php echo $rows['spouse_birth']; ?>" ></td>

</tr>
<tr><td>Wedding Date</td><td width="60">&nbsp;</td>
           

<td><input readonly="YES" type="text" value="<?php echo $rows['wedding']; ?>" ></td>
</tr>

 <tr><td>
Spouse BirthDate: ;</td><td width="60">&nbsp;</td>
            
<td><input readonly="YES" type="text" value="<?php echo $rows['divorce']; ?>" ></td>

</tr>
<tr><td>Marital Contract Type</td><td width="60"></td><td> <SELECT NAME="marital_contract_type" readonly="YES">
<option value=""></option><?php echo $rows['marital_contract_type']; ?></option>
<option value="Community of Property">Community of Property</option>
<option value="Other marital Contract">Other marital Contract </option>
<option value="na">na </option>
</SELECT></td>
</tr>

<tr><td>Spouse/Partner Borrowing Relationship</td><td width="60"></td><td> <SELECT NAME="spouse_borrowing_relationship" readonly="YES">
<option value="<?php echo $rows['spouse_borrowing_relationship']; ?>"><?php echo $rows['spouse_borrowing_relationship']; ?></option>
<option value="Co-borrower/Guanrator">Co-borrower/Guanrator</option>
<option value="Other">Other </option>
<option value="na">na</option>

</SELECT></td>
<td></td>
</tr>

<!--<tr><td>Number of Dependats </td><td width="60"></td><td><SELECT NAME="number_of_dependants">
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
</td></tr>--> 

<tr>
   <td><font color="#000066"><strong>Total Number of Children:<strong><font color="#FF0000">**</font></strong></strong></font></td>
                <td width="60"></td><td> <input type="text" name="no_of_children" value=0 onFocus="this.blur();" readonly="true" readonly="YES"> </td></tr>

<tr><td>Children living at home 0 to 12 years of age
 </td><td width="60"></td><td><SELECT NAME="to12" onChange="return total_kids()" readonly="YES">
<option value="0"><?php echo $rows['children0_to12']; ?></option>
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
</SELECT></td>
</tr>
</br>
</br>
<tr><td>Children living at home 13 to 18 years of age</td><td width="60"></td><td><SELECT NAME="to18" onChange="return total_kids()" readonly="YES">
<option value="0"><?php echo $rows['childred13_to18']; ?></option>
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
</br>
</td>
</tr>
</br>
<tr><td>Children living at home 19 to 26 years of age</td><td width="60"></td><td><SELECT NAME="to26" onChange="return total_kids()" readonly="YES">
<option value="0"><?php echo $rows['children19_to26']; ?></option>
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
</SELECT></td>
</tr>



<tr><td>Other dependants Living in Household GrandParents</td><td width="60"></td><td><SELECT NAME="grandparents" onChange="return total_dependents()" readonly="YES">
<option value="0"><?php echo $rows['other_dependents_grandparents']; ?></option>
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
</SELECT></td>

</tr>

<tr><td>
Other dependants Living in Household Aunts/Uncles/Cousins</td><td width="60"></td><td><SELECT NAME="aunts_uncles_cousins" onChange="return total_dependents()" readonly="YES">
<option value="<?php echo $rows['other_dependents_aunts']; ?>"><?php echo $rows['other_dependents_aunts']; ?></option>
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
</br>
</br>
</td>

</tr>
<tr><td>Other dependants Living in Household</td><td width="60"></td><td><SELECT NAME="other" onChange="return total_dependents()" readonly="YES">
<option value="<?php echo $rows['other_dependents']; ?>"><?php echo $rows['other_dependents']; ?></option>
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
</SELECT></td>

</tr>

<tr><td><font color="#000066"><strong>TOTAL</strong></font> Dependants 
Living at home:<strong><font color="#FF0000">**</font></strong></td>
 <td width="60"></td><td> <input readonly="YES" type="text" name="dependants_at_home" value=0 onFocus="this.blur();"  > </td></tr>


<tr><td>Years At Present Address:</td><td width="60"></td><td> <SELECT NAME="years_at_address" readonly="YES">
<option value="<?php echo $rows['years_at_present_address']; ?>"><?php echo $rows['years_at_present_address']; ?></option>
<option value="0 to 3">0 to 3</option>
<option value="3 to 5">3 to 5</option>
<option value="5 to 10">5 to 10</option>
<option value="+10">+10</option>
</SELECT></td>

</tr>

<tr><td>Monthly payment (rental/instalment):</td><td width="60"></td><td> <input type="text" name="rent" value="<?php echo $rows['monthly_payment']; ?>" readonly="YES"> </td></tr>



<tr><td>Borrower Education:</td><td width="60"></td><td> <SELECT NAME="education" readonly="YES">
<option value="<?php echo $rows['borrower_education']; ?>"><?php echo $rows['borrower_education']; ?></option>
<option value="University">University </option>
<option value="Technical School">Technical School</option>
<option value="Secondary School">Secondary School</option>
<option value="Primary Education">Primary Education</option>
<option value="None">None</option>
</SELECT></td>

</tr>

<tr><td>Professional Category:</td><td width="60"></td><td> <SELECT NAME="Professional" readonly="YES">
<option value="<?php echo $rows['professional_category']; ?>"> <?php echo $rows['professional_category']; ?></option>
<option value="Self Employed">Self Employed</option>
<option value="Civil Servant">Civil Servant</option>
<option value="Employee">Employee</option>
<option value="Other">Other</option>
</SELECT>
</td>
</tr>

<tr><td>Employment Contract:</td><td width="60"></td><td> <SELECT NAME="employment" readonly="YES">
<option value="<?php echo $rows['employment_contract'];?>"> <?php echo $rows['employment_contract']; ?></option>
<option value="Permanent">Permanent</option>
<option value="Contractual">Contractual</option>
<option value="Other">Other</option>
</SELECT></td>

</tr>

<tr><td>Number Of Years Working at Present Employer:</td><td width="60"></td><td> <SELECT NAME="years_at_job" readonly="YES">
<option value="<?php echo $rows['number_of_years_at_employer']; ?>"><?php echo $rows['number_of_years_at_employer']; ?></option>
<option value="0 to 1">0 to 1</option>
<option value="1 to 5">1 to 5</option>
<option value="5 to 20">5 to 20</option>
<option value="20 and more">20 and more</option>
</SELECT></td>
</tr>

<tr><td>Household Professional Revenues:</td><td width="60"></td><td> <SELECT NAME="revenues" onchange="return double()" readonly="YES">
<option value="<?php echo $rows['household_proffessional_revenue']; ?>"><?php echo $rows['household_proffessional_revenue']; ?></option>
<option value="Single">Single</option>
<option value="Double">Double</option>
</SELECT></td>
</tr>


<tr><td>Main Bank:</td><td width="10"></td><td> <SELECT NAME="mainbank" readonly="YES">
<option value=""><?php echo $rows['main_bank']; ?></option>
<option value="BBS">BBS</option>
<option value="BSB">BSB</option>
<option value="NDB">NDB</option>
<option value="FNBB">FNBB</option>
<option value="S&C">S&C </option>
<option value="Barclays">Barclays</option>
<option value="Stanbic">Stanbic</option>
<option value="Bank Gaborone">Bank Gaborone</option>
<option value="Bank A">Bank A</option>
<option value="Bank B">Bank B</option>
<option value="Bank C">Bank C</option>
<option value="Bank D">Bank D</option>
<option value="Bank E">Bank E</option>
<option value="NA">Not Applicable</option>

</SELECT></td>

</tr>


<tr><td>Second Bank: </td><td width="10"></td><td><SELECT NAME="secondbank" readonly="YES">
<option value="NA"><?php echo $rows['second_bank']; ?></option>
<option value="BBS">BBS</option>
<option value="BSB">BSB</option>
<option value="NDB">NDB</option>
<option value="FNBB">FNBB</option>
<option value="S&C">S&C </option>
<option value="Barclays">Barclays</option>
<option value="Stanbic">Stanbic</option>
<option value="Bank Gaborone">Bank Gaborone</option>
<option value="Bank A">Bank A</option>
<option value="Bank B">Bank B</option>
<option value="Bank C">Bank C</option>
<option value="Bank D">Bank D</option>
<option value="Bank E">Bank E</option>
</SELECT></td>

</tr>

<tr><td>Third Bank: </td><td width="10"></td><td><SELECT NAME="thirdbank" readonly="YES">
<option value="NA"><?php echo $rows['third_bank']; ?></option>
<option value="BBS">BBS</option>
<option value="BSB">BSB</option>
<option value="NDB">NDB</option>
<option value="FNBB">FNBB</option>
<option value="S&C">S&C </option>
<option value="Barclays">Barclays</option>
<option value="Stanbic">Stanbic</option>
<option value="Bank Gaborone">Bank Gaborone</option>
<option value="Bank A">Bank A</option>
<option value="Bank B">Bank B</option>
<option value="Bank C">Bank C</option>
<option value="Bank D">Bank D</option>
<option value="Bank E">Bank E</option>

</SELECT></td>
</tr>

<tr><td>Age Of Relationshp with BBS:</td><td width="10"></td><td> <SELECT NAME="relationship" readonly="YES">
<option value="<?php echo $rows['age_of_relationship']; ?>"><?php echo $rows['age_of_relationship']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>


<tr><td>Savings Account:</td><td width="10"></td><td> <SELECT NAME="Savings" readonly="YES">
<option value="NA"><?php echo $rows['savings_Account']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>

<tr><td>Deposit Account: </td><td width="10"></td><td><SELECT NAME="Deposit" readonly="YES">
<option value="<?php echo $rows['deposit_Account']; ?>"><?php echo $rows['deposit_Account']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>

<tr><td>Share Account: </td><td width="10"></td><td><SELECT NAME="Share" readonly="YES">
<option value="<?php echo $rows['share_Account']; ?>"><?php echo $rows['share_Account']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>

<tr><td>ST Loans: </td><td width="10"></td><td><SELECT NAME="ST" readonly="YES">
<option value="<?php echo $rows['ST_Loans']; ?>"><?php echo $rows['ST_Loans']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>

<tr><td>Mortgages: </td><td width="10"></td><td><SELECT NAME="Mortgages" readonly="YES">
<option value="<?php echo $rows['mortgages']; ?>"><?php echo $rows['mortgages']; ?></option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td>
</tr>


<tr><td>TOTAL BBS Products: <strong><font color="#FF0000">**</font></strong> </td><td width="10"></td><td><input type="text" name="total_bbs_products" value="<?php echo $rows['total_bbs_products']; ?>" readonly="YES"></td></tr>

<tr><td >BBS arrears for over 30days in last 12mnths?</br> 
</td><td width="10"></td><td><SELECT NAME="loan_arrears" readonly="YES">
<option value="<?php echo $rows['bbs_arreas_12months']; ?>"><?php echo $rows['bbs_arreas_12months']; ?></option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT></td>
</tr>


<tr><td >REnegotiated loans with arreas in past 24 months?</br> 
</td><td width="10"></td><td><SELECT NAME="renegotiate" readonly="YES">
<option value="<?php echo $rows['renegotiated']; ?>"><?php echo $rows['renegotiated']; ?></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT></td>
</tr>


<tr><td>Why was this needed ?
</td><td width="10"></td><td><SELECT NAME="why_renogotiation" readonly="YES">
<option value="<?php echo $rows['why_renegotiated']; ?>"><?php echo $rows['why_renegotiated']; ?></option>
<option value="0">Unexpected expenses</option>
<option value="Increased instalments">Increased instalments</option>
<option value="Reduced revenues">Reduced revenues</option>
<option value="other">other</option>
</SELECT>
</td>

</tr>


<tr><td>Number of credit card personally held</td><td width="10"></td><td>
<SELECT NAME="cards_held" readonly="YES">
<option value="<?php echo $rows['no_of_credit_card']; ?>"><?php echo $rows['no_of_credit_card']; ?></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT>
</td>
</tr>



<tr><td>Card held since (in years) </td><td width="10"></td><td><SELECT NAME="card_held_since" readonly="YES">
<option value="<?php echo $rows['card_held_since']; ?>"><?php echo $rows['card_held_since']; ?></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">greater than 5</option>
</SELECT>
</td>
</tr>

<tr><td>Number of loans presently outstanding </td><td width="10"></td><td><SELECT NAME="loans_outstanding" onchange="display(this,'1','2','3','4');" readonly="YES">
<option value="<?php echo $rows['number_of_loans_outstanding']; ?>"><?php echo $rows['number_of_loans_outstanding']; ?></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT>
</td>
</tr>

<tr>

</thead>
<tfoot>

</tfoot>
<tbody id="1" style="display: none;">
<tr>
<td class="title">loan installment:</td>
<td class="field"><input type="text" name="installment1" size="8" maxlength="7" readonly="YES"/></td>
</tr>
</tbody>
<tbody id="2" style="display: none;">

<tr>
<td class="title">loan 1 installment:</td>
<td class="field"><input type="text" name="ainstallment1" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 2 installment:</td>
<td class="field"><input type="text" name="ainstallment2" size="8" readonly="YES"/></td>
</tr>

</tbody>

<tbody id="3" style="display: none;">

<tr>
<td class="title">loan 1 installment:</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment1" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 2 installment 2:</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment2" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 3 installment :</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="binstallment3" size="8" maxlength="7" readonly="YES"/></td>
</tr>
</tbody>
<tbody id="4" style="display: none;">

<tr>
<td class="title">loan 1 installment:</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment1" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 2 installment:</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment2" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 3 installment</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment3" size="8" readonly="YES"/></td>
</tr>
<tr>
<td class="title">loan 4 installment:</td>
<td class="field">&nbsp;&nbsp;&nbsp;<input type="text" name="cinstallment4" size="8" maxlength="7" readonly="YES"/></td>
</tr>
</tbody>






<tr><td>ITC REF NP.</td><td width="60">&nbsp;</td><td><input type="text" name="itc_ref" value="<?php echo $rows['ITC_REF']; ?>" readonly="YES"></td></tr>
<tr><td>ITC REPORT -(Paid Debt) </td><td width="10"></td><td><SELECT NAME="paid_debts" readonly="YES">
<option value="<?php echo $rows['paid_debt']; ?>"><?php echo $rows['paid_debt']; ?></option>
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
</td>
</tr>

<tr><td>ITC REPORT: Judgement </td><td width="10"></td><td><SELECT NAME="judgement" readonly="YES">
<option value="<?php echo $rows['judgment']; ?>"><?php echo $rows['judgment']; ?></option>
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
</td>
</tr>

<tr><td>ITC REPORT: Default Data </td><td width="10"></td><td><SELECT NAME="default_data" readonly="YES">
<option value="<?php echo $rows['default_data']; ?>"><?php echo $rows['default_data']; ?></option>
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
</td>
</tr>

<tr><td>ITC REPORT: Trace Alerts  </td><td width="10"></td><td><SELECT NAME="trace_alerts" readonly="YES">
<option value="<?php echo $rows['trace_alerts']; ?>"><?php echo $rows['trace_alerts']; ?></option>
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
</td>

</tr>
<tr><td>BlackList Flag  </td><td width="10"></td><td><SELECT NAME="blacklisted" readonly="YES">
<option value="<?php echo $rows['blacklist_flag']; ?>"><?php echo $rows['blacklist_flag']; ?></option>
<option value="No">No</option>
<option value="Yes">Yes</option>
</SELECT>
</td>
</tr>

<tr><td>Fraud Alerts  </td><td width="10"></td><td><SELECT NAME="fraud_alert" readonly="YES">
<option value="<?php echo $rows['fraud_alerts']; ?>"><?php echo $rows['fraud_alerts']; ?></option>
<option value="No">No</option>
<option value="Yes">Yes</option>
</SELECT>
</td>
</tr>


<tr><td>Deduction from source? </td><td width="10"></td><td><SELECT NAME="deduct" readonly="YES">
<option value="<?php echo $rows['deduct']; ?>"><?php echo $rows['deduct']; ?></option>
<option value="No">No</option>
<option value="Yes">Yes</option>
</SELECT></td>


</tr>
<tr><td>
<input type="hidden" name="debtserviceratio"  value="<?php echo $rows['debtserviceratio']; ?>"/> 

<input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/>
<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
<input readonly="YES" type="hidden" value="<?php echo $y;?>" name="year" />
<input readonly="YES" type="hidden" value="<?php echo $m;?>" name="month" />
<input type="SUBMIT" name="RATE"  class="btn" value="Submit"/> 
</td></tr>


</form>


<?php
}
}
?>

</table>
   
 </table> 
</table>




</BODY>
</HTML>