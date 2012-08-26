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


class adminPlugins {

    function init() {
        switch (getArgv(4)) {
            case 'get':
                return self::get(getArgv('module'), getArgv('plugin'));
            case 'icon':
                modules::pluginIcon(getArgv('plugin'));
            case 'preview':
                return self::preview($_POST['content']);
        }
        json("adminPlugins::init::invalid-param");
    }

    public static function preview($pContent) {
        global $user;

        $temp = explode('::', $pContent);

        if (!kryn::$configs[$temp[0]])
            json('');

        $config = kryn::$configs[$temp[0]];
        $plugins = $config['plugins'];

        $lang = $user->user['settings']['adminLanguage'] ? $user->user['settings']['adminLanguage'] : 'en';
        $title = $config['title'][$lang] ? $config['title'][$lang] : $config['title']['en'];

        $opts = json_decode($temp[2], true);

        $res = '<b>' . $title . '</b><br />';
        $res .= _l($plugins[$temp[1]][0]) . '<div style="padding-left: 10px; color: #666;">';

        foreach ($plugins[$temp[1]][1] as $key => $field) {
            $value = $opts[$key];

            if ($field['type'] == 'headline') continue;

            if (is_array($opts[$key]))
                $value = implode(", ", $opts[$key]);
            $res .= _l($field['label']) . ': ' . $value . '<br />';

            if (is_array($field['depends']) && count($field['depends']) > 0) {
                foreach ($field['depends'] as $subkey => $subfield) {
                    $subvalue = $opts[$subkey];
                    if (is_array($opts[$subkey])) {
                        $subvalue = implode(", ", $opts[$subkey]);
                    }
                    if ($value == $subfield['needValue'])
                        $res .= "  " . _l($subfield['label']) . ': ' . $subvalue . '<br />';
                }
            }
        }
        $res .= '</div>';

        json($res);
    }

    public static function get($pModule, $pPlugin) {


        $config = kryn::$configs[$pModule];
        $plugin = $config['plugins'][$pPlugin];

        $moduleObj = kryn::$modules[$pModule];
        self::preparePlugin($plugin[1], $moduleObj);

        json($plugin);
    }


    public static function preparePlugin(&$pFields, $pModuleObj) {
        if (count($pFields) > 0) {
            foreach ($pFields as &$field) {

                if ($field['type'] == 'select' && $field['tableItems'] == '') {

                    if ($field['items']) {

                        $field['tableItems'] = $field['items'];

                    } else if (!empty($field['eval'])) {
                        $field['tableItems'] = eval($field['eval']);

                    } elseif (isset($field['table']) && isset($field['table_label']) && isset($field['table_id'])) {
                        $sql = "SELECT " . $field['table_label'] . ", " . $field['table_id'] . " FROM %pfx%" .
                               $field['table'];
                        $field['tableItems'] = dbExfetch($sql, DB_FETCH_ALL);

                    } elseif (!empty($field['sql'])) {
                        $field['tableItems'] = dbExfetch($field['sql'], DB_FETCH_ALL);

                    } else if ($field['method']) {
                        $nam = $field['method'];
                        if (method_exists($pModuleObj, $nam)) {
                            $field['tableItems'] = $pModuleObj->$nam($field);
                        }
                    }

                    if ($field['tableItems'] && !isset($field['table_label'])) {

                        if (is_array($field['tableItems'])) {
                            $newTableItems = array();
                            foreach ($field['tableItems'] as $id => $item) {
                                $newTableItems[] = array('id' => $id, 'label' => $item);
                            }
                            $field['table_label'] = 'label';
                            $field['table_id'] = 'id';
                            $field['tableItems'] = $newTableItems;
                        }

                    }
                }
                if ($field['type'] == 'files') {
                    $files = kryn::readFolder($field['directory'], $field['withExtension']);
                    if (count($files) > 0) {
                        foreach ($files as $file) {
                            $field['tableItems'][] = array('id' => $file, 'label' => $file);
                        }
                    } else {
                        $field['tableItems'] = array();
                    }
                    $field['table_key'] = 'id';
                    $field['table_label'] = 'label';
                    $field['type'] = 'select';
                }

                if ($field['depends']) {
                    $depends =& $field['depends'];
                    self::preparePlugin($depends, $pModuleObj);
                }
            }
        }
    }

}

?>
