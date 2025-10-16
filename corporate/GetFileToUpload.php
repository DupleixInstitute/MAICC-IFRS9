<?php include 'upload_Files_Logic.php';?>
<HTML>
<HEAD>

<TITLE>CREDIT SCORING - Upload File Attachments</TITLE>
<script type="text/javascript" src="jquery-3.5.1.js"></script>
 
<script>

 function Validate()
 {
   if (document.Files.myfile.value == "") {
      alert ("Please a select a file to upload less that 1MB");
      return false;
   }	  
 }
</script>

</HEAD>

<BODY BGCOLOR=#FFFFFF >

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
   <table class="s_text" >
        <tr bgcolor="#ECF8FF" class="s_text"> 
          <td>
        <p></br> 
         <form action="GetFileToUpload.php" name = "Files" id = "Files" method="post" onsubmit = "return Validate()" enctype="multipart/form-data">
            <td><table><tr><td><p align="justify" class="s_text"><br>
<table class="s_text">

<tr>
    <td>Unique Loan Application No__:</td>
    <td><input name="application_ref" type="text" readonly></td>
</tr>

<tr>
  <td><h3><br><br>Upload File</h3></td>
</tr>
<tr>
  <td><input type="file" name="myfile">
</tr>
<tr>
 <td><button type="submit" name="save">upload</button></td> 	 	
</tr>
    

<tr><td>&nbsp;  </td></tr>
<tr> 
<td></td>
<td><input type="hidden" name="username"  />
<input type="hidden" name="password"  value="<?php echo $_POST['password'] ?>"/>
</td>

</tr>
<script>
  document.Files.application_ref.value = localStorage.application_ref;
  document.Files.username.value = localStorage.username;
</script>


</form>



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