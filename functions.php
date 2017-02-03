<?php

/** 
 *
 * Initialize a new PDO database connection.
 *
 * @param void
 * 
 * @return PDO object
 *
*/

function get_db_conn() {
    
    require_once "config/db.php"; // load DB variables
    //require_once "lib/Medoo/medoo.php"; // SQL library

    // Initialize connection
    try {

        $dsn = sprintf('mysql:dbname=%s;host=%s;charset=UTF8', DB_NAME, DB_HOST);
        $conn = new PDO($dsn, DB_USER, DB_PASS);

        // set the PDO error mode to silent
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        return $conn;

    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }


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
 * Function will generate all the HTML and JS needed for
 * for viewing the queried data in table format.  It will
 * also set the necesarry JS variables needed for use with
 * databales JS library.
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
            var hidden = <?php echo json_encode( $hidden ); ?>;
            var pk = <?php echo json_encode( $pk ); ?>;
            var hasHistory = <?php echo json_encode( $has_history ); ?>;
            var pkHist = <?php echo json_encode( $pk_hist ); ?>;
            getDBdata(table, pk, columns, null, hidden, null, hasHistory); // function will populate table and hidden any columns needed
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

        if ( $fks !== false && isset($ref) ) {
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
 * Function handles the case when a user edits the data
 * of an item. If no change was made to the data, the 
 * user is told they have to change something. Otherwise
 * the data is validated to ensure it meets the requirements
 * set by each field.  It is then updated and an account of
 * the change is added to the history table with the new data
 * and the action "Manually Edited".
 * 
 * @params (assoc arr) $ajax_data with keys
 * pk_id - value of PK field being edited
 * table - name of table being edited
 * pk - name of PK field (_UID)
 * original_row - assoc arr of original unedited data [field => value]
 * dat - assoc arr of edited data [field => value]
 *
 * @return - error message for use with showMsg()
 *
*/
function edit_item_in_db( $ajax_data ) {

    // get some vars
    $pk_id = $ajax_data['pk_id'];
    $table = $ajax_data['table'];
    $pk = $ajax_data['pk'];
    $original_dat = $ajax_data['original_row'];
    $sent_dat = $ajax_data['dat'];
    $update_dat = array_diff_assoc( $sent_dat, $original_dat );  // assoc arr of data different than original

    // check if data is different than original
    if ( $sent_dat === $original_dat ) {
        return json_encode(array("msg" => 'The edit is not any different than the current data stored for this item, please ensure you\'ve edited at least one field.', "status" => false, "hide" => false));
    }


    // check if new data is valid to fit
    $validate = validate_row( $update_dat, $table, True );
    if ($validate !== true ) return $validate;


    // update row
    $bindings = [];
    $db_conn = get_db_conn();
    foreach ( $update_dat as $field_name => $field_val ) {
        $bindings[] = "`$field_name`=:" . $field_name;
    }
    $sql = sprintf("UPDATE `$table` SET %s WHERE `$pk`='$pk_id'", implode(',', $bindings));
    $stmt_table = bind_pdo( $update_dat, $db_conn->prepare( $sql ) );
    $status = $stmt_table->execute();


    if ( $status === false ) { // error
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => $dat, 'sql' => $sql));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    } else {
        // enter data for history table
        add_item_to_history_table( $table . "_history", USER, $pk_id, "Manually edited", $sent_dat, $db_conn );

        if ( DEBUG ) {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true, "sql" => $sql, "bind" => $update_dat ));
        } else {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true ));
        }
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
 * @return - error message for use with showMsg()
