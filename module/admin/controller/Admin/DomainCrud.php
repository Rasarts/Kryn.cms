<?php

namespace Admin\Admin;

class DomainCrud extends \Admin\ObjectCrud
{
    public $fields = array (
  '__General__' => array (
    'label' => 'General',
    'type' => 'tab',
    'children' => array (
      'domain' => array (
        'type' => 'predefined',
        'object' => 'Core\\Domain',
        'field' => 'domain',
      ),
      'master' => array (
        'type' => 'predefined',
        'object' => 'Core\\Domain',
        'field' => 'master',
      ),
    ),
  ),
);

    public $defaultLimit = 15;

    public $add = false;

    public $edit = false;

    public $remove = false;

    public $nestedRootAdd = false;

    public $nestedRootEdit = false;

    public $nestedRootRemove = false;

    public $export = false;

    public $object = 'Core\\Domain';

    public $preview = false;

    public $titleField = 'domain';

    public $workspace = true;

    public $multiLanguage = true;

    public $multiDomain = false;

    public $versioning = false;

}
