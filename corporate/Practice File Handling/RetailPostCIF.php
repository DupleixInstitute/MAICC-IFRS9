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
  //$username='root';
//$passwd='sefalana2008';

$cif=$_POST['cif'];
$username=$_POST['username'];
$loan_number=$_POST['loan_number'];
$omang=$_POST['omang'];
$password=$_POST['password'];
$db_name="creditscoring";
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
$con = mysqli_connect("localhost","root","",$db_name);
if (!$con)
  {
  die('Could not connect: ' . mysqli_error());
  }

mysqli_select_db($con,$db_name);
	
$sql2="UPDATE cust_information SET cif='$cif' where omang_passport_num='$omang' and loan_number='$loan_number'"; 
$update=mysqli_query($con,$sql2);

if($update)
{
echo "done";
{
else
{
echo "failed";
}
?>



</BODY>
</HTML>
