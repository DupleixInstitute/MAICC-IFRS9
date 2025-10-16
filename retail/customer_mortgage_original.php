
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING - Mortgage Loan</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">

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









$pass=$_POST['password'];
$username=$_POST['username'];
$password=$_POST['password'];
$application_ref = $_POST['application_ref'];

$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password=""; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$omang_passport_num=$_POST['omang'];
$loan_number=$_POST['loan'];

//echo $omang_passport_num." eeee ".$loan_number;
//echo  $omang_passport_num;
//echo  $loan_number;

$connect=mysqli_connect($host,$username,$password,$db_name); 
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}


$s="SELECT * FROM cust_information where omang_passport_num='$omang_passport_num' and loan_number='$loan_number'";

$result1=mysqli_query($connect,$s);

if (!$result1)
  {
  echo "Error: Please contact System Administrator";
  die("Cmathatah ".mysqli_error());
  }
  
//$result1=(mysql_query($selectO));
if($result1)
{
//echo 'mysequleee okay';
}

$mycounts=0;
while($rows1=mysqli_fetch_array($result1)){
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
$result=(mysqli_query($connect,$insert));
if($result)
{
//echo "done";
}

$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>


<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr">
  <tr>
    <td colspan="6" class="s_text"><table width="100%" border="0" cellspacing="0" cellpadding="0" >
      <tr>
        <td width="1286"><table width="770" height="165">
<td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
<td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
</table></td>


</tr>
<tr>
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CREDIT SCORING - Mortgage Loan</font></td><tr>
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
 <tr></tr>
          <td><strong><font color="#000000">Omang/Passport</font></strong></td>
          <td></td><td>
<?php echo $rows['omang_passport_num']; ?></td> </tr>
 <tr>
          <td><font color="#000000">Loan Application Number</font></td>
          <td></td><td>
<?php echo $loan_number; ?></td> </tr>

<tr ><td>Loan Type</td><td width="100">&nbsp;</td><td>
<?php
 if ($rows['loan_type']=="77")
{
echo "Further Advance";
}
else{
echo $rows['loan_type']; 
 }?>

</td></tr>

</thead>
<tfoot>

</tfoot>
<tbody id="77" style="display: none;">
<tr >
            <td class="title"><strong><em>Original Loan Type:</em></strong></td>
<td class="field"><?php echo $rows['loan_type']; ?> </td>
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
<tr ><td>Loan Amount Rquested :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['loan_amount_requested']; ?>
</td></tr>

<tr><td>Property Type:</td><td width="60">&nbsp;</td><td><?php echo $rows['property_type']; ?>
</td></tr>

<tr ><td>Open Market Value:</td><td width="60">&nbsp;</td><td><?php echo $rows['open_market_value']; ?>
</td></tr>


<tr><td>Loan Maturity Requested</td><td width="60"></td><td><?php echo $rows['loan_maturity_requested']; ?>
</td>
<tr ><td>Rate Type Requested:</td><td width="60">&nbsp;</td><td><?php echo $rows['rate_type_requested']; ?>
</td>
<tr><td>Estimated Loan Current (Offered) Interest Rate pa</td><td width="60">&nbsp;</td><td>
<?php echo $rows['current_interest_rate']; ?>
</td></tr>
 
<tr ><td>Insurance Replacement Cost</td><td width="60">&nbsp;</td><td>
<?php echo $rows['insurance_replacement_cost']; ?>
</td></tr>


<tr>
                <td>Estimated Insurance Premium <strong><font color="#FF0000">**</font></strong></td>
                <td width="60">&nbsp;</td><td>
<?php echo $rows['estimated_insurance_premium']; ?>
</td></tr>

<tr ><td>Estimated Instalment of the requested Loan <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<?php echo $rows['estimated_installment']; ?>
</td></tr>

<tr><td>Estimated Instalment + Insurance Premium  <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<?php echo $rows['estimated_installment_insurance']; ?>
</td></tr>


<tr ><td>Loan To Value Policy <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<?php echo $rows['loan_to_value_policy']; ?>
</td></tr>

<tr><td>Loan To Value <strong><font color="#FF0000">**</font></strong> </td><td width="60">&nbsp;</td><td>
<?php echo $rows['loan_to_value']; ?>
</td></tr>

<tr >
        <td><strong><font color="#000066">Professional Revenues (Gross 
                  amounts) Borrower:</font></strong> </td>
                <td width="60"></td><td></td></tr>

<tr><td>Annual Salary Amount (Gross): </td><td width="60"></td><td><?php echo $rows['annual_salary']; ?></td></tr>

<tr ><td>Other Income:</td><td width="60"></td><td><?php echo $rows['fixed_permanent_allowances']; ?></td></tr>

<tr><td>Tax </td><td width="60"></td><td><?php echo $rows['tax']; ?></td></tr>

<tr ><td>Total Revenues for affordability: <strong><font color="#FF0000">**</font></strong> </td><td width="60"></td><td><?php echo $rows['total_revenue_for_affordability']; ?></td></tr>

<tr>
                <td><font color="#000066"><strong>HouseHold Partner Revenues:</strong></font></td>
                <td width="60"></td><td> </td></tr>

<tr ><td>Household Partner- Annual Salary Amount (Gross): </td><td width="60"></td><td><?php echo $rows['partner_annual_salary']; ?></td></tr>

<tr><td>Household Partner- Other Income:</td><td width="60"></td><td><?php echo $rows['partner_permanent_allowances']; ?></td></tr>

<tr ><td>Household Partner- Tax :</td><td width="60"></td><td><?php echo $rows['tax2']; ?></td></tr>

<tr><td>Household Partner- Total Revenues for affordability: <strong><font color="#FF0000">**</font></strong> </td><td width="60"></td><td><?php echo $rows['partner_revenue_for_affordability']; ?></td></tr>

<tr ><td>TOTAL Allowable Household Revenues: <strong><font color="#FF0000">**</font></strong> </td><td width="10"></td><td><?php echo $rows['total_allowable_household_revenues']; ?></td></tr>



<tr><td>Total revenues for affordability <strong><font color="#FF0000">**</font></strong> </td><td width="60">&nbsp;</td><td>
<?php echo $rows['total_revenue_for_affordability'];?>
</td></tr>


<tr ><td>Affordability Policy</td><td width="60">&nbsp;</td><td>

<!--<option value="35">35%</option>-->
40%
<!--<option value="45">45%</option>-->
<!--<option value="50">50%</option> </select>-->

</td></tr>

<tr><td>Affordability Ratio <strong><font color="#FF0000">**</font></strong></td><td width="60">&nbsp;</td><td>
<?php echo $rows['affordability_ratio']; ?>
</td></tr>

<tr ><td></td><td width="60">&nbsp;</td><td></td></tr>
<tr><td></td><td width="60">&nbsp;</td><td></td></tr>

<tr><td>Customer Type:</td><td width="60">&nbsp;</td><td>
<?php echo $rows['customer_type']; ?> 
</td>
</tr>
<tr ><td>Borrower Name :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['borrower_name']; ?>
</td></tr>
<tr><td>Nationality:</td><td width="60">&nbsp;</td><td>
<?php echo $rows['nationality']; ?>
</td>
</tr>







<tr ><td>Other NAmes :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['other_names']; ?>
</td></tr>

<tr><td>Surname :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['surname']; ?>
</td></tr>

<tr ><td>Spouse,Partner,Co-Borrower Name :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['spouse_partner_co_borrower_name']; ?>
</td></tr>

<tr><td>Spouse Surname:</td><td width="60">&nbsp;</td><td>
<?php echo $rows['spouse_surname']; ?>
</td></tr>

<tr ><td>Borrower Present Address :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['borrower_present_address']; ?>
</td></tr>

<tr><td>Street Name and Number :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['street_name_and_number']; ?>
</td></tr><?php
$con = mysqli_connect("localhost","root","",$db_name);
if (!$con)
  {
  die('Could not connect: ' . mysqli_error());
  }
?>
<tr ><td>Town:</td><td width="60">&nbsp;</td>
<td>
<?php $rows['town']; ?>
</td>
 
</tr> &nbsp;&nbsp;&nbsp;
<tr><td>Country:</td><td width="60">&nbsp;</td><td>
<?php echo $rows['country']; ?>
</td></tr>

<tr ><td>Permanent Country of Residence :</td><td width="60">&nbsp;</td><td>
<?php echo $rows['permanent_country_of_residence']; ?>
</td>
</tr>
</br>
</br>
    





<tr><td>Location of Acquired REal Estate:</td><td width="60">&nbsp;</td><td> 
<?php echo $rows['location_of_acquired_real_estate']; ?>
</td>

</tr>

</td></tr>
<tr ><td>Marital Status</td><td width="60"></td><td> <?php echo $rows['marital_status']; ?> </td>

</tr>

<tr><td>Borrower Birthdate</td><td width="60">&nbsp;</td>
           
<?php
$year = $rows['borrower_birth'];
$y = date('Y',strtotime($year));
$m = date('M',strtotime($year)); 
//echo  $m." month ";






?>
<td><?php echo $rows['borrower_birth']; ?></td>
</tr>

 <tr ><td>
Spouse BirthDate: ;</td><td width="60">&nbsp;</td>
            
<td><?php echo $rows['spouse_birth']; ?></td>

</tr>
<tr><td>Wedding Date</td><td width="60">&nbsp;</td>
           

<td><?php echo $rows['wedding']; ?></td>
</tr>

 <tr ><td>
Divorce Date: ;</td><td width="60">&nbsp;</td>
            
<td><?php echo $rows['divorce']; ?></td>

</tr>
<tr><td>Marital Contract Type</td><td width="60"></td><td> <?php echo $rows['marital_contract_type']; ?></td>
</tr>

<tr><td>Spouse/Partner Borrowing Relationship</td><td width="60"></td><td> <?php echo $rows['spouse_borrowing_relationship']; ?></td>
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

<tr >
   <td><font color="#000066"><strong>Total Number of Children:<strong><font color="#FF0000">**</font></strong></strong></font></td>
                <td width="60"></td><td>  </td></tr>

<tr><td>Children living at home 0 to 12 years of age
 </td><td width="60"></td><td><?php echo $rows['children0_to12']; ?>
</td>
</tr>
</br>
</br>
<tr ><td>Children living at home 13 to 18 years of age</td><td width="60"></td><td><?php echo $rows['childred13_to18']; ?>
</br>
</td>
</tr>
</br>
<tr><td>Children living at home 19 to 26 years of age</td><td width="60"></td><td><?php echo $rows['children19_to26']; ?></td>
</tr>



<tr ><td>Other dependants Living in Household GrandParents</td><td width="60"></td><td><?php echo $rows['other_dependents_grandparents']; ?>
</tr>

<tr><td>
Other dependants Living in Household Aunts/Uncles/Cousins</td><td width="60"></td><td><?php echo $rows['other_dependents_aunts']; ?>
</br>
</br>
</td>

</tr>
<tr ><td>Other dependants Living in Household</td><td width="60"></td><td><?php echo $rows['other_dependents']; ?></td>

</tr>

<tr><td><font color="#000066"><strong>TOTAL</strong></font> Dependants 
Living at home:<strong><font color="#FF0000">**</font></strong></td>
 <td width="60"></td><td> <?php $rows['total_dependants'];?>   </td></tr>


<tr ><td>Years At Present Address:</td><td width="60"></td><td><?php echo $rows['years_at_present_address']; ?></td>

</tr>

<tr><td>Monthly payment (rental/instalment):</td><td width="60"></td><td><?php echo $rows['monthly_payment']; ?> </td></tr>



<tr ><td>Borrower Education:</td><td width="60"></td><td> <?php echo $rows['borrower_education']; ?></td>

</tr>

<tr><td>Professional Category:</td><td width="60"></td><td>  <?php echo $rows['professional_category']; ?>
</td>
</tr>

<tr ><td>Employment Contract:</td><td width="60"></td><td> <?php echo $rows['employment_contract']; ?></td>

</tr>

<tr><td>Number Of Years Working at Present Employer:</td><td width="60"></td><td> <?php echo $rows['number_of_years_at_employer']; ?></td>
</tr>

<tr ><td>Household Professional Revenues:</td><td width="60"></td><td> <?php echo $rows['household_proffessional_revenue']; ?></td>
</tr>


<tr><td>Main Bank:</td><td width="10"></td><td><?php echo $rows['main_bank']; ?></td>

</tr>


<tr ><td>Second Bank: </td><td width="10"></td><td><?php echo $rows['second_bank']; ?></td>

</tr>

<tr><td>Third Bank: </td><td width="10"></td><td><?php echo $rows['third_bank']; ?></td>
</tr>

<tr ><td>Age Of Relationshp with BBS:</td><td width="10"></td><td> <?php echo $rows['age_of_relationship']; ?></td>
</tr>


<tr><td>Savings Account:</td><td width="10"></td><td> <?php echo $rows['savings_Account']; ?></td>
</tr>

<tr ><td>Deposit Account: </td><td width="10"></td><td><?php echo $rows['deposit_Account']; ?></td>
</tr>

<tr><td>Share Account: </td><td width="10"></td><td><?php echo $rows['share_Account']; ?></td>
</tr>

<tr ><td>ST Loans: </td><td width="10"></td><td><?php echo $rows['ST_Loans']; ?></td>
</tr>

<tr><td>Mortgages: </td><td width="10"></td><td><?php echo $rows['mortgages']; ?></td>
</tr>


<tr ><td>TOTAL BBS Products: <strong><font color="#FF0000">**</font></strong> </td><td width="10"></td><td><?php echo $rows['total_bbs_products']; ?></td></tr>

<tr><td >BBS arrears for over 30days in last 12mnths?</br> 
</td><td width="10"></td><td><?php echo $rows['bbs_arreas_12months']; ?></td>
</tr>


<tr ><td >Renegotiated loans with arreas in past 24 months?</br> 
</td><td width="10"></td><td><?php echo $rows['renegotiated']; ?></td>
</tr>


<tr><td>Why was this needed ?
</td><td width="10"></td><td><?php echo $rows['why_renegotiated']; ?>
</td>

</tr>


<tr ><td>Number of credit card personally held</td><td width="10"></td><td>
<?php echo $rows['no_of_credit_card']; ?>
</td>
</tr>



<tr><td>Card held since (in years) </td><td width="10"></td><td><?php echo $rows['card_held_since']; ?>
</td>
</tr>

<tr ><td>Number of loans presently outstanding </td><td width="10"></td><td><?php echo $rows['number_of_loans_outstanding']; ?>
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






<tr><td>ITC REF NO.</td><td width="60">&nbsp;</td><td><?php echo $rows['ITC_REF']; ?></td></tr>

<tr><td>ITC REPORT -(Paid Debt) </td><td width="10"></td><td><?php echo $rows['paid_debt']; ?>
</td>
</tr>

<tr ><td>ITC REPORT: Judgement </td><td width="10"></td><td><?php echo $rows['judgment']; ?>
</td>
</tr>

<tr><td>ITC REPORT: Default Data </td><td width="10"></td><td><?php echo $rows['default_data']; ?>
</td>
</tr>

<tr ><td>ITC REPORT: Trace Alerts  </td><td width="10"></td><td><?php echo $rows['trace_alerts']; ?>
</td>

</tr>
<tr><td>BlackList Flag  </td><td width="10"></td><td><?php echo $rows['blacklist_flag']; ?>
</td>
</tr>

<tr ><td>Fraud Alerts  </td><td width="10"></td><td><?php echo $rows['fraud_alerts']; ?>
</td>
</tr>


<tr><td>Deduction from source? </td><td width="10"></td><td><?php echo $rows['deduct']; ?></td>


</tr>
<tr><td>
<input type="hidden" name="debtserviceratio"  value="<?php echo $rows['debtserviceratio']; ?>"/> 

<input type="hidden" name="username"  value="<?php echo $_POST['username'] ?>"/>
<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
<input readonly="YES" type="hidden" value="<?php echo $y;?>" name="year" />
<input readonly="YES" type="hidden" value="<?php echo $m;?>" name="month" />
</td></tr>
<tr><td>
<tr><td>
</td></tr>
<tr><td>
</td></tr>
<tr><td>&nbsp;
</td></tr>
<tr><td>&nbsp;
</td></tr>

<tr><td>
<?php echo "CUSTOMER SIGNATURE ";?>
</td></td><td><?php echo "___________________________________";?></td></tr>


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