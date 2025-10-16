/**
Step 3 — Handling Form Submit Logic in JavaScript and jQuery

To submit a form via AJAX, your script will need to handle four tasks:

1. Capture the form submit button so that the default action does not take place.
2. Get all of the data from the form using jQuery.
3. Submit the form data using AJAX.
4. Display errors if there are any.

**/


$(document).ready(function () {
  $("form").submit(function (event) {
	  
    //=============================================================================================
    //  	Every time we submit the form, our errors from our previous submission are still there. 
	//      You will need to clear them by removing them as soon as the form is submitted again.
		
	$(".form-group").removeClass("has-error");
    $(".help-block").remove();
    
	//==============================================================================================
    
	var formData = {
      name: $("#name").val(),
      email: $("#email").val(),
      superheroAlias: $("#superheroAlias").val(),
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
      url: "process.php",
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
    	if (data.errors.name) {
          $("#name-group").addClass("has-error");
          $("#name-group").append(
            '<div class="help-block">' + data.errors.name + "</div>"
          );
        }

        if (data.errors.email) {
          $("#email-group").addClass("has-error");
          $("#email-group").append(
            '<div class="help-block">' + data.errors.email + "</div>"
          );
        }

        if (data.errors.superheroAlias) {
          $("#superhero-group").addClass("has-error");
          $("#superhero-group").append(
            '<div class="help-block">' + data.errors.superheroAlias + "</div>"
          );
        }
      } else {
        $("form").html(
          '<div class="alert alert-success">' + data.message + "</div>"
        );
      }

    })
	//=====================================================================================================================================
	.fail(function (data) {
        $("form").html(
          '<div class="alert alert-danger">Could not reach server, please try again later.</div>'
        );
     });

