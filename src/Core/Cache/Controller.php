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

namespace Core\Cache;

use Core\Config\Cache;

/**
 * Cache controller
 */
class Controller
{
    /**
     * Contains the current class instance.
     *
     * @type Object
     */
    public $instance;

    /**
     * All gets/sets will be cached in this array for faster access
     * during multiple get() calls on the same key.
     *
     * @var array
     */
    public $cache = array();

    /**
     * This activates the invalidate() mechanism
     *
     * @type bool
     *
     * If activated, each time get() is called, the function searched
     * for parents based on a exploded string by '/'. If a parent is
     * found is a invalidated cache, the call is ignored and false will be returned.
     * Example: call get('workspace/tables/tableA')
     *          => checks 'workspace/tables' for invalidating (getInvalidate('workspace/tables'))
     *          => if 'workspace/tables' was flagged as invalidate (invalidate('workspace/tables')), return false
     *          => checks 'workspace' for invalidating (getInvalidate('workspace'))
     *          => if 'workspace' was flagged as invalidate (invalidate('workspace')), return false
     * So you can invalidate multiple keys with just one call.
     */
    public $withInvalidationChecks = true;

    /**
     * The class name.
     *
     * @var string
     */
    public $class;

    /**
     * Constructor.
     *
     * @param Cache $cacheConfig               The class of the cache service.
     * @param bool   $pWithInvalidationChecks  Activates the invalidating mechanism
     *
     * @throws \Exception
     */
    public function __construct(Cache $cacheConfig, $pWithInvalidationChecks = true)
    {
        $this->withInvalidationChecks = $pWithInvalidationChecks;
        $this->class = $cacheConfig->getClass();

        if (class_exists($this->class)) {
            $class = $this->class;
            $this->instance = new $class($cacheConfig->getOptions()->toArray());
        } else {
            throw new \Exception(tf('The class `%s` does not exist.', $this->class));
        }

    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Detects the fastest available cache on current machine.
     *
     * @return Cache
     */
    public static function getFastestCacheClass()
    {
        $class = '\Core\Cache\\';

        if (function_exists('apc_store')) {
            $class .= 'Apc';
        } else if (function_exists('xcache_set')) {
            $class .= 'XCache';
        } else if (function_exists('wincache_ucache_get')) {
            $class .= 'WinCache';
        } else {
            $class .= 'Files';
        }

        $cacheConfig = new Cache();
        $cacheConfig->setClass($class);
        return $cacheConfig;
    }

    /**
     * Returns data of the specified cache-key.
     *
     * @param string $pKey
     * @param bool   $pWithoutValidationCheck
     *
     * @return ref to data
     */
    public function &get($pKey, $pWithoutValidationCheck = false)
    {

        if (!isset($this->cache[$pKey])) {
            $time = microtime(true);
            $this->cache[$pKey] = $this->instance->get($pKey);
            \Core\Utils::$latency['cache'][] = microtime(true) - $time;
        }

        if (!$this->cache[$pKey]) {
            $rv = null;
            return $rv;
        }

        if ($this->withInvalidationChecks && !$pWithoutValidationCheck) {

            if ($pWithoutValidationCheck == true) {
                if (!$this->cache[$pKey]['value'] || !$this->cache[$pKey]['time']
                    || $this->cache[$pKey]['timeout'] < microtime(true)
                ) {
                    return null;
                }

                return $this->cache[$pKey]['value'];
            }

            //valid cache
            //search if a parent has been flagged as invalid
            if (strpos($pKey, '/') !== false) {

                $parents = explode('/', $pKey);
                $code = '';
                if (is_array($parents)) {
                    foreach ($parents as $parent) {
                        $code .= $parent;
                        $invalidateTime = $this->getInvalidate($code);
                        if ($invalidateTime && $invalidateTime > $this->cache[$pKey]['time']) {
                            return null;
                        }
                        $code .= '/';
                    }
                }
            }
        }

        if ($this->withInvalidationChecks && !$pWithoutValidationCheck) {
            if (is_array($this->cache[$pKey])) {
                return $this->cache[$pKey]['value'];
            } else {
                return null;
            }
        } else {
            return $this->cache[$pKey];
        }

    }

    /**
     * Returns the invalidation time.
     *
     * @param  string $pKey
     *
     * @return string
     */
    public function getInvalidate($pKey)
    {
        return $this->get('invalidate-' . $pKey, true);
    }

    /**
     * Marks a code as invalidate until $pTime.
     *
     * @param string   $pKey
     * @param bool|int $pTime
     */
    public function invalidate($pKey, $pTime = null)
    {
        $this->cache['invalidate-' . $pKey] = $pTime;

        $time = microtime(true);
        $result = $this->instance->set('invalidate-' . $pKey, $pTime, 99999999, true);
        \Core\Utils::$latency['cache'][] = microtime(true) - $time;
        return $result;
    }

    /**
     * Stores data to the specified cache-key.
     *
     * If you want to save php class objects, you should serialize it before.
     *
     * @param string $pKey
     * @param mixed  $pValue
     * @param int    $pLifeTime               In seconds. Default is one hour
     * @param bool   $pWithoutValidationData
     *
     * @return boolean
     */
    public function set($pKey, $pValue, $pLifeTime = 3600, $pWithoutValidationData = false)
    {
        if (!$pKey) {
            return false;
        }

        if (!$pLifeTime) {
            $pLifeTime = 3600;
        }

        if ($this->withInvalidationChecks && !$pWithoutValidationData) {
            $pValue = array(
                'timeout' => microtime(true) + $pLifeTime,
                'time' => microtime(true),
                'value' => $pValue
            );
        }

        $this->cache[$pKey] = $pValue;

        $time = microtime(true);
        $result = $this->instance->set($pKey, $pValue, $pLifeTime);
        \Core\Utils::$latency['cache'][] = microtime(true) - $time;
        return $result;
    }

    /**
     * Deletes the cache for specified cache-key.
     *
     * @param  string $pKey
     *
     * @return bool
     */
    public function delete($pKey)
    {
        unset($this->cache[$pKey]);

        return $this->instance->delete($pKey);
    }
}
