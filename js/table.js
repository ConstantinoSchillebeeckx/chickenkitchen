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
        jQuery("[name=unique-" + fieldNum + "]").prop('checked', false).prop('disabled', true);
        jQuery("[name=required-" + fieldNum + "]").prop('checked', false).prop('disabled', true);
        jQuery("input[type=text][name=default-" + fieldNum + "]").prop('disabled', true);
    } else {
        jQuery("[name=unique-" + fieldNum + "]").prop('disabled', false);
        jQuery("[name=required-" + fieldNum + "]").prop('disabled', false);
        jQuery("input[type=text][name=default-" + fieldNum + "]").prop('disabled', false);
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
        jQuery("[name^=unique-]").prop('checked', false).prop('disabled', true);
    } else {
        jQuery("[name^=unique-]").prop('disabled', false);
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

    // send via AJAX to process with PHP
    jQuery.ajax({
            url: 'ajax.php',
            type: "GET",
            data: data,
            dataType: 'json',
            contentType: "application/json; charset=utf-8",
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
                callback();
            }
    });

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

    if (selectedRadio.value == 'batchAdd') {
        content = 'add';
    } else if (selectedRadio.value == 'batchEdit' ) {
        content = 'edit';
    } else if (selectedRadio.value == 'batchDelete' ) {
        content = 'delete';
    }

    div.html(content);
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

    var type = dat.status ? 'success' : 'danger';
    var msg = dat.msg;
    var hide = dat.hide; // true will auto-remove the message, false will keep message on scree
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
- columns : columns in table being queried
- pk : primary key of table
- filter : (optional) filter for table in format {col: val}
- hidden : (optional) array of column names that should be hidden (e.g. UID)
- tableID: (optional) the ID for the table into which to put data, defaults to #datatable
- hasHistory: (optional) bool if table has history counter part
*/


function getDBdata(table, pk, columns, filter, hidden, tableID, hasHistory) {

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
        buttonHTML += '<button onclick="revertHistory(this)" type="button" class="btn btn-info btn-xs" title="History"><i class="fa fa-undo" aria-hidden="true"></i></button>'
        buttonHTML += '</div>';
    }

    jQuery.fn.dataTable.ext.errMode = 'throw'; // Have DataTables throw errors rather than alert() them


    // variables set with build_table() defined in functions.php
    var data =  {
        "action": "viewTable", 
        "table": table, 
        "cols": columns,
        "pk" : pk,
        "filter": filter,
    }

    // setup columnDefs
    var colDefs = [];
    for (var i = 0; i < columns.length; i++) {
        colDefs[i] = {};

        colDefs[i]['name'] = columns[i];
        colDefs[i]['targets'] = i;

        // hide any columns listed in hidden
        // also make them non-searchable
        if (hidden.length) { 
            var idx = hidden.indexOf(columns[i]);
            if (idx != -1) {
                colDefs[i]['visible'] = false;
                colDefs[i]['searchable'] = false;
            }
        }
    }

    // set Action column data to empty since we are automatically adding buttons here
    colDefs.push({ // https://datatables.net/examples/ajax/null_data_source.html
        "targets": -1,
        "name": 'Action',
        "data": null,
        "defaultContent": buttonHTML,
        "width": colWidth,
        "orderable": false,
    });


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
        "ajax": {
            "url": 'ssp.class.php',
            "data": data,
            },
        "columnDefs": colDefs,
        "paging": tableID == '#datatable',
        "searching": tableID == '#datatable',
        "info": tableID == '#datatable',
    } );

    // destroy global so that we only set this for history table
    // see workaround above
    if (tableID == '#datatable') {
        historyTable = null;
    }

};


