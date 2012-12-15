<?php

namespace Tests;


class Manager {

    public static $config;

    public static $configFile = 'default.mysql.json';

    /**
     * @param null $pConfigFile Default is config/default.mysql.json
     */
    public static function freshInstallation($pConfigFile = null){

        $configFile = $pConfigFile ?: 'config/'.self::$configFile;

        self::$config = json_decode(file_get_contents($configFile), true);

        if ($_ENV['DB_NAME'])
            self::$config['database']['name'] = $_ENV['DB_NAME'];

        if ($_ENV['DB_USER'])
            self::$config['database']['user'] = $_ENV['DB_USER'];

        if ($_ENV['DB_PW'])
            self::$config['database']['password'] = $_ENV['DB_PW'];

        if ($_ENV['DB_SERVER'])
            self::$config['database']['server'] = $_ENV['DB_SERVER'];

        if ($_ENV['DB_TYPE'])
            self::$config['database']['type'] = $_ENV['DB_TYPE'];


        $cfg = self::$config['config'];
        $cfg['displayErrors'] = false;

        if (file_exists('../config.php'))
            self::uninstall();

        self::install($cfg);

    }

    public static function get($pPath = '/', $pPostData = null){

        $content = wget('http://'.self::$config['domain'].$pPath, null, $pPostData);

        var_dump('http://'.self::$config['domain'].$pPath);
        var_dump($content);

        return $content;
    }

    public static function uninstall(){

        $origin = getcwd();

        $trace = debug_backtrace();
        foreach ($trace as $t){
            $string[] = basename($t['file']).':'.$t['line'];
        }

        if (file_exists('../config.php')){
            $config = include('../config.php');
        } else {
            die("Kryn.cms not installed. =>".implode(', ', $string)." \n");
        }

        $config['displayBeautyErrors'] = 0; //0 otherwise the exceptionHandler of kryn is used, what breaks the PHPUnit.
        $cfg = $config;

        $doit = true;

        require('../core/bootstrap.php');
        chdir(PATH);

        require('core/bootstrap.startup.php');
        \Core\Kryn::loadConfigs();

        $manager = new \Admin\Module\Manager;

            foreach ($config['activeModules'] as $module){
                $manager->uninstall($module, false, true);
            }

            $manager->uninstall('users', false, true);
            $manager->uninstall('admin', false, true);
            $manager->uninstall('core', false, true);

            \Core\PropelHelper::updateSchema();



        \Core\SystemFile::remove('config.php');

        self::cleanup();

        \Core\PropelHelper::cleanup();

        //load all configs
        \Core\Kryn::loadConfigs();

        \Admin\Utils::clearCache();

        chdir($origin);
    }


    public static function install($pConfig){

        $origin = getcwd();

        $cfg = $pConfig;
        $cfg['displayBeautyErrors'] = 0; //0 otherwise the exceptionHandler of kryn is used, what breaks the PHPUnit.

        if (!file_put_contents('../config.php', "<?php\n return ".var_export($cfg, true).'; '))
            throw new \FileNotWritableException('Can not install Kryn.cms. config.php not writeable.');

        $dir = 'media/cache';

        require('../core/bootstrap.php');

        \Core\TempFile::createFolder('./');
        \Core\MediaFile::createFolder('cache/');

        require('../core/bootstrap.startup.php');
        \Core\Kryn::loadConfigs();
        @ini_set('display_errors', 1);

        chdir(PATH);

        $manager = new \Admin\Module\Manager;

        $_GET['domain'] = self::$config['domain'];

        \Core\TempFile::remove('propel');

        if (!\Propel::isInit())
            \Propel::init(\Core\PropelHelper::getConfig());
        else
            \Propel::configure(\Core\PropelHelper::getConfig());

        try {

            foreach ($pConfig['activeModules'] as $module)
                $manager->install($module, true);

            \Core\PropelHelper::updateSchema();
            \Core\PropelHelper::generateClasses();

            $doit = true;
            include('core/bootstrap.startup.php');
        } catch (\Exception $ex){
            die($ex);
        }

        \Core\PropelHelper::cleanup();

        //load all configs
        \Core\Kryn::loadConfigs();

        \Admin\Utils::clearCache();

        chdir($origin);
    }

    public static function bootupCore(){

        if (file_exists('../config.php')){
            $cfg = include('../config.php');
        } else throw new \Exception('Kryn.cms not installed.');

        $cfg = include('../config.php');
        $cfg['displayErrors'] = false;

        //todo, make it configable
        $_SERVER['PATH_INFO'] = '/';
        $_SERVER['SERVER_NAME'] = 'localhost';

        require('../core/bootstrap.php');
        require('../core/bootstrap.startup.php');

        ini_set('display_errors', 1);

    }

    public static function cleanup(){

        //load all configs
        \Core\Kryn::loadConfigs();

        \Core\Object::cleanup();

        \Admin\Utils::clearCache();

        \Core\Kryn::cleanup();

    }

}