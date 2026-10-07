<?php 
// Database connection info 
include '../../assets/includes/connection_db_serverside.php';

// mysql db table to use 
$table = 'regression_results'; 
 
// Table's primary key 
$primaryKey = 'id'; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt` parameter represents the DataTables column identifier. 
$columns = array( 
    array( 'db' => 'id',                              'dt' => 0  ), 
    array( 'db' => 'statistic_code',                  'dt' => 1  ), 
    array( 'db' => 'credit_loss_code',                'dt' => 2  ), 
    array( 'db' => 'start_period',                    'dt' => 3  ), 
    array( 'db' => 'end_period',                      'dt' => 4  ), 
    array( 'db' => 'reporting_period',                'dt' => 5  ), 
    array( 'db' => 'expected_correlation_sign',       'dt' => 6  ), 
    array( 'db' => 'correlation_parameter_R',         'dt' => 7  ), 
    array( 'db' => 'correlation_parameter_R2_percent','dt' => 8  ), 
    array( 'db' => 'R2_cutoff',                       'dt' => 9  ), 
    array( 'db' => 'R2_cutoff_test_verdict',          'dt' => 10  ), 
    array( 'db' => 'R_sign_test_verdict',             'dt' => 11 ), 
    array( 'db' => 'slope',                           'dt' => 12 ), 
    array( 'db' => 'intercept',                       'dt' => 13 ), 
    array( 
        'db'        => 'created_at', 
        'dt'        => 14, 
        'formatter' => function( $d, $row ) { 
            return date('Ym', strtotime($row['created_at'])); 
        } 
    ), 
    array( 
        'db'        => 'id',
        'dt'        => 15, 
        'formatter' => function( $d, $row ) { 
            return '<a href="javascript:void(0)" 
					   class="btn btn-primary btn-edit"
					   data-id="'.$row['id'].'"
					   > Calc 
					 </a> 
					   <a href="javascript:void(0)" 
					   class="btn btn-danger btn-delete"
					   data-id="'.$row['id'].'"
					   ><span aria-hidden="true">&times;</span> 
					 </a>'; 
        } 
    ) 
); 
 
// Include SQL query processing class 
require 'ssp.class.php'; 
 
// Output data as json format 
echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns ));