
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

$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;

if (isset($_POST['day2']))   { $day    = $_POST['day2'];   }
if (isset($_POST['month2'])) { $month  = $_POST['month2']; }
if (isset($_POST['year2']))  { $year   = $_POST['year2'];  }

if (isset($_POST['day22']))  { $day2   = $_POST['day22'];  }
if (isset($_POST['month22'])){ $month2 = $_POST['month22'];}
if (isset($_POST['year22'])) { $year2  = $_POST['year22']; }

$mortages_counter       = 0;
$unsecured_loan_counter = 0;
$total_counter          = 0;

if (isset($day)) {
$sql1="SELECT 
             gender ,
             COUNT(CASE WHEN loan_category <> 'unsecured_loan'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   WHERE cast(creation as date)=$year$month$day 
	   GROUP BY gender 
	   ORDER BY total_count DESC;";
}

if (isset($day2)) {
$sql1="SELECT 
             gender ,
             COUNT(CASE WHEN loan_category <> 'unsecured_loan'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   WHERE cast(creation as date)=$year2$month2$day2 
	   GROUP BY gender 
	   ORDER BY total_count DESC;";
}


if(isset($day) && isset($day2))
{
$sql1="SELECT 
             gender ,
             COUNT(CASE WHEN loan_category <> 'unsecured_loan'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information 
	   WHERE cast(creation as date) BETWEEN $year$month$day AND $year2$month2$day2  
	   GROUP BY gender 
	   ORDER BY total_count DESC;";
}




if(empty($day) && empty($day2)){ 
$sql1="SELECT 
             gender ,
             COUNT(CASE WHEN loan_category <> 'unsecured_loan'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   group by gender order by total_count DESC;";
}
$result=mysqli_query($connect,$sql1);
  
if (!$result)
{
  die('Error2: ' . mysqli_error());
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>GENDER COUNTS
   
   </strong></strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="600" height="57" border="0" align="center" bordercolor="#000066" bgcolor="#000066"  class="s_text" >
              <tr> 
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">GENDER TYPE</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">MORTAGES<BR>(COUNT)</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">UNSECURED LOAN<BR>(COUNT)</font></strong></td>
                  <td width="150" bgcolor="#000066" align="center"><strong><font color="#FFFFFF">TOTAL<BR>(COUNT)</font></strong></td>				  
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
    $mortages_counter       += $rows['count_mortgage'];
	$unsecured_loan_counter += $rows['count_unsecured_loan'];
	$total_counter          += $rows['total_count'];
	$mycount++; 
//echo $rows['bbsAccount'];
?>
              <tr> 
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['gender'];?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['count_mortgage'];?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['count_unsecured_loan'];?></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><?php echo $rows['total_count']; ?></td>
				
                <?php
}
?>
              </tr>
              <tr> 
                <td width="150" bgcolor="#FFFFFF" align="center"><strong>TOTAL</strong></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $mortages_counter;?></strong></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $unsecured_loan_counter;?></strong></td>
                <td width="150" bgcolor="#FFFFFF" align="center"><strong><?php echo $total_counter; ?></strong></td>
			  </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='status_report.php'"><br><br>

</form>