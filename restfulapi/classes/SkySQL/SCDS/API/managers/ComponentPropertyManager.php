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
 * The Component PropertyManager class looks after cached component properties,
 * where a component is something that is installed on a node.
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\COMMON\AdminDatabase;
use stdClass;

class ComponentPropertyManager extends PropertyManager {
	
	protected $name = 'component';
	
	protected $updateSQL = "UPDATE ComponentProperties SET Value = :value, Updated = datetime('now') WHERE ComponentID = :key AND Property = :property";
	protected $insertSQL = "INSERT INTO ComponentProperties (ComponentID, Property, Value, Updated) VALUES (:key, :property, :value, datetime('now'))";
	protected $deleteSQL = 'DELETE FROM ComponentProperties WHERE ComponentID = :key AND Property = :property';
	protected $deleteAllSQL = 'DELETE FROM ComponentProperties WHERE ComponentID = :key';
	protected $selectSQL = 'SELECT Value FROM ComponentProperties WHERE ComponentID = :key AND Property = :property';
	protected $selectAllSQL = 'SELECT ComponentID AS key, Property AS property, Value AS value, Updated AS updated FROM ComponentProperties';
	
	protected static $instance = null;
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	private function makeKey ($systemid, $nodeid, $name=null) {
		return empty($name) ? "$systemid|$nodeid|%" : "$systemid|$nodeid|$name";
	}
	
	public function setComponentProperty ($systemid, $nodeid, $name, $property, $value) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::setProperty($key, $property, $value);
	}
	
	public function deleteComponentProperty ($systemid, $nodeid, $name, $property) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::deleteProperty($key, $property);
	}
	
	public function getComponentProperty ($systemid, $nodeid, $name, $property) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::getProperty($key, $property);
	}
	
	public function getComponentPropertyUpdated ($systemid, $nodeid, $name, $property) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::getPropertyUpdated($key, $property);
	}
	
	public function getAllComponentProperties ($systemid, $nodeid, $name) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		return parent::getAllProperties($key);
	}
	
	public function deleteAllComponentProperties ($systemid, $nodeid, $name) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		$counter = parent::deleteAllProperties($key);
		if ($counter) $this->clearCache(true);
		return $counter;
	}
	
	public function getAllComponents ($systemid, $nodeid) {
		$key = $this->makeKey($systemid, $nodeid);
		$select = AdminDatabase::getInstance()->prepare('SELECT * FROM ComponentProperties WHERE ComponentID LIKE :key ORDER BY ComponentId, Property');
		$select->execute(array('key' => $key));
		foreach ($select->fetchAll() as $property) {
			$keyparts = explode('|', $property->ComponentID, 3);
			if (3 == count($keyparts)) {
				$name = $keyparts[2];
				$components[$name][$property->Property] = $property->Value;
			}
		}
		return isset($components) ? $components : new stdClass();
	}
	
	public function deleteAllComponents ($systemid, $nodeid) {
		return $this->deleteByKey($this->makeKey($systemid, $nodeid));
	}
	
	public function deleteAllComponentsForSystem ($systemid) {
		return $this->deleteByKey("$systemid|%");
	}
	
	protected function deleteByKey ($key) {
		$delete = AdminDatabase::getInstance()->prepare('DELETE FROM ComponentProperties WHERE ComponentID LIKE :key');
		$delete->execute(array('key' => $key));
		$counter = $delete->rowCount();
		if ($counter) $this->clearCache(true);
		return $counter;
	}
}
