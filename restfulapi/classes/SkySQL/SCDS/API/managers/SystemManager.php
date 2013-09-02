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
 * The SystemManager class caches all Systems and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\System;

class SystemManager extends EntityManager {
	protected static $instance = null;
	protected $systems = array();
	
	protected function __construct () {
		foreach (System::getAll() as $system) {
			$this->systems[$system->systemid] = $system;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getByID ($id) {
		return isset($this->systems[$id]) ? $this->systems[$id] : null;
	}
	
	public function getAll () {
		return array_values($this->systems);
	}
	
	public function putSystem ($id) {
		$system = new System($id);
		$system->save();
		// Above method does not return - sends a response and exits
	}
	
	public function deleteSystem ($id) {
		$system = new System($id);
		if (isset($this->systems[$id])) unset($this->systems[$id]);
		SystemPropertyManager::getInstance()->deleteAllProperties($id);
		NodeManager::getInstance()->deleteNode($id);
		$system->delete();
		// Above method does not return - sends a response and exits
	}
}