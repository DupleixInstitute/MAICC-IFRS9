
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>SOUR PROJECT</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">

<script type="text/javascript">
function checkRates(irate)
{
var mm =document.total_revenue_for_Affordability.irate.value;
var numString = String(mm);
var papi = String("papiso");
newstring= papi.concat(numString);
if(newstring.length == 6) 
   {
      alert("Ensure that the three are all filled in");
	     return false;
   }
 }

function calcTotal() {

var add1 = document.total_revenue_for_Affordability.annual_sal.value
    var add1 = parseFloat(add1, 10)
    add1 = (isNaN(add1))?0:add1;
    
    //ADD SECOND INPUT VALUE
    var add2 = document.total_revenue_for_Affordability.fixed_perm_allowances.value
    var add2 = parseFloat(add2, 10)
    add2 = (isNaN(add2))?0:add2;
    
	var firstItem = add1 + add2 ;
	
	var add3 = document.total_revenue_for_Affordability.hp_annual_sal.value
    var add3 = parseFloat(add3, 10)
    add3 = (isNaN(add3))?0:add3;
    
    //ADD SECOND INPUT VALUE
    var add4 = document.total_revenue_for_Affordability.hp_fixed_perm_allowances.value
    var add4 = parseFloat(add4, 10)
    add4 = (isNaN(add4))?0:add4;
	
	var secondItem = add3 + add4;
	
	
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
		return true;
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







$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="sour"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysql_connect($host,$username,$password); 
//echo $pass;
  if (!$connect) {
  mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());

}
$insert="INSERT INTO Access_Table VALUES('$ip',CURDATE())";
$result=(mysql_query($insert));
if($result)
{
//echo "done";
}

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
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">SOUR PROJECT</font></td><tr>
      </tr>
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text"> 
          <td>
<p></br> 
<form ACTION="testing.php" name="total_revenue_for_Affordability" method="post" >
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">
<tr><td>Loan Amount Rquested :</td><td width="60">&nbsp;</td><td>
<input name="loan_amount" type="text">
</td></tr>

<tr><td>Property Type:</td><td width="60">&nbsp;</td><td><SELECT NAME="property_type" >
<option value="Residential">Residential </option>
<option value="Commercial">Commercial</option>
<option value="Thatched_Residential">Thatched_Residential </option>
<option value="Thatched_Commercial">Thatched_Commercial</option>
</SELECT></td></tr>

<tr><td>Open Market Value:</td><td width="60">&nbsp;</td><td>
<input name="open_market_value" type="text">
</td></tr>


<tr><td>Loan Type</td><td width="60">&nbsp;</td><td>
<SELECT NAME="loan_type" >
<option value="Std_batswana">Standard to Batswana </option>
<option value="scheme">Guranteed Scheme</option>
<option value="std_non_citizen">Standrad to Non-Citizens </option>
<option value="second_time_owner">Second Time Home Owner</option>
</SELECT></td></tr>

<tr><td>Loan Maturity Requested</td><td width="60"></td><td><SELECT NAME="loan_maturity">
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
<option value="Fixed">Fixed </option>
<option value="Floating">Floating</option>
<option value="Variable">Variable</option>
</SELECT></td></tr>

<tr><td>Estimated Loan Current (Offered) Interest Rate pa</td><td width="60">&nbsp;</td><td>
<input name="irate" type="text">
</td></tr>
 
<tr><td>Insurance Replacement Cost</td><td width="60">&nbsp;</td><td>
<input name="insurance replacement" type="text" onFocus="checkRates(irate.value)">
</td></tr>


<tr><td>Estimated Insurance Premium</td><td width="60">&nbsp;</td><td>
<input name="insurance_premium" type="text" value=" " readonly="true">
</td></tr>

<tr><td>Estimated Instalment of the requested Loan</td><td width="60">&nbsp;</td><td>
<input name="loan_installment" type="text" value=" " readonly="true">
</td></tr>

