<?php
/**
 * Script that receives AJAX request for querying database   
 *
 * @param _GET['table'] str - table name to query
 * @param _GET['cols'] assoc arr - array of column names in table as keys, column format as value
 * @param _GET['pk'] arr - name of field in DB that is primary key
 * @param _GET['filter'] assoc arr [col: filter] - filter for column
 *
 * @return datatables formatted data
*/


require_once("functions.php"); 
 
if ( isset($_GET['table'] ) ) {

    // DB table to use
    $table = $_GET['table'];
    $primaryKey = $_GET['pk'];
    $acct = $_GET['acct'];

    // Array of database columns which should be read and sent back to DataTables.
    // The `db` parameter represents the column name in the database, while the `dt`
    // parameter represents the DataTables column identifier. In this case simple
    // indexes
    $columns = array();
    if ( isset($_GET['cols']) && is_array($_GET['cols']) && !empty($_GET['cols']) ) {
        foreach (array_keys($_GET['cols']) as $i => $col) {
            $format = ($_GET['cols'][$col] !== '') ? $_GET['cols'][$col] : null;
            $columns[] = array('db' => $col, 'dt' => $i, 'format' => $format);
        }
         
        $results = SSP::simple( $_GET, get_db_conn($acct), $table, $primaryKey, $columns );

        echo json_encode( $results );
    }
    
}

class SSP {
    /**
     * Create the data output array for the DataTables rows
     *
     *  @param  array $columns Column information array
     *  @param  array $data    Data from the SQL get
     *  @return array          Formatted data in a row based format
     */
    static function data_output ( $columns, $data )
    {
        $out = array();

        for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
            $row = array();

            for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
                $column = $columns[$j];

                // Is there a formatter?
                if ( !is_null( $column['format'] ) ) {
                    $dat = $data[$i][ $columns[$j]['db'] ];
                    if ($column['format'] == 'date') {
                        $row[ $column['dt'] ] = explode(' ', $dat)[0];
                    } else {
                        $row[ $column['dt'] ] = $dat;
                    }
                }
                else {
                    $row[ $column['dt'] ] = $data[$i][ $columns[$j]['db'] ];
                }
            }

