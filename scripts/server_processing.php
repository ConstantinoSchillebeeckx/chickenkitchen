<?php

/**
 * Script tht receives AJAX request for querying database   
 *
 * @param _GET['table'] str - table name to query
 * @param _GET['cols'] arr - array of column names in table
 * @param _GET['pk'] arr - name of field in DB that is primary key
 *
 * @return datatables formatted data
*/


require_once( 'ssp.class.php' );
require_once("../functions.php"); 

 
if ( isset($_GET['table'] ) ) {

    // DB table to use
    $table = $_GET['table'];
     
    $primaryKey = $_GET['pk'];
     
    // Array of database columns which should be read and sent back to DataTables.
    // The `db` parameter represents the column name in the database, while the `dt`
    // parameter represents the DataTables column identifier. In this case simple
    // indexes
    $columns = array();
    foreach ($_GET['cols'] as $i => $col) {
        $columns[] = array('db' => $col, 'dt' => $i);
    }
     
    echo json_encode( SSP::simple( $_GET, get_db_conn(), $table, $primaryKey, $columns ) );

}