<tr><td>Estimated Instalment + Insurance Premium </td><td width="60">&nbsp;</td><td>
<input name="loanandinsurance" type="text" value=" " readonly="true">
</td></tr>


<tr><td>Loan To Value Policy</td><td width="60">&nbsp;</td><td>
<input name="ltv_policy" type="text" >
</td></tr>

<tr><td>Loan To Value </td><td width="60">&nbsp;</td><td>
<input name="ltv" type="text" >
</td></tr>


<tr><td>Total revenues for affordability </td><td width="60">&nbsp;</td><td>
<input name="rev4afford" type="text" >
</td></tr>


<tr><td>Affordability Policy</td><td width="60">&nbsp;</td><td>
<SELECT NAME="affordability_policy" >
<option value="35">35%</option>
<option value="40">40%</option>
<option value="45">45%</option>
<option value="50">50%</option> </select>
</td></tr>

<tr><td>Affordability Ratio</td><td width="60">&nbsp;</td><td>
<input name="affordability_ratio" type="text" readonly="true">
</td></tr>

<tr><td></td><td width="60">&nbsp;</td><td></td></tr>
<tr><td></td><td width="60">&nbsp;</td><td></td></tr>

<tr><td>Customer Type:</td><td width="60">&nbsp;</td><td><SELECT NAME="customer_type" >
<option value="Legal Entity">Legal Entity </option>
<option value="Physical">Physical</option>
</SELECT></td></tr>

<tr><td>Nationality:</td><td width="60">&nbsp;</td><td>
  <SELECT NAME="nationality" >
<option value="Motswana">Motswana </option>
<option value="Foreigner">Foreigner </option>
</SELECT>
</td></tr>

<tr><td>Borrower Name :</td><td width="60">&nbsp;</td><td>
<input name="name" type="text">
</td></tr>


<tr><td>Other NAmes :</td><td width="60">&nbsp;</td><td>
<input name="other_name" type="text">
</td></tr>

<tr><td>Surname :</td><td width="60">&nbsp;</td><td>
<input name="surname" type="text">
</td></tr>

<tr><td>Spouse,Partner,Co-Borrower Name :</td><td width="60">&nbsp;</td><td>
<input name="spouse_name" type="text">
</td></tr>

<tr><td>Spouse Surname:</td><td width="60">&nbsp;</td><td>
<input name="spouse_surname" type="text">
</td></tr>

<tr><td>Borrower Present Address :</td><td width="60">&nbsp;</td><td>
<input name="address" type="text">
</td></tr>

<tr><td>Street Name and Number :</td><td width="60">&nbsp;</td><td>
<input name="street_add" type="text">
</td></tr>

<tr><td>Twon:</td><td width="60">&nbsp;</td><td>
<input name="town" type="text">
</td></tr>

<tr><td>Country:</td><td width="60">&nbsp;</td><td>
<input name="name" type="text">
</td></tr>

<tr><td>Permanenet Country of Residence :</td><td width="60">&nbsp;</td><td><SELECT name="permanent_residence" >
<option value="Botswana">Botswana </option>
<option value="Other">Other </option>
</SELECT>

</td></tr>
</br>
</br>
    





<tr><td>Location of Acquired REal Estate:</td><td width="60">&nbsp;</td><td> <SELECT NAME="location" >
<option value="Major Urban">Major Urban </option>
<option value="Minor Urban">Minor Urban </option>
<option value="Rural">Rural </option>
</SELECT>
</td></tr>


<tr><td>Borrower Birthdate</td><td width="60">&nbsp;</td><td> 
            <?php

