<html>
<head>
   
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
   
   <TITLE>CREDIT SCORING:Progress Tracker</TITLE>
   
   <link href="Dupleix.css" rel="stylesheet" type="text/css">
   <script type="text/javascript" src="Dupleix.js"></script>
   <script type="text/javascript" src="jquery-3.5.1.js"></script>
   
   <!-- Calling jquery.number.js script file for As-You-Type number formating with Comma separator for thousands -->
   <script type="text/javascript" src="jquery.number.js"></script>
   	 
   <!-- <script src = "easy-number-separator"></script> -->
<?php
	    
//Get search variables from previous page
$application_ref = $_POST['application_ref'];
$company_reg_no = $_POST['company_reg_no'];
$loan_number = $_POST['loan_number'];
$username = $_POST['username'];
				 
//Connect to the database
$pass= "";
$host="localhost"; 
$user="root"; 
$db_name="corporatescoring"; 
                   
// GET HOST BY NAME ?
// Connect to server and select databse.
//$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
//echo $ip;

$connect=mysqli_connect($host,$user,$pass,$db_name);     

//Failed Connection ==> die
if (!$connect) {
   mysqli_close($connect); 
   echo "Cannot connect to the database! Please Check your username and password."; 
   die;
   echo "<meta http-equiv=\"refresh\" content=\"0;URL=user_error.php\">";
}
          
// Construct Search queries depending on the search key used:===========================
if ($application_ref !== ""){       // Where search key used is application_ref
	$CompanyData_Query      = "SELECT * FROM  progress_tracker_single_record WHERE application_ref ='$application_ref' "; 
}
else {                              // Where search key used is company reg no + loan number
   $CompanyData_Query = "SELECT * FROM progress_tracker_single_record WHERE 
			             company_reg_no ='$company_reg_no' and loan_number = '$loan_number' ";     
}	        

//1. COMPANY DATA - Run CompanyData query and Update local Storage===============================================================================
$result= mysqli_query($connect,$CompanyData_Query); //interchanged by edward
if (!$result){                                  
   echo 'error with getting registration no'. mysql_error();
   exit;
}     
// 
while($row = mysqli_fetch_assoc($result)) 
{
   
}
?>         
   <script type="text/javascript">
      
	    //<!-- Document.Ready to set up jquery.number.js -->
	    $(function()
		    {
				// Set up the number formatting.
						
				
				ProgressTrackerForm_LoadData();
				
				
				$('#number_container').slideDown('fast');
				
				$('.price').on('change',function(){
					console.log('Change event.');
					var val = $('.price').val();
					$('#the_number').text( val !== '' ? val : '(empty)' );
				});
				
				$('.price').change(function(){
					console.log('Second change event...');
					
				});
				
				$('.price').number( true, 0 );
				
				// Get the value of the number for the demo.
				$('#get_number').on('click',function(){
					
					var val = $('.price').val();
					
					$('#the_number').text( val !== '' ? val : '(empty)' );
				});
			});
	    

		function ToNumber(nStr) 
		{
	      //alert (IsNan(nStr));
		  if (nStr == "") {return 0};
		  if (nStr != "") {return parseFloat(nStr.replace(/,/g,''))};
	    }  inter
   </script 
</head>


				
				
