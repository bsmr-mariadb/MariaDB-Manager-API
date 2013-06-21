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
 * The Backup class models a backup of a system.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;

class Backup extends EntityModel {
	protected static $setkeyvalues = true;

	protected static $classname = __CLASS__;

	protected $ordinaryname = 'backup';
	protected static $headername = 'Backup';
	
	protected static $updateSQL = 'UPDATE Backup SET %s WHERE SystemID = :systemid AND BackupID = :backupid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Backup WHERE SystemID = :systemid AND BackupID = :backupid';
	protected static $insertSQL = 'INSERT INTO Backup (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Backup WHERE SystemID = :systemid AND BackupID = :backupid';
	protected static $selectSQL = 'SELECT %s FROM Backup WHERE SystemID = :systemid AND BackupID = :backupid';
	protected static $selectAllSQL = 'SELECT %s FROM Backup %s ORDER BY SystemID, BackupID';
	
	protected static $getAllCTO = array('backupid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'desc' => 'ID for the system'),
		'backupid' => array('sqlname' => 'BackupID', 'desc' => 'ID for the backup')
	);

	protected static $fields = array(
		'nodeid' => array('sqlname' => 'NodeID', 'desc' => 'ID for the node running the backup', 'default' => 0),
		'level' => array('sqlname' => 'BackupLevel', 'desc' => 'Backup level, 1 = standard, 2 = incremental', 'default' => 0),
		'parentid' => array('sqlname' => 'ParentID', 'desc' => 'Base for an incremental backup', 'default' => 0),
		'state' => array('sqlname' => 'State', 'desc' => 'Current state of the backup', 'default' => 'running'),
		'started' => array('sqlname' => 'Started', 'desc' => 'Date and time backup started', 'default' => ''),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Date and time backup updated', 'default' => ''),
		'restored' => array('sqlname' => 'Restored', 'desc' => 'Date and time backup restored', 'default' => ''),
		'size' => array('sqlname' => 'Size', 'desc' => 'Size of the backup', 'default' => 0),
		'storage' => array('sqlname' => 'Storage', 'default' => ''),
		'binlog' => array('sqlname' => 'BinLog', 'default' => ''),
		'log' => array('sqlname' => 'Log', 'default' => '')
	);
	
	public function __construct ($systemid, $backupid=0) {
		$this->systemid = $systemid;
		$this->backupid = $backupid;
	}
	
	public static function getBackupStates () {
		return API::mergeStates(API::$backupstates);
	}

	protected function keyComplete () {
		return $this->backupid ? true : false;
	}
	
	protected function makeNewKey () {
		$highest = AdminDatabase::getInstance()->prepare('SELECT MAX(BackupID) FROM Backup WHERE SystemID = :systemid');
		$highest->execute(array(':systemid' => $this->systemid));
		$this->backupid = 1 + (int) $highest->fetch(PDO::FETCH_COLUMN);
		$this->bind[':backupid'] = $this->backupid;
	}

	protected function insertedKey ($insertid) {
		return $this->backupid;
	}

	protected function validateInsert () {
		if (empty($this->bind[':nodeid'])) $errors[] = 'No value provided for node when requesting system backup';
		if (empty($this->bind[':level'])) $errors[] = 'No value provided for level when requesting system backup';
		elseif ($this->bind[':level'] != 1 AND $this->bind[':level'] != 2) $errors[] = "Level given {$this->bind[':level']}, must be 1 or 2 (full or incremental)";
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors, 400);
		if (2 == $this->bind[':level']) {
			$getlog = AdminDatabase::getInstance()->prepare('SELECT BinLog, MAX(Started) AS binlog FROM Backup 
				WHERE SystemID = :systemid AND NodeID = :nodeid AND BackupLevel = 1');
			$getlog->execute(array(
			':systemid' => $this->systemid,
			':nodeid' => $this->bind[':nodeid']
			));
			$this->setInsertValue('binlog', $getlog->fetch(PDO::FETCH_COLUMN));
		}
	}

	public static function getSelectedBackups ($systemid, $fromdate, $todate, $limit, $offset) {
		$mainquery = "SELECT BackupID AS backupid, NodeID as nodeid, BackupLevel AS level, State AS state,
			Size AS size, Started AS started, Updated AS updated, Restored AS restored,
			Storage AS storage, Log AS log, BinLog AS binlog, ParentID AS parentid FROM Backup WHERE ";
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
		$conditions = implode(' AND ', $where);
		$mainquery .= $conditions;
		$database = AdminDatabase::getInstance();
		if ($limit) {
			$totaller = $database->prepare('SELECT COUNT(*) FROM Backup WHERE '.$conditions);
			$totaller->execute($bind);
			$total = $totaller->fetch(PDO::FETCH_COLUMN);
			$mainquery .= " LIMIT $limit OFFSET $offset";
		}
		$statement = $database->prepare($mainquery);
		$statement->execute($bind);
		$backups = $statement->fetchALL(PDO::FETCH_ASSOC);
		return array(($limit ? $total : count($backups)), $backups);
	}
}
