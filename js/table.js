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


function getDBdata(table, columns, filter, hidden, tableID) {

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
    getDBdata(table, columnHist, {'_UID_fk': uidVal}, hiddenHist, '#historyTable');
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
    getDBdata(table, columnHist, {'_UID_fk': uidVal}, hiddenHist, '#historyTable');
}
