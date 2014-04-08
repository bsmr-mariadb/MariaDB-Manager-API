<?php

/*
 ** Part of the MariaDB Manager API.
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
 * Copyright 2013 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date: June 2013
 * 
 * The SystemProperties class within the API implements calls to handle
 * system properties.
 * 
 */

namespace SkySQL\Manager\API\controllers;

use SkySQL\Manager\API\managers\ComponentPropertyManager;
use SkySQL\Manager\API\models\Property;
use SkySQL\Manager\API\models\System;
use SkySQL\Manager\API\models\Node;

class ComponentProperties extends SystemNodeCommon {
	protected $defaultResponse = 'componentproperty';
	protected $nodeid = 0;
	protected $component = '';
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Property::checkLegal();
	}
	
	public function setComponentProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'value');
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$value = $this->getParam('PUT', 'value');
		ComponentPropertyManager::getInstance()->setComponentProperty($this->systemid, $this->nodeid, $this->component, $property, $value);
	}
	
	public function deleteComponentProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		ComponentPropertyManager::getInstance()->deleteComponentProperty($this->systemid, $this->nodeid, $this->component, $property);
	}
	
	public function getComponentProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'componentproperty');
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		ComponentPropertyManager::getInstance()->getComponentProperty($this->systemid, $this->nodeid, $this->component, $property);
	}
	
	public function getComponentPropertyUpdated ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Date last updated');
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		ComponentPropertyManager::getInstance()->getComponentPropertyUpdated($this->systemid, $this->nodeid, $this->component, $property);
	}
	
	public function deleteComponentProperties ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$counter = ComponentPropertyManager::getInstance()->deleteAllComponentProperties($this->systemid, $this->nodeid, $this->component);
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse("Delete $this->component property did not match any $this->component property", 404);
	}
	
	public function getComponentProperties ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'componentproperties', true);
		$this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$properties = ComponentPropertyManager::getInstance()->getAllComponentProperties($this->systemid, $this->nodeid, $this->component);
		$this->sendResponse(array("{$this->component}properties" => $properties));
	}
	
	public function deleteComponents ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$counter = ComponentPropertyManager::getInstance()->deleteAllComponents($this->systemid, $this->nodeid);
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse("Delete $this->component property did not match any $this->component property", 404);
	}
	
	public function getComponents ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'components', true);
		$this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$components = ComponentPropertyManager::getInstance()->getAllComponents($this->systemid, $this->nodeid);
		$this->sendResponse(array('components' => $components));
	}
	
	protected function checkNodeIDGetProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$this->component = @$uriparts[5];
		$property = @$uriparts[7];
		if (0 == $this->nodeid) {
			if (0 == $this->systemid) return $property;
			if (System::getByID($this->systemid)) return $property;
		}
		if (Node::getByID($this->systemid, $this->nodeid)) return $property;
		$this->sendErrorResponse(sprintf("No node with System ID '%s' and Node ID '%s'", $this->systemid, $this->nodeid), 404);
	}
}