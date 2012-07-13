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


/**
 * Html class
 * @author MArc Schmidt <marc@kryn.org>
 *
 *
 * @events onRenderSlot
 *
 */

class krynHtml {

    public static $docType = 'xhtml 1.0 transitional';


    public static $docTypeMap = array(

        'html 4.01 transitional' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
        'html 4.01 strict' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
        'html 4.01 frameset' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',

        'xhtml 1.0 transitional' =>
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'xhtml 1.0 strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'xhtml 1.0 frameset' =>
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        'xhtml 1.1 dtd' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',

        'html5' => '<!DOCTYPE html>'
    );

    public static function plugin($pMethod) {

        switch ($pMethod) {
            case 'head':
                return self::buildHead();
        }

    }

    public static function getPage(&$pContent = '') {
        global $_start;

        $res = self::$docTypeMap[strtolower(self::$docType)];
        $res .= "\n<head>" . kryn::$htmlHeadTop;
        $res .= self::buildHead(true);
        error_log('Kryn.cms - page generation tooks '.(microtime(true)-$_start).' seconds.');

        $res .= kryn::$htmlHeadEnd . '</head><body>' . kryn::$htmlBodyTop . $pContent . "\n\n" . kryn::$htmlBodyEnd .
                '</body></html>';

        return $res;
    }

    public static function printPage(&$pContent = '') {
        global $_start;

        $html = self::getPage($pContent);

        $html = str_replace('inc/template/', 'media/', $html);

        print $html;
    }

