
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php

// Connect to server and select databse.
include("assets/includes/authenticate.php");
//Open database connection
include ("assets/includes/database_connection.php");
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 


if (isset($_POST['day2']))   { $day    = $_POST['day2'];   }
if (isset($_POST['month2'])) { $month  = $_POST['month2']; }
if (isset($_POST['year2']))  { $year   = $_POST['year2'];  }

if (isset($_POST['day22']))  { $day2   = $_POST['day22'];  }
if (isset($_POST['month22'])){ $month2 = $_POST['month22'];}
if (isset($_POST['year22'])) { $year2  = $_POST['year22']; }

$statistics = [];
$statistic['mortgage'      ]['approved']        = 0;
$statistic['mortgage'      ]['not approved']    = 0;
$statistic[''              ]['approved']        = 0;
$statistic[''              ]['not approved']    = 0;
$statistic['unsecured_loan']['approved']     	= 0;
$statistic['unsecured_loan']['not approved'] 	= 0;

if (isset($day)) {
   $sql1="SELECT cust_information.omang_passport_num as omang,
                 cust_information.loan_number as loan_number, 
			     cust_information.loan_category as loan_category, 
			     approved.status as status
	      FROM   approved,cust_information  
          WHERE  cast(approved.date as date)=$year$month$day and
		         cust_information.omang_passport_num = approved.omang;";
}

if (isset($day2)) {
   $sql1="SELECT cust_information.omang_passport_num as omang,
                 cust_information.loan_number as loan_number, 
			     cust_information.loan_category as loan_category, 
			     approved.status as status
	      FROM   approved,cust_information  
          WHERE  cast(approved.date as date)=$year2$month2$day2 and
		         cust_information.omang_passport_num = approved.omang;";
}


if(isset($day) && isset($day2)) {
 $sql1="SELECT cust_information.omang_passport_num as omang,
               cust_information.loan_number as loan_number, 
			   cust_information.loan_category as loan_category, 
			   approved.status as status
	    FROM   approved,cust_information 
	    WHERE  cast(approved.date as date)between $year$month$day and $year2$month2$day2 and
		   	   cust_information.omang_passport_num = approved.omang;";
}


if(empty($day) && empty($day2)) {
   $sql1="SELECT cust_information.omang_passport_num as omang,
               cust_information.loan_number as loan_number, 
			   cust_information.loan_category as loan_category, 
			   approved.status as status
	    FROM   approved,cust_information 
	    WHERE  cust_information.omang_passport_num = approved.omang;";
}

$result=mysqli_query($connect,$sql1);
  
if (!$result){
  die('Error2: ' . mysqli_error());
}

?>
  
<?php
  
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
   <tr>
      <td><font color="#000066" size="4" class="btn"><strong>LOANS BY STATUS</strong></font></td>
    </tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="800" height="57" border="0" align="center"  class="s_text"   bgcolor="#000066">
              <tr> 
                  <td width="200" align = center ><strong><font color="#FFFFFF">STATUS</font></strong></td>
                  <td width="200" align = center><strong><font color="#FFFFFF">Mortgages<br>Count</font></strong></td>
                  <td width="200" align = center><strong><font color="#FFFFFF">Unsecured Loans<br>Count</font></strong></td>
                  <td width="200" align = center><strong><font color="#FFFFFF">TOTAL</font></strong></td>
              </tr>
                <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
  $mycount++; 
  $loan_category = ($rows['loan_category'] == '')? 'mortgage':$rows['loan_category'];
  $statistic[$loan_category][$rows['status']]++;
}
$total_mortgages       = $statistic['mortgage'      ]['not approved'] + $statistic['mortgage'      ]['approved'];
$total_unsecured_loan  = $statistic['unsecured_loan']['not approved'] + $statistic['unsecured_loan']['approved'];
$total_approved        = $statistic['mortgage'      ]['approved'] + $statistic['unsecured_loan'    ]['approved'];
$total_notapproved     = $statistic['mortgage'      ]['not approved'] + $statistic['unsecured_loan' ]['not approved'];

?>
              <tr> 
                <td width="300" bgcolor="#FFFFFF" align = center >Approved</td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $statistic['mortgage']['approved']; ?></td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $statistic['unsecured_loan']['approved']; ?></td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $total_approved; ?></td>
              </tr>
              <tr> 
                <td width="300" bgcolor="#FFFFFF" align = center >Not Approved</td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $statistic['mortgage']['not approved']; ?></td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $statistic['unsecured_loan']['not approved']; ?></td>
                <td width="220" bgcolor="#FFFFFF" align = center ><?php echo  $total_notapproved; ?></td>
              </tr>
                <?php

?>
              </tr>
              <tr>  
			      <td width="220" bgcolor="#FFFFFF" align = center><strong>TOTAL</strong></td>
                  <td width="220" bgcolor="#FFFFFF" align = center><strong><?php echo $total_mortgages;?></strong></td>
                  <td width="220" bgcolor="#FFFFFF" align = center><strong><?php echo $total_unsecured_loan;?></strong></td>
                  <td width="220" bgcolor="#FFFFFF" align = center><strong><?php echo $total_approved+$total_notapproved;?></strong></td>
			  </tr>
                <tr><td><input type="hidden" name="username"  value="<?php echo $username;?>"/></td></tr>
                <tr><td><input type="hidden" name="password"  value="<?php echo $password; ?>"/></td></tr>

            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index2.php';"><br><br>

</form>