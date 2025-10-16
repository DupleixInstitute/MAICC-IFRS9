
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING - Unsecured Loan</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">
<script src="assets/js/jquery.min.js" type="text/javascript"></script>
<script src="assets/js/sweetalert.min.js" type="text/javascript"></script>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
	include("assets/includes/authenticate.php");
	include("assets/includes/database_connection.php");

	$database_mode      = isset($_POST['omang_no_to_edit'])?'Add':'Edit';
	$omang_no_to_edit   = isset($_POST['omang_no_to_edit'])?$_POST['omang_no_to_edit']:'N/A';
	$loan_no_to_edit    = isset($_POST['omang_no_to_edit'])?$_POST['loan_no_to_edit']:'N/A';
	echo '<input type = text name = "omang_no_to_edit" id = "omang_no_to_edit" value = "'.$omang_no_to_edit.'" >';
	echo '<input type = text name = "loan_no_to_edit"  id = "loan_no_to_edit"  value = "'.$loan_no_to_edit.'" >';

?>
 <script>
	//document. ready function - get the list of table names
	var customer_record = [];
	$(function(){

	   if ($('#omang_no_to_edit').val() != 'N/A') {

			$.ajax({
				url:"ajaxScripts/get_customer_record.php",
				type: "POST",
				data: {
					omang: $('#omang_no_to_edit').val(),
					loan_number: $('#loan_no_to_edit').val()
				},
				dataType : 'json',
				success: function(result){
					customer_record = result;
					if (result.errorStatus == 'Error') {
						//alert (result.errorText);
						swal ('',result.errorText,'error');
					} else {
						document.total_revenue_for_Affordability.affordability_policy.value  = result.data.affordability_policy
						document.total_revenue_for_Affordability.affordability_ratio.value  = result.data.affordability_ratio
						document.total_revenue_for_Affordability.relationship.value  = result.data.age_of_relationship
						document.total_revenue_for_Affordability.annual_sal.value  = result.data.annual_salary
						document.total_revenue_for_Affordability.loan_arrears.value  = result.data.bbs_arreas_12months
						document.total_revenue_for_Affordability.blacklisted.value  = result.data.blacklist_flag
						document.total_revenue_for_Affordability.education.value  = result.data.borrower_education
						document.total_revenue_for_Affordability.address.value  = result.data.borrower_present_address
						document.total_revenue_for_Affordability.aunts_uncles_cousins.value  = result.data.other_dependents_aunts
						document.total_revenue_for_Affordability.card_held_since.value  = result.data.card_held_since
						document.total_revenue_for_Affordability.to18.value  = result.data.childred13_to18
						document.total_revenue_for_Affordability.to12.value  = result.data.children0_to12
						document.total_revenue_for_Affordability.to26.value  = result.data.children19_to26
						document.total_revenue_for_Affordability.cif.value  = result.data.CIF
						document.total_revenue_for_Affordability.country.value  = result.data.country
						document.total_revenue_for_Affordability.irate.value  = result.data.current_interest_rate
						document.total_revenue_for_Affordability.customer_type.value  = result.data.customer_type
						document.total_revenue_for_Affordability.deduct.value  = result.data.deduct
						document.total_revenue_for_Affordability.default_data.value  = result.data.default_data
						document.total_revenue_for_Affordability.Deposit.value  = result.data.deposit_Account
						document.total_revenue_for_Affordability.email.value  = result.data.e_mail
						document.total_revenue_for_Affordability.employment.value  = result.data.employment_contract
						document.total_revenue_for_Affordability.loan_installment.value  = result.data.estimated_installment
						document.total_revenue_for_Affordability.life_cover_amount.value  = result.data.life_cover_amount
						document.total_revenue_for_Affordability.insurance_premium.value  = result.data.estimated_insurance_premium
						document.total_revenue_for_Affordability.fixed_perm_allowances.value  = result.data.fixed_permanent_allowances
						document.total_revenue_for_Affordability.fraud_alert.value  = result.data.fraud_alerts
						document.total_revenue_for_Affordability.gender.value  = result.data.gender
						document.total_revenue_for_Affordability.itc_ref.value  = result.data.ITC_REF
						document.total_revenue_for_Affordability.judgement.value  = result.data.judgment
						document.total_revenue_for_Affordability.loan_amount.value  = result.data.loan_amount_requested
						document.total_revenue_for_Affordability.loan_maturity.value  = result.data.loan_maturity_requested
						document.total_revenue_for_Affordability.loan_number.value  = result.data.loan_number
						document.total_revenue_for_Affordability.loan_type.value  = result.data.loan_type
						document.total_revenue_for_Affordability.mainbank.value  = result.data.main_bank
						document.total_revenue_for_Affordability.marital_contract_type.value  = result.data.marital_contract_type
						document.total_revenue_for_Affordability.marital_status.value  = result.data.marital_status
						document.total_revenue_for_Affordability.rent.value  = result.data.monthly_payment
						document.total_revenue_for_Affordability.Mortgages.value  = result.data.mortgages
						document.total_revenue_for_Affordability.nationality.value  = result.data.nationality
						document.total_revenue_for_Affordability.cards_held.value  = result.data.no_of_credit_card
						document.total_revenue_for_Affordability.years_at_job.value  = result.data.number_of_years_at_employer
						document.total_revenue_for_Affordability.contractual_years_remaining.value  = result.data.contractual_years_remaining
						document.total_revenue_for_Affordability.one.value  = result.data.omang_passport_num
						document.total_revenue_for_Affordability.grandparents.value  = result.data.other_dependents_grandparents
						document.total_revenue_for_Affordability.name.value  = result.data.borrower_name
						document.total_revenue_for_Affordability.other_name.value  = result.data.other_names
						document.total_revenue_for_Affordability.paid_debts.value  = result.data.paid_debt
						document.total_revenue_for_Affordability.permanent_residence.value  = result.data.permanent_country_of_residence
						document.total_revenue_for_Affordability.Professional.value  = result.data.professional_category
						document.total_revenue_for_Affordability.rate_type.value  = result.data.rate_type_requested
						document.total_revenue_for_Affordability.renegotiate.value  = result.data.renegotiated
						document.total_revenue_for_Affordability.Savings.value  = result.data.savings_Account
						document.total_revenue_for_Affordability.secondbank.value  = result.data.second_bank
						document.total_revenue_for_Affordability.Share.value  = result.data.share_Account
						document.total_revenue_for_Affordability.ST.value  = result.data.ST_Loans
						if (typeof(result.data.street_name_and_number) == "undefined") {
							document.total_revenue_for_Affordability.street_add.value  = result.data.Street_name_and_number
						} else {
  						    document.total_revenue_for_Affordability.street_add.value  = result.data.street_name_and_number
						}
						document.total_revenue_for_Affordability.surname.value  = result.data.surname
						document.total_revenue_for_Affordability.tax.value  = result.data.tax
						document.total_revenue_for_Affordability.thirdbank.value  = result.data.third_bank
						document.total_revenue_for_Affordability.dependants_at_home.value  = result.data.total_dependants
						document.total_revenue_for_Affordability.no_of_children.value  = result.data.total_number_of_children
						document.total_revenue_for_Affordability.town.value  = result.data.town
						document.total_revenue_for_Affordability.loans_outstanding.value  = result.data.number_of_loans_outstanding
						document.total_revenue_for_Affordability.trace_alerts.value  = result.data.trace_alerts
						document.total_revenue_for_Affordability.why_renogotiation.value  = result.data.why_renegotiated
						document.total_revenue_for_Affordability.years_at_address.value  = result.data.years_at_present_address

                        //call a function to populate the loan installments for previous loans
						populate_loan_instalments();//
						//call a function to show the input fields for loans outstanding instalments
						display(result.data.number_of_loans_outstanding,'1','2','3','4','5','6','7','8','9');

					   //update calculated fields
						document.total_revenue_for_Affordability.total_bbs_products.value  = result.data.total_bbs_products
						document.total_revenue_for_Affordability.revenues.value  = result.data.household_proffessional_revenue
						document.total_revenue_for_Affordability.dependants_at_home.value  = result.data.total_dependants
						document.total_revenue_for_Affordability.no_of_children.value  = result.data.total_number_of_children
						document.total_revenue_for_Affordability.rev4afford.value  = result.data.total_revenue_for_affordability
						document.total_revenue_for_Affordability.total_rev_for_afford.value  = result.data.total_revenue_for_affordability

						document.total_revenue_for_Affordability.borrower_birth.value = result.data.borrower_birth
						document.total_revenue_for_Affordability.spouse_birth.value   = result.data.spouse_birth
						document.total_revenue_for_Affordability.wedding.value        = result.data.wedding
						document.total_revenue_for_Affordability.divorce.value        = result.data.divorce

						//special marital dates
						if (result.data.spouse_birth  == '0000-00-00' ) {
						    localStorage.spouse_birth_checkbox_disabled = "true"
						}

						if (result.data.wedding == '0000-00-00') {
 						    localStorage.wedding_checkbox_disabled = "true"
						}
						if (result.data.divorce == '0000-00-00') {
 						    localStorage.divorce_checkbox_disabled = "true"
						}
						 ReCalculateForm();
					}
				}
			}); // end ajax call
	   } // end if
	   ReCalculateForm();
	   read_dates_disabled_property();
	   if ($('#omang_no_to_edit').val() != 'N/A') {
			$("#one").attr("readonly", true);
			$("#loan_number").attr("readonly", true);
       }
	});
