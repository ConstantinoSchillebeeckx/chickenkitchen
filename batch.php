
<?php require_once 'header.php'; ?>
<?php require_once 'nav.php'; ?>
<?php require_once "functions.php"; ?>


<?php /*


*/ ?>

<div class="alertContainer"></div> <!-- automatically filled by showMsg() -->

<div id="body">
    <div class="row">
        <div class="col-sm-12">
            <h1><?php echo isset($_GET['table']) ? "Batch edit table <code>" . $_GET['table'] . "</code>" : "View tables" ?></h1>
        </div>
    </div> 
    <?php isset($_GET['table']) ? batch_form() : list_tables() ?>
</div>


<?php require_once 'footer.php'; ?>
