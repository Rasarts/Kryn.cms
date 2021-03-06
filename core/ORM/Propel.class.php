<?php

namespace Core\ORM;

use \Core\Kryn;
use \Core\Object;

/**
 * Propel ORM Wrapper.
 */
class Propel extends ORMAbstract {


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
    public $objectKey = '';

    public $query;

    public $tableMap;

    public $propelPrimaryKeys;

    public function init(){

        if ($this->propelPrimaryKeys) return;

        $this->query = $this->getQueryClass();
        $this->tableMap = $this->query->getTableMap();
        $this->propelPrimaryKeys = $this->tableMap->getPrimaryKeys();

    }

    public function primaryStringToArray($pPk){
        $this->init(); //load pks
        return parent::primaryStringToArray($pPk);
    }


    /**
     * Filters $pFields by allowed fields.
     * If '*' we return all allowed fields.
     *
     * @param array|string $pFields
     * @return array
     */
    public function getFields($pFields){

        $this->init();

        if ($pFields != '*' && is_string($pFields))
            $pFields = explode(',', str_replace(' ', '', trim($pFields)));

        $query = $this->getQueryClass();
        $tableMap = $query->getTableMap();

        $fields = array();
        $relations = array();
        $relationFields = array();

        foreach ($this->propelPrimaryKeys as $primaryKey){
            $fields[$primaryKey->getPhpName()] = $primaryKey;
        }

        if ($pFields == '*'){

            $columns = $tableMap->getColumns();
            foreach ($columns as $column){
                $fields[$column->getPhpName()] = $column;
            }

            //add relations
            $relationMap = $tableMap->getRelations();

            foreach ($relationMap as $relationName => $relation){
                if (!$relations[$relationName]){
                    $relations[$relationName] = $relation;

                    //add columns
                    if ($localColumns = $relation->getForeignColumns()){
                        foreach ($localColumns as $col){
                            $fields[$col->getPhpName()] = $col;
                        }
                    }
                    $relations[ucfirst($relationName)] = $relation;

                    $cols = $relation->getRightTable()->getColumns();
                    foreach ($cols as $col){
                        if ($relation->getType == \RelationMap::ONE_TO_ONE || $relation->getType == \RelationMap::MANY_TO_ONE){
                            $fields[$relationName.'.'.$col->getPhpName()] = $col;
                        } else {
                            $relationFields[ucfirst($relationName)][] = $col->getPhpName();
                        }
                    }
                }
            }

        } else {
            foreach ($pFields as $field){

                $relationFieldSelection = '';
                $relationName = '';

                if ( ($pos = strpos($field, '.')) !== false){
                    $relationName = ucfirst(substr($field, 0, $pos));
                    $field = ucfirst(substr($field, $pos+1));
                    $relationFieldSelection = $field;
                    $addRelationField = $field;
                    if (!$tableMap->hasRelation(ucfirst($relationName))){
                        continue;
                    }

                } else if ($tableMap->hasRelation(ucfirst($field))){
                    $relationName = ucfirst($field);
                }

                if ($relationName){
                    $relation = $tableMap->getRelation(ucfirst($relationName));

                    //check if $field exists in the foreign table
                    if ($relationFieldSelection)
                        if (!$relation->getRightTable()->hasColumnByPhpName($relationFieldSelection)) continue;

                    $relations[ucfirst($relationName)] = $relation;

                    //add foreignKeys in main table.
                    if ($localColumns = $relation->getLocalColumns()){
                        foreach ($localColumns as $col)
                            $fields[$col->getPhpName()] = $col;
                    }

                    //select at least all pks of the foreign table
                    $pks = $relation->getRightTable()->getPrimaryKeys();
                    foreach ($pks as $pk){
                       $relationFields[ucfirst($relationName)][] = $pk->getPhpName();
                    }
                    if ($addRelationField)
                        $relationFields[ucfirst($relationName)][] = $addRelationField;

                    continue;
                }

                if ($tableMap->hasColumnByPhpName(ucfirst($field)) &&
                    $column = $tableMap->getColumnByPhpName(ucfirst($field))){
                    $fields[$column->getPhpName()] = $column;
                }
            }
        }

        //filer relation fields
        foreach ($relationFields as $relation => &$objectFields){
            $objectName = $relations[$relation]->getRightTable()->getPhpName();
            $def = Object::getDefinition(lcfirst($objectName));
            $limit = $def['blacklistSelection'];
            if (!$limit) continue;
            $allowedFields = strtolower(','.str_replace(' ', '', trim($limit)).',');

            $filteredFields = array();
            foreach ($objectFields as $name){
                if (strpos($allowedFields, strtolower(','.$name.',')) === false){
                    $filteredFields[] = $name;
                }
            }
            $objectFields = $filteredFields;

        }

        //filter
        if ($this->definition['blacklistSelection']){

            $allowedFields = strtolower(','.str_replace(' ', '', trim($this->definition['blacklistSelection'])).',');

            $filteredFields = array();
            foreach ($fields as $name => $def){
                if (strpos($allowedFields, strtolower(','.$name.',')) === false){
                    $filteredFields[$name] = $def;
                }
            }
            $filteredRelations = array();
            foreach ($relations as $name => $def){
                if (strpos($allowedFields, strtolower(','.$name.',')) === false){
                    $filteredRelations[$name] = $def;
                }
            }
            return array($filteredFields, $filteredRelations, $relationFields);
        }

        return array($fields, $relations, $relationFields);

    }

