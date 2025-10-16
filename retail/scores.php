
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php
include ("assets/includes/authenticate.php");        // Connect to server and select databse.
include ("assets/includes/database_connection.php"); //Open database connection;
$ip                  =  gethostbyname($_SERVER['REMOTE_ADDR']); 

$omang_passport_num  =  $_POST['omang'];

if (isset($_POST['day2']))   { $day    = $_POST['day2'];   }
if (isset($_POST['month2'])) { $month  = $_POST['month2']; }
if (isset($_POST['year2']))  { $year   = $_POST['year2'];  }

if (isset($_POST['day22']))  { $day2   = $_POST['day22'];  }
if (isset($_POST['month22'])){ $month2 = $_POST['month22'];}
if (isset($_POST['year22'])) { $year2  = $_POST['year22']; }

$statistics = [];
$statistic[1]['mortgage']        = 0;
$statistic[2]['unsecured_loan']  = 0;



if (isset($day)) {
$sql1="SELECT  count(*) as count, cust_credit_rating.rate as rate from cust_credit_rating where cust_credit_rating.rate IS NOT NULL and cast(cust_credit_rating.date as date)=$year$month$day group by cust_credit_rating.rate;";
}

if (isset($day2)) {

$sql1="SELECT  count(*) as count, cust_credit_rating.rate as rate from cust_credit_rating where cust_credit_rating.rate IS NOT NULL and cast(cust_credit_rating.date as date)=$year2$month2$day2 group by cust_credit_rating.rate;";
}


if(isset($day) && isset($day2)) {

$sql1=	"SELECT  
				cust_credit_rating.rate as rate, 
				COUNT(CASE WHEN cust_information.loan_category <> 'unsecured_loan' THEN cust_information.omang_passport_num END) as count_mortgage,
				COUNT(CASE WHEN cust_information.loan_category =  'unsecured_loan' THEN cust_information.omang_passport_num END) as count_unsecured_loan,
				COUNT(*) as total_count
		FROM 	cust_credit_rating, cust_information
		WHERE 	cust_credit_rating.omang_passport_num = cust_information.omang_passport_num	AND
				cust_credit_rating.rate IS NOT NULL 										AND 
				cast(cust_credit_rating.date as date) 	BETWEEN $year$month$day 			AND 
																$year2$month2$day2 
		GROUP 	by cust_credit_rating.rate;";
}


if(empty($day) && empty($day2)) {

$sql1="SELECT  count(*) as count,rate as rate from cust_credit_rating where rate IS NOT NULL group by rate";
}



$result=mysqli_query($connect,$sql1);
  
if (!$result)
  {
  die('Error2: ' . mysqli_error());
  }
  
  
  if (isset($day)) {

$aql3="SELECT  * from cust_credit_rating where cust_credit_rating.rate IS NOT NULL and cast(cust_credit_rating.date as date)=$year$month$day;";
}

if (isset($day2)) {

$aql3="SELECT  * from cust_credit_rating where cust_credit_rating.rate IS NOT NULL and cast(cust_credit_rating.date as date)=$year2$month2$day2;";
}


if(isset($day) && isset($day2)) {

$aql3="SELECT  * from cust_credit_rating where cust_credit_rating.rate IS NOT NULL and cast(cust_credit_rating.date as date) between $year$month$day and $year2$month2$day2;";
}


if(empty($day) && empty($day2)) {

$aql3="SELECT * from cust_credit_rating where rate IS NOT NULL ";
}


$result3=mysqli_query($connect,$aql3);
  
if (!$result3)
  {
  die('Error3: ' . mysqli_error());
  }
$count3=mysqli_num_rows($result3);
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>CUSTOMER CREDIT RATINGS</strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="700" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="250" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">CREDIT RATING</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">MORTGAGES<BR>(count)</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">UNSECURED LOANS<BR>(Count)</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">TOTAL<br>(Count)</font></strong></td>
              </tr>
              <?php
$mycount=0;
$mortgage_counter 		= 0;
$unsecured_loan_counter = 0;
$total_counter          = 0;
while($rows=mysqli_fetch_array($result)){
    $mycount++; 
	$mortgage_counter 		+=  $rows['count_mortgage'];
	$unsecured_loan_counter	+=	$rows['count_unsecured_loan'];
	$total_counter			+=	$rows['total_count'];
?>
              <tr> 
                <td width="250" bgcolor="#FFFFFF" align="center"><?php echo $rows['rate']; ?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['count_mortgage']; ?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['count_unsecured_loan']; ?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['total_count']; ?></td>
                <?php
}
?>
              </tr>
              </tr>
              <tr><td width="250" bgcolor="#FFFFFF" align="center"><strong>TOTAL</strong></td>
                  <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $mortgage_counter; ?></strong></td>
                  <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $unsecured_loan_counter; ?></strong></td>
                  <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $total_counter; ?></strong></td>
			 </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index2.php';"><br><br>

</form>