*/
function add_item_to_db( $ajax_data ) {

    // init
    $table = $ajax_data['table'];
    $dat = $ajax_data['dat'];
    $pk = $ajax_data['pk'];
    $db = get_db_setup();

    // check that we have everything
    if ( !isset( $table ) || empty( $table) || !isset( $pk ) || empty( $pk ) ) {
        return json_encode(array("msg" => 'There was an error, please try again.', "status" => false, "hide" => false));
    }

    // validate row
    $validate = validate_row( $dat, $table );
    if ($validate !== true ) {
        return $validate;
    }

    
    // generate SQL for data
    $table_cols = implode('`,`', array_keys( $dat ) );
    $table_vals = implode(',:', array_keys( $dat ) );
    $sql = sprintf( "INSERT INTO `%s` (`%s`) VALUES (:%s)", $table, $table_cols, $table_vals);
    $db_conn = get_db_conn();
    $stmt_table = bind_pdo( $dat, $db_conn->prepare( $sql ) );

    // Execute
    $status = $stmt_table->execute();

    if ( $status === false ) { // error
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => $dat, 'sql' => $sql));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    } else {
        // enter data for history table
        $_UID_fk = $db_conn->query( "SELECT $pk FROM `$table` ORDER BY $pk DESC LIMIT 1" )->fetch()[$pk]; // UID of last element added
        add_item_to_history_table( $table . "_history", USER, $_UID_fk, "Manually added", $dat, $db_conn );

        if ( DEBUG ) {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true, "sql" => $sql, "bind" => $dat ));
        } else {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true ));
        }
    }

}


/**
 * Handle AJAX request for batch add/edit/delete
 *
 * @params 
 * (assoc arr) $ajax_data with keys:
 *  - (str) table - name of table acting on
 *  - (str) batchType - type of batch request will be
 *    one of batchEdit, batchAdd, batchDelete
 * (assoc arr) $files $_FILES content
 *
 * @return - error message for use with showMsg()
 *
*/
function batch_update_db( $ajax_data, $files ) {

    // init
    $type = $ajax_data['batchType'];
    $table = $ajax_data['table'];
    $db = get_db_setup();

    // check that we have everything
    if ( !isset( $table ) || empty( $table) ) {
        return json_encode(array("msg" => 'There was an error, please try again.', "status" => false, "hide" => false));
    }

    if ( $type == 'batchAdd') {

        return batch_add($db, $table, $files);

    } else if ( $type == 'batchEdit') {

        // need to handle concern of uniquely identifying row
        // in cases where table has to PK set by user

    } else if ( $type == 'batchDelete') {

        // need to handle concern of uniquely identifying row
        // in cases where table has to PK set by user

    }

}


/**
 * Execute batch add
 * 
 * @params:
 * (db class) $db - db setup
 * (str) $table - name of table being acted on
 * (obj) $files - $_FILES['batchFile']
 * 
 * @returns:
 * json encoded error message for use with showMsg()
 * 
*/
function batch_add($db, $table, $files ) {

    // get list of columns that should be checked:
    // not null, FK, unique
    $required_fields = $db->get_required_fields( $table );
    $fk_vals = $db->get_fk_vals( $table );
    $unique_vals = $db->get_unique_vals( $table );
    $visible_fields = $db->get_visible_fields( $table );


    // check if valid file type supplied
    if ( mime_content_type( $files['tmp_name'] ) != "text/plain" ) {
        if (DEBUG) {
            return json_encode(array("msg" => 'You must upload a plain text file with some sort of delimiter.', "status" => false, "hide" => false, "log" => mime_content_type( $files['tmp_name'] ) ));
        } else {
            return json_encode(array("msg" => 'You must upload a plain text file with some sort of delimiter.', "status" => false, "hide" => false));
        }
    }



    // figure out delimiter
    $delimiters = array(',','\t',';','|',':');
    $delim = getFileDelimiter($files['tmp_name'], 5, $delimiters); // figure out delimiter

    if ( !in_array( $delim, $delimiters ) ) {
        return json_encode(array("msg" => 'You must use one of the following delimiters: <code>' . implode('</code>,<code>', $delimiters) . "</code>" , "status" => false, "hide" => false));
    }



    // loop through file and validate each row
    if (($handle = fopen( $files['tmp_name'], "r")) !== FALSE) {
        $count = 0;
        $bind_vals = [];  // validated row values to batch insert SQL
        $bind_labels = [];
        while ( ( $line = fgetcsv($handle, 0, $delim ) ) !== FALSE ) {
            
            // check header for all required fields
            if ( $count == 0 ) {
                $header = $line;

                if ( count( $required_fields ) > 0 && array_intersect( $required_fields, $header ) != $required_fields ) {
                    return json_encode(array("msg" => 'Ensure you\'ve included all the required fields for this table including: <code>' . implode('</code>,<code>', $required_fields) . '</code>', "status" => false, "hide" => false));
                }

                $bind_label_template = ':' . implode('_num, :', $header) . '_num'; // this will get updated for each row by replacing _num with $count - used to speed things up
            } else {

                // validate file row data
                $dat = array_combine( $header, $line );
                $validate = validate_row( $dat, $table, False, $visible_fields, $required_fields, $fk_vals, $unique_vals );
                if ($validate !== true ) {
                    return $validate;
                }

                // update unique_vals with current validated file row
                foreach( $unique_vals as $field => $vals ) {
                    $vals[] = $dat[$field];
                    $unique_vals[$field] = $vals;
                }

                // if valid, append to batch form of SQL add
                $bind_vals[] = $line;
                $bind_labels[] = str_replace( '_num', $count - 1, $bind_label_template );
            }

            $count += 1;
        }
    }

    // generate SQL for data
    $stmt = bind_pdo_batch( $table, $header, $bind_vals, $bind_labels );
    $status = $stmt->execute();

    if ( $status === false ) { // error
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt->errorInfo()), "status" => false, "hide" => false));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    } else {
        // enter data for history table
        $stmt = bind_pdo_batch( $table, $header, $bind_vals, $bind_labels, 'User', 'Batch added' );
        $status = $stmt->execute();

        if ( DEBUG ) {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true, "log" => $stmt->errorInfo(), 'stat' => $stmt ) );
        } else {
            return json_encode(array("msg" => "Item properly added to table", "status" => true, "hide" => true ));
        }
    }

}



