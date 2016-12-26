<?php
 
 
// DB table to use
$table = 'matatu_test';
 
// Table's primary key
$primaryKey = '_UID';
 
// Array of database columns which should be read and sent back to DataTables.
// The `db` parameter represents the column name in the database, while the `dt`
// parameter represents the DataTables column identifier. In this case simple
// indexes
$columns = array(
    array( 'db' => '_UID', 'dt' => 0 ),
    array( 'db' => 'asdf',  'dt' => 1 ),
);

 
 
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP
 * server-side, there is no need to edit below this line.
 */
 
require( 'ssp.class.php' );
require_once("../functions.php");
 
echo json_encode(
    SSP::simple( $_GET, init_db(), $table, $primaryKey, $columns )
);
