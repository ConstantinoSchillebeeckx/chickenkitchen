<?php

    // script to handle AJAX requests from front end
    // simply calls the proper function based on
    // the 'action' parameter
    require_once('functions.php');

    if( isset( $_GET['action'] ) && !empty( $_GET['action'] ) ) {

        if ( $_GET['action'] == 'addTable' ) {
            add_table_to_db( $_GET );
        }

    } else {

        echo json_encode( array( 'msg'=>'Ensure "action" is sent in AJAX data.', 'status'=>false, 'hide'=> false, "log" => $_GET ));

    }


?>
