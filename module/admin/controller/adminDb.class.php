<?php

/*
 * This file is part of Kryn.cms.
 *
 * (c) Kryn.labs, MArc Schmidt <marc@kryn.org>
 *
 * To get the full copyright and license informations, please view the
 * LICENSE file, that was distributed with this source code.
 *
 */


/**
 * Class to handle/sync the database table schemes from config.json "db".

 */

define('DB_PRIMARY', 1);
define('DB_INDEX', 2);

class adminDb {

    public static function sync($pModuleConfig) {
        $res = array();

        if (is_array($pModuleConfig['db']) && $tables = self::tableSync($pModuleConfig['db']))
            $res = array_merge($res, $tables);


        if (is_array($pModuleConfig['objects'])){
            foreach ($pModuleConfig['objects'] as $objectKey => $object){
                $res = array_merge($res, adminDb::installObjectTable($objectKey));
            }
        };


        return $res;
    }

    public static function checkObjectTable($pObjectTable){

        $res = array();

        foreach (kryn::$objects as $objectKey => $object){
            if ($object['table'] && $object['table'] == $pObjectTable){
                $res = array_merge($res, self::installObjectTable($objectKey));
            }
        }

        return $res;
    }

    public static function installObjectTable($pObjectKey){

        $object =& kryn::$objects[$pObjectKey];
        if (!$object || !$object['tableSync']) return array();
        $tables = database::getTablesFromObject($pObjectKey);

        return $tables ? self::tableSync($tables) : array();

    }

    public static function remove($pModuleConfig) {
        if (!is_array($pModuleConfig['db'])) return false;

        $tables = database::getAllTables();
        $removedTables = array();

        foreach ($pModuleConfig['db'] as $tableName => $tableFields) {
            if ($tables[pfx.$tableName]){
                dbExec("DROP TABLE ".dbQuote(pfx.$tableName));
                unset($tables[pfx.$tableName]);
                $removedTables[pfx.$tableName] = $tableFields;
                $res[] = $tableName;
            }
        }

        if ($pModuleConfig['objects']){
            foreach ($pModuleConfig['objects'] as $objectKey => $object){
                if ($object['table']){
                    $objectTables = database::getTablesFromObject($objectKey);
                    if (is_array($tables)){
                        foreach ($objectTables as $tableName => $tableFields) {
                            if ($tables[pfx.$tableName]){
                                dbExec("DROP TABLE ".dbQuote(pfx.$tableName));
                                unset($tables[pfx.$tableName]);
                                $removedTables[pfx.$tableName] = $tableFields;
                                $res[] = $tableName;
                            }
                        }
                    }
                }
            }
        }

        //remove sequences in postgresql
        if (kryn::$config['db_type'] == 'postgresql'){
            foreach($removedTables as $table => $fields){
                foreach ($fields as $fName => $fOptions) {
                    if ($fOptions[3]){
                        $sequenceName = 'kryn_'.$table.'_seq';

                        $sequenceExist = dbExfetch("SELECT c.relname FROM pg_class c WHERE c.relkind = 'S' AND relname = '$sequenceName'", 1);

                        if ($sequenceExist){
                            dbExec("DROP SEQUENCE $sequenceName");
                        }
                    }
                }
            }
        }


        return $res;
    }

    public static function dropTables($pDb) {
        $result = array();

        $tables = database::getAllTables();

        foreach ($pDb as $tableName => $tableFields) {
            if ($tables[pfx.$tableName]){
                dbExec("DROP TABLE ".dbQuote(pfx.$tableName));
                $result[] = $tableName;
            }
        }

        return $result;
    }

    private static function tableSync($pTables) {

        if (!count($pTables) > 0)
            return false;

        $tables = database::getAllTables();

        $res = array();

        foreach ($pTables as $tableName => $tableFields) {
            $tableName = strtolower(pfx . $tableName);

            if ($tables[$tableName]) {
                self::updateTable($tableName, $tableFields);
                $res[$tableName] = 0;
            } else {
                self::installTable($tableName, $tableFields);
                $res[$tableName] = 1;
            }

            self::updateIndexes($tableName, $tableFields);
        }

        return $res;
    }


