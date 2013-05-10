<?php

/*
 * Part of the SCDS API.
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
 * The SystemBackups class within the API implements request to do with backups.
 * 
 * The getSystemsBackups method requires a system ID, and has optional parameters
 * of from and/or to date; it returns information about existing backups.
 * 
 * The makeSystemBackup method instigates a backup.
 */

namespace SkySQL\SCDS\API;

use \PDO;
use \PDOException;

class SystemBackups extends ImplementAPI {
	protected static $fields = array(
		'level' => array('sqlname' => 'BackupLevel', 'default' => 0),
		'parent' => array('sqlname' => 'ParentID', 'default' => 0),
		'state' => array('sqlname' => 'State', 'default' => 0),
		'started' => array('sqlname' => 'Started', 'default' => ''),
		'updated' => array('sqlname' => 'Updated', 'default' => ''),
		'restored' => array('sqlname' => 'Restored', 'default' => ''),
		'size' => array('sqlname' => 'Size', 'default' => 0),
		'storage' => array('sqlname' => 'Storage', 'default' => ''),
		'binlog' => array('sqlname' => 'BinLog', 'default' => ''),
		'log' => array('sqlname' => 'Log', 'default' => ''),
	);

	protected $errors = array();

	public function getSystemBackups ($uriparts) {
		$systemid = (int) $uriparts[1];
		$limit = $this->getParam('GET', 'limit', 10);
		$offset = $this->getParam('GET', 'offset', 0);
		$fromdate = $this->getDate('from');
		$todate = $this->getDate('to');
		$mainquery = "SELECT rowid AS id, NodeID as node, BackupLevel AS level, State AS status,
			Size AS size, Started AS started, Updated AS updated, Restored AS restored,
			Storage AS storage, Log AS log, ParentID AS parent FROM Backup WHERE ";
		$where[] = "SystemID = :systemid";
		$bind[':systemid'] = $systemid;
		if ($fromdate) {
			$where[] = "started >= :fromdate";
			$bind[':fromdate'] = $fromdate;
		}
		if ($todate) {
			$where[] = "started <= :todate";
			$bind[':todate'] = $todate;
		}
		if (count($this->errors)) {
			$this->sendErrorResponse($this->errors, 400);
			exit;
		}
		$conditions = implode(' AND ', $where);
		$mainquery .= $conditions;
		if ($limit) {
			$totaller = $this->db->prepare('SELECT COUNT(*) FROM Backup WHERE '.$conditions);
			$totaller->execute($bind);
			$results['total'] = $totaller->fetch(PDO::FETCH_COLUMN);
			$mainquery .= " LIMIT $limit OFFSET $offset";
		}
		$statement = $this->db->prepare($mainquery);
		$statement->execute($bind);
		$results['backups'] = $statement->fetchALL(PDO::FETCH_ASSOC);
        $this->sendResponse(array('result' => $this->filterResults($results)));
	}
	
	protected function getDate ($datename) {
		$date = $this->getParam('GET', $datename);
		if ($date) {
			$time = strtotime($date);
			if ($time) return date('d M Y H:i:s');
			else $this->errors[] = "Invalid $datename date: $date";
		}
	}
	
	public function updateSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$backupid = (int) $uriparts[3];
		list($insname, $insvalue, $setter, $bind) = $this->settersAndBinds('PUT', self::$fields);
		$bind[':systemid'] = $systemid;
		$bind[':backupid'] = $backupid;
		if (!empty($setter)) {
			$update = $this->db->prepare('UPDATE Backup SET '.implode(', ',$setter).
				' WHERE SystemID = :systemid AND BackupID = :backupid');
			$update->execute($bind);
			$counter = $update->fetch(PDO::FETCH_COLUMN);
		}
		else $counter = 0;
		$this->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
		/*		
		if ('yes' == $restored) {
			$sets[] = "Restored = datetime('now')";
		}
		 * 
		 */
	}
	
	public function makeSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$nodeid = $this->getParam('POST', 'nodeid', 0);
		if (!$nodeid) $errors[] = 'No value provided for node when requesting system backup';
		$level = $this->getParam('POST', 'level', 0);
		if (!$level) $errors[] = 'No value provided for level when requesting system backup';
		if ($level AND $level != 1 AND $level != 2) $errors[] = "Level given $level, must be 1 or 2 (full or incremental)";
		$parent = $this->getParam('POST', 'parentid');
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		$query = $this->db->prepare("INSERT INTO Backup (SystemID, NodeID, BackupLevel, Started, ParentID)
			VALUES(:systemid, :nodeid, :level, datetime('now'), :parent)");
		try {
			$query->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid,
				':level' => $level,
				':parent' => $parent
			));
			$result['id'] = $this->db->lastInsertId();
			// Extra work for incremental backup
			if (2 == $level) {
				$getlog = $this->db->prepare('SELECT MAX(Started), BinLog AS binlog FROM Backup 
					WHERE SystemID = :systemid AND NodeID = :nodeid AND BackupLevel = 1');
				$getlog->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid
				));
				$result['binlog'] = $getlog->fetch(PDO::FETCH_COLUMN);
			}
			$this->sendResponse(array('backup' => $this->db->lastInsertId()));
		}
		catch (PDOException $pe) {
			$this->sendErrorResponse("Failed backup request, system ID $systemid, node ID $nodeid, level $level, parent $parent", 500, $pe);
		}
	}

	public function getBackupStates () {
		$query = $this->db->query('SELECT State AS state, Description AS description FROM BackupStates');
        $this->sendResponse(array("backupStates" => $query->fetchAll(PDO::FETCH_ASSOC)));
	}
}