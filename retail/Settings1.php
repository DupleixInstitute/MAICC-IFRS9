<html>

<head>

    <title>RETAIL CORPORATE SCORING MODEL CALIBRATION - Main Weights</title>
    <script type="text/javascript" src="jquery-3.5.1.js"></script>
	<script type="text/javascript" src="jquery.number.js"></script>
	<script>
	    
        $(function()
		    {
				document.forms["Settings1Form"]["username"].value = localStorage.username;
				//document.Settings1Form.username.value = "Edward";//localStorage.username;
				//Setting up the number.js number formatting
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
			
				
				
				//Load Data
				LoadMainWeights(document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"]);
				
				LoadData();
				RecalculateForm()
	            //SetReadOnly_MainWeights();
				
				
			}); 
		function RecalculateForm()
		{
				sfRecalculate(document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"]);
				bfRecalculate(document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"]);
	
	
		}
		
		function LoadMainWeights()
		
		{
              // Loading Big Firms Data
			  document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"].value = ToNumber(localStorage.bfFinancialAnalysis);
		      document.forms["Settings1Form"]["bfManagementAnalysisPercentage"].value = ToNumber(localStorage.bfManagementAnalysis);
		      document.forms["Settings1Form"]["bfIndustryAnalysisPercentage"].value = ToNumber(localStorage.bfIndustryAnalysis);
		      document.forms["Settings1Form"]["bfShareholderAnalysisPercentage"].value = ToNumber(localStorage.bfShareholderAnalysis);
		      document.forms["Settings1Form"]["bfBehavioralAnalysisPercentage"].value = ToNumber(localStorage.bfBehavioralAnalysisAnalysis);
		      document.forms["Settings1Form"]["bfTotalScore"].value = ToNumber(localStorage.bfTotalScore);
		      // Loading Small Firms Data
              document.forms["Settings1Form"]["sfFinancialAnalysisPercentage"].value = ToNumber(localStorage.sfFinancialAnalysis) ;
		      document.forms["Settings1Form"]["sfManagementAnalysisPercentage"].value = ToNumber(localStorage.sfManagementAnalysis);
		      document.forms["Settings1Form"]["sfIndustryAnalysisPercentage"].value = ToNumber(localStorage.sfIndustryAnalysis);
		      document.forms["Settings1Form"]["sfShareholderAnalysisPercentage"].value = ToNumber(localStorage.sfShareholderAnalysis);
		      document.forms["Settings1Form"]["sfBehavioralAnalysisPercentage"].value = ToNumber(localStorage.sfBehavioralAnalysis);
		      document.forms["Settings1Form"]["sfTotalScore"].value = ToNumber(localStorage.bfTotalScore);
	        
	          document.forms["Settings1Form"]["update_notes"].value = localStorage.update_notes);  
       

			//Loading Turnover Threshold and Ratio Weights
			  document.forms["Settings1Form"]["TurnoverThreshold"].value = Thousands_Separators(localStorage.TurnoverThreshold);  
        	  document.forms["Settings1Form"]["RatioWeightYear1"].value = localStorage.RatioWeightYear1;  
       	      document.forms["Settings1Form"]["RatioWeightYear2"].value = localStorage.RatioWeightYear2;  
       	      document.forms["Settings1Form"]["RatioWeightYear3"].value = localStorage.RatioWeightYear3;  
   		
		}
	   function SetReadOnly_MainWeights()
		{
           // Loading Big Firms Data
		      document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"].readOnly = true;		
			  document.forms["Settings1Form"]["bfManagementAnalysisPercentage"].readOnly = true;  
			  document.forms["Settings1Form"]["bfIndustryAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["bfShareholderAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["bfBehavioralAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["bfTotalScore"].readOnly = true;
		      // Loading Small Firms Data
              document.forms["Settings1Form"]["sfFinancialAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["sfManagementAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["sfIndustryAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["sfShareholderAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["sfBehavioralAnalysisPercentage"].readOnly = true;
		      document.forms["Settings1Form"]["sfTotalScore"].readOnly = true;
			  //Loading Turnover Threshold and Ratio Weights
			  document.forms["Settings1Form"]["TurnoverThreshold"].readOnly = true;  
        	  document.forms["Settings1Form"]["RatioWeightYear1"].readOnly = true;  
       	      document.forms["Settings1Form"]["RatioWeightYear2"].readOnly = true;  
       	      document.forms["Settings1Form"]["RatioWeightYear3"].readOnly = true;  
 		 
		}
				
		function SaveData()
		
		{   
		
	          localStorage.bfFinancialAnalysis = ToNumber(document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"].value) ;
		      localStorage.bfManagementAnalysis = ToNumber(document.forms["Settings1Form"]["bfManagementAnalysisPercentage"].value);
		      localStorage.bfIndustryAnalysis = ToNumber(document.forms["Settings1Form"]["bfIndustryAnalysisPercentage"].value);
		      localStorage.bfShareholderAnalysis = ToNumber(document.forms["Settings1Form"]["bfShareholderAnalysisPercentage"].value);
		      localStorage.bfBehavioralAnalysis = ToNumber(document.forms["Settings1Form"]["bfBehavioralAnalysisPercentage"].value);
		      localStorage.bfTotalScore = ToNumber(document.forms["Settings1Form"]["bfTotalScore"].value);
		      // Loading Small Firms Data
              localStorage.sfFinancialAnalysis = ToNumber(document.forms["Settings1Form"]["sfFinancialAnalysisPercentage"].value) ;
		      localStorage.sfManagementAnalysis = ToNumber(document.forms["Settings1Form"]["sfManagementAnalysisPercentage"].value);
		      localStorage.sfIndustryAnalysis = ToNumber(document.forms["Settings1Form"]["sfIndustryAnalysisPercentage"].value);
		      localStorage.sfShareholderAnalysis = ToNumber(document.forms["Settings1Form"]["sfShareholderAnalysisPercentage"].value);
		      localStorage.sfBehavioralAnalysis = ToNumber(document.forms["Settings1Form"]["sfBehavioralAnalysisPercentage"].value);
		      localStorage.sfTotalScore = ToNumber(document.forms["Settings1Form"]["sfTotalScore"].value);
		
			  localStorage.update_notes = document.forms["Settings1Form"]["update_notes"].value;
			  
			  //Saving Turnover Threshold and Ratio Weights
			  localStorage.TurnoverThreshold = ToNumber(document.forms["Settings1Form"]["TurnoverThreshold"].value);  
        	  localStorage.RatioWeightYear1 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear1"].value);  
       	      localStorage.RatioWeightYear2 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear2"].value);  
       	      localStorage.RatioWeightYear3 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear3"].value);  
              
			   // alert successful saving operation
			   alert ("Settings successfully saved to localStorage");
		}
       
	   function round(number,decimalPlaces)
	   {
	      var factorOf10 = Math.pow(10,decimalPlaces);
		  return Math.round(number * factorOf10) / factorOf10;
	   }
	   
	   
	   function ToNumber(nStr) 
		{
	      //alert (IsNan(nStr));
		  if (nStr == "infinity")
		     {
		       return 0;
		     }
		  else
		     {  
			   if   (typeof(nStr) == "undefined")
		            {  return 0;}
		       else  
		            {
		            if (nStr == "") {return 0};
		            if (nStr != "") 
					   {
					       var UnRoundedNumber = parseFloat(nStr.replace(/,/g,''));
						   return round(UnRoundedNumber,2);
					   }
		            }
	         }  
	    }
	   
	   function LoadData()
		
		{   
			 
			 
			 
	            
		}
     
		
		
		
		function bfRecalculate(elem)
		{
      
		      var bfFinancialAnalysis = ToNumber(document.forms["Settings1Form"]["bfFinancialAnalysisPercentage"].value);
		      var bfManagementAnalysis = ToNumber(document.forms["Settings1Form"]["bfManagementAnalysisPercentage"].value);
		      var bfIndustryAnalysis = ToNumber(document.forms["Settings1Form"]["bfIndustryAnalysisPercentage"].value);
		      var bfShareholderAnalysis = ToNumber(document.forms["Settings1Form"]["bfShareholderAnalysisPercentage"].value);
			  var bfTotalPercentageBeforeBehavioral = bfFinancialAnalysis + bfManagementAnalysis + bfIndustryAnalysis + bfShareholderAnalysis;
		      var bfTotalScore = Number(document.forms["Settings1Form"]["bfTotalScore"].value);
	
			  var bfBalancing = 100 - bfTotalPercentageBeforeBehavioral;
			  
			  
			  if (bfBalancing < 0)
		      {
		         alert("Behavioral Score may not be negative");
				 elem.value = "";
				 
		      }
              else  
			    // Display Total Score;
			  {
			    document.forms["Settings1Form"]["bfBehavioralAnalysisPercentage"].value = bfBalancing;   
				document.forms["Settings1Form"]["bfTotalPercentage"].value = 100;  
                document.forms["Settings1Form"]["bfFinancialAnalysisScore"].value = round(bfTotalScore * bfFinancialAnalysis / 100,2);
                document.forms["Settings1Form"]["bfManagementAnalysisScore"].value = round(bfTotalScore * bfManagementAnalysis / 100,2);
		        document.forms["Settings1Form"]["bfIndustryAnalysisScore"].value = round(bfTotalScore * bfIndustryAnalysis / 100,2);
				document.forms["Settings1Form"]["bfShareholderAnalysisScore"].value = round(bfTotalScore * bfShareholderAnalysis / 100,2);
	    		document.forms["Settings1Form"]["bfBehavioralAnalysisScore"].value = round(bfTotalScore * bfBalancing / 100,2);
	            // Updata Small Firms Total Score = Big Firm Total Score
				if (elem.name=="bfTotalScore")
				{
				  document.forms["Settings1Form"]["sfTotalScore"].value = elem.value;
	            }
			}
			
		}
		
		function sfRecalculate(elem)
		{
              var sfFinancialAnalysis = ToNumber(document.forms["Settings1Form"]["sfFinancialAnalysisPercentage"].value);
		      var sfManagementAnalysis = ToNumber(document.forms["Settings1Form"]["sfManagementAnalysisPercentage"].value);
		      var sfIndustryAnalysis = ToNumber(document.forms["Settings1Form"]["sfIndustryAnalysisPercentage"].value);
		      var sfShareholderAnalysis = ToNumber(document.forms["Settings1Form"]["sfShareholderAnalysisPercentage"].value);
			  var sfTotalPercentageBeforeBalancing = sfFinancialAnalysis + sfManagementAnalysis + sfIndustryAnalysis + sfShareholderAnalysis;
		      var sfTotalScore = Number(document.forms["Settings1Form"]["sfTotalScore"].value);
	
			  var sfBalancing = 100 - sfTotalPercentageBeforeBalancing;
			  
			  
			  if (sfBalancing < 0)
		      {
		         alert("Behavioral Analysis may not be negative");
				 elem.value = "";
				 
		      }
              else  
			       document.forms["Settings1Form"]["sfBehavioralAnalysisPercentage"].value = sfBalancing;

				// Display Total Score;
			  {
			    document.forms["Settings1Form"]["sfTotalPercentage"].value = 100;  
                document.forms["Settings1Form"]["sfFinancialAnalysisScore"].value = round(sfTotalScore * sfFinancialAnalysis / 100,2);
                document.forms["Settings1Form"]["sfManagementAnalysisScore"].value = round(sfTotalScore * sfManagementAnalysis / 100,2);
		        document.forms["Settings1Form"]["sfIndustryAnalysisScore"].value = round(sfTotalScore * sfIndustryAnalysis / 100,2);
				document.forms["Settings1Form"]["sfShareholderAnalysisScore"].value = round(sfTotalScore * sfShareholderAnalysis / 100,2);
				document.forms["Settings1Form"]["sfBehavioralAnalysisScore"].value = round(sfTotalScore * sfBalancing / 100,2);
			   
  
 			  }
			
		}
	  
	  function RecalculateRatios(val)
	  {
	     var RatioWeightYear1 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear1"].value);
	     var RatioWeightYear2 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear2"].value);
	     var RatioWeightYear3 = ToNumber(document.forms["Settings1Form"]["RatioWeightYear3"].value);
	     var TotalWeights = RatioWeightYear1 + RatioWeightYear2 + RatioWeightYear3;
		 
		 if (val.value < 0)
		 { 
		    alert ("Weight cannot be negative");
			val.value  = 0;
		 }
		 if (val.value>100)
		 { 
		    alert ("Weight cannot be greater than 100");
		    val.value = 0;
		 }
		 document.forms["Settings1Form"]["RatioWeightYear3"].value = 100 - RatioWeightYear1 - RatioWeightYear2;
	  }
	  
	  
	  function Thousands_Separators(num)
      {
         numStr = num+= "";
		// var num_parts = num.toString().split(".");
         //num_parts[0] = num_parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return numStr.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		//return num_parts.join(".");
	
      }
    </script>	
		
    </script>
</head>
<body>
   <form id = "Settings1Form" method = "post" action = "SaveSettings1.php" onclick = "RecalculateForm()">
   <Table Border = 2 border-style = 2 align = center>
      <tr bgcolor = blue color = white>
	       <th colspan = 3><h1 style = "color:white">MAIN MODEL SETTINGS CALIBRATION<h1></td>
	  </tr>
	  <tr>
	      <td style = "vertical-align:top" >
		      <table Border = 0 border-style = 2 align = top >
   			     <tr style = "background-color:lightgrey"> <td><strong>Big Firms Calibration<strong></td> 
				      <td></td>
					  <td></td>
				 </tr> 
				 <tr> <td></td>
				      <td colspan = 2  align = center>BIG FIRMS</td> 
				  </tr>
				 <tr> 
				      <td></td>
					  <td align = center><strong>%<strong></td>
					  <td><strong>Score<strong></td>
				 </tr>
				 <tr> 
				      <td><strong>Total Scores<strong></td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:blue" 
								 size = "1" type = text readonly name = "bfTotalPercentage" value = "100" onchange="bfRecalculate(this)">%
					  </td>
					  <td>
					      <input size = "1" type = text name = "bfTotalScore" onchange="bfRecalculate(this)" style = "text-align:right">
					  </td>
				 </tr>
				 <tr> 
				      <td><strong>Made up of:</td>
					  <td></td><td></td>
				 </tr>
				 <tr> 
				      <td><a href ="Settings2.htm">Financial Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "bfFinancialAnalysisPercentage" onchange="bfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								readonly size = "1" type = text name = "bfFinancialAnalysisScore">
					  </td>
				 </tr>
				 <tr> 
				      <td><a href = "Settings3.htm"> Management Analysis </a></td>
					  <td><input class = "price" type size = "1" type = text name = "bfManagementAnalysisPercentage" onchange="bfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "bfManagementAnalysisScore"></td>
				 </tr>
				 <tr> 
				      <td><a href = "Settings3.htm">Industry Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "bfIndustryAnalysisPercentage" onchange="bfRecalculate(this)" >%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "bfIndustryAnalysisScore"></td>
				 </tr>
			    <tr> 
				      <td><a href = "Settings3.htm">Shareholder Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "bfShareholderAnalysisPercentage" onchange="bfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "bfShareholderAnalysisScore"></td>
				</tr>
				<tr> 
				      <td><a href = "Setting3.htm">Behavioral & Product Analysis (Balancing)</a></td>
					  <td><input size = "1" type = text name = "bfBehavioralAnalysisPercentage" onchange="bfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "bfBehavioralAnalysisScore"></td>
				 </tr>
				 <tr>
	                <td></td>
	                <td> <Button name = "BtnSave" onclick = "SaveData()">Save</Button> </td>
	             </tr>
		         <!-Markup for Small Firms Calibration>
				<tr><td>.</td></tr>
		
   			     <tr style = "background-color:lightgrey" > <td><strong>Small Firms Calibration<strong></td> 
				      <td></td>
					  <td></td>
				 </tr> 
				 <tr > <td></td>
				      <td colspan = 2  align = center>SMALL FIRMS</td> 
				  </tr>
				 <tr> 
				      <td></td>
					  <td align = center><strong>%<strong></td>
					  <td><strong>Score<strong></td>
				 </tr>
				 <tr> 
				      <td><strong>Total Scores<strong></td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:blue" 
								 size = "1" type = text readonly name = "sfTotalPercentage" value = "100" onchange="sfRecalculate(this)">%
					  </td>
					  <td>
					      <input size = "1" type = text name = "sfTotalScore" readonly style = "background-color:blue;color:white;text-align:right">
					  </td>
				 </tr>
				 <tr> 
				      <td><strong>Made up of:</td>
					  <td></td><td></td>
				 </tr>
				 <tr> 
				      <td><a href ="Settings2.htm">Financial Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "sfFinancialAnalysisPercentage" onchange="sfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								readonly size = "1" type = text name = "sfFinancialAnalysisScore">
					  </td>
				 </tr>
				 <tr> 
				      <td><a href = "Settings2.htm"> Management Analysis </a></td>
					  <td><input class = "price" type size = "1" type = text name = "sfManagementAnalysisPercentage" onchange="sfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "sfManagementAnalysisScore"></td>
				 </tr>
				 <tr> 
				      <td><a href = "Settings3.htm">Industry Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "sfIndustryAnalysisPercentage" onchange="sfRecalculate(this)" >%%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "sfIndustryAnalysisScore"></td>
				 </tr>
			    <tr> 
				      <td><a href = "Settings3.htm">Shareholder Analysis</a></td>
					  <td><input class = "price" type size = "1" type = text name = "sfShareholderAnalysisPercentage" onchange="sfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "sfShareholderAnalysisScore"></td>
				</tr>
				<tr> 
				      <td><a href = "Settings3.htm">Behavioral & Product Analysis (Balancing)</a></td>
					  <td><input size = "1" type = text name = "sfBehavioralAnalysisPercentage" onchange="sfRecalculate(this)">%</td>
					  <td><input style = "text-align:right;
										  color:white;
										  background-color:grey"
								 readonly size = "1" type = text name = "sfBehavioralAnalysisScore"></td>
				 </tr>
				 <!-Markup for other settings>
   			     <tr><td>.</td></tr>
				 <tr style = "background-color:lightgrey" > <td><strong>Firm Size Settings<strong></td> 
				      <td></td>
					  <td></td>
				 </tr> 
	             <tr><td>.</td></tr>
				 <tr>
				      <td colspan = 3>Firm Size Turnover Threshold......<input size = 8 type = text name = "TurnoverThreshold" class = "price" style = "text-align:right"></td>
				 </tr>
				 <tr><td>.</td></tr>
				 <tr style = "background-color:lightgrey" > 
				      <td colspan = 3><strong>3 Year Ratio Analysis Weights Settings<strong></td> 
				      <td></td>
					  <td></td>
				 </tr> 
				 <tr><td>.</td></tr>
				 <tr align = right>
					  <td><strong>Year 1%</strong></td>
				      <td><strong>Year 2%</strong></td>
				      <td><strong>Year 3%</strong></td>
				 </tr>
				 <tr align = right>
					  <td>Ratio Analysis Weights:................<input class = "price" type size = 1 type = text name = "RatioWeightYear1"  style = "text-align:right" onchange = "RecalculateRatios(this)">%</td>
				      <td><input class = "price" type size = 1 type = text name = "RatioWeightYear2" style = "text-align:right" onchange = "RecalculateRatios(this.value)">%</td>
				      <td><input class = "price" type size = 1 type = text name = "RatioWeightYear3" style = "background-Color:grey;text-align:right" onchange = "RecalculateRatios(this.value)">%</td>
				 </tr>
				 <tr>
	                <td></td>
	                <td> <Button type = submit name = "BtnSave" onclick = "SaveData()">Save</Button> </td>
	             </tr>
			     <tr>
		          <td><input name = "username" type = hidden></td>
 			    </tr>
			  </table>
			  <td>____</td>
			  <td style = "vertical-align:top" >
			      <table border = 1 cellpadding = 0 align = top>
                     <tr style = "background-color:lightgrey" > 
					     <td align = center ><strong>Enter the reason for the update in the box below<strong></td> 
				     </tr> 
				     <tr>
					     <td>
						     <textarea name = "update_notes" rows = 20 cols = 100 style = "background-color:cream"></textarea>
						 </td>
					 </tr>
				  </table>
			  </td>
		  </td>
	
			      </table>
			</td>
			 
			
		  </tr> 
		</Table> 
		  </td></td>              
	  </tr>
	 
   </Table>
   </form>
</body>

</html>