<body style="background-color:lightgrey;" >
   <!--<label for="number">How much would you like to pay?</label><br />-->
   
   <form name="ProgressTrackerForm" action="/StartMenu.htm" onsubmit="return validateForm()" method="post">>    
   
   <table border = 0 cellspacing = 0 class = "bdr" align = "left" >
  	  <tr>
	    <td><img src="img1.gif" alt="nknk" name="g" width="420" height="208"/></td>
		<td><img src="img2.jpg" alt="nknk" name="g" width="420" height="208"/></td>
	   </tr>
	   <table align = "center" border = 0>
	   
	  <tr><td cellspacing = 0 colspan = 9 align = "center" style="background-color:darkblue;color:white"><h3>GARE VISION (PTY) LTD</h3></td></tr>
	  
	   
		<tr bgcolor = darkblue>
	      <th colspan = 9><h4 style="color:white;text-align:Center">PROGRESS TRACKER<h4></th>
	    </tr>
		
		<tr bgcolor = darkblue>
		  <th Width = 20 style="color:white">Section.....................................</th>
		  <th Width = 20 style="color:white">Date Saved</th>
		  <th Width = 20 style="color:white">Saved By</th>
		  <th Width = 20 style="color:white">1st Review Date</th>
		  <th Width = 20 style="color:white">1st Reviewer</th>
	      <th Width = 20 style="color:white">2nd Review Date</th>
		  <th Width = 20 style="color:white">2nd Reviewer</th>
		  <th Width = 20 style="color:white">1st Review Comment</th>
		  <th Width = 20 style="color:white">2nd Review Comment</th>
		</tr>
		
		<!-- PROPERT, PLANT AND EQUIPMENT HEADING -->
		<tr>
		  <td size = 20>Company Data</td>
		  <td><Input type="text" name = "CompanyDataTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CompanyDataTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CompanyDataTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CompanyDataTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CompanyDataTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CompanyDataTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CompanyDataTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CompanyDataTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Loan Data</td>
		  <td><Input type="text" name = "LoanDataTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "LoanDataTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "LoanDataTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "LoanDataTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "LoanDataTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "LoanDataTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "LoanDataTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "LoanDataTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Income Statement</td>
		  <td><Input type="text" name = "IncomeStatementTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IncomeStatementTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IncomeStatementTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IncomeStatementTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IncomeStatementTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IncomeStatementTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IncomeStatementTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IncomeStatementTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	  <tr>
		  <td size = 20>Current Assets</td>
		  <td><Input type="text" name = "CurrentAssetsTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CurrentAssetsTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CurrentAssetsTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentAssetsTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentAssetsTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentAssetsTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentAssetsTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentAssetsTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Non Current Assets</td>
		  <td><Input type="text" name = "NonCurrentAssetsTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "NonCurrentAssetsTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "NonCurrentAssetsTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentAssetsTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentAssetsTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentAssetsTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentAssetsTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentAssetsTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Current Liabilities</td>
		  <td><Input type="text" name = "CurrentLiabilitiesTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CurrentLiabilitiesTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "CurrentLiabilitiesTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentLiabilitiesTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentLiabilitiesTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentLiabilitiesTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentLiabilitiesTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "CurrentLiabilitiesTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Non Current Liabilities</td>
		  <td><Input type="text" name = "NonCurrentLiabilitiesTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "NonCurrentLiabilitiesTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "NonCurrentLiabilitiesTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentLiabilitiesTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentLiabilitiesTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentLiabilitiesTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentLiabilitiesTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "NonCurrentLiabilitiesTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Equity</td>
		  <td><Input type="text" name = "EquityTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "EquityTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "EquityTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "EquityTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "EquityTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "EquityTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "EquityTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "EquityTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Industry Analysis</td>
		  <td><Input type="text" name = "IndustryAnalysisTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IndustryAnalysisTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IndustryAnalysisTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryAnalysisTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryAnalysisTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryAnalysisTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryAnalysisTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryAnalysisTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Management Analysis</td>
		  <td><Input type="text" name = "ManagementAnalysisTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "ManagementAnalysisTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "ManagementAnalysisTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ManagementAnalysisTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ManagementAnalysisTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ManagementAnalysisTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ManagementAnalysisTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ManagementAnalysisTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Shareholder Analysis</td>
		  <td><Input type="text" name = "ShareholderAnalysisTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "ShareholderAnalysisTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "ShareholderAnalysisTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ShareholderAnalysisTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ShareholderAnalysisTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ShareholderAnalysisTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ShareholderAnalysisTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "ShareholderAnalysisTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Behavioral Analysis</td>
		  <td><Input type="text" name = "BehavioralAnalysisTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "BehavioralAnalysisTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "BehavioralAnalysisTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "BehavioralAnalysisTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "BehavioralAnalysisTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "BehavioralAnalysisTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "BehavioralAnalysisTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "BehavioralAnalysisTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	   <tr>
		  <td size = 20>Industry Benchmarks Overrides</td>
		  <td><Input type="text" name = "IndustryBenchmarksOverridesTrackerDateSaved" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IndustryBenchmarksOverridesTrackerSavedBy" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
		  <td><Input type="text" name = "IndustryBenchmarksOverridesTracker1stReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryBenchmarksOverridesTracker1stReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryBenchmarksOverridesTracker2ndReviewDate" size = 12 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryBenchmarksOverridesTracker2ndReviewer" size = 20 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryBenchmarksOverridesTracker1stReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	      <td><Input type="text" name = "IndustryBenchmarksOverridesTracker2ndReviewComment" size = 30 readonly = true style="text-align:right;background-color:Lightgrey;color:black"></td>
	   </tr>
	
	   
	      <td></td>
		  <td><Input type = Submit value = "Save/Submit" ></td><td><Button type = "button" value onclick ="">Clear Form</Button></td>
	    </table>
   </table>
   <script>
	 
	  function Thousands_Separators(num)
      {
         numStr = num+= "";
		// var num_parts = num.toString().split(".");
         //num_parts[0] = num_parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return numStr.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		//return num_parts.join(".");
	
      }
    </script>
   </Form>
</body>

</html>