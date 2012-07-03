<?php

/*
* This file is part of Kryn.cms.
*
* (c) Kryn.labs, MArc Schmidt <marc@kryn.org>
*
* To get the full copyright and license informations, please view the
* LICENSE file, that was distributed with this source code.
*/


/**
 * Database class
 *
 * @author MArc Schmidt <marc@kryn.org>
 * @internal
 */

define("DB_FETCH_ALL", -1);

class database {

    /**
     * Which target database we use (mysql, postgresql, etc)
     *
     * @var string
     */
    public $type;

    /**
     * Counter of queries
     *
     * @var int
     */
    public $queryCount = 0;

    /**
     * Instance of current PDO object
     *
     * @var
     */
    public $pdo;

    /**
     * Ressource to current database
     *
     * @var
     */
    public $connection;

    /**
     * Stores the last error (if some appears)
     *
     * @var string
     */
    public $lastError;

    /**
     * Stores last inserted table
     *
     * @var string
     */
    public $lastInsertTable;

    /**
     *  If true, we do not log or throw errors
     *
     * @var bool
     */
    public static $hideReporting = false;

    /**
     * Some types which represents a number
     *
     * @var array
     */
    private static $needToInt = array('int*', 'tinyint*', 'bit*', 'timestamp', 'double', 'float', 'bigint*');

    /**
     * Defines whether we use the pdo driver
     *
     * @var bool
     */
    public $usePdo = false;

    /**
     * Defined whether this connection is connected to a slave
     *
     * @var bool
     */
    public $readOnly = false;

    public static $activeTransaction = false;
    public static $activeLock = false;


    public function __construct($pDatabaseType = false, $pHost = false, $pUser = false,
                                $pPassword = false, $pDatabaseName = false, $pUsePdo = true) {

        if ($pDatabaseType)
            $this->type = $pDatabaseType;

        if ($pUsePdo)
            $this->usePdo = true;

        if ($pDatabaseType && $pHost) {
            $this->login($pHost, $pUser, $pPassword, $pDatabaseName);
        }
    }


    public static function isIntEscape($pType) {
        if (!$pType) return false;
        foreach (self::$needToInt as $type) {
            if (preg_match("/^$type/", $pType))
                return true;
        }
    }

    public static function getTable($pTable) {

        if (kryn::$tables[$pTable]) return pfx . $pTable;
        return $pTable;

    }

    public static function getRelation($pTableOne, $pTableTwo) {

        if (kryn::$configs) {
            foreach (kryn::$configs as $config) {
                if ($config['db_relations']) {

                    if ($config['db_relations'][$pTableOne] && $config['db_relations'][$pTableOne]['table'] == $pTableTwo) {
                        return $config['db_relations'][$pTableOne];

                    } else if ($config['db_relations'][$pTableTwo] && $config['db_relations'][$pTableTwo]['table'] == $pTableOne) {
                        $res = array(
                            'table' => $pTableOne
                        );
                        if ($config['db_relations'][$pTableTwo]['relation'] == '1-n')
                            $res['relation'] = 'n-1';
                        if ($config['db_relations'][$pTableTwo]['relation'] == 'n-1')
                            $res['relation'] = '1-n';

                        foreach ($config['db_relations'][$pTableTwo]['relation'] as $left_field => $right_field) {
                            $res['fields'][$right_field] = $left_field;
                        }
                        return $res;
                    }
                }
            }
        }

        return false;
    }

    public static function getAllTables() {

        $res = array();

        switch (kryn::$config['db_type']) {
            case 'sqlite':
                $ttemp = dbExfetch("SELECT * FROM sqlite_master WHERE type = 'table'", -1);
                if (count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[] = $t['name'];
                    }
                }
                break;

            case 'postgresql':
                $ttemp = dbExfetch("SELECT tablename FROM pg_tables", -1);
                if (count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[] = current($t);
                    }
                }
                break;