function check_record_exist() {
    var omang_entered       = document.total_revenue_for_Affordability.one.value;
	var loan_number_entered = document.total_revenue_for_Affordability.loan_number.value;
    console.log ("omang entered is       "+omang_entered);
    console.log ("loan number entered is "+loan_number_entered);
	if (omang_entered != '' && loan_number_entered != '') {
			$.ajax({
				url:"ajaxScripts/get_customer_record.php",
				type: "POST",
				data: {
					omang: omang_entered,
					loan_number: loan_number_entered
				},
				dataType : 'json',
				success: function(result){
					if (result.errorStatus != 'Error') {
						//alert (result.errorText);
						swal ('Record Exist','Customer record for omang/passport no '+omang_entered+' and loan number '+loan_number_entered+' already scored','error');
						document.total_revenue_for_Affordability.one.value         = '';
						document.total_revenue_for_Affordability.loan_number.value = '';
						//document.total_revenue_for_Affordability.one.focus();
						//document.getElementById("one").focus()
					}
				}
			});
		    if ($('#omang_no_to_edit').val() != 'N/A') {
				$("#loan_number").val(customer_record.data.loan_number);
			}
	}
}
function populate_loan_instalments(){
   if (customer_record.data.number_of_loans_outstanding == '1') {
		document.total_revenue_for_Affordability.installment1.value  = customer_record.data.loan_instalment1;
   }
   //2 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '2') {
	   document.total_revenue_for_Affordability.ainstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.ainstallment2.value  = customer_record.data.loan_instalment2;
   }
   //3 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '3') {
	   document.total_revenue_for_Affordability.binstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.binstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.binstallment3.value  = customer_record.data.loan_instalment3;
   }
   //4 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '4') {
	   document.total_revenue_for_Affordability.cinstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.cinstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.cinstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.cinstallment4.value  = customer_record.data.loan_instalment4;
   }
	   //5 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '5') {
	   document.total_revenue_for_Affordability.dinstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.dinstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.dinstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.dinstallment4.value  = customer_record.data.loan_instalment4;
	   document.total_revenue_for_Affordability.dinstallment5.value  = customer_record.data.loan_instalment5;
   }
   //6 loans outstanding
  if (customer_record.data.number_of_loans_outstanding == '6') {
	   document.total_revenue_for_Affordability.einstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.einstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.einstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.einstallment4.value  = customer_record.data.loan_instalment4;
	   document.total_revenue_for_Affordability.einstallment5.value  = customer_record.data.loan_instalment5;
	   document.total_revenue_for_Affordability.einstallment6.value  = customer_record.data.loan_instalment6;
  }
  if (customer_record.data.number_of_loans_outstanding == '7') {
   //7 loans outstanding
	   document.total_revenue_for_Affordability.finstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.finstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.finstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.finstallment4.value  = customer_record.data.loan_instalment4;
	   document.total_revenue_for_Affordability.finstallment5.value  = customer_record.data.loan_instalment5;
	   document.total_revenue_for_Affordability.finstallment6.value  = customer_record.data.loan_instalment6;
	   document.total_revenue_for_Affordability.finstallment7.value  = customer_record.data.loan_instalment7;
  }
   //8 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '8') {
	   document.total_revenue_for_Affordability.ginstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.ginstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.ginstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.ginstallment4.value  = customer_record.data.loan_instalment4;
	   document.total_revenue_for_Affordability.ginstallment5.value  = customer_record.data.loan_instalment5;
	   document.total_revenue_for_Affordability.ginstallment6.value  = customer_record.data.loan_instalment6;
	   document.total_revenue_for_Affordability.ginstallment7.value  = customer_record.data.loan_instalment7;
	   document.total_revenue_for_Affordability.ginstallment8.value  = customer_record.data.loan_instalment8;
   }
   //9 loans outstanding
   if (customer_record.data.number_of_loans_outstanding == '9') {
	   document.total_revenue_for_Affordability.hinstallment1.value  = customer_record.data.loan_instalment1;
	   document.total_revenue_for_Affordability.hinstallment2.value  = customer_record.data.loan_instalment2;
	   document.total_revenue_for_Affordability.hinstallment3.value  = customer_record.data.loan_instalment3;
	   document.total_revenue_for_Affordability.hinstallment4.value  = customer_record.data.loan_instalment4;
	   document.total_revenue_for_Affordability.hinstallment5.value  = customer_record.data.loan_instalment5;
	   document.total_revenue_for_Affordability.hinstallment6.value  = customer_record.data.loan_instalment6;
	   document.total_revenue_for_Affordability.hinstallment7.value  = customer_record.data.loan_instalment7;
	   document.total_revenue_for_Affordability.hinstallment8.value  = customer_record.data.loan_instalment8;
	   document.total_revenue_for_Affordability.hinstallment9.value  = customer_record.data.loan_instalment9;
   }
}

