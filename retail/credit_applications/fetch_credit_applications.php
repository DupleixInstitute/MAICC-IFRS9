 <?php 
// Database connection info 
include '../assets/includes/connection_db_serverside.php';

// mysql db table to use 
$table = 'cust_information'; 
 
// Table's primary key 
$primaryKey = 'application_ref'; 
/**
session_start();
$reporting_year  = $_SESSION['reporting_year'];
$reporting_month = $_SESSION['reporting_month'];
if ($reporting_month < 10) {
    $reporting_month = '0'.$reporting_month;
}
$reporting_period = intval($reporting_year.$reporting_month);
**/
$where = "application_ref <> 0 order by application_ref DESC"; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt`

$columns = array( 
    array( 'db'         => 'application_ref',             'dt' => 0  ), 
    array( 'db'         => 'omang_passport_num',          'dt' => 1  ), 
    array( 'db'         => 'loan_number',                 'dt' => 2  ), 
    array( 'db'         => 'loan_category',               'dt' => 3  ), 
    array( 'db'         => 'status',                      'dt' => 4  ), 
    array( 'db'         => 'borrower_name',               'dt' => 5  ), 
    array( 'db'         => 'loan_amount_requested',       'dt' => 6  ), 
    array( 'db'         => 'open_market_value',           'dt' => 7  ), 
    array( 'db'         => 'loan_to_value',               'dt' => 8  ), 
    array( 'db'         => 'loan_maturity_requested',     'dt' => 9  ), 
    array( 'db'         => 'rate_type_requested',         'dt' => 10  ), 
    array( 'db'         => 'current_interest_rate',    
	       'dt'         => 11, 
           'formatter'  => function( $d, $row ) { 
             return $row['current_interest_rate']; 
		 }), 
    array( 'db'         => 'total_revenues_affordability','dt' => 12), 
    array( 'db'         => 'affordability_ratio',         'dt' => 13), 
    array( 'db'         => 'marital_status',              'dt' => 14), 
	array( 
        'db'        => 'creation', 
        'dt'        => 15, 
        'formatter' => function( $d, $row ) { 
            return date('Ym', strtotime($row['creation'])); 
        } 
    ), 
    array( 
        'db'        => 'application_ref',
        'dt'        => 16, 
        'formatter' => function( $d, $row ) { 
             return '<a href="javascript:void(0)" 
			           class="edit-icon"
					   data-id="'.$row['application_ref'].'"> 
					   <i  class="fas fa-edit"     style="font-size:18px"></i>
					</a> ,
					<a href="javascript:void(0)" 
			           class="delete-icon"					   
					   data-id="'.$row['application_ref'].'"> 
					   <i class="icon-ok-sign" style="font-size:18px"></i>
					 </a>
					<a href="javascript:void(0)" 
			           class="book-icon"					   
					   data-id="'.$row['application_ref'].'"> 
					   <i class="fa fa-check" aria-hidden="true" style="font-size:20px;color:green"></i>
					 </a>
					<a href="javascript:void(0)" 
			           class="calculator-icon"					   
					   data-id="'.$row['application_ref'].'"> 
					   <i class="fa fa-scoreboard" aria-hidden="true" style="font-size:18px"></i>
					 </a>
					<a href="javascript:void(0)" 
			           class="binoculars-icon"					   
					   data-id="'.$row['application_ref'].'"> 
					   <i class="fa fa-binoculars" aria-hidden="true" style="font-size:18px"></i>
					 </a>
					 '; 
        } 
    )
); 
 //<i class="fa fa-book" aria-hidden="true"></i>
// Include SQL query processing class 
require 'ssp.class.php'; 
 
// Output data as json format 
echo json_encode( 
    SSP::complex( $_GET, $dbDetails, $table, $primaryKey, $columns,null,$where));	