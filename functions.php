<?php

/** 
 *
 * Initialize a new database connection using the Medoo framework.
 *
 * @param void
 * 
 * @return medoo object
 *
*/

function init_db() {
    
    require_once "config/db.php"; // load DB variables
    require_once "Medoo/medoo.php"; // SQL library
    require_once "scripts/db_setup.php"; // DB setup class

    // Initialize connection
    $db = new medoo([
        'database_type' => 'mysql',
        'database_name' => DB_NAME,
        'server' => DB_HOST,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => utf8mb4_general_ci
    ]);

    // Get setup
    $db->setup = new Database( ACCT, $db );

    return $db;
}




?>