    /**
     * Returns a new query class.
     *
     * @param string $pName
     * @return Object The query class object.
     */
    public function getQueryClass($pName = null){
        $objectKey = $pName ? $pName : $this->getPhpName();

        $clazz = $objectKey.'Query';
        if (!class_exists($clazz)){
            throw new \ObjectNotFoundException(tf('The object query %s of %s does not exist.', $clazz, $objectKey));
        }

        return $clazz::create();
    }

    /**
     * Returns the peer name.
     *
     * @param string $pName
     * @return string
     */
    public function getPeerName($pName = null){
        $objectKey = $pName ? $pName : $this->getPhpName();

        $clazz = ucfirst($objectKey).'Peer';
        if (!class_exists($clazz)){
            throw new \ObjectNotFoundException(tf('The object peer %s of %s does not exist.', $clazz, $objectKey));
        }

        return $clazz;
    }


    /**
     * Returns php class name.
     *
     * @param string $pName
     * @return string
     */
    public function getPhpName($pName = null){
        return $pName ? ucfirst($pName) :
            ucfirst($this->definition['propelClassName']
            ?: $this->objectKey);
    }


    /**
     * Since the core provide the pk as array('id' => 123) and not as array(123) we have to convert it for propel orm.
     * 
     * @param  array $pPk
     * @return mixed Propel PK
     */
    public function getPropelPk($pPk){
        
        $pPk = array_values($pPk);
        if (count($pPk) == 1) $pPk = $pPk[0];
        return $pPk;
    }

    /**
     * Sets the filterBy<pk> by &$pQuery from $pPk.
     *
     * @param mixed $pQuery
     * @param array $pPk
     */
    public function mapPk(&$pQuery, $pPk){
        foreach ($pPk as $key => $val){
            $filter = 'filterBy'.ucfirst($key);
            if (method_exists($pQuery, $filter))
                $pQuery->$filter($val);
        }
    }


    public function mapOptions($pQuery, $pOptions = array()){

        if ($pOptions['limit'])
            $pQuery->limit($pOptions['limit']);

        if ($pOptions['offset'])
            $pQuery->offset($pOptions['offset']);

        if (is_array($pOptions['order'])){
            foreach ($pOptions['order'] as $field => $direction){
                if (!$this->tableMap->hasColumnByPhpName(ucfirst($field))){
                    throw new \FieldNotFoundException(tf('Field %s in object %s not found', $field, $this->objectKey));
                } else {
                    $column = $this->tableMap->getColumnByPhpName(ucfirst($field));

                    $pQuery->orderBy($column->getName(), $direction);
                }
            }
        }
    }

