<!DOCTYPE html>
<?php 
require_once "config/db.php";
?>

<html lang="en"> 

    <head>

        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="chicken kitchen">
        <title>Chicken Kitchen</title>

        <!-- CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs/pdfmake-0.1.18/dt-1.10.13/b-1.2.4/b-colvis-1.2.4/b-html5-1.2.4/b-print-1.2.4/r-2.1.0/datatables.min.css" />
        <link rel="stylesheet" type="text/css" href='https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css' />
        <link rel="stylesheet" type="text/css" href='http://www.meepmoop.com/chickenkitchen/bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css' />
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous" />
        <link rel="stylesheet" type="text/css" href='https://cdn.jsdelivr.net/jquery.query-builder/2.4.0/css/query-builder.default.min.css' />
        <link rel="stylesheet" type="text/css" href='http://www.meepmoop.com/chickenkitchen/css/styles.css' />
 

        <!-- JS -->
        <script src="https://code.jquery.com/jquery-3.1.1.min.js" integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8=" crossorigin="anonymous"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/v/bs/pdfmake-0.1.18/dt-1.10.13/b-1.2.4/b-colvis-1.2.4/b-html5-1.2.4/b-print-1.2.4/r-2.1.0/datatables.min.js"></script> 
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
        <script type="text/javascript" src="http://www.meepmoop.com/chickenkitchen/js/table.js"></script>
        <script type="text/javascript" src="http://www.meepmoop.com/chickenkitchen/meowcow/js/plugin.js"></script> <!-- this will render the chart plot button -->
        <script type="text/javascript" src="http://www.meepmoop.com/chickenkitchen/TableCSVExport/jquery.TableCSVExport.js"></script>
        <script type="text/javascript" src="http://www.meepmoop.com/chickenkitchen/moment/moment.js"></script>
        <script type="text/javascript" src="http://www.meepmoop.com/chickenkitchen/bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery.query-builder/2.4.1/js/query-builder.standalone.min.js"></script>
        <!-- <script src="bootstrap-slider/src/js/bootstrap-slider.js" crossorigin="anonymous"></script> -->
        <script type="text/javascript">var DEBUG = <?php echo DEBUG; ?></script>
    </head>


    <div class="container" style="margin-top:60px;"> <!-- closed in footer -->