function read_dates_disabled_property() {

   //read the disable state from local storage
   var spouse_birth_checked = (localStorage.spouse_birth_checkbox_disabled == "false")?false:true;
   var divorce_checked      = (localStorage.divorce_checkbox_disabled      == "false")?false:true;
   var wedding_checked      = (localStorage.wedding_checkbox_disabled      == "false")?false:true;

   //disable the checkboxes
   document.total_revenue_for_Affordability.spouse_birth_checkbox.checked  = spouse_birth_checked
   document.total_revenue_for_Affordability.wedding_checkbox.checked       = wedding_checked
   document.total_revenue_for_Affordability.divorce_checkbox.checked       = divorce_checked


   //disable the actual date
   document.total_revenue_for_Affordability.spouse_birth.disabled  = spouse_birth_checked
   document.total_revenue_for_Affordability.wedding.disabled       = wedding_checked
   document.total_revenue_for_Affordability.divorce.disabled       = divorce_checked

}
function total_kids()
{

var children1  = document.total_revenue_for_Affordability.to12.options[document.total_revenue_for_Affordability.to12.selectedIndex].value;
 var children11 = parseFloat(children1);



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


  var aunts=document.total_revenue_for_Affordability.aunts_uncles_cousins.value;
  var aunts1= parseFloat(aunts);


  var others=document.total_revenue_for_Affordability.other.value;
  var others1= parseFloat(others);



  var grandparent=document.total_revenue_for_Affordability.grandparents.value;
  var grandparents1= parseFloat(grandparent);

  var total_dependents= aunts1 + others1 + grandparents1;

  document.total_revenue_for_Affordability.dependants_at_home.value=total_dependents;

return true;
}

