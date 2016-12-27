/* 
AJAX call for retreiving DB data

When script is called on a page with a table that
has the ID 'datatable', AJAX will be used to query
the DB.

The function build_table() [functions.php] is used to
build the HTML table needed to display the data
queried by AJAX.  Both must have the same columns.

Parameters (set by build_table()):
- table : table name to query
- columns : columns in table being queried
- pk : primary key of table
- filter : (optional) filter for table in format {col: val}
- hidden : (optional) array of column names that should be hidden (e.g. UID)
- tableID: the ID for the table into which to put data, defaults to #datatable
*/


function getDBdata(table, pk, columns, filter, hidden, tableID) {

    if (!tableID || tableID == null) tableID = '#datatable';

    // html for Action button column
    if (tableID == '#datatable') {
        var buttonHTML = '<div class="btn-group" role="group">';
        buttonHTML += '<button onclick="historyModal(this)" type="button" class="btn btn-info btn-xs" title="History"><i class="fa fa-history" aria-hidden="true"></i></button>'
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
        "filter": filter,
        "pk" : pk
    }

    // set Action column data to empty since we are automatically adding buttons here
    var colDefs = [{ // https://datatables.net/examples/ajax/null_data_source.html
        "targets": -1,
        "data": null,
        "defaultContent": buttonHTML,
        "width": tableID == '#datatable' ? "70px" : "40px",
        "orderable": false,
    }];

    // hide any columns listed in hidden
    // also make them non-searchable
    if (hidden.length) {
        for (var i = 0; i < hidden.length; i++) {
            var idx = columns.indexOf(hidden[i]);
            colDefs.push({"targets": idx, "visible": false, "searchable": false })
        }
    }

    // crusty workaround for the issue: https://datatables.net/manual/tech-notes/3
    // first viewing the history modal will initialize the table, looking at the modal
    // again (e.g. after an edit) will cause this error
    // work around is to destroy the table initialization and recreate it each time ... :(
    if (typeof historyTable !== 'undefined' && jQuery.fn.dataTable.isDataTable( '#historyTable' )) {
        historyTable.destroy();
    }


    historyTable = jQuery(tableID).DataTable( {
        "retrieve": true,
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": 'scripts/server_processing.php',
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




/* Function called when action button delete clicked

Because none of the buttons have a specified ID, we
need to use some jQuery to figure out which button
was clicked and thus which row the user is trying
to act on.  This function will figure out the ID
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
    var dat = jQuery('#datatable').DataTable().row(rowNum).data();

    jQuery("#deleteID").html( "<code>" + dat[1] + "</code>" ); // set PK message
    jQuery('#deleteModal').modal('toggle'); // show modal
    jQuery("#confirmDelete").attr("onclick", "deleteItem('" + dat[0] + "')");
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
    var cellVal = jQuery('#datatable').DataTable().row(rowNum).data()[1];


    // get values from row and fill modal
    var dat = parseTableRow(rowNum);
    originalRowVals = dat; // set to global for comparison to edited values
    for (var col in dat) {
        var cell = dat[col];
        jQuery('#' + col).val(cell);
    }


    jQuery("#editID").html( "<code>" + cellVal + "</code>" ); // set PK message
    jQuery('#editModal').modal('toggle'); // show modal

    jQuery("#confirmEdit").attr("onclick", "editItem('" + cellVal + "')");
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
    getDBdata(table, columnHist, pk, {'_UID_fk': uidVal}, hiddenHist, '#historyTable');
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





/* Function called when action button history clicked

Because none of the buttons have a specified ID, we
need to use some jQuery to figure out which button
was clicked and thus which row the user is trying
to act on.  This function will figure out the ID
of the first column item and update the modal with
its value.  It will then display the modal.

Parameters:
- sel : will be the 'a' selection of the button that was clicked
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
    getDBdata(table, columnHist, pk, {'_UID_fk': uidVal}, hiddenHist, '#historyTable');
}



/* Function called when add row button history clicked
*/
function addItemModal() {
    jQuery('#addItemModal').modal('toggle'); // show modal
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
    jQuery("#confirmDeleteTable").attr("onclick", "deleteTable('" + tableName + "')");

    jQuery('#deleteTableModal').modal('toggle'); // show modal

}



/* Function called by "Add field" button on add table template page
Will add all the necessarry GUI fields for defining a given field
*/
var fieldNum = 0;
function addField() {
    fieldNum += 1;
            //'<button type="button" class="close" data-target="#field-' + fieldNum + '" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>',
    var dom = ['<div class="panel panel-default" style="margin-bottom:20px;" id="field-' + fieldNum + '">',
            '<div class="panel-heading">',
            '<span class="panel-title">Field #' + fieldNum + '</span>',
            '<button type="button" onclick="fieldNum-=1;" class="close" data-dismiss="alert" data-target="#field-' + fieldNum + '"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>',
            '</div>',
            '<div class="panel-body">',
            '<div class="form-group">',
            '<label class="col-sm-2 control-label" id="fieldLabel">Field name*</label>',
            '<div class="col-sm-3">',
            '<input type="text" class="form-control" name="name-' + fieldNum + '" required pattern="[a-zA-Z0-9 ]+" title="Letters, numbers and spaces only">',
            '</div>',
            '<label class="col-sm-1 control-label">Type</label>',
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
            '<input type="text" class="form-control" name="default-' + fieldNum + '" pattern="[a-zA-Z0-9 ]+" title="Letters, numbers and spaces only">',
            '</div>',
            '<div class="col-sm-offset-1 col-sm-6" id="hiddenType-' + fieldNum + '">',
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
            '<input type="checkbox" name="unique-' + fieldNum + '"> check if field is unique',
            '</label>',
            '</div>',
            '</div>',
            '</div>',
            '</div>']
    jQuery("form").append(dom.join('\n'));
}






/* Function called when "Create table" button is clicked
*/
function addTable() {
    
    event.preventDefault(); // cancel form submission
    jQuery('#submit_handle').click(); // needed to validate form

    if (jQuery('form')[0].checkValidity()) { // if valid, load
        var data = {
                "action": "addTable", 
                "dat": getFormData('form'), // form values
                "field_num": fieldNum // number of fields
        }
        console.log(data);



        // ensure table doesn't exist
        // global var db is set in the add_table WP template
        // NOTE: protected attributes will have a prepended '*' in the key, see:
        // https://ocramius.github.io/blog/fast-php-object-to-array-conversion/
        for (var i in db['tables']) {
            var table = db['tables'][i];
            var table_safe = table.split('_')[1];
            if (table_safe.toLowerCase() == jQuery('[name="table_name"').val().toLowerCase()) {
                showMsg({'msg':'Table name <code>' + table_safe + '</code> already exists, please choose another.', 'status':false, 'hide': false});
                return;
            }
        }

        var names = [];
        for (var i = 1; i <= data.field_num; i++ ) {

            // sensure field names are unique
            var field = 'name-' + i;
            var name = data.dat[field];
            if (names.indexOf(name) > -1) { // name not unique
                showMsg({'msg':'All column names must be unique, <code>' + name + '</code> given multiple times.', 'status':false, 'hide': false});
                return;
            }        
            names.push(name);

            // check that default value matches with field type
            var defaultVal = data.dat['default-' + i];
            if (defaultVal) {
                var type = data.dat['type-' + i];
                if ( type == 'float' && !(isFloat(defaultVal) || isInt(defaultVal)) ) {
                    showMsg({'msg':'If specifying a float type for the column <code>' + name + '</code>, please ensure the default value is a float value.', 'status':false, 'hide': false});
                    return;
                } else if ( type == 'int' && !isInt(defaultVal) ) {
                    showMsg({'msg':'If specifying an integer type for the column <code>' + name + '</code>, please ensure the default value is an integer.', 'status':false, 'hide': false});
                    return;
                }
            }
        }

     
        // send data to server
        doAJAX(data, function() {
            if (ajaxStatus) {
                addTableToNav(jQuery('[name="table_name"').val());
                showMsg(ajaxResponse);
                // should update js var DB here ...
            } else {
                showMsg({"msg":"There was an error creating the table, please try again.", "status": false, 'hide': false});
                console.log(ajaxReponse);
            }
        });
    }

}




// hide/show divs based on what user selects for field type
function selectChange(id){
    var val = jQuery("#type-" + id).val()

    // reset input fields that were automatically set in case of FK
    jQuery("[name^=default-]").prop('disabled',false)
    jQuery("[name^=unique-]").prop('disabled',false)

    var hidden = jQuery("#hiddenType-" + id);
    if (val == 'fk') {
        var html = '<p>Text for foreign key</p>';
        html += getFKchoices(id);
        hidden.html(html);

        // a FK cannot have a default nor can it be unique
        jQuery("[name^=default-]").prop('disabled',true)
        jQuery("[name^=unique-]").prop('disabled',true)
        jQuery("[name^=unique-]").prop('checked',false)

    } else if (val == 'date') {
        html = '<span>A date field is used for values with a date part but no time part; it is stored in the format <em>YYYY-MM-DD</em> and there fore can only contain numbers and dashes, for example <code>2015-03-24</code>. </span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" clas="form-control" name="currentDate-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date at the time of editing.</label>';
        hidden.html(html);
    } else if (val == 'varchar') {
        hidden.html('<p>A string field can be contain letters, numbers and various other characters such as commas or dashes.</p>');
    } else if (val == 'int') {
        hidden.html('<p>An integer field can only contain whole numbers such as <code>4321</code>.</p>');
    } else if (val == 'float') {
        hidden.html('<p>A float field can only contain numbers as well as a decimal point, for example <code>89.45</code></p>');
    } else if (val == 'datetime') {
        html = '<span>A date time field is used is used for values that contain both date and time parts, it is stored in the format <em>YYYY-MM-DD HH:MM:SS</em>, for example <code>2023-01-19 03:14:07</code></span><br>';
        html +='<label class="checkbox-inline"><input type="checkbox" clas="form-control" name="currentDate-' + id + '" onchange="toggleDate(this)"> check if you want this field automatically filled with the date & time at editing.</label>';
        hidden.html(html);
    }
}



/* Generate a dropdown of possible tables/fields for FK
When setting up a table, a field can be chosen to be
a foreign key; this will generate a drop down select
filled with table name and field name from which to
choose as a reference for the FK
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
    var struct = db['struct'];

    var name = 'foreignKey';
    if (id) {
        name += '-' + id;
    }

    var html = '<select class="form-control" name="' + name + '" required>';
    for (var i in db['tables']) {
        var table = db['tables'][i];
        var tableSafe = table.split('_')[1]; // remove company name
        var tableStruct = struct[table];
        var fieldStruct = tableStruct['struct'];
        
        for (var j in tableStruct['fields']) {
            var field = tableStruct['fields'][j];
            if ( fieldStruct[field]['hidden'] == false) {
                var val = tableSafe + '.' + field;
                var label = 'Table: ' + tableSafe + ' | Field: ' + field;
            
                html += '<option value="' + val + '">' + label + '</option>';
            }
        }

    }
    html += '</select>';

    return html;

}

