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

function get_db_conn() {
    
    require_once "config/db.php"; // load DB variables
    require_once "lib/Medoo/medoo.php"; // SQL library

    // Initialize connection
    $db = new medoo([
        'database_type' => 'mysql',
        'database_name' => DB_NAME,
        'server' => DB_HOST,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => 'utf8',
    ]);

    return $db;

}




/** 
 *
 * Get the database setup using the 'Database class'
 *
 * @param void
 * 
 * @return Database class
 *
*/

function get_db_setup() {

    require_once "config/db.php"; // load DB variables
    require_once "db.class.php"; // Database class


    if ( !isset( $_SESSION['db'] ) || !is_a( $_SESSION['db'], 'Database' ) ) {

        // Get setup
        $setup = new Database( ACCT, get_db_conn() );

        $_SESSION['db'] = $setup;
    }

    return $_SESSION['db'];
}





/**
 *
 * Refresh DB class once it has been changed such
 * as after a table is created/deleted/edited.
 * Will update the $_SESSION['db'] global var
 * 
 * @para void
 * 
 * @return void
 *
*/
function refresh_db_setup() {

    $_SESSION['db'] = NULL;
    get_db_setup();

}
















/**
 * Generate HTML for viewing a table
 *
 * @param $table str database table to view
 *
 * @return will echo proper HTML
 *
*/
function build_table( $table ) {

    $db = get_DB_setup();
    $pk = $db->get_pk($table);
    $table_class = $db->get_table($table);
    $has_history = $db->has_history($table);
    if ( $has_history ) $pk_hist = $db->get_pk($table . "_history");
   
    // generate table HTML
    if ( isset( $db ) && isset( $table ) && $table_class ) {
  
        $fields = $db->get_all_fields($table); 
        $hidden = $table_class->get_hidden_fields();

        ?>
        
        <table class="table table-bordered table-hover" id="datatable">
        <thead>
        <tr class="info">

        <?php foreach ( $fields as $field ) echo "<th>$field</th>"; ?>

        <th>Action</th>
        </tr>
        </thead>
        </table>

        <script type="text/javascript">
            // This will do the AJAX call, func defined in js/table.js
            var table = <?php echo json_encode( $table ); ?>;
            var columns = <?php echo json_encode( $fields ); ?>;
            var filter = <?php echo json_encode( $filter ); ?>;
            var hidden = <?php echo json_encode( $hidden ); ?>;
            var pk = <?php echo json_encode( $pk ); ?>;
            var hasHistory = <?php echo json_encode( $has_history ); ?>;
            var pkHist = <?php echo json_encode( $pk_hist ); ?>;
            getDBdata(table, pk, columns, filter, hidden, null, hasHistory);
        </script>

    <?php } else {
        echo 'Table doesn\'t exist; list of available tables are: ' . implode(', ', $db->get_data_tables());
    }

}


/**
 * Function will generate HTML as <ul> of all available 
 * data tables in the database along with a link to view them.
 * 
 * @param void
 *
 * @return will echo <ul> html with <a href> to table
*/

function list_tables() {

    $db = get_db_setup();
    $tables = $db->get_data_tables();

    echo '<div class="col-sm-12">';

    if ( $tables ) {

        echo '<ul>';

        foreach( $tables as $table ) {
            echo "<li><a href='?table=$table'>$table</a></li>";
        }

        echo '</ul>';
    } else {
        echo "No tables present in database.";
    }

    echo '</div>';


}














