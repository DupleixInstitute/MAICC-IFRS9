function round(number,decimalPlaces)
{
    var factorOf10 = Math.pow(10,decimalPlaces);
    return Math.round(number * factorOf10) / factorOf10;
}
function ToNumber(nStr) 
{
	if   (typeof(nStr) == "undefined") {  return 0;}
	else  
		{
		if (nStr == "") {return 0};
		if (nStr != "") {return parseFloat(nStr.replace(/,/g,''))};
		} 
}  
function includeHTML() {
  var z, i, elmnt, file, xhttp;
  /*loop through a collection of all HTML elements:*/
  z = document.getElementsByTagName("*");
  for (i = 0; i < z.length; i++) {
    elmnt = z[i];
    /*search for elements with a certain atrribute:*/
    file = elmnt.getAttribute("w3-include-html");
    if (file) {
      /*make an HTTP request using the attribute value as the file name:*/
      xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4) {
          if (this.status == 200) {elmnt.innerHTML = this.responseText;}
          if (this.status == 404) {elmnt.innerHTML = "Page not found.";}
          /*remove the attribute, and call this function once more:*/
          elmnt.removeAttribute("w3-include-html");
          includeHTML();
        }
      }      
      xhttp.open("GET", file, true);
      xhttp.send();
      /*exit the function:*/
      return;
    }
  }
}
function MacroScenarioDefinitionsForm_LoadData()
{
		 document.MacroScenarioDefinitionsForm.ScenarioCode1.value  = localStorage.ScenarioCode1;
	     document.MacroScenarioDefinitionsForm.ScenarioCode2.value  = localStorage.ScenarioCode2;
	     document.MacroScenarioDefinitionsForm.ScenarioCode3.value  = localStorage.ScenarioCode3;
	     document.MacroScenarioDefinitionsForm.ScenarioCode4.value  = localStorage.ScenarioCode4;
	     
		 document.MacroScenarioDefinitionsForm.ScenarioCode5.value  = localStorage.ScenarioCode5;
	     document.MacroScenarioDefinitionsForm.ScenarioCode6.value  = localStorage.ScenarioCode6;
	     document.MacroScenarioDefinitionsForm.ScenarioCode7.value  = localStorage.ScenarioCode7;
	     document.MacroScenarioDefinitionsForm.ScenarioCode8.value  = localStorage.ScenarioCode8;
	     document.MacroScenarioDefinitionsForm.ScenarioCode9.value  = localStorage.ScenarioCode9;
	     document.MacroScenarioDefinitionsForm.ScenarioCode10.value = localStorage.ScenarioCode10;
		 
		 //Scenario Names
		 document.MacroScenarioDefinitionsForm.ScenarioName1.value  = localStorage.ScenarioName1;
	     document.MacroScenarioDefinitionsForm.ScenarioName2.value  = localStorage.ScenarioName2;
	     document.MacroScenarioDefinitionsForm.ScenarioName3.value  = localStorage.ScenarioName3;
	     document.MacroScenarioDefinitionsForm.ScenarioName4.value  = localStorage.ScenarioName4;
	     document.MacroScenarioDefinitionsForm.ScenarioName5.value  = localStorage.ScenarioName5;
	     document.MacroScenarioDefinitionsForm.ScenarioName6.value  = localStorage.ScenarioName6;
	     document.MacroScenarioDefinitionsForm.ScenarioName7.value  = localStorage.ScenarioName7;
	     document.MacroScenarioDefinitionsForm.ScenarioName8.value  = localStorage.ScenarioName8;
	     document.MacroScenarioDefinitionsForm.ScenarioName9.value  = localStorage.ScenarioName9;
	     document.MacroScenarioDefinitionsForm.ScenarioName10.value = localStorage.ScenarioName10;

         //Scenario Descriptions
		 document.MacroScenarioDefinitionsForm.ScenarioDesc1.value  = localStorage.ScenarioDesc1;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc2.value  = localStorage.ScenarioDesc2;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc3.value  = localStorage.ScenarioDesc3;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc4.value  = localStorage.ScenarioDesc4;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc5.value  = localStorage.ScenarioDesc5;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc6.value  = localStorage.ScenarioDesc6;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc7.value  = localStorage.ScenarioDesc7;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc8.value  = localStorage.ScenarioDesc8;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc9.value  = localStorage.ScenarioDesc9;
	     document.MacroScenarioDefinitionsForm.ScenarioDesc10.value = localStorage.ScenarioDesc10;

         //Scenario Range Sliding Bar Weights
		 document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight1.value  = localStorage.ScenarioRange_slide_weight1;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight2.value  = localStorage.ScenarioRange_slide_weight2;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight3.value  = localStorage.ScenarioRange_slide_weight3;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight4.value  = localStorage.ScenarioRange_slide_weight4;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight5.value  = localStorage.ScenarioRange_slide_weight5;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight6.value  = localStorage.ScenarioRange_slide_weight6;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight7.value  = localStorage.ScenarioRange_slide_weight7;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight8.value  = localStorage.ScenarioRange_slide_weight8;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight9.value  = localStorage.ScenarioRange_slide_weight9;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight10.value = localStorage.ScenarioRange_slide_weight10;
	 	 
         //Scenario Range Input Box Weights
		 document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight1.value  = localStorage.ScenarioRange_box_weight1;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight2.value  = localStorage.ScenarioRange_box_weight2;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight3.value  = localStorage.ScenarioRange_box_weight3;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight4.value  = localStorage.ScenarioRange_box_weight4;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight5.value  = localStorage.ScenarioRange_box_weight5;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight6.value  = localStorage.ScenarioRange_box_weight6;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight7.value  = localStorage.ScenarioRange_box_weight7;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight8.value  = localStorage.ScenarioRange_box_weight8;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight9.value  = localStorage.ScenarioRange_box_weight9;
	     document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight10.value = localStorage.ScenarioRange_box_weight10;               ;
  }   
	
