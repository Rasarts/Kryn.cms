<?php

class usersAdminList extends adminWindowList {

    public $object = 'user';

    public $itemsPerPage = 20;
    public $orderBy = 'username';

    public $filter = array('last_name', 'first_name', 'username', 'email');

    public $add = true;
    public $edit = true;
    public $remove = true;

    public $primary = array('id');
    
    public $itemLayout = '<b id="username"></b> (<span id="first_name"></span> <span id="last_name"></span>)<br/><span style="color: silver;" id="groups_name"></span>';

    public $columns = array(
        'LastName' => array(
            'label' => 'Last name',
            'type' => 'text'
        ),
        'FirstName' => array(
            'label' => 'First name',
            'type' => 'text'
        ),
        'Username' => array(
            'label' => 'Username',
            'type' => 'text',
            'width' => 100
        ),
        'Email' => array(
            'label' => 'Email',
            'width' => '140',
            'type' => 'text'
        ),
        'Activate' => array(
            'label' => 'Active',
            'width' => '35',
            'type' => 'imagemap',
            'imageMap' => array(
                'null' => 'admin/images/icons/cancel.png',
                '0' => 'admin/images/icons/cancel.png',
                '1' => 'admin/images/icons/accept.png'
            )
        ),
        'Groups',
    );

    function filterSql(){
    	$filter = parent::filterSql();
    	
    	$filter .= " AND %pfx%".$this->table.".id > 0";
    	
    	return $filter;    	
    }
    
    function deleteItem(){

        parent::deleteItem();

        $sql = "DELETE FROM `%pfx%system_groupaccess` WHERE `user_id` = ".($_POST['item']['id']+0);
        dbExec( $sql );
        return true;
    }

    function acl( $pItem ){
        $res = parent::acl( $pItem );

        if( $pItem['id'] == '1' )
            $res['remove'] = false;

        if( $pItem['id'] == '0' ){
            $res['remove'] = false;
            $res['edit'] = false;
        }

        return $res;
    }

}

?>
