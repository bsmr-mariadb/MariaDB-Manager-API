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
 * Date: May 2013
 * 
 * The NodeManager class caches all Nodes and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use LogicException;
use DomainException;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\models\NodeNullState;
use SkySQL\SCDS\API\managers\NodeStateManager;

class NodeManager extends EntityManager {
	protected static $instance = null;
	protected $nodes = array();
	protected $nodeips = array();
	
	protected function __construct () {
		foreach (Node::getAll() as $node) {
			$this->nodes[$node->systemid][$node->nodeid] = $node;
			$this->nodeips[] = $node->privateip;
			$ipcounts[$node->privateip][] = $node->nodeid;
		}
		foreach ((array) @$ipcounts as $privateip=>$nodeids) if (1 < count($nodeids)) {
			if ($privateip) {
				$idlist = implode(',', $nodeids);
				Request::getInstance()->warnings[] = sprintf("Nodes with IDs '%s' have the same private IP address, '%s'", $idlist, $privateip);
			}
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getByID ($system, $id) {
		return isset($this->nodes[$system][$id]) ? $this->nodes[$system][$id] : null;
	}
	
	public function getDescription ($systemid, $nodeid) {
		$node = $this->getByID($systemid, $nodeid);
		if ($node) {
			$system = SystemManager::getInstance()->getByID($systemid);
			if ($system) return sprintf("node called '%s' in system called '%s' (S%d, N%d)", $node->name, $system->name, $systemid, $nodeid);
			else {
				$systemname = 'unknown';
				return sprintf("node called '%s' in system called '%s' (S%d, N%d)", $node->name, $systemname, $systemid, $nodeid);
			}
		}
		else return "unknown node";
	}
	
	public function getAll () {
		$merged = array();
		foreach ($this->nodes as $systemnodes) $merged = array_merge($merged, $systemnodes);
		return array_values($merged);
	}
	
	public function getAllForSystem ($system, $state='') {
		if (isset($this->nodes[$system])) {
			if ($state) {
				foreach ($this->nodes[$system] as $node) if ($state == $node->state) $results[] = $node;
				if (isset($results)) return $results;
			}
			else return array_values($this->nodes[$system]);
		}
		return array();
	}
	
	public function getAllIDsForSystem ($system) {
		$nodes = isset($this->nodes[$system]) ? array_values($this->nodes[$system]) : array();
		return array_map(array($this, 'extractID'),$nodes);		
	}
	
	protected function extractID ($node) {
		return $node->nodeid;
	}
	
	public function usedIP ($ip) {
		return in_array($ip, (array) @$this->nodeips);
	}
	
	public function createNode ($system) {
		$node = new Node($system);
		SystemManager::getInstance()->markUpdated($system);
		$node->insert();
	}
	
	public function updateNode ($system, $id) {
		$node = new Node($system,$id);
		$request = Request::getInstance();
		$old = $this->getByID($system, $id);
		if (!$old) $request->sendErrorResponse(sprintf("Update node, no node with system ID '%s' and node ID '%s'", $system, $id), 400);
		$stateid = $request->getParam($request->getMethod(), 'stateid', 0);
		if ($stateid) {
			$request->putParam($request->getMethod(), 'state', NodeStateManager::getInstance()->getByStateID($node->getSystemType(), $stateid));
			$newstate = NodeStateManager::getInstance()->getByStateID($node->getSystemType(), $stateid);
		}
		else $newstate = $request->getParam($request->getMethod(), 'state');
		if ($newstate AND $newstate != $old->state AND NodeStateManager::getInstance()->isProvisioningState($old->state)) {
			class_exists ('SkySQL\\SCDS\\API\\models\\NodeProvisioningStates');
			try {
				$stateobj = NodeNullState::create($old->state);
				$stateobj->make($newstate);
			}
			catch (LogicException $l) {
				$request->sendErrorResponse($l->getMessage(), 500);
			}
			catch (DomainException $d) {
				$request->sendErrorResponse($d->getMessage(), 409);
			}
		}
		$node->update();
	}
	
	public function markUpdated ($system, $id, $stamp=0) {
		$node = new Node($system, $id);
		$node->markUpdated($stamp);
		$this->clearCache(true);
	}
	
	public function deleteNode ($system, $id=0) {
		if ($id) {
			$node = new Node($system,$id);
			if (isset($this->nodes[$system][$id])) unset($this->nodes[$system][$id]);
			SystemManager::getInstance()->markUpdated($system);
			$node->delete();
		}
		else {
			// Must delete components before altering data about nodes
			ComponentPropertyManager::getInstance()->deleteAllComponentsForSystem($system);
			if (isset($this->nodes[$system])) unset($this->nodes[$system]);
			Node::deleteAllForSystem($system);
			$this->clearCache();
		}
	}
}