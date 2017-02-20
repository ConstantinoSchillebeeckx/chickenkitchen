<?php if (session_status() == PHP_SESSION_NONE) session_start(); ?>
<?php require_once 'header.php'; ?>
<?php require_once 'nav.php'; ?>
<?php require_once "functions.php"; ?>


<?php /*

Page allows user to add new tables to DB.  Form collects all required
info which then fires and AJAX request to the add_table_to_db()
function found in functions.php.  On success or error, a message
is displayed to the user - the success message will autohide in 3s.

*/


if (isset($_GET['table'])) {
    echo "<h1>Edit table</h1>"; 
} else {
    echo '<h1>Add new table</h1>';
}

?>

<div class="alertContainer"></div> <!-- automatically filled by showMsg() -->

<form class="form-horizontal" onsubmit="return false;">
  <div class="form-group">
    <label class="col-sm-2 control-label">Table name*</label>
    <div class="col-sm-2">
      <input type="text" class="form-control" id="table_name" name="table_name" placeholder="samples" required pattern="[A-Za-z0-9-_ ]+" title="Only letters, numbers, underscores, spaces and dashes allowed." maxlength="64">
    </div>
    <div class="col-sm-2">
      <button type="button" class="btn btn-default btn-info" onclick="addField()" id="add_field" >Add field</button>
    </div>
    <div class="col-sm-offset-4 col-sm-2">
      <?php if (isset($_GET['table'])) {
        echo '<div class="pull-right btn-group"><button type="button" class="btn btn-success" onclick="editTable()">Save changes</button>';
      } else {
        echo '<button type="button" class="btn pull-right btn-success" onclick="addTable(event)" id="addTableSubmit">Create table</button>';
      }?>
    </div>
  </div>
  <input id="submit_handle" type="submit" style="display: none"> <!-- needed for validating form -->
</form>


<script  type="text/javascript">
    jQuery(function() {
        // store DB as JS var in case user requests a FK field type
        // in which case we need to look up the FK values using this var
        // see getFKchoices()
        fks = <?php echo json_encode(get_db_setup()->get_possible_fk()); // send DB to javascript var ?>;

        jQuery('#add_field').click(); // automatically add the first field
        jQuery('#type-1').val('varchar'); // automatically set the first field type
        selectChange(1); // call function to show helper text for string type

        // fill table name if available
        table = '<?php echo $_GET['table'] ;?>';
        if (table && table != '') {
            jQuery('#table_name').val(table);
        }
    });
</script>



<?php require_once 'footer.php'; ?>