    public static function buildHead($pContinue = false) {
        global $cfg;

        $tagEnd = (strpos(strtolower(krynHtml::$docType), 'xhtml') !== false) ? ' />' : ' >';

        if ($pContinue == false && kryn::$admin == false) {
            return '{*kryn-header*}';
        }
        $page = kryn::$page;
        $domain = kryn::$domain;

        $title = ($page['page_title']) ? $page['page_title'] : $page['title'];

        if (!empty(kryn::$pageTitle))
            $title = kryn::$pageTitle . ' ' . $title;

        $e = explode('::', $domain['title_format']);
        if ($e[0] && $e[1] && $e[0] != 'admin' && $e[0] != 'kryn' && method_exists($e[0], $e[1])) {
            $title = call_user_func(array($e[0], $e[1]));
        } else {

            $title = str_replace(
                array('%title', '%domain'),
                array(
                    $title,
                    $_SERVER['SERVER_NAME']),
                $domain['title_format']
            );

            if (strpos($title, '%path') !== false) {
                $title = str_replace('%path', kryn::getBreadcrumpPath(), $title);
            }
        }

        $html = '<title>' . $title . '</title>' . "\n";


        $html .= "<base href=\"" . kryn::$baseUrl . "\" $tagEnd\n";
        $html .= '<meta name="DC.language" content="' . $domain['lang'] . '" ' . $tagEnd . "\n";

        $html .= '<link rel="canonical" href="' . kryn::$baseUrl . substr(kryn::$url, 1) . '" ' . $tagEnd . "\n";

        $metas = @json_decode($page['meta'], 1);
        if (count($metas) > 0)
            foreach ($metas as $meta)
                if ($meta['value'] != '')
                    $html .= '<meta name="' . str_replace('"', '\"', $meta['name']) . '" content="' .
                             str_replace('"', '\"', $meta['value']) . '" ' . $tagEnd . "\n";

        if (kryn::$cfg['show_banner'] == 1) {
            $html .= '<meta name="generator" content="Kryn.cms" ' . $tagEnd . "\n";
        }


        $myCssFiles = array();
        $myJsFiles = array();


        if (kryn::$kedit == true) {
            $html .= '<script type="text/javascript">var kEditPageId = ' . kryn::$page['id'] . ';</script>' . "\n";
        }


        /*
        * CSS FILES
        *
        */

        foreach (kryn::$cssFiles as $css) {
            $myCssFiles[] = $css;
        }

        # clearstatcache();

        if ($domain['resourcecompression'] != '1') {
            foreach ($myCssFiles as $css) {
                if (strpos($css, "http://") !== false) {
                    $html .= '<link rel="stylesheet" type="text/css" href="' . $css . '" ' . $tagEnd . "\n";
                } else if (file_exists(PATH . PATH_MEDIA . $css) &&
                           $mtime = @filemtime(PATH . PATH_MEDIA . $css)
                ) {
                    $css .= '?c=' . $mtime;
                    $html .=
                        '<link rel="stylesheet" type="text/css" href="' . $cfg['path'] . PATH_MEDIA . $css . '" ' .
                        $tagEnd . "\n";
                }
            }
        } else {
            $cssCode = '';
            foreach ($myCssFiles as $css) {
                if (strpos($css, "http://") !== false) {
                    $html .= '<script type="text/javascript" src="' . $css . '" ></script>' . "\n";
                } else {
                    //local
                    $file = PATH_MEDIA . $css;
                    if (file_exists(PATH . $file) && $mtime = @filemtime(PATH . $file)) {
                        $cssCode .= $file . '_' . $mtime;
                    }
                }
            }

            $cssmd5 = md5($cssCode);

            $cssCachedFile = $cfg['media_cache'] . 'cachedCss_' . $cssmd5 . '.css';


            $cssContent = '';

            if (!file_exists(PATH . $cssCachedFile)) {
                foreach ($myCssFiles as $css) {
                    $file = PATH_MEDIA . $css;
                    if (file_exists($file)) {
                        $cssContent .= "/* $file: */\n\n";
                        $temp = kryn::fileRead($file) . "\n\n\n";
                        //$cssContent .= kryn::fileRead( $file )."\n\n\n"; 

                        //replace relative urls to absolute
                        $mypath = $cfg['path'] . dirname($file);
                        $temp = preg_replace('/url\(/', 'url(' . $mypath . '/', $temp);

                        $cssContent .= $temp;
                    }
                }
                kryn::fileWrite($cssCachedFile, $cssContent);
            }
            $html .=
                '<link rel="stylesheet" type="text/css" href="' . $cfg['path'] . $cssCachedFile . '" ' . $tagEnd . "\n";

            $jsCode = '';
        }


        /*
        * JS FILES
        *
        */

        foreach (kryn::$jsFiles as $js) {
            $myJsFiles[] = $js;
        }

        if ($domain['resourcecompression'] != '1') {
            foreach ($myJsFiles as $js) {
                if (strpos($js, "http://") !== FALSE) {
                    $html .= '<script type="text/javascript" src="' . $js . '" ></script>' . "\n";
                } else {
                    if ($mtime = @filemtime(PATH . PATH_MEDIA . $js) || $js == '/krynJavascriptGlobalPath.js') {
                        $html .= '<script type="text/javascript" src="' . $cfg['path']
                            . ((substr($js,0,1)=='/') ? PATH_MEDIA . $js . '?c=' : substr($js, 1))
                            . $mtime . '" ></script>' . "\n";
                    }
                }
            }
        } else {
            foreach ($myJsFiles as $js) {
                if (strpos($js, "http://") !== false) {
                    $html .= '<script type="text/javascript" src="' . $js . '" ></script>' . "\n";
                } else {
                    //local
                    $file = PATH_MEDIA . $js;
                    if (file_exists(PATH . $file) && $mtime = @filemtime(PATH . $file)) {
                        $jsCode .= $file . '_' . $mtime;
                    }
                }
            }
            $jsmd5 = md5($jsCode);
            $jsCachedFile = $cfg['media_cache'] . 'cachedJs_' . $jsmd5 . '.js';
            $jsContent = '';

            if (!file_exists(PATH . $jsCachedFile)) {

                foreach ($myJsFiles as $js) {
                    $file = PATH_MEDIA . $js;
                    if (file_exists( $file)) {
                        $jsContent .= "/* $file: */\n\n";
                        $jsContent .= kryn::fileRead($file) . "\n\n\n";
                    }
                }
                kryn::fileWrite($jsCachedFile, $jsContent);
            }

            $html .= '<script type="text/javascript" src="' . $cfg['path'] . $jsCachedFile . '" ></script>' . "\n";
        }


        /*
        *
        * HEADER
        */

        foreach (kryn::$header as $head)
            $html .= "$head\n";

        //customized metas
        $metas = json_decode($page['meta'], true);
        if ($page['meta_fromParent'] == 1) {
            $ppage = kryn::getParentPage($page['id']);
            $pmetas = json_decode($ppage['meta'], true);
            $metas = array_merge($ppage, $pmetas);
        }

        return $html;
    }

