<?php 
// Database connection info 
include '../../assets/includes/connection_db_serverside.php';


// mysql db table to use 
$table = 'regression_definitions'; 
 
// Table's primary key 
$primaryKey = 'id'; 
 
// Array of database columns which should be read and sent back to DataTables. 
// The `db` parameter represents the column name in the database.  
// The `dt` parameter represents the DataTables column identifier. 
$columns = array( 
    array( 'db' => 'id',                              'dt' => 0  ), 
    array( 'db' => 'statistic_code',                  'dt' => 1  ), 
    array( 'db' => 'statistic_name',                  'dt' => 2  ), 
    array( 'db' => 'credit_loss_code',                'dt' => 3  ), 
    array( 'db' => 'credit_loss_name',                'dt' => 4  ), 
    array( 'db' => 'expected_correlation_sign',       'dt' => 5  ), 
    array( 'db' => 'R2_cutoff',                       'dt' => 6  ), 
    array( 'db' => 'comment',                         'dt' => 7 ), 
    array( 
        'db'        => 'created_at', 
        'dt'        => 8, 
        'formatter' => function( $d, $row ) { 
            return date('Ym', strtotime($row['created_at'])); 
        } 
    ), 
    array( 
        'db'        => 'id',
        'dt'        => 9, 
        'formatter' => function( $d, $row ) { 
            return '<a href="javascript:void(0)" 
					   class="btn btn-primary btn-edit"
					   data-id="'.$row['id'].'"
					   > Edit 
					 </a> 
					   <a href="javascript:void(0)" 
					   class="btn btn-danger btn-delete"
					   data-id="'.$row['id'].'"
					   > Delete 
					 </a>'; 
        } 
    ) 
); 
 
// Include SQL query processing class 
require 'ssp.class.php'; 
 
// Output data as json format 
echo json_encode( 
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns ));