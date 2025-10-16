 <HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>
<script type="text/javascript" language="JavaScript1.2" src="file:///Z|/Desktop/BBS_website/stmenu.js"></script>
<script language="JavaScript">


</script>
<link href="btu.css" rel="stylesheet" type="text/css">
</HEAD>
<BODY BGCOLOR=#FFFFFF >
<p><font class="s_text"> 
<?php

$username=$_POST['username'];
$password=$_POST['password'];
echo $username;

// Connect to server and select databse.
include("assets/includes/authenticate.php");

//Open database connection
include ("assets/includes/database_connection.php");

$cif=$_POST['cif'];
$loan_number=$_POST['loan_number'];
$omang=$_POST['omang'];
?>
 
<?php
//if($cif=2222){
//echo "<meta http-equiv=\"refresh\" content=\"0;URL=failed.php\">";
//die();
//}
//else

//echo "correct";
//echo $age."eee".$myyear;

?>





<?php
	
$sql2="UPDATE cust_information SET cif='$cif' where omang_passport_num='$omang' and loan_number='$loan_number'"; 

$update=mysqli_query($connect,$sql2);

if($update){
  echo "done";
{
  else
{
  echo "failed";
}
mysqli_close($connect);
?>



</BODY>
</HTML>
