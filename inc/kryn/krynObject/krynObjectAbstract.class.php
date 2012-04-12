<?php

abstract class krynObjectAbstract {

    /**
     * Object definition
     *
     * @var array
     */
    public $definition = array();

    /**
     * The key of the object
     *
     * @var string
     */
    public $object_key = '';

    /**
     * Cached primary key order
     *
     * @var array
     */
    public $primaryKeys = array();

    /**
     * Constructor
     *
     * @param string $pObjectKey
     * @param array  $pDefinition
     */
    function __construct($pObjectKey, $pDefinition){
        $this->object_key = $pObjectKey;
        $this->definition = $pDefinition;

        if (is_array($this->definition['fields'])){
            foreach ($this->definition['fields'] as $key => $field){
                if ($field['primaryKey'])
                    $this->primaryKeys[] = $key;
            }

        }
    }

    /**
     * Converts given primary values into proper SQL.
     * Resolve all patterns in krynObject::parseUrl();
     *
     * @param $pPrimaryValue
     * @return bool|string
     */
    public function primaryArrayToSql($pPrimaryValue){

        $sql = '';

        if (!is_array($pPrimaryValue)){
            $pPrimaryValue = $this->primaryStringToArray($pPrimaryValue);
        }

        if (!$pPrimaryValue) return false;

        if (array_key_exists(0, $pPrimaryValue)){
            //we have to select multiple rows
            foreach ($pPrimaryValue as $group){
                $sql .= ' (';
                foreach ($group as $primKey => $primValue){
                    $sql .= dbQuote($this->object_key).".".dbQuote($primKey)." = '".esc($primValue)."' AND ";
                }
                $sql = substr($sql, 0, -5).') OR ';
            }

            $sql = substr($sql, 0, -3);
        } else {
            //we only have to select one row
            $sql .= ' (';
            foreach ($pPrimaryValue as $primKey => $primValue){
                $sql .= dbQuote($this->object_key).'.'.dbQuote($primKey)." = '".esc($primValue)."' AND ";
            }
            $sql = substr($sql, 0, -5).')';
        }

        return $sql;

    }

    /**
     * Converts given primary values from type string into proper array definition.
     * Generates a array for the usage of krynObject:get()
     *
     * @param string $pPrimaryValue
     *
     * @return array
     */
    public function primaryStringToArray($pPrimaryValue){

        if (!$pPrimaryValue) return false;
        $groups = explode('/', $pPrimaryValue);

        $result = array();

        foreach ($groups as $group){

            $item = array();
            $primaryGroups = explode(',', $group);

            foreach ($primaryGroups as $pos => $value){


                if ($ePos = strpos($value, '=')){
                    $key = substr($value, 0, $ePos);
                    if (!in_array($key, $this->primaryKeys)) continue;
                } else if (!$this->primaryKeys[$pos]) continue;

                $key = $this->primaryKeys[$pos];

                $item[$key] = urldecode($value);
            }

            if (count($item) > 0)
                $result[] = $item;
        }

        return $result;

    }

    /**
     * Converts the array from ka.field type 'condition' to SQL
     *
     * @param array  $pConditions
     * @param string $pTable
     *
     * @return string
     */
    public static function conditionArrayToSql($pConditions, $pTable = ''){

        $result = '';

        if (is_string($pConditions[0])){
            //only one condition, ex: array('rsn', '>', 0)

            $result = self::conditionSingleField($pConditions, $pTable);

        } else {
            foreach ($pConditions as $condition){

                if (is_array($condition) && is_array($condition[0])){
                    $result .= ' ('.self::conditionArrayToSql($condition, $pTable).')';
                } else if(is_array($condition)){
                    $result .= self::conditionSingleField($condition, $pTable);
                } else if (is_string($condition)){
                    $result .= ' '.$condition.' ';
                }

            }
        }

        return $result;
    }

    private static function conditionSingleField($pCondition, $pTable = ''){

        if (($pos = strpos($pCondition[0], '.')) === false){
            $result = ($pTable?dbQuote($pTable).'.':'').dbQuote($pCondition[0]).' ';
        } else {
            $result = dbQuote(substr($pCondition[0], 0, $pos)).'.'.dbQuote(substr($pCondition[0], $pos)).' ';
        }

        if ($pCondition[1] == 'REGEXP')
            $result .= kryn::$config['db_type']=='mysql'?'REGEXP':'~';
        else
            $result .= $pCondition[1];

        if ($pCondition[1] == 'IN'){
            if (is_numeric($pCondition[2]))
                $result .= ' '.$pCondition[2];
            else
                $result .= " '".esc($pCondition[2])."'";
        } else {
            $result .= ' '.$pCondition[2];
        }

        return $result;
    }


    /**
     *
     *
     * @abstract
     * @param mixed $pPrimaryValues
     * @param string $pFields
     * @param string $pResolveForeignValues
     */
    abstract public function getItem($pPrimaryValues, $pFields = '*', $pResolveForeignValues = '*');

    /**
     *
     * @abstract
     * @param mixed $pPrimaryValues
     * @param int $pOffset
     * @param int $pLimit
     * @param bool $pCondition
     * @param string $pFields
     * @param string $pResolveForeignValues
     * @param $pOrder
     */
    abstract public function getItems($pPrimaryValues, $pOffset = 0, $pLimit = 0, $pCondition = false, $pFields = '*',
                                      $pResolveForeignValues = '*', $pOrder);

    /**
     * @abstract
     * @param $pPrimaryValues
     *
     */
    abstract public function removeItem($pPrimaryValues);

    /**
     * @abstract
     * @param $pValues
     *
     * @return inserted primary key. (last_insert_id() for SQL backend)
     */
    abstract public function addItem($pValues);

    /**
     * Updates an object
     *
     * @abstract
     * @param $pPrimaryValues
     * @param $pValues
     */
    abstract public function updateItem($pPrimaryValues, $pValues);

    /**
     * @abstract
     * @param bool|string $pCondition
     *
     * @return int
     */
    abstract public function getCount($pCondition = false);

}

?>