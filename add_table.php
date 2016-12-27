<?php require_once 'header.php'; ?>

<div class="container">

    <?php /*

    TODO

    */


    if (isset($_GET['table'])) {
        echo "<h1>Edit table <code>" . $_GET['table'] . "</code></h1>"; 
    } else {
        echo '<h1>Add table</h1>';
    }

    ?>

   <form class="form-horizontal" onsubmit="return false;">
      <div class="form-group">
        <label class="col-sm-2 control-label">Table name</label>
        <div class="col-sm-2">
          <input type="text" class="form-control" name="table_name" placeholder="samples" required pattern="[A-Za-z0-9-_]+" title="Only letters, numbers, underscores and dashes allowed (no spaces).">
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
        //db = cleanDB(tmp);

        jQuery(function() { // automatically add the first field
            jQuery('#add_field').click();
        });
    </script>

</div>


<?php require_once 'footer.php'; ?>
