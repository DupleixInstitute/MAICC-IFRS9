<html>

<head>
<Title>CORPORATE SCORING MODEL - Saving Model Calibration Company Data</Title>
</head>

<body>
    <?php
        /***************************************************************************************************************************************
	     STEP 1 : INITIALISE VARIABLES
         ****************************************************************************************************************************************
		**/ 
		 $application_ref                         = null;
	     $company_reg_no                          = null;
	     $CIF                                     = null;
		 $loan_number                             = null;
		 $registration_year                       = null;
         $customer_type                           = null;  
         $legal_name                              = null;
         $trading_name                            = null;
         $registration_country                    = null;
         $financial_year1                         = null;
         $financail_year2                         = null;
         $financial_year3                         = null;
         $reporitng_month_year1                   = null;
		 $reporitng_month_year2                   = null;
         $reporitng_month_year3                   = null;
         $audit_status_year1                      = null;
		 $audit_status_year2                      = null;
		 $audit_status_year3                      = null;
		 $real_inflation_year1                    = null;
         $real_inflation_year2                    = null;
         $real_inflation_year3                    = null;
         $nominal_inflation_year1                 = null;
         $nominal_inflation_year2                 = null;
         $nominal_inflation_year3                 = null:
         $industrial_sector                       = null;
         $borrower_present_address                = null;
         $street_name_and_number                  = null;
         $years_at_present_address                = null;
         $e_mail                                  = null;
         $permanent_country_of_residence          = null;
         $location_of_aquired_real_estate         = null;
         $main_bank                               = null;
         $second_bank                             = null;
         $third_bank                              = null;
         		 
	    /***************************************************************************************************************************************
	     STEP 2 : ASSIGN THE VARIABLES WITH EXTRACTED VALUES FROM THE FORM
         ****************************************************************************************************************************************/
 		 $application_ref                         = $_POST['application_ref'];
	     $company_reg_no                          = $_POST['company_reg_no'];
	     $CIF                                     = $_POST['CIF '];
		 $loan_number                             = $_POST['loan_number'];
		 $registration_year                       = $_POST['registration_year'];
         $customer_type                           = $_POST['customer_type'];  
         $legal_name                              = $_POST['legal_name'];
         $trading_name                            = $_POST['trading_name'];
         $registration_country                    = $_POST['registration_country'];
         $financial_year1                         = $_POST['financial_year1'];
         $financail_year2                         = $_POST['financail_year2'];
         $financial_year3                         = $_POST['financial_year3'];
         $reporitng_month_year1                   = $_POST['reporitng_month_year1'];
		 $reporitng_month_year2                   = $_POST['reporitng_month_year2'];
         $reporitng_month_year3                   = $_POST['reporitng_month_year3'];
         $audit_status_year1                      = $_POST['audit_status_year1'];
		 $audit_status_year2                      = $_POST['audit_status_year2'];
		 $audit_status_year3                      = $_POST['audit_status_year3'];
		 $real_inflation_year1                    = $_POST['real_inflation_year1'];
         $real_inflation_year2                    = $_POST['real_inflation_year2'];
         $real_inflation_year3                    = $_POST['real_inflation_year3'];
         $nominal_inflation_year1                 = $_POST['nominal_inflation_year1'];
         $nominal_inflation_year2                 = $_POST['nominal_inflation_year2'];
         $nominal_inflation_year3                 = $_POST['nominal_inflation_year3'];
         $industrial_sector                       = $_POST['industrial_sector'];
         $borrower_present_address                = $_POST['borrower_present_address'];
         $street_name_and_number                  = $_POST['street_name_and_number'];
         $years_at_present_address                = $_POST['years_at_present_address'];
         $e_mail                                  = $_POST['e_mail'];
         $permanent_country_of_residence          = $_POST['permanent_country_of_residence'];
         $location_of_aquired_real_estate         = $_POST['location_of_aquired_real_estate'];
         $main_bank                               = $_POST['main_bank'];
         $second_bank                             = $_POST['second_bank'];
         $third_bank                              = $_POST['third_bank'];
        
	   
		/***************************************************************************************************************************************
	     STEP 3 : CONNECT TO DATABASE AND SAVE THE ASSIGNED VARIABLES
         ****************************************************************************************************************************************/
      		 
	     
		 //Connecting to the database
		 $pass= "";
         $host="localhost"; 
         $user="root"; 
         $db_name="corporatescoring"; 
                   
	               // GET HOST BY NAME ?
				   // Connect to server and select databse.
                   //$ip =gethostbyname($_SERVER['REMOTE_ADDR']); 
                   //echo $ip;

         //New PHP Version
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
		  
		 //Old PHP Version
			       // mysqli_select_db( $connect,$db_name)  
                   // or die("Couldn't open $dbase: ".mysqli_error())


         // GET DUMMY USERNAME AND UPDATE NOTES - NEED TO ADJUST FORM TO GET THE PASSWORD
     
        $username = "Edward";
 
		$insert_query = "INSERT INTO loan_data 
 	    SET
		    application_ref                         = $application_ref,
	        company_reg_no                          = $company_reg_no,
	        CIF                                     = $CIF,
		    loan_number                             = $loan_number,
		    registration_year                       = $registration_year,
            customer_type                           = $customer_type,  
            legal_name                              = $legal_name,
            trading_name                            = $trading_name,
            registration_country                    = $registration_country,
            financial_year1                         = $financial_year1,
            financail_year2                         = $financail_year2,
            financial_year3                         = $financial_year3,
            reporitng_month_year1                   = $reporitng_month_year1,
		    reporitng_month_year2                   = $reporitng_month_year2,
            reporitng_month_year3                   = $reporitng_month_year3,
            audit_status_year1                      = $audit_status_year1,
		    audit_status_year2                      = $audit_status_year2,
		    audit_status_year3                      = $audit_status_year3,
		    real_inflation_year1                    = $real_inflation_year1,
            real_inflation_year2                    = $real_inflation_year2,
            real_inflation_year3                    = $real_inflation_year3,
            nominal_inflation_year1                 = $nominal_inflation_year1,
            nominal_inflation_year2                 = $nominal_inflation_year2,
            nominal_inflation_year3                 = $nominal_inflation_year3,
            industrial_sector                       = $industrial_sector,
            borrower_present_address                = $borrower_present_address,
            street_name_and_number                  = $street_name_and_number,
            years_at_present_address                = $years_at_present_address,
            permanent_country_of_residence          = $permanent_country_of_residence,
            location_of_aquired_real_estate         = $location_of_aquired_real_estate,
            main_bank                               = $main_bank,
            second_bank                             = $second_bank,
            third_bank                              = $third_bank";
            username                                = $username;
   
        $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting data'. mysqli_error();
          }
        else
		  {
		     echo 'Data successfully saved';
	      }
		  
 ?>
</body>

</html>