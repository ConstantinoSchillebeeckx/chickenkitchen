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
        <input class="btn btn-info pull-right" type="button" value="Go!" id="searchSubmit"></input>
      </div>
    </div>
  </div>
</div>

<script>

    // get the DB structure
    if (typeof db == 'undefined') {
        var tmp = <?php echo get_db_setup()->asJSON( $_GET['table'] ); ?>;
        db = tmp.struct; // global
    }

    var filters = setup_query_builder_filter( db );

    // build the query builder
    jQuery('#query-builder').queryBuilder({
        filters: filters
    });

    jQuery('#searchSubmit').on('click', function() {
        var result = jQuery('#query-builder').queryBuilder('getSQL', 'named');

        // getDBdata();

        if (!jQuery.isEmptyObject(result)) {
            alert(JSON.stringify(result, null, 2));
        }
    });

</script>