/**
 * Build a form of inputs based on a table row
 *
 * When either adding a new item or editing a table row item,
 * a modal appears that should be filled with inputs for each
 * table field.  This function will generate a form that has
 * proper inputs for each of these fields.  If the field is
 * an FK, a select will be generated, otherwise an input
 * box is shown.  For any field that is automatically populated
 * (e.g. datetime), the input will be disabled and a note will
 * be displayed.
 *
 * @param $table - str - table name for which to generate input fields
 * 
 * @return will echo all proper HTML to be placed in form
*/
function get_form_table_row($table) {

    $db = get_db_setup();
    $table_class = $db->get_table($table);
    $fields = $table_class->get_fields();
    $hasRequired = false;
    forEach($fields as $field) {
        $field_class = $db->get_field($table, $field);
        $field_type = $field_class->get_type();
        $comment = $field_class->get_comment();
    
        if ($comment['column_format'] != 'hidden') {
            if ( preg_match('/float|int/', $field_type) ) {
                $type = 'number';
            } elseif ( $field_type == 'date') {
                $type = 'date';
            } elseif ( $field_type == 'datetime') {
                $type = 'datetime';
            } else {
                $type = 'text';
            }
            ?>

            <div class="form-group">

                <?php if ($field_class->is_required()) {
                    $hasRequired = true;
                    echo '<label class="col-sm-2 control-label">' . $field . '<span class="required">*</span></label>';
                } else {
                    echo '<label class="col-sm-2 control-label">' . $field . '</label>';
                } ?>

                <div class="col-sm-10">

                <?php if ( $field_class->is_fk() ) {  // if field is an fk, show a select dropdown with available values
                    get_fks_as_select($field_class);
                } else {
                    if ( in_array( $field_class->get_type(), array('datetime', 'date') ) && $field_class->get_default() ) {
                        echo "<input type='$type' id='$field' name='$field' class='form-control' disabled></input><small class='text-muted'>Field has been disabled since it populates automatically</small>";
                    } elseif ($field_class->is_required()) {
                        echo "<input type='$type' id='$field' name='$field' class='form-control' required>";
                    } else {
                        echo "<input type='$type' id='$field' name='$field' class='form-control'>";
                    }
                } ?>

                </div>
            </div>
    <?php    }
     } 
    if ($hasRequired) {
        echo '<p class="text-right"><span class="required">*</span> field is required</p>';
    }
    ?>

<?php }






/**
 * Generates a dropbown with available values for a foreign key
 * A foreign key field must take on a value from the table and
 * column that it references.  This function will generate the
 * HTML for a select dropdown that is filled with those
 * available column values.
 *
 * @param $field_class field class, the field (assumed to be an
 *        FK) for which to find the available column values for
 *
 * @return HTML for a select dropdown if the field is an FK; if 
 * field is not an FK or if the reference table/column doesn't 
 * exist, nothing is returned.
 *
*/
function get_fks_as_select($field_class) {

        $fks = $field_class->get_fks(); // get the available values
        $name = $field_class->get_name();
        $ref = $field_class->get_fk_ref(); // get the [table, field] the FK references
        if ( $fks !== false && isset($ref_id) ) {
            echo '<select class="form-control" id="' . $name . '" name="' . $name . '">';
            foreach ($fks as $fk) {
                echo sprintf("<option value='%s'>%s</option>", $fk, $fk);
            }
            echo '</select>';
        } else {
            $ref_field = $ref[1];
            $ref_table = $ref[0];
            echo "This field is a foreign key and references the field <code>$ref_field</code> in the table <code>$ref_table</code> which is currently empty. Please enter some values for <code>$ref_field</code> first.";
        }

}




/**
 * Function called by AJAX when user adds item with modal button.
 * Will do all the proper error checking on the sent data and,
 * if it all checks out, insert it into the datatable. Will then
 * add the proper entry to the history counterpart.
 *
 * @param assoc arr $aja_data with keys: table, pk, dat
 *        dat -> obj of form data (key: col name, val: value)
 *
 * @return void
*/
function add_item_to_db( $ajax_data ) {

    // init
    $table = $ajax_data['table'];
    $dat = $ajax_data['dat'];
    $pk = $ajax_data['pk'];
    $db = get_db_setup();

    // check that we have everything
    if ( !isset( $table ) || empty( $table) || !isset( $pk ) || empty( $pk ) ) {
        echo json_encode(array("msg" => 'There was an error, please try again.', "status" => false, "hide" => false));
        return;
    }

    $sent_fields = array_keys( $dat );
    $all_fields = $db->get_visible_fields( $table );
    $required_fields = $db->get_required_fields( $table );


    // go through each field of the table and check 
    // that valid information was sent for it
    $bindings = [];
    $sql = sprintf( "INSERT INTO `%s` (`%s`) VALUES (:%s)", $table, implode("`,`", $sent_fields), implode(",:", $sent_fields) );
    foreach ( $all_fields as $field_name ) {

        if ( !empty( $required_fields ) && in_array( $field_name, $required_fields ) && !in_array( $field_name, $sent_fields ) ) {
            echo json_encode(array("msg" => "Please ensure you've filled out all required fields including <code>$table_field</code>.", "status" => false, "hide" => false));
            return;
        }

        $field_type = $db->get_field( $table, $field_name );
        $field_val = $dat[ $field_name ];

        // validate field value
        $check = validate_field_value( $field_type, $field_val );
        if ( $check !== true ) {
            echo $check;
            return;
        }

        // field value checks out
        $bindings[":$field_name"] = $field_val;

    }
    
    // generate SQL for data and history table
    $db_conn = get_db_conn()->pdo;
    $stmt_table = bind_pdo( $bindings, $db_conn->prepare( $sql ) );


    // Execute
    if ( $stmt_table->execute() ) {

        $_UID_fk = $db_conn->query( "SELECT $pk FROM $table ORDER BY $pk DESC LIMIT 1" )->fetch()[$pk]; // UID of last element added
        add_item_to_history_table( $table . "_history", USER, $_UID_fk, "Manually added", $sent_fields, $bindings, $db_conn );

        if ( DEBUG ) {
            echo json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true, "sql" => $sql, "bind" => $sql_history ));
        } else {
            echo json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true ));
        }

    } else { // if error

        if ( DEBUG ) {
            echo json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => implode(' - ', $bindings), 'sql' => $sql ));
        } else {
            echo json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

    return;
}