            $out[] = $row;
        }

        return $out;
    }



    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @return string SQL limit clause
     */
    static function limit ( $request, $columns )
    {
        $limit = '';

        if ( isset($request['start']) && $request['length'] != -1 ) {
            $limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
        }

        return $limit;
    }


    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @return string SQL order by clause
     */
    static function order ( $request, $columns )
    {
        $order = '';

        if ( isset($request['order']) && count($request['order']) ) {
            $orderBy = array();
            $dtColumns = self::pluck( $columns, 'dt' );

            for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
                // Convert the column index into the column data property
                $columnIdx = intval($request['order'][$i]['column']);
                $requestColumn = $request['columns'][$columnIdx];

                $columnIdx = array_search( $requestColumn['data'], $dtColumns );
                $column = $columns[ $columnIdx ];

                if ( $requestColumn['orderable'] == 'true' ) {
                    $dir = $request['order'][$i]['dir'] === 'asc' ?
                        'ASC' :
                        'DESC';

                    $orderBy[] = '`'.$column['db'].'` '.$dir;
                }
            }

            $order = 'ORDER BY '.implode(', ', $orderBy);
        }

        return $order;
    }


    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @param  array $bindings Array of values for PDO bindings, used in the
     *    sql_exec() function
     *  @return string SQL where clause
     */
    static function filter ( $request, $columns, &$bindings )
    {
        $globalSearch = array();
        $columnSearch = array();
        $dtColumns = self::pluck( $columns, 'dt' );

        // global filter
        if ( isset($request['search']) && $request['search']['value'] != '' ) {
            $str = $request['search']['value'];

            for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search( $requestColumn['data'], $dtColumns );
                $column = $columns[ $columnIdx ];

                if ( $requestColumn['searchable'] == 'true' ) {
                    $binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
                    $globalSearch[] = "`".$column['db']."` LIKE ".$binding;
                }
            }
        }

        // Individual column filtering
        if ( isset( $request['columns'] ) ) {
            for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search( $requestColumn['data'], $dtColumns );
                $column = $columns[ $columnIdx ];

                $str = $requestColumn['search']['value'];

                if ( $requestColumn['searchable'] == 'true' && $str != '' ) {
                    $binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
                    $columnSearch[] = "`".$column['db']."` LIKE ".$binding;
                }
            }
        }

        // do the fitering for the case when
        // - A) viewing history of an item and we need a particular UID_fk
        // - B) filtering with the query builder
        if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
            $filters = $request['filter'];

            if ( array_key_exists( 'sql', $filters )  && array_key_exists( 'params', $filters ) ) { // B)
                $columnSearch[] = '(' . $filters['sql'] . ')';
                foreach ( $filters['params'] as $col => $str ) {
                    $binding = self::bind( $bindings, $str, PDO::PARAM_STR, $col );
                }
            } else { // A)
                foreach ( $filters as $col => $str ) {
                    $binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
                    $columnSearch[] = "`".$col."` LIKE ".$binding;
                }
            }

        }

        // Combine the filters into a single string
        $where = '';

        if ( count( $globalSearch ) ) {
            $where = '('.implode(' OR ', $globalSearch).')';
        }

        if ( count( $columnSearch ) ) {
            $where = $where === '' ?
                implode(' AND ', $columnSearch) :
                $where .' AND '. implode(' AND ', $columnSearch);
        }

        if ( $where !== '' ) {
            $where = 'WHERE '.$where;
        }

        return $where;
    }


    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions of this class, limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to an SSP request, or can be modified if needed before
     * sending back to the client.
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array|PDO $conn PDO connection resource or connection parameters array
     *  @param  string $table SQL table to query
     *  @param  string $primaryKey Primary key of the table
     *  @param  array $columns Column information array
     *  @param  array filter columns based on user click on cell [col name: filter]
     *  @return array          Server-side processing response array
     */
    static function simple ( $request, $db, $table, $primaryKey, $columns )
    {
        $bindings = array();

        // Build the SQL query string from the request
        $limit = self::limit( $request, $columns );
        $order = self::order( $request, $columns );
        $where = self::filter( $request, $columns, $bindings );


        // Main query to actually get the data
        $data = self::sql_exec( $db, $bindings,
            "SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
             FROM `$table`
             $where
             $order
             $limit"
        );

        // Data set length after filtering
        $resFilterLength = self::sql_exec( $db, $bindings,
            "SELECT COUNT(`{$primaryKey}`)
             FROM   `$table`
             $where"
        );
        $recordsFiltered = $resFilterLength[0][0];

        // Total data set length
        $resTotalLength = self::sql_exec( $db,
            "SELECT COUNT(`{$primaryKey}`)
             FROM   `$table`"
        );
        $recordsTotal = $resTotalLength[0][0];

        /*
         * Output
         */
        return array(
            "draw"            => isset ( $request['draw'] ) ?
                intval( $request['draw'] ) :
                0,
            "recordsTotal"    => intval( $recordsTotal ),
            "recordsFiltered" => intval( $recordsFiltered ),
            "data"            => self::data_output( $columns, $data ),
            "log" => $columns, "filter" => $request['filter'], "where" => $where
        );
    }


    /**
     * The difference between this method and the `simple` one, is that you can
     * apply additional `where` conditions to the SQL queries. These can be in
     * one of two forms:
     *
     * * 'Result condition' - This is applied to the result set, but not the
     *   overall paging information query - i.e. it will not effect the number
     *   of records that a user sees they can have access to. This should be
     *   used when you want apply a filtering condition that the user has sent.
     * * 'All condition' - This is applied to all queries that are made and
     *   reduces the number of records that the user can access. This should be
     *   used in conditions where you don't want the user to ever have access to
     *   particular records (for example, restricting by a login id).
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array|PDO $conn PDO connection resource or connection parameters array
     *  @param  string $table SQL table to query
     *  @param  string $primaryKey Primary key of the table
     *  @param  array $columns Column information array
     *  @param  string $whereResult WHERE condition to apply to the result set
     *  @param  string $whereAll WHERE condition to apply to all queries
     *  @return array          Server-side processing response array
     */
    static function complex ( $request, $conn, $table, $primaryKey, $columns, $whereResult=NULL, $whereAll=NULL )
    {
        $bindings = array();
        $db = self::db( $conn );
        $localWhereResult = array();
        $localWhereAll = array();
        $whereAllSql = '';

        // Build the SQL query string from the request
        $limit = self::limit( $request, $columns );
        $order = self::order( $request, $columns );
        $where = self::filter( $request, $columns, $bindings );

        $whereResult = self::_flatten( $whereResult );
        $whereAll = self::_flatten( $whereAll );

        if ( $whereResult ) {
            $where = $where ?
                $where .' AND '.$whereResult :
                'WHERE '.$whereResult;
        }

        if ( $whereAll ) {
            $where = $where ?
                $where .' AND '.$whereAll :
                'WHERE '.$whereAll;

            $whereAllSql = 'WHERE '.$whereAll;
        }

        // Main query to actually get the data
        $data = self::sql_exec( $db, $bindings,
            "SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
             FROM `$table`
             $where
             $order
             $limit"
        );

        // Data set length after filtering
        $resFilterLength = self::sql_exec( $db, $bindings,
            "SELECT COUNT(`{$primaryKey}`)
             FROM   `$table`
             $where"
        );
        $recordsFiltered = $resFilterLength[0][0];

        // Total data set length
        $resTotalLength = self::sql_exec( $db, $bindings,
            "SELECT COUNT(`{$primaryKey}`)
             FROM   `$table` ".
            $whereAllSql
        );
        $recordsTotal = $resTotalLength[0][0];

        /*
         * Output
         */
        return array(
            "draw"            => isset ( $request['draw'] ) ?
                intval( $request['draw'] ) :
                0,
            "recordsTotal"    => intval( $recordsTotal ),
            "recordsFiltered" => intval( $recordsFiltered ),
            "data"            => self::data_output( $columns, $data )
        );
    }



    /**
     * Execute an SQL query on the database
     *
     * @param  resource $db  Database handler
     * @param  array    $bindings Array of PDO binding values from bind() to be
     *   used for safely escaping strings. Note that this can be given as the
     *   SQL query string if no bindings are required.
     * @param  string   $sql SQL query to execute.
     * @return array         Result from the query (all rows)
     */
    static function sql_exec ( $db, $bindings, $sql=NULL )
    {
        // Argument shifting
        if ( $sql === NULL ) {
            $sql = $bindings;
        }

        $stmt = $db->prepare( $sql );

        // Bind parameters
        if ( is_array( $bindings ) ) {
            for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
                $binding = $bindings[$i];
                $stmt->bindValue( $binding['key'], $binding['val'], $binding['type'] );
            }
        }

        // Execute
        try {
            $stmt->execute();
        }
        catch (PDOException $e) {
            self::fatal( "An SQL error occurred: ".$e->getMessage() );
        }

        // Return all
        return $stmt->fetchAll( PDO::FETCH_BOTH );
    }


    /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
     * Internal methods
     */

    /**
     * Throw a fatal error.
     *
     * This writes out an error message in a JSON string which DataTables will
     * see and show to the user in the browser.
     *
     * @param  string $msg Message to send to the client
     */
    static function fatal ( $msg )
    {
        echo json_encode( array( 
            "error" => $msg
        ) );

        exit(0);
    }

    /**
     * Create a PDO binding key which can be used for escaping variables safely
     * when executing a query with sql_exec()
     *
     * @param  array &$a    Array of bindings
     * @param  *      $val  Value to bind
     * @param  int    $type PDO field type
     * @return string       Bound key to be used in the SQL where this parameter
     *   would be used.
     */
    static function bind ( &$a, $val, $type, $key=False )
    {
        if ($key == False ) {
            $key = ':binding_'.count( $a );
        }

        $a[] = array(
            'key' => $key,
            'val' => $val,
            'type' => $type
        );

        return $key;
    }


    /**
     * Pull a particular property from each assoc. array in a numeric array, 
     * returning and array of the property values from each item.
     *
     *  @param  array  $a    Array to get data from
     *  @param  string $prop Property to read
     *  @return array        Array of property values
     */
    static function pluck ( $a, $prop )
    {
        $out = array();

        for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
            $out[] = $a[$i][$prop];
        }

        return $out;
    }


    /**
     * Return a string from an array or a string
     *
     * @param  array|string $a Array to join
     * @param  string $join Glue for the concatenation
     * @return string Joined string
     */
    static function _flatten ( $a, $join = ' AND ' )
    {
        if ( ! $a ) {
            return '';
        }
        else if ( $a && is_array($a) ) {
            return implode( $join, $a );
        }
        return $a;
    }
}

