//menu.php 
function menuphp_show_loan_category_icon(category) {
	if (category == 'mortgage') {
	 initialise_unneeded_unsecured_loans_fields();
     document.getElementById("loan_category_icon").src = "assets/icons/mortgage_loan1.png";
	 document.getElementById('property_type').style.display                 = '';
	 document.getElementById('bond_plot').style.display                      = '';
	 document.getElementById('open_market_value').style.display             = '';
	 document.getElementById('loan_to_value').style.display                 = '';
	 document.getElementById('insurance_replacement').style.display         = '';
	 document.getElementById('life_cover_amount').style.display             = 'none';
	 document.getElementById('household_partner_revenues').style.display    = '';
	 document.getElementById('spouse_names').style.display                  = '';
	 document.getElementById('location').style.display                      = '';
	 document.getElementById('spouse_borrowing_relationship').style.display = '';
	 document.getElementById('contractual_years_remaining').style.display        = 'none';	 
	} else {
	 initialise_unneeded_mortgage_loans_fields();
     document.getElementById("loan_category_icon").src = "assets/icons/unsecured_personal_loan3.png";
	 document.add_form.property_type.value           = 'NA';
	 document.getElementById('property_type').style.display                 = 'none';
	 document.getElementById('bond_plot').style.display                     = 'none';
	 document.getElementById('bond_plot').value                             = 'NA';
	 document.getElementById('open_market_value').style.display             = 'none';
	 document.getElementById('loan_to_value').style.display                 = 'none';
	 document.getElementById('insurance_replacement').style.display         = 'none';
	 document.getElementById('life_cover_amount').style.display             = '';
	 document.getElementById('household_partner_revenues').style.display    = 'none';
	 document.getElementById('spouse_names').style.display                  = 'none';
	 document.getElementById('location').style.display                      = 'none';
	 document.getElementById('spouse_borrowing_relationship').style.display = 'none';
	 document.getElementById('contractual_years_remaining').style.display   = '';
	}
    calcTotal();	
}
function initialise_unneeded_mortgage_loans_fields() {
	 $('#property_type').val('NA');
	 $('#open_market_value').val('0');
	 $('#ltv').val('0');
	 $('#insurance_replacement').val('0');
	 $('#spouse_name').val('NA');
	 $('#surname').val('NA');
	 $('#location').val('NA');
	 $('#spouse_borrowing_relationship').val('NA');

     //nullify the partner revenues
     $('#hp_annual_sal').val(0);
     $('#hp_fixed_perm_allowances').val(0);
     $('#tax2').val(0);
     $('#hp_total_rev_for_afford').val(0);

}
function get_loan_number() {
}
function initialise_unneeded_unsecured_loans_fields() {
	 $('#life_cover_amount').val('0');
	 $('#contractual_years_remaining').val('NA');
}
//show contractual_years_remaining only if unsecured loan and employee on contract. How about mortgage loans?
function menuphp_toggle_contractual_years(contract_type) {
    var loan_category = $('#loan_category').val();
	if (contract_type == 'Contractual' && loan_category == 'unsecured loan'  ) {
        document.getElementById('contractual_years_remaining').style.display = '';
	} else {
    	document.getElementById('contractual_years_remaining').style.display = 'none';
	}	
}
/********************************************************************************************
/*Function Name    : displaya
/*Function Purpose : toggle view of Further Assistance Loan further information
/*How does it work?: toggle the style.display attribute and loan amount hidden attribute accrodingly
/*********************************************************************************************/

function displaya(obj,a,b,c) 
{
	txt = obj.options[obj.selectedIndex].value;
	document.getElementById(a).style.display = 'none';
	document.getElementById(a+"a").style.display = 'none';
	document.getElementById(b).style.display = 'none';
	document.getElementById(b+"a").style.display = 'none';
	document.getElementById(c).style.display = 'none';
	document.getElementById(c+"a").style.display = 'none';
	$('#loan_amount').attr('disabled', false);
	//Further Advance is option value 77 which is the a variable
	if ( txt.match(a) )    //show if further advance
	{
		document.getElementById(a).style.display = 'block';
		document.getElementById(a+"a").style.display = 'block';
		document.getElementById(b).style.display = 'block';
		document.getElementById(b+"a").style.display = 'block';
		document.getElementById(c).style.display = 'block';
		document.getElementById(c+"a").style.display = 'block';
		$('#loan_amount').attr('disabled', true);
	}
}

