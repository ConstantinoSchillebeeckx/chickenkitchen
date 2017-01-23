<?php

    // script to handle AJAX requests from front end
    // simply calls the proper function based on
    // the 'action' parameter
    require_once('functions.php');

    $error = true;

    if( isset( $_GET['action'] ) && !empty( $_GET['action'] ) ) {

        $action = $_GET['action'];

        if ( $action == 'addTable' && isset( $_GET['field_num'] ) && isset( $_GET['dat'] ) && isset( $_GET['dat']['table_name'] ) ) {
            add_table_to_db( $_GET ); // add a new table
            $error = false;
        } else if ( $action == 'deleteTable' && isset( $_GET['table_name'] ) ) {
            delete_table_from_db( $_GET['table_name'] ); // delete existing table
            $error = false;
        } else if ( $action == 'addItem' ) {
            add_item_to_db( $_GET );
            $error = false;
        } else if ( $action == 'deleteItem' ) {
            delete_item_from_db( $_GET );
            $error = false;
        } else if ( $action == 'editItem' ) {
            echo edit_item_in_db( $_GET );
            $error = false;
        }
    
    }

    // check for error
    if ( $error ) {
        echo json_encode( array( 'msg'=>'Ensure "action" is sent in AJAX data.', 'status'=>false, 'hide'=> false, "log" => $_GET ));

    }


?>
