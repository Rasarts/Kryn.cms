<?php

namespace Admin;

class Object extends  \RestServerController {

    /**
     * @param $pObject
     * @return Object
     */
    public function getQueryClass($pObject){

        $clazz = '\\'.ucfirst($pObject).'Query';
        if (!class_exists($clazz)){
            $this->sendBadRequest('object_not_exist', tf('The object %s does not exist.', $clazz));
        }

        return $clazz::create();
    }



    public function getItems($pObject, $pFields = null, $pLimit = null, $pOffset = null, $pOrder = null){


        $options = array(
            'fields' => $pFields,
            'limit'  => $pLimit,
            'offset' => $pOffset,
            'order'  => $pOrder
        );

        return \Core\Object::getList($pObject, null, $options);

        $query = $this->getQueryClass($pObject);

        if ($pFields){
            $fields = trim(str_replace(' ', '', explode(',', $pFields)));
            $query->select($fields);
        }


        $items = \UserQuery::create()
            ->leftJoinUserGroup()
            ->addJoin(\UserGroupPeer::GROUP_ID, \GroupPeer::ID, \Criteria::LEFT_JOIN)

            //mysql
            //->withColumn('group_concat('.\GroupPeer::NAME.')', 'groups')

            //postgers
            ->withColumn('string_agg('.\GroupPeer::NAME.', \',\')', 'groups')

            ->select(array('Id', 'Username', 'groups'))
            ->groupBy('Id')
            ->findOne();
//        select(
//            array('Id', 'Username', 'group_concat(UserGroup.UserId) AS bla')
//        )

//
//        $query->with('Group');
//        //$query->where('UserGroup.UserId = Id');
//        $query->groupById();
//
//        $items = $query->find();

        $result['items'] = $items->toArray();

        return $result;

        var_dump($pObject); exit;
    }
}