    public function getStm($pQuery, $pCondition = null){

        //we have a condition, so extract the SQL and append our custom condition object
        $params = array();
        //var_dump('getStm --------------------------------------------------------------------------------------------');

        $id = (hexdec(uniqid())/mt_rand())+mt_rand();

        if ($pCondition){
            $pQuery->where($id.' != '.$id);
        }

        $con = \Propel::getConnection($pQuery->getDbName(), \Propel::CONNECTION_READ);
        $db = \Propel::getDB($pQuery->getDbName());
        $peer = $pQuery->getModelPeerName();
        $dbMap = \Propel::getDatabaseMap($pQuery->getDbName());

        $pQuery->setPrimaryTableName(constant($peer . '::TABLE_NAME'));
        $peer::setReturnSqlInNextSelect(true);

        list($sql, $params) = $peer::doSelectStmt($pQuery); //triggers all behaviors that attached code to preSelect();

        if ($pCondition){
            $data = $params;
            $condition = dbConditionToSql($pCondition, $data, $pQuery->getPrimaryTableName());
            $sql = str_replace($id.' != '.$id, $condition, $sql);
        }

        //var_dump($sql);
        $stmt = $con->prepare($sql);
        $db->bindValues($stmt, $params, $dbMap);

        if ($data){
            foreach ($data as $idx => $v){
                if (!is_array($v)){ //propel uses arrays as bind values, we with dbConditionToSql not.
                    $stmt->bindValue($idx, $v);
                }
            }
        }

        $stmt->execute();

        return $stmt;

/*
        $pQuery->setPrimaryTableName(constant($this->getPeerName() . '::TABLE_NAME'));

        list($sql, $params) = $pQuery->getSql();

        if ($pCondition){
            $data = $params;
            $condition = dbConditionToSql($pCondition, $data, $pQuery->getPrimaryTableName());
            $sql = str_replace($id.' != '.$id, $condition, $sql);
        }

        $stmt = $pQuery->bindValues($sql, $params, $dbMap);

        if ($data){
            foreach ($data as $idx => $v){
                if (!is_array($v)){ //propel uses arrays as bind values, we with dbConditionToSql not.
                    $stmt->bindValue($idx, $v);
                }
            }
        }

        $stmt->execute();
        return $stmt;
*/
    }

    public function mapToOneRelationFields($pQuery, $pRelations, $pRelationFields){

        if ($pRelations){
            foreach ($pRelations as $name => $relation){
                if ($relation->getType() != \RelationMap::MANY_TO_MANY && $relation->getType() != \RelationMap::ONE_TO_MANY){

                    $pQuery->{'join'.$name}($name);
                    $pQuery->with($name);

                    if ($pRelationFields[$name]){
                        foreach ($pRelationFields[$name] as $col){
                            $pQuery->addAsColumn('"'.$name.".".$col.'"', $name.".".$col);
                        }
                    }

                    //todo, add ACL condition for object $relation->getForeignTable()->getPhpName()
                    //var_dump($relation->getForeignTable()->getPhpName()); exit;
                }
            }
        }

    }

