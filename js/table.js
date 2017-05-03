/*

Function called every time the checkbox is selected
for automatically filling field with date or
date/time.

Used to uncheck/disable the required/unique
checkboxes since they don't make sense when 
automatically setting the date.

*/
function toggleDate(checkBox) {

    var fieldNum = checkBox.name.split('-').slice(-1)[0];

    // if user selects checkbox, uncheck
    // the required and unique and disable
    if (jQuery(checkBox).is(':checked')) {
        var sel = jQuery("[name=unique-" + fieldNum + "]").prop('checked', false)
        sel.prop('disabled', true);
        sel.parent().addClass( "text-muted" );

        var sel = jQuery("[name=required-" + fieldNum + "]").prop('checked', false)
        sel.prop('disabled', true);
        sel.parent().addClass( "text-muted" );

        jQuery("input[type=text][name=default-" + fieldNum + "]").prop('disabled', true).addClass( "text-muted" );
        jQuery('#fieldDefault').addClass( "text-muted" );
        jQuery('#fieldRequired').addClass( "text-muted" );
        jQuery('#fieldUnique').addClass( "text-muted" );
    } else {
        var sel = jQuery("[name=unique-" + fieldNum + "]")
        sel.prop('disabled', false)
        sel.parent().removeClass( "text-muted" );

        var sel = jQuery("[name=required-" + fieldNum + "]")
        sel.prop('disabled', false)
        sel.parent().removeClass( "text-muted" );

        jQuery("input[type=text][name=default-" + fieldNum + "]").prop('disabled', false).removeClass( "text-muted" );
        jQuery('#fieldDefault').removeClass( "text-muted" );
        jQuery('#fieldRequired').removeClass( "text-muted" );
        jQuery('#fieldUnique').removeClass( "text-muted" );
    }

}


/*

Function called when long version of string selected
by the user. It is used to unselect and disable
the unique field since a long string type (> 191 char)
cannot have the unique SQL index on it

*/
function toggleLongString(checkBox) {

    // if user selects checkbox, uncheck
    // the required and unique and disable
    if (jQuery(checkBox).is(':checked')) {
        var sel = jQuery("[name^=unique-]")
        sel.prop('checked', false).prop('disabled', true);
        sel.parent().addClass('text-muted');
        jQuery('#fieldUnique').addClass( "text-muted" );
    } else {
        sel = jQuery("[name^=unique-]")
        sel.prop('disabled', false);
        sel.parent().removeClass('text-muted');
        jQuery('#fieldUnique').removeClass( "text-muted" );
    }

}



/* Send AJAX request to sever

Will send an AJAX request to the server and properly show/log
the response as either a message to the user or an error
message in the console.

Paramters:
----------
- data : obj
         data object to send to the server

Returns:
--------
obj to be used with showMsg(), has keys:
- msg : string to display in message
- status: false for error, true for no error

will set globals ajaxStatus (true on success, false otherwise) and
ajaxResponse as well as run the callback on complete.

*/
function doAJAX(data, callback) {

    ajaxStatus = false; // global!
    ajaxResponse = ''; // global!
    var button = jQuery("#addTableSubmit");

    // send via AJAX to process with PHP
    jQuery.ajax({
            url: 'http://meepmoop.com/chickenkitchen/ajax.php',
            type: "GET",
            data: data,
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
            beforeSend: function() {

                // update button text to loading and disable it
                if ( data['action'] == 'addTable' ) {
                    button.html('<i class="fa fa-circle-o-notch fa-spin fa-lg" aria-hidden="true"></i> working ...');
                    button.attr('disabled', true);
                }
            },
            success: function(response) {
                ajaxStatus = true;
                ajaxResponse = response;
            },
            error: function(xhr, status, error) {
                ajaxResponse = xhr.responseText;
                if (DEBUG) console.log(xhr);
                if (DEBUG) console.log(status);
                if (DEBUG) console.log(error);
            },
            complete: function() {

                // reset button
                if (typeof button !== 'undefined') {
                    button.html('Submit');
                    button.attr('disabled', false);
                }

                callback();
            }
    });

}





/* Will parse form on the edit table page into obj for use with AJAX

Parameters:
===========
- sel : str
        selector for form (e.g. form)
- table : str
        name of original table
- db : obj
       current database setup (optional)

Returns:
========
- obj : with form input field values in the form
{'field-x': {'original':{...}, 'update':{} }, ..., 'table_name': {'original': xx, 'update': xx } }

*/
function getTableSetupForm(sel, table, db) {

    var data = {};

    data['table_name'] = {'original':table, 'update':''}; // table is global

    var formData = jQuery(sel).serializeArray(); // form data


    jQuery.each(formData, function() {
        var val = this.value;
        var ix = this.name.split('-')[1];
        var dat_name = this.name.split('-')[0];

        if (typeof ix !== 'undefined') { // skip table name

            if (val == 'on') {
                val = true;
            } else if (val == '') {
                val = null;
            }

            if (!('field-' + ix in data)) { // if field-X key not already in obj
                var fieldNum = parseInt(ix)-1;
                if (db !== null && fieldNum in db['fields']) {
                    var original_name = db['fields'][fieldNum];
                    var original_dat = db['struct'][original_name];
                    data['field-' + ix] = {'original':original_dat, 'update':{}}
                } else { // if newly added field
                    data['field-' + ix] = {'original':null, 'update':{}}
                }
            }
            data['field-' + ix]['update'][dat_name] = val;


        } else {

            if (dat_name == 'table_name') data['table_name']['update'] = val;

        }
    })

    return data

}



