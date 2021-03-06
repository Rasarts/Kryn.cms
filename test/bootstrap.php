<?php


//setup auto-loader
spl_autoload_register(function ($pClass) {

    $pClass = substr($pClass, strpos($pClass, '\\')+1);
    $fullClazz = 'test/Tests/'.str_replace('\\', '/', $pClass).'.class.php';

    if (file_exists($fullClazz)){
        include $fullClazz;
        return true;
    }
});

include_once(dirname(__FILE__).'/../core/global/exceptions.global.php');

error_reporting(E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR|E_USER_ERROR);
ini_set('display_errors', 1);

if (file_exists(dirname(__FILE__).'/../config.php')){
    die("It seems Kryn.cms is already installed! Please delete the config.php. aborted.\n");
}