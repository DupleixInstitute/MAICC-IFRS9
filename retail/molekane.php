 <HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>SOUR PROJECT</TITLE>
<script type="text/javascript" language="JavaScript1.2" src="file:///Z|/Desktop/BBS_website/stmenu.js"></script>
<script language="JavaScript">
<!--

function enable_text(status)
{
status=!status; 
document.f1.one.disabled = status;
}

function enable_text2(status)
{
status=!status; 
document.f1.pone.disabled = status;
}


function calc_total_afford_rev(objTextBox){
 alert("a valid email address must have an @ in it");
 
 }
//objTextBox is the box from which the onchange is called
function calc_total_afford_revx(objTextBox){
 //get the quantity
 var annualsalary = document.f1.annual_sal.value;
 
 //get the discount value (from the objTextBox)
 var fixedpermanentallowance = objTextBox.value;

 //set the new price
  // first cast the two values as numbers
  // The value of a text box is a string so we use
  // parseFloat to cast as number
  annualsalary = parseFloat(annualsalary);

  // we can do the division on discount here too
  fixedpermanentallowance= parseFloat(fixedpermanentallowance) ;

  var total = annualsalary + fixedpermanentallowance;

  document.f1.total_rev_for_afford = total;
}



</script>
<link href="btu.css" rel="stylesheet" type="text/css">
</HEAD>
<BODY BGCOLOR=#FFFFFF >
<font class="s_text"> 
<?php
$pass=$_POST['password'];
$host="localhost"; // Host name 
$username="root"; // Mysql username 
$password="sefalana2008"; // Mysql password 
$db_name="sour"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 



$number_of_dependants=$_POST['number_of_dependants'];
$dependants_at_home=$_POST['dependants_at_home'];





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
<table width="760" border="0" align="center" cellpadding="0" cellspacing="0" class="bdr" ><tr><td colspan="6" class="s_text">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" >
    <tr> 
      <td width="1286"><table width="770" height="165">
          <td><img src="img1.gif" alt="nknk" name="g" width="385" height="208"/>&nbsp;</td>
          <td><font color="#FFFFFF" size="16" face="Arial Narrow"><img src="img2.JPG" alt="nknk" name="g" width="385" height="208"/></font></td>
        </table></td>
    </tr>
    <tr> 
      <td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">SOUR 
        PROJECT</font></td>
    <tr> </tr>
    <table class="s_text" >
      <tr bgcolor="#ECF8FF" class="s_text"> 
        <td> <p></br> 
          <form ACTION="testing.php" method="post" name="f1">
            <?php         
switch ($marital_status) {
    case 'Single':
        marital_status_score=300;
		break;
    case 'Married':
        marital_status_score=400;
        break;
    case 'Divorced':
       marital_status_score=300;
        break;
	case 'Widowed':
        marital_status_score=300;
        break;
	case 'Other':
       marital_status_score=200;
        break;
}
w_marital_status_score=marital_status_score* 0.03;


switch($no_of_children){
case 0:
       $no_of_children_score=100;
		break;
	case 1:
       $no_of_children_score=200;
		break;
case 2:
       $no_of_children_score=300;
		break;

case 3:
       $no_of_children_score=400;
		break;

case 4:
       $no_of_children_score=300;
		break;

case 5:
       $no_of_children_score=200;
		break;

case 6:
       $no_of_children_score=100;
		break;

}
w_$no_of_children_score=$no_of_children_score*0.01;

//---------------EDUCATION LEVEL SCORE-----------------------------------------------------------------------


switch($education){
case 'University':
       $education_score=350;
		break;
	case 'Technical School':
       $education_level_score=300;
		break;
case 'Secondary School':
      $education_level_score=250;
		break;

case 'Primary School':
       $education_level_score=200;
		break;
case 'None':
       $education_level_score=100;
		break;
}
w_$education_score=$education_score*0.04;

//----------------PROFESSIONAL CATEGORY SCORE----------------------------------------------------------


switch($professional{
case 'Civil Servant':
       $professional_score=500;
		break;
	case 'Employee':
       $professional_score=400;
		break;
case 'Self Employed':
       $professional_score=200;
		break;

case 'Other':
       $professional_score=200;
		break;
}
w_$professional_score=$professional_score*0.08;

//------------------EMPLOYMENT CONTRACT TYPE SCORE---------------------------------------------------

switch($employment){
case 'Permanent':
       $employment_score=500;
		break;
	case 'Contactual':
       $employment_score=300;
		break;
case 'Other':
       $employment_score=200;
		break;
}
w_$employment_score=$employment_score*0.08;

//-------------------------------YEARS WITH EMPLOYER----------------------------------------------

switch($Years){
case ($Years >0 and Years<=1 ):
       $Years_score=500;
		break;
	case ($Years >1 and Years<=5):
       $Years_score=400;
		break;
case ($Years >5 and Years<=20):
       $Years_score=300;
		break;
case ($Years >20):
       $Years_score=300;
		break;
}
w_$Years_score=$Years_score*0.02;

//-----------------------SINGLE OR DOUBLE SALARY SCORE----------------------------------------------

switch($Revenues){
case 'Single':
       $Revenues_score=250;
		break;
	case 'Double':
       $Revenues_score=500;
		break;
}
w_$Revenues_score=$Revenues_score*0.03;

//-----------------------AREA OF RESIDENCE SCORE---------------------------------------------------

switch($area_of_residence){
case 'Major_urban':
       $area_of_residence_score=400;
		break;
	case 'Minor_urban':
       $area_of_residence_score=300;
		break;
case 'Rural':
       $area_of_residence_score=200;
		break;
}
w_$area_of_residence_score=$area_of_residence_score*0.02;


?>
          </form></td>
      </tr>
    </table></td></tr>
  </table>
</table></table>
</font> 
</BODY>
</HTML>
