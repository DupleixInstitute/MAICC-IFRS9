<html>
<head>
   
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
   
   <TITLE>CREDIT SCORING:FINANCIAL ANALYSIS - Non Current Assets</TITLE>
   <link href="complaintstyle.css" rel="stylesheet" type="text/css">
   <link href="Dupleix.css" rel="stylesheet" type="text/css">
   <script type="text/javascript" src="Dupleix.js"></script> 
   <script type="text/javascript" src="jquery-3.5.1.js"></script>
   
   <!-- Calling jquery.number.js script file for As-You-Type number formating with Comma separator for thousands -->
   <script type="text/javascript" src="jquery.number.js"></script>
   	 
   <!-- <script src = "easy-number-separator"></script> -->
    
   <script type="text/javascript">
      
	    //<!-- Document.Ready to set up jquery.number.js -->
	    $(function()
		    {
				// Set up the number formatting.
				//alert ("The previous page is " + document.referrer);
				
				document.AddMenuForm.application_ref.value = localStorage.application_ref;
				document.AddMenuForm.company_reg_no.value = localStorage.company_reg_no;
				document.AddMenuForm.loan_number.value = localStorage.loan_number;
				document.AddMenuForm.legal_name.value = localStorage.legal_name  + " " + localStorage.customer_type;
				
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
	 function ShowHelp()
	 {
		var Tracker1 = "PROGRESS TRACKER PAGE\r\nSince the data input is split across many pages, the Progress tracker showing the ";
		var Tracker2 = "of capturing in all the input pages, showing the dates and the capturer's user name.\r\n";
		var ScoreCard1 = "SCORE CARD\r\nPlease ensure all input activities are completed and updated on the Progress Tracker before the";
		var ScoreCard2 = "Score Card is executed.\r\n";
		var Financials1 = "\r\nCAPTURING FINANCIALS\r\nThis is the menu for adding client data for scoring for the 5 rated categories. ";
		var Financials2 = "Financials are are entered in 6 separate pages i.e Income Statement, Current Assets, ";
		var Financials3 = "Current Liabilities, Non-Current Assets, Non-Current Laibilities and Equity. Please ensure";
		var Financials4 = "that the Balance sheet balances by checking the Balance Sheet Check on the SummaryBalance Sheet";
		var Financials5 = "page. If the Balance Sheet is out of balance, the check will be in red background formatting.\r\n";
		var Ratios1 = "\r\nRATIO ANALYSIS.\r\n Please run the ratio analysis before the Score Card.  The ratio analysis starts with Edward ";
	    var Ratios2 = "Altman's Z Score Model ratios which are a set of 5 ratios for predicting a client's bankrupcy within the two years.  The";
        var Ratios3 = "ratio is not part of the scoring charecteristis but acts as a recommendation to REJECT an application in the red zone. ";
		var Ratios4 = "The Financial Section of the ScoreCard is based on 3 year weighted ratios. The weights are set on the model calibration page";
		
		var ALLTracker = Tracker1+Tracker2;
		var ALLScoreCard = ScoreCard1+ScoreCard2;
		var ALLFinancials = Financials1+Financials2+Financials3+Financials4+Financials5;
		var ALLRatios = Ratios1 + Ratios2 + Ratios3 + Ratios4;
		alert (ALLTracker+ALLScoreCard+ALLFinancials+ALLRatios);	
	}

	    function ToNumber(nStr) 
		{
	       if   (typeof(nStr) == "undefined")
		      {  return 0;}
		   else  
		      {
		        if (nStr == "") {return 0};
		        if (nStr != "") {return parseFloat(nStr.replace(/,/g,''))};
		      } 
	    }   
   </script 
</head>


				
				
<body style="background-color:white;" >
   <!--<label for="number">How much would you like to pay?</label><br />-->
   
   <form name="AddMenuForm" action="/action_BSPage.htm"  method="post">    
  
   <table border = 0 cellspacing = 0 class = "bdr" align = "center" >
  	  <tr>
	    <td><img src="img1.gif" alt="nknk" name="g" width="532" height="208"/></td>
		<td><img src="img2.jpg" alt="nknk" name="g" width="532" height="208"/></td>
	   </tr>
	   <table align = "center" border = 1 bordercolor = white width = 880 cellpadding =0 cellspacing = 0>
	      <tr>
		     <td colspan = 2><input type = "submit" name = "MainMenu" onclick="form.action='index.php';" value = "Main Menu">
			 <input type = "button" name = "Help"  onclick ="ShowHelp()" value = "Help"></td>
		  </tr>
	      <tr>
		     <td cellspacing = 0 colspan = 5 align = "center" style="background-color:darkblue;color:white"><h3>CORPORATE CREDIT SCORE CARD</h3></td>
			 <td></td>
			 <td></td>
			 <td></td>
		   </tr>
	     
		 <tr bgcolor = lightgrey style = "color:black">
		    <td colspan = 2><strong>Name of Company</strong></td>
			<td><strong>Company Reg. No</strong></td>
		    <td><strong>Application Loan No</strong></td>
			<td><strong>Company Loan Count</strong></td>
		 </tr>
		 <tr>
		    <td colspan = 2><Input type = text name = "legal_name" size = 53 readonly></td>
			<td><input type = text name = "company_reg_no" size = 24 readonly ></td>
		    <td><input type = text name = "application_ref" size = 24 readonly></td>
			<td><input type = text name = "loan_number" size = 29 readonly></td>
		 </tr>
		 <tr><td>.</td></tr>
		 <tr>
		     <td cellspacing = 0 colspan = 5 align = "left" style="background-color:white;color:black"><strong>Reports</strong></td>
			 <td></td>
			 <td></td>
			 <td></td>
		   </tr>
	      <tr>
		     <td><input name = "ScoreCard" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'ScoreCard.htm'" value = "Score Card"></td>
		     <td><input name = "ProgressTracker" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'ProgressTracker.htm'" value = "Progress Tracker"></td>
			 <td><input name = "RatioAnalysis" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'ratioanalysis.htm'" value = "Ratio Analysis"></td>
	         <td><input name = "SummaryBalance" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'SummaryBalanceSheet.htm'" value = "Summary Balance Sheet"></td>
		     <td><input name = "Upload" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'Upload_Files_Downloads.php'" value = "Download Attached Files"></td>
		  
		  </tr>
	      <tr>
		     <td><input name = "Charts" class = "btn2" style ="width:200px" type = "submit" onclick ="form.action = 'Charts.htm'" value = "Charts"></td>
		   </tr>
		   <tr></tr>
		   <tr></tr>
		   <tr></tr>
		   <tr>
		     <td cellspacing = 0 colspan = 5 align = "left" style="background-color:white;color:black"><strong>Inputs</strong></td>
			 
		   </tr>
	      <tr>
  		     <td><input name = "CompanyData" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'CompanyDataSecond.htm'" value = "Company Data"></td>
		     <td><input name = "IncomeStatement" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'IS.htm'" value = "Income Statement"></td>
		     <td><input name = "NonCurrentAssets" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'NonCurrentAssets.htm'" value = "Non Current Assets"></td>
		     <td><input name = "CurrentLiabilities" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'CurrentLiabilities.htm'" value = "Current Liabilties"></td>
		     <td><input name = "Upload" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'GetFileToUpload.php'" value = "Upload/Attached Files"></td>
		  </tr>
		   <tr>
      		 <td><input name = "LoanData" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'LoanData.htm'" value = "Loan and ITC Data"></td>
		     <td><input name = "CurrentAssets" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'CurrentAssets.htm'" value = "Current Assets"></td>
		     <td><input name = "NonCurrentLiabilities" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'NonCurrentLiabilities.htm'" value = "Non Current Liabilities"></td>
		     <td><input name = "BenchmarkOverrides" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'IndustryBenchmarkOverrides.htm'" value = "Industry Benchmarks Overrides"></td>
		  </tr
		  <tr>
  		     <td><input name = "ShareholderData" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'ShareholderData.htm'" value = "Shareholder Data"></td>
		     <td><input name = "Equity" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'Equity.htm'" value = "Equity"></td>
		 	 <td><input name = "ManagementAnalysis" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'ManagementAnalysis.htm'" value = "Management Analysis"></td>
		     <td><input name = "IndustryAnalysis" class = "btn" style ="width:200px" type = "submit" onclick ="form.action = 'IndustryAnalysis.htm'" value = "Industry Analysis"></td>
		  </tr>
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