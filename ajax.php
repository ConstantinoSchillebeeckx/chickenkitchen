<?php

    // script to handle AJAX requests from front end
    // simply calls the proper function based on
    // the 'action' parameter
    require_once('functions.php');

    $error = true;

    // in the case of a batchUpdate, the request comes in as a _POST not a _GET
    $action = isset( $_GET['action'] ) ? $_GET['action'] : $_POST['action'];

    if( isset( $action ) ) {

        if ( $action == 'addTable' && isset( $_GET['field_num'] ) && isset( $_GET['dat'] ) && isset( $_GET['dat']['table_name'] ) ) {
            echo add_table_to_db( $_GET ); // add a new table
            $error = false;
        } else if ( $action == 'deleteTable' && isset( $_GET['table_name'] ) ) {
            echo delete_table_from_db( $_GET['table_name'] ); // delete existing table
            $error = false;
        } else if ( $action == 'addItem' ) {
            echo add_item_to_db( $_GET );
            $error = false;
        } else if ( $action == 'deleteItem' ) {
            echo delete_item_from_db( $_GET );
            $error = false;
        } else if ( $action == 'editItem' ) {
            echo edit_item_in_db( $_GET );
            $error = false;
        } else if ( $action == 'batchUpdate' ) { // handles add, edit, delete in batch
            echo batch_update_db( $_POST, $_FILES['batchFile'] );
            $error = false;
        } else if ( $action == 'revertItem' ) { // revert changes from history modal
            echo revert_item( $_GET );
            $error = false;
        } else if ( $action == 'saveTable' ) { // edit and save table
            echo save_table( $_GET );
            $error = false;
        }
    
    }

    // check for error
    if ( $error ) {

        echo json_encode( array( 'msg'=>'Ensure "action" is sent in AJAX data.', 'status'=>false, 'hide'=> false, "log" => $_GET ));

    }


?>