/**
 * Add entry to history table after events
 * such as an element being added, edited
 * or deleted from a table.
 *
 * @param str $table - table name to add to
 *        str $user - user name to assoc change with
 *        int $fk - FK UID of element associated
 *        with change
 *        str $action - note about what was
 *        being done e.g. "Item deleted manually"
 *        array $fields - field names modified by user
 *        assoc. array $bindings - [field name, field value]
 *        PDO obj $db_conn
 *
 * @return void
 *
*/
function add_item_to_history_table( $table, $user, $fk, $action, $fields, $bindings, $db_conn ) {

    $sql_history = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`, `%s`) VALUES ('$fk', '$user', '$action', :%s)", $table, implode("`,`", $fields), implode(",:", $fields) );
    $stmt_table_history = bind_pdo( $bindings, $db_conn->prepare( $sql_history ) );
    $stmt_table_history->execute();

}











/* Function called by AJAX when user attempts to add table

Will generate both the standard data table and its
history counter part.

Standard table will have:
- columns specified by user
- _UID int(11) col with primary key

History table will have:
- same columns as standard table
- _UID_fk int(11) as foreign key to standard table ref. _UID
- User varchar(128)
- Timestamp timestamp, default CURRENT_TIMESTAMP
- Action varchar(128)

Will do all the proper error checking:
- table name must not already exist
- table name length must be <= 64
- table name must only include [a-zA-Z0-9\-_]
- field names are unique
- field name length must be <= 64
- field name must only include [a-zA-Z0-9\-_ ]

By default, all fields will receive an index, except
for those fields with an extra long varchar type. These
indexes are added so that the field can later act as a 
reference to a foreign key on any other field. Indexes
will have a random 4 character string appended to its
name to avoid clashing.


Parameters:
===========
- $ajax_data['dat'] : obj of form data (key: col name, val: value)
                 at a minimum will have the following keys:
                 - table_name (safe name)
                 - name-1 (field name)
                 - type-1 (field type)
- $ajax_data['field_num'] : number of fields being added
*/
function add_table_to_db( $ajax_data ) {

    // init
    $db = get_db_setup();
    $field_num = $ajax_data['field_num'];
    $data = $ajax_data['dat'];
    $fields = []; // list of fields in table
    $sql_fields = []; // sql command for each field, concat for full sql statement
    $bindings = []; // PDO bindings for default values
    $history_fields = []; // column name and type for history table


    // make sure we have everything
    if ( isset( $data['table_name'] ) && !empty( $data['table_name'] ) ) {
        $table = $data['table_name'];
    } else {
        echo json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
        return;
    }

    // validate table name
    $check = validate_name( $table, $db->get_all_tables() );
    if ( $check !== true ) {
        echo $check;
        return;
    }

    // check field names for errors
    for( $i = 1; $i<=$field_num; $i++ ) {

        $field_name = $data['name-' . $i];
        $field_type = $data['type-' . $i];
        $field_current = isset($data['currentDate-' . $i]) ? $data['currentDate-' . $i] : false;
        $field_required = isset($data['required-' . $i]) ? $data['required-' . $i] : false;
        $field_unique = isset($data['unique-' . $i]) ? $data['unique-' . $i] : false;
        $field_long_string = isset($data["longString-$i"]) ? true : false;
        $field_description = isset($data["description-$i"]) ? $data["description-$i"] : false;
        if ( isset( $data["default-$i"] ) && $data["default-$i"] !== "" ) {
            $field_default = $data["default-$i"];
            if ( $field_default === 'true' ) $field_default = true; // cant get AJAX to send as boolean
        } else {
            $field_default = false;
        }


        // validate field name
        $check = validate_name( $field_name, $fields );
        if ( $check !== true ) {
            echo $check;
            return;
        }

        // ensure default field matches field type
        if ( $field_default && count( $field_default) > 0 && is_string( $field_default ) ) {
            $check = validate_field_value( $field_type, $field_default );
            if ( $check !== true ) {
                echo $check;
                return;
            }
        }

        $fields[] = $field_name;


        // date field (as opposed to datetime) 
        // type cannot have default current_date (per SQL),
        // so we change the type to timestamp
        // and leave a note in the comment field
        $comment = false;
        $is_fk = false;
        if ( $field_type == 'date' ) {
            $field_type = 'datetime';
            $comment =' COMMENT \'{"column_format": "date"}\'';
        } elseif ($field_type == 'fk') { // if FK, set type the same as reference
            $field_default = false; // foreign key cannot have a default value
            $is_fk = true;

            $fk = explode('.', $data['foreignKey-' . $i]); // table_name.col of foreign key
            $fk_table = $fk[0];
            $fk_col = $fk[1];
            $field_class = $db->get_field($fk_table, $fk_col);

            if ($field_class) {
                $field_type = $field_class->get_type();
            } else {
                if (DEBUG) {
                    echo json_encode(array("msg"=>"There was an error, please try again.", "status"=>false, "log"=>array($fk_table, $fk_col, $field_class), "hide" => false));
                } else {
                    echo json_encode(array("msg"=>"There was an error, please try again.", "status"=>false, "hide" => false));
                }
                return;
            }
        }


        // set field type
        $sql_str = ''; // sql statement for this field, appended to sql_fields
        if ($field_type == 'int') {
            $sql_str = " `$field_name` int(32)";
        } else if ($field_type == 'varchar') {
            // limit varchar length to 255 unless users specifies long version
            // a unique field will create an index which is limited to 767 bytes (255 * 3 if utf8) 
            $sql_str = "`$field_name` varchar(255)";
            if ( $field_long_string ) { 
                $sql_str = "`$field_name` varchar(4096)";
            }
        } else {
            $sql_str = "`$field_name` $field_type";
        }
        $history_fields[] = $sql_str; // only need field name and type for history table

        // set NOT NULL if required
        if ( $field_required) $sql_str .= " NOT NULL";

        // set default, will be executed as a prepared statement
        if ( $field_default)  {
            if ( $field_type == 'datetime' ) {
                $sql_str .= " DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            } else {
                $bindings[":default"] = $field_default;
                $sql_str .= " DEFAULT :default"; // binding
            }
        }

        // set unique
        if ( $field_unique ) $sql_str .= " UNIQUE";
  
        // add comment if it exists
        if ($comment) $sql_str .= $comment;

        // add index if not long string and not unique
        if ( $field_long_string == false && $field_unique == false ) $sql_str .= sprintf(", INDEX `%s_IX_%s` (`$field_name`)", $field_name, substr(md5(rand()), 0, 4));

        // if FK type was requested, add the constraint
        if ($is_fk) $sql_str .= sprintf(", FOREIGN KEY fk_%s_%s(`%s`) REFERENCES `%s`(`%s`) ON DELETE RESTRICT ON UPDATE CASCADE", substr(md5(rand()), 0, 4), $field_name, $field_name, $fk_table, $fk_col);

        // combine sql for field
        $sql_fields[] = $sql_str;
 
    }

    // put together the sql statment
    $_UID = "`_UID` int(11) AUTO_INCREMENT COMMENT '{\"column_format\": \"hidden\"}', PRIMARY KEY (`_UID`)";
    $_UID_fk = "`_UID_fk` int(11) COMMENT '{\"column_format\": \"hidden\"}', INDEX `_UID_fk_IX` (`_UID_fk`)";
    $user = "`User` varchar(128)";
    $timestamp = "`Timestamp` timestamp DEFAULT CURRENT_TIMESTAMP";
    $action = "`Action` varchar(128)";
    $fk = "CONSTRAINT `_UID_fk_FK_" . substr(md5(rand()), 0, 4) . "` FOREIGN KEY (`_UID_fk`) REFERENCES `$table` (`_UID`) ON DELETE RESTRICT ON UPDATE CASCADE";

    $sql_table = sprintf("CREATE TABLE `%s` (%s, %s);", $table, $_UID, implode( ', ', $sql_fields ) );
    $sql_table_history = sprintf("CREATE TABLE `%s_history` (%s, %s, %s, %s, %s, %s, %s)", $table, $_UID, $_UID_fk, $user, $timestamp, $action, implode( ', ', $sql_fields ), $fk );

    // generate two statements since PDO won't do both as one and error check properly
    $db_conn = get_db_conn()->pdo;
    $stmt_table = bind_pdo( $bindings, $db_conn->prepare( $sql_table ) );
    $stmt_table_history = bind_pdo( $bindings, $db_conn->prepare( $sql_table_history ) );


    // Execute
    if ( $stmt_table->execute() !== false && $stmt_table_history->execute() !== false ) {
        refresh_db_setup(); // update DB class

        // XXX hard coded links
        if ( DEBUG ) {
            echo json_encode(array("msg" => "Table <a href='/chickenkitchen/?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true, "sql" => $sql_table ));
        } else {
            echo json_encode(array("msg" => "Table <a href='/chickenkitchen/?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true ));
        }

    } else { // if error

        // drop table because it was properly generated but the history version wasn't
        if ( $stmt_table->errorInfo()[2] == NULL ) {
            $db_conn->exec("DROP TABLE `$table`");
        }

        if ( DEBUG ) {
            echo json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => implode(' - ', $bindings), 'sql' => $sql_table . $sql_table_history ));
        } else {
            echo json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

    return;



}







/**
 * Bind the PDO sql statment and return it
 *
 * @param assoc. array $bindings [field, field_val]
 *        PDO statement $stmt
 *
 * @return bound PDO statement
 *
*/
function bind_pdo($bindings, $stmt) {

    if ( !empty( $bindings ) ) {
        foreach ($bindings as $field_name => $field_val) {
            $pdo_type = PDO::PARAM_STR;
            if ( is_numeric( $field_val) ) { // XXX not working
                $field_val = intval( $field_val );
                $pdo_type = PDO::PARAM_INT;
            }
            $stmt->bindParam($field_name, $field_val, $pdo_type);
        }
    }

    return $stmt;

}








/**
 * Will validate if the field value matches the
 * allowed characters for the field type. If so,
 * true is returned, otherwise an error message
 * is returned.
 *
 * @param $field_type str type of field (int,
 *        varchar, float, date, datetime)
 *        $field_val str value of field
 *
 * @return true if no error, error message if
 *         error
*/
function validate_field_value($field_type, $field_val) {

    if ( $field_type == 'int' && !preg_match('/^[0-9]+$/i', $field_val)) {
        return json_encode(array("msg" => "Only numbers are allowed as a default value if selecting an integer type field; please adjust the default value <code>$field_val</code>.", "status" => false, "hide" => false));
    } else if ( $field_type == 'varchar' && !preg_match('/^[a-z0-9_~\-\'\"!@#\$%\^&\*\(\)\. ]+$/i', $field_val)) {
        return json_encode(array("msg" => "Only alphanumeric and special characters are allowed as a default value if selecting an string type field; please adjust the default value <code>$field_val</code>.", "status" => false, "hide" => false));
    } else if ( $field_type == 'float' && !preg_match('/^[0-9\.]+$/i', $field_val)) {
        return json_encode(array("msg" => "Only numbers are allowed as a default value if selecting a float type field; please adjust the default value <code>$field_val</code>.", "status" => false, "hide" => false));
    } else if ( $field_type == 'date' && !preg_match('/(\d{4})-(\d{2})-(\d{2})/', $field_val)) {
        return json_encode(array("msg" => "Default must be formatted as <code>YYYY-MM-DD</code> if selecting a date type field; please adjust the default value <code>$field_val</code>.", "status" => false, "hide" => false));
    } else if ( $field_type == 'datetime' && !preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/', $field_val)) {
        return json_encode(array("msg" => "Default must be formatted as <code>YYYY-MM-DD hh:mm:ss</code> if selecting a datetime type field; please adjust the default value <code>$field_val</code>.", "status" => false, "hide" => false));
    }
    return true;

}


/**
 * Ensure table/field name is valid by checking:
 * - it doesn't already exist
 * - matches allowed chars [a-z0-9\-_ ]
 * - less than 64 chars long
 * - not empty
 *
 * @param str $name name of table
 *        array $names list of current
 *        names not allowed (for uniqueness
 *        check)
 *
 * @return true if table name ok, error
 *         message otherwise
*/
function validate_name( $name, $names ) {

    // table name must not already exist
    if ( in_array( $name, $names ) ) {
        return json_encode(array("msg" => "Table name <code>$name</code> already exists, please choose another.", "status" => false, "hide" => false)); 
    }

    // ensure table name is only allowed letters
    if ( !preg_match( '/^[a-z0-9\-_ ]+$/i', $name ) ) {
        return json_encode(array("msg" => "Table name may only include letters, numbers, hypens, spaces and underscores, please choose another.", "status" => false, "hide" => false)); 
    }

    // table name can only be max 64 chars
    if ( strlen( $name ) > 64 ) {
        return json_encode(array("msg" => "Table name <code>$name</code> is too long, please choose a shorter name.", "status" => false, "hide" => false)); 
    }
    if ( empty( $name ) ) {
        return json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
    }

    return true;

}







/**
 *
 * Function called by AJAX when user attempts to delete table
 * Will delete the specified table, both the standard and the
 * history counterpart.
 *
 * @param $table_name str - table name to be deleted
 *
 * @return void
 * 
*/
function delete_table_from_db( $table_name ) {

    $db = get_db_setup();
    $data_tables = $db->get_data_tables();
    $table_name_history = $table_name . '_history';


    if ( !isset( $table_name ) ) {
        echo json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
        return;
    }

    if ( !in_array( $table_name, $db->get_data_tables() ) ) {
        echo json_encode(array("msg" => "Table does not exist.", "status" => false, "hide" => false)); 
        return;
    } 

    if ( !in_array( $table_name_history, $db->get_history_tables() ) ) {
        echo json_encode(array("msg" => "History table does not exist.", "status" => false, "hide" => false)); 
        return;
    } 

    // check if table has a key that is referenced as a PK in another table
    // if so, the ref table must be deleted first
    $refs = $db->get_data_ref( $table_name );

    if ($refs) {
        $msg = "This table is referenced by:<ul>";

        foreach($refs as $ref) {
            $ref_table = explode('.',$ref)[0];
            $ref_field = explode('.',$ref)[1];
            if ( in_array( $ref_table, $data_tables ) ) { // only alert about references to data tables (instead of history tables)
                $msg .= "<li>field <code>$ref_field</code> in table <code>$ref_table</code></li>";
            }

        }

        $msg .="</ul><br>You must delete those field(s)/table(s) first, or remove the foreign key on this table before deleting this table.";
        echo json_encode(array("msg"=>$msg, "status"=>false, "hide" => false));
        return;
    } 

    // table is safe to delete
    // delete history version first since it has a FK
    $db_conn = get_db_conn();
    $q1 = $db_conn->query("DROP TABLE `$table_name_history`");
    $q2 = $db_conn->query("DROP TABLE `$table_name`");

    if ( count( $q1 ) == 1 && count( $q2 ) == 1 ) { // success
        echo json_encode(array("msg" => "The table <code>$table_name</code> was properly deleted.", "status" => true, "hide" => true));
        refresh_db_setup();
        return;
    } else {
        if ( DEBUG ) {
            echo json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false, "q1" => $q1, "q2" => $q2 ));
            return;
        } else {
            echo json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
            return;
        }
    }










}







?>
