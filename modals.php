<!-- history modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content panel-info">
      <div class="modal-header panel-heading">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-history" aria-hidden="true"></i> Item history <span id="historyID"></span></h4>
      </div>
      <div class="modal-body">

        <?php // generate table HTML
        if ( isset( $db ) && isset( $table ) ) {
       
            $table .= '_history'; 
            $fields_hist = $db->get_fields($table); 
            $table_class = $db->get_table($table);
            $hidden_hist = array_values($table_class->get_hidden_fields());
            ?>
            
            <table class="table table-bordered table-hover table-responsive" id="historyTable" width="100%">
            <thead>
            <tr class="info">

            <?php foreach ( $fields_hist as $field ) {
                if ($field == '_timestamp') {
                    echo "<th>Timestamp</th>";
                } else if ($field == '_user') {
                    echo "<th>User</th>";
                } else if ($field == '_action') {
                    echo "<th>Notes</th>";
                } else {
                    echo "<th>$field</th>"; 
                }
            } ?>

            <th>Revert</th>
            </tr>
            </thead>
            </table>

            <script type="text/javascript">
                var columnHist = <?php echo json_encode( $fields_hist ); ?>;
                var pk = <?php echo json_encode( $db->get_pk( $table ) ); ?>;
                var hiddenHist = <?php echo json_encode( $hidden_hist ); ?>;
                table += '_history'; // assumes history table has appended '_history'
                // table gets filled once the history button is clicked
                // this is done by historyModal()
            </script>
        <?php } ?>

      </div>
    </div>
  </div>
</div>




<!-- edit item modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content panel-warning">
            <div class="modal-header panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-pencil-square-o text" aria-hidden="true"></i> Edit item</h4>
            </div>
            <form class="form-horizontal" onsubmit="return false;" id="editItemForm">
                <div class="modal-body">
                    <p class="lead">Editing the item <span id="editID"></span></p>
                    <?php get_form_table_row($table); // vars defined by build_table() in functions.php ?>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn" data-dismiss="modal">Cancel</a>
                    <button type="button" class="btn btn-warning" id="confirmEdit" onclick="editItem()">Save changes</button>
                </div>
                <input id="submit_handle" type="submit" style="display: none"> <!-- needed for validating form -->
            </form>
        </div>
    </div>
</div>









<!-- delete item modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content panel-danger">
      <div class="modal-header panel-heading">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-times" aria-hidden="true"></i> Archive item</h4>
      </div>
      <div class="modal-body">
        Are you sure you want to archive the item <span id="deleteID"></span>?
      </div>
      <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">Cancel</a>
        <button type="button" class="btn btn-danger" id="confirmDelete">Archive item</button>
      </div>
    </div>
  </div>
</div>

















<!-- add item modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content panel-primary">
            <div class="modal-header panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-plus" aria-hidden="true"></i> Add item</h4>
            </div>
            <form class="form-horizontal" onsubmit="addItem()" id="addItemForm">
                <div class="modal-body">
                        <?php get_form_table_row($table); // vars defined by build_table() in EL.php ?>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn" data-dismiss="modal">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="confirmAddItem">Add item</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- delete table modal -->
<div class="modal fade" id="deleteTableModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content panel-danger">
      <div class="modal-header panel-heading">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-times" aria-hidden="true"></i> Delete table</h4>
      </div>
      <div class="modal-body">
        Are you sure you want to delete the table <code><?php echo $_GET['table']; ?></code>? <br><br>
        <mark><strong>Note:</strong> you cannot undo this.</mark>
      </div>
      <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">Cancel</a>
        <button type="button" class="btn btn-danger" id="confirmDeleteTable">Delete table</button>
      </div>
    </div>
  </div>
</div>
