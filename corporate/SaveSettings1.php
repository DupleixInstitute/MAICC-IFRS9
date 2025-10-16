<html>

<head>
<Title>CORPORATE SCORING MODEL - Saving Model Calibration Settings</Title>
</head>

<body>
    <?php
        /***************************************************************************************************************************************
	     STEP 1 : INITIALISE VARIABLES
         ****************************************************************************************************************************************
         MAIN SETTINGS WEIGHTS
		 1. Main Settings Table Weights - Big Firms
		**/ 
		 $bfFinancialAnalysisPercentage                   = null;
	     $bfManagementAnalysisPercentage                  = null;
	     $bfIndustryAnalysisPercentage                    = null;
		 $bfShareholderAnalysisPercentage                 = null;
		 $bfBehavioralAnalysisPercentage                  = null;
		 //2. Main Settings Table Weights - Small Firms
	     $sfFinancialAnalysisPercentage                   = null;
	     $sfManagementAnalysisPercentage                  = null;
	     $sfIndustryAnalysisPercentage                    = null;
		 $sfShareholderAnalysisPercentage                 = null;
		 $sfBehavioralAnalysisPercentage                  = null;
	     //3. Main Settings Table Scores - Big Firms
		 $bfFinancialAnalysisScore                        = null;
	     $bfManagementAnalysisScore                       = null;
	     $bfIndustryAnalysisScore                         = null;
		 $bfShareholderAnalysisScore                      = null;
		 $bfBehavioralAnalysisScore                       = null;
		 //4. Main Settings Table Scores - Small Firms
	     $sfFinancialAnalysisScore                        = null;
	     $sfManagementAnalysisScore                       = null;
	     $sfIndustryAnalysisScore                         = null;
		 $sfShareholderAnalysisScore                      = null;
		 $sfBehavioralAnalysisScore                       = null;
		 //5. Main Settings Table Total Scores - Big and Small Firms
		 $bfTotalScore=100;
	     $sfTotalScore=100;
		
  	 
	    /***************************************************************************************************************************************
	     STEP 2 : ASSIGN THE VARIABLES WITH EXTRACTED VALUES FROM THE FORM
         ****************************************************************************************************************************************/
         
		 //1. Main Settings Table Weights - Big Firms
	 	 $bfFinancialAnalysisPercentage                = str_replace(",","",$_POST['bfFinancialAnalysisPercentage']);
	 	 $bfManagementAnalysisPercentage               = str_replace(",","",$_POST['bfManagementAnalysisPercentage']);
	 	 $bfIndustryAnalysisPercentage                 = str_replace(",","",$_POST['bfIndustryAnalysisPercentage']);
	 	 $bfShareholderAnalysisPercentage              = str_replace(",","",$_POST['bfShareholderAnalysisPercentage']);
	 	 $bfBehavioralAnalysisPercentage               = str_replace(",","",$_POST['bfBehavioralAnalysisPercentage']);

		 //2. Main Settings Table Weights - Small Firms
	 	 $sfFinancialAnalysisPercentage                = str_replace(",","",$_POST['sfFinancialAnalysisPercentage']);
	 	 $sfManagementAnalysisPercentage               = str_replace(",","",$_POST['sfManagementAnalysisPercentage']);
	 	 $sfIndustryAnalysisPercentage                 = str_replace(",","",$_POST['sfIndustryAnalysisPercentage']);
	 	 $sfShareholderAnalysisPercentage              = str_replace(",","",$_POST['sfShareholderAnalysisPercentage']);
	 	 $sfBehavioralAnalysisPercentage               = str_replace(",","",$_POST['sfBehavioralAnalysisPercentage']);

		 //3. Main Settings Table Scores - Big Firms
	 	 $bfFinancialAnalysisScores                    = str_replace(",","",$_POST['bfFinancialAnalysisScore']);
	 	 $bfManagementAnalysisScores                   = str_replace(",","",$_POST['bfManagementAnalysisScore']);
	 	 $bfIndustryAnalysisScores                     = str_replace(",","",$_POST['bfIndustryAnalysisScore']);
	 	 $bfShareholderAnalysisScores                  = str_replace(",","",$_POST['bfShareholderAnalysisScore']);
	 	 $bfBehavioralAnalysisScores                   = str_replace(",","",$_POST['bfBehavioralAnalysisScore']);
		
		//4. Main Settings Table Scores - Small Firms
	 	 $sfFinancialAnalysisScores                    = str_replace(",","",$_POST['sfFinancialAnalysisScore']);
	 	 $sfManagementAnalysisScores                   = str_replace(",","",$_POST['sfManagementAnalysisScore']);
	 	 $sfIndustryAnalysisScores                     = str_replace(",","",$_POST['sfIndustryAnalysisScore']);
	 	 $sfShareholderAnalysisScores                  = str_replace(",","",$_POST['sfShareholderAnalysisScore']);
	 	 $sfBehavioralAnalysisScores                   = str_replace(",","",$_POST['sfBehavioralAnalysisScore']);
		
	
		 //======================================================================================================================================
		 // ASSIGNING TURNOVER THRESHOLD AND RATIO WEIGHTS VARIABLES BEFORE SAVING ON DATABASE
	     $TurnoverThreshold                               = str_replace(",","",$_POST['TurnoverThreshold']);  
         $RatioWeightYear1                                = str_replace(",","",$_POST['RatioWeightYear1']);  
       	 $RatioWeightYear2                                = str_replace(",","",$_POST['RatioWeightYear2']);  
       	 $RatioWeightYear3                                = str_replace(",","",$_POST['RatioWeightYear3']);  
	    
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
     
        $username = $_POST['username'];
        $update_notes = $_POST['update_notes']; 
		$blank = "";
		

		$insert_query = "INSERT IGNORE INTO model_calibration 
 		   (charecteristic                      ,table_name                  ,weight_in_table                                  ,big_firm_effective_weight                       ,small_firm_effective_weight                      ,big_firm_max_score                    ,small_firm_max_score                   ,username  ,update_notes)
   
        	VALUES
			  ('bfFinancialAnalysisPercentage'  ,'MainSettings'              ,'$bfFinancialAnalysisPercentage',                '100'                                            ,'100'                                            ,'$bfFinancialAnalysisScore'           ,'0'                                  ,'$username','$blank'),
       		  ('bfManagementAnalysisPercentage' ,'MainSettings'              ,'$bfManagementAnalysisPercentage'                ,'100'                                           ,'100'                                            ,'$bfManagementAnalysisScore'          ,'0'                                  ,'$username','$blank'),
  			  ('bfIndustryAnalysisPercentage'   ,'MainSettings'              ,'$bfIndustryAnalysisPercentage'                  ,'100'                                           ,'100'                                            ,'$bfIndustryAnalysisScore'            ,'0'                                  ,'$username','$blank'),
  		      ('bfShareholderAnalysisPercentage','MainSettings'              ,'$bfShareholderAnalysisPercentage'               ,'100'                                           ,'100'                                            ,'$bfShareholderAnalysisScore'         ,'0'                                  ,'$username','$blank'),
 	     	  ('bfBehavioralAnalysisPercentage' ,'MainSettings'              ,'$bfBehavioralAnalysisPercentage'                ,'100'                                           ,'100'                                            ,'$bfBehavioralAnalysisScore'          ,'0'                                  ,'$username','$blank'),
        	  ('bfTotalPercentage'              ,'MainSettings'              ,'100'                                            ,'100'                                           ,'100'                                            ,'$bfTotalScore'                       ,'0'                                  ,'$username','$blank'),
   
			  ('sfFinancialAnalysisPercentage'  ,'MainSettings'              ,'$sfFinancialAnalysisPercentage'                 ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfFinancialAnalysisScore'            ,'$username','$blank'),
  		      ('sfManagementAnalysisPercentage' ,'MainSettings'              ,'$sfManagementAnalysisPercentage'                ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfManagementAnalysisScore'           ,'$username','$blank'),
  			  ('sfIndustryAnalysisPercentage'   ,'MainSettings'              ,'$sfIndustryAnalysisPercentage'                  ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfIndustryAnalysisScore'             ,'$username','$blank'),
  		      ('sfShareholderAnalysisPercentage','MainSettings'              ,'$sfShareholderAnalysisPercentage'               ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfShareholderAnalysisScore'          ,'$username','$blank'),
 	     	  ('sfBehavioralAnalysisPercentage' ,'MainSettings'              ,'$sfBehavioralAnalysisPercentage'                ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfBehavioralAnalysisScore'           ,'$username','$blank'),
        	  ('sfTotalPercentage'              ,'MainSettings'              ,'100'                                            ,'100'                                           ,'100'                                            ,'0'                                 ,'$sfTotalScore'                        ,'$username','$blank'),
   
	 
 	          ('TurnoverThreshold'              ,'OtherSettings'              ,'$TurnoverThreshold'                            ,'0'                                               ,'0'                                            , '0'                                   , '0'                               ,'$username','$blank'),
 	          ('RatioWeightYear1'               ,'OtherSettings'              ,'$RatioWeightYear1'                             ,'0'                                               ,'0'                                            , '0'                                   , '0'                               ,'$username','$blank'),
 	          ('RatioWeightYear2'               ,'OtherSettings'              ,'$RatioWeightYear2'                             ,'0'                                               ,'0'                                            , '0'                                   , '0'                               ,'$username','$blank'),
 	          ('RatioWeightYear3'               ,'OtherSettings'              ,'$RatioWeightYear3'                             ,'0'                                               ,'0'                                            , '0'                                   , '0'                               ,'$username','$blank'),
	          ('update_notes'                   ,'MainSettings'              ,'n/a'                                           ,'0'                                               ,'0'                                            , '0'                                   , '0'                               ,'$username','$update_notes')";
    
   
        $resultm= mysqli_query($connect,$insert_query); 

        if (!$resultm)
          {                                                    
             echo 'error with inserting data'. mysqli_error();
          }
        else
		  {
		     echo 'Data successfully saved';
			      
			 //header("Location:Settings1.php");
            // exit;
	   
	      }
		  
 ?>
</body>

</html>