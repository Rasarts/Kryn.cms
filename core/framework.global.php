<?php

/*
 * This file is part of Kryn.cms.
 *
 * (c) Kryn.labs, MArc Schmidt <marc@Kryn.org>
 *
 * To get the full copyright and license informations, please view the
 * LICENSE file, that was distributed with this source code.
 *
 */


/**
 * Global important functions for working with Kryn.cms
 * @author MArc Schmidt <marc@Kryn.org>
 */


/**
 * klog saves log informations to the log monitor.
 * @package    Kryn
 * @subpackage Log
 */
function klog($pArea, $pMsg) {
    errorHandler($pArea, $pMsg);
}

/**
 * Returns the value in $_REQUEST[$pVal] but with the possibility to escape the
 * value with pEscape.
 *
 * @param string  $pVal
 * @param bool|integer $pEscape 1: Will be escaped with esc(), 2: will delete character beside a-Z0-9.
 *
 * @return string|array
 */
function getArgv($pVal, $pEscape = false) {
    $_REQUEST[$pVal] = str_replace(chr(0), '', $_REQUEST[$pVal]);
    if ($pEscape == false) return $_REQUEST[$pVal];
    return esc($_REQUEST[$pVal], $pEscape);
}


/**
 * This convert the argument in json, send the json to the client and exit the script.
 *
 * @param mixed
 */
function json($pValue) {
    global $client, $adminClient, $argv;

    ob_end_clean();
    ob_clean();

    if (php_sapi_name() !== 'cli' )
        header('Content-Type: text/javascript; charset=utf-8');

    if ($adminClient) $adminClient->syncStore();
    if ($client) $client->syncStore();

    print json_format(json_encode($pValue))."\n";

    exit(0);
}

/**
 * Prints the json ["error": "<errorCode>"] to the client and exit.
 *
 * @param string Error code
 */
function jsonError($pErrorCode){
    json(array('error' => $pErrorCode));
}

/**
 * Return a translated message $pMsg with plural and context ability
 *
 * @param string   $pMsg     message id (msgid)
 * @param string   $pPlural  message id plural (msgid_plural)
 * @param int|bool $pCount   the count for plural
 * @param string   $pContext the message id of the context (msgctxt)
 *
 * @return string
 */
function t($pMsg, $pPlural = '', $pCount = false, $pContext = '') {

    $id = ($pContext == '') ? $pMsg : $pContext . "\004" . $pMsg;

    if (Kryn::$lang[$id]) {
        if (is_array(Kryn::$lang[$id])) {

            if ($pCount){
                $plural = intval(@call_user_func('gettext_plural_fn_' . Kryn::$lang['__lang'], $pCount));
                if ($pCount && Kryn::$lang[$id][$plural])
                    return str_replace('%d', $pCount, Kryn::$lang[$id][$plural]);
                else
                    return (($pCount === null || $pCount === false || $pCount === 1) ? $pMsg : $pPlural);
            } else {
                return Kryn::$lang[$id][0];
            }
        } else {
            return Kryn::$lang[$id];
        }
    } else {
        return ($pCount === null || $pCount === false || $pCount === 1) ? $pMsg : $pPlural;
    }

}

/**
 * Return a translated message $pMsg within a context $pContext
 *
 * @param string $pContext the message id of the context
 * @param string $pMsg     message id
 *
 * @return string
 */
function tc($pContext, $pMsg) {
    return t($pMsg, null, null, $pContext);
}

/**
 * Translate the specified string to the current language if available.
 * If not available it returns the given string.
 *
 * @param  string $pString
 *
 * @return string Translated string
 * @deprecated Use t() instead.
 */
function _l($pMsg) {
    return t($pMsg);
}

?>
