<?php 
require_once "db.class.php"; // Database class
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'functions.php';
setup_session();
?>

<?php /*

Primary page for making all batch adjustments to the database. Based on the table setup and the action
being requested, different table columns will be required:

batch add:
- naturally, all required columns are required when uploading

batch update:
- we need to be able to uniquely identify each row being updated. therefore we must ensure that a 
unique, required column exists in the table. if this is not the case, it will be impossible to make
updates to the row (since we can't uniquely identify which one is being updated).

batch delete:
- like update, we need to uniquely identify each row with a unique, required column. however, if it
does not exist, we can have the user upload all the columns and match against that. it is assumed that
all columns collectively make it unique; in the case of duplicate rows in the database, both should be
deleted if requested.

*/ ?>

<div class="alertContainer"></div> <!-- automatically filled by showMsg() -->

<div id="body">
    <div class="row">
        <div class="col-sm-12">
            <h1><?php echo isset($_GET['table']) ? "Batch modify table: <code>" . $_GET['table'] . "</code>" : "View tables" ?></h1>
        </div>
    </div> 
    <?php isset($_GET['table']) ? batch_form( $_GET['table'] ) : list_tables() ?>
</div>

<?php require_once 'footer.php'; ?>
