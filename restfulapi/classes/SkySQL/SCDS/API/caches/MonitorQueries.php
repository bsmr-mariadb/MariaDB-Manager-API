<?php

/*
 ** Part of the SkySQL Manager API.
 * 
 * This file is distributed as part of MariaDB Enterprise.  It is free
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
 * Date: December 2013
 * 
 * The MonitorQueries class provides a cache of requests for monitor data, so
 * that an If-Modified-Since request can be handled effectively when the same
 * request is repeated.
 * 
 */

namespace SkySQL\SCDS\API\caches;

use SkySQL\COMMON\CACHE\CachedSingleton;

class MonitorQueries extends CachedSingleton {
	protected static $instance = null;
	
	protected $queries = array();

	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	protected function __construct () {
	}
	
	public function newQuery ($monitorid, $systemid, $nodeid, $finish, $count, $interval) {
		$this->queries[$finish][$monitorid][$systemid][$nodeid][$count][$interval] = 1;
		$this->cacheNow();
	}
	
	public function hasBeenDone ($monitorid, $systemid, $nodeid, $finish, $count, $interval) {
		return isset($this->queries[$finish][$monitorid][$systemid][$nodeid][$count][$interval]);
	}
	
	public function newData ($monitorids, $systemid, $nodeid, $timestamp) {
		foreach (array_keys($this->queries) as $finish) {
			if ($finish < (time() - 3600*24)) unset($this->queries[$finish]);
			elseif ($timestamp < $finish) foreach ($monitorids as $monitorid) {
				if (isset($this->queries[$finish][$monitorid][$systemid][$nodeid])) {
					unset($this->queries[$finish][$monitorid][$systemid][$nodeid]);
				}
			}
		}
		$this->cacheNow();
	}
}