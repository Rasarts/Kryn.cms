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

namespace Admin;

use \Core\Kryn;

class AdminController {

    /**
     * Checks the access to the administration URLs and redirect to administration login if no access.
     * 
     * @internal
     * @static
     */
    public static function checkAccess($pUrl, $pRoute) {

        return true;

        if (substr($pUrl, 0, 9) == 'admin/ui/'){
            return true;
        }

        if ($pUrl == 'admin/login'){
            return true;
        }

        if (Kryn::checkUrlAccess($pUrl))
            throw new \AccessDeniedException(tf('Access denied.'));
    }

    public function exceptionHandler($pException){
        if (get_class($pException) != 'AccessDeniedException')
            \Core\Utils::exceptionHandler($pException);
    }


    public function run() {

        @header('Expires:');

        $code = Kryn::getRequestedPath();
        $pEntryPoint = Utils::getPathItem($code); //admin entry point

        if (!$pEntryPoint) {
            $pEntryPoint = Utils::getPathItem(substr($code, 6)); //extensions
        }

        if ($pEntryPoint) {
            $epc = new RestEntryPoint('admin');
            $epc->run($pEntryPoint);
        }

        if (Kryn::$modules[getArgv(2)] && getArgv(2) != 'admin'){

            die(Kryn::$modules[getArgv(2)]->admin());

        } else {

            if (Kryn::$config['displayRestErrors']){
                $exceptionHandler = array($this, 'exceptionHandler');
            }

            \RestService\Server::create('admin', $this)

                ->setCheckAccess(array($this, 'checkAccess'))
                ->setExceptionHandler($exceptionHandler)

                ->addGetRoute('', 'showLogin')

                ->addGetRoute('css/style.css', 'loadCss')
                ->addGetRoute('login', 'loginUser', array('username', 'password'))
                ->addGetRoute('logout', 'logoutUser')

                ->addSubController('ui', '\Admin\UIAssets')
                    ->addGetRoute('possibleLangs', 'getPossibleLangs')
                    ->addGetRoute('languagePluralForm', 'getLanguagePluralForm', array('lang'))
                    ->addGetRoute('language', 'getLanguage', array('lang'))
                ->done()

                //admin/backend
                ->addSubController('backend', '\Admin\Backend')
                    ->addGetRoute('js/script.js', 'loadJs')
                    ->addGetRoute('settings', 'getSettings')

                    ->addGetRoute('desktop', 'getDesktop')
                    ->addPostRoute('desktop', 'saveDesktop', array('icons'))

                    ->addGetRoute('widgets', 'getWidgets')
                    ->addPostRoute('widgets', 'saveWidgets', array('widgets'))

                    ->addGetRoute('menus', 'getMenus')
                    ->addGetRoute('custom-js', 'getCustomJs')
                    ->addPostRoute('user-settings', 'saveUserSettings', array('settings'))


                    //admin/backend/object
                    ->addSubController('object', '\Admin\Object\Controller')
                        ->addGetRoute('([a-zA-Z-_]+)/([^/]+)', 'getItem')
                        ->addPostRoute('([a-zA-Z-_]+)/([^/]+)', 'postItem')
                        ->addDeleteRoute('([a-zA-Z-_]+)/([^/]+)', 'deleteItem')
                        ->addPutRoute('([a-zA-Z-_]+)', 'putItem')
                        ->addGetRoute('([a-zA-Z-_]+)', 'getItems')
                    ->done()

                    //admin/backend/object-branch
                    /*->addSubController('object-branch', '\Admin\Object\Controller')
                        ->addGetRoute('([a-zA-Z-_]+)/(.+)', 'getBranch', null, array(
                            'fields', 'order', 'depth'
                        ))
                        ->addGetRoute('([a-zA-Z-_]+)', 'getRootBranches', null, array(
                            'fields', 'order', 'depth'
                        ))
                    ->done()*/

                    //admin/backend/object-count
                    ->addSubController('object-count', '\Admin\Object\Controller')
                        ->addGetRoute('([a-zA-Z-_]+)', 'getCount', null, array('query'))
                    ->done()


                ->done()

                ->addSubController('backend', '\Admin\Object\Controller')
                    ->addGetRoute('objects', 'getItemsByUri', array('uri'))
                ->done()

                //admin/system
                ->addSubController('system', '\Admin\System')

                    ->addGetRoute('', 'getSystemInformation')

                    //admin/system/module/manager
                    ->addSubController('module/manager', '\Admin\Module\Manager')
                        ->addGetRoute('install/pre', 'installPre', array('name'))
                        ->addGetRoute('install/extract', 'installExtract', array('name'))
                        ->addGetRoute('install/database', 'installDatabase', array('name'))
                        ->addGetRoute('install/post', 'installPost', array('name'))
                        ->addGetRoute('check-updates', 'check4updates')
                        ->addGetRoute('local', 'getLocal')
                        ->addGetRoute('installed', 'getInstalled')
                    ->done()


                    //admin/system/orm
                    ->addSubController('orm', '\Admin\ORM')
                        ->addGetRoute('environment', 'buildEnvironment')
                        ->addGetRoute('models', 'writeModels')
                        ->addGetRoute('update', 'updateScheme')
                        ->addGetRoute('check', 'checkScheme')
                    ->done()

                    //admin/system/module/editor
                    ->addSubController('module/editor', '\Admin\Module\Editor')
                        ->addGetRoute('config', 'getConfig', array('name'))

                        ->addGetRoute('windows', 'getWindows', array('name'))

                        ->addGetRoute('objects', 'getObjects', array('name'))
                        ->addPostRoute('objects', 'saveObjects', array('name'))

                        ->addGetRoute('plugins', 'getPlugins', array('name'))
                        ->addPostRoute('plugins', 'savePlugins', array('name'))

                        //
                        ->addPostRoute('new-window', 'newWindow')


                        ->addPostRoute('model/from-object', 'setModelFromObject', array('name', 'object'))

                        ->addPostRoute('model', 'saveModel', array('name', 'model'))
                        ->addGetRoute('model', 'getModel', array('name'))

                        ->addPostRoute('general', 'saveGeneral', array('name'))
                        ->addPostRoute('entryPoints', 'saveEntryPoints', array('name', 'entryPoints'))


                    ->done()

                ->done()

                //->addSubController('file', '\Admin\File')

            ->run();

            exit;

        }
    }

    public function loginUser($pUsername, $pPassword){
        return Kryn::getAdminClient()->login($pUsername, $pPassword);
    }

    public function logoutUser(){
        Kryn::getClient()->logout();
        return true;
    }


    public function loadCss() {
        return Utils::loadCss();
    }

    public static function showLogin() {

        $language = Kryn::$adminClient->getSession()->getLanguage();
        if (!$language) $language = 'en';

        if (getArgv('setLang') != '')
            $language = getArgv('setLang', 2);

        tAssign('adminLanguage', $language);

        print tFetch('admin/index.tpl');
        exit;
    }

}