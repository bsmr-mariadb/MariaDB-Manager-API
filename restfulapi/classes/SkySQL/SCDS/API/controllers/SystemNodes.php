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
 * Date: February 2013
 * 
 * Implements:
 * Obtain node information with GET request to /system/{id}/node/{id}
 * Obtain a monitor with GET request to /system/{id}/node/{id}/monitor/{id}
 */

namespace SkySQL\SCDS\API\controllers;

use LogicException;
use DomainException;
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\models\NodeNullState;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeStateManager;
use SkySQL\SCDS\API\caches\CachedProvisionedNodes;

class SystemNodes extends SystemNodeCommon {
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}
	
	public function nodeStates ($uriparts) {
		$manager = NodeStateManager::getInstance();
		if (empty($uriparts[1])) {
			$this->sendResponse(array('nodestates' => $this->filterResults($manager->getAll())));
		}
		else {
			$this->sendResponse(array('nodestates' => $this->filterResults($manager->getAllForType(urldecode($uriparts[1])))));
		}
	}
	
	public function getSystemAllNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse("No system with ID $this->systemid", 404);
		$nodes = NodeManager::getInstance()->getAllForSystem($this->systemid, $this->getParam('GET', 'state'));
		foreach ($nodes as $node) $this->extraNodeData($node);
		$this->sendResponse(array('nodes' => $this->filterResults($nodes)));
	}
	
	public function getProvisionedNodes () {
		$pnodescache = CachedProvisionedNodes::getInstance();
		$nodes = $pnodescache->getIfChangedSince($this->ifmodifiedsince);
		if ($nodes) $this->sendResponse(array('provisionednodes' => $nodes));
		header (HTTP_PROTOCOL.' 304 Not Modified');
		exit;
	}
	
	public function getSystemNode ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$node = NodeManager::getInstance()->getByID($this->systemid, $this->nodeid);
		if ($node) {
			if ($this->ifmodifiedsince < strtotime($node->updated)) $this->modified = true;
			$this->extraNodeData($node);
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
			$this->sendResponse(array('node' => $this->filterSingleResult($node)));
		}
		else $this->sendErrorResponse("No matching node for system ID $this->systemid and node ID $this->nodeid", 404);
	}
	
	public function getSystemNodeProcesses ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$this->sendResponse(array('process' => $this->filterResults($this->getNodeProcesses($this->nodeid))));
	}
	
	public function getProcessPlan ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$processid = (int) $uriparts[5];
		exit;
	}
	
	public function killSystemNodeProcess ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$processid = (int) $uriparts[5];
		if ($processid) $this->targetDatabaseQuery("KILL QUERY $processid", $this->nodeid);
		exit;
	}
	
	protected function extraNodeData (&$node) {
		$node->commands = ($this->isFilterWord('commands') AND $node->state) ? $node->getCommands() : null;
		$node->monitorlatest = $this->getMonitorData($node->nodeid);
		$node->task = Task::latestForNode($node);
	}

	public function createSystemNode ($uriparts) {
		Node::checkLegal();
		$this->db->beginImmediateTransaction();
		$this->systemid = (int) $uriparts[1];
		if ($this->validateSystem()) NodeManager::getInstance()->createNode($this->systemid);
		else $this->sendErrorResponse('Create node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	public function updateSystemNode ($uriparts) {
		$this->db->beginImmediateTransaction();
		Node::checkLegal('stateid');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) @$uriparts[3];
		$old = NodeManager::getInstance()->getByID($this->systemid, $this->nodeid);
		if (!$old) $this->sendErrorResponse(sprintf("Update node, no node with system ID '%s' and node ID '%s'", $this->systemid, $this->nodeid), 400);
		$newstate = $this->getParam($this->requestmethod, 'state');
		if ($newstate AND $newstate != $old->state AND NodeStateManager::getInstance()->isProvisioningState($old->state)) {
			class_exists ('SkySQL\\SCDS\\API\\models\\NodeProvisioningStates');
			try {
				$stateobj = NodeNullState::create($old->state);
				$stateobj->make($newstate);
			}
			catch (LogicException $l) {
				$this->sendErrorResponse($l->getMessage(), 500);
			}
			catch (DomainException $d) {
				$this->sendErrorResponse($d->getMessage(), 409);
			}
		}
		NodeManager::getInstance()->updateNode($this->systemid, $this->nodeid);
	}
	
	public function deleteSystemNode ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		if ($this->validateSystem()) {
			NodeManager::getInstance()->deleteNode($this->systemid, $this->nodeid);
			ComponentPropertyManager::getInstance()->deleteAllComponents($this->systemid, $this->nodeid);
		}
		else $this->sendErrorResponse('Delete node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	protected function validateSystem () {
		return SystemManager::getInstance()->getByID($this->systemid) ? true : false;
	}
	
	protected function validateNode () {
		return NodeManager::getInstance()->getByID($this->systemid, $this->nodeid) ? true : false;
	}
}
