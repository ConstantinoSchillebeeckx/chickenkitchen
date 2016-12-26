<?php

/** 
 *
 * Initialize a new database connection using the Medoo framework.
 * Function assumes the DB parameters have been loaded, these are
 * stored in var/db.php.
 *
 * @param void
 * 
 * @return medoo object
 *
*/

function init_db() {

    // Initialize
    $db = new medoo([
        'database_type' => 'mysql',
        'database_name' => DB_NAME,
        'server' => DB_HOST,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => utf8mb4_general_ci
    ]);

    return $db;
}




?>
