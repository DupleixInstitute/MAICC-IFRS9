
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
var strfield1 = document.total_revenue_for_Affordability.loan_amount.value;
var strfield2 = document.total_revenue_for_Affordability.property_type.value;
var strfield3 = document.total_revenue_for_Affordability.loan_type.value;

  //name field
    if (strfield1 == "" || strfield1 == null )
    {
    alert("Please enter value for the loan amount requested.")
    return false;
    }

  //url field 
    if (strfield2 == "" || strfield2 == null )
    {
    alert("Please enter value for the roperty type.")
    return false;
    }

  //title field 
    if (strfield3 == "" || strfield3 == null )
    {
    alert("Please enter value for the Loan type.")
    return false;
    }
    return true;
}

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

function isEmpty(strfield1, strfield2, strfield3, strfield4,strfield5, strfield6, strfield7, strfield8, strfield9, strfield10, strfield11, strfield12, strfield13, strfield14,strfield15, strfield16, strfield17, strfield18, strfield19,strfield20, strfield21, strfield22, strfield23, strfield24, strfield25, strfield26)
   {
//variables names
var strfield1 = document.total_revenue_for_Affordability.loan_amount.value;
var strfield2 = document.total_revenue_for_Affordability.property_type.value;
var strfield3 = document.total_revenue_for_Affordability.open_market_value.value;
var strfield4 = document.total_revenue_for_Affordability.loan_type.value;
var strfield5 = document.total_revenue_for_Affordability.loan_maturity.value;
var strfield6 = document.total_revenue_for_Affordability.rate_type.value;
var strfield7 = document.total_revenue_for_Affordability.irate.value;
var strfield8 = document.total_revenue_for_Affordability.insurance_replacement.value;
var strfield9 = document.total_revenue_for_Affordability.annual_sal.value;
var strfield10 = document.total_revenue_for_Affordability.fixed_perm_allowances.value;
var strfield11 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value;
var strfield12 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value;
var strfield13 = document.total_revenue_for_Affordability.affordability_policy.value;
var strfield14 = document.total_revenue_for_Affordability.customer_type.value;
var strfield15 = document.total_revenue_for_Affordability.nationality.value;
var strfield16 = document.total_revenue_for_Affordability.name.value;
var strfield17 = document.total_revenue_for_Affordability.other_name.value;
var strfield18 = document.total_revenue_for_Affordability.surname.value;
var strfield19 = document.total_revenue_for_Affordability.spouse_name.value;
var strfield20 = document.total_revenue_for_Affordability.spouse_surname.value;
var strfield21 = document.total_revenue_for_Affordability.address.value;
var strfield22 = document.total_revenue_for_Affordability.street_add.value;
var strfield23 = document.total_revenue_for_Affordability.town.value;
var strfield24 = document.total_revenue_for_Affordability.country.value;
var strfield25 = document.total_revenue_for_Affordability.permanent_residence.value;
var strfield26 = document.total_revenue_for_Affordability.location.value;


  //name field
 
if(strfield1 == "" || strfield1 == null)
{ 
alert("Please enter value for_loan amount requested.")
return false;
}
if(strfield2 == "" || strfield2 == null)
{
alert("Please enter value for_Property type.") 
return false;
}
if(strfield3 == "" || strfield3 == null)
{
alert("Please enter value for_Open Market Value." )
return false;
 }
if(strfield4 == "" || strfield4 == null)
{
 alert("Please enter value for_Loan Type." )
return false;
}
if(strfield5 == "" || strfield5 == null)
{
 alert("Please enter value for_Loan Maturity Requested.")
  return false;
}
if(strfield6 == "" || strfield6 == null)
{ 
alert("Please enter value for_Rate Type Requested.")  
  return false;
}
if(strfield7 == "" || strfield7 == null)
{ 
alert("Please enter value for_Estimated Loan Current (Offered) Interest Rate pa.") 
   return false;
}
if(strfield8 == "" || strfield8 == null)
{ 
alert("Please enter value for_Insurance Replacement Cost.") 
   return false;
}
if(strfield9 == "" || strfield9 == null)
{ 
alert("Please enter value for_Annual Salary Amount (Gross)." )
   return false;
}
if(strfield10 == "" || strfield10 == null)
{ 
alert("Please enter value for_Fixed Permanent allowances (pa).") 
   return false;
}
//if(strfield11 == "" || strfield11 == null)
//{ 
//alert("Please enter value for_Household Partner- Annual Salary Amount (Gross),enter 0 if not available.")   
// return false;
//}
//if(strfield12 == "" || strfield12 == null)
//{ 
//alert("Please enter value for_Household Partner- Fixed Permanent allowances (pa) enter 0 if not available..")
//    return false;
//}
if(strfield13 == "" || strfield13 == null)
{ 
alert("Please enter value for_Affordability Policy.")  
 return false;
}
if(strfield14 == "" || strfield14 == null)
{ 
alert("Please enter value for_Customer Type.")   
 return false;
}
if(strfield15 == "" || strfield15 == null)
{
 alert("Please enter value for_Nationality.")   
 return false;
}
if(strfield16 == "" || strfield16 == null)
{ 
alert("Please enter value for_Borrower Name.")
   return false;
}
//if(strfield17 == "" || strfield17 == null)
//{ 
//alert("Please enter value for_Other Names.")   
 //return false;
//}
if(strfield18 == "" || strfield18 == null)
{ 
alert("Please enter value for_Surname.")   
 return false;
}
/*
if(strfield19 == "" || strfield19 == null)
{ 
alert("Please enter value for_Spouse,Partner,Co-Borrower Name.")   
return false;
}
if(strfield20 == "" || strfield20 == null)
{ 
alert("Please enter value for_Spouse Surname." )  
 return false;
}
*/
if(strfield21 == "" || strfield21 == null)
{ 
alert("Please enter value for_borrower present address.")
   return false;
}
if(strfield22 == "" || strfield22 == null)
{ 
alert("Please enter value for_street name and number.")
    return false;
}
if(strfield23 == "" || strfield23 == null)
{ 
alert("Please enter value for_town.") 
 return false;
}
if(strfield24 == "" || strfield24 == null)
{ 
alert("Please enter value for_Country.") 
  return false;
}
if(strfield25 == "" || strfield25 == null)
{
 alert("Please enter value for_Permanent Country of Residence.") 
   return false;
}
if(strfield26 == "" || strfield26 == null)
{
 alert("Please enter value for_Location of Acquired Real Estate")  
 return false;
}

    return true;
}
function check(total_revenue_for_Affordability){

if (isEmpty(total_revenue_for_Affordability.loan_amount)){
if (isEmpty(total_revenue_for_Affordability.property_type)){
if (isEmpty(total_revenue_for_Affordability.open_market_value)){
if (isEmpty(total_revenue_for_Affordability.loan_type)){
if (isEmpty(total_revenue_for_Affordability.loan_maturity)){
if (isEmpty(total_revenue_for_Affordability.rate_type)){
if (isEmpty(total_revenue_for_Affordability.irate)){
if (isEmpty(total_revenue_for_Affordability.insurance_replacement)){
if (isEmpty(total_revenue_for_Affordability.annual_sal)){
if (isEmpty(total_revenue_for_Affordability.fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.hp_fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.hp_fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.affordability_policy)){
if (isEmpty(total_revenue_for_Affordability.customer_type)){
if (isEmpty(total_revenue_for_Affordability.nationality)){
if (isEmpty(total_revenue_for_Affordability.name)){
if (isEmpty(total_revenue_for_Affordability.other_name)){
if (isEmpty(total_revenue_for_Affordability.surname)){
if (isEmpty(total_revenue_for_Affordability.spouse_name)){
if (isEmpty(total_revenue_for_Affordability.spouse_surname)){
if (isEmpty(total_revenue_for_Affordability.address)){
if (isEmpty(total_revenue_for_Affordability.street_add)){
if (isEmpty(total_revenue_for_Affordability.town)){
if (isEmpty(total_revenue_for_Affordability.country)){
if (isEmpty(total_revenue_for_Affordability.permanent_residence)){
if (isEmpty(total_revenue_for_Affordability.location)){

return true;

}}}}}}}}}}}}}}}}}}}}}}}}}}