function ReCalculateForm() {
   instal();
   premium();
   displ();
   myltvpolicy();
   loantoval();
   mortinstal();
   total_dependents();
   total_kids();
   calcTotal();
}

// After selected New Advance this is the event for the New Request Amount
function new_loan(){
    var installment   = $('#o_bal').val();//document.add_form.o_bal.value;
    var installment   = parseFloat(installment, 10)
        installment   = (isNaN(installment))?0:installment;
    var monthsal      = $('#newloan').val();//document.add_form.newloan.value;    
    var monthsal      = parseFloat(monthsal, 10)
        monthsal      = (isNaN(monthsal))?0:monthsal;

    var newtotalloan  = installment + monthsal;
    var newtotalloan2 = parseFloat(newtotalloan.toFixed(2));
	$('#loan_amount').val(newtotalloan2);

    //document.add-form.loan_amount.value= newtotalloan2;
    return true;		
}

function total_kids()
{
  var children11       = parseFloat($('#to12').val());
  var children22       = parseFloat($('#to18').val());
  var children33       = parseFloat($('#to26').val());
  var total_dependents = children11 + children22 + children33;
  $('#no_of_children').val(total_dependents);
  return true;
}

function total_dependents()
{
  var aunts1           = parseFloat($('#aunts_uncles_cousins').val());
  var others1          = parseFloat($('#other').val());
  var grandparents1    = parseFloat($('#grandparents').val());
  var total_dependents = aunts1 + others1 + grandparents1;
  $('#dependants_at_home').val(total_dependents);
  return true;
}

function displ()
{
  var savings   = $('#Savings').val();
  var Deposit   = $('#Deposit').val();
  var Share     = $('#Share').val();
  var ST        = $('#ST').val();
  var Mortgages = $('#Mortgages').val();
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
  
  $('#total_bbs_products').val(x);
  
  return true;

}


function loantoval()
{
  var loan_category =$('#loan_category').val(); 
  if (loan_category == 'mortgage') {
		var loan1  = parseFloat($('#loan_amount').val());
		var omv1   = parseFloat($('#open_market_value').val()); 
		var loantovalue= loan1 / omv1;
		var loantovalue3= loantovalue*100;
		var loantovalue2= loantovalue3.toFixed(0);
		$('#ltv').val(loantovalue2);
  }else {
	  
  }
  return true;
}


function mortinstal()
{
  var instal= $('#loan_installment').val();
  var insure= $('#insurance_premium').val();
  var oloan = $('#o_loan_type').val();
  var add2  = isNaN(parseFloat(instal))?0:parseFloat(instal);
  var add1  = parseFloat(insure);
  var finalinstal= add1 + add2;
 
  if(oloan == "Fixed") {
     var finalinstal= add2;
  }  
  var finalinstalment= finalinstal.toFixed(2);
  $('#loanandinsurance').val(finalinstalment);
  return true;
}



function myltvpolicy()
{
	var val=String($('#loan_type').val());

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
	$('#ltv_policy').val(ltvratio);
	return true;
}


function premium()
{
	var loan_category        = $('#loan_category').val();
	if (loan_category == 'mortgage') {
		var myinsure=$('#insurance_replacement').val()   ;		
		var test=$('#property_type').val();
		var val= String(test);
		if (val == "Residential")
		{
			var premiumpercent=0.0500;
		}
		if (val == "Commercial")
		{
			var premiumpercent=0.0400;
		}
		if (val == "Thatched_Residential")
		{
			var premiumpercent=0.35;
		}
		if (val == "Thatched_Commercial")
		{
			var premiumpercent=0.35;
		}
		var mypremium= (myinsure * premiumpercent/12)/100;
	} else {
		var loan_maturity_months =$('#loan_maturity').val()*12;
		var life_cover_amount    =$('#life_cover_amount').val();
		if (loan_maturity_months >= 72) {
			   premiumpercent=5.213;
		} else if (loan_maturity_months >= 60) {
			   premiumpercent=4.238;
		} else if (loan_maturity_months >= 48) {
			   premiumpercent=3.319;
		} else if (loan_maturity_months >= 36) {
			   premiumpercent=2.456;
		} else if (loan_maturity_months >= 24) {
			   premiumpercent=1.642;
		
		} else if (loan_maturity_months < 24) {	
			   premiumpercent=0.833;
		}
		var mypremium= (life_cover_amount * premiumpercent/12)/100;	
    }	
    var finalpremium= mypremium.toFixed(2);
    $('#insurance_premium').val(finalpremium);
	return true;
}