Function ShowFromDate($year_interval,$YearIntervalType) { 
GLOBAL $day,$month,$year; 
//DAY 
echo "<select name=day>\n"; 
$i=1; 
$CurrDay=date("d"); 
If(!IsSet($day)) $day=$CurrDay; 
while ($i <= 31) 
      { 
       If(IsSet($day)) { 
         If($day == $i || ($i == substr($day,1,1) && (substr($day,0,1) == 0))) { 
                  echo"<option selected> $day\n"; 
                  $i++; 
         }Else{ 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
                $i++; 
         } 
       }Else { 
              If($i == $CurrDay) 
                If($i<10) { 
                   echo "<option selected> 0$i\n"; 
                }Else { 
                   echo"<option selected> $i\n"; 
                } 
              Else { 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
              } 
              $i++; 
       } 
      } 
echo "</select>\n"; 

//MONTH 
echo " / <select name=month>\n"; 
$i=1; 
$CurrMonth=date("m"); 
while ($i <= 12) 
     { 
      If(IsSet($month)) { 
         If($month == $i || ($i == substr($month,1,1) && (substr($month,0,1) == 0))) { 
            echo"<option selected> $month\n"; 
            $i++; 
         }Else{ 
            If($i<10) { 
               echo "<option> 0$i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
         } 
      }Else { 
            If($i == $CurrMonth) { 
              If($i<10) { 
                 echo "<option selected> 0$i\n"; 
              }Else { 
                 echo "<option selected> $i\n"; 
              } 
            }Else { 
              If($i<10){ 
                 echo "<option> 0$i\n"; 
              }Else { 
                 echo "<option> $i\n"; 
              } 
            } 
            $i++; 
      } 
} 
  echo "</select>\n"; 

//YEAR 
  echo " / <select name=year>\n"; 
  $CurrYear=date("Y"); 
  If($YearIntervalType == "Past") { 
      $i=$CurrYear-$year_interval+1; 
      while ($i <= $CurrYear) 
           { 
            If($i == $year) { 
               echo "<option selected> $i\n"; 
            }ElseIf ($i == $CurrYear && !IsSet($year)) { 
               echo "<option selected> $i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  If($YearIntervalType == "Future") { 
      $i=$CurrYear+$year_interval; 
      while ($CurrYear < $i) 
           { 
            if ($year == $CurrYear) echo "<option selected> $CurrYear\n"; 
              else echo "<option> $CurrYear\n"; 
            $CurrYear++; 
           } 
       echo "</select>\n"; 
  } 
  If($YearIntervalType == "Both") { 
      $i=$CurrYear-$year_interval+1; 
      while ($i < $CurrYear+$year_interval) 
           { 
            if ($i == $CurrYear) echo "<option selected> $i\n"; 
              else echo "<option> $i\n"; 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  

} 

//Ussage Example : 
ShowFromDate(60,"Past");

?>
</td></tr>

 <tr><td>Spouse BirthDate:  </td><td width="60">&nbsp;</td><td>
            <?php
Function ShowFromDate2($year2_interval,$year2IntervalType) { 
GLOBAL $day2,$month2,$year2; 
//day2 
echo "<select name=day2>\n"; 
$i=1; 
$Currday2=date("d"); 
If(!IsSet($day2)) $day2=$Currday2; 
while ($i <= 31) 
      { 
       If(IsSet($day2)) { 
         If($day2 == $i || ($i == substr($day2,1,1) && (substr($day2,0,1) == 0))) { 
                  echo"<option selected> $day2\n"; 
                  $i++; 
         }Else{ 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
                $i++; 
         } 
       }Else { 
              If($i == $Currday2) 
                If($i<10) { 
                   echo "<option selected> 0$i\n"; 
                }Else { 
                   echo"<option selected> $i\n"; 
                } 
              Else { 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
              } 
              $i++; 
       } 
      } 
echo "</select>\n"; 

//month2 
echo " / <select name=month2>\n"; 
$i=1; 
$Currmonth2=date("m"); 
while ($i <= 12) 
     { 
      If(IsSet($month2)) { 
         If($month2 == $i || ($i == substr($month2,1,1) && (substr($month2,0,1) == 0))) { 
            echo"<option selected> $month2\n"; 
            $i++; 
         }Else{ 
            If($i<10) { 
               echo "<option> 0$i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
         } 
      }Else { 
            If($i == $Currmonth2) { 
              If($i<10) { 
                 echo "<option selected> 0$i\n"; 
              }Else { 
                 echo "<option selected> $i\n"; 
              } 
            }Else { 
              If($i<10){ 
                 echo "<option> 0$i\n"; 
              }Else { 
                 echo "<option> $i\n"; 
              } 
            } 
            $i++; 
      } 
} 
  echo "</select>\n"; 

//year2 
  echo " / <select name=year2>\n"; 
  $Curryear2=date("Y"); 
  If($year2IntervalType == "Past") { 
      $i=$Curryear2-$year2_interval+1; 
      while ($i <= $Curryear2) 
           { 
            If($i == $year2) { 
               echo "<option selected> $i\n"; 
            }ElseIf ($i == $Curryear2 && !IsSet($year2)) { 
               echo "<option selected> $i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  If($year2IntervalType == "Future") { 
      $i=$Curryear2+$year2_interval; 
      while ($Curryear2 < $i) 
           { 
            if ($year2 == $Curryear2) echo "<option selected> $Curryear2\n"; 
              else echo "<option> $Curryear2\n"; 
            $Curryear2++; 
           } 
       echo "</select>\n"; 
  } 
  If($year2IntervalType == "Both") { 
      $i=$Curryear2-$year2_interval+1; 
      while ($i < $Curryear2+$year2_interval) 
           { 
            if ($i == $Curryear2) echo "<option selected> $i\n"; 
              else echo "<option> $i\n"; 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  

} 
//Ussage Example : 
ShowFromDate2(60,"Past"); 
?>
</td></tr>
</br>
<tr><td>Wedding Date:  </td><td width="60">&nbsp;</td><td>
<?php
Function ShowFromDate22($year22_interval,$year22IntervalType) { 
GLOBAL $day22,$month22,$year22; 
//day22 
echo "<select name=day22>\n"; 
$i=1; 
$Currday22=date("d"); 
If(!IsSet($day22)) $day22=$Currday22; 
while ($i <= 31) 
      { 
       If(IsSet($day22)) { 
         If($day22 == $i || ($i == substr($day22,1,1) && (substr($day22,0,1) == 0))) { 
                  echo"<option selected> $day22\n"; 
                  $i++; 
         }Else{ 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
                $i++; 
         } 
       }Else { 
              If($i == $Currday22) 
                If($i<10) { 
                   echo "<option selected> 0$i\n"; 
                }Else { 
                   echo"<option selected> $i\n"; 
                } 
              Else { 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
              } 
              $i++; 
       } 
      } 
echo "</select>\n"; 

//month22 
echo " / <select name=month22>\n"; 
$i=1; 
$Currmonth22=date("m"); 
while ($i <= 12) 
     { 
      If(IsSet($month22)) { 
         If($month22 == $i || ($i == substr($month22,1,1) && (substr($month22,0,1) == 0))) { 
            echo"<option selected> $month22\n"; 
            $i++; 
         }Else{ 
            If($i<10) { 
               echo "<option> 0$i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
         } 
      }Else { 
            If($i == $Currmonth22) { 
              If($i<10) { 
                 echo "<option selected> 0$i\n"; 
              }Else { 
                 echo "<option selected> $i\n"; 
              } 
            }Else { 
              If($i<10){ 
                 echo "<option> 0$i\n"; 
              }Else { 
                 echo "<option> $i\n"; 
              } 
            } 
            $i++; 
      } 
} 
  echo "</select>\n"; 

//year22 
  echo " / <select name=year22>\n"; 
  $Curryear22=date("Y"); 
  If($year22IntervalType == "Past") { 
      $i=$Curryear22-$year22_interval+1; 
      while ($i <= $Curryear22) 
           { 
            If($i == $year22) { 
               echo "<option selected> $i\n"; 
            }ElseIf ($i == $Curryear22 && !IsSet($year22)) { 
               echo "<option selected> $i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  If($year22IntervalType == "Future") { 
      $i=$Curryear22+$year22_interval; 
      while ($Curryear22 < $i) 
           { 
            if ($year22 == $Curryear22) echo "<option selected> $Curryear22\n"; 
              else echo "<option> $Curryear22\n"; 
            $Curryear22++; 
           } 
       echo "</select>\n"; 
  } 
  If($year22IntervalType == "Both") { 
      $i=$Curryear22-$year22_interval+1; 
      while ($i < $Curryear22+$year22_interval) 
           { 
            if ($i == $Curryear22) echo "<option selected> $i\n"; 
              else echo "<option> $i\n"; 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  

} 

//Ussage Example : 
ShowFromDate22(60,"Past");

?>
</td></tr>

<tr><td>Divorce Date:</td><td width="60">&nbsp;</td><td>
<?php
Function ShowFromDate23($year23_interval,$year23IntervalType) { 
GLOBAL $day23,$month23,$year23; 
//day23 
echo "<select name=day23>\n"; 
$i=1; 
$Currday23=date("d"); 
If(!IsSet($day23)) $day23=$Currday23; 
while ($i <= 31) 
      { 
       If(IsSet($day23)) { 
         If($day23 == $i || ($i == substr($day23,1,1) && (substr($day23,0,1) == 0))) { 
                  echo"<option selected> $day23\n"; 
                  $i++; 
         }Else{ 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
                $i++; 
         } 
       }Else { 
              If($i == $Currday23) 
                If($i<10) { 
                   echo "<option selected> 0$i\n"; 
                }Else { 
                   echo"<option selected> $i\n"; 
                } 
              Else { 
                If($i<10) { 
                   echo "<option> 0$i\n"; 
                }Else { 
                   echo "<option> $i\n"; 
                } 
              } 
              $i++; 
       } 
      } 
echo "</select>\n"; 

//month23 
echo " / <select name=month23>\n"; 
$i=1; 
$Currmonth23=date("m"); 
while ($i <= 12) 
     { 
      If(IsSet($month23)) { 
         If($month23 == $i || ($i == substr($month23,1,1) && (substr($month23,0,1) == 0))) { 
            echo"<option selected> $month23\n"; 
            $i++; 
         }Else{ 
            If($i<10) { 
               echo "<option> 0$i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
         } 
      }Else { 
            If($i == $Currmonth23) { 
              If($i<10) { 
                 echo "<option selected> 0$i\n"; 
              }Else { 
                 echo "<option selected> $i\n"; 
              } 
            }Else { 
              If($i<10){ 
                 echo "<option> 0$i\n"; 
              }Else { 
                 echo "<option> $i\n"; 
              } 
            } 
            $i++; 
      } 
} 
  echo "</select>\n"; 

//year23 
  echo " / <select name=year23>\n"; 
  $Curryear23=date("Y"); 
  If($year23IntervalType == "Past") { 
      $i=$Curryear23-$year23_interval+1; 
      while ($i <= $Curryear23) 
           { 
            If($i == $year23) { 
               echo "<option selected> $i\n"; 
            }ElseIf ($i == $Curryear23 && !IsSet($year23)) { 
               echo "<option selected> $i\n"; 
            }Else { 
               echo "<option> $i\n"; 
            } 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  If($year23IntervalType == "Future") { 
      $i=$Curryear23+$year23_interval; 
      while ($Curryear23 < $i) 
           { 
            if ($year23 == $Curryear23) echo "<option selected> $Curryear23\n"; 
              else echo "<option> $Curryear23\n"; 
            $Curryear23++; 
           } 
       echo "</select>\n"; 
  } 
  If($year23IntervalType == "Both") { 
      $i=$Curryear23-$year23_interval+1; 
      while ($i < $Curryear23+$year23_interval) 
           { 
            if ($i == $Curryear23) echo "<option selected> $i\n"; 
              else echo "<option> $i\n"; 
            $i++; 
           } 
       echo "</select>\n"; 
  } 
  

} 

//Ussage Example : 
ShowFromDate23(60,"Past");

?> </td></tr>
<tr><td>Marital Status</td><td width="60"></td><td> <SELECT NAME="marital-status"> 
<option value="Single">Single</option>
<option value="Married">Married </option>
<option value="Divorced">Divorced </option>
<option value="Widowed">Widowed </option>
<option value="Other">Divorced </option>
</SELECT> </td></tr>

<tr><td>Marital Contract Type</td><td width="60"></td><td> <SELECT NAME="marital_contract_type" >
<option value="Community of Property">Community of Property</option>
<option value="Other marital Contract">Other marital Contract </option>
<option value="na">na </option>
</SELECT></td></tr>

<tr><td>Spouse/Partner Borrowing Relationship</td><td width="60"></td><td> <SELECT NAME="spouse_borrowing_relationship">
<option value="Co-borrower/Guanrator">Co-borrower/Guanrator</option>
<option value="Other">Other </option>
<option value="na">na</option>

</SELECT></td></tr>

<tr><td>Number of Dependats </td><td width="60"></td><td><SELECT NAME="number_of_dependants">
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
</td></tr>

<tr><td>Children Living at home:</td><td width="60"></td><td> <input type="text" name="no_of_children"> </td></tr>

<tr><td>Children living at home 0 to 12 years of age
 </td><td width="60"></td><td><SELECT NAME="0to12">
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
<tr><td>Children living at home 13 to 18 years of age</td><td width="60"></td><td><SELECT NAME="13to18">
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
<tr><td>Children living at home 19 to 26 years of age</td><td width="60"></td><td><SELECT NAME="19to26">
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

<tr><td>Total Dependants Living at home:</td><td width="60"></td><td> <input type="text" name="dependants_at_home"> </td></tr>

<tr><td>Other dependants Living in Household GrandParents</td><td width="60"></td><td><SELECT NAME="0to12">
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
Aunts/Uncles/Cousins</td><td width="60"></td><td><SELECT NAME="aunts_uncles_cousins">
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
<tr><td>Other</td><td width="60"></td><td><SELECT NAME="other">
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

<tr><td>Years At Present Address:</td><td width="60"></td><td> <SELECT NAME="Address" >
<option value="0 to 3">0 to 3</option>
<option value="3 to 5">3 to 5</option>
<option value="5 to 10">5 to 10</option>
<option value="+10">+10</option>
</SELECT></td></tr>

<tr><td>Monthly payment (rental/instalment):</td><td width="60"></td><td> <input type="text" name="rent"> </td></tr>



<tr><td>Borrower Education:</td><td width="60"></td><td> <SELECT NAME="education" >
<option value="University">University </option>
<option value="Technical School">Technical School</option>
<option value="Secondary School">Secondary School</option>
<option value="Primary Education">Primary Education</option>
<option value="None">None</option>
</SELECT></td></tr>

<tr><td>Professional Category:</td><td width="60"></td><td> <SELECT NAME="Professional" >
<option value="Self Employed">Self Employed</option>
<option value="Civil Servant">Civil Servant</option>
<option value="Employee">Employee</option>
<option value="Other">Other</option>
</SELECT>
</td></tr>

<tr><td>Employment Contract:</td><td width="60"></td><td> <SELECT NAME="Employment" >
<option value="Permanent">Permanent</option>
<option value="Contractual">Contractual</option>
<option value="Other">Other</option>
</SELECT></td></tr>

<tr><td>Number Of Years Working at Present Employer:</td><td width="60"></td><td> <SELECT NAME="Years" >
<option value="0 to 1">0 to 1</option>
<option value="1 to 5">1 to 5</option>
<option value="5 to 20">5 to 20</option>
<option value="20 and more">20 and more</option>
</SELECT></td></tr>

<tr><td>Household Professional Revenues:</td><td width="60"></td><td> <SELECT NAME="Revenues" >
<option value="Single">Single</option>
<option value="Double">Double</option>
</SELECT></td></tr>

<tr><td>Professional Revenues (Gross amounts) Borrower: </td><td width="60"></td><td></td></tr>

<tr><td>Annual Salary Amount (Gross): </td><td width="60"></td><td><input type="text" name="annual_sal" onchange="return calcTotal()"></td></tr>

<tr><td>Fixed Permanent  allowances (pa):</td><td width="60"></td><td> <input type="text" name="fixed_perm_allowances" onchange="return calcTotal()"></td></tr>


<tr><td>Total Revenues for affordability: </td><td width="60"></td><td><input type="text" name="total_rev_for_afford" onFocus="this.blur();"></td></tr>

<tr><td>HouseHold Partner:</td><td width="60"></td><td> <input type="text" name="household_partner"></td></tr>

<tr><td>Household Partner- Annual Salary Amount (Gross): </td><td width="60"></td><td><input type="text" name="hp_annual_sal" onchange="return calcTotal()"></td></tr>

<tr><td>Household Partner- Fixed Permanent  allowances (pa):</td><td width="60"></td><td> <input type="text" name="hp_fixed_perm_allowances" onchange="return calcTotal()"></td></tr>


<tr><td>Household Partner- Total Revenues for affordability: </td><td width="60"></td><td><input type="text" name="hp_total_rev_for_afford" onFocus="this.blur();"></td></tr>

<tr><td>TOTAL Allowable Household Revenues: </td><td width="10"></td><td><input type="text" name="total_allowable_household_revenues"></td></tr>



<tr><td>Main Bank:</td><td width="10"></td><td> <SELECT NAME="MainBank" >
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

</SELECT></td></tr>


<tr><td>Second Bank: </td><td width="10"></td><td><SELECT NAME="SecondBank" >
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

</SELECT></td></tr>

<tr><td>Third Bank: </td><td width="10"></td><td><SELECT NAME="ThirdBank" >
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
</SELECT></td></tr>

<tr><td>Age Of Relationshp with BBS:</td><td width="10"></td><td> <SELECT NAME="relationship" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>


<tr><td>Savings Account:</td><td width="10"></td><td> <SELECT NAME="Savings">
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Deposit Account: </td><td width="10"></td><td><SELECT NAME="Deposit" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Share Account: </td><td width="10"></td><td><SELECT NAME="Share" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>ST Loans: </td><td width="10"></td><td><SELECT NAME="ST" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>

<tr><td>Mortgages: </td><td width="10"></td><td><SELECT NAME="Mortgages" >
<option value="NA">NA</option>
<option value="Since less than 1 year">Since less than 1 year</option>
<option value="Since 1 to 5 years">Since 1 to 5 years</option>
<option value="More than 5 years">More than 5 years</option>
</SELECT></td></tr>


<tr><td>TOTAL BBS Products: </td><td width="10"></td><td><input type="text" name="total_bbs_products"></td></tr>

<tr><td >BBS arrears for over 30days in last 12mnths?</br> 
</td><td width="10"></td><td><SELECT NAME="loan_arrears">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT></td></tr>


<tr><td >REnegotiated loans with arreas in past 24 months?</br> 
</td><td width="10"></td><td><SELECT NAME="renegotiate">
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
</SELECT>



<tr><td>Number of credit card personally held</td><td width="10"></td><td>
<SELECT NAME="cards_held">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT>



<tr><td>Card held since (in years) </td><td width="10"></td><td><SELECT NAME="card_held_since">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">greater than 5</option>
</SELECT>
</td></tr>

<tr><td>Number of loans presently outstanding </td><td width="10"></td><td><SELECT NAME="loans_outstanding">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
</SELECT>
</td></tr>


<tr><td>ITC REF NP.</td><td width="60">&nbsp;</td><td><input type="text" name="itc_ref"></td></tr>
<tr><td>ITC SUBJECT NUMBER</td><td width="60">&nbsp;</td><td><input type="text" name="itc_sub_no"></td></tr>

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