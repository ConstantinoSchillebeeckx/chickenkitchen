<?php

/**
 * Script tht receives AJAX request for querying database   
 *
 * @param _GET['table'] str - table name to query
 * @param _GET['cols'] arr - array of column names in table
 * @param _GET['pk'] arr - name of field in DB that is primary key
 * @param _GET['filter'] assoc arr [col: filter] - filter for column
 *
 * @return datatables formatted data
*/


require_once( 'ssp.class.php' );
require_once("../functions.php"); 
 
if ( isset($_GET['table'] ) ) {

    // DB table to use
    $table = $_GET['table'];
    $primaryKey = $_GET['pk'];

    // setup filter
    // should be a way of setting up the ajax call before it comes to the
    // server (e.g. columns[X][search][value] = ..
    // but I don't know how to do it, so I'm doing it manually here
    $filter = $_GET['filter'];
    if ( isset( $filter ) && is_array( $filter ) ) {
        foreach ( $filter as $col => $str ) {
            $idx = array_search( $col, $_GET['cols'] ); // col number of filter
            $_GET['columns'][$idx]['search']['value'] = $str;
        }
    }

    // Array of database columns which should be read and sent back to DataTables.
    // The `db` parameter represents the column name in the database, while the `dt`
    // parameter represents the DataTables column identifier. In this case simple
    // indexes
    $columns = array();
    foreach ($_GET['cols'] as $i => $col) {
        $columns[] = array('db' => $col, 'dt' => $i);
    }
     
    $results = SSP::simple( $_GET, get_db_conn(), $table, $primaryKey, $columns );

    echo json_encode( $results );

}