            case 'mysql':
            case 'mysqli':
                $ttemp = dbExfetch('SHOW TABLES', -1);
                if (count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[] = current($t);
                    }
                }
                break;

        }

        if (count($res) > 0) {
            foreach ($res as $table) {
                $tables[$table] = true;
            }
        }
        return $tables;
    }

    public static function getColumns($pTable) {
        global $kdb;

        $res = array();
        $qTable = dbQuote($pTable);

        switch ($kdb->type) {
            case 'sqlite':
                $ttemp = dbExfetch("PRAGMA table_info($qTable)", -1);
                if (count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[$t['name']] = array(
                            'type' => $t['type']
                        );
                    }
                }
                break;

            case 'postgresql':
                $ttemp = dbExfetch("SELECT * FROM information_schema.columns WHERE table_name = '$pTable'", -1);
                if (count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[$t['column_name']] = array(
                            'type' => $t['data_type']
                        );
                    }
                }
                break;

            case 'mysql';
                $ttemp = dbExfetch('SHOW COLUMNS FROM ' . $qTable, -1);
                if (is_array($ttemp) && count($ttemp) > 0) {
                    foreach ($ttemp as $t) {
                        $res[$t['Field']] = array(
                            'type' => $t['Type']
                        );
                    }
                }
                break;
        }

        return $res;
    }


    public function login($host, $user = '', $pw = '', $kdb = NULL, $pForceUtf8 = false) {

        if (strpos($host, ':') !== false) {
            $t = explode(":", $host);
            if (is_array($t) && $t[1] != "")
                $port = $t[1];
        }

        if (!$this->usePdo) {
            try {
                switch ($this->type) {
                    case 'sqlite':
                        $this->connection = new SQLite3($host);
                        //$this->connection->query('PRAGMA short_column_names = 1');
                        break;
                    case 'mysql':
                        if ($this->connection = mysql_pconnect($host, $user, $pw)) {
                            if (!@mysql_select_db($kdb, $this->connection)) {
                                die("Can not select db: " . $kdb);
                            }
                            mysql_query("SET NAMES 'utf8'", $this->connection);
                        } else {
                            $this->lastError = mysql_error();
                            return false;
                        }
                        break;
                    case 'mysqli':
                        if ($this->connection = mysqli_pconnect($host, $user, $pw)) {
                            if (!@mysqli_select_db($this->connection, $kdb)) {
                                die("Can not select db: " . $kdb);
                            }
                            mysqli_query("SET NAMES 'utf8'", $this->connection);
                        } else {
                            $this->lastError = mysqli_error($this->connection);
                            return false;
                        }
                        break;
                    case 'mssql':
                        @ini_set('mssql.charset', 'utf8');
                        $this->connection = mssql_pconnect($host, $user, $pw);
                        if (!@mssql_select_db($kdb, $this->connection)) {
                            die("Can not select db: " . $kdb);
                        }
                        break;
                    case 'oracle':
                        if (!$this->connection = oci_pconnect($user, $pw, $host . "/" . $kdb, 'UTF8')) {
                            $this->lastError = oci_error();
                            return false;
                        }
                        break;
                    case 'postgresql':
                        if (!$port) {
                            $port = 5432;
                        }
                        if ($user != '' && $user) {
                            $connect_string = "host=$host port=$port dbname=$kdb user=$user password=$pw";
                        } else {
                            $connect_string = "host=$host port=$port dbname=$kdb";
                        }
                        $connect_string . +" options='--client_encoding=UTF8'";


                        if (!$this->connection = pg_pconnect($connect_string)) {
                            $this->lastError = pg_last_error();
                        }
                        break;
                }
            } catch (Exception $e) {
                die('ERROR');
                $this->lastError = $e;
                return false;
            }
            return $this->connection;

        } else {

            //pdo is deactivated, too unstable right now
            die('PDO is deactiavted, please change the inc/config.php db_pdo => 0');

            switch ($this->type) {
                case 'mysql':
                case 'mysqli':
                    $pdoString = "mysql:host=$host;dbname=$kdb";
                    break;
                case 'postgresql':
                    $pdoString = "pgsql:dbname=$kdb;host=$host";
                    break;
                case 'sqlite':
                    $pdoString = "sqlite:$host";
                    break;
                case 'oracle':
                    if (!$port)
                        $port = 1521;
                    $tns = "
    						(DESCRIPTION =
    						    (ADDRESS_LIST =
    						      (ADDRESS = (PROTOCOL = TCP)(HOST = $host)(PORT = $port))
    						    )
    						  )
    						       ";
                    $pdoString = "oci:dbname=$tns";
                    break;
                case 'firebird':
                    $pdoString = "firebird:dbname=$host:$kdb";
                    break;
            }

            try {
                $opts = null;
                if ($this->type != 'postgresql') {
                    $opts = array(
                        PDO::ATTR_PERSISTENT => true
                    );
                }

                $this->pdo = new PDO($pdoString, $user, $pw, $opts);
            } catch (PDOException $e) {
                $this->lastError = $e->getMessage();
                return false;
            }

            //check if we need to force utf8 for mysql
            if ($pForceUtf8 && ($this->type == 'mysql' || $this->type == 'mysqli'))
                $this->pdo->query("SET NAMES 'utf8'");

            if ($this->type == 'mysql' || $this->type == 'mysqli')
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

            return $this->pdo;
        }
    }

    public function lastError() {
        return $this->lastError;
    }

    public function connected() {
        if ($this->pdo || $this->connection)
            return true;
        return false;
    }

    public function fetch($pStatement, $pRows = 1) {
        if ($pStatement === false) return;

        if (!$this->usePdo) {

            if ($pRows == 1) {
                switch ($this->type) {
                    case 'sqlite':
                        $res = $pStatement->fetchArray(SQLITE3_ASSOC);
                        break;
                    case 'mysql':
                        $res = mysql_fetch_assoc($pStatement);
                        break;
                    case 'mysqli':
                        $res = mysqli_fetch_assoc($pStatement);
                        break;
                    case 'mssql':
                        $res = mssql_fetch_assoc($pStatement);
                        break;
                    case 'postgresql':
                        $res = pg_fetch_assoc($pStatement);
                        break;
                }
            } else {
                $res = array();
                $i = 0;
                switch ($this->type) {
                    case 'sqlite':
                        while (($pRows > $i++ || $pRows == -1) && $row = $pStatement->fetchArray(SQLITE3_ASSOC))
                            $res[] = $row;
                        break;
                    case 'mysql':
                        while (($pRows > $i++ || $pRows == -1) && $row = mysql_fetch_assoc($pStatement))
                            $res[] = $row;
                        break;
                    case 'mssql':
                        while (($pRows > $i++ || $pRows == -1) && $row = mssql_fetch_assoc($pStatement))
                            $res[] = $row;
                        break;
                    case 'mysqli':
                        while (($pRows > $i++ || $pRows == -1) && $row = mysqli_fetch_assoc($pStatement))
                            $res[] = $row;
                        break;
                    case 'postgresql':
                        while (($pRows > $i++ || $pRows == -1) && $row = pg_fetch_assoc($pStatement))
                            $res[] = $row;
                        break;
                }
            }
        } else {

            if (!$pStatement) return false;

            if ($pRows == 1)
                $res = $pStatement->fetch(PDO::FETCH_ASSOC);
            else
                $res = $pStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($res && is_array($res)) {
            foreach ($res as $index => &$row)


                if ($index !== 0 && $index + 0 == 0) {
                    if ($index != strtolower($index))
                        $res[strtolower($index)] = $row;
                } else {

                    if ($row && is_array($row)) {
                        foreach ($row as $key => $val)
                            $row[strtolower($key)] = $val;
                    }
                }
        }
        return $res;
    }

    public function rowCount($pStatement) {
        if ($pStatement === false) return;

        if (!$this->usePdo) {

            switch ($this->type) {
                case 'sqlite':
                    return $pStatement->numRows();
                    break;
                case 'mysql':
                    return mysql_num_rows($pStatement);
                    break;
                case 'mysqli':
                    return mysqli_num_rows($pStatement);
                    break;
                case 'mssql':
                    return mssql_num_rows($pStatement);
                    break;
                case 'postgresql':
                    return pg_num_rows($pStatement);
                    break;
            }
        } else {

            return $pStatement->rowCount();
        }

        return false;
    }


    public function free($pStatement) {
        if ($pStatement === false) return;

        if (!$this->usePdo) {

            switch ($this->type) {
                case 'sqlite':
                    return $pStatement->closeCursor();
                    break;
                case 'mysql':
                    return mysql_free_result($pStatement);
                    break;
                case 'mysqli':
                    return mysqli_free_result($pStatement);
                    break;
                case 'mssql':
                    return mssql_free_result($pStatement);
                    break;
                case 'postgresql':
                    return pg_free_result($pStatement);
                    break;
            }
        } else {

            return $pStatement->rowCount();
        }

        return false;
    }

    public static function clearOptionsCache($pTable) {
        $cacheKey = 'krynDatabaseTable_' . str_replace('_', '..', $pTable);
        kryn::deleteCache($cacheKey);
    }

    public static function getTablesFromObject($pObjectKey){


        $objectKey = $pObjectKey;
        $object = kryn::$objects[$objectKey];

        if (!$object || !$object['table']) return false;

        $typesMap = array(
            'text' => array('textarea', 'wysiwyg', 'codemirror', 'array', 'layoutelement', 'checkboxgroup', 'filelist'),
            'int' => array('number')
        );

        $tables = array();

        $table = array();
        foreach ($object['fields'] as $fieldKey => $field){
            $type = 'varchar';
            $length = 255;
            $autoincrement = false;
            $mode = '';

            if ($field['primaryKey'])
                $mode = 'DB_PRIMARY';

            if ($field['dbIndex'])
                $mode = 'DB_INDEX';

            if ($field['autoIncrement'])
                $autoincrement = true;

            if ($field['type'] == 'text' || $field['type'] == 'password' || $field['type'] == 'number'){
                if ($field['maxlength'] && $field['maxlength'] <= 255)
                    $length = $field['maxlength'];
            }

            if ($field['type'] == 'object'){

                $definition = kryn::$objects[$field['object']];
                if (!$definition){
                    klog('adminDb', 'The wished object cant be found: '.$field['object'].' for field '.$fieldKey.' '.
                        'during the initializing of table '.$object['table'].' for object'.$pObjectKey);
                    continue;
                }

                if (!$definition['fields']){
                    klog('adminDb', 'The wished object doesnt have any fields: '.$field['object'].' for field '.$fieldKey.' '.
                        'during the initializing of table '.$object['table'].' for object'.$pObjectKey);
                    continue;
                }

                if ($definition && $definition['fields']){

                    if ($field['object_relation'] == 'nToM'){

                        $relTable = array();
                        $leftPrimaryKeys = array();

                        $relTableName = $field['object_relation_table'];
                        if (!$relTableName)
                            $relTableName = 'relation_'.$objectKey.'_'.$field['object'];

                        foreach ($object['fields'] as $relFieldKey => $relField){

                            if ($relField['primaryKey']){
                                $relTable[$objectKey.'_'.$relFieldKey] = array(
                                    ($relField['type'] == 'number')?'int':'varchar',
                                    $relField['maxlength']?$relField['maxlength']:(($relField['type'] == 'number')?'':'255')
                                );
                                $leftPrimaryKeys[] = $objectKey.'_'.$relFieldKey;
                            }
                        }
                        $relTable['___index'][] = implode(',', $leftPrimaryKeys);

                        foreach ($definition['fields'] as $relFieldKey => $relField){
                            if ($relField['primaryKey']){
                                $relTable[$field['object'].'_'.$relFieldKey] = array(
                                    ($relField['type'] == 'number')?'int':'varchar',
                                    $relField['maxlength']?$relField['maxlength']:(($relField['type'] == 'number')?'':'255')
                                );
                                $leftPrimaryKeys[] = $relFieldKey;
                            }
                        }

                        $tables[strtolower($relTableName)] = $relTable;


                    } else {
                        //n-1

                        $primaryKeys = array();
                        $lastPrimaryKey = array();

                        foreach ($definition['fields'] as $dKey => $dField){
                            if ($dField['primaryKey']){
                                $primaryKeys[$dKey] = $dField;
                                $lastPrimaryKey = $dField;
                            }
                        }

                        if (count($primaryKeys) == 1){
                            $type = ($lastPrimaryKey['type'] == 'number')?'int':'varchar';
                            $length = $lastPrimaryKey['maxlength']?$lastPrimaryKey['maxlength']:(($lastPrimaryKey['type'] == 'number')?'':'255');

                        } else if(count($primaryKeys) > 1){

                            $index = array();
                            foreach ($primaryKeys as $pKey => $pField){
                                $index[] = $pKey;

                                $table[ $fieldKey.'_'.$pKey ] = array(
                                    ($pField['type'] == 'number')?'int':'varchar',
                                    $pField['maxlength']?$pField['maxlength']:(($pField['type'] == 'number')?'':'255')
                                );

                            }

                            continue;
                        }
                    }
                }
            }

            if (in_array($field['type'], $typesMap['text'])){
                $type = 'text';
                $length = '';
            }

            if ($field['type'] == 'number' || $field['type'] == 'datetime' || $field['type'] == 'date'){
                $type = 'int';
                $length = '';
            }

            if ($field['type'] == 'checkbox'){
                $type = 'smallint';
                $length = '';
            }

            $table[$fieldKey] = array(
                $type,
                $length,
                $mode,
                $autoincrement
            );
        }

        $tables[strtolower($object['table'])] = $table;
        return $tables;
    }

    public static function getOptions($pTable) {

        $pTable = strtolower($pTable);
        $cacheKey = 'krynDatabaseTable_' . str_replace('_', '-', $pTable);
        $columnDefs =& kryn::getCache($cacheKey);

        if (!$columnDefs) {

            $columns = false;
            $ncolumns = array();

            if (kryn::$tables[$pTable]) {
                $columns = kryn::$tables[$pTable];
            }

            if ($columns) {

                foreach ($columns as $key => &$column) {

                    $ncolumn = array();
                    $ncolumn['auto_increment'] = ($column[4]) ? true : false;
                    $ncolumn['primary'] = ($column[3] == 'DB_PRIMARY') ? true : false;
                    $ncolumn['escape'] = self::isIntEscape($column[0]) ? 'int' : 'text';
                    $ncolumn['type'] = $column[0];
                    $ncolumns[$key] = $ncolumn;

                }
                kryn::setCache($cacheKey, $ncolumns);

                return $ncolumns;

            } else {
                $columns = self::getColumns($pTable);
                if (is_array($columns) && count($columns) > 0) {

                    foreach ($columns as $key => $column) {

                        $ncolumn = array();

                        $ncolumn['escape'] = 'text';
                        $ncolumn['type'] = $column['type'];

                        if (self::isIntEscape($column['Type']) || self::isIntEscape($column['type'])
                            || self::isIntEscape($column[0])
                        ) {
                            $ncolumn['escape'] = 'int';
                        }

                        //only for mysql
                        $ncolumn['auto_increment'] = ($column['Extras'] == 'auto_increment') ? true : false;
                        //todo need it for sqlite&pgsql

                        $fieldname = $column['Field'] ? $column['Field'] : $key;

                        $ncolumns[$fieldname] = $ncolumn;
                    }

                    kryn::setCache($cacheKey, $ncolumns);
                    return $ncolumns;
                } else {
                    kryn::deleteCache($cacheKey);
                    return false;
                }
            }

        } else {
            return $columnDefs;
        }
    }

    public function lastId() {

        if($this->lastInsertTable)
            $seqName = 'kryn_' . $this->lastInsertTable . '_seq';

        if ($this->usePdo) {
            if (!$seqName) return false;
            return $this->pdo->lastInsertId($seqName) + 0;
        }

        if (!$this->usePdo) {
            switch ($this->type) {
                case 'sqlite':
                    return $this->connection->lastInsertRowID();
                case 'mysql':
                    return mysql_insert_id($this->connection);
                case 'mysqli':
                    return mysqli_insert_id($this->connection);
                case 'postgresql':

                    if (!$seqName) return false;

                    try {
                        $row = @$this->exfetch("SELECT currval('" . $seqName . "') as lastid");
                    }catch (Exception $e){
                        return false;
                    }

                    if (!$row) return false;
                    return $row['lastid'];
            }
        }
        return false;
    }


    public function updateSequences($pDb = false) {
        global $cfg;

        if (!$pDb) return;
        if ($this->type != 'postgresql') return;

        foreach ($pDb as $table => $tableFields) {

            if ($tableFields) {
                foreach ($tableFields as $fieldKey => $field) {
                    if ($field[3] == 'DB_PRIMARY') {
                        $row = dbExfetch('SELECT MAX(' . $fieldKey . ') as mmax FROM ' . pfx . $table, 1);
                        $sql = 'ALTER SEQUENCE kryn_' . pfx . $table . '_seq RESTART WITH ' . ($row['mmax'] + 1);
                        dbExec($sql);
                    }
                }
            }
        }
    }

    public static function isActive() {
        global $kdb, $cfg;

        if (!$kdb) return false;
        if ($cfg['db_pdo'] == 1 && !$kdb->pdo) return false;
        if ($cfg['db_pdo'] == 0 && !$kdb->connection) return false;

        return true;
    }

    public function exec($pQuery) {

        if ($pQuery == "")
            return false;

        if (!database::$hideReporting && kryn::$config['debug_log_sqls']){
            database::$hideReporting = true;
            klog('debug', 'query: '.$pQuery);
            database::$hideReporting = false;
        }

        $this->lastQuery = $pQuery;

        $this->lastError = null;

        $queries = explode(';', $pQuery);
        foreach ($queries as $query) {
            if (preg_match('/[\s\n\t]*INSERT[\t\n ]+INTO[\t\n ]+([a-z0-9\_\-]+)/is', $query, $matches)) {
                $this->lastInsertTable = $matches[1];
            }
        }


        if (!$this->usePdo) {
            try {
                switch ($this->type) {
                    case 'sqlite':
                        $res = $this->connection->query($pQuery);
                        break;
                    case 'mysql':
                        $res = mysql_query($pQuery, $this->connection);
                        break;
                    case 'mysqli':
                        $res = mysqli_query($this->connection, $pQuery);
                        break;
                    case 'mssql':
                        $res = mssql_query($pQuery, $this->connection);
                        break;
                    case 'postgresql':
                        $res = pg_query($this->connection, $pQuery);
                        break;
                }
            } catch (Exception $e) {
                return $this->raiseError($e);
            }

            if (!$res){
                return $this->raiseError($this->retrieveError());
            }

            return $res;
        } else {

            try {

                $res = $this->pdo->prepare($pQuery);
                if (method_exists($res, 'execute'))
                    $state = $res->execute();

            } catch (PDOException $err) {

                return $this->raiseError($err->getMessage());
            }

        }
        $this->queryCount++;


        if ($state === false)
            return false;
        elseif (is_numeric($state))
            return $state;

        return $res;
    }

    public function raiseError($pErrorStr){

        if ($this->type == 'sqlite'){
            if ($pErrorStr == 'database is locked'){
                usleep(50*1000);
                return $this->exec($this->lastQuery);
            }
        }

        if (kryn::$config['db_error_print_sql'])
            $pErrorStr .= "\n SQL: ".$this->lastQuery;

        if (!database::$hideReporting)
            klog('database', $pErrorStr);

        $this->lastError = $pErrorStr;

        if (!database::$hideReporting && kryn::$config['db_exceptions_nostop'] != 1){
            throw new Exception($pErrorStr);
        }

        return false;
    }

    public function retrieveError() {
        switch ($this->type) {
            case 'sqlite':
                $res = $this->connection->lastErrorMsg();
                break;
            case 'mysql':
                $res = mysql_error($this->connection);
                break;
            case 'mysqli':
                $res = mysqli_error($this->connection);
                break;
            case 'postgresql':
                $res = pg_last_error($this->connection);
                break;
            case 'mssql':
                $res = mssql_get_last_message();
                break;
        }
        return $res;
    }

    public function close() {

    }

    public function exfetch($pQuery, $pRowcount = 1) {
        return $this->fetch($this->exec($pQuery), $pRowcount);
    }

    public function rowExist($pQuery) {
        $row = $this->exfetch($pQuery);
        return ($row == false) ? false : true;
    }
}

?>