/* Will parse form on page into obj for use with AJAX

Parameters:
===========
- sel : str
        selector for form (e.g. form)

Returns:
========
- obj : with form input field values {name: val}

*/
function getFormData(sel) {

    var data = {};

    var formData = jQuery(sel).serializeArray(); // form data

    jQuery.each(formData, function() {
        var val = this.value;
        if (val == 'on') {
            val = true;
        } else if (val == '') {
            val = null;
        }
        data[this.name] = val;
    })

    return data

}



/*
Event handler for radio button change on batch update screen.
Will display various information based on which radio button is
selected which will help the user understand which columns are
required for uploading a file.

Function assumes there's a div available with id=radioHelp

*/
function radioSelect(selectedRadio) {
   
    var div = jQuery('#radioHelp');
    var content = ''; 

    var required = [];
    var pk = '';
    for ( var field in db ) {
        if (db[field]['hidden'] == false && db[field]['required'] == true) {
            required.push(field);
            if (db[field]['key'] == 'UNI' && pk == '') pk = field; // grab only first on
        }

    }
    jQuery('#confirmEdit').prop('disabled', false);

    if (selectedRadio.value == 'batchAdd') {
        content = 'To add data to this table, please upload a file with atleast the following columns (any others will be ignored): <code>' + required.join('</code>,<code>') + '</code>';
    } else if (selectedRadio.value == 'batchEdit' ) {
        content = '';
        if (pk != '') {
            content += 'This table does have a unique, required column (<code>' + pk + '</code>); when uploading a file, this column along with any data to be updated are the only required information needed. If you\'d like to edit the data in the column <code>' + pk + '</code> simply add an additional column to your upload file with the header name <code>Rename</code> and place the updated data there.';
        } else {
            content += 'In order to edit multiple rows in this table, each row must be uniquely identifiable. This table does not have any unique, required columns; therefore <b>it is not possible to batch update</b> the table.'
            jQuery('#confirmEdit').prop('disabled', true);
        }
    } else if (selectedRadio.value == 'batchDelete' ) {
        content = 'In order to archive multiple rows in this table, each row must be uniquely identifiable.';
        if (pk != '') {
            content += ' This table does have a unique, required column (<code>' + pk + '</code>); when uploading a file, this is the only required information needed.'
        } else {
            content += ' This table does not have any unique, required columns; therefore <b>all columns must be provided</b> when deleting a row.'
        }
    }

    div.html(content);

}


/* Generate popover hover fields to detail field information

Will provide all the available field information (type, default, etc)
for a field through a JS popover. Function used after build_table()
is called and/or in the modals (add, delete, update item).

Function assumes that any field that requires a popover will have a
span surrounding it with the id popover-XXX where XXX is the field name.

Params:
 obj db - database setup with keys as fields and obj as values
 obj fk_vals - key = col, value = fk values it can have

*/
function make_popover_labels( db, fk_vals ) {

    fields = jQuery('span[class^="popover"]'); // fields that require a popover

    // setup popover for each field
    for (var i = 0; i < fields.length; i++) {

        var sel = '.' + fields[i].className.split(' ')[0]; // assumes popover-XXX class is first
        var name = sel.split('-')[1];
        var dat = db[name];

        if (typeof dat !== 'undefined') {

            var title = dat.comment.name;
            var description = dat.comment.description;
            var defau = dat['default'];
            var required = dat.required;
            var unique = dat.key == 'UNI';
            var type = dat.type;
            var is_fk = dat.is_fk;
            var fk_ref = dat.fk_ref;
            var length = dat.length;

            var content = '';
            if (typeof description !== 'undefined' && description != '') content += 'Description: ' + description + '<hr>'; // description
            if (defau) content += 'Default: <code>' + defau + '</code><br>'; // default value
            content += 'Required: <code>' + (required ? 'true' : 'false') + '</code><br>'; // required
            content += 'Unique: <code>' + (unique ? 'true' : 'false') + '</code><br>'; // unique
            if (length) content += 'Length: <code>' + length + '</code><br>'; // length
           
            // type 
            if (type.includes('varchar')) {
                content += 'Type: <code>string</code><br>';
            } else if (type.includes('int')) {
                content += 'Type: <code>integer</code><br>';
            } else if (type.includes('float')) {
                content += 'Type: <code>float</code><br>';
            } else if (type.includes('datetime')) {
                if (dat.comment.column_format == 'date') {
                    content += 'Type: <code>date</code><br>';
                } else {
                    content += 'Type: <code>timestamp</code><br>';
                }
            }
               
            if (is_fk) {
                content += 'Foreign key: <code>' + fk_ref + '</code><br>'; // fk
                content += 'Allowable values: <code>' + Object.values(fk_vals) + '</code><br>';
            }

            jQuery(sel).popover({
                html: true,
                content: content,
                trigger: "hover",
                title: title,
                container: 'body'
            });
        }

    }

}



/* Catch AJAX response and show message if needed

Will generate a dismissable alert div at the top
of the page which will hide after 3 seconds

Parameters:
===========
- dat : object
        -> msg : msg to display
        -> status : bool - true if success msg, false if error msg
        -> hide : bool - true will auto-hide message after 3s (if key is ommited, message will hide)
- sel : str
        selector into which alert is placed (will do a jQuery prepend()); if none provided it will be ".alertContainer"
*/
function showMsg(dat, sel) {

    if (sel == null) { sel = '.alertContainer' };

    jQuery('#alertDiv').remove(); // remove any alerts already present

    if (typeof dat.msg !== 'undefined') {
        var type = dat.status ? 'success' : 'danger';
        var msg = dat.msg;
        var hide = dat.hide; // true will auto-remove the message, false will keep message on scree
    } else {
        var type = 'danger';
        var msg = "An unknown error occurred, please try again.";
        var hide = false;
    }
    var alertDiv = '<div id="alertDiv" class="alert alert-' + type + ' alert-dismissible" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' + msg + '</div>';

    jQuery( alertDiv ).prependTo( sel );

    // automatically hide msg after 3s
    var timeout = setTimeout(function () {
        jQuery(".alert").fadeTo(2000, 500).slideUp(500, function ($) {
            jQuery(".alert").remove();
        });
    }, 3000);

    if (!hide) {
        clearTimeout(timeout);
    }

}









