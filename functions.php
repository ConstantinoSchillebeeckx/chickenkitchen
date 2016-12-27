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
        'charset' => utf8mb4_general_ci
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
    require_once "scripts/db_setup.php"; // Database class


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
            getDBdata(table, pk, columns, filter, hidden, null, hasHistory);
        </script>

    <?php } else {
        echo 'Table doesn\'t exist; list of available tables are: ' . implode(', ', $db->get_tables());
    }

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



?>
