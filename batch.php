
<?php require_once 'header.php'; ?>
<?php require_once 'nav.php'; ?>
<?php require_once "functions.php"; ?>


<?php /*


*/ ?>

<div class="alertContainer"></div> <!-- automatically filled by showMsg() -->

<div id="body">
    <div class="row">
        <div class=<?php echo isset($_GET['table']) ? "col-sm-8" : "col-sm-12" ?>>
            <h1><?php echo isset($_GET['table']) ? "Viewing table <code>" . $_GET['table'] . "</code>" : "View tables" ?></h1>
        </div>
        <?php if( isset($_GET['table']) ) { ?>
            <!-- code here -->
        <?php } else { list_tables(); } ?>
    </div> 
</div>

<?php include_once("modals.php"); // must be included after table vars are defined ?>



<?php require_once 'footer.php'; ?>
