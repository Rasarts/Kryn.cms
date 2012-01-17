<?php

class krynObject {


    /**
     * Array of instances of the object classes
     *
     * @var array
     */
    public static $instances = array();

    /**
     * @var array
     */
    public static $cache = array();

    /**
     * Translates the internal url to the real path.
     *
     * Example: getUrl('file://45') => '/myImageFolder/Picture1.png'
     *          getUrl('news://4/<contentPluginId>') => '/newspage/detail/my-news-title'
     *          getUrl('user://1/<contentPluginId>') => '/userdetail/admini-strator'
     *
     * @link http://docu.kryn.org/developer/extensions/internal-url
     *
     * Can return additionally 'http(s)://myDomain/' at the beginning if the target
     * is on a different domain.
     *
     * @static
     * @param string $pInternalUrl
     *
     * @return string|bool
     */
    public static function getUrl($pInternalUrl){

        //TODO, not done here

        $pos = strpos($pInternalUrl,'://');
        $object_id = substr($pInternalUrl, 0, $pos);
        $params = explode('/', substr($pInternalUrl, $pos+2));

        $objectDefinition = kryn::$objects[$object_id];
        if (!$objectDefinition) return false;

        if (method_exists($objectDefinition['_extension'], $objectDefinition['urlGetter'])){
            return call_user_func(array($objectDefinition['_extension'], $objectDefinition['urlGetter']), $params);

        } else return false;
    }

    /**
     * @static
     * @param $pInternalUrl
     * @return array [object_key, object_id/s, queryParams]
     */
    public static function parseUrl($pInternalUrl){

        $catch = 'object://';
        if (substr(strtolower($pInternalUrl),0,strlen($catch)) == $catch){
            $pInternalUrl = substr($pInternalUrl, strlen($catch));
        }

        $pos = strpos($pInternalUrl, '/');
        $questionPos = strpos($pInternalUrl, '?');

        if ($pos === false && $questionPos === false){
            return array(
                $pInternalUrl,
                false,
                array()
            );
        }

        if ($pos === false && $questionPos != false)
            $object_key = substr($pInternalUrl, 0, $questionPos);
        else
            $object_key = substr($pInternalUrl, 0, $pos);

        $params = array();

        if ($questionPos !== false){
            parse_str(substr($pInternalUrl, $questionPos+1), $params);

            if ($pos !== false)
                $object_id = substr($pInternalUrl, $pos+1, $questionPos-($pos+1));

        } else if ($pos !== false)
            $object_id = substr($pInternalUrl, $pos+1);

        if (strpos($object_id, ',')!==false){
            $object_id = explode(',', $object_id);
        }

        return array(
            $object_key,
            $object_id==""?false:$object_id,
            $params
        );
    }

    /**
     * Returns the object for the given url
     * 
     *
     * @static
     * @param $pInternalUrl
     * @return object
     */
    public static function getFromUrl($pInternalUrl){

        list($object_key, $object_id, $params) = self::parseUrl($pInternalUrl);

        return self::get($object_key, $object_id, $params);
    }


    /**
     * Returns the object for the given url
     *
     * @static
     * @param $pObjectKey
     * @param mixed $pObjectPrimaryValues
     * @param array $pOptions
     * @return array|bool
     */
    public static function get($pObjectKey, $pObjectPrimaryValues = false, $pOptions = array()){

        if (is_array($pObjectPrimaryValues)){
            //don't call every time, instead make one big sql
            $res = array();
            foreach ($pObjectPrimaryValues as $id){
                $url = $pObjectKey.'/'.$id.((count($pOptions)==0)?'':'?'.http_build_query($pOptions));
                $res[] = self::getFromUrl($url);
            }
            return $res;
        }

        $definition = kryn::$objects[$pObjectKey];
        if (!$definition) return false;

        $obj = self::getClassObject($pObjectKey);

        if (!$pOptions['fields'])
            $pOptions['fields'] = '*';

        if ($pObjectPrimaryValues !== false){

            $item = $obj->getItem($pObjectPrimaryValues, $pOptions['fields']);

            if (!$pOptions['noForeignValues'])
                self::setForeignValues($definition, $item, $pOptions);

            return $item;

        } else {

            if (!$pOptions['offset']) $pOptions['offset'] = 0;
            if (!$pOptions['limit'] && $definition['table_default_limit']) $pOptions['limit'] = $definition['table_default_limit'];

            return $obj->getItems($pOptions['offset'], $pOptions['limit'], $pOptions['condition'], $pOptions['fields']);
        }
    }