/* 
AJAX call for retreiving DB data

When script is called on a page with a table that
has the ID 'datatable', AJAX will be used to query
the DB and fill the HTML table with data. This function
will also hide any hidden columns.

The function build_table() [functions.php] is used to
build the HTML table needed to display the data
queried by AJAX.  Both must have the same columns.

Parameters (set by build_table()):
- table : table name to query
- columnFormat (obj) : keys are columns, values are format (if available, e.g. 'date')
- pk : primary key of table
- filter : (optional) filter for table in format {col: val}
- tableID: (optional) the ID for the table into which to put data, defaults to #datatable
- hasHistory: (optional) bool if table has history counter part
- acct (str): database name to which to connect
*/


function getDBdata(table, pk, columnFormat, filter, tableID, hasHistory, acct) {

    var colWidth = '40px'; // column width for "Action" column

    if (!tableID || tableID == null) tableID = '#datatable';

    // html for Action button column
    if (tableID == '#datatable') {
        var buttonHTML = '<div class="btn-group" role="group">';
        if (hasHistory) {
            buttonHTML += '<button onclick="historyModal(this)" type="button" class="btn btn-info btn-xs" title="History"><i class="fa fa-history" aria-hidden="true"></i></button>'
            colWidth = '70px';
        } else {
            colWidth = '50px';
        }
        buttonHTML += '<button onclick="editModal(this)" type="button" class="btn-xs btn btn-warning" title="Edit"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></button>'
        buttonHTML += '<button onclick="deleteModal(this)" type="button" class="btn-xs btn btn-danger" title="Delete"><i class="fa fa-times" aria-hidden="true"></i></button>'
        buttonHTML += '</div>';
    } else if (tableID == '#historyTable') {
        var buttonHTML = '<div class="text-center">';
        buttonHTML += '<button onclick="revertHistory(event, this)" type="button" class="btn btn-info btn-xs" title="History"><i class="fa fa-undo" aria-hidden="true"></i></button>'
        buttonHTML += '</div>';
    }

    jQuery.fn.dataTable.ext.errMode = 'throw'; // Have DataTables throw errors rather than alert() them


    // variables set with build_table() defined in functions.php
    var data =  {
        "action": "viewTable", 
        "table": table, 
        "cols": columnFormat,
        "pk" : pk,
        "filter": filter,
        "acct": acct,
    }

    // setup columnDefs
    var colDefs = [];
    for (var i = 0; i < Object.keys(columnFormat).length; i++) {
        colDefs[i] = {};

        var colName = Object.keys(columnFormat)[i];
        var colFormat = columnFormat[colName];

        colDefs[i]['name'] = colName;
        colDefs[i]['targets'] = i;

        // hide any columns listed in hidden
        // also make them non-searchable
        if (colFormat === 'hidden') { 
            colDefs[i]['visible'] = false;
            colDefs[i]['searchable'] = false;
        }

    }

    // set Action column data to empty since we are automatically adding buttons here
    if (user_role !== 'subscriber') { 
        colDefs.push({ // https://datatables.net/examples/ajax/null_data_source.html
            "targets": -1,
            "name": 'Action',
            "data": null,
            "defaultContent": buttonHTML,
            "width": colWidth,
            "orderable": false,
        });
    }

    // crusty workaround for the issue: https://datatables.net/manual/tech-notes/3
    // first viewing the history modal will initialize the table, looking at the modal
    // again (e.g. after an edit) will cause this error
    // work around is to destroy the table initialization and recreate it each time ... :(
    if (typeof historyTable !== 'undefined' && jQuery.fn.dataTable.isDataTable( '#historyTable' )) {
        historyTable.destroy();
    }

    if (DEBUG) console.log(data);

    historyTable = jQuery(tableID).DataTable( {
        "retrieve": true,
        "processing": true,
        "serverSide": true,
        "lengthMenu": [[10, 50, 100, -1], [10, 50, 100, "All"]],
        "ajax": {
            "url": '/chickenkitchen/ssp.class.php',
            "data": data,
            "complete": function(d) {
                if (tableID == '#historyTable') {
                    // disable last revert button, since it doesn't make sense to revert to itself
                    jQuery("#historyTable tbody").find("tr:last").find("td:last").find("button").prop('disabled', true)
                }
                responseDat = d.responseJSON.data; // store data as global for use with meowcow (plotting)
            },
            },
        "columnDefs": colDefs,
        "paging": tableID == '#datatable',
        "searching": tableID == '#datatable',
        "info": tableID == '#datatable',
    } );


    // destroy global so that we only set this for history table
    // see workaround above
    if (tableID == '#datatable') historyTable = null;

};