function ltv()
{
   var amt=$('#loan_amount').val();;
   var val=$('#open_market_value').val();
   var loanval= amt/val;
   var myltv= loanval.toFixed(2);
   $('#insurance_replacement').val(myltv);
   return true;
}



/**
function insure()
{
var opv=document.add_form.open_market_value.value;
var insurance= opv * 1.4;
var ins= insurance.toFixed(2);
document.add_form.insurance_replacement.value = ins;
	return true;
}
**/


function instal()
{
	premium();
	var rate     = $('#irate').val();
	var amount   = $('#loan_amount').val();
	var maturity = $('#loan_maturity').val();

	if (isNaN(rate) || isNaN(amount) || isNaN(maturity)) {
	   swal("Loan Amount and Interest Rate","You can only enter numbers in the Loan Amount and Rate fields","error");	
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
	$('#loan_installment').val(dd);

	loantoval();
	return true;
 }

function checkRates(irate)
{
	var mm         = $('#.irate').val();
	var mm2        = $('#loan_amount').val();
	var mm3        = $('#loan_maturity').val();
	var numString  = String(mm);
	var numString2 = String(mm2);
	var numString3 = String(mm3);

	var papi       = String("papiso");
	newstring      = papi.concat(numString);
	newstring2     = papi.concat(numString2);
	newstring3     = papi.concat(numString3);

	if(newstring.length == 6 || newstring2.length == 6 || newstring3.length == 6 ) 
	{
		swal("Installment Amount Calculation","Ensure that the loan amount, loan maturity and interest are all filled to get a correct installment amount","error");
		return false;
	}
 }



function calcTotal() {
	var installment = parseFloat($('#loanandinsurance').val(),10);
	var addaa       = parseFloat($('#tax').val(),10);
	addaa           = (isNaN(addaa))?0:addaa;
	var addbb       = parseFloat($('#tax2').val(),10);
	addbb           = (isNaN(addbb))?0:addbb;
	var add1        = parseFloat($('#annual_sal').val(),10);
	add1 = (isNaN(add1))?0:add1;
	//ADD SECOND INPUT VALUE
	var add2 = parseFloat($('#fixed_perm_allowances').val(),10);
	add2 = (isNaN(add2))?0:add2;
	var firstItem = add1 + add2 - addaa ;

	var add3 = parseFloat($('#hp_annual_sal').val(),10);
	add3 = (isNaN(add3))?0:add3;
	//ADD SECOND INPUT VALUE
	var add4 = parseFloat($('#hp_fixed_perm_allowances').val(),10);
	add4 = (isNaN(add4))?0:add4;
	var secondItem = add3 + add4 - addbb;
	var forafford1=add1 + add2;
	var forafford2=add3 + add4;
	if (isNaN(firstItem) || isNaN(secondItem)) {
	   swal("Rate Fields","You can only enter numbers in the Rate fields!","error");
	   return false;
}
else {
	$('#total_rev_for_afford').val(firstItem);
	$('#hp_total_rev_for_afford').val(secondItem);
	/*document.forms[0].amount6.value = sixthItem;*/
	var grandTotal  = firstItem + secondItem ;
	var grandTotal2 = forafford1 + forafford2;
		
	$('#total_allowable_household_revenues').val(grandTotal);
	var tt         = grandTotal/12;
	var tt2        = tt.toFixed(2);
	$('#rev4afford').val(tt2);
	var gg         = grandTotal2/12;
	var ratio      = installment/gg;
	var ratio2     = ratio*100;
	var finalratio = ratio2.toFixed(2);
	$('#affordability_ratio').val(finalratio);
	return true;
}
}

/******************************************************************************************************
*Function Name           : new_loan
*Purpose                 : calculates new total loan
*Input fields            : o_bal,newloan
*Output fields           : loan_amount = o_bal + new_loan
*
/******************************************************************************************************/

function new_loan(){
  var installment   = parseFloat($('#o_bal').val(),10);
  installment       = (isNaN(installment))?0:installment;
  var monthsal      = parseFloat($('#newloan').val(),10);    
  monthsal          = (isNaN(monthsal))?0:monthsal;
  var newtotalloan  = installment + monthsal;
  var newtotalloan2 = newtotalloan.toFixed(2);
  $('#loan_amount').val(newtotalloan2);
  return true;
}

/******************************************************************************************************
*Function Name           : isEmpty
*Purpose                 : Validation to prevent empty fields before submitting
*Input fields            : loan_amount, 
                           property_type,
                           loan_type,
                           loan_category,                             						   
*Output fields           : error message in swal
*
/******************************************************************************************************/
function isEmpty(strfield1, strfield2, strfield3) {


	//change "field1, field2 and field3" to your field names
	var strfield1     = $('#loan_amount').val();
	var strfield2     = $('#property_type').val();
	var strfield3     = $('#loan_type').val();
	var loan_category = $('#loan_category').val();

    //Loan Amount
    if (strfield1 == "" || strfield1 == null )
    {
       swal("Loan Amount","Please enter value for the loan amount requested.","error");
       return false;
    }

    //Property Type 
    if (strfield2 == "" || strfield2 == null )
    {
       if (loan_category == 'mortgage') {    
	      swal("Loan Category","Please enter value for the property type.","error")
          return false;
	   }else {
          return true;       
	   }		   
    }

    //Loan Type 
    if (strfield3 == "" || strfield3 == null )
    {
       swal("Loan Type","Please enter value for the Loan type.","error");
       return false;
    }
    return true;
}

/******************************************************************************************************
*Function Name           : Validation of Total Revenue for affordability
*Purpose                 : Validation to prevent empty fields before submitting
*Input fields            : loan_amount, 
                           property_type,
                           loan_type,
                           loan_category,                             						   
*Output fields           : error message in swal
*
/******************************************************************************************************/

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

/******************************************************************************************************
*Function Name           : isEmpty
*Purpose                 : Validation to prevent empty fields before submitting
*Input fields            : All input fields 
 *Output fields          : error message in swal
*
/******************************************************************************************************/


function isEmpty(strfield0,strfield1, strfield2, strfield3, strfield4,strfield5, strfield6, strfield7, strfield8, strfield9, strfield10, strfield11, strfield12, strfield13, strfield14,strfield15, strfield16, strfield17, strfield18, strfield19,strfield20, strfield21, strfield22, strfield23, strfield24, strfield25, strfield26 , strfield27, strfield28, strfield29, strfield30, strfield31)
{
	//variables names
	var loan_category = $('#loan_category').val();
	var strfield1     = $('#loan_amount').val();
	var strfield2     = $('#property_type').val();
	var strfield3     = $('#open_market_value').val();
	var strfield4     = $('#loan_type').val();
	var strfield5     = $('#loan_maturity').val();
	var strfield6     = $('#rate_type').val();
	var strfield7     = $('#irate').val();
	var strfield8     = $('#insurance_replacement').val();
	var strfield9     = $('#annual_sal').val();
	var strfield10    = $('#fixed_perm_allowances').val();
	var strfield11    = $('#hp_fixed_perm_allowances').val();
	var strfield12    = $('#hp_fixed_perm_allowances').val();
	var strfield13    = $('#affordability_policy').val();
	var strfield14    = $('#customer_type').val();
	var strfield15    = $('#nationality').val();
	var strfield16    = $('#name').val();
	var strfield17    = $('#other_name').val();
	var strfield18    = $('#surname').val();
	var strfield19    = $('#spouse_name').val();
	var strfield20    = $('#spouse_surname').val();
	var strfield21    = $('#address').val();
	var strfield22    = $('#street_add').val();
	var strfield23    = $('#town').val();
	var strfield24    = $('#country').val();
	var strfield25    = $('#permanent_residence').val();
	var strfield26    = $('#location').val();
	var strfield27    = $('#gender').val();
	var strfield0     = $('#one').val();
	var strfield28    = $('#education').val();
	var strfield29    = $('#employment').val();
	var strfield30    = $('#Professional').val();
	var strfield31    = $('#bond_plot').val();

	//OMANG/PASSPORT NUMBER
	if(strfield0 == "" || strfield0 == null)
	{ 
	   swal("Omang/Passport Number","Please enter value omang/passport number","error");
	   return false;
	} 
	
	//LOAN AMOUNT
	if(strfield1 == "" || strfield1 == null)
	{ 
	   swal("Loan Amount","Please enter value for_loan amount requested.","error");
	   return false;
	}
	
	//PROPERTY TYPE
	if(strfield2 == "" || strfield2 == null)
	{
	  if (loan_category == 'mortgage') {    
		 swal("Property Type","Please enter value for_Property type.","error"); 
		 return false;
	  }else {
		 return true;  
	  }
	}
	
	//OPEN MARKET VALUE
	if(strfield3 == "" || strfield3 == null)
	{
	  if (loan_category == 'mortgage') {    
		 swal("Open Market Value","Please enter value for Open Market Value.","error" );
		 return false;
	  } else {
		 return true;  
	  }
	}
	
	//LOAN TYPE
	if(strfield4 == "" || strfield4 == null)
	{
	   swal("Loan Type","Please enter value for Loan Type.","error" );
	   return false;
	}
	
	//LOAN MATURITY
	if(strfield5 == "" || strfield5 == null)
	{
	  swal("Loan Maturity","Please enter value for Loan Maturity Requested.","error");
	  return false;
	}
	
	//RATE TYPE
	if(strfield6 == "" || strfield6 == null)
	{ 
	  swal("Rate Type","Please enter value for Rate Type Requested.","error");  
	  return false;
	}
	
	//INTEREST RATE
	if(strfield7 == "" || strfield7 == null)
	{ 
	   swal("Interest Rate","Please enter value for_Estimated Loan Current (Offered) Interest Rate pa.","error"); 
	   return false;
	}
	
	//INSURANCE REPLACEMENT COST
	if(strfield8 == "" || strfield8 == null)
	{ 
	  if (loan_category == 'mortgage') {    
		 swal("Insurance Replacement Cost","Please enter value for_Insurance Replacement Cost.","error"); 
		 return false;
	  } else {
		 return true;  
	  }
	}
	
	//ANNUAL SALARY
	if(strfield9 == "" || strfield9 == null)
	{ 
	   swal("Annual Salary","Please enter value for Annual Salary Amount (Gross).","error");
	   return false;
	}
	
	//PERMANENT ALLOWANCES
	if(strfield10 == "" || strfield10 == null)
	{ 
	   swal("Permanent Allowances","Please enter value for_Fixed Permanent allowances (pa).","error");
	   return false;
	}
	
	//AFFORDABILITY POLICY
	if(strfield13 == "" || strfield13 == null)
	{ 
	   swal("Affordability Policy","Please enter value for_Affordability Policy.","error"); 
	   return false;
	}
	
	//CUSTOMER TYPE
	if(strfield14 == "" || strfield14 == null)
	{ 
	   swal("Customer Type","Please enter value for_Customer Type.","error");   
	   return false;
	}
	
	//NATIONALITY
	if(strfield15 == "" || strfield15 == null)
	{
	   swal("Nationality","Please enter value for Nationality.","error");   
	   return false;
	}
	
	//BORROWER NAME
	if(strfield16 == "" || strfield16 == null)
	{ 
	   swal("Borrower Name","Please enter value for_Borrower Name.","error");
	   return false;
	}

	//SURNAME
	if(strfield18 == "" || strfield18 == null)
	{ 
	   swal("Surname","Please enter value for_Surname.","error");   
	   return false;
	}

    //BORROWER PRESENT ADDRESS
	if(strfield21 == "" || strfield21 == null)
	{ 
	   swal("Borrower Present Address","Please enter value for_borrower present address.","error");
	   return false;
	}
	
	//STREET NAME AND NUMBER
	if(strfield22 == "" || strfield22 == null)
	{ 
	    swal("Street name and number","Please enter value for_street name and number.","error");
		return false;
	}
	
	//TOWN
	if(strfield23 == "" || strfield23 == null)
	{ 
	    swal("Town","Please enter value for_town.","error"); 
	    return false;
	}
	
	//COUNTRY
	if(strfield24 == "" || strfield24 == null)
	{ 
	   swal("Country","Please enter value for Country.","error"); 
	   return false;
	}
	
	//RESIDENCE
	if(strfield25 == "" || strfield25 == null)
	{
	    alert("Residence","Please enter value for_Permanent Country of Residence.","error"); 
	    return false;
	}
	
	//LOCATION OF ACQUIRED REAL ESTATE
	if(strfield26 == "" || strfield26 == null)
	{
	  if (loan_category == 'mortgage') {    
		 swal("Location of Acquired Real Estate","Please enter value for_Location of Acquired Real Estate","error");  
		 return false;
	  }else {
		 return true;  
	  }
	}
	
	//GENDER
	if(strfield27 == "" || strfield26 == null)
	{
	    swal("Gender","Please enter value for gender","error");  
	    return false;
	}
	
	//BORROWER EDUCATION
	if(strfield28 == "" || strfield28 == null)
	{
	    swal("Borrower Education","Please enter value for borrower education","error");  
	    return false;
	}
	
	//EMPLOYMENT CONTRACT
	if(strfield29 == "" || strfield29 == null)
	{
	     swal("Employment Contract","Please enter value for employment contract","error");  
	     return false;
	}
	
	//PROFESSIONAL CATEGORY
	if(strfield30 == "" || strfield30 == null)
	{
	   swal("Professional Category","Please enter value for professional category","error");  
	   return false;
	}
	
	//PLOT NUMBER OF BONDED SECURITY
	if(strfield31 == "" || strfield31 == null)
	{
	  if (loan_category == 'mortgage') {    
		 swal("Plot Number of Bonded Security","Please enter value for plot number of bonded security","error");  
		 return false;
	  }else{
		 return true;  
	  }
	}
	return true;
}
 

/******************************************************************************************************
*Function Name           : double
*Purpose                 : Validation to prevent empty fields in Household Partner revenues
*Input fields            : All input fields 
 *Output fields          : error message in swal
*
/******************************************************************************************************/

function double()
{
	var revenues   = $('#revenues').val();
	var hp_annual_sal = $('#hp_annual_sal').val();
	var strfield12 = $('#hp_fixed_perm_allowances').val();

	if(revenues == "Double" && (hp_annual_sal == "" || hp_annual_sal == null || hp_fixed_perm_allowances == "" || hp_fixed_perm_allowances == null)) 
 
	{ 
		swal("Household Partner Revenues","PLEASE ENTER ALL DETAILS FOR HouseHold Partner Revenues:","error")   
		return false;
	}
		return true;
}

/******************************************************************************************************
*Function Name           : enable_text
*Purpose                 : Validation to prevent empty fields in Household Partner revenues
*Input fields            : All input fields 
 *Output fields          : error message in swal
*
/******************************************************************************************************/

function enable_text(status)
{
   status=!status; 
   $('#one').disabled = status;
}

function enable_text2(status)
{
   status=!status; 
   $('#pone').disabled = status;
}

// <![CDATA[
function display(obj,id1,id2,id3,id4,id5,id6,id7,id8,id9) {
	txt = obj.options[obj.selectedIndex].value;
	document.getElementById(id1).style.display = 'none';
	document.getElementById(id2).style.display = 'none';
	document.getElementById(id3).style.display = 'none';
	document.getElementById(id4).style.display = 'none';
	document.getElementById(id5).style.display = 'none';
	document.getElementById(id6).style.display = 'none';
	document.getElementById(id7).style.display = 'none';
	document.getElementById(id8).style.display = 'none';
	document.getElementById(id9).style.display = 'none';
			
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
	if ( txt.match(id5) ) {
		document.getElementById(id5).style.display = 'block';
	}
	if ( txt.match(id6) ) {
		document.getElementById(id6).style.display = 'block';
	}
	if ( txt.match(id7) ) {
		document.getElementById(id7).style.display = 'block';
	}
	if ( txt.match(id8) ) {
		document.getElementById(id8).style.display = 'block';
	}
	if ( txt.match(id9) ) {
		document.getElementById(id9).style.display = 'block';
	}
}

function setReadOnly(obj)
{
	if (obj.options[obj.selectedIndex].value == "Single" )
	{
		$('#day2').readOnly = 1;
		$('#month2').readOnly = 1;
		$('#year2').readOnly = 1;
	} 
}

function load_random() {
$('#borrower_birth_date').val('1980-12-12');
$('#spouse_birth_date').val('1981-11-12');
$('#wedding_date').val('1982-12-09');
$('#divorce_date').val('1983-01-05');


$('#one').val('32132474B77');
$('#loan_number').val('30');
$('#loan_category').val('mortgage');
$('#cif').val('56789');
$('#loan_amount').val('500000');
$('#open_market_value').val('650000');
$('#insurance_replacement').val('700000');
$('#life_cover_amount').val('');
$('#customer_type').val('Physical');
$('#irate').val('3.38');
$('#loan_maturity').val('20');
$('#annual_sal').val('150000');
$('#ainstallment1').val('');
$('#ainstallment2').val('');
$('#binstallment1').val('');
$('#binstallment2').val('');
$('#binstallment3').val('');
$('#cinstallment1').val('');
$('#cinstallment2').val('');
$('#cinstallment3').val('');
$('#cinstallment4').val('');
$('#dinstallment1').val('');
$('#dinstallment2').val('');
$('#dinstallment3').val('');
$('#dinstallment4').val('');
$('#dinstallment5').val('');
$('#einstallment1').val('');
$('#einstallment2').val('');
$('#einstallment3').val('');
$('#einstallment4').val('');
$('#einstallment5').val('');
$('#einstallment6').val('');
$('#finstallment1').val('');
$('#finstallment2').val('');
$('#finstallment3').val('');
$('#finstallment4').val('');
$('#finstallment5').val('');
$('#finstallment6').val('');
$('#finstallment7').val('');
$('#loan_type').val('Standard to Batswana');
$('#rate_type').val('Fixed');
$('#installment1').val('');
$('#name').val('Edward');
$('#nationality').val('Foreigner');
$('#hp_annual_sal').val('80000');
$('#hp_fixed_perm_allowances').val('5000');
$('#tax').val('3268');
$('#tax2').val('1564');
$('#fixed_perm_allowances').val('45000');
$('#ginstallment1').val('');
$('#ginstallment2').val('');
$('#ginstallment3').val('');
$('#ginstallment4').val('');
$('#ginstallment5').val('');
$('#ginstallment6').val('');
$('#ginstallment7').val('');
$('#ginstallment8').val('');
$('#grandparents').val('0');
$('#hinstallment1').val('');
$('#hinstallment2').val('');
$('#hinstallment3').val('');
$('#hinstallment4').val('');
$('#hinstallment5').val('');
$('#hinstallment6').val('');
$('#hinstallment7').val('');
$('#hinstallment8').val('');
$('#hinstallment9').val('');
$('#Professional').val('Other');
$('#affordability_policy').val('70');
$('#country').val('Zimbabwean');
$('#education').val('Primary Education');
$('#loans_outstanding').val('0');
$('#address').val('87 Hogerty Hill');
$('#contractual_years_remaining').val('3 to 5');
$('#employment').val('Permanent');
$('#property_type').val('Commercial');
$('#rent').val('10000');
$('#renegotiate').val('2');
$('#mainbank').val('Barclays');
$('#thirdbank').val('NA');
$('#secondbank').val('NA');
$('#loan_arrears').val('1');
$('#default_data').val('1');
$('#paid_debts').val('1');
$('#trace_alerts').val('1');
$('#itc_ref').val('123456');
$('#judgement').val('0');
$('#fraud_alert').val('Yes');
$('#revenues').val('Single');
$('#aunts_uncles_cousins').val('0');
$('#to12').val('2');
$('#to18').val('0');
$('#to26').val('0');
$('#other').val('1');
$('#deduct').val('Yes');
$('#blacklisted').val('Yes');
$('#card_held_since').val('2');
$('#cards_held').val('1');
$('#why_renogotiation').val('0');
$('#relationship').val('Since less than 1 year');
$('#month').val('12');
$('#month2').val('10');
$('#month22').val('11');
$('#month23').val('12');
$('#other_name').val('Mbombo');
$('#spouse_borrowing_relationship').val('marital_contract_type');
$('#spouse_name').val('Shapiro');
$('#spouse_surname').val('Mashayamombe');
$('#gender').val('M');
$('#marital_contract_type').val('Community of Property');
$('#marital_status').val('Married');
$('#surname').val('Mazibuko');
$('#email').val('sirmazbug@gmail.com');
$('#location').val('Semi Urban A');
$('#perm_res').val('Yes');
$('#permanent_residence').val('Other');
$('#street_add').val('71 Marondera');
$('#town').val('BOKAA');
$('#years_at_address').val('5 to 10');
$('#years_at_job').val('5 to 20');
$('#bond_plot').val('23 Masvingo Street');
$('#day').val('12');
$('#day2').val('13');
$('#day22').val('14');
$('#day23').val('15');
$('#year').val('1980');
$('#year2').val('1981');
$('#year22').val('1982');
$('#year23').val('1983');
$('#Deposit').val('Since less than 1 year');
$('#Mortgages').val('Since less than 1 year');
$('#Savings').val('Since less than 1 year');
$('#Share').val('Since less than 1 year');
$('#ST').val('Since less than 1 year');
	
}