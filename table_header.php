<?php

/**
will setup the button cluster that handle the events of:
 - adding a row to the table
 - downloading table
 - batch
 - edit table
 - delete table

Note that this cluster will only be shown for the following user_role:
 - Contributor
 - Editor
 - Admin
(that is, it'll be hidden from read_only)

*/
?>

<?php if (in_array($_SESSION['user_role'], array('contributor','editor','administrator'))) { ?>

<div class="col-sm-5" style="margin-top:20px">
    <div class="btn-group pull-right" role="group" aria-label="..." id="button-toolbar">
        <button type="button" title="Add item to table" class="btn btn-primary" onclick="addItemModal()"><i class="fa fa-plus fa-2x" aria-hidden="true"></i></button>
        <button type="button" title="Download table as CSV" class="btn btn-info" onclick="downloadCSV(event, <?php echo "'" . $_GET['table'] . "'" ?>)"><i class="fa fa-cloud-download fa-2x" aria-hidden="true"></i></button>
        <a href='<?php echo BATCH_TABLE_URL_PATH . "?table=" . $_GET['table']; ?>' title="Modify table in batch" class="btn btn-info"><i class="fa fa-magic fa-2x" aria-hidden="true"></i></a>
        <a href="<?php echo ADD_TABLE_URL_PATH . '?table=' . $_GET['table']; ?>" title="Modify table setup" class="btn btn-warning"><i class="fa fa-cog fa-2x" aria-hidden="true"></i></a>
        <button type="button" title="Delete table" class="btn btn-danger" onclick="deleteTableModal(<?php echo "'" . $_GET['table'] . "'" ?>)"><i class="fa fa-trash fa-2x" aria-hidden="true"></i></button>
    </div>
</div>
 
<?php } ?>
