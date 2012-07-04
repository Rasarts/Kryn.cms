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
 * Navigation class
 * Layer between Layouts and navigation (pages)
 * @author MArc Schmidt <marc@kryn.org>
 */


class krynNavigation {
    public $navigations;

    public static function getLinks($pRsn, $pWithFolders = false, $pDomain = false, $pWithoutCache = false) {

        if (!is_numeric($pRsn))
            return array();

        if (!$pDomain) {
            $pDomain = kryn::$domain['rsn'];
        }

        if ($pWithoutCache == false) {

            $code = $pDomain;
            $code .= '-' . $pRsn;

            $navigation =& kryn::getCache('navigation-' . $code);
        }

        if ($pWithoutCache == true || !is_array($navigation)) {

            $query = "
            SELECT
                rsn, prsn, domain_rsn, title, url, type, page_title, layout, sort, visible, access_denied,
                access_from, access_to, access_nohidenavi, access_from_groups, properties
            FROM
                %pfx%system_pages
            WHERE
                prsn = $pRsn AND domain_rsn = $pDomain
                AND ( type = 0 OR type = 1 OR type = 2)

                AND (
                    ( type = 2 )
                    OR
                    (
                        type != 2  AND visible = 1
                    )
                )
                AND access_denied != '1'
            ORDER BY sort";
            $links = dbExfetch($query, -1);

            $pages = array();
            foreach ($links as &$page) {

                if ($page['properties']) {
                    $page['properties'] = json_decode($page['properties'], true);
                }

                $page['links'] = self::getLinks($page['rsn'], $pWithFolders, null, true);

                $pages[] = $page;
            }

            if (!$pWithoutCache) {
                kryn::setCache('navigation-' . $code, $pages, 60);
            }

        } else {
            $pages =& $navigation;
        }

        $result = array();
        foreach ($pages as &$page) {

            if (!$pWithFolders && $page['type'] == 2) continue;

            if ($page['access_nohidenavi'] != 1)
                if( kryn::checkPageAccess( $page, false ) )
                    $result[] = $page;
        }

        return $result;
    }

    public static function arrayLevel($pArray, $pLevel) {
        $page = $pArray;
        return $pArray[$pLevel - 2];
    }

    public static function plugin($pOptions) {

        $pTemplate = $pOptions['template'];
        $pWithFolders = ($pOptions['folders'] == 1) ? true : false;

        if (!$pTemplate){
            return t('Navigation: No template selected.');
        }

        if(!$mtime = tModTime($pTemplate)){
            return t('Navigation: Template does not exist:').' '.$pTemplate;
        }

        $navi = false;

        if ($pOptions['id'] + 0 > 0) {
            $navi =& kryn::getPage($pOptions['id'] + 0);

            if (!$pOptions['noCache'] && kryn::$domainProperties['kryn']['cacheNavigations'] !== 0) {
                $cacheKey =
                    'systemNavigations-' . $navi['domain_rsn'] . '_' . $navi['rsn'] . '-' . md5(kryn::$canonical.$mtime);
                $cache =& kryn::getCache($cacheKey);
                if ($cache) return $cache;
            }

            $navi['links'] = self::getLinks($navi['rsn'], $pWithFolders);
        }

        if ($pOptions['level'] > 1) {

            $currentLevel = count(kryn::$breadcrumbs) + 1;

            $page = self::arrayLevel(kryn::$breadcrumbs, $pOptions['level']);

            if ($page['rsn'] > 0)
                $navi =& kryn::getPage($page['rsn']);
            elseif ($pOptions['level'] == $currentLevel + 1)
                $navi = kryn::$page;

            if (!$pOptions['noCache'] && kryn::$domainProperties['kryn']['cacheNavigations'] !== 0) {
                $cacheKey =
                    'systemNavigations-' . $navi['domain_rsn'] . '_' . $navi['rsn'] . '-' . md5(kryn::$canonical.$mtime);
                $cache =& kryn::getCache($cacheKey);
                if ($cache) return $cache;
            }

            $navi['links'] = self::getLinks($navi['rsn'], $pWithFolders, kryn::$domain['rsn']);
        }

        if ($pOptions['level'] == 1) {

            if (!$pOptions['noCache'] && kryn::$domainProperties['kryn']['cacheNavigations'] !== 0) {
                $cacheKey = 'systemNavigations-' . kryn::$page['domain_rsn'] . '_0-' . md5(kryn::$canonical.$mtime);
                $cache =& kryn::getCache($cacheKey);
                if ($cache) return $cache;
            }
            $navi['links'] = self::getLinks(0, $pWithFolders, kryn::$domain['rsn']);
        }

        if ($navi !== false) {

            tAssignRef("navi", $navi);

            if (kryn::$domainProperties['kryn']['cacheNavigations'] !== 0) {
                $res = tFetch($pTemplate);
                kryn::setCache($cacheKey, $res, 10);
                return $res;
            } else {
                return tFetch($pTemplate);
            }
        }

        switch ($pOptions['id']) {

            case 'history':
            case 'hierarchy':
            case 'breadcrumb':
                return tFetch($pTemplate);
        }
    }

}

?>
