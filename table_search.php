<?php

/**

Handles the HTML for the advanced table search.

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
        var table = '<?php echo $_GET['table']; ?>';
        var tmp = <?php echo get_db_setup()->asJSON( $_GET['table'] ); ?>;
        db = cleanDB(tmp).struct;
        console.log(db);
    }

    // build filter for query builder
    var filters = [];
    for (var field in db) {
        var field_dat = db[field];
    
        if (field_dat.hidden == false) {
            var tmp = {};
            tmp.id = field;
            tmp.label = field;
            type = field_dat.type; // varchar, datetime, etc

            // get field type
            if (type.indexOf('varchar') !== -1) {
                type = 'string';
            } else if (type.indexOf('datetime') !== -1) {
                if (field_dat.comment.column_format == 'date') { // date type also stored as datetime on backend
                    type = 'date';
                } else {
                    type = 'datetime';
                }
            } else if (type.indexOf('float') !== -1) {
                type = 'double';
            } else if (type.indexOf('int') !== -1) {
                type = 'integer';
            }
            tmp.type = type;

            // set operators and options based on type
            if (tmp.type == 'string') {
                tmp.operators = ['equal', 'not_equal', 'in', 'not_in', 'begins_with', 'not_begins_with', 'contains', 'not_contains', 'ends_with', 'not_ends_with', 'is_empty', 'is_not_empty', 'is_null', 'is_not_null'];
            } else if (tmp.type == 'integer' || tmp.type == 'double') {
                tmp.operators = ['equal', 'not_equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between', 'not_between', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty'];
            } else if (tmp.type == 'date' || tmp.type == 'datetime') {
                tmp.operators = ['equal', 'not_equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between', 'not_between', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty'];
                tmp.validation = {format: 'YYYY/MM/DD'};
                tmp.plugin = 'datepicker';
                tmp.plugin_config = {format: 'yyyy/mm/dd', todayBtn: 'linked', todayHighlight: true, autoclose: true};
            }

            filters.push(tmp);
        }

    }

    // build the query builder
    jQuery('#query-builder').queryBuilder({
        filters: filters
    });

    jQuery('#searchSubmit').on('click', function() {
      var result = jQuery('#query-builder').queryBuilder('getSQL', 'named');
      
      if (!jQuery.isEmptyObject(result)) {
        alert(JSON.stringify(result, null, 2));
      }
    });

</script>
