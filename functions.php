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


    if ( !isset( $_SESSION['db'] ) ) {

        // Get setup
        $setup = new Database( ACCT, get_db_conn() );

        $_SESSION['db'] = $setup;
    }

    return $_SESSION['db'];
}




/**
 * Generate HTML for viewing a table
 *
 * @param $table str database table to view
 *
 * @return will echo proper HTML

*/
function build_table( $table ) {

    $db = get_DB_setup();
    $pk = $db->get_pk($table);
    $table_class = $db->get_table($table);
    $has_history = $db->has_history($table);
    if ( $has_history ) $pk_hist = $db->get_pk($table . "_history");
   
    // generate table HTML
    if ( isset( $db ) && isset( $table ) && $table_class ) {
  
        $fields = $db->get_fields($table); 
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
        echo 'Table doesn\'t exist; list of available tables are: ' . implode(', ', $db->get_tables());
    }

}


/**
 * Function will generate HTML as <ul> of all available tables
 * in the database along with a link to view them.
 * 
 * @param void
 *
 * @return will echo <ul> html with <a href> to table
*/

function list_tables() {

    $db = get_db_setup();
    $tables = $db->get_tables();

    echo '<div class="col-sm-12">';
    echo '<ul>';

    foreach( $tables as $table ) {
        if ( !$db->get_table( $table )->is_history() ) {
            echo "<li><a href='?table=$table'>$table</a></li>";
        }
    }

    echo '</ul>';
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
- $_GET['dat'] : obj of form data (key: col name, val: value)
                 at a minimum will have the following keys:
                 - table_name (safe name)
                 - name-1 (field name)
                 - type-1 (field type)
- $_GET['field_num'] : number of fields being added
*/
function add_table_to_db() {


    $db = get_db_setup();
    $field_num = $_GET['field_num'];
    $data = $_GET['dat'];
    $tables = $db->get_tables(); // list of tables in DB


    // make sure we have everything
    if ( isset( $data['table_name'] ) && !empty( $data['table_name'] ) ) {
        $table = $data['table_name'];
    }



    // table name must not already exist
    if ( in_array( $table, $tables ) ) {
        echo json_encode(array("msg" => "Table name <code>$table</code> already exists, please choose another.", "status" => false, "hide" => false)); 
        return;
    }

    // ensure table name is only allowed letters
    if ( !preg_match( '/^[a-z0-9\-_]+$/i', $table ) ) {
        echo json_encode(array("msg" => "Table name may only include letters, numbers, hypens and underscores, please choose another.", "status" => false, "hide" => false)); 
        return;
    }

    // table name can only be max 64 chars
    if ( strlen( $table ) > 64 ) {
        echo json_encode(array("msg" => "Table name <code>$table</code> is too long, please choose a shorter name.", "status" => false, "hide" => false)); 
        return;
    }
    if ( empty( $table ) ) {
        echo json_encode(array("msg" => "Table name cannot be empty.", "status" => false, "hide" => false)); 
        return;
    }

    // check field names for errors
    $fields = []; // list of fields in table
    $sql_fields = []; // sql command for each field, concat for full sql statement
    $bindings = []; // PDO bindings for default values
    $history_fields = []; // column name and type for history table
    for( $i = 1; $i<=$field_num; $i++ ) {

        $field_name = $data['name-' . $i];
        $field_type = $data['type-' . $i];
        $field_current = isset($data['currentDate-' . $i]) ? $data['currentDate-' . $i] : false;
        $field_required = isset($data['required-' . $i]) ? $data['required-' . $i] : false;
        $field_unique = isset($data['unique-' . $i]) ? $data['unique-' . $i] : false;
        $field_long_string = isset($data['longString-' . $i]) ? true : false;
        if ( isset( $data["default-$i"] ) && $data["default-$i"] !== "" ) {
            $field_default = $data["default-$i"];
            if ( $field_default === 'true' ) $field_default = true; // cant get AJAX to send as boolean
        } else {
            $field_default = false;
        }


        // check uniqueness
        if ( in_array( $field_name, $fields ) ) {
            echo json_encode(array("msg" => "Field name <code>$field_name</code> is not unique, please choose another name.", "status" => false, "hide" => false)); 
            return;
        }

        // check allowed chars
        if ( !preg_match( '/^[a-z0-9\-_ ]+$/i', $field_name ) ) {
            echo json_encode(array("msg" => "Field name <code>$field_name</code> may only include letters, numbers, hypens, underscores and spaces, please choose another.", "status" => false, "hide" => false)); 
            return;
        }

        // check name length
        if ( strlen( $field_name ) > 64 ) {
            echo json_encode(array("msg" => "Field name <code>$field_name</code> is too long, please choose a shorter name.", "status" => false, "hide" => false)); 
            return;
        }
        if ( empty( $field_name ) ){
            echo json_encode(array("msg" => "Field name cannot be empty.", "status" => false, "hide" => false)); 
            return;
        }

        // ensure default field matches field type
        if ( $field_default && count( $field_default) > 0 && is_string( $field_default ) ) {
            if ( $field_type == 'int' && !preg_match('/^[0-9]+$/i', $field_default)) {
                echo json_encode(array("msg" => "Only numbers are allowed as a default value if selecting an integer type field; please adjust the default value <code>$field_default</code>.", "status" => false, "hide" => false));
                return;
            } else if ( $field_type == 'varchar' && !preg_match('/^[a-z0-9_~\-!@#\$%\^&\*\(\)\. ]+$/i', $field_default)) {
                echo json_encode(array("msg" => "Only alphanumeric and special characters are allowed as a default value if selecting an string type field; please adjust the default value <code>$field_default</code>.", "status" => false, "hide" => false));
                return;
            } else if ( $field_type == 'float' && !preg_match('/^[0-9\.]+$/i', $field_default)) {
                echo json_encode(array("msg" => "Only numbers are allowed as a default value if selecting a float type field; please adjust the default value <code>$field_default</code>.", "status" => false, "hide" => false));
                return;
            } else if ( $field_type == 'date' && !preg_match('/(\d{4})-(\d{2})-(\d{2})/', $field_default)) {
                echo json_encode(array("msg" => "Default must be formatted as <code>YYYY-MM-DD</code> if selecting a date type field; please adjust the default value <code>$field_default</code>.", "status" => false, "hide" => false));
                return;
            } else if ( $field_type == 'datetime' && !preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/', $field_default)) {
                echo json_encode(array("msg" => "Default must be formatted as <code>YYYY-MM-DD hh:mm:ss</code> if selecting a datetime type field; please adjust the default value <code>$field_default</code>.", "status" => false, "hide" => false));
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
                $bindings[] = "DEFAULT '$field_default'";
                $sql_str .= " :default"; // binding
            }
        }

        // set unique
        if ( $field_unique ) $sql_str .= " UNIQUE";
  
        // add comment if it exists
        if ($comment) $sql_str .= $comment;

        // add index if not long string
        if ( $field_long_string == false ) $sql_str .= sprintf(", INDEX `%s_IX_%s` (`$field_name`)", $field_name, substr(md5(rand()), 0, 4));

        // if FK type was requested, add the constraint
        if ($is_fk) $sql_str .= sprintf(", FOREIGN KEY fk_%s_%s(`%s`) REFERENCES `%s`(`%s`) ON DELETE RESTRICT ON UPDATE CASCADE", substr(md5(rand()), 0, 4), $field_name, $field_name, $field_name, $fk_table, $fk_col);

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
    $stmt_table = $db_conn->prepare( $sql_table );
    $stmt_table_history = $db_conn->prepare( $sql_table_history );
    if ( count( $bindings ) ) {
        foreach ($bindings as $param) {
            $prep->bindParam(':default', $param, PDO::PARAM_STR, 12);
        }
    }


    // Execute
    if ( $stmt_table->execute() && $stmt_table_history->execute() ) {
        if ( DEBUG ) {
            echo json_encode(array("msg" => "Table <code>$table</code> properly generated!", "status" => true, "hide" => true, "sql" => $sql_table ));
        } else {
            echo json_encode(array("msg" => "Table <code>$table</code> properly generated!", "status" => true, "hide" => true ));
        }
    } else {
        if ( $stmt_table->errorInfo()[2] == null ) {
            // drop table because it was properly generated but the history version wasn't
            $db_conn->prepare("DROP TABLE `$table`")->execute();
        }

        if ( DEBUG ) {
            echo json_encode(array("msg" => "An error occurred: " . implode(' - ', $stmt_table->errorInfo()), "status" => false, "hide" => false, "log" => implode(' - ', $bindings), 'sql' => $sql_table . $sql_table_history ));
        } else {
            echo json_encode(array("msg" => "An error occurred, please try again", "status" => false, "hide" => false ));
        }
    }

    return;



}





?>
