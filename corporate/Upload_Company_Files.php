<?php include 'upload_Files_Logic.php';?>
<!DOCTYPE html>
<html>
  <head>
    <link rel="stylesheet" href="Upload_Files_Style.css">
    <script type="text/javascript" src="jquery-3.5.1.js"></script>
 	<title>Files Upload and Download</title>
	<script>
        $(function()
		{
		    	document.getElementById("legal_name").innerHTML = localStorage.legal_name + " " + localStorage.customer_type;
			    document.Files.application_ref.value = localStorage.application_ref;
				document.Files.company_reg_no.value = localStorage.company_reg_no;
				document.Files.username.value = localStorage.username;
	   		    alert (document.Files.application_ref.value);
		});
	 </script>     
  </head>
  <body>
    
	<table>
        
	</table>
    <div class="container">
      <div class="row">
        <form action="Upload_Company_Files.php" name = "Files" id = "Files" method="post" enctype="multipart/form-data" >
              
			  <tr>
              <td><img src="img1.gif" alt="nknk" name="g" width="400" height="208"/></td>
             </tr>
              <tr>
			     <td cellspacing = 0 colspan = 2 align = "center" style="background-color:darkblue;color:white">
				   <h3><p id = "legal_name"></p></h3>
				 </td>
			  </tr>
			  <tr>
			     <td>Application Ref No____:<input name = "application_ref" class = "header" readonly></td>
			  </tr>
			  <tr>
			     <td>Company Reg No______:<input name = "company_reg_no" class = "header"readonly></td>		
			  </tr>
			  <tr>
	             <td>Loan Number_________:<input name = "loan_number" class = "header" readonly></td>
			  </tr>
			  <tr>
				 <td>Username____________:<input name ="username" class = "header" readonly></td>
			  </tr>
 			  <h3>Upload File</h3>
              <input type="file" name="myfile"> <br>
              <button type="submit" name="save">upload</button>
        </form>
      </div>
    </div>
  </body>
</html>