/**
 * Automatically find out what type of delimiter is
 * used in the file.
 *
 * http://stackoverflow.com/a/23608388/1153897
 *
 * @params
 * (str) $file - name/location of file to check
 * (int) $checkLines - number of lines to check in file
 * (arr) $delimeters - array of allowable delimiters
 *
 * @returns - (str) delimiter used in file
 *
 *
*/
function getFileDelimiter($file, $checkLines = 2, $delimiters){
        $file = new SplFileObject($file);
        $results = array();
        $i = 0;
         while($file->valid() && $i <= $checkLines){
            $line = $file->fgets();
            foreach ($delimiters as $delimiter){
                $regExp = '/['.$delimiter.']/';
                $fields = preg_split($regExp, $line);
                if(count($fields) > 1){
                    if(!empty($results[$delimiter])){
                        $results[$delimiter]++;
                    } else {
                        $results[$delimiter] = 1;
                    }   
                }
            }
           $i++;
        }
        $results = array_keys($results, max($results));
        return $results[0];
    }







/**
 * Function will delete a row from a given datatable
 * and will update its history counter part with a 
 * "Manually delete" event (with empty row content)
 *
 * @params (assoc arr) $ajax_data with keys:
 * (str) pk_id - id of primary key being deleted
 * (str) table - name of table deleting from
 * (str) pk - name of primary key column
 *
 * @return - error message for use with showMsg()
 *
*/
function delete_item_from_db( $ajax_data ) {

    // get some vars
    $db = get_db_setup();
    $_UID = $ajax_data['pk_id'];
    $table = $ajax_data['table'];
    $pk = $ajax_data['pk'];

    // check that we have everything
    if ( !isset( $table ) || empty( $table) || !isset( $pk ) || empty( $pk ) || !isset( $_UID ) || empty( $_UID ) ) {
        return json_encode(array("msg" => 'There was an error, please try again.', "status" => false, "hide" => false));
    }

    // check if, when deleting a row, it's referenced by an FK
    // if it is, then the item can't be deleted unless the 
    // parent table ID doesn't have that value
    $tmp = $db->get_data_ref( $table ); // [parent field => [child table, fk field]]
    if ( !empty( $tmp ) && $tmp !== false ) {
        foreach($tmp as $parent_field => $child) {
            $child_table = $child[0];
            $fk_field = $child[1];

            // get parent value trying to be deleted
            $id = get_db_conn()->query("SELECT $parent_field FROM $table WHERE `_UID` = '$_UID' limit 1")->fetch()[$parent_field];

            // check if child table has a pk field equal to $id
            if (table_has_value($child_table, $fk_field, $id)) {
                $msg = "The item <code>$id</code> that you are trying to delete is referenced as a foreign key in the table <code><a href='?table=$child_table'>$child_table</a></code>; you must remove all the entries in that child table first, before deleting this parent entry.";
                $ret = array("msg" => $msg, "status" => false, "hide" => false);
                return json_encode($ret);
            }
        }
    }

    // delete item and update history
    $sql = "DELETE FROM `$table` WHERE `_UID` = '$_UID'";
    $db_conn = get_db_conn();
    add_item_to_history_table( $table . "_history", USER, $_UID, "Manually deleted", [], $db_conn );
    $stmt = $db_conn->exec( $sql );

    if ( $stmt === false ) { // if error
        if ( DEBUG ) {
            return json_encode(array("msg"=>"There was an error, please try again", "status"=>false, "log" => $sql, "hide" => false));
        } else {
            return json_encode(array("msg"=>"There was an error, please try again", "status"=>false, "hide" => false));
        }
        return;
    } else {
        return json_encode(array("msg"=>"Item was properly deleted.", "status"=>true, "hide" => true));
    }

}