    /**
     * Returns all contents of the slot of the specified page.
     *
     * @static
     * @param $pId
     * @param bool $pBoxId
     * @param bool $pWithoutCache
     * @return array|string
     */
    public static function &getPageContents($pId, $pBoxId = false, $pWithoutCache = false) {
        global $time, $client, $kcache;

        $pId = $pId + 0;

        $time = time();
        $page = kryn::getPage($pId);


        $page = kryn::checkPageAccess($page, false);
        if (!$page)
            return array();

        $result =& kryn::getCache('pageContents-' . $pId);
        if (false && $result && !$pWithoutCache) return $result;

        $result = array();

        $versionId = $page->getActiveVersionId();

        //todo read acl from table
        $aclCanViewOtherVersions = true;

        if (kryn::$page->getId() == $pId && getArgv('kVersionId') + 0 > 0 && $aclCanViewOtherVersions) {
            $versionId = getArgv('kVersionId') + 0;
        }

        $box = '';
        if ($pBoxId) {
            $box = "AND box_id = $pBoxId";
        }

        if ($versionId > 0) {

            $res = dbExec("
            SELECT c.*
            FROM
                %pfx%system_contents c,
                %pfx%system_page_version v
            WHERE 
                v.id = $versionId
                AND v.page_id = $pId
                AND c.version_id = v.id
                $box
                AND c.hide != 1
                AND ( c.cdate > 0 AND c.cdate IS NOT NULL )
            ORDER BY c.sort");

            while ($content = dbFetch($res)) {
                if (kryn::checkPageAccess($content, false) !== false){
                    $result[$content['box_id']][] = $content;
                }
            }

        } else {

            //compatibility o old kryns <=0.7
            $result = array();
            $res = dbExec("SELECT * FROM %pfx%system_contents
                WHERE page_id = $pId
                $box 
                AND version_id = 1
                AND hide != 1
                ORDER BY sort");
            while ($page = dbFetch($res)) {
                $result[$page['box_id']][] = $page;
            }
        }

        kryn::setCache('pageContents-' . $pId, $result);

        return kryn::getCache('pageContents-' . $pId);
    }

    /**
     *
     * Build the HTML for given page. If pPageId is a deposit, it returns with kryn/blankLayout.tpl as layout, otherwise
     * it returns the layouts with all it contents.
     *
     * @static
     * @param bool $pPageId
     * @param bool $pSlotId
     * @param bool $pProperties
     * @return mixed|string
     */
    public static function renderPageContents($pPageId = false, $pSlotId = false, $pProperties = false) {


        if (kryn::$contents) {
            $oldContents = kryn::$contents;
        }
        kryn::$forceKrynContent = true;

        $start = microtime(true);
        if ($pPageId == kryn::$page->getId()) {
            //endless loop
            die(t('You produced a endless loop. Please check your latest changed pages.'));
        }

        if (!$pPageId) {

            $pPageId = kryn::$page->getId();

        } else if ($pPageId != kryn::$page->getId()) {

            $oldPage = kryn::$page;
            kryn::$page = kryn::getPage($pPageId, true);
            kryn::$nestedLevels[] = kryn::$page;
        }

        $args = array($pPageId, $pSlotId);
        krynEvent::fire('onBeforeRenderPageContents', $args);

        kryn::addCss('css/_pages/' . $pPageId . '.css');
        kryn::addJs('js/_pages/' . $pPageId . '.js');

        kryn::$contents =& self::getPageContents($pPageId);

        if (kryn::$page->getType() == 3) { //deposit
            kryn::$page->setLayout('kryn/blankLayout.tpl');
        }

        if ($pSlotId) {
            $html = self::renderContents(kryn::$contents[$pSlotId], $pProperties);
        } else {
            $html = tFetch(kryn::$page->getLayout());
        }

        if ($oldContents) {
            kryn::$contents = $oldContents;
        }
        if ($oldPage) {
            kryn::$page = $oldPage;
            array_pop(kryn::$nestedLevels);
        }
        kryn::$forceKrynContent = false;


        $arguments = array($pPageId, $pSlotId, &$html);
        krynEvent::fire('onRenderPageContents', $arguments);

        return $html;
    }

    /**
     * Build HTML for given contents.
     *
     * @param array $pContents
     * @param array $pSlotProperties
     *
     * @return string
     * @internal
     */
    public static function renderContents(&$pContents, $pSlotProperties) {
        global $client, $adminClient;

        $access = true;
        $contents = array();

        if (!is_array($pContents)) return '';


        foreach ($pContents as $key => &$content) {

            $access = true;

            if (
                ($content['access_from'] + 0 > 0 && $content['access_from'] > time()) ||
                ($content['access_to'] + 0 > 0 && $content['access_to'] < time())
            ) {
                $access = false;
            }

            if ($content['hide'] === 1) {
                $access = false;
            }

            if ($access && $content['access_from_groups']) {

                $access = false;
                $groups = ',' . $content['access_from_groups'] . ',';

                foreach ($client->user['groups'] as $group) {
                    if (strpos($groups, ',' . $group . ',') !== false) {
                        $access = true;
                        break;
                    }
                }

                if (!$access) {
                    foreach ($adminClient->user['groups'] as $group) {
                        if (strpos($groups, ',' . $group . ',') !== false) {
                            $access = true;
                            break;
                        }
                    }
                    if ($access) {
                        //have acces through the admin login
                    }
                }
            }

            if ($access) {
                $contents[$key] = $content;
            }
        }

        $count = count($contents);
        /*
         * Compatiblity
         */
        tAssign('layoutContentsMax', $count);
        tAssign('layoutContentsIsFirst', true);
        tAssign('layoutContentsIsLast', false);
        tAssign('layoutContentsId', $pSlotProperties['id']);
        tAssign('layoutContentsName', $pSlotProperties['name']);


        $slot = $pSlotProperties;
        $slot['maxItems'] = $count;
        $slot['isFirst'] = true;
        $slot['isLast'] = false;

        $i = 0;

        //$oldContent = $tpl->getTemplateVars('content');
        $argument = array($slot);
        krynEvent::fire('onBeforeRenderSlot', $argument);

        $html = '';

        if ($count > 0) {
            foreach ($contents as &$content) {
                if ($i == $count) {
                    tAssign('layoutContentsIsLast', true);
                    $slot['isLast'] = true;
                }

                if ($i > 0) {
                    tAssign('layoutContentsIsFirst', false);
                    $slot['isFirst'] = false;
                }

                $i++;
                tAssign('layoutContentsIndex', $i);
                $slot['index'] = $i;

                tAssignRef('slot', $slot);
                kryn::$slot = $slot;
                $html .= self::renderContent($content, $slot);

            }
        }

        $argument = array($slot, &$html);
        krynEvent::fire('onRenderSlot', $argument);

        if ($pSlotProperties['assign'] != "") {
            tAssignRef($pSlotProperties['assign'], $html);
            return;
        }

        return $html;

    }

    /**
     * Build HTML for given content.
     *
     * @static
     * @param $pContent
     * @param $pProperties
     * @return mixed|string
     */
    public static function renderContent($pContent, $pProperties) {

        $content =& $pContent;

        tAssignRef('content', $content);
        tAssign('css', ($content['css']) ? $content['css'] : false);


        $argument = array($pContent, $pProperties);
        krynEvent::fire('onRenderContent', $argument);


        switch (strtolower($content['type'])) {
            case 'text':
                //replace all [[ with a workaround, so that multilanguage will not fetch.
                $content['content'] = str_replace('[[', '[<!-- -->[', $content['content']);


                break;
            case 'html':
                $content['content'] = str_replace('[[', '\[[', $content['content']);

                break;
            case 'navigation':

                if ($content['content']){
                    $temp = json_decode($content['content'], 1);
                    $temp['id'] = $temp['entryPoint']+0;
                    unset($temp['entryPoint']);

                    $content['content'] = krynNavigation::get($temp);
                }

                break;
            case 'picture':

                $temp = explode('::', $content['content']);

                if ($temp[0] != '' && $temp[0] != 'none') {
                    $opts = json_decode($temp[0], true);
                    $align = ($opts['align']) ? $opts['align'] : 'left';
                    $alt = ($opts['alt']) ? $opts['alt'] : '';
                    $title = ($opts['title']) ? $opts['title'] : '';

                    $imagelink = $temp[1];

                    if ($opts['width'] && $opts['height']) {
                        $imagelink = resizeImageCached($imagelink, $opts['width'] . 'x' . $opts['height']);
                    } elseif ($pProperties['picturedimension'] && $opts['forcedimension'] != "1") {
                        $imagelink = resizeImageCached($imagelink, $pProperties['picturedimension']);
                    }

                    $link = '';
                    if ($opts['link'] + 0 > 0) {
                        $link = kryn::pageUrl($opts['link']);
                    } else if ($opts['link'] != '') {
                        $link = $opts['link'];
                    }

                    if ($link == '') {
                        $content['content'] =
                            '<div style="text-align: ' . $align . ';"><img src="' . $imagelink . '" alt="' . $alt .
                            '" title="' . $title . '" /></div>';
                    } else {
                        $content['content'] =
                            '<div style="text-align: ' . $align . ';"><a href="' . $link . '" ><img src="' .
                            $imagelink . '" alt="' . $alt . '" title="' . $title . '" /></a></div>';
                    }

                } else {
                    $content['content'] = '<img src="' . $temp[1] . '" />';
                }

                break;
            case 'template':

                if (substr($content['content'], 0, 1) == '/')
                    $content['content'] = substr($content['content'], 1);

                $file = str_replace('..', '', $content['content']);
                if (file_exists(PATH . PATH_MEDIA . $file)) {
                    $content['content'] = tFetch($file);
                }
                break;
            case 'pointer':

                if ($content['content'] + 0 > 0 && $content['content'] + 0 != kryn::$page['id'])
                    $content['content'] = self::renderPageContents($content['content'] + 0, 1, $pProperties);

                break;
            case 'layoutelement':

                $oldContents = kryn::$contents;

                $layoutcontent = json_decode($content['content'], true);
                kryn::$contents = $layoutcontent['contents'];
                $content['content'] = tFetch($layoutcontent['layout']);

                kryn::$contents = $oldContents;

                break;
            case 'plugin':


                $t = explode('::', $content['content']);
                $config = $content['content'];

                $content['content'] = '<div>Plugin not found.</div>';

                if (kryn::$modules[$t[0]]) {

                    $config = substr($config, strlen($t[0]) + 2 + strlen($t[1]) + 2);
                    $config = json_decode($config, true);

                    if (method_exists(kryn::$modules[$t[0]], $t[1]))
                        $content['content'] = kryn::$modules[$t[0]]->$t[1]($config);

                    // if in seachindex mode and plugin is configured unsearchable the kill plugin output
                    if (isset(kryn::$configs[$t[0]]['plugins'][$t[1]][3]) &&
                        kryn::$configs[$t[0]]['plugins'][$t[1]][3] == true
                    )
                        $content['content'] = kryn::$unsearchableBegin . $content['content'] . kryn::$unsearchableEnd;

                }

                break;
            case 'php':
                $temp = ob_get_contents();
                ob_end_clean();
                ob_start();
                eval($content['content']);
                $content['content'] = ob_get_contents();
                ob_end_clean();
                ob_start();
                print $temp;
                break;
        }

        $unsearchable = false;
        if ((!is_array($content['access_from_groups']) && $content['access_from_groups'] != '') ||
            (is_array($content['access_from_groups']) && count($content['access_from_groups']) > 0) ||
            ($content['access_from'] + 0 > 0 && $content['access_from'] > time()) ||
            ($content['access_to'] + 0 > 0 && $content['access_to'] < time()) ||
            $content['unsearchable'] == 1
        ) {
            $unsearchable = true;
        }

        $html = '';
        if ($content['template'] == '' || $content['template'] == '-') {
            if ($unsearchable)
                $html = '<!--unsearchable-begin-->' . $content['content'] . '<!--unsearchable-end-->';
            else
                $html = $content['content'];
        } else {

            tAssign('content', $content);
            $template = $content['template'];
            if ($unsearchable)
                $html = '<!--unsearchable-begin-->' . tFetch($template) . '<!--unsearchable-end-->';
            else
                $html = tFetch($template);
        }


        $argument = array($pContent, $pProperties, &$html);
        krynEvent::fire('onAfterRenderContent', $argument);

        return $html;
    }


}

?>