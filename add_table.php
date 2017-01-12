<?php require_once 'header.php'; ?>
<?php require_once 'nav.php'; ?>
<?php require_once "functions.php"; ?>


<?php /*

TODO

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
    <label class="col-sm-2 control-label">Table name</label>
    <div class="col-sm-2">
      <input type="text" class="form-control" id="table_name" name="table_name" placeholder="samples" required pattern="[A-Za-z0-9-_]+" title="Only letters, numbers, underscores and dashes allowed (no spaces)." maxlength="64">
    </div>
    <div class="col-sm-2">
      <button type="button" class="btn btn-default btn-info" onclick="addField()" id="add_field" >Add field</button>
    </div>
    <div class="col-sm-offset-4 col-sm-2">
      <?php if (isset($_GET['table'])) {
        echo '<div class="pull-right btn-group"><button type="button" class="btn btn-success" onclick="editTable()">Save changes</button>';
      } else {
        echo '<button type="button" class="btn pull-right btn-success" onclick="addTable()">Create table</button>';
      }?>
    </div>
  </div>
  <input id="submit_handle" type="submit" style="display: none"> <!-- needed for validating form -->
</form>


<script  type="text/javascript">
    jQuery(function() {

        jQuery('#add_field').click(); // automatically add the first field
        jQuery('#type-1').val('varchar'); // automatically set the first field type

        // fill table name if available
        table = '<?php echo $_GET['table'] ;?>';
        if (table && table != '') {
            jQuery('#table_name').val(table);
        }
    });
</script>



<?php require_once 'footer.php'; ?>