    /**
     * Generates a row from the propel object using the get*() methods. Resolves *-to-many relations.
     *
     * @param      $pClazz
     * @param      $pRow
     * @param      $pSelects
     * @param      $pRelations
     * @param      $pRelationFields
     * @param bool $pPermissionCheck
     *
     * @return array
     */
    public function populateRow($pClazz, $pRow, $pSelects, $pRelations, $pRelationFields, $pPermissionCheck = false){

        $item = new $pClazz();
        $item->hydrateFromNames($pRow, \BasePeer::TYPE_FIELDNAME);

        foreach ($pSelects as $select){
            $newRow[lcfirst($select)] = $item->{'get'.$select}();
        }

        if ($pRelations){
            foreach ($pRelations as $name => $relation){

                if ($relation->getType() != \RelationMap::MANY_TO_MANY && $relation->getType() != \RelationMap::ONE_TO_MANY){
                    if (is_array($pRelationFields[$name])){
                        
                        $foreignClazz = $relation->getForeignTable()->getPhpName();
                        $foreignObj = new $foreignClazz();
                        $foreignRow = array();
                        $allNull = true;

                        foreach ($pRelationFields[$name] as $col){
                            if ($pRow[$name.".".$col] !== null){
                                $foreignRow[$col] = $pRow[$name.".".$col];
                                $allNull = false;
                            }
                        }

                        if ($allNull){
                            $newRow[lcfirst($name)] = null;
                        } else {
                            $foreignObj->hydrateFromNames($foreignRow, \BasePeer::TYPE_FIELDNAME);

                            $foreignRow = array();
                            foreach ($pRelationFields[$name] as $col){
                                $foreignRow[lcfirst($col)] = $foreignObj->{'get'.$col}();
                            }
                            $newRow[lcfirst($name)] = $foreignRow;
                        }
                    }
                } else {
                    //*-to-many, we need a extra query
                    if (is_array($pRelationFields[$name])){
                        $sClazz    = $relation->getRightTable()->getClassname();

                        $queryName = $sClazz.'Query';
                        $filterBy  = 'filterBy'.$relation->getSymmetricalRelation()->getName();
                        //var_dump($queryName);

                        $sQuery = $queryName::create()
                            ->select($pRelationFields[$name])
                            ->$filterBy($item);
                        //var_dump($sQuery->toString());

                        $condition = array();
                        if ($pPermissionCheck){
                            $condition = \Core\Permission::getListingCondition(lcfirst($sClazz));
                        }
                        //var_dump($sQuery->toString());
                        $sStmt = $this->getStm($sQuery, $condition);
                        //die();

                        $sItems = array();
                        while ($subRow = dbFetch($sStmt)){

                            $sItem = new $sClazz();
                            $sItem->hydrateFromNames($subRow, \BasePeer::TYPE_FIELDNAME);

                            $temp = array();
                            foreach ($pRelationFields[$name] as $select){
                                $temp[lcfirst($select)] = $sItem->{'get'.$select}();
                            }
                            $sItems[] = $temp;
                        }
                        dbFree($sStmt);
                    } else {
                        $get = 'get'.$relation->getPluralName();
                        $sItems = $item->$get();
                    }

                    if ($sItems instanceof \PropelObjectCollection)
                        $newRow[lcfirst($name)] = $sItems->toArray(null, null, \BasePeer::TYPE_STUDLYPHPNAME) ?: null;
                    else if (is_array($sItems) && $sItems)
                        $newRow[lcfirst($name)] = $sItems;
                    else
                        $newRow[lcfirst($name)] = null;
                }
            }
        }

        return $newRow;

    }

    /**
     * {@inheritDoc}
     */
    public function getItems($pCondition = null, $pOptions = null){

        $this->init();
        $query = $this->getQueryClass();

        list($fields, $relations, $relationFields) = $this->getFields($pOptions['fields']);
        $selects = array_keys($fields);
        $query->select($selects);

        $this->mapOptions($query, $pOptions);

        $this->mapToOneRelationFields($query, $relations, $relationFields);

        $stmt = $this->getStm($query, $pCondition);

        $clazz = $this->getPhpName();

        while ($row = dbFetch($stmt)){
            $result[] = $this->populateRow($clazz, $row, $selects, $relations, $relationFields, $pOptions['permissionCheck']);
        }

        dbFree($stmt);
        return $result;
    }




    /**
     * {@inheritDoc}
     */
    public function getItem($pPrimaryKey, $pOptions = array()){

        $this->init();
        $query = $this->getQueryClass();
        $query->limit(1);

        list($fields, $relations, $relationFields) = $this->getFields($pOptions['fields']);

        $selects = array_keys($fields);

        $query->select($selects);

        $this->mapOptions($query, $pOptions);

        $this->mapToOneRelationFields($query, $relations, $relationFields);

        $this->mapPk($query, $pPrimaryKey);

        $item = $query->findOne();
        if (!$item) return false;

        $stmt = $this->getStm($query);

        $row = dbFetch($stmt);
        dbFree($stmt);

        $clazz = $this->getPhpName();

        return $row===false?false:$this->populateRow($clazz, $row, $selects, $relations, $relationFields, $pOptions['permissionCheck']);
    }



