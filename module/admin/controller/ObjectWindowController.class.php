<?php

namespace Admin;

use RestService\Server;

/**
 * RestController for the entry points which are from type store or framework window.
 *
 */
class ObjectWindowController extends Server {

    public $entryPoint;

    public function exceptionHandler($pException){
        if (get_class($pException) != 'AccessDeniedException')
            \Core\Utils::exceptionHandler($pException);
    }

    public function run($pEntryPoint){

        $this->entryPoint = $pEntryPoint;

        if ($pEntryPoint['type'] == 'store') {

            if (!$pEntryPoint['class']) {
                $obj = new adminStore();
            } else {
                require_once(PATH_MODULE . '' . $pEntryPoint['_module'] . '/' . $pEntryPoint['class'] . '.class.php');
                $clazz = $pEntryPoint['class'];
                $obj = new $clazz();
            }

            try {
                $this->send($obj->handle($pEntryPoint));
            } catch (Exception $e){
                $this->sendError('AdminStoreException', array('exception' => $e->getMessage(), 'entryPoint' => $pEntryPoint));
            }
        } else {


            $this

                ->addGetRoute(':branch', 'getRootBranchItems')
                ->addGetRoute(':count', 'getCount')
                ->addGetRoute(':roots', 'getRoots')
                ->addGetRoute(':root', 'getRoot')

                ->addGetRoute('', 'getItems')
                ->addGetRoute('([^/]+)', 'getItem')

                ->addGetRoute('([^/]+)/branch', 'getBranchItems')
                ->addGetRoute('([^/]+)/parent', 'getParent')
                ->addGetRoute('([^/]+)/version/([0-9]*)', 'getVersion')
                ->addGetRoute('([^/]+)/versions', 'getVersions')
                ->addGetRoute('([^/]+)/move/([^/]+)', 'moveItem')

                ->addGetRoute('([^/]+)/parents', 'getParents')
                ->addGetRoute('([^/]+)/children-count', 'getBranchChildrenCount')
                ->addGetRoute(':children-count', 'getBranchChildrenCount')

                ->addPostRoute('', 'addItem')
                ->addPutRoute('([^/]+)', 'updateItem')
                ->addDeleteRoute('([^/]+)', 'removeItem')
                ->addOptionsRoute('', 'getInfo');

            //run parent
            parent::run();
        }
    }


    public function getVersion($pPk, $pId){
        //todo
    }

    public function getVersions($pPk){
        //todo
    }

    /**
     * Count
     *
     * @return integer
     */
    public function getCount(){
        $obj = $this->getObj();

        return $obj->getCount();
    }


    public function moveItem($pPk, $pTargetPk, $pPosition = 'first', $pTargetObjectKey = ''){

        $obj = $this->getObj();
        return $obj->moveItem($pPk, $pTargetPk, $pPosition, $pTargetObjectKey);

    }



    public function getRoots(){

        $obj = $this->getObj();
        return $obj->getRoots();
    }


    public function getRoot($pScope = null){

        $obj = $this->getObj();
        return $obj->getRoot($pScope);
    }


    public function getParent($pPk){

        $obj = $this->getObj();
        return $obj->getParent($pPk);
    }

    public function getParents($pPk){

        $obj = $this->getObj();
        return $obj->getParents($pPk);
    }


    /**
     * Translate the label/title item of $fields.
     *
     * @param $pFields
     */
    public function translateFields(&$pFields){

        if (is_array($pFields)){
            foreach ($pFields as &$field){
                if ($field['label'] && substr($field['label'],0,2) == '[[' && substr($field['label'],-2) == ']]'){
                    $field['label'] = t(substr($field['label'], 2, -2));
                } else if ($field['title'] && substr($field['title'],0,2) == '[[' && substr($field['title'],-2) == ']]')
                    $field['title'] = t(substr($field['title'], 2, -2));
                else if(is_array($field['depends'])){
                    self::translateFields($field['depends']);
                } else if(is_array($field['children'])){
                    self::translateFields($field['children']);
                }
            }
        }

    }

