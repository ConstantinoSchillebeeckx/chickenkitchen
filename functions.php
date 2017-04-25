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
    
    require "config/db.php"; // load DB variables

    // Initialize connection
    try {

        $dsn = sprintf('mysql:dbname=%s;host=%s;charset=UTF8', NAME_DB, HOST_DB);
        $conn = new PDO($dsn, USER_DB, PASS_DB);

        // set the PDO error mode to silent
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        return $conn;

    } catch(PDOException $e) {
        echo "MySQL connection failed: " . $e->getMessage();
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
 * @params void
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

        <?php foreach ( $fields as $field ) {
            $field_struct = $db->get_field($table, $field);
            $comment = $field_struct->get_comment();

            // use the field name stored in comment if it exists
            if (is_array($comment) && in_array('name', array_keys($comment))) {
                $field_name = $comment['name'];
            } else {
                $field_name = $field;
            }
            echo "<th>$field_name <span class='popover-$field fa fa-info-circle fa-lg text-muted' aria-hidden='true'></span></th>"; 
        }
        ?>

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

            // assumes variables db, and fk_vals exist
            // these are set in table_search.php
            jQuery(document).ready(function() {
                make_popover_labels( db, fk_vals );
            });

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
        $field_name = $comment['name'];
    
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
                    echo "<label class='col-sm-2 control-label'>$field_name<span class='required'>*</span> <span class='popover-$field fa fa-info-circle text-primary' aria-hidden='true' data-placement='bottom'></span></label>";
                } else {
                    echo "<label class='col-sm-2 control-label'>$field_name <span class='popover-$field fa fa-info-circle text-primary' aria-hidden='true' data-placement='bottom'></span></label>";
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
 * Function for reverting the data on an item using
 * the history table.
 *
 *
 *
 * @params (assoc arr) $ajax_data with keys
 * _UID - UID in history table with data to be reverted to
 * table - name of the table being edited, add _history
 *  for the history counterpart
 * dat - assoc arr of reverted values
 * original_row - assoc arr of previous values
 *
 *
 *
 * @return - error message for use with showMsg()
 *
*/
function revert_item( $ajax_data) {

    $db = get_db_setup();
    $table = $ajax_data['table'];
    $uid = $ajax_data['_UID'];
    $db_name = DB_NAME;
    $sent_dat = $ajax_data['dat'];
    $original_dat = $ajax_data['original_row'];


    // check if data is different than original
    if ( $sent_dat === $original_dat ) {
        return json_encode(array("msg" => 'The revert state is not any different than the current one, please choose a different one.', "status" => false, "hide" => false));
    }

    if (isset($table)) {
        
        $table_history = $table . '_history';
        $cols = []; // [SET a.col = b.col, ...]
        $fields = $db->get_visible_fields( $table );

        # generate SQL statement for reverting
        foreach ( $fields as $col ) {
            $cols[] = "a.`$col` = b.`$col`";
        }

        $sql = "UPDATE $db_name.`$table` a INNER JOIN $db_name.`$table_history` b on a._UID = b._UID_fk SET " . implode(', ', $cols) . " WHERE b._UID = $uid;";
    }

    $db_conn = get_db_conn();
    $stmt = $db_conn->exec($sql);

    if ($stmt === 1) {

        // add revert to history table
        $sql = "SELECT _UID_fk,`" . implode('`,`', $fields) . "` FROM $table_history WHERE _UID = $uid";
        $results = $db_conn->query($sql)->fetch(PDO::FETCH_ASSOC);
        $pk_id = $results['_UID_fk'];
        unset($results['_UID_fk']);
        $status = add_item_to_history_table( $table_history, $_SESSION['user_name'], $pk_id, "Manually reverted", $results, $db_conn );

        return json_encode(array("msg" => 'Item properly reverted', "status" => true, "hide" => true));
    } else {
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false, "log" => $stmt, 'sql' => $sql));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
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
        add_item_to_history_table( $table . "_history", $_SESSION['user_name'], $pk_id, "Manually edited", $sent_dat, $db_conn );

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
        add_item_to_history_table( $table . "_history", $_SESSION['user_name'], $_UID_fk, "Manually added", $dat, $db_conn );

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

        return batch_edit($db, $table, $files);

    } else if ( $type == 'batchDelete') {

        return batch_delete($db, $table, $files);

    }

}