/**

Function used to setup the query builder for advanced search. Will
create the 'filter' option for the builder based on the field types
of the current table.

Param: 
db object
fk_vals object with fk fields as keys and list of values for value    

Returns: json 'filter' option

*/
function setup_query_builder_filter( db, fk_vals ) {


    // build filter for query builder
    var filters = [];
    for (var field in db) {
        var field_dat = db[field];
    
        if (field_dat.hidden == false) {
            var tmp = {};
            tmp.id = field_dat.comment.name;
            tmp.label = field;
            type = field_dat.type; // varchar, datetime, etc
            is_fk = field_dat.is_fk; // true if field is a foreign key


            // get field type
            if (type.indexOf('varchar') !== -1) {
                type = 'string';
            } else if (type.indexOf('datetime') !== -1) {
                type = 'datetime';
            } else if (type.indexOf('date') !== -1) {
                type = 'date';
            } else if (type.indexOf('float') !== -1) {
                type = 'double';
            } else if (type.indexOf('int') !== -1) {
                type = 'integer';
            }
            tmp.type = type;

            // if field is a FK, set values for dropdown
            if (is_fk) {
                tmp.values = fk_vals[field];
                tmp.input = 'select';
            }

            // set operators and options based on type
            if (tmp.type == 'string') {
                tmp.operators = ['equal', 'not_equal', 'in', 'not_in', 'begins_with', 'not_begins_with', 'contains', 'not_contains', 'ends_with', 'not_ends_with', 'is_empty', 'is_not_empty', 'is_null', 'is_not_null'];
            } else if (tmp.type == 'integer' || tmp.type == 'double') {
                tmp.operators = ['equal', 'not_equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between', 'not_between', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty'];
                /*tmp.plugin = 'slider';
                tmp.plugin_config = {min: 0, max: 100, value: 0};
                tmp.valueSetter = function(rule, value) {
                  if (rule.operator.nb_inputs == 1) value = [value];
                  rule.$el.find('.rule-value-container input').each(function(i) {
                    $(this).slider('setValue', value[i] || 0);
                  });
                }
                tmp.valueGetter = function(rule) {
                  var value = [];
                  rule.$el.find('.rule-value-container input').each(function() {
                    value.push($(this).slider('getValue'));
                  });
                  return rule.operator.nb_inputs == 1 ? value[0] : value;
                }*/
            } else if (tmp.type == 'date' || tmp.type == 'datetime') {
                tmp.operators = ['equal', 'not_equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between', 'not_between', 'is_null', 'is_not_null', 'is_empty', 'is_not_empty'];
                tmp.validation = {format: 'YYYY-MM-DD'};
                tmp.plugin = 'datetimepicker';
                if (tmp.type == 'date') {
                    tmp.plugin_config = {format: 'YYYY-MM-DD'};
                } else {
                    tmp.plugin_config = {format: 'YYYY-MM-DD HH:mm:ss'};
                }
            }

            filters.push(tmp);
        }

    }

    return filters;
}






/* Function called when action button delete clicked

Because none of the buttons have a specified ID, we
need to use some jQuery to figure out which button
was clicked and thus which row the user is trying
to act on.  This function will figure out the PK ID
of the first column item and update the modal with
its value.  It will then display the modal as well as
set the proper onclick event for the confirm delete button

Function assumes that the first column of the databale is
the hidden _UID - this will be used to identify which
item to delete.

Parameters:
- sel : will be the 'a' selection of the button that was clicked
*/
function deleteModal(sel) {

    // lookup data for the row that was selected by button click
    var rowNum = jQuery(sel).closest('tr').index();
    var rowDat = jQuery('#datatable').DataTable().row(rowNum).data();

    jQuery("#deleteID").html( "<code>" + rowDat[1] + "</code>" ); // set PK message
    jQuery('#deleteModal').modal('toggle'); // show modal
    jQuery("#confirmDelete").attr("onclick", "deleteItem('" + rowDat[0] + "')");
}



/* Function called when action button 'edit' clicked
Because none of the buttons have a specified ID, we
need to use some jQuery to figure out which button
was clicked and thus which row the user is trying
to act on.  This function will figure out the ID
of the first column item and update the modal with
its value.  It will then display the modal as well as
set the proper onclick event for the confirm edit button
Parameters:
- sel : will be the 'a' selection of the button that was clicked
*/
function editModal(sel) {


    // lookup data for the row that was selected by button click
    var rowNum = jQuery(sel).closest('tr').index();
    var rowDat = parseTableRow(rowNum);
    var cols = Object.keys(rowDat);


    // get values from row and fill modal
    originalRowVals = jQuery.extend({}, rowDat); // create a copy, set to global for comparison to edited values
    for (var col in rowDat) {
        var cell = rowDat[col];
        jQuery('input[id="' + col +'"]').val(cell);
    }

    jQuery("#editID").html( "<code>" + rowDat[cols[1]] + "</code>" ); // set PK message
    jQuery('#editModal').modal('toggle'); // show modal

    jQuery("#confirmEdit").attr("onclick", "editItem(event, '" + rowDat['_UID'] + "')");
}







/* Function called when use confirms to edit an item
Function will make an AJAX call to the server to delete
the selected item.
Parameters:
- pk_id : cell value of the PK user wants to delete
*/
function revertHistory(event, sel) {

    event.preventDefault(); // cancel form submission

    // lookup data for the row that was selected by button click
    var rowNum = jQuery(sel).closest('tr').index();
    var rowDat = parseTableRow(rowNum, '#historyTable', true);
    var uid = rowDat['_UID']; // this is the UID (row) we want to revert to
    delete rowDat['Action'];
    delete rowDat['Timestamp'];
    delete rowDat['User'];

    console.log(rowDat)

    var data = {
            "action": "revertItem", 
            "table": table, // var set by build_table() in functions.php
            "original_row": originalRowVals, // set in historyModal()
            "dat": rowDat, // form values
            "_UID": uid,
    }

    
    if (DEBUG) console.log(data);

    // send data to server
    doAJAX(data, function() {
        jQuery('#historyModal').modal('toggle'); // hide modal
        if (ajaxStatus) {
            jQuery('#datatable').DataTable().draw('page'); // refresh table
        }
        showMsg(ajaxResponse);
        if (DEBUG) console.log(ajaxResponse);
    });

}









/* Function called when use confirms to edit an item
Function will make an AJAX call to the server to delete
the selected item.
Parameters:
- pk_id : cell value of the PK user wants to delete
*/
function editItem(event, pk_id) {

    event.preventDefault(); // cancel form submission
    jQuery('#submit_handle').click(); // needed to validate form

    if (jQuery('form')[0].checkValidity()) { // if valid, load
        var data = {
                "action": "editItem", 
                "table": table, // var set by build_table() in functions.php
                "pk": pk, // var set by build_table() in functions.php
                "original_row": originalRowVals, // var set in editModal()
                "dat": getFormData('#editItemForm'), // form values
                "pk_id": pk_id,
        }
        
        if (DEBUG) console.log(data);


        // send data to server
        doAJAX(data, function() {
            jQuery('#editModal').modal('toggle'); // hide modal
            if (ajaxStatus) {

                jQuery('#datatable').DataTable().draw('page'); // refresh table
            }
            showMsg(ajaxResponse);
            if (DEBUG) console.log(ajaxResponse);
        });

    }
}




/* onclick event handler for table batch edit form

This handles all cases for batch actions including
add, edit and delete.

Submits AJAX request to server with all proper information
and then displays message to user based on response.

*/
function batchFormSubmit( event ) {

    event.preventDefault(); // cancel form submission
    jQuery('#submit_handle').click(); // needed to validate form

    var button = jQuery('#confirmEdit') // submit button

    if (jQuery('form')[0].checkValidity()) { // if valid, load

        // generate form for XHR send
        var formData = new FormData($('#batchForm')[0]);


        // since we're uploading a file, our AJAX request needs to be
        // modified a bit to be able to use FormData/XHR
        jQuery.ajax({
            url: 'http://meepmoop.com/chickenkitchen/ajax.php',
            type: 'POST',
            xhr: function() {  // custom xhr
                myXhr = $.ajaxSettings.xhr();
                return myXhr;
            },
            beforeSend: function() {

                // update button text to loading and disable it
                button.html('<i class="fa fa-circle-o-notch fa-spin fa-lg" aria-hidden="true"></i> working ...');
                button.attr('disabled', true);
            },
            complete: function() {

                // reset button
                button.html('Submit');
                button.attr('disabled', false);
            }, 
            success: completeHandler = function(response) {

                console.log(response)                
                var ajaxResponse = JSON.parse(response);
                showMsg(ajaxResponse);
                if (DEBUG) console.log(ajaxResponse);

            },
            error: errorHandler = function(response) {
                var ajaxResponse = JSON.parse(response);
                if (DEBUG) console.log(ajaxResponse);
            },
            data: formData,
            cache: false,
            contentType: false,
            processData: false
        }, 'json');
    }

}






/* Function called when 'history' action button clicked

Because none of the buttons have a specified ID, we
need to use some jQuery to figure out which button
was clicked and thus which row the user is trying
to act on.  This function will figure out the ID
of the first column item and update the modal with
its value.  It will then display the modal.

Parameters:
- sel : will be the 'a' selection of the button that was clicked
other parameters set by the history modal
*/
function historyModal(sel) {

    // find first col value (PK) of row from button press
    var rowNum = jQuery(sel).closest('tr').index();
    var rowVals = jQuery('#datatable').DataTable().row(rowNum).data();
    var rowDat = parseTableRow(rowNum);
    var uidVal = rowVals[0];
    var itemVal = rowVals[1];

    // get values from row, used for AJAX
    originalRowVals = jQuery.extend({}, rowDat); // create a copy, set to global for comparison to edited values
    delete originalRowVals['_UID'];

    jQuery("#historyID").html( "<code>" + itemVal + "</code>" ); // set PK message
    jQuery('#historyModal').modal('toggle'); // show modal


    // fill table with data
    // vars are defined in modal.php
    getDBdata(tableHist, pkHist, columnHist, hiddenHist, '#historyTable', false, acct);


}





/* parse table into array object

Instead of querying the DB again, we parse the viewed
datatable in cases when the edit modal needs to be
filled in

Format : [{colname: value}, {colname: value}]
Paramters:
==========
- rowIX : int
          represents index (0-based) for row requested,
          otherwise returns all rows
- table : str
          name of table to pull data out of
- allCols : bool
            if true, will return all cols; otherwise just
            the visible columns
Returns:
========
- obj with column names as keys and row values as value
*/
function parseTableRow(rowIX, table, allCols) {

    if (typeof table === 'undefined') table = '#datatable';
    if (typeof allCols === 'undefined') allCols = false;

    var table = jQuery(table).DataTable();
    var colData = table.columns().nodes();
    //need to use this so that we can grab UID (hidden field)

    var dat = {};
    table.columns().every(function(i) { 
        if (table.column(i).visible() || allCols) {
            var col = jQuery.trim(this.header().textContent);
            var cellVal = colData[i][rowIX].textContent;
            if (cellVal) {
                dat[col] = cellVal;
            }
        }
    })

    return dat;

}







/* Function called when add row button history clicked
*/
function addItemModal() {
    jQuery('#addItemModal').modal('toggle'); // show modal

    // check if we should disable the submit button
    if ( jQuery('*:contains("This field is a foreign key")' ).length !== 0) jQuery('#confirmAddItem').prop('disabled', true);
}





/* Function handles form submission from add item modal
When the 'Add item' button is clicked from the modal,
this function makes an AJAX call to the server to add
the item to the DB.
*/
function addItem( event ) {

    event.preventDefault(); // cancel form submission

    // ensure something is in the form
    if (jQuery.isEmptyObject(getFormData('#addItemForm'))) {
        showMsg({'msg':'Please specify something to add.', 'status':false, 'hide': false});
    } else {

        var data = {
                "action": "addItem", 
                "table": table, // var set by build_table() in functions.php
                "pk": pk, // var set by build_table() in functions.php
                "dat": getFormData('#addItemForm'), // form values
        }

        if (DEBUG) console.log(data)
     
        // send data to server
        doAJAX(data, function() {
            if (ajaxStatus) {
                jQuery('#datatable').DataTable().draw('page'); // refresh table
            }
            showMsg(ajaxResponse);
            if (DEBUG) console.log(ajaxResponse);
        });
    }
    
    jQuery('#addItemModal').modal('toggle'); // hide modal
}










/* Function called when user confirms to delete an item
Function will make an AJAX call to the server to delete
the selected item.
Parameters:
- pk_id : cell value of the PK user wants to delete
*/
function deleteItem(pk_id) {

    jQuery('#deleteModal').modal('toggle'); // hide modal
        
    var data = {
            "action": "deleteItem", 
            "pk_id": pk_id, 
            "table": table, // var set by build_table() in functions.php
            "pk": pk, // var set by build_table() in functions.php
    }

    // send data to server
    doAJAX(data, function() {
        if (ajaxStatus) {

            jQuery('#datatable').DataTable().draw('page'); // refresh table
        }
        showMsg(ajaxResponse);
        if (DEBUG) console.log(ajaxResponse);
    });

}









/* Called on click of download button
Will save current viewed table as CSV file
with the proper columns removed (e.g. Action).
File name will be name of table.
see: https://github.com/ZachWick/TableCSVExport
Parameters:
-----------
- tableName : str
              table name being saved to CSV
*/
function downloadCSV(event, tableName) {

    event.preventDefault();

    // get column headers and specify which to hide
    var tableHead = jQuery('#datatable').DataTable().table().header();
    var cols = jQuery(tableHead).find('tr').children();
    var allCols = [];
    var saveCols = []; // these are the columns that are exported
    var ignoreCols = ['Action'];
    jQuery.each( cols, function(i, val) { 
        var col = jQuery(val).text();
        allCols.push(col);
        if ( jQuery.inArray(col, ignoreCols) == -1) saveCols.push(col);
    } )


    jQuery('#datatable').TableCSVExport({
        delivery: 'download',
        filename: tableName + '.csv',
        header: allCols,
        columns: saveCols
    });

}


/* Called when delete table button clicked
Cancels form submission and pulls up the
delete table modal
*/
function deleteTableModal(tableName) {

    event.preventDefault(); // cancel form submission

    jQuery("#deleteTableID").html( "<code>" + tableName + "</code>" ); // set PK message
    jQuery("#confirmDeleteTable").attr("onclick", "deleteTable(event, '" + tableName + "')");

    jQuery('#deleteTableModal').modal('toggle'); // show modal

}



/* Function called by "Add field" button on add table template page
Will add all the necessarry GUI fields for defining a given field
*/
function addField() {

    fieldNum = jQuery('.nav-tabs li').length + 1; // number of fields after this tab/field has been added

    var id = 'field-' + fieldNum;
    var label = "Field #" + fieldNum;

    // if no fields present, generate parent divs
    if (fieldNum == 1) {
        jQuery('form').append('<div class="fieldTabs"></div>');
        jQuery('.fieldTabs').append('<ul class="nav nav-tabs" role="tablist" id="tabs"></ul>');
        jQuery('.fieldTabs').append('<div class="tab-content"></div>');
    }

    var fieldContent = 
            '<div class="panel-body">' +
            '<div class="form-group">' +
            '<label class="col-sm-2 control-label" id="fieldName">Field name*</label>' +
            '<div class="col-sm-3">' +
            '<input type="text" class="form-control" name="name-' + fieldNum + '" required pattern="[a-zA-Z0-9\-_ ]+" title="Letters, numbers, hypens and underscores and spaces only" maxlength="64">' +
            '</div>' +
            '<label class="col-sm-1 control-label">Type*</label>' +
            '<div class="col-sm-2">' +
            '<select class="form-control" onChange="selectChange(' + fieldNum + ')" id="type-' + fieldNum + '" name="type-' + fieldNum + '" required>' +
            '<option value="" disabled selected style="display:none;"></option>' +
            '<option value="varchar">String</option>' +
            '<option value="int">Integer</option>' +
            '<option value="float">Float</option>' +
            '<option value="date">Date</option>' +
            '<option value="datetime">Date & Time</option>' +
            '<option value="fk">Foreign</option>' +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="form-group">' +
            '<label class="col-sm-2 control-label" id="fieldDefault">Default value</label>' +
            '<div class="col-sm-3">' +
            '<input type="text" class="form-control" name="default-' + fieldNum + '">' +
            '</div>' +
            '<div class="col-sm-offset-1 col-sm-6" id="hiddenType-' + fieldNum + '">' +
            '</div>' +
            '</div>' +
            '<div class="form-group">' +
            '<label class="col-sm-2 control-label" id="fieldDescription">Description</label>' +
            '<div class="col-sm-3">' +
            '<input type="text" class="form-control" name="description-' + fieldNum + '">' +
            '</div>' +
            '</div>' +
            '<div class="form-group">' +
            '<label class="col-sm-2 control-label" id="fieldRequired">Required</label>' +
            '<div class="col-sm-3">' +
            '<label class="checkbox-inline">' +
            '<input type="checkbox" name="required-' + fieldNum + '"> check if field is required' +
            '</label>' +
            '</div>' +
            '<label class="col-sm-1 control-label" id="fieldUnique">Unique</label>' +
            '<div class="col-sm-3">' +
            '<label class="checkbox-inline">' +
            '<input type="checkbox" name="unique-' + fieldNum + '"> check if field is unique. <b>Note:</b> if you plan on making this field a reference for a foreign key, then this field must be unique.' +
            '</label>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

    jQuery('.nav-tabs').append('<li role="presentation" class="tab-' + fieldNum + '"><a href="#' + id + '" aria-controls="profile" role="tab" data-toggle="tab">' + label + '<span title="Remove field" class="close" onclick="removeField(\'' + fieldNum + '\')">×</span></a></li>');
    jQuery('.tab-content').append('<div role="tabpanel" class="tab-pane active tab-' + fieldNum + '" id="' + id + '">' + fieldContent + '</div>');

    jQuery('#tabs a:last').tab('show');

}


/*
 * Remove the tab with the given field number
 *
 * Both the tab <li> and tab content will have
 * a class of ".tab-XX" where XX is the given id
 * parameter. This function will do a remove
 * on that class and will then show the last
 * available tab.
 *
 */
function removeField(id) {

    jQuery('.tab-' + id).remove(); // remove tab and contet
    jQuery('#tabs a:last').tab('show'); // show last tab

}




/* 

Function called when "Save table" button is clicked while
editing the setup of a table

*/
function saveTable( event ) {
    
    event.preventDefault(); // cancel form submission
    jQuery('#submit_handle').click(); // needed to validate form

    // remove hidden _UID from field list
    var visibleFields = db.fields;
    if (visibleFields.indexOf('_UID') > -1) visibleFields.splice(visibleFields.indexOf('_UID'), 1);

    if (jQuery('form')[0].checkValidity()) { // if valid, load
        var data = {
                "action": "saveTable", 
                "dat": getTableSetupForm('form', table, db), // form values
                "fields": visibleFields, // original field names
                "field_num": fieldNum, // number of fields
                "table": table,
        }
        if (DEBUG) console.log(data);
    
        // send data to server
        doAJAX(data, function() {
            showMsg(ajaxResponse);

            // update global var db with new DB setup
            if ('db' in ajaxResponse) db = JSON.parse(ajaxResponse.db)

            if (DEBUG) console.log(ajaxResponse);
        });

    }

}





/* 

Function called when "Create table" button is clicked

*/
function addTable( event ) {
    
    event.preventDefault(); // cancel form submission
    jQuery('#submit_handle').click(); // needed to validate form

    if (jQuery('form')[0].checkValidity()) { // if valid, load
        var data = {
                "action": "addTable", 
                "dat": getTableSetupForm('form', null, null), // form values
                "field_num": fieldNum // number of fields
        }
        if (DEBUG) console.log(data);
     
        // send data to server
        doAJAX(data, function() {
            showMsg(ajaxResponse);
            //if (DEBUG) console.log(ajaxResponse);
        });
    }

}





/*

onclick event handler for delete table button called from edit table page

Parameters:
-----------
- tableName : str
              table name to be deleted

Returns:
-------
- will call doAJAX which does all the proper message handling

*/
function deleteTable(event, tableName) {

    event.preventDefault(); // cancel form submission

    var data = {
        "action": "deleteTable",
        "table_name": tableName
    }

    if (DEBUG) console.log(data);


    // send data to server
    doAJAX(data, function() {
        showMsg(ajaxResponse);
        if (DEBUG) console.log(ajaxResponse);
        if (ajaxResponse.status) jQuery('#body').remove(); // remove table if properly deleted
    });

    jQuery('#deleteTableModal').modal('toggle'); // hide modal
}










// hide/show divs based on what user selects for field type
function selectChange(id, fk_ref){

    var val = jQuery("#type-" + id).val()

    // reset input fields that were automatically set in case of FK
    jQuery("[name^=default-]").prop('disabled',false)
    jQuery("[name^=unique-]").prop('disabled',false)

    // remove class from default field in case switching from date/datetime type
    jQuery("[name^=default-" + id + "]").removeClass().addClass('form-control');

    var hidden = jQuery("#hiddenType-" + id);
    if (val == 'fk') {
        var html = '<p>Please choose a table and field that you\'d like to use as a reference for your foreign key; note that only fields that are set to be unique will be found in this list.</p>';
        html += getFKchoices(id, fk_ref);
    } else if (val == 'date') {
        jQuery("[name^=default-" + id + "]").addClass('datepicker');
        setupDatePickers();
        html = '<span>A date field is used for values with a date part but no time part; it is stored in the format <em>YYYY-MM-DD</em> and there fore can only contain numbers and dashes, for example <code>2015-03-24</code>. </span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" name="default-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date at the time of editing.</label>';
    } else if (val == 'varchar') {
        html = '<span>A string field can be contain letters, numbers and various other characters such as commas or dashes; this field is limited to 255 utf8 characters or less.</span>';
        html +='<label class="checkbox-inline"><input type="checkbox" name="longString-' + id + '" onchange="toggleLongString(this)"> check if you plan on storing strings longer than 255 characters (limit 4096); note that if this is selected, this field <b>cannot be used as the reference for a foreign key nor can it be unique</b>.</label>';
    } else if (val == 'int') {
        html = '<p>An integer field can only contain whole numbers such as <code>4321</code>.</p>';
    } else if (val == 'float') {
        html = '<p>A float field can only contain numbers as well as a decimal point, for example <code>89.45</code></p>';
    } else if (val == 'datetime') {
        jQuery("[name^=default-" + id + "]").addClass('datetimepicker');
        setupDatePickers();
        html = '<span>A date time field is used is used for values that contain both date and time parts, it is stored in the format <em>YYYY-MM-DD HH:MM:SS</em>, for example <code>2023-01-19 03:14:07</code></span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" name="default-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date & time at editing.</label>';
    }
    hidden.html(html);

    // set selected FK reference
    if (typeof fk_ref !== undefined && fk_ref !== null) jQuery("select[name=foreignKey-" + id + "]").val(fk_ref);
}



/* Generate a dropdown of possible tables/fields for FK
When setting up a table, a field can be chosen to be
a foreign key; this will generate a drop down select
filled with table name and field name from which to
choose as a reference for the FK. 

Function assumes a JS var 'fks' exists which contains
the possible fks of the DB (this is done in add_table.php)

Note that it will only list unique, non-hidden
fields in non-history tables.

Parameters:
-----------
- id : int (optional)
       if specified, select will get the name 'foreignKey-#',
       otherwise name will be 'foreignKey'
Returns:
--------
- full html for select
*/
function getFKchoices(id=null) {

    // global var db is set in the add_table WP template

    var name = 'foreignKey';
    if (id) {
        name += '-' + id;
    }

    var count = 0;
    var html = '<select class="form-control" name="' + name + '" required>';
    for (var i in fks) {
        var parts = fks[i].split('.');
        var table = parts[0];
        var field = parts[1];

        var val = table + '.' + field;
        var label = 'Table: ' + table + ' | Field: ' + field;
    
        html += '<option value="' + val + '">' + label + '</option>';
        count++;
    }
    html += '</select>';


    // a bit hacky, but if the select is empty, remove it and display a message
    // this can be improved by updating db.class.php so that all the parent field
    // are already defined
    if ( count == 0 ) html = '<code>Looks like none of the existing tables have any fields that are set to be unique - this is a requirement for a field to act as a parent for a field in a child table.</code>';
        
    return html;

}





/*

When editing a table, we use the same form as the "Add table" form;
therefore the form needs to be filled in with the current table setup
being edited.

Parameters:
-----------
- db : obj
       Table structure in form: key=column, value=table attributes.
       Table attributes are stored as an object with all keys defined
       by the db.class.php 
Returns:
--------
- will fill out the table form with filled out info for each field

*/
function fillEditTableForm(db) {

    var count = 0;
    for (var i in db.fields) {
       
        var field = db.fields[i]
        var dat = db.struct[field];

        if (!dat.hidden) { 

            if (count) addField(); // skip adding first field box since it was already done when page loaded

            // set name
            var name = dat.comment.name;
            jQuery("input[name='name-" + (count + 1) + "']").val(name);

            // set default
            var dft = dat['default'];
            if (typeof dft !== 'undefined' && dft != '') jQuery("input[name='default-" + (count + 1) + "']").val(dft);

            // set description
            var descrip = dat.comment.description
            if (typeof descrip !== 'undefined' && descrip != '') jQuery("input[name='description-" + (count + 1) + "']").val(descrip);

            // set required
            var required = dat.required;
            jQuery("input[name='required-" + (count + 1) + "']").prop('checked',required);

            // set unique
            var unique = dat.unique;
            jQuery("input[name='unique-" + (count + 1) + "']").prop('checked',unique);

            // set long string
            var length = dat.length;
            if (dat.length == 4096) jQuery("input[name='longString-" + (count + 1) + "']").prop('checked',true); 


            // set type
            var type = dat.type;
            if (type.indexOf('varchar') > -1) { // could be string or FK
                if (dat.is_fk) { // if field is a foreign key
                    jQuery("select[name='type-" + (count + 1) + "']").val('fk');
                } else { // if standard string (short or long)
                    jQuery("select[name='type-" + (count + 1) + "']").val('varchar');
                    if (dat.length > 255) { // if long string
                        jQuery("input[name='longString-" + (count + 1) + "']").prop('checked',unique);
                    } 
                }
            } else if (type === 'int(32)') {
                jQuery("select[name='type-" + (count + 1) + "']").val('int');
            } else if (type === 'float') {
                jQuery("select[name='type-" + (count + 1) + "']").val('float');
            } else if (type === 'datetime') {
                jQuery("select[name='type-" + (count + 1) + "']").val('datetime');
            } else if (type === 'date') {
                jQuery("select[name='type-" + (count + 1) + "']").val('date');
            }

            count++;
            selectChange(count, dat.fk_ref); // manually fire so that all proper (e.g. type explanation) divs are shown
        }

    }

}


/*
 * Setup the datetime picker on page load for
 * any input with datetimepicker as its class
 *
 * This is used in the add-item modal as well
 * as the table form for the default value.
 */
function setupDatePickers() {
    jQuery('input.datetimepicker').datetimepicker({
        format: "YYYY-MM-DD HH:mm:ss",
    });
    jQuery('input.datepicker').datetimepicker({
        format: "YYYY-MM-DD",
    });
}


jQuery(function () {
    setupDatePickers();
});






