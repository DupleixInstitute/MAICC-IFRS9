
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CORPORATE CREDIT SCORING - Approved Versus Disapproved</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">



</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php
//error_reporting(0);
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

$new=$bbs.$username;
$ldappass=$_POST['password'];
/**
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


**/





$pass="";
$host="localhost"; // Host name 
$user="root";
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysqli_connect($host,$user,$pass,$db_name); 
//echo $pass;
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
  die();
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error());

}


?>

<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" bgcolor="#ECF8FF">
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
            <tr><td align="center"><font color="#000066" size="4" class="btn"><strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;APPROVED VS DISAPPROVED</strong></font></td></tr>
      
        <form ACTION="approved_disapproved.php" name="returned" method="post">
            <td><table><tr><td><p align="justify" class="s_text"><br>


 <tr><td>
</td><td>
Date1:&nbsp;&nbsp;&nbsp;<input name="r3" type="checkbox" onclick="day2.disabled=true
year2.disabled=true
month2.disabled=true
" onDblClick="day2.disabled=false
year2.disabled=false
month2.disabled=false" value=""/>n/a: &nbsp;&nbsp;&nbsp;
</td><td>
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
<tr><td></td><td>Date2:&nbsp;&nbsp; <input name="r2" type="checkbox" onclick="day22.disabled=true
year22.disabled=true
month22.disabled=true
" onDblClick="day22.disabled=false
year22.disabled=false
month22.disabled=false" value=""/>n/a:&nbsp;&nbsp;&nbsp;</td><td>
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
<TR>
<TD align="right">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="VIEW" ></TD></TR>
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