return false;
}

function isEmpty(strfieldo,strfield0, strfield1, strfield2, strfield3, strfield4,strfield5, strfield6, strfield7, strfield8, strfield9, strfield10, strfield11, strfield12, strfield13, strfield14,strfield15, strfield16, strfield17, strfield18, strfield19,strfield20, strfield21, strfield22, strfield23, strfield24, strfield25, strfield26)
   {
//variables names
var strfield1 = document.total_revenue_for_Affordability.loan_amount.value;
var strfield2 = document.total_revenue_for_Affordability.property_type.value;
var strfield3 = document.total_revenue_for_Affordability.open_market_value.value;
var strfield4 = document.total_revenue_for_Affordability.loan_type.value;
var strfieldo = document.total_revenue_for_Affordability.o_loan_type.value;
var strfield5 = document.total_revenue_for_Affordability.loan_maturity.value;
var strfield6 = document.total_revenue_for_Affordability.rate_type.value;
var strfield7 = document.total_revenue_for_Affordability.irate.value;
var strfield8 = document.total_revenue_for_Affordability.insurance_replacement.value;
var strfield9 = document.total_revenue_for_Affordability.annual_sal.value;
var strfield10 = document.total_revenue_for_Affordability.fixed_perm_allowances.value;
//var strfield11 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value;
//var strfield12 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value;
var strfield13 = document.total_revenue_for_Affordability.affordability_policy.value;
var strfield14 = document.total_revenue_for_Affordability.customer_type.value;
var strfield15 = document.total_revenue_for_Affordability.nationality.value;
var strfield16 = document.total_revenue_for_Affordability.name.value;
var strfield17 = document.total_revenue_for_Affordability.other_name.value;
var strfield18 = document.total_revenue_for_Affordability.surname.value;
var strfield19 = document.total_revenue_for_Affordability.spouse_name.value;
var strfield20 = document.total_revenue_for_Affordability.spouse_surname.value;
var strfield21 = document.total_revenue_for_Affordability.address.value;
var strfield22 = document.total_revenue_for_Affordability.street_add.value;
var strfield23 = document.total_revenue_for_Affordability.town.value;
var strfield24 = document.total_revenue_for_Affordability.country.value;
var strfield25 = document.total_revenue_for_Affordability.permanent_residence.value;
var strfield26 = document.total_revenue_for_Affordability.location.value;
var strfield0 = document.total_revenue_for_Affordability.one.value;

  //name field
 if(strfield0 == "" || strfield0 == null)
{ 
alert("Please enter Customer Identification Number.")
return false;
}
if(strfield1 == "" || strfield1 == null)
{ 
alert("Please enter value for_loan amount requested.")
return false;
}
if(strfield2 == "" || strfield2 == null)
{
alert("Please enter value for_Property type.") 
return false;
}
if(strfield3 == "" || strfield3 == null)
{
alert("Please enter value for_Open Market Value." )
return false;
 }
if(strfield4 == "" || strfield4 == null)
{
 alert("Please enter value for_Loan Type." )
return false;
}

if(strfield5 == "" || strfield5 == null)
{
 alert("Please enter value for_Loan Maturity Requested.")
  return false;
}
if(strfield6 == "" || strfield6 == null)
{ 
alert("Please enter value for_Rate Type Requested.")  
  return false;
}
if(strfield7 == "" || strfield7 == null)
{ 
alert("Please enter value for_Estimated Loan Current (Offered) Interest Rate pa.") 
   return false;
}
if(strfield8 == "" || strfield8 == null)
{ 
alert("Please enter value for_Insurance Replacement Cost.") 
   return false;
}
if(strfield9 == "" || strfield9 == null)
{ 
alert("Please enter value for_Annual Salary Amount (Gross)." )
   return false;
}
if(strfield10 == "" || strfield10 == null)
{ 
alert("Please enter value for_Fixed Permanent allowances (pa).") 
   return false;
}
//if(strfield11 == "" || strfield11 == null)
//{ 
//alert("Please enter value for_Household Partner- Annual Salary Amount (Gross),enter 0 if not available.")   
// return false;
//}
//if(strfield12 == "" || strfield12 == null)
//{ 
//alert("Please enter value for_Household Partner- Fixed Permanent allowances (pa) enter 0 if not available..")
//    return false;
//}
if(strfield13 == "" || strfield13 == null)
{ 
alert("Please enter value for_Affordability Policy.")  
 return false;
}
if(strfield14 == "" || strfield14 == null)
{ 
alert("Please enter value for_Customer Type.")   
 return false;
}
if(strfield15 == "" || strfield15 == null)
{
 alert("Please enter value for_Nationality.")   
 return false;
}
if(strfield16 == "" || strfield16 == null)
{ 
alert("Please enter value for_Borrower Name.")
   return false;
}
//if(strfield17 == "" || strfield17 == null)
//{ 
//alert("Please enter value for_Other Names.")   
 //return false;
//}
if(strfield18 == "" || strfield18 == null)
{ 
alert("Please enter value for_Surname.")   
 return false;
}
/*
if(strfield19 == "" || strfield19 == null)
{ 
alert("Please enter value for_Spouse,Partner,Co-Borrower Name.")   
return false;
}
if(strfield20 == "" || strfield20 == null)
{ 
alert("Please enter value for_Spouse Surname." )  
 return false;
}
*/
if(strfield21 == "" || strfield21 == null)
{ 
alert("Please enter value for_borrower present address.")
   return false;
}
if(strfield22 == "" || strfield22 == null)
{ 
alert("Please enter value for_street name and number.")
    return false;
}
if(strfield23 == "" || strfield23 == null)
{ 
alert("Please enter value for_town.") 
 return false;
}
if(strfield24 == "" || strfield24 == null)
{ 
alert("Please enter value for_Country.") 
  return false;
}
if(strfield25 == "" || strfield25 == null)
{
 alert("Please enter value for_Permanent Country of Residence.") 
   return false;
}
if(strfield26 == "" || strfield26 == null)
{
 alert("Please enter value for_Location of Acquired Real Estate")  
 return false;
}

    return true;
}
function check(total_revenue_for_Affordability){

if (isEmpty(total_revenue_for_Affordability.loan_amount)){
if (isEmpty(total_revenue_for_Affordability.one)){
if (isEmpty(total_revenue_for_Affordability.property_type)){
if (isEmpty(total_revenue_for_Affordability.open_market_value)){
if (isEmpty(total_revenue_for_Affordability.loan_type)){
if (isEmpty(total_revenue_for_Affordability.o_loan_type)){
if (isEmpty(total_revenue_for_Affordability.loan_maturity)){
if (isEmpty(total_revenue_for_Affordability.rate_type)){
if (isEmpty(total_revenue_for_Affordability.irate)){
if (isEmpty(total_revenue_for_Affordability.insurance_replacement)){
if (isEmpty(total_revenue_for_Affordability.annual_sal)){
if (isEmpty(total_revenue_for_Affordability.fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.hp_fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.hp_fixed_perm_allowances)){
if (isEmpty(total_revenue_for_Affordability.affordability_policy)){
if (isEmpty(total_revenue_for_Affordability.customer_type)){
if (isEmpty(total_revenue_for_Affordability.nationality)){
if (isEmpty(total_revenue_for_Affordability.name)){
if (isEmpty(total_revenue_for_Affordability.other_name)){
if (isEmpty(total_revenue_for_Affordability.surname)){
if (isEmpty(total_revenue_for_Affordability.spouse_name)){
if (isEmpty(total_revenue_for_Affordability.spouse_surname)){
if (isEmpty(total_revenue_for_Affordability.address)){
if (isEmpty(total_revenue_for_Affordability.street_add)){
if (isEmpty(total_revenue_for_Affordability.town)){
if (isEmpty(total_revenue_for_Affordability.country)){
if (isEmpty(total_revenue_for_Affordability.permanent_residence)){
if (isEmpty(total_revenue_for_Affordability.location)){

return true;

}}}}}}}}}}}}}}}}}}}}}}}}}}}}

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
document.total_revenue_for_Affordability.one.disabled = status;
}

function enable_text2(status)
{
status=!status; 
document.total_revenue_for_Affordability.pone.disabled = status;
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
        <form ACTION="editfinal.php" name="total_revenue_for_Affordability" method="post"  onClick="return displ();" onsubmit="return check(this);">
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">
<tr><td><FONT class="btn" color="#FF0000"><strong><i>ERROR! </i>YOU DO NOT HAVE SUFFICIENT RIGHTS TO USE THIS APPLICATION.PLEASE CONTACT THE ADMINISTRATOR</strong></font></td></tr>

</table>
   
 </table> 
</table>




</BODY>
</HTML>