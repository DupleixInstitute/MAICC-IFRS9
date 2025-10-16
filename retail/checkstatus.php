
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>
<link href="btu.css" rel="stylesheet" type="text/css">

<script src="assets/js/sweetalert.min.js"></script>
<script src="assets/js/jquery.min.js"></script>


</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">

<?php
$omang_passport_num= NULL; 
$pass              = $_POST['password'];

// Connect to server and select databse.
include("assets/includes/authenticate.php");
//Open database connection
include ("assets/includes/database_connection.php");

$ip          = gethostbyname($_SERVER['REMOTE_ADDR']); 

$omang       = $_POST['myid'];
$loan_number = $_POST['loan_number'];

$sql1="SELECT 
             cust_information.borrower_name as name, 
	         cust_information.surname       as surname, 
	         approved.status                as status, 
	         approved.date                  as creation 

       FROM  cust_information, approved 
	   WHERE cust_information.omang_passport_num = approved.omang and 
	         approved.omang                      = '$omang'         and 
			 cust_information.loan_number        = $loan_number";

$result      = mysqli_query($connect,$sql1);
  
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
		&nbsp;&nbsp;<?php echo $rows['surname']; ?></td>
		<td width="220" bgcolor="#ECF8FF" >&nbsp;</td>
		</tr>
		<tr> 
        <td width="220" bgcolor="#ECF8FF" ><strong>Id No:</strong> <?php echo $omang; ?> 
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
&nbsp;&nbsp;<input type="submit" name="submit" class="btn" value="BACK" onclick="form.action='index2.php';"><br><br>

</form>