function displ()
{
	var savings		=	document.total_revenue_for_Affordability.Savings.value;
	var Deposit		=	document.total_revenue_for_Affordability.Deposit.value;
 	var Share		=	document.total_revenue_for_Affordability.Share.value;
	var ST			=	document.total_revenue_for_Affordability.ST.value;
	var Mortgages	=	document.total_revenue_for_Affordability.Mortgages.value;
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




function premium()
	{
	var life_cover_amount=document.total_revenue_for_Affordability.life_cover_amount.value;
	var loan_maturity_months =document.total_revenue_for_Affordability.loan_maturity.value*12;
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

	var finalpremium= mypremium.toFixed(2);
	document.total_revenue_for_Affordability.insurance_premium.value = finalpremium;
	return true;
}



function instal()
{
    premium();
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
var installment=document.total_revenue_for_Affordability.loanandinsurance.value;
var addaa = document.total_revenue_for_Affordability.tax.value
    var addaa = parseFloat(addaa, 10)
    addaa = (isNaN(addaa))?0:addaa;


var add1 = document.total_revenue_for_Affordability.annual_sal.value
    var add1 = parseFloat(add1, 10)
    add1 = (isNaN(add1))?0:add1;

    //ADD SECOND INPUT VALUE
    var add2 = document.total_revenue_for_Affordability.fixed_perm_allowances.value
    var add2 = parseFloat(add2, 10)
    add2 = (isNaN(add2))?0:add2;

	var firstItem = add1 + add2 - addaa ;


	var forafford1=add1 + add2;


	if (isNaN(firstItem)) {
		alert("You can only enter numbers in the  Rate fields!");
		return false;
	}
	else {
		document.total_revenue_for_Affordability.total_rev_for_afford.value = firstItem;


		/*document.forms[0].amount6.value = sixthItem;*/
		var grandTotal = firstItem;
		var tt=grandTotal/12;
		var tt2= tt.toFixed(2);
			document.total_revenue_for_Affordability.rev4afford.value = tt2;

var ratio= installment/tt2;

var ratio2=ratio*100;
var finalratio= ratio2.toFixed(2);
document.total_revenue_for_Affordability.affordability_ratio.value=finalratio;
		return true;
	}
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



function isEmpty(strfield0,strfield1, strfield2, strfield3, strfield4,strfield5, strfield6, strfield7, strfield8, strfield9, strfield10, strfield11, strfield12, strfield13, strfield14,strfield15, strfield16, strfield17, strfield18, strfield19,strfield20, strfield21, strfield22, strfield23, strfield24, strfield25, strfield26 , strfield27, strfield28, strfield29, strfield30, strfield31)
   {
//variables names
var strfield1 = document.total_revenue_for_Affordability.loan_amount.value;
var strfield4 = document.total_revenue_for_Affordability.loan_type.value;
var strfield5 = document.total_revenue_for_Affordability.loan_maturity.value;
var strfield6 = document.total_revenue_for_Affordability.rate_type.value;
var strfield7 = document.total_revenue_for_Affordability.irate.value;
var strfield8 = document.total_revenue_for_Affordability.life_cover_amount.value;
var strfield9 = document.total_revenue_for_Affordability.annual_sal.value;
var strfield10 = document.total_revenue_for_Affordability.fixed_perm_allowances.value;
var strfield13 = document.total_revenue_for_Affordability.affordability_policy.value;
var strfield14 = document.total_revenue_for_Affordability.customer_type.value;
var strfield15 = document.total_revenue_for_Affordability.nationality.value;
var strfield16 = document.total_revenue_for_Affordability.name.value;
var strfield17 = document.total_revenue_for_Affordability.other_name.value;
var strfield18 = document.total_revenue_for_Affordability.surname.value;
var strfield21 = document.total_revenue_for_Affordability.address.value;
var strfield22 = document.total_revenue_for_Affordability.street_add.value;
var strfield23 = document.total_revenue_for_Affordability.town.value;
var strfield24 = document.total_revenue_for_Affordability.country.value;
var strfield25 = document.total_revenue_for_Affordability.permanent_residence.value;
var strfield27 = document.total_revenue_for_Affordability.gender.value;
var strfield0 = document.total_revenue_for_Affordability.one.value;
var strfield28 = document.total_revenue_for_Affordability.education.value;
var strfield29 = document.total_revenue_for_Affordability.employment.value;
var strfield30 = document.total_revenue_for_Affordability.Professional.value;



  //name field

if(strfield0 == "" || strfield0 == null)
{
alert("Please enter value omang/passport number")
return false;
}
if(strfield1 == "" || strfield1 == null)
{
alert("Please enter value for_loan amount requested.")
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
alert("Please enter value for Life Cover Amount.")
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

if(strfield27 == "" || strfield27 == null)
{
 alert("Please enter value for gender")
 return false;
}
if(strfield28 == "" || strfield28 == null)
{
 alert("Please enter value for borrower education")
 return false;
}
if(strfield29 == "" || strfield29 == null)
{
 alert("Please enter value for employment contract")
 return false;
}
if(strfield30 == "" || strfield30 == null)
{
 alert("Please enter value for proffessional category")
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
function display(obj,id1,id2,id3,id4,id5,id6,id7,id8,id9) {
	if(typeof(obj)=='string') {
		txt = obj;
	}else{
		txt = obj.options[obj.selectedIndex].value;
	}
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

function ReCalculateForm() {
   instal();
   premium();
   displ();
   mortinstal();
   total_dependents();
   total_kids();
   calcTotal();
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
*/

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
$ip =gethostbyname($_SERVER['REMOTE_ADDR']);
//echo $ip;


$connect=mysqli_connect("localhost","admin","password",$db_name);
//echo $pass;
  if (!$connect) {
  mysqli_close($connect);
  echo "Cannot connect to the database! Please Check your username and password.";
  die();
}


?>

<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" >
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
		<td	><img src="../assets/images/corporate_logo1.jpg"" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
		<td	><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="../assets/images/corporate_logo2.png"" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>


</tr>
<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING - Unsecured Loan</font></td><tr>
      </tr>
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text">
          <td>
        <p></br>
        <form ACTION="rate_unsecured_loan_original.php" name="total_revenue_for_Affordability" method="post"  onclick = "ReCalculateForm()" onsubmit="return check(this);">
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">
<tr>
                <td><strong><font color="#FF0000">**</font></strong> <em><font color="#FF9900" size="1">System
                  Generated Data</font></em></td>
              </tr>
 <tr>
          <td><strong><font color="#000000">ID/Passport</font></strong></td>
          <td></td><td>
<input type="text" name="one" id ="one" onchange = "check_record_exist();"></td> </tr>
<tr>
          <td><strong><font color="#000000">Customer CIF <font color="#FF0000">(If
            existing BBS Customer)</font></font></strong></td>
          <td></td><td>
<input type="text" name="cif"></td> </tr>
<tr><td>Loan Application Number</td><td width="60">&nbsp;</td><td>
<SELECT NAME="loan_number" id ="loan_number" onchange = "check_record_exist();">
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
<option value="11">11 </option>
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
</SELECT></td></tr>
<tr><td>Loan Type</td><td width="60">&nbsp;</td><td>
<SELECT NAME="loan_type" onchange="displaya(this,'77', '78', '79');">
<option value=""> </option>
<option value="Standard to Batswana">Standard to Batswana </option>
<option value="Guaranteed Scheme">Guaranteed Scheme</option>
<option value="Non-Guaranteed Scheme">Non-Guaranteed Scheme</option>
<option value="Standard to Non-Citizens">Standard to Non-Citizens </option>
<option value="Second Time Home Owner">Second Time Home Owner</option>
<option value="77">Further Advance</option>
</SELECT></td></tr>

</thead>
<tfoot>

</tfoot>
<tbody id="77" style="display: none;">
<tr>
            <td class="title"><strong><em>Original Loan Type:</em></strong></td>
<td class="field"><SELECT NAME="o_loan_type" >
<option value="Fixed">Fixed </option>
<option value="Variable" selected>Variable</option>
</SELECT></td>
</tr>
</tbody>
<tbody id="78" style="display: none;">
<tr>
            <td class="title"><strong><em>Principal Outstanding in Original Loan:</em></strong></td>
<td class="field"><input name="o_bal" type="text" onchange ="return new_loan()"></td>
</tr>
</tbody>
<tbody id="79" style="display: none;">
<tr>
            <td class="title"><strong><em>New FA Requested:</em></strong></td>
<td class="field"><input name="newloan" type="text" onchange="return new_loan()"></td>
</tr>
</tbody>
<tr><td>Loan Amount Requested :</td><td width="60">&nbsp;</td><td>
<input name="loan_amount" type="text" onchange="return instal()" onFocus="this.blur">
</td></tr>

<tr><td>Loan Maturity Requested</td><td width="60"></td><td><SELECT NAME="loan_maturity" onchange="return instal()">
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


<tr><td>Rate Type Requested:</td><td width="60">&nbsp;</td><td><SELECT NAME="rate_type" >
<option value=""></option>
<option value="Fixed">Fixed </option>
<option value="Floating">Floating</option>
<option value="Variable">Variable</option>
</SELECT></td></tr>

<tr><td>Estimated Loan Current (Offered) Interest Rate pa</td><td width="60">&nbsp;</td><td>
<input name="irate" type="text" onchange="return instal()">
</td></tr>

<tr><td>Life Cover Amount</td><td width="60">&nbsp;</td><td>
<input name="life_cover_amount" type="text">
</td></tr>


<tr>
			<td>Estimated Insurance Premium <strong><font color="#FF0000">**</font></strong></td>
			<td width="60">&nbsp;</td><td>
<input name="insurance_premium" type="text"  readonly="true" onFocus="premium()">
</td></tr>

<tr><td>Estimated Instalment of the requested Loan <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="loan_installment" type="text"  readonly="true" onFocus="this.blur();">
</td></tr>

<tr><td>Estimated Instalment + Insurance Premium  <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="loanandinsurance" type="text"  readonly="true" onClick="mortinstal()" onchange="return calcTotal()">
</td></tr>


<tr>
                <td><strong><font color="#000066">Professional Revenues (Gross
                  amounts) Borrower:</font></strong> </td>
                <td width="60"></td><td></td></tr>

<tr><td>Annual Salary Amount (Gross): </td><td width="60"></td><td><input type="text" name="annual_sal" onchange="return calcTotal()"></td></tr>

<tr><td>Other Income (pa):</td><td width="60"></td><td> <input type="text" name="fixed_perm_allowances" onchange="return calcTotal()"></td></tr>

<tr><td>Tax (pa)</td><td width="60"></td><td> <input type="text" name="tax" onchange="return calcTotal()"></td></tr>

<tr><td>Total Revenues for affordability: <strong><font color="#FF0000">**</font></strong> </td><td width="60"></td><td><input type="text" name="total_rev_for_afford" onFocus="this.blur();"></td></tr>

<tr><td>Total revenues for affordability <strong><font color="#FF0000">**</font></strong> </td><td width="60">&nbsp;</td><td>
<input name="rev4afford"  readonly="true" type="text" >
</td></tr>


<tr><td>Affordability Policy</td><td width="60">&nbsp;</td><td>
<SELECT NAME="affordability_policy" >
<option value="70">70%</option>
 </select>
</td></tr>

<tr><td>Affordability Ratio <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<input name="affordability_ratio" type="text" readonly="true" >
</td></tr>

<tr><td></td><td width="60">&nbsp;</td><td></td></tr>
<tr><td></td><td width="60">&nbsp;</td><td></td></tr>

<tr><td>Customer Type:</td><td width="60">&nbsp;</td><td><SELECT NAME="customer_type" >
<option value=""> </option>
<option value="Legal Entity">Legal Entity </option>
<option value="Physical">Physical</option>
</SELECT></td></tr>

<tr><td>Borrower Name :</td><td width="60">&nbsp;</td><td>
<input name="name" type="text">
</td></tr>
<tr><td>Nationality:</td><td width="60">&nbsp;</td><td>
  <SELECT NAME="nationality" >
<option value="Motswana">Motswana </option>
<option value="Foreigner">Foreigner </option>
</SELECT>
</td></tr>

<tr><td>Has Customer Provided Permanent Residency Permit?</td><td width="60">&nbsp;</td><td>
  <SELECT NAME="perm_res" >
  <option value="NA">NA </option>
<option value="Yes">Yes </option>
<option value="No">No </option>
</SELECT>
</td></tr>


<tr><td>Other Names :</td><td width="60">&nbsp;</td><td>
<input name="other_name" type="text">
</td></tr>

<tr><td>Surname :</td><td width="60">&nbsp;</td><td>
<input name="surname" type="text">
</td></tr>
<tr><td>Gender:</td><td width="60">&nbsp;</td><td><SELECT NAME="gender" >
<option value=""> </option>
<option value="M">M</option>
<option value="F">F</option>
</SELECT></td></tr>

<tr><td>Borrower Present Address :</td><td width="60">&nbsp;</td><td>
<input name="address" type="text">
</td></tr>
<tr><td>Borrower E_mail Address :</td><td width="60">&nbsp;</td><td>
<input name="email" type="text">
</td></tr>

<tr><td>Street Name and Number :</td><td width="60">&nbsp;</td><td>
<input name="street_add" type="text">
</td></tr>

<?php
$con = mysqli_connect("localhost","admin","password",$db_name);
if (!$con)
  {
  die('Could not connect: ' . mysqli_error());
  }

?>
<tr><td>Town:</td><td width="60">&nbsp;</td><td>


<?php
$query2 = "SELECT * FROM town order by town asc" ;
	$resultn = mysqli_query($connect,$query2);
	echo'<select name="town">';
	while($row = mysqli_fetch_assoc( $resultn )) {
	 echo '<option value="'. $row['town'] .'">' . $row['town'] . '</option>';
	}
	echo '</select>';

?></td></tr> &nbsp;&nbsp;&nbsp;
<tr><td>Country:</td><td width="60">&nbsp;</td><td>
<input name="country" type="text">
</td></tr>

<tr><td>Permanent Country of Residence :</td><td width="60">&nbsp;</td><td><SELECT name="permanent_residence" >
<option value="Botswana">Botswana </option>
<option value="Other">Other </option>
</SELECT>

</td></tr>
</br>
</br>

</td></tr>
<tr><td>Marital Status</td><td width="60"></td><td> <SELECT NAME="marital_status">
<option value=""></option>
<option value="Single">Single</option>
<option value="Married">Married </option>
<option value="Divorced">Divorced </option>
<option value="Widowed">Widowed </option>
<option value="Other">Other</option>
</SELECT> </td></tr>
<tr>
  <td>Borrower BirthDate :
  </td>
  <td width="60">&nbsp;</td>
  <td><input type = "date" name ="borrower_birth"/> </td>
</tr>
 <tr>
   <td>Spouse BirthDate:
     <input name="spouse_birth_checkbox" type="checkbox"
			onclick="spouse_birth.disabled=!spouse_birth.disabled
			localStorage.spouse_birth_checkbox_disabled=spouse_birth.disabled"/>
  </td>
  <td width="60">&nbsp;</td>
  <td><input type = "date" name ="spouse_birth"/> </td>
</tr>

</br>
<tr><td>Wedding Date:
     <input name="wedding_checkbox" type="checkbox"
			onclick="wedding.disabled=!wedding.disabled
			localStorage.wedding_checkbox_disabled=wedding.disabled"/>
  </td>
  <td width="60">&nbsp;</td>
  <td><input type = "date" name ="wedding"/> </td>
</tr>
<tr><td>
<tr><td>Divorce Date:
     <input name="divorce_checkbox" type="checkbox"
			onclick="divorce.disabled=!divorce.disabled
			localStorage.divorce_checkbox_disabled=divorce.disabled"/>
  </td>
  <td width="60">&nbsp;</td>
  <td><input type = "date" name ="divorce"/> </td>
</tr>

<tr><td>Marital Contract Type</td><td width="60"></td><td> <SELECT NAME="marital_contract_type" >
<option value="Community of Property">Community of Property</option>
<option value="Other marital Contract">Other marital Contract </option>
<option value="na">na </option>
</SELECT></td></tr>


</SELECT></td></tr>

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
                <td width="60"></td><td> <input type="text" name="no_of_children" value=0 onFocus="this.blur();" readonly="true"> </td></tr>

<tr><td>Children living at home 0 to 12 years of age
 </td><td width="60"></td><td><SELECT NAME="to12" onChange="return total_kids()">
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
</SELECT></td></tr>
</br>
</br>
<tr><td>Children living at home 13 to 18 years of age</td><td width="60"></td><td><SELECT NAME="to18" onChange="return total_kids()">
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
</br>
</br>
<tr><td>Children living at home 19 to 26 years of age</td><td width="60"></td><td><SELECT NAME="to26" onChange="return total_kids()">
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
</SELECT></td></tr>



<tr><td>Other dependants Living in Household GrandParents</td><td width="60"></td><td><SELECT NAME="grandparents" onChange="return total_dependents()">
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
</SELECT></td></tr>

<tr><td>
Other dependants Living in Household Aunts/Uncles/Cousins</td><td width="60"></td><td><SELECT NAME="aunts_uncles_cousins" onChange="return total_dependents()" >
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
</br>
</br>
<tr><td>Other dependants Living in Household</td><td width="60"></td><td><SELECT NAME="other" onChange="return total_dependents()">
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
</SELECT></td></tr>

<tr><td><font color="#000066"><strong>TOTAL</strong></font> Dependants
                  Living at home:<strong><font color="#FF0000">**</font></strong></td>
                <td width="60"></td><td> <input type="text" name="dependants_at_home" value=0 onFocus="this.blur();" readonly="true"> </td></tr>


<tr><td>Years At Present Address:</td><td width="60"></td><td> <SELECT NAME="years_at_address" >
<option value="0 to 3">0 to 3</option>
<option value="3 to 5">3 to 5</option>
<option value="5 to 10">5 to 10</option>
<option value="+10">+10</option>
</SELECT></td></tr>

<tr><td>Monthly payment (rental/instalment):</td><td width="60"></td><td> <input type="text" name="rent"> </td></tr>



<tr><td>Borrower Education:</td><td width="60"></td><td> <SELECT NAME="education" >
<option value=""> </option>
<option value="Primary Education">Primary Education</option>
<option value="Secondary Education">Secondary School</option>
<option value="Certificate">Certificate</option>
<option value="Diploma">Diploma</option>
<option value="Degree">Degree</option>
<option value="Masters Degree">Masters Degree</option>
<option value="PHD">PHD</option>
<option value="Professional Course">Professional Course</option>
<option value="None">None</option>
</SELECT></td></tr>

<tr><td>Professional Category:</td><td width="60"></td><td> <SELECT NAME="Professional" >
<option value=""> </option>
<option value="Self Employed">Self Employed</option>
<option value="Civil Servant">Civil Servant</option>
<option value="Employee">Employee</option>
<option value="Other">Other</option>
</SELECT>
</td></tr>

<tr><td>Employment Contract:</td><td width="60"></td><td> <SELECT NAME="employment" >
<option value=""> </option>
<option value="Permanent">Permanent</option>
<option value="Contractual">Contractual</option>
<option value="Other">Other</option>
</SELECT></td></tr>

<tr><td>Number Of Years Working at Present Employer:</td><td width="60"></td>
	<td><SELECT NAME="years_at_job" >
				<option value="N/A">N/A</option>
				<option value="0 to 1">0 to 1</option>
				<option value="1 to 5">1 to 5</option>
				<option value="5 to 20">5 to 20</option>
				<option value="20 and more">20 and more</option>
		</SELECT>
	</td>
</tr>

<!--------------------------------------------------------------------------------------------------------->
<tbody id = "contractual_years_remaining">
	<tr>
	    <td>Number Of Years Remaining On Contract:</td>
		<td width="60"></td>
		<td><SELECT NAME="contractual_years_remaining" >
				<option value="N/A">N/A</option>
				<option value="0 to 1">0 to 1</option>
				<option value="1 to 2">1 to 2</option>
				<option value="2 to 3">2 to 3</option>
				<option value="3 to 5">3 to 5</option>
				<option value="5 to 20">5 to 20</option>
				<option value="20 and more">20 and more</option>
			</SELECT>
		</td>
	</tr>
</tbody>
<!--------------------------------------------------------------------------------------------------------->


<tr><td>Household Professional Revenues:</td><td width="60"></td><td> <SELECT NAME="revenues" onchange="return double()">
<option value="Single">Single</option>
<option value="Double">Double</option>
</SELECT></td></tr>


<tr><td>Main Bank:</td><td width="10"></td><td> <SELECT NAME="mainbank" >

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


<tr><td>Second Bank: </td><td width="10"></td><td><SELECT NAME="secondbank" >
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

<tr><td>Third Bank: </td><td width="10"></td><td><SELECT NAME="thirdbank" >
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

<tr><td>Age Of Relationshp with BBS:</td><td width="10"></td><td> <SELECT NAME="relationship" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>


<tr><td>Savings Account:</td><td width="10"></td><td> <SELECT NAME="Savings" onclick = "displ()">
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Deposit Account: </td><td width="10"></td><td><SELECT NAME="Deposit" onclick = "displ()">
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Share Account: </td><td width="10"></td><td><SELECT NAME="Share" onclick = "displ()">
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>ST Loans: </td><td width="10"></td><td><SELECT NAME="ST" onclick = "displ()" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Mortgages: </td><td width="10"></td><td><SELECT NAME="Mortgages" onclick = "displ()">
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>


<tr><td>TOTAL BBS Products: <strong><font color="#FF0000">**</font></strong> </td><td width="10"></td><td><input type="text" name="total_bbs_products"></td></tr>

<tr><td >BBS arrears for over 30days in last 12mnths?</br>
</td><td width="10"></td><td><SELECT NAME="loan_arrears">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT></td></tr>


<tr><td >Renegotiated loans with arreas in past 24 months?</br>
</td><td width="10"></td><td><SELECT NAME="renegotiate">
<option value=""></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT></td></tr>


<tr><td>Why was this needed ?
</td><td width="10"></td><td><SELECT NAME="why_renogotiation">
<option value="0">Unexpected expenses</option>
<option value="Increased instalments">Increased instalments</option>
<option value="Reduced revenues">Reduced revenues</option>
<option value="other">other</option>
<option value="N/A">N/A</option>
</SELECT>



<tr><td>Number of credit card personally held</td><td width="10"></td><td>
<SELECT NAME="cards_held">
<option value=""></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT>



<tr><td>Card held since (in years) </td><td width="10"></td><td><SELECT NAME="card_held_since">
<option value=""></option>
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4 and above</option>
</SELECT>
</td></tr>

<tr><td>Number of loans presently outstanding </td><td width="10"></td><td><SELECT NAME="loans_outstanding" onchange="display(this,'1','2','3','4','5','6','7','8','9');">
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
<!--<option value="10">10</option>-->
</SELECT>
</td></tr>

<tr>

</thead>
<tfoot>

</tfoot>
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

<tr><td>ITC REF NO.</td><td width="60">&nbsp;</td><td><input type="text" name="itc_ref"></td></tr>

<tr><td>ITC REPORT -(Paid Debt) </td><td width="10"></td><td><SELECT NAME="paid_debts">
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
</td></tr>

<tr><td>ITC REPORT: Judgement </td><td width="10"></td><td><SELECT NAME="judgement">
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
</td></tr>

<tr><td>ITC REPORT: Default Data </td><td width="10"></td><td><SELECT NAME="default_data">
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
</td></tr>

<tr><td>ITC REPORT: Trace Alerts  </td><td width="10"></td><td><SELECT NAME="trace_alerts">
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
</td></tr>
<tr><td>BlackList Flag  </td><td width="10"></td><td><SELECT NAME="blacklisted">
<option value="No">No</option>
<option value="Yes">Yes</option>
</SELECT>
</td></tr>

<tr><td>Fraud Alerts  </td><td width="10"></td><td><SELECT NAME="fraud_alert">
<option value="No">No</option>
<option value="Yes">Yes</option>


</SELECT>
</td></tr>
<tr><td>Deduction from source? </td><td width="10"></td><td><SELECT NAME="deduct">
<option value="No">No</option>
<option value="Yes">Yes</option>
</SELECT></td></tr>
<tr><td>
<input type="hidden" name="username"  value="<?php echo $username;?>"/>
<input type="hidden" name="password"  value="<?php echo $password; ?>"/>

<input type="SUBMIT" name="RATE"  class="btn" value="Submit"/>
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