/**
 * Execute batch update
 * 
 * In order to execute a batch update the table must
 * have a unique, required column. Otherwise we can't 
 * guarantee the proper row is being deleted.
 * 
 * Function will step through each row in the file,
 * and ensure that the data for the 'key' (the unique,
 * required column) is valid.
 *
 * NOTE: function does not check if data are being changed,
 * that is, if the same table is provided, these data will
 * be re-written to the table.
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
function batch_edit($db, $table, $files) {

    // ensure there is a unique, required column
    $unique_fields = $db->get_unique($table);
    $required_fields = $db->get_required_fields($table);
    $unique_required = array_intersect($unique_fields, $required_fields);

    if (count($unique_required)) { // table can be batch edited

        $key = $unique_required[0]; // just use the first one (if multiple present) as our unique identifying row key
        $fk_vals = $db->get_fk_vals( $table );
        $unique_vals = $db->get_unique_vals( $table, $key );
        $visible_fields = $db->get_visible_fields( $table );

        $delim = validate_uploaded_file( $files );
        if (is_array($delim)) return json_encode($delim);


        // loop through file and validate each row
        ini_set('auto_detect_line_endings',TRUE);
        if (($handle = fopen( $files['tmp_name'], "r")) !== FALSE) {
            $row = 0;
            $bind_vals = [];  // validated row values to batch insert SQL
            $sql_parts = [];
            $bind_vals_history = [];
            $bind_labels_history = [];
            $keys = []; // list of keys (even after renaming) that were updated, used to find _UID to upate history
            while ( ( $line = fgetcsv($handle, 0, $delim ) ) !== FALSE ) {

                // check header for all required fields
                if ( $row == 0 ) {
                    $header = $line;
                    if (!in_array($key, $header)) return json_encode(array("msg" => "The uploaded file must contain the field <code>$key</code> in order to do a batch edit; the following columns were provided <code>" . implode("</code>,<code>", $header) . "</code>", "status" => false, "hide" => false, 'log'=>$header, 'delim'=>$delim));
                    $bind_label_template = ':' . implode('_num, :', $header) . '_num'; // this will get updated for each row by replacing _num with $row - used to speed things up
                } else {
                    // change file row into assoc array with header as keys
                    $line = array_combine($header, $line);
            
                    // ensure something is written in key
                    if ($line[$key] == '') return json_encode(array("msg" => "The column <code>$key</code> is empty in row $row, please ensure this field has a value for each row.", "status" => false, "hide" => false, 'line'=>$line));

                    $rename = (in_array('Rename',$header) && $line['Rename'] != ''); // true if key being renamed

                    // remove current row from unique values since validate_row() will check it
                    foreach ($unique_vals as $col => $arr) { // $arr is assoc where the key is the $key column value
                        unset($arr[$line[$key]]);
                        $unique_vals[$col] = $arr;
                    }

                    $original_line = $line; // keep copy of original in case of renaming
                    if ($rename) {
                        $line[$key] = $line['Rename']; // if renaming, use the rename column
                        $keys[] = $line['Rename'];
                    } else {
                        $keys[] = $line[$key];
                    }

                    // validate each row of data
                    $validate = validate_row( $line, $table, False, $visible_fields, $required_fields, $fk_vals, $unique_vals, $row ); 
                    if ($validate !== true ) return $validate;

                    // if row is valid, update the unique vals
                    foreach( $unique_vals as $field => $vals ) {
                        $vals[$line[$key]] = $original_line[$field];
                        $unique_vals[$field] = $vals;
                    }

                    // row is valid, generate SQL
                    $tmp_parts = [];
                    foreach($original_line as $field => $val) {
                        if ($val != '' && $field != 'Rename') {
                            $tmp_parts[] = "`$field` = :$field" . "_$row";
                            $bind_vals[$field . "_$row"] = $val;
                        }
                    }
                    $sql_parts[] = "UPDATE `$table` SET " . implode(', ', $tmp_parts) . " WHERE `$key`= :key_$row";
                    $bind_vals["key" . "_$row"] = $original_line[$key];
                    $bind_vals_history[] = array_values($line);
                    $bind_labels_history[] = str_replace( '_num', $row - 1, $bind_label_template );

                    // update row data first, then rename key if needed
                    if ($rename) {
                        $sql_parts[] = "UPDATE `$table` SET `$key`=:key_rename_$row  WHERE `$key`=" . ":rename_$row";
                        $bind_vals["key_rename_$row"] = $original_line[$key];
                        $bind_vals["rename_$row"] = $original_line[$key];
                    }

                }
                $row += 1;
            }
        }
        ini_set('auto_detect_line_endings',FALSE);

        $db_conn = get_db_conn();
        $sql = implode('; ', $sql_parts);
        $stmt = bind_pdo( $bind_vals, $db_conn->prepare( $sql ) );
        $status = $stmt->execute();
        $status = True;

        if ( $status === false ) { // error
            if ( DEBUG ) {
                return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt->errorInfo()), "status" => false, "hide" => false));
            } else {
                return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
            }
        } else {
            // enter data for history table
            // first get list of _UID that were updated with batch file
            $sql = "SELECT _UID FROM $table WHERE `$key` in ('" . implode("','", $keys) . "')";
            $UID = $db_conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            $stmt = bind_pdo_batch( $table, $header, $bind_vals_history, $bind_labels_history, $_SESSION['user_name'], 'Batch edited', $UID );
            $status = $stmt->execute();

            if ( DEBUG ) {
                return json_encode(array("msg" => "Table properly edited, $row rows edited.", "status" => true, "hide" => true, "log" => implode(' - ', $stmt->errorInfo()) ) );
            } else {
                return json_encode(array("msg" => "Table properly edited, $row rows edited.", "status" => true, "hide" => true ));
            }
        }

    } else {
        return json_encode(array("msg" => 'In order to batch edit, the table must have a unique, required column.', "status" => false, "hide" => false));
    }

}




/**
 * Execute batch delete
 * 
 * Execute a batch delete of rows only after they
 * have all been properly validated.
 * 
 * In order to execute a batch delete the table must
 * either have a unique, required column, or all fields
 * must be provided. Otherwise we can't guarantee the
 * proper row is being deleted.
 * 
 * For each desired deleted row, the _UID is queried,
 * this set of PKs is then used with an IN() sql
 * statement to delete the rows as well as update the
 * history.
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
function batch_delete($db, $table, $files ) {

    $db_conn = get_db_conn();
    $pks = []; // list of pks to delete
    $visible_fields = $db->get_visible_fields( $table );

    $pk = $db->get_visible_pk( $table );
    if (is_array($pk) && count($pk) > 0) {
        $pk = $pk[0]; // use first PK if multiple available
        $all_cols_required = False;
    } else {
        $pk = '_UID';
        $all_cols_required = True;
    }

    $delim = validate_uploaded_file( $files );
    if (is_array($delim)) return json_encode($delim);

    // loop through file and validate each row
    ini_set('auto_detect_line_endings',TRUE);
    if (($handle = fopen( $files['tmp_name'], "r")) !== FALSE) {
        $row = 0;
        while ( ( $line = fgetcsv($handle, 0, $delim ) ) !== FALSE ) {
            
            // check header for all required fields
            if ( $row == 0 ) {
                $header = $line;

                if ( $all_cols_required && array_intersect( $visible_fields, $header ) != $visible_fields ) {
                    return json_encode(array("msg" => 'You must include all table fields when batch archiving including <code>' . implode('</code>,<code>', $visible_fields) . '</code> - the following fields were provided in the uploaded file: <code>' . implode('</code>,<code>', $header) . '</code>', "status" => false, "hide" => false, 'log'=>array($visible_fields, $header, array_intersect($visible_fields, $header))));
                } else if ( !$all_cols_required && !in_array($pk, $header) ) {
                    return json_encode(array("msg" => 'You must include the field <code>' . $pk . '</code> when batch archiving.', "status" => false, "hide" => false));
                }

                // setup prepared statement part for SQL statement
                if ( $all_cols_required ) {
                    $bind_parts = [];
                    foreach ($header as $column ) {
                        $bind_parts[] = "`$column` = :$column";
                    }
                    $sql = "SELECT `$pk` FROM `$table` WHERE " . implode(' AND ', $bind_parts);
                } else {
                    $sql = "SELECT `$pk` FROM `$table` WHERE `$pk` = :$pk";
                }
            } else {

                // begin collecting list of pks to delete
                if ( $all_cols_required ) {
                    $stmt = bind_pdo( array_combine( $header, $line), $db_conn->prepare( $sql ) );
                } else {
                    $stmt = bind_pdo( array( $pk => $line[array_search($pk, $header)]), $db_conn->prepare( $sql ) );
                }

                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                // error check if row isn't found
                if (isset($rows) && count($rows) > 0) $pks = array_merge($pks, $rows);
            }

            $row += 1;
        }

    }
    ini_set('auto_detect_line_endings',FALSE);

    // delete rows with given pks
    $sql = "DELETE FROM `$table` WHERE $pk IN (" . implode(',', array_fill(0, count($pks), '?')) . ")";
    $stmt = $db_conn->prepare($sql);
    $status = $stmt->execute($pks);
    $status2 = add_item_to_history_table( $table . "_history", $_SESSION['user_name'], $pks, "Batch deleted", [], $db_conn );

    if ( $status === false ) { // error
        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt->errorInfo()), "status" => false, "hide" => false, 'log'=>$sql));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    } else {

        if ( DEBUG ) {
            return json_encode(array("msg" => $stmt->rowCount() . " items properly deleted from table", "status" => true, "hide" => true, "log" => $stmt->errorInfo(), 'stat' => $stmt ) );
        } else {
            return json_encode(array("msg" => $stmt->rowCount() . " items properly deleted from table", "status" => true, "hide" => true ));
        }
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


    $delim = validate_uploaded_file( $files );
    if (is_array($delim)) return json_encode($delim);
        

    // loop through file and validate each row
    ini_set('auto_detect_line_endings',TRUE);
    if (($handle = fopen( $files['tmp_name'], "r")) !== FALSE) {
        $row = 0;
        $bind_vals = [];  // validated row values to batch insert SQL
        $bind_labels = [];
        while ( ( $line = fgetcsv($handle, 0, $delim ) ) !== FALSE ) {
            
            // check header for all required fields
            if ( $row == 0 ) {
                $header = $line;

                if ( count( $required_fields ) > 0 && array_intersect( $required_fields, $header ) != $required_fields ) {
                    return json_encode(array("msg" => 'Ensure you\'ve included all the required fields for this table including: <code>' . implode('</code>,<code>', $required_fields) . '</code>', "status" => false, "hide" => false));
                }

                $bind_label_template = ':' . implode('_num, :', $header) . '_num'; // this will get updated for each row by replacing _num with $row - used to speed things up
            } else {

                // validate file row data
                $dat = array_combine( $header, $line );
                $validate = validate_row( $dat, $table, False, $visible_fields, $required_fields, $fk_vals, $unique_vals, $row );
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
                $bind_labels[] = str_replace( '_num', $row - 1, $bind_label_template );
            }

            $row += 1;
        }
    }
    ini_set('auto_detect_line_endings',FALSE);

    $uid_fk = intval( get_db_conn()->query( "SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '$table'" )->fetch()['auto_increment'] ); // UID of last element added, used to increment _UID_fk

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
        $stmt = bind_pdo_batch( $table, $header, $bind_vals, $bind_labels, $_SESSION['user_name'], 'Batch added', $uid_fk );
        $status = $stmt->execute();

        if ( DEBUG ) {
            return json_encode(array("msg" => "File properly uploaded, $row rows added to the table added to table", "status" => true, "hide" => true, "log" => $stmt->errorInfo(), 'stat' => $stmt ) );
        } else {
            return json_encode(array("msg" => "File properly uploaded, $row rows added to the table added to table", "status" => true, "hide" => true ));
        }
    }

}

/**
 * Validate batch uploaded file is plain text
 * and has a delimiter.
 *
 * @params: (str) $file - $_FILES['file'] object
 *
 * @return: delimiter or array of error message
 *  ready to be returned with json_encode()
 *
*/
function validate_uploaded_file( $file ) {

    // check if valid file type supplied
    if ( !in_array( $file['type'], array("text/plain",'text/csv') ) ) {
        if (DEBUG) {
            return array("msg" => 'You must upload a plain text file with some sort of delimiter.', "status" => false, "hide" => false, "log" => $file['type'] );
        } else {
            return array("msg" => 'You must upload a plain text file with some sort of delimiter.', "status" => false, "hide" => false);
        }
    }


    // figure out delimiter
    $delimiters = array(",","\t",";","|",":");
    $delim = getFileDelimiter($file['tmp_name'], 5, $delimiters); // figure out delimiter

    if ( !in_array( $delim, $delimiters ) ) {
        return array("msg" => 'You must use one of the following delimiters: <code>' . implode('</code>,<code>', $delimiters) . "</code>" , "status" => false, "hide" => false);
    }

    return $delim;
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
        
        if ( count( $results ) > 0 ) {
            $results = array_keys($results, max($results));
            return $results[0];
        } else {
            return false;
        }
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
    add_item_to_history_table( $table . "_history", $_SESSION['user_name'], $_UID, "Manually deleted", [], $db_conn );
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
 * (int or array) $fk - FK UID of element associated
 * with change (will be an array in batch case)
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

    if ( !empty( $field_data ) ) {
        $table_columns = array_keys( $field_data );
        $sql_history = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`, `%s`) VALUES ('$fk', '$user', '$action', :%s)", $table, implode("`,`", $table_columns), implode(",:", $table_columns ) );
        $stmt_table_history = bind_pdo( $field_data, $db_conn->prepare( $sql_history ) );
        $status = $stmt_table_history->execute();
    } else { // if no field data is sent, just update the action and user
        $sql_history = "INSERT INTO `$table` (`_UID_fk`, `User`, `Action`) VALUES (?, '$user', '$action')";
        $stmt = $db_conn->prepare($sql_history);

        if (is_array($fk)) { // if batch
            foreach($fk as $val) {
                $status = $stmt->execute(array($val));
            }
        } else {
            $status = $stmt->execute(array($fk));
        }
    }

    return $status;
}








/**
 * Handle AJAX call for editing a table setup
 *
 * @param:
 * (assoc arr) $ajax_data - keys
 *  - dat: assoc arr of new & original setup ( see JS function getTableSetupForm() )
 *  - field_num: number of fields
 *  - fields: original field names
 *  - table: name of table being edited
 *
 * @return:
 * json encoded message for use with showMsg()
 *
*/
function save_table( $ajax_data ) {


    $db_conn = get_db_conn();
    $db = get_db_setup();
    $original_fields = $ajax_data['fields']; // NOTE: the order of this array is the same order as the data stored in 'dat'
    $dat = $ajax_data['dat'];
    $field_num = intval($ajax_data['field_num']);
    $original_table = $dat['table_name']['original'];
    $new_table = $dat['table_name']['update'];

    // check table name change
    $current_tables = $db->get_data_tables();
    if ($new_table !== $original_table) {
        if (in_array($new_table, $current_tables)) {
            return json_encode(array("msg" => "The table name <code>$new_table</code> already exists, please choose one that isn't in the following list: <code>" . implode(',', $current_tables) . "</code>", "status" => false, "hide" => false));
        }
    }


    // go through each column and collect all changes
    $changes = ['name' => [], 'default' => [], 'description' => [], 'required' => [], 'type' => [], 'unique' => [], 'length' =>[], 'fk' => []]; // changes to current fields
    $delete_cols = $original_fields; // list of fields to delete
    $new_cols = []; // list of newly added columns with key as name and value as assoc arr of new data (to be used with validate_field())
    $original_data = []; // assoc array of original data, key is field name, value is data
    $field_list = []; // new list of field names
    foreach($dat as $key => $val) { // key = 'field-x', val = {'original': {}, 'update': {}}

        if (strpos($key, 'field-') !== false) { // skip 'table_name' key
            $original_dat = $val['original'];
            $update_dat = $val['update'];

            if ($original_dat != null) {
                $original_name = $original_dat['name'];
            } else { // if new field
                $original_name = $update_dat['name'];
                $new_cols[$original_name] = $update_dat;
            }

            $original_data[$original_name] = $original_dat; // if changing name, will need all other field info

            $new_name = $update_dat["name"]; // store name as given, will need to replace spaces with '_'
            $new_default = $update_dat["default"];
            $new_description = $update_dat["description"];
            $new_required = isset($update_dat["required"]) ? true : false;
            $new_unique = isset($update_dat["unique"]) ? true : false;
            $new_type = $update_dat["type"]; // type comes in as varchar, float, date, varchar, int, datetime, fk
            $new_fk_ref = $update_dat['foreignKey'];
            $new_length = false;
            if ($update_dat["type"] == 'varchar') {
                if ($update_dat["longString"] === "true") {
                    $new_length = 4096;
                } else {
                    $new_length = 255;
                }
            }

            // check new data against original
            if ($original_dat != null) {

                // field name
                if ($new_name !== $original_dat['comment']['name']) {
                    $changes['name'][$original_name] = $new_name;
                    $field_list[] = $new_name;
                }

                // field default
                if ($new_default !== $original_dat['default']) {
                    $changes['default'][$original_name] = $new_default;
                }

                // field description
                if ($new_description !== $original_dat['comment']['description']) {
                    $changes['description'][$original_name] = $new_description;
                }

                // field required
                if ($new_required !== ($original_dat['required'] === 'true')) {
                    $changes['required'][$original_name] = $new_required;
                }

                // field unique
                if ($new_unique !== ($original_dat['unique'] === 'true')) {
                    $changes['unique'][$original_name] = $new_unique;
                }

                // field type
                // FK comes in as 'fk' type however the original is stored as varchar, therefore changes could be:
                // non-str -> str OR str -> non-str
                // non-fk -> fk 
                // str (fk type) -> non-fk
                $original_str = (strpos($original_dat['type'], 'varchar') !== false); // will be true if type was originally an str (or FK)
                $original_fk = ($original_dat['is_fk'] === 'true'); // will be true if type was originally FK
                $update_str = (strpos($new_type, 'varchar') !== false); // will be true if new type is str (not FK or other type)

                if (($original_str === false && $update_str === true) || ($original_str === true && $update_str === false && $new_type != 'fk')) {
                    $changes['type'][$original_name] = $new_type;
                } else if ($original_fk === false && $new_type == 'fk') { // non-fk -> fk (fk being added)
                    $changes['fk'][$original_name] = $update_dat["foreignKey"]; // if change to FK, store ref instead
                    if ($original_str === false) $changes['type'][$original_name] = 'fk'; // only make changes to type if previously not an str
                } else if ($original_fk === true && $new_type != 'fk') {
                    $changes['fk'][$original_name] = false; // fk removed
                    if ($original_str !== $update_str) $changes['type'][$original_name] = $new_type; // only make changes to type if previously not an str
                }


                // field length
                if ($new_length > 0 && $new_length !== intval($original_dat['length'])) {
                    $changes['length'][$original_name] = $new_length;
                }
            } else {
                if ($original_dat == null) { // if new field
                    // skip, will check when validating new fields
                } else {
                    $field_list[] = $original_dat['comment']['name'];
                }
            }

            // if original field name is part of original fields, remove it from the
            // to be deleted array
            if (in_array($original_name, $delete_cols)) {
                unset($delete_cols[array_search($original_name, $delete_cols)]);
            }
        } 
    }

    //return json_encode(array("msg" => "!", "status" => false, "hide" => false, "changes"=>$changes, "orig"=>$original_str, "upd"=>$update_str, 'fk'=>$original_fk ));


    // ensure something is being updated
    $no_changes = True;
    foreach($changes as $key => $val) {
        if (count(array_values($val)) > 0) $no_changes = False;
    }
    if ($no_changes && count($delete_cols) == 0 && count($new_cols) == 0) {
        return json_encode(array("msg" => 'No changes requested, table has been left unmodified.', "status" => true, "hide" => true));
    }


    // check if required changes are ok
    if (count(array_values($changes['required']))) {
        foreach($changes['required'] as $field => $field_change) {
            if (in_array($field, $original_fields)) {
                $check = check_field_required_change($db_conn, $original_table, $field, $field_change);
                if ($check !== true) return $check;
            }
        }
    }

    // check if unique changes are ok
    if (count(array_values($changes['unique']))) {
        foreach($changes['unique'] as $field => $field_change) {
            if (in_array($field, $original_fields)) {
                $check = check_field_unique_change($db_conn, $original_table, $field, $field_change);
                if ($check !== true) return $check;
            }
        }
    }

    // check if type changes are ok
    if (count(array_values($changes['type']))) {
        foreach($changes['type'] as $field => $field_change) {
            if (in_array($field, $original_fields)) {
                $check = check_field_type_change($db_conn, $original_table, $field, $field_change);
                if ($check !== true) return $check;
            }
        }
    }

    // check that fields names are unique
    if (count(array_values($changes['name']))) {
        $check = check_field_name_change($original_fields, $changes['name']);
        if ($check !== true) return $check;
    }
    
    // check that str length change is ok
    if (count(array_values($changes['length']))) {
        foreach($changes['length'] as $field => $field_change) {
            if (in_array($field, $original_fields)) {
                $check = check_field_length_change($db_conn, $original_table, $field, $field_change);
                if ($check !== true) return $check;
            }
        }
    }


    // check if new fields are ok
    $bindings = [];
    if (count($new_cols) > 0) {
        $new_fields = $field_list; // list of fields in table
        $new_sql_fields = []; // sql command for each field, concat for full sql statement
        $new_history_fields = []; // column name and type for history table

        foreach($new_cols as $col_name => $col_dat) {
            $check = validate_field($db, $col_dat, $new_fields);
            if (is_array($check)) {
                $new_sql_fields[] = $check[0];
                $new_history_fields[] = $check[1];
                $bindings = array_merge($bindings, $check[2]);
                $new_fields = $check[3];
            } else {
                return $check;
            }
        }
    }


    // if we get this far, table is ok to change
    // construct SQL for changes to table
    // loop through current visible fields first
    // to generate SQL
    $sql_parts = []; // contains SQL edits for each column
    $index_parts = [];
    $sql_parts_history = [];
    $i = 0;
    foreach ($db->get_visible_fields($original_table) as $field) {
        $sql_tmp = ""; // all the str SQL for modifying current field
        $sql_tmp_history = '';
        $to_update = false; // true if field needs a change
        $name_change = false; // if field name is changed, will be name to be changed to; we keep track of this so that we can do all SQL calls with "CHANGE COLUMN" instead of MODIFY/ALTER
        $comment = $db->get_comment($original_table, $field);
        foreach($changes as $change => $change_dat) { // check changes for current visible field

            if (in_array($field, array_keys($change_dat))) {

                $field_type = $original_data[$field]['type']; // will need original field type if e.g. changing name, NOT NULL, etc

                $change_val = $change_dat[$field];
                if ($change == 'name') {
                    $name_safe = str_replace(' ', '_', $change_val);
                    $comment['name'] = $change_val;
                    $sql_tmp .= "`$name_safe` "; // don't need to prepare since regex checked characters already
                    $sql_tmp_history .= "`$name_safe` ";
                    if (!in_array($field, array_keys($changes['type'])) || !in_array($field, array_keys($changes['length']))) { // if no change to type/length manually add column type
                        $sql_tmp .= "$field_type ";
                        $sql_tmp_history .= "$field_type ";
                    }
                    $to_update = true;
                    $name_change = $name_safe;
                    $i++;
                } else if ($change == 'default') {
                    $sql_tmp .= "DEFAULT :default ";
                    $bindings["default"] = $change_val;
                    $to_update = true;
                    $i++;
                } else if ($change == 'description') {
                    $comment['description'] = $change_val;
                    $to_update = true;
                    $i++;
                } else if ($change == 'required') {
                    $change_val ? $sql_tmp .= "$field_type NOT NULL " : $sql_tmp .= "$field_type NULL ";
                    $to_update = true;
                    $i++;
                } else if ($change == 'type') {
                    if ($change_val == 'varchar') {
                        $change_len = $changes['length'][$field];
                        $change_val .= "($change_len)";
                        $to_update = true;
                        $i++;
                        $sql_tmp .= "$change_val ";
                        $sql_tmp_history .= "$change_val ";
                    } else if ($change_val == 'date') {
                        $comment['column_format'] = 'date';
                        $to_update = true;
                        $i++;
                        $sql_tmp .= "$change_val ";
                        $sql_tmp_history .= "$change_val ";
                    }
                } else if ($change == 'fk') {
                    if ($change_val === false) { // removing fk
                        $ref_parts = explode('.', $original_data[$field]['fk_ref']);
                        $ref_table = $ref_parts[0];
                        $fk_name = "fk_" . $field . "_" . $ref_table;
                        $index_parts[] = "DROP FOREIGN KEY `$fk_name` ";
                    } else if (is_string($change_val)) { // adding fk
                        $ref_parts = explode('.', $change_val);
                        $ref_table = $ref_parts[0];
                        $ref_field = $ref_parts[1];
                        $fk_name = "fk_" . $field . "_" . $ref_table;
                        $index_parts[] = "ADD CONSTRAINT `$fk_name` FOREIGN KEY (`$field`) REFERENCES `$ref_table`(`$ref_field`) ON DELETE RESTRICT ON UPDATE CASCADE";
                    }
                    unset($changes['fk'][$field]); // remove from $changes since we take care of it manually with $index_parts
                } else if ($change == 'unique') {
                    if ($change_val) {
                        $index_parts[] = "ADD CONSTRAINT `$field` UNIQUE (`$field`)";
                    } else { // handle case of removing unique constraint, have to drop index...
                        $index_parts[] = "DROP INDEX `$field`"; // NOTE it is assumed the unique index is the same name as the column
                    }
                    unset($changes['unique'][$field]); // remove from $changes since we take care of it manually with $index_parts
                } else if ($change == 'length') { // if length change, guaranteed to be varchar
                    if (!in_array($field, array_keys($changes['type']))) { // if type was also changed, this length was already accounted for
                        $sql_tmp .= "varchar($change_val) ";
                        $to_update = true;
                        $i++;
                    }
                }
            } 
        }
        if ($to_update) {
            $bindings["comment$i"] = json_encode($comment);
            $sql_tmp .= "COMMENT :comment$i";
    
            // if no change to the field name, we use the original name twice so that we can use the "CHANGE COLUMN" function (instead of ALTER/MODIFY)
            if ($name_change !== false) {
                $sql_parts[] = "`$field` $sql_tmp";
                $sql_parts_history[] = "`$field` $sql_tmp_history";
            } else {
                $sql_parts[] = "`$field` `$field` $sql_tmp";
                $sql_parts_history[] = "`$field` `$field` $sql_tmp_history";
            }
        }
    }


    $stmt_table = edit_table_sql($db_conn, $original_table, $sql_parts, $delete_cols, $no_changes, $new_sql_fields, $bindings, $new_table, $index_parts);

    //return json_encode(array("msg" => "!", "status" => false, "hide" => false, "changes"=>$changes, 'sql'=>$stmt_table, 'parts'=>$sql_parts, 'delete'=>$delete_cols, 'new'=>$new_sql_fields, 'index'=>$index_parts, 'bind'=>$bindings ));

    // Execute
    if ( $stmt_table->execute() !== false ) {
        refresh_db_setup(); // update DB class

        $stmt_table = edit_table_sql($db_conn, $original_table . "_history", $sql_parts_history, $delete_cols, $no_changes, $new_sql_fields, null, $new_table, null);

        $db_conn->exec($stmt_table);

        return json_encode(array("msg" => "Table properly updated!", "status" => true, "hide" => true, "db"=>get_db_setup()->asJSON( $new_table ) ));

    } else { // if error

        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => $stmt_table->debugDumpParams(), 'sql' => $sql_table ));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

}


/*
 * Generate a prepared SQL statment for editing a table including
 * edits to current fields, deletion of fields, and adding of new fields
 *
 * @params:
 * (PDO obj) $db_conn - PDO connection to DB
 * (str) $original_table - name of original table
 * (arr) $sql_parts - list of sql snippets for adjusting each column
 * (arr) $delete_cols - list of column names to delete
 * (bool) $no_changes - true if not changes to current fields in table (exlcudes adding new tables)
 * (arr) $new_sql_fields - same as $sql_parts but for the newly added fields
 * (obj) $bindings - PDO bindings for prepared statement
 * (str) $new_table - (optional) name of new table if changing name
 * (arr) $index_parts - (optional) SQL changes to indexes (e.g. remove unique constraint)
 * 
*/

function edit_table_sql($db_conn, $original_table, $sql_parts, $delete_cols, $no_changes, $new_sql_fields, $bindings, $new_table=NULL, $index_parts=NULL) {

    // we lock table and disable foreign key checks so that any changes
    // to a field with a foreign key won't throw an error
    // the right way to do it is to delete the FK and then recreate it
    // see: http://stackoverflow.com/q/13606469/1153897
    $sql_table = "LOCK TABLES `$original_table` WRITE; SET FOREIGN_KEY_CHECKS = 0;";

    // if at least one field is being updated
    if (!$no_changes && count($sql_parts) > 0) $sql_table .= "ALTER TABLE `$original_table` CHANGE COLUMN " . implode(', ', $sql_parts) . "; ";

    // if removing columns
    if (count($delete_cols) > 0) $sql_table .= "ALTER TABLE `$original_table` DROP COLUMN `". implode('`, DROP COLUMN `', $delete_cols) . "`; "; 

    // if adding columns
    if (count($new_sql_fields) > 0) {
        $sql_table .= "ALTER TABLE `$original_table` ADD " . implode(', ADD', $new_sql_fields) . "; ";
    }

    // if editing indexes
    if ($index_parts !== null && count($index_parts) > 0) {
        $sql_table .= "ALTER TABLE `$original_table` " . implode(', ', $index_parts);
    }

    // if renaming table, do it last
    if ($new_table !== $original_table) $sql_table .= " RENAME TABLE `$original_table` TO `$new_table`; ";

    $sql_table .= "SET FOREIGN_KEY_CHECKS = 1; UNLOCK TABLES; ";

    if ($bindings !== null) {
        return bind_pdo( $bindings, $db_conn->prepare( $sql_table ) );
    } else {
        return $sql_table;
    }

}


/**
 * Validate whether field length can be changed
 *
 * @param:
 * (pdo) $db_conn - connection to database
 * (str) $table - name of table being modified
 * (str) $field - name of field being updated
 * (type) $field_change - field length to change to either 4096 or 255
 *
*/
function check_field_length_change($db_conn, $table, $field, $field_change) {

    if ($field_change === 4096) {
        // don't need to check as there won't be a loss of data
    } else if ($field_change === 255) {
        // check that every value in field is less than 256 long
        $q = $db_conn->query("SELECT count(*) FROM `$table` WHERE char_length(`$field`) > 255;");
        $num = intval($q->fetchColumn());
        if ($num > 0) return json_encode(array("msg" => "You cannot change the length of field <code>$field</code> because it currently contains data that is longer than 255 characters; update these values before updating this field.", "status" => false, "hide" => false));
    }
    return true;

}





/**
 * Validate that field names can be changed.
 *
 * Function will check whether the new list of 
 * field names is unique.
 *
 * @param:
 * (arr) $original_fields - name of original table fields
 * (assoc. arr) $field_changes - any field changes to be made
 * stores as [original_name => new_name, ...]
 *
 * @return:
 * json encoded message for use with showMsg() if field
 * cannot be changed to required; otherwise true
 *
*/
function check_field_name_change($original_fields, $field_changes) {



    // generate a list of all the final fields names in the table
    $new_fields = [];
    foreach($original_fields as $field) {
        if (array_key_exists($field, $field_changes)) {
            $field = $field_changes[$field];
        }

        // validate field length, chars, duplication
        $check = validate_name( $field, $new_fields, 'Field' );
        if ( $check !== true ) {
            return $check;
        }
        array_push($new_fields, $field);
    }
    return true;

    // check if any fields are duplicated
    $counts = array_count_values($new_fields);
    $dups = [];
    foreach ($counts as $field => $count) {
        if ($count > 1) array_push($dups, $field);
    }

    // return
    if (count($dups)) { 
        return json_encode(array("msg" => "The updated field names are not unique, please choose a new name for the field(s) <code>" . implode(',', $dups) . "</code>", "status" => false, "hide" => false));
    } else {
        return true;
    }
}



/**
 * Validate that field type attribute can be changed.
 *
 * Function will check whether a field type can be changed
 * based on the type of field:
 * - change to datetime: all fields must match regex '^[0-9]{1,4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}$'
 * - change to varchar: don't need to check
 * - change to int: all fields must match regex '^[0-9]$'
 * - change to float: all fields must match regex '^[0-9]*\.?[0-9]$'
 * - change to FK: all fields must have a valid FK value
 *
 * @param:
 * (pdo) $db_conn - connection to database
 * (str) $table - name of table being modified
 * (str) $field - name of field being updated
 * (type) $type - field type to change to, note that if changing to an FK
 *  this value will be the reference table.field
 *
 * @return:
 * json encoded message for use with showMsg() if field
 * cannot be changed to required; otherwise true
 *
*/
function check_field_type_change($db_conn, $table, $field, $type) {

    $error = false;

    $name_map = ['int' => 'Integer', 'float' => 'Float', 'date' => 'Date', 'datetime' => 'Date & Time'];
    $db = get_db_setup();
    $table_fields = $db->get_visible_fields($table);

    if (!in_array($field, $table_fields))  return true; // if new field, don't need to check type change
    

    // get number of rows in table
    $q = $db_conn->query("SELECT count(*) FROM `$table`");
    $num_rows = intval($q->fetchColumn());

    if ($type == 'varchar') {
        // don't need to check anything, any entry can be a str
    } else if ($type == 'float') {

        // check how many rows match float regex
        $q = $db_conn->query("SELECT count(*) FROM `$table` WHERE `$field` REGEXP '^[0-9]*\.?[0-9]$';");
        $num = intval($q->fetchColumn());
        if ($num !== $num_rows) $error = true;

    } else if ($type == 'int') {

        // check how many rows match int regex
        $q = $db_conn->query("SELECT count(*) FROM `$table` WHERE `$field` REGEXP '^[0-9]$';");
        $num = intval($q->fetchColumn());
        if ($num !== $num_rows) $error = true;

    } else if ($type == 'date' || $type == 'datetime') {

        // check how many rows match datetime regex
        // note that date fields are also stored as datetime
        $q = $db_conn->query("SELECT count(*) FROM `$table` WHERE `$field` REGEXP '^[0-9]{1,4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}$';");
        $num = intval($q->fetchColumn());
        if ($num !== $num_rows) $error = true;

    } else if (is_array($type) && array_keys($type)[0] == 'fk') { // FK stores as array in form ('fk' => ref)

        // check that field contains only FK values
        $ref = $type['fk'];
        $ref_table = explode('.', $ref)[0];
        $ref_field = explode('.', $ref)[1];
        $vals = get_db_setup()->get_unique_vals_field($ref_table, $ref_field); // field must contain only these values
        $vals_str = implode("','", $vals);
        $q = $db_conn->query("SELECT count(*) FROM `$table` WHERE `$field` IN ('$vals_str') ;");
        $num = intval($q->fetchColumn());

        if ($num !== $num_rows) $error = true;
        $name_map['fk'] = 'Foreign';

    }

   
    if ($error) { 
        $type_clean = $name_map[$type];
        if ($type_clean == 'Foreign') {
            return json_encode(array("msg" => "You cannot change the field <code>$field</code> to <code>$type_clean</code> because all values must be one of <code>'$vals_str'</code>; update these values before updating this field.", "status" => false, "hide" => false));
        } else {
            return json_encode(array("msg" => "You cannot change the field <code>$field</code> to <code>$type_clean</code> because it contains values that cannot be converted to this type; update these values before updating this field.", "status" => false, "hide" => false));
        }
    } else {
        return true;
    }

}




/**
 * Validate that field unique attribute can be changed.
 *
 * Function will only check field if requesting to change
 * it to a unique attribute, in this case the database
 * is queried with a "group by ... having > 1" query
 * to check how many of the fields values are not
 * unique. If removing the unique constraint,
 * function will check that no FK to this column exists.
 *
 * @param:
 * (pdo) $db_conn - connection to database
 * (str) $table - name of table being modified
 * (str) $field - name of field being updated
 * (bool) $unique - whether field should be required
 *
 * @return:
 * json encoded message for use with showMsg() if field
 * cannot be changed to required; otherwise true
 *
*/
function check_field_unique_change($db_conn, $table, $field, $unique) {

    if ($unique) { // if changing to unique

        // check that every value in field is unique
        $q = $db_conn->query("SELECT count(*) FROM (SELECT `$field` FROM `$table` GROUP BY `$field` HAVING count(*) > 1) a"); // will return a single number representing the number of field values that are not unique
        $num = intval($q->fetchColumn());
        if ($num > 0) return json_encode(array("msg" => "You cannot change the field <code>$field</code> to be unique because its current values are not unique; update these values before updating this field.", "status" => false, "hide" => false));
    } else { // if removing unique constraint, check that no FK to this column exists

        // check if any table uses this column as a FK
        $q = $db_conn->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE referenced_table_name = '$table' AND REFERENCED_COLUMN_NAME = '$field'");
        $q->execute();
        $result = $q->fetchAll();
        if (count($result)) {
            $ref = [];
            foreach($result as $dat) {
                $ref[] = "<code>" . $dat['TABLE_NAME'] . '.' . $dat['COLUMN_NAME'] . '</code>';
            }
            return json_encode(array("msg" => "You cannot remove the unique constraint on the field <code>$field</code> because it is currently being referenced by the following foreign keys: " . implode(',', $ref) . ". Either remove these foreign keys or delete the columns before updating the unique constraint.", "status" => false, "hide" => false));
        }

    }

    return true;

}


/**
 * Validate that field required attribute can be changed.
 *
 * Function will only check the field if requesting to
 * be required - in this case, this will check whether
 * any of the values for the field are currently empty.
 * If so, field cannot be made required.
 * 
 * @param:
 * (pdo) $db_conn - connection to database
 * (str) $table - name of table being modified
 * (str) $field - name of field being updated
 * (bool) $required - whether field should be required
 *
 * @return:
 * json encoded message for use with showMsg() if field
 * cannot be changed to required; otherwise true
 *
*/
function check_field_required_change($db_conn, $table, $field, $required) {

    
    if ($required) { // if changing to required

        // check that no item is empty in table
        $q = $db_conn->query("SELECT count(`$field`) FROM `$table` WHERE `$field` is null");
        $num = intval($q->fetchColumn());
        if ($num > 0) return json_encode(array("msg" => "$num You cannot change the field <code>$field</code> to required because it currently has empty cells; add values to these cells before updating this field.", "status" => false, "hide" => false));

    }

    return true;

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
        $table = $data['table_name']['update'];
    } else {
        return json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
    }

    // validate table name
    $check = validate_name( $table, $db->get_all_tables(), 'Table' );
    if ( $check !== true ) {
        return $check;
    }

    // check field names for errors
    for( $i = 1; $i<=$field_num; $i++ ) {

        $check = validate_field($db, $data["field-$i"]['update'], $fields);
        if (is_array($check)) {
            $sql_fields[] = $check[0];
            $history_fields[] = $check[1];
            $bindings = array_merge($bindings, $check[2]);
            $fields = $check[3];
        } else {
            return $check;
        }
 
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
            return json_encode(array("msg" => "Table <a href='/chickenkitchen/?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true, 'log'=>json_encode($comment) ));
        } else {
            return json_encode(array("msg" => "Table <a href='/chickenkitchen/?table=$table'>$table</a> properly generated!", "status" => true, "hide" => true ));
        }

    } else { // if error

        // drop table because it was properly generated but the history version wasn't
        if ( $stmt_table->errorInfo()[2] == NULL ) {
            $db_conn->exec("DROP TABLE `$table`");
        }

        if ( DEBUG ) {
            return json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => $stmt_table->debugDumpParams(), 'sql' => $sql_table . $sql_table_history ));
        } else {
            return json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

}



/*
 * Will check whether the given data is valide in order
 * to create a column in a table by validating:
 * - name length
 * - name uniqueness
 * - name characters used
 *
 * If field name is valide, function will generate all
 * the SQL parts needed to create the field
 *
 * @params
 * (assoc arr) $dat - form data for field setup with keys
 *  like name, type, description, etc
 * (arr) $fields - already validated fields
 *
 * @returns
 * (arr) [$sql_str, $history_field, $bindings, $fields]
 * $sql_str - SQL string needed to generated field
 * $history_field - SQL string needed to generate history counterpart
 * $bindings - PDO bindings for field
 * $fields - list of validated fields
*/
function validate_field($db, $dat, $fields) {

    $bindings = []; // PDO bindings for default values
    $i = count($fields) + 1;

    $comment['name'] = $dat['name'];
    $field_name = str_replace(' ', '_', $dat['name']); // replace spaces with _ to make parepared statements easier
    $field_type = $dat['type'];
    $field_current = isset($dat['currentDate']) ? $dat['currentDate'] : false;
    $field_required = isset($dat['required']) ? $dat['required'] : false;
    $field_unique = isset($dat['unique']) ? $dat['unique'] : false;
    $field_long_string = isset($dat["longString"]) ? true : false;
    $field_description = isset($dat["description"]) ? $dat["description"] : false;
    if ( isset( $dat["default"] ) && $dat["default"] !== "" ) {
        $field_default = $dat["default"];
        if ( $field_default === 'true' ) $field_default = true; // cant get AJAX to send as boolean
    } else {
        $field_default = false;
    }

    // validate field name
    $check = validate_name( $field_name, $fields, 'Field' );
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
        $fk = explode('.', $dat['foreignKey']); // table_name.col of foreign key
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
        $sql_str = "`$field_name` int(32)";
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

    $history_field = $sql_str; // only need field name and type for history table
    // set NOT NULL if required
    if ( $field_required) $sql_str .= " NOT NULL";
    // set default, will be executed as a prepared statement
    if ( $field_default)  {
        if ( $field_type == 'datetime' ) {
            $sql_str .= " DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        } else {
            $bindings["default$i"] = $field_default;
            $sql_str .= " DEFAULT :default$i"; // binding
        }
    }

    // set unique
    if ( $field_unique ) $sql_str .= " UNIQUE";

    // add comment
    $comment['description'] = $field_description;
    $bindings["comment$i"] = json_encode($comment);
    $sql_str .= " COMMENT :comment$i";

    // add index if not long string and not unique
    if ( $field_long_string == false && $field_unique == false ) $sql_str .= sprintf(", ADD INDEX `%s_IX` (`$field_name`)", $field_name);

    // if FK type was requested, add the constraint
    if ($is_fk) {
        $fk_name = "fk_" . $field_name . "_" . $fk_table;
        $sql_str .= ", CONSTRAINT `$fk_name` FOREIGN KEY (`$field_name`) REFERENCES `$fk_table`(`$fk_col`) ON DELETE RESTRICT ON UPDATE CASCADE";
    }

    return [$sql_str, $history_field, $bindings, $fields];
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
 * (int or arr) $uid_fk - last _UID in DB before batch add
 *  initiated, used to properly set _UID_fk (in case of batch add)
 * OR an array of UIDs (in the case of batch edit)
 *
 *
 * @returns:
 * prepared PDO statement ready for execution
 *
*/
function bind_pdo_batch( $table, $header, $bind_vals, $bind_labels, $user=NULL, $action=NULL, $uid_fk=NULL ) {

    $db_conn = get_db_conn();

    if ( isset( $user ) && isset( $action ) && isset( $uid_fk ) ) { // batch history
        $sql = sprintf( "INSERT INTO `%s` (`_UID_fk`, `User`, `Action`, `%s`) VALUES ", $table . '_history', implode( "`,`", $header ));

        // we need to manaully construct the bound values a bit since we have the _UID_pk
        foreach ( $bind_labels as $i => $label ) {
            if (!is_array($uid_fk)) { // batch add, $uid_fk is last _UID added in DB
                $parts[] = "(" . ($uid_fk + $i) . ", '$user', '$action', $label)";
            } else { // batch edit, $uid_fk is an array of _UIDs
                $parts[] = "(" . ($uid_fk[$i]) . ", '$user', '$action', $label)";
            }
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
 *  bound in prepare statment with form
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
 * (int) $row_num - current row being checked
 *
 *
 * @return json encoded error message, otherwise true
 *
*/
function validate_row( $dat, $table, $edit=False, $visible_fields=False, $required_fields=False, $fk_vals=False, $unique_vals=False, $row_num=False ) {

    $db = get_db_setup();
    if ($visible_fields === False) $visible_fields = $db->get_visible_fields( $table );
    if ($required_fields === False) $required_fields = $db->get_required_fields( $table );
    if ($fk_vals === False) $fk_vals = $db->get_fk_vals( $table );
    if ($unique_vals === False) $unique_vals = $db->get_unique_vals( $table );
    $sent_fields = array_keys( $dat );

    // go through each field of the table and check 
    // that valid information was sent for it
    foreach ( $visible_fields as $field_name ) {

        $field_required = (count($required_fields) > 0 && is_array($required_fields)) ? in_array( $field_name, $required_fields ) : false;
        if ( in_array( $field_name, $sent_fields ) ) {

            $field_type = $db->get_field( $table, $field_name );
            $field_val = $dat[ $field_name ];

            if ( isset( $field_val ) && $field_val !== '' ) { // skip empty fields

                // validate field value in proper format
                $check = validate_field_value( $field_type, $field_val );
                if ( $check !== true ) {
                    return $check;
                }

                // validate field is unique
                if ( in_array( $field_name, array_keys( $unique_vals ) ) && in_array( $field_val, $unique_vals[$field_name] ) ) {
                    if ($row_num === False) {
                        return json_encode(array("msg" => "The item value <code>$field_val</code> already exists in the unique field <code>$field_name</code>, please choose another.", "status" => false, "hide" => false));
                    } else {
                        return json_encode(array("msg" => "The item value <code>$field_val</code> (found in row $row_num) already exists in the unique field <code>$field_name</code>, please choose another.", "status" => false, "hide" => false, 'unique'=>$unique_vals));
                    }
                }

                // validate FK field has proper value
                if ( in_array( $field_name, array_keys( $fk_vals ) ) && !in_array( $field_val, $fk_vals[$field_name] ) ) {
                    if ($row_num === False) {
                        return json_encode(array("msg" => "The item value <code>$field_val</code> must be one of the following: <code>" . implode('</code>,<code>', $fk_vals[$field_name] ) . "</code>, please choose another.", "status" => false, "hide" => false));
                    } else {
                        return json_encode(array("msg" => "The item value <code>$field_val</code> (found in row $row_num) must be one of the following: <code>" . implode('</code>,<code>', $fk_vals[$field_name] ) . "</code>, please choose another.", "status" => false, "hide" => false));
                    }
                }

            } else if ( $field_required ) {

                if ($row_num === False) {
                    return json_encode( array("msg" => "Please ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false) );
                } else {
                    return json_encode( array("msg" => "Error in row $row_num - please ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false, "log" => array($dat, $field_val)) );
                }

            }

        } else if ( $field_required && $edit ) {

/*
            if ($row_num === False) {
                return json_encode( array("msg" => "Pease ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false) );
            } else {
                return json_encode( array("msg" => "Error in row $row_num - please ensure you've filled out all required fields including <code>$field_name</code>.", "status" => false, "hide" => false) );
            }
*/
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
function validate_name( $name, $names, $type='Table' ) {

    // table name must not already exist
    if ( in_array( $name, $names ) ) {
        return json_encode(array("msg" => "$type name <code>$name</code> already exists, please choose another.", "status" => false, "hide" => false)); 
    }

    // ensure table name is only allowed letters
    if ( !preg_match( '/^[a-z0-9\-_ ]+$/i', $name ) ) {
        return json_encode(array("msg" => "$type name <code>$name</code> may only include letters, numbers, hypens, spaces and underscores, please choose another.", "status" => false, "hide" => false)); 
    }

    // table name can only be max 64 chars
    if ( strlen( $name ) > 64 ) {
        return json_encode(array("msg" => "$type name <code>$name</code> is too long, please choose a shorter name.", "status" => false, "hide" => false)); 
    }
    if ( empty( $name ) ) {
        return json_encode(array("msg" => "$type name cannot be empty.", "status" => false, "hide" => false)); 
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

    if ( !in_array( $table_name, $data_tables ) ) {
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
        jQuery(function() {
            var tmp = <?php echo get_db_setup()->asJSON( $_GET['table'] ); ?>;
            db = tmp.struct; // global - required for radioSelect function to display proper info
            var table = '<?php echo $_GET['table']; ?>'; // needed for AJAX
            jQuery('input[id=batchAdd]').click(); // do initial click on radio so that text is shown to user
        })
    </script>


<?php }




/**
 * Function ensures all extra data needed is present in $_SESSION
 * including the user name and the user role.
 *
 * @param: void
 *
 * @return: void
*/
function setup_session() {

    require_once('config/db.php');

    if (function_exists(wp_get_current_user)) { // if wordpress exists
        $user = wp_get_current_user();
        $user_dat = get_userdata($user->ID);
        $user_roles = $user_dat->roles; // this is an array of roles

        $role_check = array('administrator', 'contributor', 'editor', 'author', 'subscriber');

        foreach ($role_check as $role) {
            if (in_array($role, $user_roles)) {
                $_SESSION['user_role'] = $role;
            }
        }

        $_SESSION['user_name'] = $user->ID;
    } else {
        $_SESSION['user_role'] = USER_ROLE;
        $_SESSION['user_name'] = USER_NAME;
    }

}





?>
