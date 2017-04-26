<?php

/**
Handles the HTML and JS for the advanced table search.

Adds a bootstrap panel that can be opened and closed.
Parses the DB structure to generate all the proper filters
and options for the query builder.

*/

?>

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne"><i class="fa fa-search" aria-hidden="true" style="margin-right: 5px"></i>Search</a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
      <div class="panel-body">
        <div id="query-builder"></div> <!-- will get filled by JS -->
        <input style="margin-top:30px;" class="btn btn-info pull-right" type="button" value="Go!" id="searchSubmit"></input>
      </div>
    </div>
  </div>
</div>

<script>

    // get the DB structure
    var tmp = <?php echo get_db_setup()->asJSON( $_GET['table'] ); ?>;
    var fk_vals = <?php echo json_encode(get_db_setup()->get_fk_vals( $_GET['table'] )); ?>;
    var db = tmp.struct;

    var filters = setup_query_builder_filter( db, fk_vals );

    // build the query builder
    jQuery('#query-builder').queryBuilder({
        filters: filters
    });

    jQuery('#searchSubmit').on('click', function() {
        var filter = jQuery('#query-builder').queryBuilder('getSQL', 'named');

        // query database for table data
        // vars are all set by build_table() in functions.php
        jQuery('#datatable').DataTable().destroy(); // destroy so that we can re-query table
        getDBdata(table, pk, columnFormat, filter, null, true);
    });

</script>
