
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>CREDIT SCORING</TITLE>


<link href="btu.css" rel="stylesheet" type="text/css">



</HEAD>

<BODY BGCOLOR=#FFFFFF >
<font class="s_text">
<?php


$username = $_POST['username'];
$password = $_POST['password'];
//echo $username;
//echo $password; echo 'ttt';


$pass=$_POST['password'];
$host="localhost"; // Host name 
//$username="root"; // Mysql username 
//$password="sefalana2008"; // Mysql password 
$db_name="creditscoring"; // Database name 
// Connect to server and select databse.
$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;


$connect=mysql_connect("localhost","root","sefalana2008"); 
//echo $pass;
  if (!$connect) {
  //mysql_close($connect); 
  echo "Cannot connect to the database! Please Check your username and password.";
  die(); 
}
else {
mysql_select_db($db_name, $connect)
or die("Couldn't open $dbase: ".mysql_error());

}




?>




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