    public static function getClassObject($pObjectKey){


        $definition = kryn::$objects[$pObjectKey];
        if (!$definition) return false;

        if (!self::$instances[$pObjectKey]){
            if ($definition['class']){
                $path = (substr($definition[''], 0, 5) == 'kryn/'?'inc/':'inc/module/').$definition['class'].'.class.php';
                @require_once($path);

                $p = explode('/', $definition['class']);
                $className = $p[count($p)-1];
                if ($className && class_exists($className)){
                    self::$instances[$pObjectKey] = new $className($definition);
                } else throw new Exception('Create object instance error: Class '.$className.' not found');

            } else if ($definition['table']){

                @require_once('inc/kryn/krynObject/krynObjectTable.class.php');
                self::$instances[$pObjectKey] = new krynObjectTable($definition);
            }
        }

        return self::$instances[$pObjectKey];

    }

    /**
     * Replaces the foreign keys with the real value/label (table column: $pDefinition['fields'][..]['object_label'])
     * mapped as id $pDefinition['fields'][..]['object_label_map']
     *
     * @static
     * @param  array &$pDefinition
     * @param  array &$pItem
     * @param  array &$pParams
     *
     */
    public static function setForeignValues(&$pDefinition, &$pItem, $pParams){

        if ($pDefinition['fields']){
            $fields = $pParams['fields'];
            if ($fields != '*')
                $fields = ','.$fields.',';

            foreach ($pDefinition['fields'] as $key => &$field){

                if ($fields != '*' && strpos($fields, ','.$key.',') === false){;
                    continue;
                }

                if ($field['type'] == 'object' && $field['object'] && !$pItem[$field['object_foreign_label']]){

                    $label = $field['object_label']?$field['object_label']:kryn::$objects[$field['object']]['object_label'];
                    $object = self::get($field['object'].'://'.$pItem[$key].'?fields='.$label);

                    $key = $field['object_label_map']?$field['object_label_map']:$field['object'].'_'.$field['object_label'];
                    $pItem[$key] = $object[$label];
                }

            }
        }
    }

    /**
     * Sets the object for the given url
     *
     * @static
     * @param  $pInternalUrl
     * @param  $pObject
     * @return bool
     */
    public static function set($pInternalUrl, $pObject){

        //TODO, not done here

        list($object_key, $object_id, $params) = self::parseUrl($pInternalUrl);

        $objectDefinition = kryn::$objects[$object_key];
        if (!$objectDefinition) return false;

        if (method_exists($objectDefinition['_extension'], $objectDefinition['setter'])){
            return call_user_func(array($objectDefinition['_extension'], $objectDefinition['setter']), $params, $pObject);
        } else return false;

    }

    public static function countFromUrl($pInternalUrl){
        list($object_key, $object_id, $params) = self::parseUrl($pInternalUrl);

        return self::count($object_key, $params['condition']);
    }


    public static function count($pObjectKey, $pCondition = ''){

        $obj = self::getClassObject($pObjectKey);

        if (!$obj) return array('error'=>'object_not_found');

        return $obj->getCount($pCondition);

    }

    /**
     * TBD
     *
     * @static
     * @param $pObjectId
     * @param $pObject
     */
    public static function add($pObjectId, $pObject){
        //TODO
    }

    public static function removeUsages($pObjectId){

    }

    public static function removeUsage($pObjectId, $pUseObjectId){

    }

    public static function addUsage($pObjectId, $pUseObjectId){



    }

}

?>