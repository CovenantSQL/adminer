<?php
$drivers["covenantsql"] = "CovenantSQL";

if (isset($_GET["covenantsql"])) {
    define("DRIVER", "covenantsql");
    // test if sqlite escape feature exists
    if (class_exists("SQLite3")) {
        $_covenantsql_escape_dummy_sqlite = new SQLite3(":memory:");
        function _covenantsql_escape($s)
        {
            global $_covenantsql_escape_dummy_sqlite;
            return $_covenantsql_escape_dummy_sqlite->escapeString($s);
        }
    } elseif (function_exists("sqlite_escape_string")) {
        function _covenantsql_escape($s)
        {
            return sqlite_escape_string($s);
        }
    } elseif (extension_loaded("pdo_sqlite")) {
        $_covenantsql_escape_dummy_pdo = new PDO('sqlite::memory:');
        function _covenantsql_escape($s)
        {
            global $_covenantsql_escape_dummy_pdo;
            return $_covenantsql_escape_dummy_pdo->quote($s);
        }
    } else {
        // escape not exists
        trigger_error("SQLite escape does not exists");
        function _covenantsql_escape($s)
        {
            return $s;
        }
    }

    class Min_CovenantSQL
    {
        var $extension = "CovenantSQL";
        var $server_info = "CovenantSQL/1.0";
        var $affected_rows;
        var $error;
        var $last_id;
        var $adapter_addr;
        var $database_id;

        function __construct($database_id = null)
        {
            if (!$database_id) {
                return;
            }

            global $adminer;
            list($this->adapter_addr,) = $adminer->credentials();
            $this->database_id = $database_id;
        }

        function query($query)
        {
            if (empty($this->adapter_addr)) {
                $this->error = "adapter address not provided for query";
                return false;
            }
            if (empty($this->database_id)) {
                $this->error = "database not selected to query";
                return false;
            }

            $result_obj = null;
            $has_error = true;
            $this->error = "";
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FOLLOWLOCATION => 0,
                CURLOPT_POSTFIELDS => array(
                    "database" => $this->database_id,
                    "query" => $query,
                ),
            ));

            // test if request is a write query
            $is_write_query = !preg_match('/^\\s*(?:select|show|desc|explain)/i', $query);
            if ($is_write_query) {
                // write query
                curl_setopt($ch, CURLOPT_URL, "http://" . $this->adapter_addr . "/v1/exec");
            } else {
                // read query
                curl_setopt($ch, CURLOPT_URL, "http://" . $this->adapter_addr . "/v1/query");
            }

            $result = curl_exec($ch);
            $result = @json_decode($result, true);
            if (!$result) {
                // network error
                $this->error = curl_error($ch);
            } elseif (!$result["success"]) {
                // logical error
                $this->error = $result["status"];
            } else {
                $has_error = false;
                $result = $result["data"];
                // success
                if ($is_write_query) {
                    $this->affected_rows = $result["affected_rows"];
                    $this->last_id = $result["last_insert_id"];
                } else {
                    $this->affected_rows = count($result["rows"]);
                    $result_obj = new Min_Result($result);
                }
            }

            curl_close($ch);

            return is_null($result_obj) ? !$has_error : $result_obj;
        }

        function quote($string)
        {
            return "'" . _covenantsql_escape($string) . "'";
        }

        function store_result()
        {
            return $this->_result;
        }

        function result($query, $field = 0)
        {
            $result = $this->query($query);
            if (!is_object($result)) {
                return false;
            }
            $row = $result->fetch_row();
            return $row[$field];
        }
    }

    class Min_Result
    {
        var $_result;
        var $_offset = 0;
        var $_row_offset = 0;
        var $num_rows;
        var $cols;

        function __construct($result)
        {
            $this->_result = $result["rows"];
            $this->num_rows = count($result['rows']);
            $this->cols = array();
            foreach ($result["columns"] as $col) {
                if ($col[0] == '"') {
                    $col = idf_unescape($col);
                }

                $this->cols[] = $col;
            }
        }

        function fetch_assoc()
        {
            $row = $this->fetch_row();
            if (!$row) {
                return false;
            }
            return array_combine($this->cols, $row);
        }

        function fetch_row()
        {
            $row = $this->_result[$this->_row_offset++];
            if (!$row) {
                return false;
            }
            return $row;
        }

        function fetch_field()
        {
            $name = $this->cols[$this->_offset++];
            return (object)array(
                "name" => $name,
                "orgname" => $name,
                "orgtable" => "",
            );
        }
    }

    class Min_DB extends Min_CovenantSQL
    {
        function __construct()
        {
            parent::__construct();
        }

        function select_db($database_id)
        {
            // connect database with select 1
            parent::__construct($database_id);
            return !!$this->query("SELECT 1");
        }

        function multi_query($query)
        {
            return $this->_result = $this->query($query);
        }

        function next_result()
        {
            return false;
        }
    }

    class Min_Driver extends Min_SQL
    {
        function insertUpdate($table, $rows, $primary)
        {
            $values = array();
            foreach ($rows as $set) {
                $values[] = "(" . implode(", ", $set) . ")";
            }
            return queries("REPLACE INTO " . table($table) . " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
        }
    }

    function idf_escape($idf)
    {
        return "`" . $idf . "`";
    }

    function table($idf)
    {
        return idf_escape($idf);
    }

    function connect()
    {
        return new Min_DB;
    }

    function get_databases()
    {
        return array();
    }

    function limit($query, $where, $limit, $offset = 0, $separator = " ")
    {
        return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
    }

    function limit1($table, $query, $where, $separator = "\n")
    {
        // does not support update/delete limit 1 statement
        return " $query WHERE rowid = (SELECT rowid FROM " . table($table) . $where . $separator . "LIMIT 1)";
    }

    function db_collation($db, $collections)
    {
        return "";
    }

    function engines()
    {
        return array();
    }

    function logged_user()
    {
        return get_current_user();
    }

    function tables_list()
    {
        return array_fill_keys(get_vals("SHOW TABLES"), "table");
    }

    function count_tables($databases)
    {
        return array();
    }

    function table_status($name = "")
    {
        global $connection;
        $return = array();
        foreach (get_rows("SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS 'Auto_increment' FROM sqlite_master WHERE type IN ('table', 'view') " . ($name != "" ? "AND name = " . q($name) : "ORDER BY name")) as $row) {
            $row["Rows"] = $connection->result("SELECT COUNT(*) FROM " . idf_escape($row["Name"]));
            $return[$row["Name"]] = $row;
        }
        foreach (get_rows("SELECT * FROM sqlite_sequence", null, "") as $row) {
            $return[$row["name"]]["Auto_increment"] = $row["seq"];
        }
        return ($name != "" ? $return[$name] : $return);
    }

    function is_view($table_status)
    {
        return false;
    }

    function fk_support($table_status)
    {
        return false;
    }

    function fields($table)
    {
        $return = array();
        $primary = "";
        foreach (get_rows("DESC " . $table) as $row) {
            $name = $row["name"];
            $type = strtolower($row["type"]);
            $default = $row["dflt_value"];
            $return[$name] = array(
                "field" => $name,
                "type" => (preg_match('~int~i', $type) ? "integer" : (preg_match('~char|clob|text~i', $type) ? "text" : (preg_match('~blob~i', $type) ? "blob" : (preg_match('~real|floa|doub~i', $type) ? "real" : "numeric")))),
                "full_type" => $type,
                "default" => (preg_match("~'(.*)'~", $default, $match) ? str_replace("''", "'", $match[1]) : ($default == "NULL" ? null : $default)),
                "null" => !$row["notnull"],
                "privileges" => array("select" => 1, "insert" => 1, "update" => 1),
                "primary" => $row["pk"],
            );
            if ($row["pk"]) {
                if ($primary != "") {
                    $return[$primary]["auto_increment"] = false;
                } elseif (preg_match('~^integer$~i', $type)) {
                    $return[$name]["auto_increment"] = true;
                }
                $primary = $name;
            }
        }
        return $return;
    }

    function indexes($table, $connection2 = null)
    {
        global $connection;
        if (!is_object($connection2)) {
            $connection2 = $connection;
        }
        $return = array();
        $sql = $connection2->result("SHOW CREATE TABLE " . idf_escape($table));
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $sql, $match)) {
            $return[""] = array("type" => "PRIMARY", "columns" => array(), "lengths" => array(), "descs" => array());
            preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i', $match[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $return[""]["columns"][] = idf_unescape($match[2]) . $match[4];
                $return[""]["descs"][] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
            }
        }
        if (!$return) {
            foreach (fields($table) as $name => $field) {
                if ($field["primary"]) {
                    $return[""] = array("type" => "PRIMARY", "columns" => array($name), "lengths" => array(), "descs" => array(null));
                }
            }
        }

        // TODO(): currently index desc/columns is not supported
        $indexes = get_key_vals("SHOW INDEX FROM TABLE " . idf_escape($table), $connection2, false);
        foreach ($indexes as $name) {
            $return[$name] = array(
                "type" => "INDEX",
                "lengths" => array(),
                "descs" => array(),
                "columns" => array($name),
            );
        }
        return $return;
    }

    function foreign_keys($table)
    {
        return array();
    }

    function view($name)
    {
        global $connection;
        return array("select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
            $connection->result("SHOW CREATE TABLE " . idf_escape($name))));
    }

    function collations()
    {
        return array();
    }

    function information_schema()
    {
        return false;
    }

    function error()
    {
        global $connection;
        return h($connection->error);
    }

    function create_database($db, $collation)
    {
        // not supported, should create using cql command line
        global $connection;
        $connection->error = "create database on adminer is not supported, please use cql cli";
        return false;
    }

    function drop_databases($databases)
    {
        global $connection;
        $connection->error = "drop databases on adminer is not supported, please use cql cli";
        return false;
    }

    function rename_database($name, $collation)
    {
        global $connection;
        $connection->error = "rename database is not supported by covenantsql";
        return false;
    }

    function auto_increment()
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning)
    {
        $alter = array();
        $originals = array();
        foreach ($fields as $field) {
            if ($field[1]) {
                $alter[] = ($table != "" ? "ADD " . implode($field[1]) : $field[1]);
                if ($field[0] != "") {
                    $originals[$field[0]] = $field[1][0];
                }
            }
        }
        if ($table != "") {
            foreach ($alter as $val) {
                if (!queries("ALTER TABLE " . table($table) . " $val")) {
                    return false;
                }
            }
            if ($table != $name && !queries("ALTER TABLE " . table($table) . " RENAME TO " . table($name))) {
                return false;
            }
        } elseif (!recreate_table($table, $name, $alter, $originals, $foreign)) {
            return false;
        }
        if ($auto_increment) {
            queries("UPDATE sqlite_sequence SET seq = " . $auto_increment . " WHERE name = " . q($name)); // ignores error
        }
        return true;
    }

    function recreate_table($table, $name, $fields, $originals, $foreign, $indexes = array())
    {
        if ($table != "") {
            global $connection;
            $connection->error = "recreate table is not supported by covenantsql";
            return false;
        }
        foreach ($fields as $key => $field) {
            $fields[$key] = " " . implode($field);
        }
        $fields = array_merge($fields, array_filter($foreign));
        if (!queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $fields) . "\n)")) {
            return false;
        }
        return true;
    }

    function index_sql($table, $type, $name, $columns)
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "")
            . idf_escape($name != "" ? $name : uniqid($table . "_"))
            . " ON " . table($table)
            . " $columns";
    }

    function alter_indexes($table, $alter)
    {
        foreach ($alter as $primary) {
            if ($primary[0] == "PRIMARY") {
                return recreate_table($table, $table, array(), array(), array(), $alter);
            }
        }
        foreach (array_reverse($alter) as $val) {
            if (!queries($val[2] == "DROP"
                ? "DROP INDEX " . idf_escape($val[1])
                : index_sql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")")
            )) {
                return false;
            }
        }
        return true;
    }

    function truncate_tables($tables)
    {
        return apply_queries("DELETE FROM", $tables);
    }

    function drop_views($views)
    {
        global $connection;
        $connection->error = 'view is not supported by covenantsql';
        return false;
    }

    function drop_tables($tables)
    {
        return apply_queries("DROP TABLE", $tables);
    }

    function move_tables($tables, $views, $target)
    {
        return false;
    }

    function trigger($name)
    {
        return array();
    }

    function triggers($table)
    {
        return array();
    }

    function trigger_options()
    {
        return array();
    }

    function begin()
    {
    }

    function last_id()
    {
        global $connection;
        return $connection->last_id;
    }

    function explain($connection, $query)
    {
        return $connection->query("EXPLAIN " . $query);
    }

    function found_rows($table_status, $where)
    {
    }

    function types()
    {
        return array();
    }

    function schemas()
    {
        return array();
    }

    function get_schema()
    {
        return "";
    }

    function set_schema($schema)
    {
        return "";
    }

    function create_sql($table, $auto_increment, $style)
    {
        global $connection;
        $return = $connection->result("SHOW CREATE TABLE " . idf_escape($table));
        // TODO(): no create index sql
        return $return;
    }

    function truncate_sql($table)
    {
        return "DELETE FROM " . table($table);
    }

    function use_sql($databases)
    {
    }

    function trigger_sql($table)
    {
    }

    function show_variables()
    {
        return array();
    }

    function show_status()
    {
        return array();
    }

    function convert_field($field)
    {
    }

    function unconvert_field($field, $return)
    {
        return $return;
    }

    function support($feature)
    {
        return preg_match('~^(columns|indexes|descidx|sql|status|table)$~', $feature);
    }

    $jush = "covenantsql";
    $types = array("integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0);
    $structured_types = array_keys($types);
    $unsigned = array();
    $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"); // REGEXP can be user defined function
    $functions = array("hex", "length", "lower", "round", "upper");
    $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");
    $edit_functions = array(
        array(),
        array(
            "integer|real|numeric" => "+/-",
            "text" => "||",
        )
    );
}