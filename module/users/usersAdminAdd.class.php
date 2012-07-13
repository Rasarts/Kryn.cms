<?php

class usersAdminAdd extends windowAdd {

    public $object = 'user';
    public $checkUsage = true; //default on

    public $primary = array('id');
    
    public $tabLayouts = array(
        'General' => '<table width="100%"><tr>
        	<td width="110">
        		 <div style="height: 100px; margin:5px" id="picture"></div>
        	</td>
        	<td>
        		<div id="name"></div>
        		<div style="clear: both"></div>
        		<div id="lastname"></div>
        		<div style="clear: both"></div>
        	</td>
        </tr><tr>
        	<td colspan="2" style="padding: 10px;">
        		<table width="100%">
        			<tbody id="default"></tbody>
        		</table>
        	</td>
        </tr></table>'
    );

    public $tabFields = array(
        'General' => array(
            'picture' => array(
                'onlycontent' => 1,
                'target' => 'picture',
                'type' => 'custom',
                'class' => 'users_field_picture'
            ),
            'first_name' => array(
                'label' => 'First name',
                'target' => 'name',
                'small' => 1,
                'type' => 'text'
            ),
        	'last_name' => array(
                'label' => 'Last name',
                'target' => 'lastname',
                'small' => 1,
                'type' => 'text'
            ),
            'company' => array(
                'label' => 'Company',
                'tableitem' => true,
                'type' => 'text'
            ),
            'street' => array(
                'label' => 'Street',
                'tableitem' => true,
                'type' => 'text'
            ),
            'city' => array(
                'label' => 'City',
                'tableitem' => true,
                'type' => 'text'
            ),
            'zip' => array(
                'label' => 'Zipcode',
                'tableitem' => true,
                'type' => 'number',
                'length' => 10
            ),
            'country' => array(
                'label' => 'Country',
                'tableitem' => true,
                'type' => 'text'
            ),
            'phone' => array(
                'label' => 'Phone',
                'tableitem' => true,
                'type' => 'text'
            ),
            'fax' => array(
                'label' => 'Fax',
                'tableitem' => true,
                'type' => 'text'
            ),
            
        ),
        'Account' => array(
            'username' => array(
                'label' => 'Username',
                'desc' => '(and the administration login)',
                'type' => 'text',
                'empty' => false
            ),
            'passwd' => array(
                'label' => 'Password',
                'type' => 'password',
                'empty' => false,
                'customSave' => 'savePasswd'
            ),
            'email' => array(
                'label' => 'Email',
                'type' => 'text',
                'empty' => false
            ),
            'activate' => array(
                'label' => 'Active account',
                'type' => 'checkbox'
            ),
            'userBg' => array(
               'label' => 'Desktop background image',
                'type' => 'fileChooser',
                'customSave' => 'saveUserBg',
                'customValue' => 'userBgValue',
            ),
            'groups',
            /*'groups' => array(
                'label' => 'Groups',
                'type' => 'textlist',
                'store' => 'admin/backend/stores/groups',
                'relation' => 'n-n',
                'n-n' => array(
                    'right' => 'system_groups',
                    'right_key' => 'id',
                    'right_label' => 'name',
                    'middle' => 'system_groupaccess',
                    'middle_keyright' => 'group_id',
                    'middle_keyleft' => 'user_id',
                    'left_key' => 'id'
                )
            )*/
        )
    );

    public function userBgValue($pPrimary, $pItem){
        $id = $pPrimary['id'];
        $user = dbTableFetch('system_user', 1, "id = $id");
        $settings = unserialize($user['settings']);
        return $settings['userBg'];
    }

    public function saveUserBg(){
        global $user;
        $settings = $user->user['settings'];
        $settings['userBg'] = getArgv('userBg', 1);
        $settings = serialize( $settings );
        dbUpdate( 'system_user', array('id' => $this->last), array('settings' => $settings) );
    }
    
    public function savePasswd( &$pRow ){
        
        $salt = krynAuth::getSalt();
        $passwd = krynAuth::getHashedPassword( getArgv('passwd'), $salt );
        $pRow['passwd'] = $passwd;
        $pRow['passwd_salt'] = $salt;

    }

}

?>