    /**
     * {@inheritdoc}
     */
    public function remove($pPk){

        $query = $this->getQueryClass();

        $this->mapPk($query, $pPk);
        $item = $query->findOne();

        return $item->delete();
    }


    /**
     * {@inheritdoc}
     */
    public function clear(){

        $query = $this->getQueryClass();

        if ($this->definition['workspace']){
            //delete all versions
            $versionQueryClazz = $this->getPhpName().'VersionQuery';
            $versionQuery = new $versionQueryClazz;
            $versionQuery->deleteAll();
        }

        return $query->deleteAll();
    }

    public function getVersions($pPk, $pOptions = null){

        $queryClass = $this->getPhpName().'VersionQuery';
        $query = new $queryClass();

        $query->select(array('id', 'workspaceRev', 'workspaceAction', 'workspaceActionDate', 'workspaceActionUser'));

        $query->filterByWorkspaceId(\Core\WorkspaceManager::getCurrent());

        $this->mapPk($query, $pPk);

        return $query->find()->toArray();

    }

    public function getVersionDiff($pPk, $pOptions = null){

        //default is the diff to the previous

    }



    /**
     * {@inheritdoc}
     */
    public function update($pPk, $pValues){

        $query = $this->getQueryClass();

        $this->mapPk($query, $pPk);
        $item = $query->findOne();

        $this->mapValues($item, $pValues);

        return $item->save()?true:false;
    }


    /**
     * {@inheritDoc}
     */
    public function move($pPk, $pTargetPk, $pMode = 'into', $pTargetObjectKey = null){

        $query = $this->getQueryClass();
        $item = $query->findPK($pPk);

        $method = 'moveToLastChildOf';
        if ($pMode == 'up' || $pMode == 'before')
            $method = 'moveToPrevSiblingOf';
        if ($pMode == 'down' || $pMode == 'below')
            $method = 'moveToNextSiblingOf';

        if (!$pTargetPk){
            //search root
            $target = $query->findRoot();
            $method = 'moveToLastChildOf';
        } else {

            if ($this->objectKey != $pTargetObjectKey){
                if (!$this->definition['nestedRootAsObject'])
                    throw new \InvalidArgumentException('This object has no different object as root.');

                $scopeField = 'get'.ucfirst($this->definition['nestedRootObjectField']);
                $scopeId = $item->$scopeField();
                $method = 'moveToLastChildOf';

                $target = $query->findRoot($scopeId);
            } else {
                $target = $query->findPK($this->getPropelPk($pTargetPk));
            }
        }

        if ($item == $target){
            return false;
        }

        if ($target){
            return $item->$method($target) ? true : false;
        } else {
            throw new \Exception('Can not find the appropriate target.');
        }

    }


    /**
     * {@inheritdoc}
     */
    public function add($pValues, $pBranchPk = false, $pMode = 'first', $pScope = 0){

        $this->init();

        $clazz = $this->getPhpName();
        $obj = new $clazz();

        if ($this->definition['nested']){

            $query = $this->getQueryClass();
            if ($pBranchPk)
                $branch = $query->findPk($this->getPropelPk($pBranchPk));
            else {
                $branch = $query->findRoot($pScope);
                $root = true;
            }

            switch (strtolower($pMode)){
                case 'first': $obj->insertAsFirstChildOf($branch); break;
                case 'last':  $obj->insertAsLastChildOf($branch); break;
                case 'prev':  if (!$root) $obj->insertAsPrevSiblingOf($branch); break;
                case 'next':  if (!$root) $obj->insertAsNextSiblingOf($branch); break;
            }

            if ($pScope){
                $obj->setScopeValue($pScope);
            }
        }

        $this->mapValues($obj, $pValues);

        if (!$obj->save()) return false;

        return $this->pkFromRow($obj->toArray(\BasePeer::TYPE_STUDLYPHPNAME));
    }

