<?php

/*
 ** Part of the SkySQL Manager API.
 * 
 * This file is distributed as part of the SkySQL Cloud Data Suite.  It is free
 * software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * version 2.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: May 2013
 * 
 * The CachedSingleton class provides a general foundation for caching, and is
 * derived from the Aliro class cachedSingleton which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 * The SingletonObjectCache class is used by CachedSingleton, and is derived
 * from the Aliro class aliroSingletonObjectCache which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 */

namespace SkySQL\COMMON\CACHE;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class SingletonObjectCache extends BasicCache {
	protected static $instance = null;
	protected $timeout = _SKYSQL_API_OBJECT_CACHE_TIME_LIMIT;
	protected $sizelimit = _SKYSQL_API_OBJECT_CACHE_SIZE_LIMIT;

	public static function getInstance () {
	    return (self::$instance instanceof self) ? self::$instance : (self::$instance = new self());
	}

	protected function getCachePath ($name) {
		return $this->getBasePath().'singleton/'.$name;
	}

	public function delete () {
		$classes = func_get_args();
		clearstatcache();
		foreach ($classes as $class) {
			$cachepath = $this->getCachePath($class);
			$this->handler->delete($cachepath);
		}
	}
}

abstract class CachedSingleton {

	protected function __clone () { /* Enforce singleton */ }

	protected static function getCachedSingleton ($class) {
		$objectcache = SingletonObjectCache::getInstance();
		$object = $objectcache->retrieve($class);
		if ($object == null OR !($object instanceof $class)) {
			$object = new $class();
			$objectcache->store($object);
		}
		return $object;
	}

	public function clearCache ($immediate=false) {
		$objectcache = SingletonObjectCache::getInstance();
		$classname = get_class($this);
		$objectcache->delete($classname);
		if ($immediate) {
			$instancevar = $classname.'::$instance';
			eval("$instancevar = '$classname';");
		}
	}
	
	public function cacheNow () {
		SingletonObjectCache::getInstance()->store($this);
	}
	
	public static function deleteAll () {
		SingletonObjectCache::deleteAll();
	}
}
