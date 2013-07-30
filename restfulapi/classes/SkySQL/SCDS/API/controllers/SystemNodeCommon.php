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

use PDO;
use stdClass;
use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\managers\NodeManager;

abstract class SystemNodeCommon extends ImplementAPI {
	protected $monitorquery = null;
	protected $systemid = 0;
	
	public function __construct ($controller) {
		parent::__construct($controller);
		$this->monitorquery = $this->db->prepare('SELECT Monitor AS monitor, Value AS value, MAX(Stamp) FROM MonitorData 
			WHERE SystemID = :systemid AND NodeID = :nodeid GROUP BY Monitor');
	}

	protected function getMonitorData ($nodeid) {
		$monitors = MonitorManager::getInstance()->getAll();
		$monitorlatest = new stdClass;
		foreach ($monitors as $monitor) {
			$property = $monitor->monitor;
			$monitorlatest->$property = null;
		}
		$this->monitorquery->execute(
			array(':systemid' => $this->systemid, ':nodeid' => $nodeid)
		);
		$latest = $this->monitorquery->fetchAll();
		foreach ($latest as $data) {
			$property = $data->monitor;
			$monitorlatest->$property = $data->value;
		}
		return $monitorlatest;
	}
	
	protected function targetDatabaseQuery ($query, $nodeid) {
		try {
			$node = NodeManager::getInstance()->getByID($this->systemid, $nodeid);
			if (!$node) $this->sendErrorResponse("System $this->systemid and node $nodeid are not valid node identifiers", 400);
			$connection = "mysql:host=$node->privateip;dbname=information_schema";
			if ($node->port) $connection .= ";port=$node->port";
            $this->subjectdb = new PDO($connection, $node->dbusername, $node->dbpassword);
            $this->subjectdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->subjectdb->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->subjectdb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			try {
				$statement = $this->subjectdb->prepare($query);
	            $statement->execute();
				$results = $statement->fetchAll();
				return $results;
			}
			catch (PDOException $pe) {
				$this->sendResponse(array('error' => $pe->getMessage()));
			}
        }
        catch (PDOException $pe) {
            $this->sendErrorResponse($pe->getMessage(), 400);
            exit;
        }
	}

	protected function getNodeProcesses ($nodeid) {
		$processes = $this->targetDatabaseQuery('SHOW PROCESSLIST', $nodeid);
		if ($processes) foreach ($processes as &$process) {
			$process->nodeid = $nodeid;
			foreach (get_object_vars($process) as $name=>$value) {
				$lcname = strtolower($name);
				if ($lcname != $name) {
					$process->$lcname = $value;
					unset($process->$name);
				}
			}
		}
		return $processes;
	}
}