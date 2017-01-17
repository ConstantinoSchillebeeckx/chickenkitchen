<?php

    // script to handle AJAX requests from front end
    // simply calls the proper function based on
    // the 'action' parameter
    require_once('functions.php');

    $error = true;

    if( isset( $_GET['action'] ) && !empty( $_GET['action'] ) ) {

        if ( $_GET['action'] == 'addTable' && isset( $_GET['field_num'] ) && isset( $_GET['dat'] ) && isset( $_GET['dat']['table_name'] ) ) {
            add_table_to_db( $_GET ); // add a new table
            $error = false;
        } else if ( $_GET['action'] == 'deleteTable' && isset( $_GET['table_name'] ) ) {
            delete_table_from_db( $_GET['table_name'] ); // delete existing table
            $error = false;
        }
    
    }

    // check for error
    if ( $error ) {
        echo json_encode( array( 'msg'=>'Ensure "action" is sent in AJAX data.', 'status'=>false, 'hide'=> false, "log" => $_GET ));

    }


?>
