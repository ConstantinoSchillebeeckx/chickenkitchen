
<?php require_once 'header.php'; ?>
<?php require_once 'nav.php'; ?>
<?php require_once "functions.php"; ?>
<?php require_once "config/db.php"; ?>


<?php /*

This is the main page most users will spend their time on, it displays the contents of a table defined by the
$_GET['table'] parameter; if $_GET['table'] not set, page will show list of tables available for viewing.

Primarily, it will call the build_table() function which will generate all the HTML and JS necessary for viewing
the table contents.  Specifically, build_table() will generate the HTML <table> structure for the given table,
set numerous JS variables (table, columns, filter, hidden) and then do an AJAX request using JS function
getDBdata() - this AJAX call will populate the table.

Furthermore, various modals are loaded (after build_table() is called) which will handle the "Actions" of a table
such as deleteing an item, viewing history or editing the item.  These actions are accessed by the buttons
available in the last column of the table which are automatically generated with the build_table() function. These
modals will expect various JS variables to be set, which is why build_table() must be called first.

On the server side, the server_processing.php script will receive all the sent paramters and use the
ssp.class.php script to query the database and format all the results properly for use with datatables.net
library

*/ ?>

<div class="alertContainer"></div> <!-- automatically filled by showMsg() -->

<div class="row">
    <div class=<?php echo isset($_GET['table']) ? "col-sm-8" : "col-sm-12" ?>>
        <h1><?php echo isset($_GET['table']) ? "Viewing table <code>" . $_GET['table'] . "</code>" : "View tables" ?></h1>
    </div>
    <?php if( isset($_GET['table']) ) { ?>
    <div class="col-sm-4" style="margin-top:20px">
        <div class="btn-group pull-right" role="group" aria-label="...">
            <button type="button" title="Add item to table" class="btn btn-info" onclick="addItemModal()"><i class="fa fa-plus fa-2x" aria-hidden="true"></i></button>
            <button type="button" title="Download table as CSV" class="btn btn-info" onclick="downloadCSV(<?php echo "'" . $_GET['table'] . "'" ?>)"><i class="fa fa-cloud-download fa-2x" aria-hidden="true"></i></button>
            <button type="button" title="Delete table" class="btn btn-danger" onclick="deleteTableModal(<?php echo "'" . $_GET['table'] . "'" ?>)"><i class="fa fa-times fa-2x" aria-hidden="true"></i></button>
            <a href="<?php echo ADD_TABLE_URL_PATH . '?table=' . $_GET['table']; ?>" title="Modify table setup" class="btn btn-warning"><i class="fa fa-cog fa-2x" aria-hidden="true"></i></a>
        </div>
    </div>
    <?php } else { list_tables(); } ?>
</div> 

<?php if ( isset( $_GET['table'] ) ) build_table( $_GET['table'] ) ?>

<?php include_once("modals.php"); // must be included after table vars are defined ?>



<?php require_once 'footer.php'; ?>
