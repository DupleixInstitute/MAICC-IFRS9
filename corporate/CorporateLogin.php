<script type="text/javascript">

function isEmpty(strfield1, strfield2)
   {
//variables names
var strfield1 = document.login.username.value;
var strfield2 = document.login.password.value;




  //name field


if(strfield1 == "" || strfield1 == null)
{ 
alert("PLEASE ENTER YOUR USERNAME.")
return false;
}
if(strfield2 == "" || strfield2 == null)
{
alert("PLEASE ENTER THE PASSWORD.") 
return false;
} 

    return true;
}
function UpdateLocalStorage()
{
  //   localStorage.username = document.login.username.value;

}
function check(login){
localStorage.username = login.username.value;

if (isEmpty(login.username)){
  if (isEmpty(login.password)){
	
		  return true;
		}
	
  }

return false;
}



</script>


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
<td width="1286" align="CENTER" bgcolor="#000040" class="s_text"><font  color="#FFFFFF" size="7" face="Arial Narrow">CORPORATE CREDIT SCORING</font></td><tr>
      </tr>
<table>
<form method="post" action="CorporateStartMenu.php" name="login" onsubmit="return check(this);">


<table width="400" border="0" cellspacing="1" cellpadding="0">
<tr>
<td>
<table align="right">
<tr>
<td>&nbsp;</td>
</tr>
<tr>


</tr>

<tr>
<td>&nbsp;&nbsp;</td>
</tr>
<table width="60%" align="center">
<tr></tr>
<tr></tr>
<tr></tr>
<tr ><td colspan="3"><font color="#000033" size="5"><strong>LOGIN DETAILS</strong></font></td></tr>
<tr ><td colspan="3"><font color="red"  size="2"><strong>Please use your windows login details</strong></font></td></tr>
<tr><td>Username:</td> <td></td><td><input type="text" name="username"  /></td></tr>
<tr></tr>
<tr></tr>
<tr><td>Password:</td> <td></td><td><input type="password" size="20" name="password"  /></td></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>
<tr></tr>

<tr><td> <input type="submit" name="login" value="Login" onclick = "UpdateLocalStorage()" /></td> </tr> </form>

</table>
<tr>
<td>&nbsp;&nbsp;</td>
</tr><tr>
<td>&nbsp;&nbsp;</td>
</tr>
<tr>
<td colspan="8" align="center">
 </td>
</tr>
<tr>


</tr>


</table>

</td>
</tr>
</table>
<br>
<br>
<br>
<br>

</body>
</html>



