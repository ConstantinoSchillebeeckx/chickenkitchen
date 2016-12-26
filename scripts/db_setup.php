<?php


/*------------------------------------*\
    database class
\*------------------------------------*/

/* Database class for loading structure of the database

Once a user logs in, the general structure of the database
associated with the company of that user is loaded.

Class properties:
- tables : array of tables associated with user's company
- struct : associative array where each table is a key and
           the value is a class Table
- name : name of database e.g. db215537_EL
- company : company associated with logged in user

TODO
*/
class Database {


    protected $tables = array(); // array of tables associated with user's company
    protected $struct = array(); // associative array where each table is a key and the value is a class table()
    protected $name = null; // DB name e.g. db215537_EL
    protected $company = null; // company associated with logged in user

    public function __construct( $comp=null, $db ) {

        if ($comp) {

            $this->company = $comp;
            $this->name = DB_NAME;

            // get list of tables
            $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $this->name . "'";
            $results = $db->query($sql)->fetchAll();
            if ($results !== true) {
                foreach ($results as $row ) {
                    $this->tables[] = $row["TABLE_NAME"];
                }


                // check FKs for table
                $sql = sprintf("select concat(table_name, '.', column_name) as 'foreign key',  
                concat(referenced_table_name, '.', referenced_column_name) as 'references'
                from
                    information_schema.key_column_usage
                where
                    referenced_table_name is not null
                    and table_schema = '%s' 
                    and table_name like '%s_%%'
                ", $this->get_name(), $this->get_company());
                $results = $db->query($sql)->fetchAll();
                $fks = array();
                if ($results !== true) {
                    foreach ($results as $row) {
                        $fks[$row["foreign key"]] = $row["references"];
                    }
                }

                // generate DB structure
                foreach ($this->tables as $table) {
                    $this->struct[$table] = new Table($table, $fks, $db);
                }
            }
        }
    }

    // return array of full table names
    public function get_tables() {
        return $this->tables;
    }
    
    // return assoc array of table struct
    public function get_struct() {
        return $this->struct;
    }

    // return name of company for user
    public function get_company() {
        return $this->company;
    }

    // return name of DB
    public function get_name() {
        return $this->name;
    }

    // return field name that is pk, if it exists
    // otherwise return false
    public function get_pk($table) {
        if ( in_array( $table, $this->get_tables() ) ) {
            $tmp = $this->get_table($table);
            return $tmp->get_pk();
        } else {
            return false;
        }
    }

    // given a table (name) return its Table class
    public function get_table($table) {
        if ( in_array( $table, $this->get_tables() ) ) {
            return $this->get_struct()[$table];
        } else {
            return false;
        }
    }

    // given a table (name) return the columns that are unique,
    // if any, as an array
    public function get_unique($table) {
        if ( in_array( $table, $this->get_tables() ) ) {
            $tmp = $this->get_struct()[$table];
            if ($tmp->get_unique()) {
                return $tmp->get_unique();
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    // given a table (name) and field return its Field class
    public function get_field($table, $field) {
        if ( in_array( $table, $this->get_tables() ) ) {
            $table_class = $this->get_struct()[$table];
            return $table_class->get_field($field);
        } else {
            return false;
        }
    }

    // given a table (name) return its full name (with prepended DB)
    public function get_table_full_name($table) {
        if ( in_array( $table, $this->get_tables() ) ) {
            $table_class = $this->get_table($table);
            return $table_class->get_full_name();
        } else {
            return false;
        }
    }

    // given a table (name) return fields in table as array
    public function get_fields($table) {
        if ( in_array( $table, $this->get_tables() ) ) {
            return $this->get_struct()[$table]->get_fields();
        } else {
            return false;
        }
    }

    // pretty print
    public function show() {
        echo '<pre style="font-size:8px;">';
        print_r($this);
        echo '</pre>';
    }

    public function asJSON() {
        return json_encode(objectToArray($this)) . ';'; // ';' so that JS doesn't complain
    }

}

// http://stackoverflow.com/a/2476954/1153897
function objectToArray ($object) {
    if(!is_object($object) && !is_array($object))
        return $object;

    return array_map('objectToArray', (array) $object);
}


/* Table class defines properties of a given database table

Class roperties:
- struct : associative array where each field is a key and
           the value is a class Field
- name : name of table with prepended company (e.g. matatu_samples)
- fields : array of fields contained in table

*/
class Table {

    protected $fields = array();
    protected $name = null;
    protected $struct = array();
    

    public function __construct($name, $fks, $db) {
        $this->name = $name;

        // get list of fields
        $sql = sprintf("SHOW FULL COLUMNS FROM %s", $this->name);
        $results = $db->query($sql)->fetchAll();
        $info = array();
        foreach($results as $row) {
            $this->fields[] = $row["Field"];
            $info[$row['Field']] = array("Type" => $row['Type'], 
                                           "Null" => $row['Null'],
                                           "Key" => $row['Key'],
                                           "Default" => $row['Default'],
                                           "Extra" => $row['Extra'],
                                           "Comment" => $row['Comment']
                                            );
        }

        // get details of each field
        foreach ($this->fields as $field) {
            $this->struct[$field] = new Field($this->name, $field, $fks, $info, $db);
        }
     }

    // same as get_table()
    public function get_name() {
        return $this->name;
    }

    // return safe name (without company or DB)
    public function get_safe_name() {
        return explode("_", $this->get_name())[1];
    }

    // return full name (with DB prepended)
    public function get_full_name() {
        return DB_NAME_EL . '.' . $this->get_name();
    }

    // return full name (with DB prepended)
    public function get_full_name_history() {
        return DB_NAME_EL_HISTORY . '.' . $this->get_name();
    }

    // return an array of non-hidden fields
    public function get_visible_fields() {
        $fields = array();
        if (count($this->fields)) {
            foreach($this->fields as $field) {
                if (!$this->get_field($field)->is_hidden()) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    // return array of fields in table
    public function get_fields() {
        return $this->fields;
    }

    // return array of hidden fields in table
    public function get_hidden_fields() {
        return array_values(array_diff($this->get_fields(), $this->get_visible_fields()));
    }

    // return table struct as assoc array
    // keys are field names, values are Field class
    public function get_struct() {
        return $this->struct;
    }

    // given a field name, return the Field class
    public function get_field($field) {
        if ( in_array( $field, $this->get_fields() ) ) {
            return $this->get_struct()[$field];
        } else {
            return false;
        }
    }

    // check if table contains a field that is
    // referenced by an FK
    // if so, return the field name(s) [table.col] as an array
    public function get_ref() {
        $fields = $this->get_fields();
        $fks = array();  

        foreach($fields as $field) {
            $field_class = $this->get_field($field);
            if ($field_class->is_ref()) {

                $ref = $field_class->get_ref();
                $fks[] = $ref;
            }
        }

        if ( count($fks) > 0 ) {
            return $fks;
        } else {
            return false;
        }
    }

    // return field name that is primary key in table
    // returns false if none found
    public function get_pk() {
        $info = $this->get_struct();
        foreach ($info as $k => $v) { // $k = field name, $v Field class
            if ( $v->is_pk() ) {
                return $k;
            }
        }
        return false;
    }

    // return an array of fields that have
    // the unique property in the table
    // otherwise false
    public function get_unique() {
        $info = $this->get_struct();
        $tmp = array();
        foreach ($info as $k => $v) { // $k = field name, $v Field class
            if ( $v->is_unique() ) {
                array_push($tmp, $k);
            }
        }
        if ( count($tmp) > 0 ) {
            return $tmp;
        } else {
            return false;
        }
    }


    // pretty print
    public function show() {
        echo '<pre style="font-size:8px;">';
        print_r($this);
        echo '</pre>';
    }
}

/* Field class defined properties of a given column in a table

Class properties:
- name : name of field (e.g. sampleType)
- is_fk : bool for if a field is a foreign key
- fk_ref : if a field is a foreign key, it references this field (full_name)
- hidden : bool for whether field should be hidden from front-end view
- is_ref : bool if field is referenced by a foreign key (this makes the field a primary key)
- ref : if field is referenced by a foreign key, this is the field that references it (full_name)
- type : field type (e.g. datetime, varchar, etc)
- required : bool if field is required (inverst of NULL property)
- key: can be empty, PRI, UNI or MUL (see https://dev.mysql.com/doc/refman/5.7/en/show-columns.html)
- default : default value of field
- extra : any additional information that is available about a given column
- table : name of table field belongs
- comment : extra data stored in comment field, e.g. column_format
*/
class Field {

    protected $is_fk; 
    protected $fk_ref;
    protected $hidden; 
    protected $is_ref;
    protected $ref; 
    protected $type;
    protected $required;
    protected $key;
    protected $default;
    protected $extra;
    protected $name;
    protected $table;
    protected $comment;
    protected $length;

    public function __construct($table, $name, $fks, $info, $db) {
        $this->name = $name;
        $this->type = $info[$name]["Type"];
        $this->key = $info[$name]["Key"];
        $this->default = $info[$name]["Default"];
        $this->extra = $info[$name]["Extra"];
        $this->comment = json_decode($info[$name]["Comment"], true);
        $this->table = $table;
        $this->length = $this->get_length();

        // check if field is required
        if ( $info[$name]["Null"] == "YES" || in_array($this->type, array('timestamp', 'date') ) ) {
            $this->required = false;
        } else {
            $this->required = true;
        }

        // check if field is fk
        if (array_key_exists($table . '.' . $this->name, $fks)) {
            $this->is_fk = true;
            $this->fk_ref = $fks[$table . '.' . $this->name];
        } else {
            $this->is_fk = false;
            $this->fk_ref = false;
        }

        // check if field is referenced by fk
        $tmp = array_search($table . '.' . $this->name, $fks);
        if ($tmp) {
            $this->is_ref = true;
            $this->ref = $tmp;
        } else {
            $this->is_ref = false;
            $this->ref = false;
        }

        // check if hidden field
        if ($this->comment['column_format'] == 'hidden') {
            $this->hidden = true;
        } else {
            $this->hidden = false;
        }

    }

    // return true if field is a foreign key
    public function is_fk() {
        return $this->is_fk;
    }

    // return true if field is referenced by an FK
    public function is_ref() {
        return $this->is_ref;
    }

    // return true if field is hidden
    public function is_hidden() {
        return $this->hidden;
    }

    // return the default value
    public function get_default() {
        return $this->default;
    }

    // return name of field (e.g. sample)
    public function get_name() {
        return $this->name;
    }

    // if a field is referenced by a FK
    // return the table.col it references
    public function get_ref() {
        return $this->ref;
    }

    // return full name of field (db123.matatu_samples.sample)
    public function get_full_name() {
        return DB_NAME_EL . '.' . $this->get_table() . '.' . $this->get_name();
    }    

    // return name of table this field belongs to
    public function get_table() {
        return $this->table;
    }

    // return true if field is a primary key
    public function is_pk() {
        return $this->key == 'PRI' ? true : false;
    }

    // return field type
    public function get_type() {
        return $this->type;
    }

    // return field type length
    // if no type, return false,
    // if float, date or timestamp return null
    // if int or string, return int val
    public function get_length() {
        if ($this->type) {
            if (strpos($this->type, '(') !== false) {
                return intval(str_replace(')', '', explode('(', $this->type)[1]));
            } else {
                return null;
            }
        } else {
            return false;
        }
    }

    // return true if field is required
    public function is_required() {
        return $this->required;
    }

    // return true if field is unique (PRI or UNI key)
    public function is_unique() {
        return in_array($this->key, array('PRI','UNI'));
    }

    // return comment attribute
    public function get_comment() {
        return $this->comment;
    }

    // if a field is unique, return the current values
    // of the field, otherwise false
    public function get_unique_vals() {
        if ( $this->is_unique() ) {
            $sql = sprintf("SELECT DISTINCT(`%s`) FROM %s.%s", $this->get_name(), DB_NAME_EL, $this->get_table());
            $result = $db->query($sql)->fetchAll();
            $vals = array();
            if ($result) {
                foreach($result as $row) {
                    $vals[] = $row[$this->name];
                }
                return $vals;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    // if a field is an fk, this will return
    // the field it references
    public function get_fk_field() {
        $ref = explode('.',$this->fk_ref);
        return $ref[1];
    }

    // will return a list of possible values a
    // field can take assuming it is an fk
    public function get_fks() {
        if ($this->is_fk) {
            $ref = explode('.',$this->fk_ref);
            $ref_table = $ref[0];
            $ref_field = $ref[1];
            $sql = sprintf( "SELECT DISTINCT(%s) from %s.%s ORDER BY %s", $ref_field, DB_NAME_EL, $ref_table, $ref_field );
            $res = $db->query($sql)->fetchAll();
            $vals = array();
            foreach ($res as $row) {
                $vals[] = $row[$ref_field];
            }
            if ( count( $vals ) > 0 ) {
                return $vals;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    // pretty print
    public function show() {
        echo '<pre style="font-size:8px;">';
        print_r($this);
        echo '</pre>';
    }
}




?>
