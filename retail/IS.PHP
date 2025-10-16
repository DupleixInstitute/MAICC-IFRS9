<html>
<head>
   
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
   
   <TITLE>CREDIT SCORING - Income Statement Entry</TITLE>
   
   <link href="Dupleix.css" rel="stylesheet" type="text/css">
    
   <script type="text/javascript" src="jquery-3.5.1.js"></script>
   
   <!-- Calling jquery.number.js script file for As-You-Type number formating with Comma separator for thousands -->
   <script type="text/javascript" src="jquery.number.js"></script>
   	 
   <!-- <script src = "easy-number-separator"></script> -->
    
   <script type="text/javascript">
      <?php
	      //Connecting to the database
		 $pass= "";
         $host="localhost"; 
         $user="root"; 
         $db_name="corporatescoring"; 
                   
         $connect=mysqli_connect($host,$user,$pass,$db_name); 
    
         if (!$connect) 
	      {
             mysqli_close($connect); 
             echo "Cannot connect to the database! Please Check your username and password."; 
             die;
		 }
         else 
		  {
             echo 'Successfully Connected';
			 
          }
		  
   
        $username = $_POST['username'];
        $update_notes = "";	 
		
		

		$select_query = "SELECT * FROM model_calibration"; 
	  
	    $resultm= mysqli_query($connect,$select_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting data'. mysqli_error();
          }
        else
		  {
		     while($row = mysql_fetch_assoc($resultm)) 
			 {
				 echo 
             }
          }
	  
	  
	  
	  ?>
	  
	  
	  
	    //<!-- Document.Ready to set up jquery.number.js -->
	    $(function()
		    {
				// Set up the number formatting.
				
				document.getElementById("legal_name").innerHTML = localStorage.legal_name + " " + localStorage.customer_type;
			    document.ISForm.application_ref.value = localStorage.application_ref;
				document.ISForm.company_reg_no.value = localStorage.company_reg_no;
				document.ISForm.username.value = localStorage.username;
					
				document.ISForm.financial_year0.value = localStorage.financial_year0;
				document.ISForm.financial_year1.value = localStorage.financial_year1;
				document.ISForm.financial_year2.value = localStorage.financial_year2;
				document.ISForm.financial_year3.value = localStorage.financial_year3;
					
				
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
			
			    LoadData();
				Recalculate();
			
			});
	    //<!-- Convert number input into a thousand seperator -->
        
		function SaveData()
		{
		   //1. Net Sales
	       localStorage.NetSales0 = ToNumber(document.forms["ISForm"]["NetSalesYear0"].value);
	       localStorage.NetSales1 = ToNumber(document.forms["ISForm"]["NetSalesYear1"].value);
	       localStorage.NetSales2 = ToNumber(document.forms["ISForm"]["NetSalesYear2"].value);    
		   localStorage.NetSales3 = ToNumber(document.forms["ISForm"]["NetSalesYear3"].value);  
		   
		   //2. Cost of Sales - Depreciation  
		   localStorage.CosDep0 = ToNumber(document.forms["ISForm"]["CosDepYear0"].value);
 		   localStorage.CosDep1 = ToNumber(document.forms["ISForm"]["CosDepYear1"].value);
           localStorage.CosDep2 = ToNumber(document.forms["ISForm"]["CosDepYear2"].value);
		   localStorage.CosDep3 = ToNumber(document.forms["ISForm"]["CosDepYear3"].value);
		   
		   //3. Cost of Sales - Other
		   localStorage.CosOther0 = ToNumber(document.forms["ISForm"]["CosOtherYear0"].value);
		   localStorage.CosOther1 = ToNumber(document.forms["ISForm"]["CosOtherYear1"].value);
	       localStorage.CosOther2 = ToNumber(document.forms["ISForm"]["CosOtherYear2"].value);
		   localStorage.CosOther3 = ToNumber(document.forms["ISForm"]["CosOtherYear3"].value);
		   
		   //4. Other Income Line 1
		   localStorage.OtherIncomeLine1_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year0"].value);
		   localStorage.OtherIncomeLine1_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year1"].value);
	       localStorage.OtherIncomeLine1_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year2"].value);
		   localStorage.OtherIncomeLine1_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year3"].value);
		   
		   //5. Other Income Line 2
		   localStorage.OtherIncomeLine2_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year0"].value);
		   localStorage.OtherIncomeLine2_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year1"].value);
	       localStorage.OtherIncomeLine2_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year2"].value);
		   localStorage.OtherIncomeLine2_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year3"].value);
		   
		   //6. Other Income Line 3
		   localStorage.OtherIncomeLine3_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year0"].value);
 		   localStorage.OtherIncomeLine3_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year1"].value);
           localStorage.OtherIncomeLine3_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year2"].value);
		   localStorage.OtherIncomeLine3_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year3"].value);
		   
		   //7. Other Income Line 4
		   localStorage.OtherIncomeLine4_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year0"].value);
		   localStorage.OtherIncomeLine4_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year1"].value);
		   localStorage.OtherIncomeLine4_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year2"].value);
		   localStorage.OtherIncomeLine4_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year3"].value);
		   
		   //8. Opex Line - Staff Costs
		   localStorage.StaffCosts0 = ToNumber(document.forms["ISForm"]["StaffCostsYear0"].value);
		   localStorage.StaffCosts1 = ToNumber(document.forms["ISForm"]["StaffCostsYear1"].value);
		   localStorage.StaffCosts2 = ToNumber(document.forms["ISForm"]["StaffCostsYear2"].value);
		   localStorage.StaffCosts3 = ToNumber(document.forms["ISForm"]["StaffCostsYear3"].value);

		   //9. Opex Line - Directors Fees
		   localStorage.DirectorsFees0 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear0"].value);
		   localStorage.DirectorsFees1 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear1"].value);
		   localStorage.DirectorsFees2 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear2"].value);
		   localStorage.DirectorsFees3 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear3"].value);
		   
		   //10. Opex Line - Depreciation
		   localStorage.Depreciation0 = ToNumber(document.forms["ISForm"]["DepreciationYear0"].value);
		   localStorage.Depreciation1 = ToNumber(document.forms["ISForm"]["DepreciationYear1"].value);
		   localStorage.Depreciation2 = ToNumber(document.forms["ISForm"]["DepreciationYear2"].value);
		   localStorage.Depreciation3 = ToNumber(document.forms["ISForm"]["DepreciationYear3"].value);
		   
		   //11. Opex Line - Amortisation
		   localStorage.Amortisation0 = ToNumber(document.forms["ISForm"]["AmortisationYear0"].value);
		   localStorage.Amortisation1 = ToNumber(document.forms["ISForm"]["AmortisationYear1"].value);
		   localStorage.Amortisation2 = ToNumber(document.forms["ISForm"]["AmortisationYear2"].value);
		   localStorage.Amortisation3 = ToNumber(document.forms["ISForm"]["AmortisationYear3"].value);
		   
		   //12. Opex Line - NoNameOpex Line 1
		   localStorage.NoNameOpexLine1_0 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year0"].value);
		   localStorage.NoNameOpexLine1_1 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year1"].value);
		   localStorage.NoNameOpexLine1_2 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year2"].value);
		   localStorage.NoNameOpexLine1_3 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year3"].value);

           //13. Opex Line - NoNameOPex Line 2
		   localStorage.NoNameOpexLine2_0 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year0"].value);
		   localStorage.NoNameOpexLine2_1 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year1"].value);
	       localStorage.NoNameOpexLine2_2 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year2"].value);
		   localStorage.NoNameOpexLine2_3 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year3"].value);
		   
		   //14. Opex Line - Other
		   localStorage.OtherOpex0 = ToNumber(document.forms["ISForm"]["OtherOpexYear0"].value);
		   localStorage.OtherOpex1 = ToNumber(document.forms["ISForm"]["OtherOpexYear1"].value);
           localStorage.OtherOpex2 = ToNumber(document.forms["ISForm"]["OtherOpexYear2"].value);
           localStorage.OtherOpex3 = ToNumber(document.forms["ISForm"]["OtherOpexYear3"].value);
           
		   //15. Net Finance Costs
           localStorage.NetFinanceCosts0 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear0"].value);
           localStorage.NetFinanceCosts1 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear1"].value);
		   localStorage.NetFinanceCosts2 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear2"].value);
		   localStorage.NetFinanceCosts3 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear3"].value);
           
		   //16. Income Tax
           localStorage.IncomeTax0 = ToNumber(document.forms["ISForm"]["IncomeTaxYear0"].value);
           localStorage.IncomeTax1 = ToNumber(document.forms["ISForm"]["IncomeTaxYear1"].value);
		   localStorage.IncomeTax2 = ToNumber(document.forms["ISForm"]["IncomeTaxYear2"].value);
		   localStorage.IncomeTax3 = ToNumber(document.forms["ISForm"]["IncomeTaxYear3"].value);
           
		   //16. Deferred Tax
           localStorage.DeferredTax0 = ToNumber(document.forms["ISForm"]["DeferredTaxYear0"].value);
           localStorage.DeferredTax1 = ToNumber(document.forms["ISForm"]["DeferredTaxYear1"].value);
		   localStorage.DeferredTax2 = ToNumber(document.forms["ISForm"]["DeferredTaxYear2"].value);
		   localStorage.DeferredTax3 = ToNumber(document.forms["ISForm"]["DeferredTaxYear3"].value);
				   
           //17. Other Income Line Descriptions
		   localStorage.OtherIncomeDescLine1 = document.forms["ISForm"]["OtherIncomeDescLine1"].value;
           localStorage.OtherIncomeDescLine2 = document.forms["ISForm"]["OtherIncomeDescLine2"].value;
           localStorage.OtherIncomeDescLine3 = document.forms["ISForm"]["OtherIncomeDescLine3"].value;
           localStorage.OtherIncomeDescLine4 = document.forms["ISForm"]["OtherIncomeDescLine4"].value;
           //18. Other Opex Line Descriptions
           localStorage.NoNameOpexDescLine1 = document.forms["ISForm"]["NoNameOpexDescLine1"].value;
           localStorage.NoNameOpexDescLine2 = document.forms["ISForm"]["NoNameOpexDescLine2"].value;
          //19. Saving the Calculated Items
 		   localStorage.CosTotal0 = ToNumber(document.forms["ISForm"]["CosTotalYear0"].value);
		   localStorage.CosTotal1 = ToNumber(document.forms["ISForm"]["CosTotalYear1"].value);
		   localStorage.CosTotal2 = ToNumber(document.forms["ISForm"]["CosTotalYear2"].value);
		   localStorage.CosTotal3 = ToNumber(document.forms["ISForm"]["CosTotalYear3"].value);
		   
		   //2. Gross Profit 
		   localStorage.GrossProfit0 = ToNumber(document.forms["ISForm"]["GrossProfitYear0"].value);
		   localStorage.GrossProfit1 = ToNumber(document.forms["ISForm"]["GrossProfitYear1"].value);
		   localStorage.GrossProfit2 = ToNumber(document.forms["ISForm"]["GrossProfitYear2"].value);
		   localStorage.GrossProfit3 = ToNumber(document.forms["ISForm"]["GrossProfitYear3"].value);
		   
		   //3. Total Other Income
		   localStorage.OtherIncomeTotal0 = ToNumber(document.forms["ISForm"]["OtherIncomeTotalYear0"].value);
		   localStorage.OtherIncomeTotal1 = ToNumber(document.forms["ISForm"]["OtherIncomeTotalYear1"].value);
		   localStorage.OtherIncomeTotal2 = ToNumber(document.forms["ISForm"]["OtherIncomeTotalYear2"].value);
		   localStorage.OtherIncomeTotal3 = ToNumber(document.forms["ISForm"]["OtherIncomeTotalYear3"].value);
		   
		   //4. Total Opex
		   localStorage.OpexTotal0 = ToNumber(document.forms["ISForm"]["OpexTotalYear0"].value);
		   localStorage.OpexTotal1 = ToNumber(document.forms["ISForm"]["OpexTotalYear1"].value);
		   localStorage.OpexTotal2 = ToNumber(document.forms["ISForm"]["OpexTotalYear2"].value);
		   localStorage.OpexTotal3 = ToNumber(document.forms["ISForm"]["OpexTotalYear3"].value);
		   
		   //5. EBIT
	       localStorage.EBIT0 = ToNumber(document.forms["ISForm"]["EBITYear0"].value);
           localStorage.EBIT1 = ToNumber(document.forms["ISForm"]["EBITYear1"].value);
		   localStorage.EBIT2 = ToNumber(document.forms["ISForm"]["EBITYear2"].value);
		   localStorage.EBIT3 = ToNumber(document.forms["ISForm"]["EBITYear3"].value);
		   
		   //6. PBT
	       localStorage.PBT0 = ToNumber(document.forms["ISForm"]["PBTYear0"].value);
	       localStorage.PBT1 = ToNumber(document.forms["ISForm"]["PBTYear1"].value);
		   localStorage.PBT2 = ToNumber(document.forms["ISForm"]["PBTYear2"].value);
		   localStorage.PBT3 = ToNumber(document.forms["ISForm"]["PBTYear3"].value);
		   //7. Taxation for the Year
	       localStorage.Taxation0 = ToNumber(document.forms["ISForm"]["TaxationYear0"].value);
	       localStorage.Taxation1 = ToNumber(document.forms["ISForm"]["TaxationYear1"].value);
		   localStorage.Taxation2 = ToNumber(document.forms["ISForm"]["TaxationYear2"].value);
		   localStorage.Taxation3 = ToNumber(document.forms["ISForm"]["TaxationYear3"].value);
		   //8. Net Profit
	       localStorage.NetProfit0 = ToNumber(document.forms["ISForm"]["TaxationYear0"].value);
	       localStorage.NetProfit1 = ToNumber(document.forms["ISForm"]["TaxationYear1"].value);
		   localStorage.NetProfit2 = ToNumber(document.forms["ISForm"]["TaxationYear2"].value);
		   localStorage.NetProfit3 = ToNumber(document.forms["ISForm"]["TaxationYear3"].value);
 	      
  		   alert ("Income Statement Successfully Saved");
		}
		
		function LoadData()
		{
		   //1. Net Sales
	       document.forms["ISForm"]["NetSalesYear0"].value = Thousands_Separators(ToNumber(localStorage.NetSales0)) ;
	       document.forms["ISForm"]["NetSalesYear1"].value = Thousands_Separators(ToNumber(localStorage.NetSales1)) ;
	       document.forms["ISForm"]["NetSalesYear2"].value = Thousands_Separators(ToNumber(localStorage.NetSales2)) ;
	       document.forms["ISForm"]["NetSalesYear3"].value = Thousands_Separators(ToNumber(localStorage.NetSales3)) ;
		   
		   //2. Cost of Sales - Depreciation  
	       document.forms["ISForm"]["CosDepYear0"].value = Thousands_Separators(ToNumber(localStorage.CosDep0)) ;
  	       document.forms["ISForm"]["CosDepYear1"].value = Thousands_Separators(ToNumber(localStorage.CosDep1)) ;
 	       document.forms["ISForm"]["CosDepYear2"].value = Thousands_Separators(ToNumber(localStorage.CosDep2)) ;
 	       document.forms["ISForm"]["CosDepYear3"].value = Thousands_Separators(ToNumber(localStorage.CosDep3) );
		   
		   //3. Cost of Sales - Other
	       document.forms["ISForm"]["CosOtherYear0"].value = Thousands_Separators(ToNumber(localStorage.CosOther0)) ;
	       document.forms["ISForm"]["CosOtherYear1"].value = Thousands_Separators(ToNumber(localStorage.CosOther1)) ;
	       document.forms["ISForm"]["CosOtherYear2"].value = Thousands_Separators(ToNumber(localStorage.CosOther2)) ;
	       document.forms["ISForm"]["CosOtherYear3"].value = Thousands_Separators(ToNumber(localStorage.CosOther3));
		   
		   //4. Other Income Line 1
	       document.forms["ISForm"]["OtherIncomeLine1Year0"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine1_0)) ;
	       document.forms["ISForm"]["OtherIncomeLine1Year1"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine1_1)) ;
	       document.forms["ISForm"]["OtherIncomeLine1Year2"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine1_2)) ;
	       document.forms["ISForm"]["OtherIncomeLine1Year3"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine1_3)) ;
		   
		   //5. Other Income Line 2
	       document.forms["ISForm"]["OtherIncomeLine2Year0"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine2_0)) ;
	       document.forms["ISForm"]["OtherIncomeLine2Year1"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine2_1)) ;
	       document.forms["ISForm"]["OtherIncomeLine2Year2"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine2_2)) ;
	       document.forms["ISForm"]["OtherIncomeLine2Year3"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine2_3)) ;
		   
		   //6. Other Income Line 3
	       document.forms["ISForm"]["OtherIncomeLine3Year0"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine3_0)) ;
	       document.forms["ISForm"]["OtherIncomeLine3Year1"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine3_1)) ;
	       document.forms["ISForm"]["OtherIncomeLine3Year2"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine3_2)) ;
	       document.forms["ISForm"]["OtherIncomeLine3Year3"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine3_3)) ;
		   
		   //7. Other Income Line 4
	       document.forms["ISForm"]["OtherIncomeLine4Year0"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine4_0)) ;
	       document.forms["ISForm"]["OtherIncomeLine4Year1"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine4_1));
	       document.forms["ISForm"]["OtherIncomeLine4Year2"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine4_2)) ;
	       document.forms["ISForm"]["OtherIncomeLine4Year3"].value = Thousands_Separators(ToNumber(localStorage.OtherIncomeLine4_3)) ;
		   
		   //8. Opex Line - Staff Costs
	       document.forms["ISForm"]["StaffCostsYear0"].value = Thousands_Separators(ToNumber(localStorage.StaffCosts0)) ;
	       document.forms["ISForm"]["StaffCostsYear1"].value = Thousands_Separators(ToNumber(localStorage.StaffCosts1)) ;
	       document.forms["ISForm"]["StaffCostsYear2"].value = Thousands_Separators(ToNumber(localStorage.StaffCosts2)) ;
	       document.forms["ISForm"]["StaffCostsYear3"].value = Thousands_Separators(ToNumber(localStorage.StaffCosts3)) ;

		   //9. Opex Line - Directors Fees
	       document.forms["ISForm"]["DirectorsFeesYear0"].value = Thousands_Separators(ToNumber(localStorage.DirectorsFees0)) ;
	       document.forms["ISForm"]["DirectorsFeesYear1"].value = Thousands_Separators(ToNumber(localStorage.DirectorsFees1)) ;
	       document.forms["ISForm"]["DirectorsFeesYear2"].value = Thousands_Separators(ToNumber(localStorage.DirectorsFees2)) ;
	       document.forms["ISForm"]["DirectorsFeesYear3"].value = Thousands_Separators(ToNumber(localStorage.DirectorsFees3)) ;
		   
		   //10. Opex Line - Depreciation
	       document.forms["ISForm"]["DepreciationYear0"].value = Thousands_Separators(ToNumber(localStorage.Depreciation0)) ;
	       document.forms["ISForm"]["DepreciationYear1"].value = Thousands_Separators(ToNumber(localStorage.Depreciation1)) ;
	       document.forms["ISForm"]["DepreciationYear2"].value = Thousands_Separators(ToNumber(localStorage.Depreciation2)) ;
	       document.forms["ISForm"]["DepreciationYear3"].value = Thousands_Separators(ToNumber(localStorage.Depreciation3)) ;
		   
		   //11. Opex Line - Amortisation
	       document.forms["ISForm"]["AmortisationYear0"].value = Thousands_Separators(ToNumber(localStorage.Amortisation0)) ;
	       document.forms["ISForm"]["AmortisationYear1"].value = Thousands_Separators(ToNumber(localStorage.Amortisation1)) ;
	       document.forms["ISForm"]["AmortisationYear2"].value = Thousands_Separators(ToNumber(localStorage.Amortisation2)) ;
	       document.forms["ISForm"]["AmortisationYear3"].value = Thousands_Separators(ToNumber(localStorage.Amortisation3)) ;
		   
		   //12. Opex Line - NoNameOpex Line 1
	       document.forms["ISForm"]["NoNameOpexLine1Year0"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine1_0)) ;
	       document.forms["ISForm"]["NoNameOpexLine1Year1"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine1_1)) ;
	       document.forms["ISForm"]["NoNameOpexLine1Year2"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine1_2)) ;
	       document.forms["ISForm"]["NoNameOpexLine1Year3"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine1_3)) ;

           //13. Opex Line - NoNameOPex Line 2
	       document.forms["ISForm"]["NoNameOpexLine2Year0"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine2_0)) ;
	       document.forms["ISForm"]["NoNameOpexLine2Year1"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine2_1)) ;
	       document.forms["ISForm"]["NoNameOpexLine2Year2"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine2_2)) ;
	       document.forms["ISForm"]["NoNameOpexLine2Year3"].value = Thousands_Separators(ToNumber(localStorage.NoNameOpexLine2_3)) ;
		   
		   //14. Opex Line - Other
	       document.forms["ISForm"]["OtherOpexYear0"].value = Thousands_Separators(ToNumber(localStorage.OtherOpex0)) ;
	       document.forms["ISForm"]["OtherOpexYear1"].value = Thousands_Separators(ToNumber(localStorage.OtherOpex1)) ;
	       document.forms["ISForm"]["OtherOpexYear2"].value = Thousands_Separators(ToNumber(localStorage.OtherOpex2)) ;
 	       document.forms["ISForm"]["OtherOpexYear3"].value = Thousands_Separators(ToNumber(localStorage.OtherOpex3)) ;
           
		   //15. Net Finance Costs
 	       document.forms["ISForm"]["NetFinanceCostsYear0"].value = Thousands_Separators(ToNumber(localStorage.NetFinanceCosts0)) ;
	       document.forms["ISForm"]["NetFinanceCostsYear1"].value = Thousands_Separators(ToNumber(localStorage.NetFinanceCosts1)) ;
	       document.forms["ISForm"]["NetFinanceCostsYear2"].value = Thousands_Separators(ToNumber(localStorage.NetFinanceCosts2)) ;
 	       document.forms["ISForm"]["NetFinanceCostsYear3"].value = Thousands_Separators(ToNumber(localStorage.NetFinanceCosts3)) ;
            
		   //16. Income Tax
  	       document.forms["ISForm"]["IncomeTaxYear0"].value = Thousands_Separators(ToNumber(localStorage.IncomeTax0)) ;
 	       document.forms["ISForm"]["IncomeTaxYear1"].value = Thousands_Separators(ToNumber(localStorage.IncomeTax1)) ;
	       document.forms["ISForm"]["IncomeTaxYear2"].value = Thousands_Separators(ToNumber(localStorage.IncomeTax2)) ;
 	       document.forms["ISForm"]["IncomeTaxYear3"].value = Thousands_Separators(ToNumber(localStorage.IncomeTax3)) ;
             
		   //16. Deferred Tax
  	       document.forms["ISForm"]["DeferredTaxYear0"].value = Thousands_Separators(ToNumber(localStorage.DeferredTax0)) ;
 	       document.forms["ISForm"]["DeferredTaxYear1"].value = Thousands_Separators(ToNumber(localStorage.DeferredTax1)) ;
	       document.forms["ISForm"]["DeferredTaxYear2"].value = Thousands_Separators(ToNumber(localStorage.DeferredTax2)) ;
 	       document.forms["ISForm"]["DeferredTaxYear3"].value = Thousands_Separators(ToNumber(localStorage.DeferredTax3)) ;
 				   
           //17. Other Income Line Descriptions
 	       document.forms["ISForm"]["OtherIncomeDescLine1"].value = localStorage.OtherIncomeDescLine1 ;
	       document.forms["ISForm"]["OtherIncomeDescLine2"].value = localStorage.OtherIncomeDescLine2 ;
 	       document.forms["ISForm"]["OtherIncomeDescLine3"].value = localStorage.OtherIncomeDescLine3 ;
	       document.forms["ISForm"]["OtherIncomeDescLine4"].value = localStorage.OtherIncomeDescLine4 ;
          //18. Other Opex Line Descriptions
	       document.forms["ISForm"]["NoNameOpexDescLine1"].value = localStorage.NoNameOpexDescLine1 ;
 	       document.forms["ISForm"]["NoNameOpexDescLine2"].value = localStorage.NoNameOpexDescLine2 ;

   }
			
		
		function Recalculate()
		{
			 
			 // Read input values from form into working placeholder variables
		   //1. Net Sales
	       var NetSales0 = ToNumber(document.forms["ISForm"]["NetSalesYear0"].value);
	       var NetSales1 = ToNumber(document.forms["ISForm"]["NetSalesYear1"].value);
	       var NetSales2 = ToNumber(document.forms["ISForm"]["NetSalesYear2"].value);    
		   var NetSales3 = ToNumber(document.forms["ISForm"]["NetSalesYear3"].value);  
		   
		   //2. Cost of Sales - Depreciation  
		   var CosDep0 = ToNumber(document.forms["ISForm"]["CosDepYear0"].value);
		   var CosDep1 = ToNumber(document.forms["ISForm"]["CosDepYear1"].value);
           var CosDep2 = ToNumber(document.forms["ISForm"]["CosDepYear2"].value);
		   var CosDep3 = ToNumber(document.forms["ISForm"]["CosDepYear3"].value);
		   
		   //3. Cost of Sales - Other
		   var CosOther0 = ToNumber(document.forms["ISForm"]["CosOtherYear0"].value);
		   var CosOther1 = ToNumber(document.forms["ISForm"]["CosOtherYear1"].value);
	       var CosOther2 = ToNumber(document.forms["ISForm"]["CosOtherYear2"].value);
		   var CosOther3 = ToNumber(document.forms["ISForm"]["CosOtherYear3"].value);
		   
		   //4. Other Income Line 1
		   var OtherIncomeLine1_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year0"].value);
		   var OtherIncomeLine1_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year1"].value);
	       var OtherIncomeLine1_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year2"].value);
		   var OtherIncomeLine1_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine1Year3"].value);
		   
		   //5. Other Income Line 2
		   var OtherIncomeLine2_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year0"].value);
		   var OtherIncomeLine2_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year1"].value);
	       var OtherIncomeLine2_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year2"].value);
		   var OtherIncomeLine2_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine2Year3"].value);
		   
		   //6. Other Income Line 3
		   var OtherIncomeLine3_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year0"].value);
 		   var OtherIncomeLine3_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year1"].value);
           var OtherIncomeLine3_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year2"].value);
		   var OtherIncomeLine3_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine3Year3"].value);
		   
		   //7. Other Income Line 4
		   var OtherIncomeLine4_0 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year0"].value);
		   var OtherIncomeLine4_1 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year1"].value);
		   var OtherIncomeLine4_2 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year2"].value);
		   var OtherIncomeLine4_3 = ToNumber(document.forms["ISForm"]["OtherIncomeLine4Year3"].value);
		   
		   //8. Opex Line - Staff Costs
		   var StaffCosts0 = ToNumber(document.forms["ISForm"]["StaffCostsYear0"].value);
		   var StaffCosts1 = ToNumber(document.forms["ISForm"]["StaffCostsYear1"].value);
		   var StaffCosts2 = ToNumber(document.forms["ISForm"]["StaffCostsYear2"].value);
		   var StaffCosts3 = ToNumber(document.forms["ISForm"]["StaffCostsYear3"].value);

		   //9. Opex Line - Directors Fees
		   var DirectorsFees0 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear0"].value);
		   var DirectorsFees1 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear1"].value);
		   var DirectorsFees2 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear2"].value);
		   var DirectorsFees3 = ToNumber(document.forms["ISForm"]["DirectorsFeesYear3"].value);
		   
		   //10. Opex Line - Depreciation
		   var Depreciation0 = ToNumber(document.forms["ISForm"]["DepreciationYear0"].value);
		   var Depreciation1 = ToNumber(document.forms["ISForm"]["DepreciationYear1"].value);
		   var Depreciation2 = ToNumber(document.forms["ISForm"]["DepreciationYear2"].value);
		   var Depreciation3 = ToNumber(document.forms["ISForm"]["DepreciationYear3"].value);
		   
		   //11. Opex Line - Amortisation
		   var Amortisation0 = ToNumber(document.forms["ISForm"]["AmortisationYear0"].value);
		   var Amortisation1 = ToNumber(document.forms["ISForm"]["AmortisationYear1"].value);
		   var Amortisation2 = ToNumber(document.forms["ISForm"]["AmortisationYear2"].value);
		   var Amortisation3 = ToNumber(document.forms["ISForm"]["AmortisationYear3"].value);
		   
		   //12. Opex Line - NoNameOpex Line 1
		   var NoNameOpexLine1_0 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year0"].value);
		   var NoNameOpexLine1_1 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year1"].value);
		   var NoNameOpexLine1_2 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year2"].value);
		   var NoNameOpexLine1_3 = ToNumber(document.forms["ISForm"]["NoNameOpexLine1Year3"].value);

           //13. Opex Line - NoNameOPex Line 2
		   var NoNameOpexLine2_0 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year0"].value);
		   var NoNameOpexLine2_1 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year1"].value);
	       var NoNameOpexLine2_2 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year2"].value);
		   var NoNameOpexLine2_3 = ToNumber(document.forms["ISForm"]["NoNameOpexLine2Year3"].value);
		   
		   //14. Opex Line - Other
		   var OtherOpex0 = ToNumber(document.forms["ISForm"]["OtherOpexYear0"].value);
 		   var OtherOpex1 = ToNumber(document.forms["ISForm"]["OtherOpexYear1"].value);
           var OtherOpex2 = ToNumber(document.forms["ISForm"]["OtherOpexYear2"].value);
           var OtherOpex3 = ToNumber(document.forms["ISForm"]["OtherOpexYear3"].value);
           
		   //15. Net Finance Costs
           var NetFinanceCosts0 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear0"].value);
           var NetFinanceCosts1 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear1"].value);
		   var NetFinanceCosts2 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear2"].value);
		   var NetFinanceCosts3 = ToNumber(document.forms["ISForm"]["NetFinanceCostsYear3"].value);
           
		   //16. Income Tax
           var IncomeTax0 = ToNumber(document.forms["ISForm"]["IncomeTaxYear0"].value);
           var IncomeTax1 = ToNumber(document.forms["ISForm"]["IncomeTaxYear1"].value);
		   var IncomeTax2 = ToNumber(document.forms["ISForm"]["IncomeTaxYear2"].value);
		   var IncomeTax3 = ToNumber(document.forms["ISForm"]["IncomeTaxYear3"].value);
           
		   //16. Deferred Tax
           var DeferredTax0 = ToNumber(document.forms["ISForm"]["DeferredTaxYear0"].value);
           var DeferredTax1 = ToNumber(document.forms["ISForm"]["DeferredTaxYear1"].value);
		   var DeferredTax2 = ToNumber(document.forms["ISForm"]["DeferredTaxYear2"].value);
		   var DeferredTax3 = ToNumber(document.forms["ISForm"]["DeferredTaxYear3"].value);
		   
		   // RECALCULATING CALCULATED FIELDS
			
		   //1. Total Cost of Sales
	       var CosTotal0 = CosDep0 + CosOther0;
	       var CosTotal1 = CosDep1 + CosOther1;
	       var CosTotal2 = CosDep2 + CosOther2;
		   var CosTotal3 = CosDep3 + CosOther3;
		   
		   //2.Gross Profit
		   var GrossProfit0 = NetSales0 - CosTotal0;
		   var GrossProfit1 = NetSales1 - CosTotal1;
	       var GrossProfit2 = NetSales2 - CosTotal2;
		   var GrossProfit3 = NetSales3 - CosTotal3;
		   
		   //3. Total Other Income  
		   var OtherIncomeTotal0 = OtherIncomeLine1_0 + OtherIncomeLine2_0 + OtherIncomeLine3_0 + OtherIncomeLine4_0;
		   var OtherIncomeTotal1 = OtherIncomeLine1_1 + OtherIncomeLine2_1 + OtherIncomeLine3_1 + OtherIncomeLine4_1;
		   var OtherIncomeTotal2 = OtherIncomeLine1_2 + OtherIncomeLine2_2 + OtherIncomeLine3_2 + OtherIncomeLine4_2;
		   var OtherIncomeTotal3 = OtherIncomeLine1_3 + OtherIncomeLine2_3 + OtherIncomeLine3_3 + OtherIncomeLine4_3;
		   
		   //4. Total Opex
		   var OpexTotal0 = StaffCosts0 + DirectorsFees0 + Depreciation0 + Amortisation0 + NoNameOpexLine1_0 + NoNameOpexLine2_0 + OtherOpex0;
 		   var OpexTotal1 = StaffCosts1 + DirectorsFees1 + Depreciation1 + Amortisation1 + NoNameOpexLine1_1 + NoNameOpexLine2_1 + OtherOpex1;
           var OpexTotal2 = StaffCosts2 + DirectorsFees2 + Depreciation2 + Amortisation2 + NoNameOpexLine1_2 + NoNameOpexLine2_2 + OtherOpex2;
		   var OpexTotal3 = StaffCosts3 + DirectorsFees3 + Depreciation3 + Amortisation3 + NoNameOpexLine1_3 + NoNameOpexLine2_3 + OtherOpex3;
		   
		   //5. EBIT (Operating Profit)
		   var EBIT0 = GrossProfit0 + OtherIncomeTotal0 - OpexTotal0;
		   var EBIT1 = GrossProfit1 + OtherIncomeTotal1 - OpexTotal1;
           var EBIT2 = GrossProfit2 + OtherIncomeTotal2 - OpexTotal2; 
           var EBIT3 = GrossProfit3 + OtherIncomeTotal3 - OpexTotal3;

           //6. PBT (Profit Before Tax)
           var PBT0 = EBIT0 - NetFinanceCosts0;
           var PBT1 = EBIT1 - NetFinanceCosts1;
		   var PBT2 = EBIT2 - NetFinanceCosts2;
		   var PBT3 = EBIT3 - NetFinanceCosts3;
          //7. Taxation
           var Taxation0 = IncomeTax0 + DeferredTax0;
           var Taxation1 = IncomeTax1 + DeferredTax1;
		   var Taxation2 = IncomeTax2 + DeferredTax2;
		   var Taxation3 = IncomeTax3 + DeferredTax3;
         //7. Net Profit
           var NetProfit0 = PBT0 - Taxation0;
           var NetProfit1 = PBT1 - Taxation1;
		   var NetProfit2 = PBT2 - Taxation2;
		   var NetProfit3 = PBT3 - Taxation3;
			   
		   // DISPLAY THE CALCULATED FIELDS IN COMMA STYLE
	       
		   //1. Total Cost Of Sales
	       document.forms["ISForm"]["CosTotalYear0"].value = Thousands_Separators(CosTotal0);
	       document.forms["ISForm"]["CosTotalYear1"].value = Thousands_Separators(CosTotal1);
	       document.forms["ISForm"]["CosTotalYear2"].value = Thousands_Separators(CosTotal2);
		   document.forms["ISForm"]["CosTotalYear3"].value = Thousands_Separators(CosTotal3);
		   
		   //2. Gross Profit 
		   document.forms["ISForm"]["GrossProfitYear0"].value = Thousands_Separators(GrossProfit0);
		   document.forms["ISForm"]["GrossProfitYear1"].value = Thousands_Separators(GrossProfit1);
		   document.forms["ISForm"]["GrossProfitYear2"].value = Thousands_Separators(GrossProfit2);
		   document.forms["ISForm"]["GrossProfitYear3"].value = Thousands_Separators(GrossProfit3);
		   
		   //3. Total Other Income
		   document.forms["ISForm"]["OtherIncomeTotalYear0"].value = Thousands_Separators(OtherIncomeTotal0);
		   document.forms["ISForm"]["OtherIncomeTotalYear1"].value = Thousands_Separators(OtherIncomeTotal1);
		   document.forms["ISForm"]["OtherIncomeTotalYear2"].value = Thousands_Separators(OtherIncomeTotal2);
		   document.forms["ISForm"]["OtherIncomeTotalYear3"].value = Thousands_Separators(OtherIncomeTotal3);
		   
		   //4. Total Opex
		   document.forms["ISForm"]["OpexTotalYear0"].value = Thousands_Separators(OpexTotal0);
		   document.forms["ISForm"]["OpexTotalYear1"].value = Thousands_Separators(OpexTotal1);
		   document.forms["ISForm"]["OpexTotalYear2"].value = Thousands_Separators(OpexTotal2);
		   document.forms["ISForm"]["OpexTotalYear3"].value = Thousands_Separators(OpexTotal3);
		   
		   //5. EBIT
		   document.forms["ISForm"]["EBITYear0"].value = Thousands_Separators(EBIT0);
		   document.forms["ISForm"]["EBITYear1"].value = Thousands_Separators(EBIT1);
		   document.forms["ISForm"]["EBITYear2"].value = Thousands_Separators(EBIT2);
		   document.forms["ISForm"]["EBITYear3"].value = Thousands_Separators(EBIT3);
		   
		   //6. PBT
		   document.forms["ISForm"]["PBTYear0"].value = Thousands_Separators(PBT0);
		   document.forms["ISForm"]["PBTYear1"].value = Thousands_Separators(PBT1);
		   document.forms["ISForm"]["PBTYear2"].value = Thousands_Separators(PBT2);
		   document.forms["ISForm"]["PBTYear3"].value = Thousands_Separators(PBT3);
		   //7. Taxation for the Year
		   document.forms["ISForm"]["TaxationYear0"].value = Thousands_Separators(Taxation0);
		   document.forms["ISForm"]["TaxationYear1"].value = Thousands_Separators(Taxation1);
		   document.forms["ISForm"]["TaxationYear2"].value = Thousands_Separators(Taxation2);
		   document.forms["ISForm"]["TaxationYear3"].value = Thousands_Separators(Taxation3);
		   //7. Net Profit
		   document.forms["ISForm"]["NetProfitYear0"].value = Thousands_Separators(NetProfit0);
		   document.forms["ISForm"]["NetProfitYear1"].value = Thousands_Separators(NetProfit1);
		   document.forms["ISForm"]["NetProfitYear2"].value = Thousands_Separators(NetProfit2);
		   document.forms["ISForm"]["NetProfitYear3"].value = Thousands_Separators(NetProfit3);
		   
		}
	  function Thousands_Separators(num)
      {
         numStr = num+= "";
		// var num_parts = num.toString().split(".");
         //num_parts[0] = num_parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return numStr.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		//return num_parts.join(".");
	
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


				
				
<body style="background-color:lightgrey;" >
  <form name="ISForm"  onsubmit="SaveData()" method="post" action = "SaveIncomeStatementData.php">    
   
   <table border = 0 cellspacing = 0 class = "bdr" align = "center" >
  	  <tr>
	    <td><img src="img1.gif" alt="nknk" name="g" width="497" height="208"/></td>
		<td><img src="img2.jpg" alt="nknk" name="g" width="497" height="208"/></td>
	   </tr>
	   <table align = "center">
	   
	  <tr>
	      <td cellspacing = 0 rowspan = 2"><strong><p id = "legal_name">COMPANY NAME</p></strong></td>
	      <td align = center>Application Ref No</td>
	      <td align = center>Company Reg No</td>
		  <td align = center>Username</td>
	  </tr>
      <tr>
	     <td><input style="background-color:powderblue;text-align:center" name = "application_ref" readonly></td>
         <td><input style="background-color:powderblue;text-align:center" name = "company_reg_no" readonly></td>
         <td><input style="background-color:powderblue;text-align:center" name = "username" readonly></td>
	  </tr>
	  
	   <tr bgcolor = darkblue>
	      <th></th>
		  <th Width = 20 style="color:white">Year 0</th>
		  <th Width = 20 style="color:white">Year 1</th>
		  <th Width = 20 style="color:white">Year 2</th>
		  <th Width = 20 style="color:white">Year 3</th>
		</tr>
		<tr bgcolor = darkblue>
	<tr bgcolor = darkblue >
	      <th style="background-color:darkblue;color:white;text-align:left"><strong>INCOME STATEMENT<strong></th>
		  <th><input readonly type = text size = 15 style="background-color:darkblue;color:white;text-align:center;font-weight:bold;font-size:16px" name = "financial_year0"></th>
		  <th><input readonly type = text size = 15 style="background-color:darkblue;color:white;text-align:center;font-weight:bold;font-size:16px" name = "financial_year1"></th>
	      <th><input readonly type = text size = 15 style="background-color:darkblue;color:white;text-align:center;font-weight:bold;font-size:16px" name = "financial_year2"></th>
	      <th><input readonly type = text size = 15 style="background-color:darkblue;color:white;text-align:center;font-weight:bold;font-size:16px" name = "financial_year3"></th>
	    </tr>
		</tr>
		<tr bgcolor = darkblue>
		  <th></th>
		  <th Width = 20 style="color:white" id = "currency">BWP</th>
		  <th Width = 20 style="color:white" id = "currency">BWP</th>
		  <th Width = 20 style="color:white" id = "currency">BWP</th>
		  <th Width = 20 style="color:white" id = "currency">BWP</th>
		</tr>
		<tr>
		  <td><strong>Net Sales<strong></td>
		  <td><Input type="text" name="NetSalesYear0" class="price" style="text-align:right;" onblur="Recalculate()"></td>
		  <td><Input type="text" name="NetSalesYear1" class="price" style="text-align:right;" onblur="Recalculate()"></td>
		  <td><Input type="text" name="NetSalesYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type="text" name="NetSalesYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  
	   </tr>
	   <tr style ="background-color:lightblue;color:black" >
		  <td><strong>Total Cost of Sales<strong></td>
		  <td><Input type="text" name = "CosTotalYear0" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type="text" name = "CosTotalYear1" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type="text" name = "CosTotalYear2" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type="text" name = "CosTotalYear3" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
	   </tr>
	   </tr>
		  <td>Cost of sales - depreciation</td>
		  <td><Input type=text name = "CosDepYear0" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosDepYear1" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosDepYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosDepYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr>
	   </tr>
		  <td>Cost of sales - other</td>
		  <td><Input type=text name = "CosOtherYear0" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosOtherYear1" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosOtherYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "CosOtherYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr>
	   <tr style ="background-color:lightblue;" >
		  <td><strong>Gross profit<strong></td>
		  <td><Input type=text name = "GrossProfitYear0" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "GrossProfitYear1" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "GrossProfitYear2" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "GrossProfitYear3" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
	   </tr>
	   <tr></tr>
	   <tr style ="background-color:lightblue;" ><td><strong>Other Income:<strong></td>
		  <td><Input type=text name = "OtherIncomeTotalYear0" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeTotalYear1" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeTotalYear2" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherIncomeTotalYear3" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>   
	   </tr>
	   <tr>
		  <td><input type=text name = "OtherIncomeDescLine1" Size = 35 Placeholder = "Enter other income line description 1"></td>
		  <td><Input type=text name = "OtherIncomeLine1Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine1Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine1Year2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherIncomeLine1Year3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		</tr>
	    <tr>
		  <td><input type=text name = "OtherIncomeDescLine2" Size = 35 Placeholder = "Enter other income line description 2"></td>
		  <td><Input type=text name = "OtherIncomeLine2Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine2Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine2Year2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherIncomeLine2Year3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
	   <tr>
		  <td><input type=text name = "OtherIncomeDescLine3" Size = 35 Placeholder = "Enter other income line description 3"></td>
		  <td><Input type=text name = "OtherIncomeLine3Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine3Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine3Year2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherIncomeLine3Year3" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
	   </tr> 
	   <tr>
		  <td><input type=text name = "OtherIncomeDescLine4" Size = 35 Placeholder = "Enter other income line description 4"></td>
		  <td><Input type=text name = "OtherIncomeLine4Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine4Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherIncomeLine4Year2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherIncomeLine4Year3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
       <tr style ="background-color:lightblue;" ><td><strong>Operating Expenses :<strong></td>
		  <td><Input type=text name = "OpexTotalYear0" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OpexTotalYear1" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OpexTotalYear2" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OpexTotalYear3" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>   
	   </tr>
	   <tr>
		  <td>Staff Cost</td>
		  <td><Input type=text name = "StaffCostsYear0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "StaffCostsYear1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "StaffCostsYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "StaffCostsYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		</tr>
	    <tr>
		  <td>Directors Remuneration</td>
		  <td><Input type=text name = "DirectorsFeesYear0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "DirectorsFeesYear1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "DirectorsFeesYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "DirectorsFeesYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
	   <tr>
		  <td>Depreciation</td>
		  <td><Input type=text name = "DepreciationYear0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "DepreciationYear1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "DepreciationYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "DepreciationYear3" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
	   </tr> 
	   <tr>
		  <td>Amortisation</td>
		  <td><Input type=text name = "AmortisationYear0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "AmortisationYear1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "AmortisationYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "AmortisationYear3" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
	   </tr>
	    <tr>
		  <td><input type =text name = "NoNameOpexDescLine1" Size = 35 Placeholder = "Enter Significant Opex line description 1"></td>
		  <td><Input type=text name = "NoNameOpexLine1Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "NoNameOpexLine1Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "NoNameOpexLine1Year2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "NoNameOpexLine1Year3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
	   <tr>
		  <td><input type =text name = "NoNameOpexDescLine2" Size = 35 Placeholder = "Enter Significant Opex line description 2"></td>
		  <td><Input type=text name = "NoNameOpexLine2Year0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "NoNameOpexLine2Year1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "NoNameOpexLine2Year2" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
		  <td><Input type=text name = "NoNameOpexLine2Year3" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
	   </tr>
	   <tr>
		  <td>Other Opex</td>
		  <td><Input type=text name = "OtherOpexYear0" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherOpexYear1" class="price" style="text-align:right;" onchange="Recalculate()"</td>
		  <td><Input type=text name = "OtherOpexYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "OtherOpexYear3" class="price" style="text-align:right;" onchange="Recalculate()" ></td>
	   </tr>
	   <tr style ="background-color:lightblue;">
	      <td><strong>Operating Profit/EBIT :<strong></td>
		  <td><Input type=text name = "EBITYear0" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()" </td>
		  <td><Input type=text name = "EBITYear1" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()" </td>
		  <td><Input type=text name = "EBITYear2" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>
		  <td><Input type=text name = "EBITYear3" class="price" style="text-align:right;background-color:lightblue;color:black" onchange="Recalculate()"></td>   
	   </tr>
	   </tr></tr>
	   <tr>
		  <td <strong>Net Finance Costs<strong></td>
		  <td><Input type=text name = "NetFinanceCostsYear0" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "NetFinanceCostsYear1" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "NetFinanceCostsYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "NetFinanceCostsYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
	  <tr style ="background-color:blue;">
		  <td style="color:white" <strong>Profit Before Tax<strong></td>
		  <td><Input type=text name = "PBTYear0" class="price" style="text-align:right;background-color:blue;color:white" onchange="Recalculate()"></td>
		  <td><Input type=text name = "PBTYear1" class="price" style="text-align:right;background-color:blue;color:white" onchange="Recalculate()"></td>
		  <td><Input type=text name = "PBTYear2" class="price" style="text-align:right;background-color:blue;color:white" onchange="Recalculate()"></td>
		  <td><Input type=text name = "PBTYear3" class="price" style="text-align:right;background-color:blue;color:white" onchange="Recalculate()" ></td>
	   </tr> 
	  <tr style ="background-color:lightblue;color:black">
		  <td style="color:black" ><strong>Taxation for the year</strong></td>
		  <td><Input type=text name = "TaxationYear0" class="price" style="text-align:right;background-color:lightblue;color:white" readonly></td>
		  <td><Input type=text name = "TaxationYear1" class="price" style="text-align:right;background-color:lightblue;color:white" readonly></td>
		  <td><Input type=text name = "TaxationYear2" class="price" style="text-align:right;background-color:lightblue;color:white" readonly></td>
		  <td><Input type=text name = "TaxationYear3" class="price" style="text-align:right;background-color:lightblue;color:white" readonly></td>
	   </tr> 
       <tr><td>Comprising:</td></tr>
	   <tr>
		  <td <strong>Income Tax<strong></td>
		  <td><Input type=text name = "IncomeTaxYear0" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "IncomeTaxYear1" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "IncomeTaxYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "IncomeTaxYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
	   <tr>
		  <td <strong>Deferred Tax<strong></td>
		  <td><Input type=text name = "DeferredTaxYear0" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "DeferredTaxYear1" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "DeferredTaxYear2" class="price" style="text-align:right;" onchange="Recalculate()"></td>
		  <td><Input type=text name = "DeferredTaxYear3" class="price" style="text-align:right;" onchange="Recalculate()"></td>
	   </tr> 
       <tr style ="background-color:blue;">
		  <td style="color:white" <strong>Net Profit/(Loss)<strong></td>
		  <td><Input type=text name = "NetProfitYear0" class="price" style="text-align:right;background-color:blue;color:white" readonly></td>
		  <td><Input type=text name = "NetProfitYear1" class="price" style="text-align:right;background-color:blue;color:white" readonly></td>
		  <td><Input type=text name = "NetProfitYear2" class="price" style="text-align:right;background-color:blue;color:white" readonly></td>
		  <td><Input type=text name = "NetProfitYear3" class="price" style="text-align:right;background-color:blue;color:white" readonly></td>
	   </tr> 
    	   
	      <td></td>
		  <td><Input type = Submit value = "Save/Submit" onclick = "SaveData()" ></td><td><Input type = reset value = "Clear Form"></td>
	    </table>
   </table>
   <script>

  </script>
   </Form>
</body>

</html>