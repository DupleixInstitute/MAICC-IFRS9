
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CORPORATE CREDIT SCORING - Check Loan Status</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php





$company_reg_no=NULL; 
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password=""; // Mysql password 
$db_name="corporatescoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;
//echo $_POST['myid']; echo "TEST";
$company_reg_no=$_POST['myid'];
$loan_number=$_POST['loan_number'];
$connect=mysqli_connect($host,$username,$password,$db_name); 
  if (!$connect) {
  mysqli_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password."; 
}
else {
mysqli_select_db($connect,$db_name)
or die("Couldn't open $dbase: ".mysqli_error());

}
$sql1="SELECT loan_data.legal_name as name, 
              loan_data.customer_type, 
			  approved.status as status, 
			  approved.date as creation 
	   FROM   loan_data,approved 
       WHERE  loan_data.company_reg_no=approved.company_reg_no and 
	          approved.company_reg_no= '$company_reg_no' and 
			  loan_data.loan_number=$loan_number";

$result=mysqli_query($connect,$sql1);
	
if (!$result)
  {
  die('Error2: '. mysqli_error($connect));
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
   <tr>
      <td><font color="#000066" size="4" class="btn"><strong>LOANS STATUS</strong></font></td>
    </tr>
        <tr bgcolor="#ECF8FF" class="s_text"> 
 
        <p></br> 
        <form name="editform" method="POST">
          <tr><td><p align="justify" class="s_text"><br>
            <table width="750" height="57" border="0" align="center"  class="s_text"   bgcolor="#ECF8FF" >
              
              <?php
$mycount=0;
while($rows=mysqli_fetch_array($result)){
$mycount++; 
//echo $rows['bbsAccount'];
?>



<tr> 
                  <td width="750" bgcolor="#ECF8FF" ><strong>Name:</strong> <?php echo $rows['name']; ?> 
                    &nbsp;&nbsp;<?php echo $rows['customer_type']; ?></td>
                <td width="220" bgcolor="#ECF8FF" >&nbsp;</td>

              </tr>
			  <tr> 
                  <td width="220" bgcolor="#ECF8FF" ><strong>Reg No:</strong> <?php echo $company_reg_no; ?> 
                  </td>  <td width="220" bgcolor="#ECF8FF" >&nbsp;</td>

              </tr> <tr><td></td><td></td></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr>
              <tr> 
                <td width="750" bgcolor="#ECF8FF" >
				
				<?php 
				if ($rows['status']=="")
				{
				echo "This loan is still pending and was captured ";
				}
				else if ($rows['status']=="not approved")
				{
				echo "This loan was";
				}
				else
				{
				echo "This loan has been ";
				}
				?>
				<strong><font color="#FF6600"><?php echo $rows['status']; ?></font></strong> at <font color="#0066CC"><?php echo $rows['creation']; ?></font></strong></td>
                <td width="220" bgcolor="#ECF8FF" >&nbsp;</td>
				
                <?php
}
?>
              </tr>
              <tr> </tr>
            </table>
            </br></br> 
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index.php';"><br><br>

</form>