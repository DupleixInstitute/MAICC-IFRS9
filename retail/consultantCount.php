
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

$ip                = gethostbyname($_SERVER['REMOTE_ADDR']); 

//echo $ip;

$omang_passport_num = $_POST['omang'];

if (isset($_POST['day2']))   { $day    = $_POST['day2'];   }
if (isset($_POST['month2'])) { $month  = $_POST['month2']; }
if (isset($_POST['year2']))  { $year   = $_POST['year2'];  }

if (isset($_POST['day22']))  { $day2   = $_POST['day22'];  }
if (isset($_POST['month22'])){ $month2 = $_POST['month22'];}
if (isset($_POST['year22'])) { $year2  = $_POST['year22']; }

$statistics = [];
$statistic['mortgage']        = 0;
$statistic['unsecured_loan']  = 0;

if (isset($day)) {

$sql1="SELECT 
             username,
             COUNT(CASE WHEN loan_category <> 'unsecured_loan'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   WHERE username IS NOT NULL AND username!='' and cast(creation as date)=$year$month$day group by username order by total_count DESC;";
}

if (isset($day2)) {

$sql1="SELECT 
             username,
             COUNT(CASE WHEN loan_category <> 'mortgage'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   WHERE username IS NOT NULL AND username!='' and cast(creation as date)=$year2$month2$day2  group by username order by total_count DESC;";
}

if(isset($day) && isset($day2)) {
$sql1="SELECT 
             username,
             COUNT(CASE WHEN loan_category <> 'mortgage'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information 
	   WHERE username IS NOT NULL AND username!='' and cast(creation as date) between $year$month$day and $year2$month2$day2 group by username order by total_count DESC;";
}


if(empty($day) && empty($day2)) {

$sql1="SELECT 
             username,
             COUNT(CASE WHEN loan_category <> 'mortgage'       THEN omang_passport_num END) as count_mortgage,
             COUNT(CASE WHEN loan_category =  'unsecured_loan' THEN omang_passport_num END) as count_unsecured_loan,
             COUNT(*) as total_count
	   FROM  cust_information  
	   WHERE username IS NOT NULL AND username!='' group by username order by total_count DESC;";
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
   <tr><td><font color="#000066" size="4" class="btn"><strong>TOTAL SCORED BY EACH CONSULTANT</strong></font></td></tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="600" height="128" border="0" align="center" bgcolor="#000066" class="s_text">
              <tr> 
                  <td width="200" align="center" bgcolor="#000066"><strong><font color="#FFFFFF">USERNAME</font></strong></td>
                  <td width="200" align="center" bgcolor="#000066"><strong><font color="#FFFFFF">MORTGAGE<br>(Count)</font></strong></td>
                  <td width="150" align="center" bgcolor="#000066"><strong><font color="#FFFFFF">UNSECURED LOAN<BR>(Count)</font></strong></td>
                  <td width="150" align="center" bgcolor="#000066"><strong><font color="#FFFFFF">TOTAL<BR>(Count)</font></strong></td>
              </tr>
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
     $mycount++; 
	 $statistic['mortgage'      ]+= $rows['count_mortgage'];
	 $statistic['unsecured_loan']+= $rows['count_unsecured_loan'];

//echo $rows['bbsAccount'];
?>
              <tr> 
                <td bgcolor="#FFFFFF" width="150" align="center"><?php echo $rows['username']; ?></td>
                <td bgcolor="#FFFFFF" width="150" align="center"><?php echo $rows['count_mortgage']; ?></td>
                <td bgcolor="#FFFFFF" width="150" align="center"><?php echo $rows['count_unsecured_loan']; ?></td>
                <td bgcolor="#FFFFFF" width="150" align="center"><?php echo $rows['total_count']; ?></td>
                <?php 
}

$total_loans = $statistic['mortgage'] + $statistic['unsecured_loan'];
?>
              </tr>
			   

              <tr>
			        <td bgcolor="#FFFFFF" width="150" align="center"><strong>TOTAL</strong></td>
			        <td bgcolor="#FFFFFF" width="150" align="center"><strong><?php echo $statistic['mortgage'];?></strong></td>
			        <td bgcolor="#FFFFFF" width="150" align="center"><strong><?php echo $statistic['unsecured_loan'];?></strong></td>
                    <td bgcolor="#FFFFFF" width="150" align="center"><strong><?php echo $total_loans;?></strong></td>
			  
			  </tr>
            </table>
            </br></br> 
<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index2.php';"><br><br>

</form>