/**

Function used to setup the query builder for advanced search. Will
create the 'filter' option for the builder based on the field types
of the current table.

Param: db object

Returns: json 'filter' option

*/
function setup_query_builder_filter( db ) {


    // build filter for query builder
    var filters = [];
    for (var field in db) {
        var field_dat = db[field];
    
        if (field_dat.hidden == false) {
            var tmp = {};
            tmp.id = field_dat.comment.name;
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
    delete originalRowVals['_UID'];
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

    if (jQuery('form')[0].checkValidity()) { // if valid, load

        // generate form for XHR send
        var formData = new FormData($('#batchForm')[0]);

        // since we're uploading a file, our AJAX request needs to be
        // modified a bit to be able to use FormData/XHR
        jQuery.ajax({
            url: 'ajax.php',
            type: 'POST',
            xhr: function() {  // custom xhr
                myXhr = $.ajaxSettings.xhr();
                return myXhr;
            },
            success: completeHandler = function(response) {
                
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
    var uidVal = rowVals[0];
    var itemVal = rowVals[1];

    jQuery("#historyID").html( "<code>" + itemVal + "</code>" ); // set PK message
    jQuery('#historyModal').modal('toggle'); // show modal

    // fill table with data
    // vars are defined in modal.php
    getDBdata(tableHist, pkHist, columnHist, {'_UID_fk': uidVal}, hiddenHist, '#historyTable', false);
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
Returns:
========
- obj with column names as keys and row values as value
*/
function parseTableRow(rowIX) {

    var table = jQuery('#datatable').DataTable();
    var colData = table.columns().nodes();
    //need to use this so that we can grab UID (hidden field)

    var dat = {};
    table.columns().every(function(i) { 
        var col = this.header().textContent;
        var cellVal = colData[i][rowIX].textContent;
        if (cellVal) {
            dat[col] = cellVal;
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
function downloadCSV(tableName) {

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


    event.preventDefault();
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
var fieldNum = 0;
function addField() {
    fieldNum += 1;
    var dom = ['<div class="panel panel-default" style="margin-bottom:20px;" id="field-' + fieldNum + '">',
            '<div class="panel-heading">',
            '<span class="panel-title">Field #' + fieldNum + '</span>',
            '<button type="button" onclick="fieldNum-=1;" class="close" data-dismiss="alert" data-target="#field-' + fieldNum + '"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>',
            '</div>',
            '<div class="panel-body">',
            '<div class="form-group">',
            '<label class="col-sm-2 control-label" id="fieldLabel">Field name*</label>',
            '<div class="col-sm-3">',
            '<input type="text" class="form-control" name="name-' + fieldNum + '" required pattern="[a-zA-Z0-9\-_ ]+" title="Letters, numbers, hypens and underscores and spaces only" maxlength="64">',
            '</div>',
            '<label class="col-sm-1 control-label">Type*</label>',
            '<div class="col-sm-2">',
            '<select class="form-control" onChange="selectChange(' + fieldNum + ')" id="type-' + fieldNum + '" name="type-' + fieldNum + '" required>',
            '<option value="" disabled selected style="display:none;"></option>',
            '<option value="varchar">String</option>',
            '<option value="int">Integer</option>',
            '<option value="float">Float</option>',
            '<option value="date">Date</option>',
            '<option value="datetime">Date & Time</option>',
            '<option value="fk">Foreign</option>',
            '</select>',
            '</div>',
            '</div>',
            '<div class="form-group">',
            '<label class="col-sm-2 control-label" id="fieldLabel">Default value</label>',
            '<div class="col-sm-3">',
            '<input type="text" class="form-control" name="default-' + fieldNum + '">',
            '</div>',
            '<div class="col-sm-offset-1 col-sm-6" id="hiddenType-' + fieldNum + '">',
            '</div>',
            '</div>',
            '<div class="form-group">',
            '<label class="col-sm-2 control-label" id="fieldLabel">Description</label>',
            '<div class="col-sm-3">',
            '<input type="text" class="form-control" name="description-' + fieldNum + '">',
            '</div>',
            '</div>',
            '<div class="form-group">',
            '<label class="col-sm-2 control-label">Required</label>',
            '<div class="col-sm-3">',
            '<label class="checkbox-inline">',
            '<input type="checkbox" name="required-' + fieldNum + '"> check if field is required',
            '</label>',
            '</div>',
            '<label class="col-sm-1 control-label">Unique</label>',
            '<div class="col-sm-3">',
            '<label class="checkbox-inline">',
            '<input type="checkbox" name="unique-' + fieldNum + '"> check if field is unique. <b>Note:</b> if you plan on making this field a reference for a foreign key, then this field must be unique.',
            '</label>',
            '</div>',
            '</div>',
            '</div>',
            '</div>']
    jQuery("form").append(dom.join('\n'));
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
                "dat": getFormData('form'), // form values
                "field_num": fieldNum // number of fields
        }
        if (DEBUG) console.log(data);
     
        // send data to server
        doAJAX(data, function() {
            showMsg(ajaxResponse);
            if (DEBUG) console.log(ajaxResponse);
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
function selectChange(id){
    var val = jQuery("#type-" + id).val()

    // reset input fields that were automatically set in case of FK
    jQuery("[name^=default-]").prop('disabled',false)
    jQuery("[name^=unique-]").prop('disabled',false)

    var hidden = jQuery("#hiddenType-" + id);
    if (val == 'fk') {
        var html = '<p>Please choose a table and field that you\'d like to use as a reference for your foreign key; note that only fields that are set to be unique will be found in this list.</p>';
        html += getFKchoices(id);
    } else if (val == 'date') {
        html = '<span>A date field is used for values with a date part but no time part; it is stored in the format <em>YYYY-MM-DD</em> and there fore can only contain numbers and dashes, for example <code>2015-03-24</code>. </span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" clas="form-control" name="default-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date at the time of editing.</label>';
    } else if (val == 'varchar') {
        html = '<span>A string field can be contain letters, numbers and various other characters such as commas or dashes; this field is limited to 255 utf8 characters or less.</span>';
        html +='<label class="checkbox-inline"><input type="checkbox" clas="form-control" name="longString-' + id + '" onchange="toggleLongString(this)"> check if you plan on storing strings longer than 255 characters (limit 4096); note that if this is selected, this field <b>cannot be used as the reference for a foreign key nor can it be unique</b>.</label>';
    } else if (val == 'int') {
        html = '<p>An integer field can only contain whole numbers such as <code>4321</code>.</p>';
    } else if (val == 'float') {
        html = '<p>A float field can only contain numbers as well as a decimal point, for example <code>89.45</code></p>';
    } else if (val == 'datetime') {
        html = '<span>A date time field is used is used for values that contain both date and time parts, it is stored in the format <em>YYYY-MM-DD HH:MM:SS</em>, for example <code>2023-01-19 03:14:07</code></span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" clas="form-control" name="default-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date & time at editing.</label>';
    }
    hidden.html(html);
}



/* Generate a dropdown of possible tables/fields for FK
When setting up a table, a field can be chosen to be
a foreign key; this will generate a drop down select
filled with table name and field name from which to
choose as a reference for the FK. 

Function assumes a JS var db exists which contains
the structure of the DB (this is done in add_table.php)

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

    var html = '<select class="form-control" name="' + name + '" required>';
    var count = 0;
    for (var table in db) {
        var tableStruct = db[table];
        var fieldStruct = tableStruct['struct'];
        var isHist = tableStruct['is_history'];
      
        // only generate FK for regular tables (not history tables)
        if (isHist == false) { 
            for (var j in tableStruct['fields']) {
                var field = tableStruct['fields'][j];
                if ( fieldStruct[field]['hidden'] == false && (fieldStruct[field]['key'] == 'PRI' || fieldStruct[field]['key'] == 'UNI')) {
                    var val = table + '.' + field;
                    var label = 'Table: ' + table + ' | Field: ' + field;
                
                    html += '<option value="' + val + '">' + label + '</option>';
                    count++;
                }
            }
        }

    }
    html += '</select>';


    // a bit hacky, but if the select is empty, remove it and display a message
    // this can be improved by updating db.class.php so that all the parent field
    // are already defined
    if ( count == 0 ) html = '<code>Looks like none of the existing tables have any fields that are set to be unique - this is a requirement for a field to act as a parent for a field in a child table.</code>';
        
    return html;

}