function MacroScenarioDefinitionsForm_SaveData()
{
  var TotalWeights = parseInt($("#ScenarioRange_box_weightTotal").val());
  //
  if (TotalWeights == 180) {alert (TotalWeights)};
  if (TotalWeights != 100) {
     alert("error");
	 swal("failure!", "error", "success");

	 swal("Saving Suspended!", "The scenario weights must add up to 100", "success");
  }	   
   //Save Log into the local Storage Tracker DashBoard

   
   var today = new Date();
   var date = today.getDate()+'-'+(today.getMonth()+1)+'-'+today.getFullYear();
   var time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();

   
   localStorage.MacroScenarioDefinitionsTrackerDateSaved = date;
   localStorage.MacroScenarioDefinitionsTrackerSavedBy = localStorage.username;
   //Saving Row 1
   localStorage.ScenarioCode1               = document.MacroScenarioDefinitionsForm.ScenarioCode1.value;
   localStorage.ScenarioName1               = document.MacroScenarioDefinitionsForm.ScenarioName1.value;
   localStorage.ScenarioDesc1               = document.MacroScenarioDefinitionsForm.ScenarioDesc1.value;
   localStorage.ScenarioRange_slide_weight1 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight1.value;
   localStorage.ScenarioRange_box_weight1   = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight1.value;
   //Saving Row 2
   localStorage.ScenarioCode2               = document.MacroScenarioDefinitionsForm.ScenarioCode2.value;
   localStorage.ScenarioName2               = document.MacroScenarioDefinitionsForm.ScenarioName2.value;
   localStorage.ScenarioDesc2               = document.MacroScenarioDefinitionsForm.ScenarioDesc2.value;
   localStorage.ScenarioRange_slide_weight2 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight2.value;
   localStorage.ScenarioRange_box_weight2   = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight2.value;

   //Saving Row 3
   localStorage.ScenarioCode3               = document.MacroScenarioDefinitionsForm.ScenarioCode3.value;
   localStorage.ScenarioName3               = document.MacroScenarioDefinitionsForm.ScenarioName3.value;
   localStorage.ScenarioDesc3               = document.MacroScenarioDefinitionsForm.ScenarioDesc3.value;
   localStorage.ScenarioRange_slide_weight3 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight3.value;
   localStorage.ScenarioRange_box_weight3   = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight3.value;

   //Saving Row 4
   localStorage.ScenarioCode4               = document.MacroScenarioDefinitionsForm.ScenarioCode4.value;
   localStorage.ScenarioName4               = document.MacroScenarioDefinitionsForm.ScenarioName4.value;
   localStorage.ScenarioDesc4               = document.MacroScenarioDefinitionsForm.ScenarioDesc4.value;
   localStorage.ScenarioRange_slide_weight4 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight4.value;
   localStorage.ScenarioRange_box_weight4   = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight4.value;
   //Saving Row 5
   localStorage.ScenarioCode5                        = document.MacroScenarioDefinitionsForm.ScenarioCode5.value;
   localStorage.ScenarioName5                        = document.MacroScenarioDefinitionsForm.ScenarioName5.value;
   localStorage.ScenarioDesc5                        = document.MacroScenarioDefinitionsForm.ScenarioDesc5.value;
   localStorage.ScenarioRange_slide_weight5          = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight5.value;
   localStorage.ScenarioRange_box_weight5            = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight5.value;

   //Saving Row 6
   localStorage.ScenarioCode6                        = document.MacroScenarioDefinitionsForm.ScenarioCode6.value;
   localStorage.ScenarioName6                        = document.MacroScenarioDefinitionsForm.ScenarioName6.value;
   localStorage.ScenarioDesc6                        = document.MacroScenarioDefinitionsForm.ScenarioDesc6.value;
   localStorage.ScenarioRange_slide_weight6          = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight6.value;
   localStorage.ScenarioRange_box_weight6            = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight6.value;
   //Saving Row 7
   localStorage.ScenarioCode7                        = document.MacroScenarioDefinitionsForm.ScenarioCode7.value;
   localStorage.ScenarioName7                        = document.MacroScenarioDefinitionsForm.ScenarioName7.value;
   localStorage.ScenarioDesc7                        = document.MacroScenarioDefinitionsForm.ScenarioDesc7.value;
   localStorage.ScenarioRange_slide_weight7          = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight7.value;
   localStorage.ScenarioRange_box_weight7            = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight7.value;
   //Saving Row 8
   localStorage.ScenarioCode8                        = document.MacroScenarioDefinitionsForm.ScenarioCode8.value;
   localStorage.ScenarioName8                        = document.MacroScenarioDefinitionsForm.ScenarioName8.value;
   localStorage.ScenarioDesc8                        = document.MacroScenarioDefinitionsForm.ScenarioDesc8.value;
   localStorage.ScenarioRange_slide_weight8          = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight8.value;
   localStorage.ScenarioRange_box_weight8            = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight8.value;
   //Saving Row 9
   localStorage.ScenarioCode9                        = document.MacroScenarioDefinitionsForm.ScenarioCode9.value;
   localStorage.ScenarioName9                        = document.MacroScenarioDefinitionsForm.ScenarioName9.value;
   localStorage.ScenarioDesc9                        = document.MacroScenarioDefinitionsForm.ScenarioDesc9.value;
   localStorage.ScenarioRange_slide_weight9 		 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight9.value;
   localStorage.ScenarioRange_box_weight9            = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight9.value;
   //Saving Row 10
   localStorage.ScenarioCode10                       = document.MacroScenarioDefinitionsForm.ScenarioCode10.value;
   localStorage.ScenarioName10                       = document.MacroScenarioDefinitionsForm.ScenarioName10.value;
   localStorage.ScenarioDesc10                       = document.MacroScenarioDefinitionsForm.ScenarioDesc10.value;
   localStorage.ScenarioRange_slide_weight10 		 = document.MacroScenarioDefinitionsForm.ScenarioRange_slide_weight10.value;
   localStorage.ScenarioRange_box_weight10           = document.MacroScenarioDefinitionsForm.ScenarioRange_box_weight10.value;

   alert ("Macro Scenario Definitions temporarilly saved(overwritten) to Local Machine");
   //SAVING TO DATABASE===========================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	//$(".form-group").removeClass("has-error");
    //$(".help-block").remove();
    
	//==============================================================================================

    
	
	var formData = {
      //Row 1
	  ScenarioCode1					: $("#ScenarioCode1").val(),
      ScenarioName1					: $("#ScenarioName1").val(),
      ScenarioDesc1					: $("#ScenarioDesc1").val(),
      ScenarioRange_box_weight1	    : $("#ScenarioRange_box_weight1").val(),
    //Row 2
	  ScenarioCode2					: $("#ScenarioCode2").val(),
      ScenarioName2					: $("#ScenarioName2").val(),
      ScenarioDesc2					: $("#ScenarioDesc2").val(),
      ScenarioRange_box_weight2	    : $("#ScenarioRange_box_weight2").val(),
    //Row 3
	  ScenarioCode3					: $("#ScenarioCode3").val(),
      ScenarioName3					: $("#ScenarioName3").val(),
      ScenarioDesc3					: $("#ScenarioDesc3").val(),
      ScenarioRange_box_weight3	    : $("#ScenarioRange_box_weight3").val(),
    //Row 4
	  ScenarioCode4					: $("#ScenarioCode4").val(),
      ScenarioName4					: $("#ScenarioName4").val(),
      ScenarioDesc4					: $("#ScenarioDesc4").val(),
      ScenarioRange_box_weight4	    : $("#ScenarioRange_box_weight4").val(),
    //Row 5
	  ScenarioCode5					: $("#ScenarioCode5").val(),
      ScenarioName5					: $("#ScenarioName5").val(),
      ScenarioDesc5					: $("#ScenarioDesc5").val(),
      ScenarioRange_box_weight5	    : $("#ScenarioRange_box_weight5").val(),
    //Row 6
	  ScenarioCode6					: $("#ScenarioCode6").val(),
      ScenarioName6					: $("#ScenarioName6").val(),
      ScenarioDesc6					: $("#ScenarioDesc6").val(),
      ScenarioRange_box_weight6	    : $("#ScenarioRange_box_weight6").val(),
    //Row 7
	  ScenarioCode7					: $("#ScenarioCode7").val(),
      ScenarioName7					: $("#ScenarioName7").val(),
      ScenarioDesc7					: $("#ScenarioDesc7").val(),
      ScenarioRange_box_weight7	    : $("#ScenarioRange_box_weight7").val(),
    //Row 8
	  ScenarioCode8					: $("#ScenarioCode8").val(),
      ScenarioName8					: $("#ScenarioName8").val(),
      ScenarioDesc8					: $("#ScenarioDesc8").val(),
      ScenarioRange_box_weight8	    : $("#ScenarioRange_box_weight8").val(),
    //Row 9
	  ScenarioCode9					: $("#ScenarioCode9").val(),
      ScenarioName9					: $("#ScenarioName9").val(),
      ScenarioDesc9					: $("#ScenarioDesc9").val(),
      ScenarioRange_box_weight9	    : $("#ScenarioRange_box_weight9").val(),
    //Row 10
	  ScenarioCode10				: $("#ScenarioCode10").val(),
      ScenarioName10				: $("#ScenarioName10").val(),
      ScenarioDesc10				: $("#ScenarioDesc10").val(),
      ScenarioRange_box_weight10	: $("#ScenarioRange_box_weight10").val(),
    };

    
	//Using $.post instead of $.ajax
    //jQuery also provides a $.post shorthand method as an alternative to $.ajax.

    //The $.ajax code in form.js could be rewritten with $.post:

    //$.post('process.php', function(formData) {
    // place success code here
    //})
    //.fail(function(data) {
    // place error code here
    //});
    //The advantage of $.post is it does not require as much connection configuration to be declared.
	
	$.ajax({
      type: "POST",
      url: "save_macro_scenario_definitionData.php",
      data: formData,
      dataType: "json",
      encode: true,
    })
	
	
	.done(function (data) {
      console.log(data);
  	/**===============================================================================================================================

 	Step 5 — Displaying Server Connection Errors
    If there is an error connecting to the server, there will be no JSON response from the AJAX call. 
    To prevent users from waiting for a response that will never arrive, you can provide an error message for connection failures.
    If the server is broken or down for any reason, a user who attempts to submit a form will get an error message:   
	**/
	


    /**==============================================================================================================================
	//Step 4 — Displaying Form Validation Errors
    //In the PHP script, the code checks to ensure that all the fields are required. 
	//If a field is not present, an error is sent back.
    This code checks to see if the response contains an error for each field. 
	If an error is present, it adds a has-error class and appends the error message.
    Now, revisit your form in a web browser and experiment with submitting data with the form.
    If there are any errors that come back from the server, the form will provide feedback on any required fields:
    **/
	
	if (!data.success) {
    
	}
    
	else {
	  	swal("Good job**!", data.message, "success");
	}

    })
	//=====================================================================================================================================
	.fail(function (data) {
        $("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });  //fail
}        //end function

function MacroStatisticDefinitionsForm_SaveData()
{
   //Save Log into the local Storage Tracker DashBoard

   
   var today = new Date();
   var date = today.getDate()+'-'+(today.getMonth()+1)+'-'+today.getFullYear();
   var time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();

   
   localStorage.MacroStatisticDefinitionsTrackerDateSaved = date;
   localStorage.MacroStatisticDefinitionsTrackerSavedBy = localStorage.username;
   //Saving Row 1
   localStorage.StatisticCode1           = $("#StatisticCode1").val();
   localStorage.StatisticName1           = $("#StatisticName1").val();;
   localStorage.StatisticDesc1           = $("#StatisticDesc1").val();;
   localStorage.ForecastingInterval1     = $("#ForecastingInterval1").val();;
   localStorage.HistoricalPeriods1       = $("#HistoricalPeriods1").val();
   localStorage.ForecastingPeriods1      = $("#ForecastingPeriods1").val();
    
   //Saving Row 2
   localStorage.StatisticCode2           = $("#StatisticCode2").val();
   localStorage.StatisticName2           = $("#StatisticName2").val();;
   localStorage.StatisticDesc2           = $("#StatisticDesc2").val();;
   localStorage.ForecastingInterval2     = $("#ForecastingInterval2").val();;
   localStorage.HistoricalPeriods2       = $("#HistoricalPeriods2").val();
   localStorage.ForecastingPeriods2      = $("#ForecastingPeriods2").val();

   //Saving Row 3
   localStorage.StatisticCode3           = $("#StatisticCode3").val();
   localStorage.StatisticName3           = $("#StatisticName3").val();;
   localStorage.StatisticDesc3           = $("#StatisticDesc3").val();;
   localStorage.ForecastingInterval3     = $("#ForecastingInterval3").val();;
   localStorage.HistoricalPeriods3       = $("#HistoricalPeriods3").val();
   localStorage.ForecastingPeriods3      = $("#ForecastingPeriods3").val();

   //Saving Row 4
   localStorage.StatisticCode4           = $("#StatisticCode4").val();
   localStorage.StatisticName4           = $("#StatisticName4").val();;
   localStorage.StatisticDesc4           = $("#StatisticDesc4").val();;
   localStorage.ForecastingInterval4     = $("#ForecastingInterval4").val();;
   localStorage.HistoricalPeriods4       = $("#HistoricalPeriods4").val();
   localStorage.ForecastingPeriods4      = $("#ForecastingPeriods4").val();
   
   //Saving Row 5
   localStorage.StatisticCode5           = $("#StatisticCode5").val();
   localStorage.StatisticName5           = $("#StatisticName5").val();;
   localStorage.StatisticDesc5           = $("#StatisticDesc5").val();;
   localStorage.ForecastingInterval5     = $("#ForecastingInterval5").val();;
   localStorage.HistoricalPeriods5       = $("#HistoricalPeriods5").val();
   localStorage.ForecastingPeriods5      = $("#ForecastingPeriods5").val();

   //Saving Row 6
   localStorage.StatisticCode6           = $("#StatisticCode6").val();
   localStorage.StatisticName6           = $("#StatisticName6").val();;
   localStorage.StatisticDesc6           = $("#StatisticDesc6").val();;
   localStorage.ForecastingInterval6     = $("#ForecastingInterval6").val();;
   localStorage.HistoricalPeriods6       = $("#HistoricalPeriods6").val();
   localStorage.ForecastingPeriods6      = $("#ForecastingPeriods6").val();
  
  //Saving Row 7
   localStorage.StatisticCode7           = $("#StatisticCode7").val();
   localStorage.StatisticName7           = $("#StatisticName7").val();;
   localStorage.StatisticDesc7           = $("#StatisticDesc7").val();;
   localStorage.ForecastingInterval7     = $("#ForecastingInterval7").val();;
   localStorage.HistoricalPeriods7       = $("#HistoricalPeriods7").val();
   localStorage.ForecastingPeriods7      = $("#ForecastingPeriods7").val();
  
  //Saving Row 8
   localStorage.StatisticCode8           = $("#StatisticCode8").val();
   localStorage.StatisticName8           = $("#StatisticName8").val();;
   localStorage.StatisticDesc8           = $("#StatisticDesc8").val();;
   localStorage.ForecastingInterval8     = $("#ForecastingInterval8").val();;
   localStorage.HistoricalPeriods8       = $("#HistoricalPeriods8").val();
   localStorage.ForecastingPeriods8      = $("#ForecastingPeriods8").val();
 
   //Saving Row 9
   localStorage.StatisticCode9           = $("#StatisticCode9").val();
   localStorage.StatisticName9           = $("#StatisticName9").val();;
   localStorage.StatisticDesc9           = $("#StatisticDesc9").val();;
   localStorage.ForecastingInterval9     = $("#ForecastingInterval9").val();;
   localStorage.HistoricalPeriods9       = $("#HistoricalPeriods9").val();
   localStorage.ForecastingPeriods9      = $("#ForecastingPeriods9").val();
  
   //Saving Row 10
   localStorage.StatisticCode10           = $("#StatisticCode10").val();
   localStorage.StatisticName10           = $("#StatisticName10").val();;
   localStorage.StatisticDesc10           = $("#StatisticDesc10").val();;
   localStorage.ForecastingInterval10     = $("#ForecastingInterval10").val();;
   localStorage.HistoricalPeriods10       = $("#HistoricalPeriods10").val();
   localStorage.ForecastingPeriods10      = $("#ForecastingPeriods10").val();

   alert ("Macro Statistic Definitions temporarilly saved(overwritten) to Local Machine");
   //SAVING TO DATABASE===========================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	//$(".form-group").removeClass("has-error");
    //$(".help-block").remove();
    
	//==============================================================================================

    
	
	var formData = {
      //Row 1
	  StatisticCode1           : $("#StatisticCode1").val(),
      StatisticName1           : $("#StatisticName1").val(),
      StatisticDesc1           : $("#StatisticDesc1").val(),
      ForecastingInterval1     : $("#ForecastingInterval1").val(),
      HistoricalPeriods1       : $("#HistoricalPeriods1").val(),
      ForecastingPeriods1      : $("#ForecastingPeriods1").val(),
      //Row 2
	  StatisticCode2           : $("#StatisticCode2").val(),
      StatisticName2           : $("#StatisticName2").val(),
      StatisticDesc2           : $("#StatisticDesc2").val(),
      ForecastingInterval2     : $("#ForecastingInterval2").val(),
      HistoricalPeriods2       : $("#HistoricalPeriods2").val(),
      ForecastingPeriods2      : $("#ForecastingPeriods2").val(),
    //Row 3
	  StatisticCode3           : $("#StatisticCode3").val(),
      StatisticName3           : $("#StatisticName3").val(),
      StatisticDesc3           : $("#StatisticDesc3").val(),
      ForecastingInterval3     : $("#ForecastingInterval3").val(),
      HistoricalPeriods3       : $("#HistoricalPeriods3").val(),
      ForecastingPeriods3      : $("#ForecastingPeriods3").val(),
    //Row 4
	  StatisticCode4           : $("#StatisticCode4").val(),
      StatisticName4           : $("#StatisticName4").val(),
      StatisticDesc4           : $("#StatisticDesc4").val(),
      ForecastingInterval4     : $("#ForecastingInterval4").val(),
      HistoricalPeriods4       : $("#HistoricalPeriods4").val(),
      ForecastingPeriods4      : $("#ForecastingPeriods4").val(),
    //Row 5
	  StatisticCode5           : $("#StatisticCode5").val(),
      StatisticName5           : $("#StatisticName5").val(),
      StatisticDesc5           : $("#StatisticDesc5").val(),
      ForecastingInterval5     : $("#ForecastingInterval5").val(),
      HistoricalPeriods5       : $("#HistoricalPeriods5").val(),
      ForecastingPeriods5      : $("#ForecastingPeriods5").val(),
    //Row 6
	  StatisticCode6           : $("#StatisticCode6").val(),
      StatisticName6           : $("#StatisticName6").val(),
      StatisticDesc6           : $("#StatisticDesc6").val(),
      ForecastingInterval6     : $("#ForecastingInterval6").val(),
      HistoricalPeriods6       : $("#HistoricalPeriods6").val(),
      ForecastingPeriods6      : $("#ForecastingPeriods6").val(),
    //Row 7
	  StatisticCode7           : $("#StatisticCode7").val(),
      StatisticName7           : $("#StatisticName7").val(),
      StatisticDesc7           : $("#StatisticDesc7").val(),
      ForecastingInterval7     : $("#ForecastingInterval7").val(),
      HistoricalPeriods7       : $("#HistoricalPeriods7").val(),
      ForecastingPeriods7      : $("#ForecastingPeriods7").val(),
    //Row 8
	  StatisticCode8           : $("#StatisticCode8").val(),
      StatisticName8           : $("#StatisticName8").val(),
      StatisticDesc8           : $("#StatisticDesc8").val(),
      ForecastingInterval8     : $("#ForecastingInterval8").val(),
      HistoricalPeriods8       : $("#HistoricalPeriods8").val(),
      ForecastingPeriods8      : $("#ForecastingPeriods8").val(),
    //Row 9
	  StatisticCode9           : $("#StatisticCode9").val(),
      StatisticName9           : $("#StatisticName9").val(),
      StatisticDesc9           : $("#StatisticDesc9").val(),
      ForecastingInterval9     : $("#ForecastingInterval9").val(),
      HistoricalPeriods9       : $("#HistoricalPeriods9").val(),
      ForecastingPeriods9      : $("#ForecastingPeriods9").val(),
    //Row 10
	  StatisticCode10          : $("#StatisticCode10").val(),
      StatisticName10          : $("#StatisticName10").val(),
      StatisticDesc10          : $("#StatisticDesc10").val(),
      ForecastingInterval10    : $("#ForecastingInterval10").val(),
      HistoricalPeriods10      : $("#HistoricalPeriods10").val(),
      ForecastingPeriods10     : $("#ForecastingPeriods10").val()
    };

  	//Using $.post instead of $.ajax
    //jQuery also provides a $.post shorthand method as an alternative to $.ajax.

    //The $.ajax code in form.js could be rewritten with $.post:

    //$.post('process.php', function(formData) {
    // place success code here
    //})
    //.fail(function(data) {
    // place error code here
    //});
    //The advantage of $.post is it does not require as much connection configuration to be declared.
	
	$.ajax({
      type: "POST",
      url: "save_macro_statistic_definitionData.php",
      data: formData,
      dataType: "json",
      encode: true,
    })
	
	
	.done(function (data) {
      console.log(data);
  	/**===============================================================================================================================

 	Step 5 — Displaying Server Connection Errors
    If there is an error connecting to the server, there will be no JSON response from the AJAX call. 
    To prevent users from waiting for a response that will never arrive, you can provide an error message for connection failures.
    If the server is broken or down for any reason, a user who attempts to submit a form will get an error message:   
	**/
	


    /**==============================================================================================================================
	//Step 4 — Displaying Form Validation Errors
    //In the PHP script, the code checks to ensure that all the fields are required. 
	//If a field is not present, an error is sent back.
    This code checks to see if the response contains an error for each field. 
	If an error is present, it adds a has-error class and appends the error message.
    Now, revisit your form in a web browser and experiment with submitting data with the form.
    If there are any errors that come back from the server, the form will provide feedback on any required fields:
    **/
	
	if (!data.success) {
    
	}
    
	else {
	  	swal("Good job**!", data.message, "success");
	}

    })
	//=====================================================================================================================================
	.fail(function (data) {
        $("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });  //fail
}        //end function

function segment_codesDefinitionsForm_SaveData()
{
   //Save Log into the local Storage Tracker DashBoard

   
   var today = new Date();
   var date = today.getDate()+'-'+(today.getMonth()+1)+'-'+today.getFullYear();
   var time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();

   
   localStorage.segment_codesDefinitionsTrackerDateSaved = date;
   localStorage.segment_codesDefinitionsTrackerSavedBy = localStorage.username;
   //Saving Row 1
   localStorage.segment_code1           = $("#segment_code1").val();
   localStorage.segment_name1         = $("#Ssegment_name1").val();
   localStorage.segment_desc1           = $("#segment_desc1").val();
   localStorage.segment_macro_statistic1            = $("#S_andPGrade1").val();
   localStorage.segment_macro_statisticDesc1        = $("#S_andPGradeDesc1").val();
   localStorage.segment_nameComment1  = $("#segment_nameComment1").val();
    
   //Saving Row 2
   localStorage.segment_code2           = $("#segment_code2").val();
   localStorage.segment_name2         = $("#Ssegment_name2").val();
   localStorage.segment_desc2           = $("#segment_desc2").val();
   localStorage.segment_macro_statistic2            = $("#S_andPGrade2").val();
   localStorage.segment_macro_statisticDesc2        = $("#S_andPGradeDesc2").val();
   localStorage.segment_nameComment2  = $("#segment_nameComment2").val();

   //Saving Row 3
   localStorage.segment_code3           = $("#segment_code3").val();
   localStorage.segment_name3         = $("#Ssegment_name3").val();
   localStorage.segment_desc3           = $("#segment_desc3").val();
   localStorage.segment_macro_statistic3            = $("#S_andPGrade3").val();
   localStorage.segment_macro_statisticDesc3        = $("#S_andPGradeDesc3").val();
   localStorage.segment_nameComment3  = $("#segment_nameComment3").val();

   //Saving Row 4
   localStorage.segment_code4           = $("#segment_code4").val();
   localStorage.segment_name4         = $("#Ssegment_name4").val();
   localStorage.segment_desc4           = $("#segment_desc4").val();
   localStorage.segment_macro_statistic4            = $("#S_andPGrade4").val();
   localStorage.segment_macro_statisticDesc4        = $("#S_andPGradeDesc4").val();
   localStorage.segment_nameComment4  = $("#segment_nameComment4").val();
   
   //Saving Row 5
   localStorage.segment_code5           = $("#segment_code5").val();
   localStorage.segment_name5         = $("#Ssegment_name5").val();
   localStorage.segment_desc5           = $("#segment_desc5").val();
   localStorage.segment_macro_statistic5            = $("#S_andPGrade5").val();
   localStorage.segment_macro_statisticDesc5        = $("#S_andPGradeDesc5").val();
   localStorage.segment_nameComment5  = $("#segment_nameComment5").val();

   //Saving Row 6
   localStorage.segment_code6           = $("#segment_code6").val();
   localStorage.segment_name6         = $("#Ssegment_name6").val();
   localStorage.segment_desc6           = $("#segment_desc6").val();
   localStorage.segment_macro_statistic6            = $("#S_andPGrade6").val();
   localStorage.segment_macro_statisticDesc6        = $("#S_andPGradeDesc6").val();
   localStorage.segment_nameComment6  = $("#segment_nameComment6").val();
  
  //Saving Row 7
   localStorage.segment_code7           = $("#segment_code7").val();
   localStorage.segment_name7         = $("#Ssegment_name7").val();
   localStorage.segment_desc7           = $("#segment_desc7").val();
   localStorage.segment_macro_statistic7            = $("#S_andPGrade7").val();
   localStorage.segment_macro_statisticDesc7        = $("#S_andPGradeDesc7").val();
   localStorage.segment_nameComment7  = $("#segment_nameComment7").val();
  
  //Saving Row 8
   localStorage.segment_code8           = $("#segment_code8").val();
   localStorage.segment_name8         = $("#Ssegment_name8").val();
   localStorage.segment_desc8           = $("#segment_desc8").val();
   localStorage.segment_macro_statistic8            = $("#S_andPGrade8").val();
   localStorage.segment_macro_statisticDesc8        = $("#S_andPGradeDesc8").val();
   localStorage.segment_nameComment8  = $("#segment_nameComment8").val();
 
   //Saving Row 9
   localStorage.segment_code9           = $("#segment_code9").val();
   localStorage.segment_name9         = $("#Ssegment_name9").val();
   localStorage.segment_desc9           = $("#segment_desc9").val();
   localStorage.segment_macro_statistic9            = $("#S_andPGrade9").val();
   localStorage.segment_macro_statisticDesc9        = $("#S_andPGradeDesc9").val();
   localStorage.segment_nameComment9  = $("#segment_nameComment9").val();
 
   //Saving Row 10
   localStorage.segment_code10           = $("#segment_code10").val();
   localStorage.segment_name10         = $("#Ssegment_name10").val();
   localStorage.segment_desc10           = $("#segment_desc10").val();
   localStorage.segment_macro_statistic10            = $("#S_andPGrade10").val();
   localStorage.segment_macro_statisticDesc10        = $("#S_andPGradeDesc10").val();
   localStorage.segment_nameComment10  = $("#segment_nameComment10").val();

  //Saving Row 11
   localStorage.segment_code11           = $("#segment_code11").val();
   localStorage.segment_name11         = $("#Ssegment_name11").val();
   localStorage.segment_desc11           = $("#segment_desc11").val();
   localStorage.segment_macro_statistic11            = $("#S_andPGrade11").val();
   localStorage.segment_macro_statisticDesc11        = $("#S_andPGradeDesc11").val();
   localStorage.segment_nameComment11  = $("#segment_nameComment11").val();
 
   //Saving Row 12
   localStorage.segment_code12           = $("#segment_code12").val();
   localStorage.segment_name12         = $("#Ssegment_name12").val();
   localStorage.segment_desc12           = $("#segment_desc12").val();
   localStorage.segment_macro_statistic12            = $("#S_andPGrade12").val();
   localStorage.segment_macro_statisticDesc12        = $("#S_andPGradeDesc12").val();
   localStorage.segment_nameComment12  = $("#segment_nameComment12").val();
 
   //Saving Row 13
   localStorage.segment_code13           = $("#segment_code13").val();
   localStorage.segment_name13         = $("#Ssegment_name13").val();
   localStorage.segment_desc13           = $("#segment_desc13").val();
   localStorage.segment_macro_statistic13            = $("#S_andPGrade13").val();
   localStorage.segment_macro_statisticDesc13        = $("#S_andPGradeDesc13").val();
   localStorage.segment_nameComment13  = $("#segment_nameComment13").val();

  //Saving Row 14
   localStorage.segment_code14           = $("#segment_code14").val();
   localStorage.segment_name14         = $("#Ssegment_name14").val();
   localStorage.segment_desc14           = $("#segment_desc14").val();
   localStorage.segment_macro_statistic14            = $("#S_andPGrade14").val();
   localStorage.segment_macro_statisticDesc14        = $("#S_andPGradeDesc14").val();
   localStorage.segment_nameComment14  = $("#segment_nameComment14").val();

  //Saving Row 15
   localStorage.segment_code15           = $("#segment_code15").val();
   localStorage.segment_name15         = $("#Ssegment_name15").val();
   localStorage.segment_desc15           = $("#segment_desc15").val();
   localStorage.segment_macro_statistic15            = $("#S_andPGrade15").val();
   localStorage.segment_macro_statisticDesc15        = $("#S_andPGradeDesc15").val();
   localStorage.segment_nameComment15  = $("#segment_nameComment15").val();


  //Saving Row 16
   localStorage.segment_code16           = $("#segment_code16").val();
   localStorage.segment_name16         = $("#Ssegment_name16").val();
   localStorage.segment_desc16           = $("#segment_desc16").val();
   localStorage.segment_macro_statistic16            = $("#S_andPGrade16").val();
   localStorage.segment_macro_statisticDesc16        = $("#S_andPGradeDesc16").val();
   localStorage.segment_nameComment16  = $("#segment_nameComment16").val();

  //Saving Row 17
   localStorage.segment_code17           = $("#segment_code17").val();
   localStorage.segment_name17         = $("#Ssegment_name17").val();
   localStorage.segment_desc17           = $("#segment_desc17").val();
   localStorage.segment_macro_statistic17            = $("#S_andPGrade17").val();
   localStorage.segment_macro_statisticDesc17        = $("#S_andPGradeDesc17").val();
   localStorage.segment_nameComment17  = $("#segment_nameComment17").val();

  //Saving Row 18
   localStorage.segment_code18           = $("#segment_code18").val();
   localStorage.segment_name18         = $("#Ssegment_name18").val();
   localStorage.segment_desc18           = $("#segment_desc18").val();
   localStorage.segment_macro_statistic18            = $("#S_andPGrade18").val();
   localStorage.segment_macro_statisticDesc18        = $("#S_andPGradeDesc18").val();
   localStorage.segment_nameComment18  = $("#segment_nameComment18").val();

  //Saving Row 19
   localStorage.segment_code19           = $("#segment_code19").val();
   localStorage.segment_name19         = $("#Ssegment_name19").val();
   localStorage.segment_desc19           = $("#segment_desc19").val();
   localStorage.segment_macro_statistic19            = $("#S_andPGrade19").val();
   localStorage.segment_macro_statisticDesc19        = $("#S_andPGradeDesc19").val();
   localStorage.segment_nameComment19  = $("#segment_nameComment19").val();

  //Saving Row 20
   localStorage.segment_code20           = $("#segment_code20").val();
   localStorage.segment_name20         = $("#Ssegment_name20").val();
   localStorage.segment_desc20           = $("#segment_desc20").val();
   localStorage.segment_macro_statistic20            = $("#S_andPGrade20").val();
   localStorage.segment_macro_statisticDesc20        = $("#S_andPGradeDesc20").val();
   localStorage.segment_nameComment20  = $("#segment_nameComment20").val();

   swal("Good job**!", "Internal Grades Definitions temporarilly saved(overwritten) to Local Machine", "success");
    //SAVING TO DATABASE===========================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	//$(".form-group").removeClass("has-error");
    //$(".help-block").remove();
    
	//==============================================================================================

    
	
	var formData = {
	   //Saving Row 1
	   segment_code1           : $("#segment_code1").val(),
	   segment_name1         : $("#segment_name1").val(),
	   segment_desc1           : $("#segment_desc1").val(),
	   segment_macro_statistic1            : $("#segment_macro_statistic1").val(),
	   segment_macro_statisticDesc1        : $("#segment_macro_statisticDesc1").val(),
	   segment_nameComment1  : $("#segment_nameComment1").val(),
		
	   //Saving Row 2
	   segment_code2           : $("#segment_code2").val(),
	   segment_name2         : $("#segment_name2").val(),
	   segment_desc2           : $("#segment_desc2").val(),
	   segment_macro_statistic2            : $("#segment_macro_statistic2").val(),
	   segment_macro_statisticDesc2        : $("#segment_macro_statisticDesc2").val(),
	   segment_nameComment2  : $("#segment_nameComment2").val(),

	   //Saving Row 3
	   segment_code3           : $("#segment_code3").val(),
	   segment_name3         : $("#segment_name3").val(),
	   segment_desc3           : $("#segment_desc3").val(),
	   segment_macro_statistic3            : $("#segment_macro_statistic3").val(),
	   segment_macro_statisticDesc3        : $("#segment_macro_statisticDesc3").val(),
	   segment_nameComment3  : $("#segment_nameComment3").val(),

	   //Saving Row 4
	   segment_code4           : $("#segment_code4").val(),
	   segment_name4         : $("#segment_name4").val(),
	   segment_desc4           : $("#segment_desc4").val(),
	   segment_macro_statistic4            : $("#segment_macro_statistic4").val(),
	   segment_macro_statisticDesc4        : $("#segment_macro_statisticDesc4").val(),
	   segment_nameComment4  : $("#segment_nameComment4").val(),
	   
	   //Saving Row 5
	   segment_code5           : $("#segment_code5").val(),
	   segment_name5         : $("#segment_name5").val(),
	   segment_desc5           : $("#segment_desc5").val(),
	   segment_macro_statistic5            : $("#segment_macro_statistic5").val(),
	   segment_macro_statisticDesc5        : $("#segment_macro_statisticDesc5").val(),
	   segment_nameComment5  : $("#segment_nameComment5").val(),

	   //Saving Row 6
	   segment_code6           : $("#segment_code6").val(),
	   segment_name6         : $("#segment_name6").val(),
	   segment_desc6           : $("#segment_desc6").val(),
	   segment_macro_statistic6            : $("#segment_macro_statistic6").val(),
	   segment_macro_statisticDesc6        : $("#segment_macro_statisticDesc6").val(),
	   segment_nameComment6  : $("#segment_nameComment6").val(),
	  
	  //Saving Row 7
	   segment_code7           : $("#segment_code7").val(),
	   segment_name7         : $("#segment_name7").val(),
	   segment_desc7           : $("#segment_desc7").val(),
	   segment_macro_statistic7            : $("#segment_macro_statistic7").val(),
	   segment_macro_statisticDesc7        : $("#segment_macro_statisticDesc7").val(),
	   segment_nameComment7  : $("#segment_nameComment7").val(),
	  
	  //Saving Row 8
	   segment_code8           : $("#segment_code8").val(),
	   segment_name8         : $("#segment_name8").val(),
	   segment_desc8           : $("#segment_desc8").val(),
	   segment_macro_statistic8            : $("#segment_macro_statistic8").val(),
	   segment_macro_statisticDesc8        : $("#segment_macro_statisticDesc8").val(),
	   segment_nameComment8  : $("#segment_nameComment8").val(),
	 
	   //Saving Row 9
	   segment_code9           : $("#segment_code9").val(),
	   segment_name9         : $("#segment_name9").val(),
	   segment_desc9           : $("#segment_desc9").val(),
	   segment_macro_statistic9            : $("#segment_macro_statistic9").val(),
	   segment_macro_statisticDesc9        : $("#segment_macro_statisticDesc9").val(),
	   segment_nameComment9  : $("#segment_nameComment9").val(),
	 
	   //Saving Row 10
	   segment_code10           : $("#segment_code10").val(),
	   segment_name10         : $("#segment_name10").val(),
	   segment_desc10           : $("#segment_desc10").val(),
	   segment_macro_statistic10            : $("#segment_macro_statistic10").val(),
	   segment_macro_statisticDesc10        : $("#segment_macro_statisticDesc10").val(),
	   segment_nameComment10  : $("#segment_nameComment10").val(),

	  //Saving Row 11
	   segment_code11           : $("#segment_code11").val(),
	   segment_name11         : $("#segment_name11").val(),
	   segment_desc11           : $("#segment_desc11").val(),
	   segment_macro_statistic11            : $("#segment_macro_statistic11").val(),
	   segment_macro_statisticDesc11        : $("#segment_macro_statisticDesc11").val(),
	   segment_nameComment11  : $("#segment_nameComment11").val(),
	 
	   //Saving Row 12
	   segment_code12           : $("#segment_code12").val(),
	   segment_name12         : $("#segment_name12").val(),
	   segment_desc12           : $("#segment_desc12").val(),
	   segment_macro_statistic12            : $("#segment_macro_statistic12").val(),
	   segment_macro_statisticDesc12        : $("#segment_macro_statisticDesc12").val(),
	   segment_nameComment12  : $("#segment_nameComment12").val(),
	 
	   //Saving Row 13
	   segment_code13           : $("#segment_code13").val(),
	   segment_name13         : $("#segment_name13").val(),
	   segment_desc13           : $("#segment_desc13").val(),
	   segment_macro_statistic13            : $("#segment_macro_statistic13").val(),
	   segment_macro_statisticDesc13        : $("#segment_macro_statisticDesc13").val(),
	   segment_nameComment13  : $("#segment_nameComment13").val(),

	  //Saving Row 14
	   segment_code14           : $("#segment_code14").val(),
	   segment_name14         : $("#segment_name14").val(),
	   segment_desc14           : $("#segment_desc14").val(),
	   segment_macro_statistic14            : $("#segment_macro_statistic14").val(),
	   segment_macro_statisticDesc14        : $("#segment_macro_statisticDesc14").val(),
	   segment_nameComment14  : $("#segment_nameComment14").val(),

	  //Saving Row 15
	   segment_code15           : $("#segment_code15").val(),
	   segment_name15         : $("#segment_name15").val(),
	   segment_desc15           : $("#segment_desc15").val(),
	   segment_macro_statistic15            : $("#segment_macro_statistic15").val(),
	   segment_macro_statisticDesc15        : $("#segment_macro_statisticDesc15").val(),
	   segment_nameComment15  : $("#segment_nameComment15").val(),


	  //Saving Row 16
	   segment_code16           : $("#segment_code16").val(),
	   segment_name16         : $("#segment_name16").val(),
	   segment_desc16           : $("#segment_desc16").val(),
	   segment_macro_statistic16            : $("#segment_macro_statistic16").val(),
	   segment_macro_statisticDesc16        : $("#segment_macro_statisticDesc16").val(),
	   segment_nameComment16  : $("#segment_nameComment16").val(),

	  //Saving Row 17
	   segment_code17           : $("#segment_code17").val(),
	   segment_name17         : $("#segment_name17").val(),
	   segment_desc17           : $("#segment_desc17").val(),
	   segment_macro_statistic17            : $("#segment_macro_statistic17").val(),
	   segment_macro_statisticDesc17        : $("#segment_macro_statisticDesc17").val(),
	   segment_nameComment17  : $("#segment_nameComment17").val(),

	  //Saving Row 18
	   segment_code18           : $("#segment_code18").val(),
	   segment_name18         : $("#segment_name18").val(),
	   segment_desc18           : $("#segment_desc18").val(),
	   segment_macro_statistic18            : $("#segment_macro_statistic18").val(),
	   segment_macro_statisticDesc18        : $("#segment_macro_statisticDesc18").val(),
	   segment_nameComment18  : $("#segment_nameComment18").val(),

	  //Saving Row 19
	   segment_code19           : $("#segment_code19").val(),
	   segment_name19         : $("#segment_name19").val(),
	   segment_desc19           : $("#segment_desc19").val(),
	   segment_macro_statistic19            : $("#segment_macro_statistic19").val(),
	   segment_macro_statisticDesc19        : $("#segment_macro_statisticDesc19").val(),
	   segment_nameComment19  : $("#segment_nameComment19").val(),

	  //Saving Row 20
	   segment_code20           : $("#segment_code20").val(),
	   segment_name20         : $("#segment_name20").val(),
	   segment_desc20           : $("#segment_desc20").val(),
	   segment_macro_statistic20            : $("#segment_macro_statistic20").val(),
	   segment_macro_statisticDesc20        : $("#segment_macro_statisticDesc20").val(),
	   segment_nameComment20  : $("#segment_nameComment20").val()    
	   
	   };

  	//Using $.post instead of $.ajax
    //jQuery also provides a $.post shorthand method as an alternative to $.ajax.

    //The $.ajax code in form.js could be rewritten with $.post:

    //$.post('process.php', function(formData) {
    // place success code here
    //})
    //.fail(function(data) {
    // place error code here
    //});
    //The advantage of $.post is it does not require as much connection configuration to be declared.
	
	$.ajax({
      type: "POST",
      url: "save_internal_grading_definitionData.php",
      data: formData,
      dataType: "json",
      encode: true,
    })
	
	
	.done(function (data) {
      console.log(data);
  	/**===============================================================================================================================

 	Step 5 — Displaying Server Connection Errors
    If there is an error connecting to the server, there will be no JSON response from the AJAX call. 
    To prevent users from waiting for a response that will never arrive, you can provide an error message for connection failures.
    If the server is broken or down for any reason, a user who attempts to submit a form will get an error message:   
	**/
	


    /**==============================================================================================================================
	//Step 4 — Displaying Form Validation Errors
    //In the PHP script, the code checks to ensure that all the fields are required. 
	//If a field is not present, an error is sent back.
    This code checks to see if the response contains an error for each field. 
	If an error is present, it adds a has-error class and appends the error message.
    Now, revisit your form in a web browser and experiment with submitting data with the form.
    If there are any errors that come back from the server, the form will provide feedback on any required fields:
    **/
	
	if (!data.success) {
    
	}
    
	else {
	  	swal("Good job**!", data.message, "success");
	}

    })
	//=====================================================================================================================================
	.fail(function (data) {
           console.log(data);
		
		$("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });  //fail
}        //end function

function ECLVariablesDefinitionsForm_SaveData()
{
   //Save Log into the local Storage Tracker DashBoard

   
   var today = new Date();																																																															
   var date = today.getDate()+'-'+(today.getMonth()+1)+'-'+today.getFullYear();
   var time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();

   
   localStorage.ecl_variables_definitionsz_trackerDate_saved = date;
   localStorage.ecl_variables_definitions_trackerSavedBy = localStorage.username;
  
   //Saving to local storage
   localStorage.pd_segment_code                                     = $("#pd_segment_code").val();
   localStorage.pd_segment_desc                                      = $("#pd_segment_desc").val();
   localStorage.pd_comment                                           = $("#pd_comment").val();
   localStorage.pd_segment_code                                      = $("#pd_segment_code").val();
   localStorage.pd_segment_desc                                      = $("#pd_segment_desc").val();
   localStorage.pd_comment                                           = $("#pd_comment").val();
   localStorage.ecl_variable_pd_average_period_years                 = $("#ecl_variable_pd_average_period_years").val();
   localStorage.ecl_variable_lgd_cure_rate_average_period_years      = $("#ecl_variable_lgd_cure_rate_average_period_years").val();
   localStorage.ecl_variable_lgd_recovery_rate_average_period_years  = $("#ecl_variable_lgd_recovery_rate_average_period_years").val();	
	
 
   swal("Good job**!", "ECL Variables Definitions temporarilly saved(overwritten) to Local Machine", "success");
    //SAVING TO DATABASE===========================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	//$(".form-group").removeClass("has-error");
    //$(".help-block").remove();
    
	//==============================================================================================

    
	
	var formData = {
	   //
	   pd_segment_code                                      : $("#pd_segment_code").val(),
	   pd_segment_desc                                      : $("#pd_segment_desc").val(),
	   pd_comment                                           : $("#pd_comment").val(),
	   pd_segment_code                                      : $("#pd_segment_code").val(),
	   pd_segment_desc                                      : $("#pd_segment_desc").val(),
	   pd_comment                                           : $("#pd_comment").val(),
	   ecl_variable_pd_average_period_years                 : $("#ecl_variable_pd_average_period_years").val(),
	   ecl_variable_lgd_cure_rate_average_period_years      : $("#ecl_variable_lgd_cure_rate_average_period_years").val(),
	   ecl_variable_lgd_recovery_rate_average_period_years  : $("#ecl_variable_lgd_recovery_rate_average_period_years").val(),	
	   lgd_cure_rate_comment                                : $("#lgd_cure_rate_comment").val(),
	   lgd_recovery_rate_comment	                        : $("#lgd_recovery_rate_comment").val()
	   };

  	//Using $.post instead of $.ajax
    //jQuery also provides a $.post shorthand method as an alternative to $.ajax.

    //The $.ajax code in form.js could be rewritten with $.post:

    //$.post('process.php', function(formData) {
    // place success code here
    //})
    //.fail(function(data) {
    // place error code here
    //});
    //The advantage of $.post is it does not require as much connection configuration to be declared.
	
	$.ajax({
      type: "POST",
      url: "save_ecl_variables_definitionData.php",
      data: formData,
      dataType: "json",
      encode: true,
    })
	
	
	.done(function (data) {
      console.log(data);
  	/**===============================================================================================================================

 	Step 5 — Displaying Server Connection Errors
    If there is an error connecting to the server, there will be no JSON response from the AJAX call. 
    To prevent users from waiting for a response that will never arrive, you can provide an error message for connection failures.
    If the server is broken or down for any reason, a user who attempts to submit a form will get an error message:   
	**/
	


    /**==============================================================================================================================
	//Step 4 — Displaying Form Validation Errors
    //In the PHP script, the code checks to ensure that all the fields are required. 
	//If a field is not present, an error is sent back.
    This code checks to see if the response contains an error for each field. 
	If an error is present, it adds a has-error class and appends the error message.
    Now, revisit your form in a web browser and experiment with submitting data with the form.
    If there are any errors that come back from the server, the form will provide feedback on any required fields:
    **/
	
	if (!data.success) {
    
	}
    
	else {
	  	swal("Good job**!", data.message, "success");
	}

    })
	//=====================================================================================================================================
	.fail(function (data) {
           console.log(data);
		
		$("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });  //fail
}        //end function


function SegmentationCriteriaDefinitionsForm1_SaveData()
{
   //Save Log into the local Storage Tracker DashBoard
   
   var today = new Date();
   var date = today.getDate()+'-'+(today.getMonth()+1)+'-'+today.getFullYear();
   var time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();

   localStorage.SegmentationCriteriaDefinitionsForm1TrackerDateSaved = date;
   localStorage.SegmentationCriteriaDefinitionsForm1TrackerSavedBy = localStorage.username;
   
   //Saving Row 1
   localStorage.segment_code1            = $("#segment_code1").val();
   localStorage.segment_name1            = $("#Segment_name1").val();
   localStorage.segment_desc1            = $("#segment_desc1").val();
   localStorage.segment_macro_statistic1 = $("#segment_macro_statistic1").val();
    
   //Saving Row 2
   localStorage.segment_code2            = $("#segment_code2").val();
   localStorage.segment_name2            = $("#segment_name2").val();
   localStorage.segment_desc2            = $("#segment_desc2").val();
   localStorage.segment_macro_statistic2 = $("#segment_macro_statistic2").val();
 
   //Saving Row 3
   localStorage.segment_code3            = $("#segment_code3").val();
   localStorage.segment_name3            = $("#segment_name3").val();
   localStorage.segment_desc3            = $("#segment_desc3").val();
   localStorage.segment_macro_statistic3 = $("#segment_macro_statistic3").val();
 
   //Saving Row 4
   localStorage.segment_code4            = $("#segment_code4").val();
   localStorage.segment_name4            = $("#segment_name4").val();
   localStorage.segment_desc4            = $("#segment_desc4").val();
   localStorage.segment_macro_statistic4 = $("#segment_macro_statistic4").val();
    
   //Saving Row 5
   localStorage.segment_code5            = $("#segment_code5").val();
   localStorage.segment_name5            = $("#segment_name5").val();
   localStorage.segment_desc5            = $("#segment_desc5").val();
   localStorage.segment_macro_statistic5 = $("#segment_macro_statistic5").val();
 
   //Saving Row 6
    localStorage.segment_code6            = $("#segment_code6").val();
   localStorage.segment_name6            = $("#segment_name6").val();
   localStorage.segment_desc6            = $("#segment_desc6").val();
   localStorage.segment_macro_statistic6 = $("#segment_macro_statistic6").val();
  
  //Saving Row 7
   localStorage.segment_code7            = $("#segment_code7").val();
   localStorage.segment_name7            = $("#segment_name7").val();
   localStorage.segment_desc7            = $("#segment_desc7").val();
   localStorage.segment_macro_statistic7 = $("#segment_macro_statistic7").val();
   
  //Saving Row 8
   localStorage.segment_code8            = $("#segment_code8").val();
   localStorage.segment_name8            = $("#segment_name8").val();
   localStorage.segment_desc8            = $("#segment_desc8").val();
   localStorage.segment_macro_statistic8 = $("#segment_macro_statistic8").val();
 
   //Saving Row 9
   localStorage.segment_code9            = $("#segment_code9").val();
   localStorage.segment_name9            = $("#segment_name9").val();
   localStorage.segment_desc9            = $("#segment_desc9").val();
   localStorage.segment_macro_statistic9 = $("#segment_macro_statistic9").val();
  
   //Saving Row 10
   localStorage.segment_code10            = $("#segment_code10").val();
   localStorage.segment_name10            = $("#segment_name10").val();
   localStorage.segment_desc10            = $("#segment_desc10").val();
   localStorage.segment_macro_statistic10 = $("#segment_macro_statistic10").val();
 
  //Saving Row 11
   localStorage.segment_code11            = $("#segment_code11").val();
   localStorage.segment_name11            = $("#segment_name11").val();
   localStorage.segment_desc11            = $("#segment_desc11").val();
   localStorage.segment_macro_statistic11 = $("#segment_macro_statistic11").val();
  
   //Saving Row 12
   localStorage.segment_code12            = $("#segment_code12").val();
   localStorage.segment_name12            = $("#segment_name12").val();
   localStorage.segment_desc12            = $("#segment_desc12").val();
   localStorage.segment_macro_statistic12 = $("#segment_macro_statistic12").val();
  
   //Saving Row 13
   localStorage.segment_code13            = $("#segment_code13").val();
   localStorage.segment_name13            = $("#segment_name13").val();
   localStorage.segment_desc13            = $("#segment_desc13").val();
   localStorage.segment_macro_statistic13 = $("#segment_macro_statistic13").val();
 
  //Saving Row 14
   localStorage.segment_code14            = $("#segment_code14").val();
   localStorage.segment_name14            = $("#segment_name14").val();
   localStorage.segment_desc14            = $("#segment_desc14").val();
   localStorage.segment_macro_statistic14 = $("#segment_macro_statistic14").val();
 
  //Saving Row 15
   localStorage.segment_code15            = $("#segment_code15").val();
   localStorage.segment_name15            = $("#segment_name15").val();
   localStorage.segment_desc15            = $("#segment_desc15").val();
   localStorage.segment_macro_statistic15 = $("#segment_macro_statistic15").val();
 
 
   swal("Good job**!", "Internal Grades Definitions temporarilly saved(overwritten) to Local Machine", "success");
    //SAVING TO DATABASE===========================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	//$(".form-group").removeClass("has-error");
    //$(".help-block").remove();
    
	//==============================================================================================

    
	
	var formData = {
	  
	  //Header information
	   segment_criteria_formNo        : 1,       
	   segment_criteria_name          : $("#segment_criteria_name1").val(),
	   segment_criteria_desc          : $("#segment_criteria_desc1").val(),
	  
	  //Saving Row 1
	   
	   segment_code1                  : $("#segment_code1").val(),
	   segment_name1                  : $("#segment_name1").val(),
	   segment_desc1                  : $("#segment_desc1").val(),
	   segment_macro_statistic1       : $("#segment_macro_statistic1").val(),
		
	   //Saving Row 2
	   segment_code2           		  : $("#segment_code2").val(),
	   segment_name2                  : $("#segment_name2").val(),
	   segment_desc2                  : $("#segment_desc2").val(),
	   segment_macro_statistic2       : $("#segment_macro_statistic2").val(),

	   //Saving Row 3
	   segment_code3                  : $("#segment_code3").val(),
	   segment_name3                  : $("#segment_name3").val(),
	   segment_desc3                  : $("#segment_desc3").val(),
	   segment_macro_statistic3       : $("#segment_macro_statistic3").val(),

	   //Saving Row 4
	   segment_code4                  : $("#segment_code4").val(),
	   segment_name4                  : $("#segment_name4").val(),
	   segment_desc4                  : $("#segment_desc4").val(),
	   segment_macro_statistic4       : $("#segment_macro_statistic4").val(),
	   
	   //Saving Row 5
	   segment_code5                  : $("#segment_code5").val(),
	   segment_name5                  : $("#segment_name5").val(),
	   segment_desc5                  : $("#segment_desc5").val(),
	   segment_macro_statistic5       : $("#segment_macro_statistic5").val(),

	   //Saving Row 6
	   segment_code6                  : $("#segment_code6").val(),
	   segment_name6                  : $("#segment_name6").val(),
	   segment_desc6                  : $("#segment_desc6").val(),
	   segment_macro_statistic6       : $("#segment_macro_statistic6").val(),
	  
	  //Saving Row 7
	   segment_code7           		  : $("#segment_code7").val(),
	   segment_name7                  : $("#segment_name7").val(),
	   segment_desc7                  : $("#segment_desc7").val(),
	   segment_macro_statistic7       : $("#segment_macro_statistic7").val(),
	  
	  //Saving Row 8
	   segment_code8           		  : $("#segment_code8").val(),
	   segment_name8                  : $("#segment_name8").val(),
	   segment_desc8                  : $("#segment_desc8").val(),
	   segment_macro_statistic8       : $("#segment_macro_statistic8").val(),
	 
	   //Saving Row 9
	   segment_code9                  : $("#segment_code9").val(),
	   segment_name9                  : $("#segment_name9").val(),
	   segment_desc9                  : $("#segment_desc9").val(),
	   segment_macro_statistic9       : $("#segment_macro_statistic9").val(),
	 
	   //Saving Row 10
	   segment_code10                 : $("#segment_code10").val(),
	   segment_name10                 : $("#segment_name10").val(),
	   segment_desc10                 : $("#segment_desc10").val(),
	   segment_macro_statistic10      : $("#segment_macro_statistic10").val(),

	  //Saving Row 11
	   segment_code11                 : $("#segment_code11").val(),
	   segment_name11                 : $("#segment_name11").val(),
	   segment_desc11                 : $("#segment_desc11").val(),
	   segment_macro_statistic11      : $("#segment_macro_statistic11").val(),
	 
	   //Saving Row 12
	   segment_code12                 : $("#segment_code12").val(),
	   segment_name12                 : $("#segment_name12").val(),
	   segment_desc12                 : $("#segment_desc12").val(),
	   segment_macro_statistic12      : $("#segment_macro_statistic12").val(),
	 
	   //Saving Row 13
	   segment_code13                 : $("#segment_code13").val(),
	   segment_name13                 : $("#segment_name13").val(),
	   segment_desc13                 : $("#segment_desc13").val(),
	   segment_macro_statistic13      : $("#segment_macro_statistic13").val(),

	  //Saving Row 14
	   segment_code14                 : $("#segment_code14").val(),
	   segment_name14                 : $("#segment_name14").val(),
	   segment_desc14                 : $("#segment_desc14").val(),
	   segment_macro_statistic14      : $("#segment_macro_statistic14").val(),

	  //Saving Row 15
	   segment_code15                 : $("#segment_code15").val(),
	   segment_name15                 : $("#segment_name15").val(),
	   segment_desc15                 : $("#segment_desc15").val(),
	   segment_macro_statistic15      : $("#segment_macro_statistic15").val()
   
	   };

  	//Using $.post instead of $.ajax
    //jQuery also provides a $.post shorthand method as an alternative to $.ajax.

    //The $.ajax code in form.js could be rewritten with $.post:

    //$.post('process.php', function(formData) {
    // place success code here
    //})
    //.fail(function(data) {
    // place error code here
    //});
    //The advantage of $.post is it does not require as much connection configuration to be declared.
	
	$.ajax({
      type: "POST",
      url: "save_segmentation_criteria_definitionData.php",
      data: formData,
      dataType: "json",
      encode: true,
    })
	
	
	.done(function (data) {
      console.log(data);
  	/**===============================================================================================================================

 	Step 5 — Displaying Server Connection Errors
    If there is an error connecting to the server, there will be no JSON response from the AJAX call. 
    To prevent users from waiting for a response that will never arrive, you can provide an error message for connection failures.
    If the server is broken or down for any reason, a user who attempts to submit a form will get an error message:   
	**/
	


    /**==============================================================================================================================
	//Step 4 — Displaying Form Validation Errors
    //In the PHP script, the code checks to ensure that all the fields are required. 
	//If a field is not present, an error is sent back.
    This code checks to see if the response contains an error for each field. 
	If an error is present, it adds a has-error class and appends the error message.
    Now, revisit your form in a web browser and experiment with submitting data with the form.
    If there are any errors that come back from the server, the form will provide feedback on any required fields:
    **/
	
	if (!data.success) {
    
	}
    
	else {
	  	swal("Good job**!", data.message, "success");
	}

    })
	//=====================================================================================================================================
	.fail(function (data) {
           console.log(data);
		
		$("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });  //fail
}        //end function

//===========================================================================================================================
function ValidateShareholderForm()
/** Purpose of Function : return boolean value to allow or bar form submission depending on input elements validation

**/
  
{
	
  return true;

	
}
function ProgressTrackerForm_LoadData()
{
   document.ProgressTrackerForm.CompanyDataTrackerDateSaved.value =  localStorage.CompanyDataTrackerDateSaved;
   document.ProgressTrackerForm.CompanyDataTrackerSavedBy.value =  localStorage.CompanyDataTrackerSavedBy;
   document.ProgressTrackerForm.CompanyDataTracker1stReviewDate.value =  localStorage.CompanyDataTracker1stReviewDate;
   document.ProgressTrackerForm.CompanyDataTracker1stReviewer.value =  localStorage.CompanyDataTracker1stReviewer;
   document.ProgressTrackerForm.CompanyDataTracker2ndReviewDate.value =  localStorage.CompanyDataTracker2ndReviewDate;
   document.ProgressTrackerForm.CompanyDataTracker2ndReviewer.value =  localStorage.CompanyDataTracker2ndReviewer;
   document.ProgressTrackerForm.CompanyDataTracker1stReviewComment.value =  localStorage.CompanyDataTracker1stReviewComment;
   document.ProgressTrackerForm.CompanyDataTracker2ndReviewComment.value =  localStorage.CompanyDataTracker2ndReviewComment;
   document.ProgressTrackerForm.LoanDataTrackerDateSaved.value =  localStorage.LoanDataTrackerDateSaved;
   document.ProgressTrackerForm.LoanDataTrackerSavedBy.value =  localStorage.LoanDataTrackerSavedBy;
   document.ProgressTrackerForm.LoanDataTracker1stReviewDate.value =  localStorage.LoanDataTracker1stReviewDate;
   document.ProgressTrackerForm.LoanDataTracker1stReviewer.value =  localStorage.LoanDataTracker1stReviewer;
   document.ProgressTrackerForm.LoanDataTracker2ndReviewDate.value =  localStorage.LoanDataTracker2ndReviewDate;
   document.ProgressTrackerForm.LoanDataTracker2ndReviewer.value =  localStorage.LoanDataTracker2ndReviewer;
   document.ProgressTrackerForm.LoanDataTracker1stReviewComment.value =  localStorage.LoanDataTracker1stReviewComment;
   document.ProgressTrackerForm.LoanDataTracker2ndReviewComment.value =  localStorage.LoanDataTracker2ndReviewComment;
   document.ProgressTrackerForm.IncomeStatementTrackerDateSaved.value =  localStorage.IncomeStatementTrackerDateSaved;
   document.ProgressTrackerForm.IncomeStatementTrackerSavedBy.value =  localStorage.IncomeStatementTrackerSavedBy;
   document.ProgressTrackerForm.IncomeStatementTracker1stReviewDate.value =  localStorage.IncomeStatementTracker1stReviewDate;
   document.ProgressTrackerForm.IncomeStatementTracker1stReviewer.value =  localStorage.IncomeStatementTracker1stReviewer;
   document.ProgressTrackerForm.IncomeStatementTracker2ndReviewDate.value =  localStorage.IncomeStatementTracker2ndReviewDate;
   document.ProgressTrackerForm.IncomeStatementTracker2ndReviewer.value =  localStorage.IncomeStatementTracker2ndReviewer;
   document.ProgressTrackerForm.IncomeStatementTracker1stReviewComment.value =  localStorage.IncomeStatementTracker1stReviewComment;
   document.ProgressTrackerForm.IncomeStatementTracker2ndReviewComment.value =  localStorage.IncomeStatementTracker2ndReviewComment;
   document.ProgressTrackerForm.CurrentAssetsTrackerDateSaved.value =  localStorage.CurrentAssetsTrackerDateSaved;
   document.ProgressTrackerForm.CurrentAssetsTrackerSavedBy.value =  localStorage.CurrentAssetsTrackerSavedBy;
   document.ProgressTrackerForm.CurrentAssetsTracker1stReviewDate.value =  localStorage.CurrentAssetsTracker1stReviewDate;
   document.ProgressTrackerForm.CurrentAssetsTracker1stReviewer.value =  localStorage.CurrentAssetsTracker1stReviewer;
   document.ProgressTrackerForm.CurrentAssetsTracker2ndReviewDate.value =  localStorage.CurrentAssetsTracker2ndReviewDate;
   document.ProgressTrackerForm.CurrentAssetsTracker2ndReviewer.value =  localStorage.CurrentAssetsTracker2ndReviewer;
   document.ProgressTrackerForm.CurrentAssetsTracker1stReviewComment.value =  localStorage.CurrentAssetsTracker1stReviewComment;
   document.ProgressTrackerForm.CurrentAssetsTracker2ndReviewComment.value =  localStorage.CurrentAssetsTracker2ndReviewComment;
   document.ProgressTrackerForm.NonCurrentAssetsTrackerDateSaved.value =  localStorage.NonCurrentAssetsTrackerDateSaved;
   document.ProgressTrackerForm.NonCurrentAssetsTrackerSavedBy.value =  localStorage.NonCurrentAssetsTrackerSavedBy;
   document.ProgressTrackerForm.NonCurrentAssetsTracker1stReviewDate.value =  localStorage.NonCurrentAssetsTracker1stReviewDate;
   document.ProgressTrackerForm.NonCurrentAssetsTracker1stReviewer.value =  localStorage.NonCurrentAssetsTracker1stReviewer;
   document.ProgressTrackerForm.NonCurrentAssetsTracker2ndReviewDate.value =  localStorage.NonCurrentAssetsTracker2ndReviewDate;
   document.ProgressTrackerForm.NonCurrentAssetsTracker2ndReviewer.value =  localStorage.NonCurrentAssetsTracker2ndReviewer;
   document.ProgressTrackerForm.NonCurrentAssetsTracker1stReviewComment.value =  localStorage.NonCurrentAssetsTracker1stReviewComment;
   document.ProgressTrackerForm.NonCurrentAssetsTracker2ndReviewComment.value =  localStorage.NonCurrentAssetsTracker2ndReviewComment;
   document.ProgressTrackerForm.CurrentLiabilitiesTrackerDateSaved.value =  localStorage.CurrentLiabilitiesTrackerDateSaved;
   document.ProgressTrackerForm.CurrentLiabilitiesTrackerSavedBy.value =  localStorage.CurrentLiabilitiesTrackerSavedBy;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker1stReviewDate.value =  localStorage.CurrentLiabilitiesTracker1stReviewDate;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker1stReviewer.value =  localStorage.CurrentLiabilitiesTracker1stReviewer;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker2ndReviewDate.value =  localStorage.CurrentLiabilitiesTracker2ndReviewDate;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker2ndReviewer.value =  localStorage.CurrentLiabilitiesTracker2ndReviewer;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker1stReviewComment.value =  localStorage.CurrentLiabilitiesTracker1stReviewComment;
   document.ProgressTrackerForm.CurrentLiabilitiesTracker2ndReviewComment.value =  localStorage.CurrentLiabilitiesTracker2ndReviewComment;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTrackerDateSaved.value =  localStorage.NonCurrentLiabilitiesTrackerDateSaved;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTrackerSavedBy.value =  localStorage.NonCurrentLiabilitiesTrackerSavedBy;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker1stReviewDate.value =  localStorage.NonCurrentLiabilitiesTracker1stReviewDate;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker1stReviewer.value =  localStorage.NonCurrentLiabilitiesTracker1stReviewer;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker2ndReviewDate.value =  localStorage.NonCurrentLiabilitiesTracker2ndReviewDate;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker2ndReviewer.value =  localStorage.NonCurrentLiabilitiesTracker2ndReviewer;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker1stReviewComment.value =  localStorage.NonCurrentLiabilitiesTracker1stReviewComment;
   document.ProgressTrackerForm.NonCurrentLiabilitiesTracker2ndReviewComment.value =  localStorage.NonCurrentLiabilitiesTracker2ndReviewComment;
   document.ProgressTrackerForm.EquityTrackerDateSaved.value =  localStorage.EquityTrackerDateSaved;
   document.ProgressTrackerForm.EquityTrackerSavedBy.value =  localStorage.EquityTrackerSavedBy;
   document.ProgressTrackerForm.EquityTracker1stReviewDate.value =  localStorage.EquityTracker1stReviewDate;
   document.ProgressTrackerForm.EquityTracker1stReviewer.value =  localStorage.EquityTracker1stReviewer;
   document.ProgressTrackerForm.EquityTracker2ndReviewDate.value =  localStorage.EquityTracker2ndReviewDate;
   document.ProgressTrackerForm.EquityTracker2ndReviewer.value =  localStorage.EquityTracker2ndReviewer;
   document.ProgressTrackerForm.EquityTracker1stReviewComment.value =  localStorage.EquityTracker1stReviewComment;
   document.ProgressTrackerForm.EquityTracker2ndReviewComment.value =  localStorage.EquityTracker2ndReviewComment;
   document.ProgressTrackerForm.ManagementAnalysisTrackerDateSaved.value =  localStorage.ManagementAnalysisTrackerDateSaved;
   document.ProgressTrackerForm.ManagementAnalysisTrackerSavedBy.value =  localStorage.ManagementAnalysisTrackerSavedBy;
   document.ProgressTrackerForm.ManagementAnalysisTracker1stReviewDate.value =  localStorage.ManagementAnalysisTracker1stReviewDate;
   document.ProgressTrackerForm.ManagementAnalysisTracker1stReviewer.value =  localStorage.ManagementAnalysisTracker1stReviewer;
   document.ProgressTrackerForm.ManagementAnalysisTracker2ndReviewDate.value =  localStorage.ManagementAnalysisTracker2ndReviewDate;
   document.ProgressTrackerForm.ManagementAnalysisTracker2ndReviewer.value =  localStorage.ManagementAnalysisTracker2ndReviewer;
   document.ProgressTrackerForm.ManagementAnalysisTracker1stReviewComment.value =  localStorage.ManagementAnalysisTracker1stReviewComment;
   document.ProgressTrackerForm.ManagementAnalysisTracker2ndReviewComment.value =  localStorage.ManagementAnalysisTracker2ndReviewComment;
   document.ProgressTrackerForm.IndustryAnalysisTrackerDateSaved.value =  localStorage.IndustryAnalysisTrackerDateSaved;
   document.ProgressTrackerForm.IndustryAnalysisTrackerSavedBy.value =  localStorage.IndustryAnalysisTrackerSavedBy;
   document.ProgressTrackerForm.IndustryAnalysisTracker1stReviewDate.value =  localStorage.IndustryAnalysisTracker1stReviewDate;
   document.ProgressTrackerForm.IndustryAnalysisTracker1stReviewer.value =  localStorage.IndustryAnalysisTracker1stReviewer;
   document.ProgressTrackerForm.IndustryAnalysisTracker2ndReviewDate.value =  localStorage.IndustryAnalysisTracker2ndReviewDate;
   document.ProgressTrackerForm.IndustryAnalysisTracker2ndReviewer.value =  localStorage.IndustryAnalysisTracker2ndReviewer;
   document.ProgressTrackerForm.IndustryAnalysisTracker1stReviewComment.value =  localStorage.IndustryAnalysisTracker1stReviewComment;
   document.ProgressTrackerForm.IndustryAnalysisTracker2ndReviewComment.value =  localStorage.IndustryAnalysisTracker2ndReviewComment;
   document.ProgressTrackerForm.ShareholderAnalysisTrackerDateSaved.value =  localStorage.ShareholderAnalysisTrackerDateSaved;
   document.ProgressTrackerForm.ShareholderAnalysisTrackerSavedBy.value =  localStorage.ShareholderAnalysisTrackerSavedBy;
   document.ProgressTrackerForm.ShareholderAnalysisTracker1stReviewDate.value =  localStorage.ShareholderAnalysisTracker1stReviewDate;
   document.ProgressTrackerForm.ShareholderAnalysisTracker1stReviewer.value =  localStorage.ShareholderAnalysisTracker1stReviewer;
   document.ProgressTrackerForm.ShareholderAnalysisTracker2ndReviewDate.value =  localStorage.ShareholderAnalysisTracker2ndReviewDate;
   document.ProgressTrackerForm.ShareholderAnalysisTracker2ndReviewer.value =  localStorage.ShareholderAnalysisTracker2ndReviewer;
   document.ProgressTrackerForm.ShareholderAnalysisTracker1stReviewComment.value =  localStorage.ShareholderAnalysisTracker1stReviewComment;
   document.ProgressTrackerForm.ShareholderAnalysisTracker2ndReviewComment.value =  localStorage.ShareholderAnalysisTracker2ndReviewComment;
   document.ProgressTrackerForm.BehavioralAnalysisTrackerDateSaved.value =  localStorage.BehavioralAnalysisTrackerDateSaved;
   document.ProgressTrackerForm.BehavioralAnalysisTrackerSavedBy.value =  localStorage.BehavioralAnalysisTrackerSavedBy;
   document.ProgressTrackerForm.BehavioralAnalysisTracker1stReviewDate.value =  localStorage.BehavioralAnalysisTracker1stReviewDate;
   document.ProgressTrackerForm.BehavioralAnalysisTracker1stReviewer.value =  localStorage.BehavioralAnalysisTracker1stReviewer;
   document.ProgressTrackerForm.BehavioralAnalysisTracker2ndReviewDate.value =  localStorage.BehavioralAnalysisTracker2ndReviewDate;
   document.ProgressTrackerForm.BehavioralAnalysisTracker2ndReviewer.value =  localStorage.BehavioralAnalysisTracker2ndReviewer;
   document.ProgressTrackerForm.BehavioralAnalysisTracker1stReviewComment.value =  localStorage.BehavioralAnalysisTracker1stReviewComment;
   document.ProgressTrackerForm.BehavioralAnalysisTracker2ndReviewComment.value =  localStorage.BehavioralAnalysisTracker2ndReviewComment;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTrackerDateSaved.value =  localStorage.IndustryBenchmarksOverridesTrackerDateSaved;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTrackerSavedBy.value =  localStorage.IndustryBenchmarksOverridesTrackerSavedBy;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker1stReviewDate.value =  localStorage.IndustryBenchmarksOverridesTracker1stReviewDate;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker1stReviewer.value =  localStorage.IndustryBenchmarksOverridesTracker1stReviewer;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker2ndReviewDate.value =  localStorage.IndustryBenchmarksOverridesTracker2ndReviewDate;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker2ndReviewer.value =  localStorage.IndustryBenchmarksOverridesTracker2ndReviewer;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker1stReviewComment.value =  localStorage.IndustryBenchmarksOverridesTracker1stReviewComment;
   document.ProgressTrackerForm.IndustryBenchmarksOverridesTracker2ndReviewComment.value =  localStorage.IndustryBenchmarksOverridesTracker2ndReviewComment;
}