    private static function updateTable($pTable, $pFields) {

        $columns = database::getColumns($pTable);

        $tableName = dbQuote($pTable);

        //print $pTable."--------------------------------------\n";

        foreach ($pFields as $fName => $fOptions) {

            if ($fName == '___index') continue;

            $fieldDef = dbQuote($fName).' '.self::getFieldSqlType($fOptions);
            $fieldDefType = self::getFieldSqlType($fOptions);
            $field = dbQuote($fName);

            if (!array_key_exists($fName, $columns)) {

                $query = "ALTER TABLE $tableName ADD $fieldDef";

                dbExec($query);

            } else {
                //update column


                list($fieldType, $fieldOption) = self::splitFieldDefinition($columns[$fName]['type']);
                //print $columns[$fName]['type']." => $fieldType ==  $fieldOption \n";

                list($fieldType, $fieldOption) = self::splitFieldDefinition(self::getFieldSqlType(array($fieldType,$fieldOption)));
                list($newFieldType, $newFieldOption) = self::splitFieldDefinition(self::getFieldSqlType($fOptions));

                //check if the type is different
                if ($fieldType == $newFieldType && $fieldOption == $newFieldOption) continue;

                //print $columns[$fName]['type']." - $fieldType == $newFieldType && $fieldOption == $newFieldOption\n";

                if (kryn::$config['db_type'] == 'postgresql') {
                    $query = "ALTER TABLE $tableName ALTER COLUMN $field TYPE $fieldDefType";
                } else {
                    $query = "ALTER TABLE $tableName CHANGE COLUMN $field $fieldDef";
                }

                //print $columns[$fName]['type']." => $fieldType == $newFieldType && $fieldOption == $newFieldOption\n";

                try {
                    @dbExec($query);
                } catch(Exception $e){
                    klog('dbSync', "Can't change the type of column '$fName' from $fieldType to $newFieldType in table $pTable");
                }

            }
        }
    }

    public static function splitFieldDefinition($pDef){

        $fieldType = $pDef;
        $fieldOption = '';
        if (($pos1 = strpos($pDef, '(')) !== false){
            $pos2 = strpos($pDef, ')');

            $fieldType = trim(substr($pDef, 0, $pos1).substr($pDef, $pos2+1));
            $fieldOption = substr($pDef, $pos1+1, $pos2-$pos1-1);
        }

        return array(
            $fieldType,
            $fieldOption
        );
    }

    public static function installTable($pTable, $pFields) {

        $createTable = 'CREATE TABLE ' . dbQuote($pTable) . ' (' . "\n";

        $primaries = '';
        $sequence = false;

        foreach ($pFields as $fName => $fOptions) {

            if ($fName == '___index') continue;

            $createTable .= self::getField4CreateTable($pTable, $fName, $fOptions) . ", \n";

            if ($fOptions[3] && kryn::$config['db_type'] == 'postgresql'){
                //it is auto_increment, if postgresql, we need a Sequence
                $sequence = true;
            }

            if ($fOptions[2] == "DB_PRIMARY")
                $primaries .=  dbQuote($fName) . ',';
        }

        $primaries = substr($primaries, 0, -1);

        if ($primaries == '')
            $createTable = substr($createTable, 0, -3);
        else
            $createTable .= ' PRIMARY KEY ( ' . $primaries . ' )';

        $createTable .= "\n )";

        if (kryn::$config['db_type'] == 'mysql' || kryn::$config['db_type'] == 'mysqli')
            $createTable .= 'ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;';

        $createSequence = '';
        if ($sequence){

            $sequenceName = 'kryn_' . $pTable . '_seq';

            $sequenceExist = dbExfetch("SELECT c.relname FROM pg_class c WHERE c.relkind = 'S' AND relname = '$sequenceName'", 1);

            if (!$sequenceExist){
                $createSequence  = "CREATE SEQUENCE $sequenceName; ";
                $createSequence .= "ALTER SEQUENCE $sequenceName RESTART WITH 1;";
            }

        }

        if ($createSequence)
            dbExec($createSequence);


        dbExec($createTable);

    }