    public function mapValues($pItem, $pValues){

        foreach ($pValues as $fieldName => $fieldValue){

            $field = $this->getField($fieldName);
            $fieldName = ucfirst($fieldName);

            $set = 'set'.$fieldName;
            $methodExist = method_exists($pItem, $set);

            if (!$field && !$methodExist) continue;

            if ($field['type'] == 'object' || $this->tableMap->hasRelation($fieldName)){


                if ($field['objectRelation'] == 'nToM'){

                    //$getItems = 'get'.underscore2Camelcase($fieldName).'s';
                    $setItems = 'set'.underscore2Camelcase($fieldName).'s';
                    $clearItems = 'clear'.underscore2Camelcase($fieldName).'s';


                    if ($fieldValue){

                        $foreignQuery = $this->getQueryClass($field['object']);

                        $foreignObjClass = \Core\Object::getClass($field['object']);

                        foreach ($fieldValue as $value){
                            $primaryKeys[] = $foreignObjClass->normalizePrimaryKey($value);
                        }

                        $propelPks = array();
                        foreach ($primaryKeys as $primaryKey){
                            $propelPks[] = $this->getPropelPk($primaryKey);
                        }

                        $collItems = $foreignQuery->findPks($propelPks);
                        $pItem->$setItems($collItems);
                    } else {
                        $pItem->$clearItems();
                    }
                    continue;
                }
            }

            if ($methodExist){
                $pItem->$set($fieldValue);
            } else {
                throw new \FieldNotFoundException(tf('Field %s in object %s not found (%s)', $fieldName, $this->objectKey, $set));
            }


        }

    }
    /**
     * {@inheritdoc}
     */
    public function getCount($pCondition = false){

        $query = $this->getQueryClass();

        $query->clearSelectColumns()->addSelectColumn('COUNT(*)');

        $stmt = $this->getStm($query, $pCondition);

        $row = dbFetch($stmt);

        dbFree($stmt);

        return current($row)+0;

    }

    public function pkFromRow($pRow){
        $pks = array();
        foreach ($this->primaryKeys as $pk){
            $pks[$pk] = $pRow[$pk];
        }
        return $pks;
    }

    /**
     * {@inheritdoc}
     */
    public function getTree($pPk = null, $pCondition = null, $pDepth = 1, $pScope = null, $pOptions = null){

        $query = $this->getQueryClass();
        if (!$pPk){
            if ($pScope === null && $this->definition['nestedRootAsObject'])
                throw new \InvalidArgumentException('Argument scope is missing.');
            $parent = $query->findRoot($pScope);
        } else {
            $parent = $query->findPK($this->getPropelPk($pPk));
        }

        $query = $this->getQueryClass();

        $query->childrenOf($parent);

        list($fields, $relations, $relationFields) = $this->getFields($pOptions['fields']);
        $selects = array_keys($fields);

        $selects[] = 'Lft';
        $selects[] = 'Rgt';
        $selects[] = 'Title';
        $query->select($selects);

        $query->orderByBranch();

        $this->mapOptions($query, $pOptions);

        $this->mapToOneRelationFields($query, $relations, $relationFields);

        $stmt = $this->getStm($query, $pCondition);

        $clazz = $this->getPhpName();

        while ($row = dbFetch($stmt)){
            $item = $this->populateRow($clazz, $row, $selects, $relations, $relationFields, $pOptions['permissionCheck']);
            $item['_childrenCount'] = ($item['rgt'] - $item['lft'] - 1)/2;
            if ($pDepth > 1 && $item['_childrenCount'] > 0){
                $item['_children'] = self::getTree($this->pkFromRow($item), $pCondition, $pDepth-1, $pScope, $pOptions);
            }
            $result[] = $item;
        }

        dbFree($stmt);

        return $result;
    }


}