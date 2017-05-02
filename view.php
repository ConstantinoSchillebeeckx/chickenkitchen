<?php 
require_once "db.class.php"; // Database class
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'header.php'; // hacky way of loading things with WP
require_once 'functions.php';
setup_session();
?>


<?php 

/*

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

<div id="body">
    <div class="row">
        <div class=<?php echo isset($_GET['table']) ? "col-sm-7" : "col-sm-12" ?>>
            <h1><?php echo isset($_GET['table']) ? "Viewing table <code>" . $_GET['table'] . "</code>" : "View tables" ?></h1>
        </div>
        <?php isset($_GET['table']) ? include('table_header.php') : list_tables(); ?>
    </div> 

    <?php if ( isset( $_GET['table'] ) ) {
        include("table_search.php");
        build_table( $_GET['table'] );
    } ?>
</div>

<?php include_once("modals.php"); // must be included after table vars are defined ?>



<?php require_once 'footer.php'; ?>