    public static function updateIndexes($pTable, $pFields, $pCreate = true) {

        $createIndexes = array();

        foreach ($pFields as $fName => $fOptions) {

            if ($fOptions[2] == "DB_INDEX"){
                $createIndexes[] = $fName;
            }

        }
        if (is_array($pFields['___index'])){
            foreach ($pFields['___index'] as $index){

                $createIndexes[] = $index;

            }
        }

        foreach ($createIndexes as $index){

            $indexName = $pTable.'_';
            if (strpos($index, ',') !== false){
                $indexName .= md5(preg_replace('/\W/', '_', $index)).'_idx';
            } else {
                $indexName .= $index;
            }

            if (!self::indexExists($pTable, $indexName)){

                try {
                    dbExec('CREATE INDEX ' . dbQuote($indexName) . ' ON ' . dbQuote($pTable) . ' (' . dbQuote($index) . ')');
                } catch(Exception $e){
                    return false;
                }
            }
        }

        return true;
    }

    public static function indexExists($pTable, $pIndexName){

        $table = esc($pTable);
        $keyName = esc($pIndexName);

        if (kryn::$config['db_type'] == 'postgresql'){
            $query = "SELECT * FROM pg_indexes WHERE tablename = '$table' AND indexname = '$keyName'";
        } else {
            $query = "SHOW INDEX FROM ".dbQuote($pTable)." WHERE Key_name = '$keyName'";
        }

        $res = dbExFetch($query, 1);

        return $res?true:false;

    }

    public static function getField4CreateTable($pTableName, $pFieldName, $pFieldOptions){

        $sql = dbQuote($pFieldName).' '.self::getFieldSqlType($pFieldOptions);

        //auto increment
        if ($pFieldOptions[3] == true) {

            if (kryn::$config['db_type'] == 'mysql' || kryn::$config['db_type'] == 'mysqli') {
                $sql .= ' AUTO_INCREMENT';
            }

            if (kryn::$config['db_type'] == 'postgresql') {
                $sql .= " DEFAULT nextval('kryn_" . $pTableName . "_seq')";

            }
        }

        return $sql;
    }

    public static function getFieldSqlType($pFieldOptions){

        $sql = '';

        $field = strtolower($pFieldOptions[0]);
        $unsigned = false;

        if (strpos($field, ' unsigned') !== false){
            $unsigned = true;
            $field = str_replace(' unsigned', '', $field);
        }

        switch ($field) {

            case 'char':
                $sql .= 'char(' . $pFieldOptions[1] . ')'; break;

            case 'varchar':
            case 'character varying':
                $sql .= 'varchar(' . $pFieldOptions[1] . ')'; break;
            case 'text':
                $sql .= 'text '; break;

            case 'enum': //deprecated since 1.0
                $sql .= 'varchar(255)';
                break;

            //dates
            case 'date':
                $sql .= 'date'; break;
            case 'time':
                $sql .= 'time'; break;
            case 'timestamp':
                $sql .= 'timestamp'; break;

            //numerics
            case 'boolean':
            case 'tinyint':
            case 'smallint':
                $sql .= 'smallint'; break;

            case 'int':
            case 'integer':
                $sql .= 'integer';break;

            case 'decimal':
                $sql .= 'decimal( ' . $pFieldOptions[1] . ' )'; break;

            case 'bigint':
                $sql .= 'bigint ';break;

            case 'float4':
                if (kryn::$config['db_type'] == 'postgresql')
                    $sql .= 'float4';
                else
                    $sql .= 'float';
                break;

            case 'double precision':
                $sql .= 'double precision'; break;

        }

        if ($unsigned && kryn::$config['db_type'] != 'postgresql')
            $sql .= ' UNSIGNED';

        return $sql;

    }

}

?>