    /**
     * Proxy method for REST DELETE to remove().
     *
     * @param string $pObject
     * @return mixed
     */
    public function removeItem($pObject = null){

        $obj = $this->getObj();
        $pk = \Core\Object::parsePk($obj->getObject(), $pObject);
        return $obj->remove($pk[0]);
    }

    /**
     * Proxy method for REST PUT to update().
     *
     * @param null $pObject
     * @return mixed
     */
    public function updateItem($pObject = null){

        $obj = $this->getObj();

        $pk = \Core\Object::parsePk($obj->getObject(), $pObject);

        return $obj->update($pk[0]);
    }

    /**
     * Proxy method for REST POST to add().
     *
     * @param bool $_multiple
     * @return mixed
     */
    public function addItem($_multiple = null){

        $obj = $this->getObj();

        if ($_multiple){
            return $obj->addMultiple();
        } else {
            return $obj->add();
        }
    }

    /**
     * Proxy method for REST GET to getItem/getItems/getPosition.
     *
     * @param string $pObject
     * @param int $pLimit
     * @param int $pOffset
     * @param int $pGetPosition
     * @return mixed
     */
    public function getItems($pUrl = null, $_ = null, $pLimit = null, $pOffset = null, $pGetPosition = null){

        $obj = $this->getObj();

        if ($pGetPosition !== null){
            return $obj->getPosition($pGetPosition);
        }

        if ($pUrl !== null){
            $pk = \Core\Object::parsePk($obj->getObject(), $pUrl);
            return $obj->getItem($pk[0]);
        } else {
            return $obj->getItems($_, $pLimit, $pOffset);
        }

    }

    public function getRootBranchItems($pScope = null, $pFields = null, $pDepth = 1, $pLimit = null, $pOffset = null){
        $obj = $this->getObj();

        return $obj->getBranchItems(null, $pFields, $pScope, $pDepth, $pLimit, $pOffset);
    }

    public function getBranchItems($pPk = null, $pFields = null, $pScope = null, $pDepth = 1, $pLimit = null, $pOffset = null){
        $obj = $this->getObj();

        $pk = \Core\Object::normalizePkString($obj->getObject(), $pPk);
        return $obj->getBranchItems($pk, $pFields, $pScope, $pDepth, $pLimit, $pOffset);
    }

    public function getBranchChildrenCount($pPk = null, $pScope = null){

        $obj = $this->getObj();

        if ($pPk)
            $pPk = \Core\Object::normalizePkString($obj->getObject(), $pPk);

        return $obj->getBranchChildrenCount($pPk, $pScope);

    }


    public function getItem($pPk, $pFields = null){

        $obj = $this->getObj();

        $primaryKeys = \Core\Object::parsePk($obj->getObject(), $pPk);

        if (count($primaryKeys) == 1)
            return $obj->getItem($primaryKeys[0], $pFields);
        else {
            foreach ($primaryKeys as $primaryKey){
                if ($item = $obj->getItem($primaryKey, $pFields))
                    $items[] = $item;
            }
            return $items;
        }
    }

    /**
     * Returns the class definition/properties of the class behind this REST endpoint.
     *
     * @return mixed
     */
    public function getInfo(){

        $obj = $this->getObj();
        $info = $obj->getInfo();
        $info['_isClassDefinition'] = true;
        return $info;
    }


    /**
     * Returns the class object, depended on the current entryPoint.
     *
     * @return \Admin\ObjectWindow
     * @throws \Exception
     */
    public function getObj() {

        if ($this->obj) return $this->obj;

        $class = $this->entryPoint['class'];

        if (class_exists($class)){
            $obj = new $class($this->entryPoint);
        } else {
            throw new \Exception(tf('Class %s not found', $class));
        }
        return $obj;

    }

    /**
     * @param \Admin\ObjectWindow $pObj
     */
    public function setObj($pObj){
        $this->obj = $pObj;
    }


}