/* Check if table has value

Function useful for checking if a table has a certain value;
used when trying to remove a field that is referenced by
a FK.

Parameters:
===========
- $table_name : str
               table in which to check for results
- $field_name : str
               field to query
- $id : str
        value in $field_name to query
*/
function table_has_value($table_name, $field_name, $id) {

    $result = get_db_conn()->query("SELECT $field_name FROM $table_name WHERE $field_name = '$id'");

    return $result;

    if ( !empty( $result ) ) {
        return $result;
    } else {
        return false;
    }
}








/**
 * Add entry to history table after events
 * such as an element being added, edited
 * or deleted from a table.
 *
 * @param 
 * (str) $table - table name to add to
 * (str) $user - user name to assoc change with
 * (int) $fk - FK UID of element associated
 * with change
 * (str) $action - note about what was
 *  being done e.g. "Item deleted manually"
 * (assoc. array) $field_data - data being entered into history
 *  table in format [column name => col value]. this will be used
 *  to automatically generate a prepared statement. If array is
 *  empty, default history table columns (User, Action) will be
 *  set - this is used when item is deleted.
 *
 * (PDO obj) $db_conn - connection to DB
 *
 * @return False on failure
 *
*/
function add_item_to_history_table( $table, $user, $fk, $action, $field_data, $db_conn ) {

    $table_columns = array_keys( $field_data );

    if ( !empty( $field_data ) ) {
        $sql_history = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`, `%s`) VALUES ('$fk', '$user', '$action', :%s)", $table, implode("`,`", $table_columns), implode(",:", $table_columns ) );
        $stmt_table_history = bind_pdo( $field_data, $db_conn->prepare( $sql_history ) );
        $status = $stmt_table_history->execute();
    } else { // if no field data is sent, just update the action and user
        $sql_history = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`) VALUES ('$fk', '$user', '$action')", $table );
        $status = $db_conn->exec( $sql_history );
    }

    return $status;
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

 * @return - error message for use with showMsg()
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
        return json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
    }

    // validate table name
    $check = validate_name( $table, $db->get_all_tables() );
    if ( $check !== true ) {
        return $check;
    }

    // check field names for errors
    for( $i = 1; $i<=$field_num; $i++ ) {

        $comment = null; // clear it
        $comment['name'] = $data['name-' . $i];
        $field_name = str_replace(' ', '_', $data['name-' . $i]); // replace spaces with _ to make parepared statements easier
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
            return $check;
        }

        // ensure default field matches field type
        if ( $field_default && count( $field_default) > 0 && is_string( $field_default ) ) {
            $check = validate_field_value( $field_type, $field_default );
            if ( $check !== true ) {
                return $check;
            }
        }

        $fields[] = $field_name;


        // date field (as opposed to datetime) 
        // type cannot have default current_date (per SQL),
        // so we change the type to timestamp
        // and leave a note in the comment field
        $is_fk = false;
        if ( $field_type == 'date' ) {
            $field_type = 'datetime';
            $comment['column_format'] = 'date';
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
                    return json_encode(array("msg"=>"There was an error, please try again.", "status"=>false, "log"=>array($fk_table, $fk_col, $field_class), "hide" => false));
                } else {
                    return json_encode(array("msg"=>"There was an error, please try again.", "status"=>false, "hide" => false));
                }
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
                $bindings["default"] = $field_default;
                $sql_str .= " DEFAULT :default"; // binding
            }
        }

        // set unique
        if ( $field_unique ) $sql_str .= " UNIQUE";
  
        // add comment
        $sql_str .= " COMMENT '" . json_encode($comment) . "'";

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

    $sql_table = sprintf("CREATE TABLE `%s` (%s, %s);", $table, $_UID, implode( ', ', $sql_fields ) );

    // we are only interested in field names and types for history table    
    $sql_table_history = sprintf("CREATE TABLE `%s_history` (%s, %s, %s, %s, %s, %s)", $table, $_UID, $_UID_fk, $user, $timestamp, $action, implode( ', ', $history_fields ) );

    // generate two statements since PDO won't do both as one and error check properly
    $db_conn = get_db_conn();
    $stmt_table = bind_pdo( $bindings, $db_conn->prepare( $sql_table ) );
    $stmt_table_history = bind_pdo( $bindings, $db_conn->prepare( $sql_table_history ) );


    // Execute
    if ( $stmt_table->execute() !== false && $stmt_table_history->execute() !== false ) {
        refresh_db_setup(); // update DB class

        if ( DEBUG ) {
            return json_encode(array("msg" => "Table <a href='?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true, "sql" => $sql_table ));
        } else {
            return json_encode(array("msg" => "Table <a href='?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true ));
        }

    } else { // if error

        // drop table because it was properly generated but the history version wasn't
        if ( $stmt_table->errorInfo()[2] == NULL ) {
            $db_conn->exec("DROP TABLE `$table`");
        }

        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => implode(' - ', $bindings), 'sql' => $sql_table . $sql_table_history ));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

}





/**
 * Bind prepared statement in batch form
 *
 * @params:
 * (str) $table - table being affected, in case
 *  of history batch, this should be the root table
 *  name (e.g. samples, not samples_history)
 * (arr.) $header - field names in table
 * (arr. of arr.) $bind_vals - each outer array
 *  is an array of field values being bound
 * (arr.) $bind_labels - array of field names formated
 * for binding e.g. [":field1, :field2", ...]
 * The following are optional and designate a batch edit
 * (str) $user - name of user doing batch
 * (str) $action - type of batch action
 *
 *
 * @returns:
 * prepared PDO statement ready for execution
 *
*/
function bind_pdo_batch( $table, $header, $bind_vals, $bind_labels, $user=NULL, $action=NULL ) {

    $db_conn = get_db_conn();

    if ( isset( $user ) && isset( $action ) ) { // batch history
        $fk = intval( $db_conn->query( "SELECT _UID FROM `$table` ORDER BY _UID DESC LIMIT 1" )->fetch()['_UID'] ); // UID of last element added
        $sql = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`, `%s`) VALUES ", $table . '_history', implode( "`,`", $header ));

        // we need to manaully construct the bound values a bit since we have the _UID_pk
        foreach ( $bind_labels as $i => $label ) {
            $parts[] = "(" . ($fk + $i) . ", '$user', '$action', $label)";
        }
        $sql .= implode(", ", $parts);
    } else {
        $sql = "INSERT INTO `$table` (`" . implode( "`,`", $header ) . "`) VALUES ";
        $sql .= "(" . implode( '), (', $bind_labels) . ")";
    }

    $stmt = $db_conn->prepare( $sql );


    foreach( $bind_vals as $row_count => $vals ) {

        foreach( $vals as $i => $field_val ) {

            $pdo_type = PDO::PARAM_STR;

            if ( is_numeric( $field_val ) && is_int( $field_val ) ) { // float case is handled as str
                $field_val = intfield_val( $field_val );
                $pdo_type = PDO::PARAM_INT;
            }
        
            $stmt->bindValue(":" . $header[$i] . $row_count, $field_val, $pdo_type);

        }
    }

    return $stmt;
}





/**
 * Bind the PDO sql statment and return it
 *
 * @param 
 * (assoc. array) $bindings - table data being
 *  bound in parepare statment with form
 *  [ table_column => column value ]
 * (pdo) $stmt - prepared PDO statement for binding
 *
 * @return bound PDO statement
 *
*/
function bind_pdo($bindings, $stmt) {

    if ( !empty( $bindings ) ) {
        foreach ($bindings as $field_name => $field_val) {

            $pdo_type = PDO::PARAM_STR;

            if ( is_numeric( $field_val) && is_int( $field_val ) ) { // float case is handled as str
                $field_val = intval( $field_val );
                $pdo_type = PDO::PARAM_INT;
            }
         
            $stmt->bindValue(":$field_name", $field_val, $pdo_type);
        }
    }

    return $stmt;

}



/**
 * Validate row values by checking that:
 * - each field value is valid for the field type
 *   (e.g. str for str field, int for int field)
 * - value is unique if field is unique type
 * - value not null if field is required
 * - value is in ref field if field is a FK
 *
 * @param
 * (assoc. arr.) $dat row data being validated with
 *  table fields as keys and cell value as value
 * (str) $table - table name for row
 * (bool) [optional] $edit - in the case of a row being edited
 * $dat will only be the changes to the row which
 * may not include a field that is required, in this
 * case the 'field required' check isn't performed.
 *
 * The following fields are optional and used when validing
 * a row in a batch fashion:
 * (arr.) $required_fields - list of fields in table with NOT NULL
 * (assoc. arr.) $fk_vals - keys are fields that have an FK constraint
 *  and the value is an array of values that FK can have
 * (assoc. arr.) $unique_vals - keys are fields that have a unique constraint
 *  and the value is an array of unique values currently in the field
 *
 *
 *
 * @return json encoded error message, otherwise true
 *
*/
function validate_row( $dat, $table, $edit=False, $visible_fields=False, $required_fields=False, $fk_vals=False, $unique_vals=False ) {

    $db = get_db_setup();
    if ($visible_fields === False) $visible_fields = $db->get_visible_fields( $table );
    if ($required_fields === False) $required_fields = $db->get_required_fields( $table );
    if ($fk_vals === False) $fk_vals = $db->get_fk_vals( $table );
    if ($unique_vals === False) $unique_vals = $db->get_unique_vals( $table );
    $sent_fields = array_keys( $dat );

    // go through each field of the table and check 
    // that valid information was sent for it
    foreach ( $visible_fields as $field_name ) {

        $field_required = in_array( $field_name, $required_fields );
        if ( in_array( $field_name, $sent_fields ) ) {

            $field_type = $db->get_field( $table, $field_name );
            $field_val = $dat[ $field_name ];

            if ( !empty( $field_val ) && $field_val !== '' ) { // skip empty fields

                // validate field value in proper format
                $check = validate_field_value( $field_type, $field_val );
                if ( $check !== true ) {
                    return $check;
                }

                // validate field is unique
                if ( in_array( $field_name, array_keys( $unique_vals ) ) && in_array( $field_val, $unique_vals[$field_name] ) ) {
                    return json_encode(array("msg" => "The item value <code>$field_val</code> you are trying to add already exists in the unique field <code>$field_name</code>, please choose another.", "status" => false, "hide" => false));
                }

                // validate FK field has proper value
                if ( in_array( $field_name, array_keys( $fk_vals ) ) && !in_array( $field_val, $fk_vals[$field_name] ) ) {
                    return json_encode(array("msg" => "The item value <code>$field_val</code> must be one of the following: <code>" . implode('</code>,<code>', $fk_vals[$field_name] ) . "</code>, please choose another.", "status" => false, "hide" => false));
                }

            } else if ( $field_required ) {

                return json_encode( array("msg" => "Please ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false) );

            }

        } else if ( $field_required && $edit ) {

            return json_encode( array("msg" => "Please ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false) );

        }

    }

    return true;
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
 * @return - error message to be used with showMsg()
 * 
*/
function delete_table_from_db( $table_name ) {

    $db = get_db_setup();
    $data_tables = $db->get_data_tables();
    $table_name_history = $table_name . '_history';


    if ( !isset( $table_name ) ) {
        return json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
    }

    if ( !in_array( $table_name, $db->get_data_tables() ) ) {
        return json_encode(array("msg" => "Table does not exist.", "status" => false, "hide" => false)); 
    } 

    if ( !in_array( $table_name_history, $db->get_history_tables() ) ) {
        return json_encode(array("msg" => "History table does not exist.", "status" => false, "hide" => false)); 
    } 

    // check if table has a key that is referenced as a PK in another table
    // if so, the ref table must be deleted first
    $refs = $db->get_data_ref( $table_name );

    if ($refs) {
        $msg = "This table is referenced by:<ul>";

        foreach($refs as $ref) {
            $ref_table = $ref[0];
            $ref_field = $ref[1];
            if ( in_array( $ref_table, $data_tables ) ) { // only alert about references to data tables (instead of history tables)
                $msg .= "<li>field <code>$ref_field</code> in table <code>$ref_table</code></li>";
            }

        }

        $msg .="</ul><br>You must delete those field(s)/table(s) first, or remove the foreign key on this table before deleting this table.";
        return json_encode(array("msg"=>$msg, "status"=>false, "hide" => false));
    } 

    // table is safe to delete
    // delete history version first since it has a FK
    $db_conn = get_db_conn();
    $q1 = $db_conn->query("DROP TABLE `$table_name_history`");
    $q2 = $db_conn->query("DROP TABLE `$table_name`");

    if ( count( $q1 ) == 1 && count( $q2 ) == 1 ) { // success
        return json_encode(array("msg" => "The table <code>$table_name</code> was properly deleted.", "status" => true, "hide" => true));
        refresh_db_setup();
    } else {
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false, "q1" => $q1, "q2" => $q2 ));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }
}




/**
 * Generate all the HTML for the batch form
 *
 * @Param (str) $table - name of table to act on
 *
 * Batch form used when user wants to add,
 * edit or delete items in given table in
 * batch format. Will generate the input
 * form needed to do the batch editing.
 *
*/
function batch_form( $table ) { ?>

    <div> Please choose one of the radio buttons shown below and upload a plain text file that is delimited with one of the following delimiters: comma, tab, colon, semi-colon or bar (|).  </div>

    <form class="form-horizontal" onsubmit="return false;" id="batchForm">
        <label class="radio-inline">
            <input type="radio" name="batchType" id="batchAdd" value="batchAdd" onclick="radioSelect(this);"> Add
        </label>
        <label class="radio-inline">
            <input type="radio" name="batchType" id="batchEdit" value="batchEdit" onclick="radioSelect(this);"> Edit
        </label>
        <label class="radio-inline">
            <input type="radio" name="batchType" id="batchDelete" value="batchDelete" onclick="radioSelect(this);"> Archive
        </label>
        <div id="radioHelp"></div> <!-- will be filled by JS-->

        <input type="file" name="batchFile" id="batchFile" required>
        <input name="table" value="<?php echo $table ?>" style="display: none">
        <input name="action" value='batchUpdate' style="display: none">
        <button type="button" class="btn btn-warning" id="confirmEdit" onclick="batchFormSubmit(event)">Submit</button>
        <input id="submit_handle" type="submit" style="display: none"> <!-- needed for validating form -->
    </form>

    <script>
        $(function() {
            var table = '<?php echo $_GET['table']; ?>'; // needed for AJAX
            jQuery('input[id=batchAdd]').click(); // do initial click on radio so that text is shown to user
            var tmp = <?php echo get_db_setup()->asJSON( $_GET['table'] ); ?>;
            db = tmp.struct; // global - required for radioSelect function to display proper info
        })
    </script>


<?php }







?>
