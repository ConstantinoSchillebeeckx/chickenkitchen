<div class="container">

<script>
$(document).ready(function() {
    $('#example').DataTable( {
        "processing": true,
        "serverSide": true,
        "ajax": "scripts/server_processing.php"
    } );
} );
</script>


    <table id="example" class="table" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>_UID</th>
                <th>asdf</th>
            </tr>
        </thead>
    </table>

</div>
