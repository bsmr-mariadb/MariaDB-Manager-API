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
 * The Task class models a job in a System, which is the running of a command.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\Request;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\COMMON\WHEN\When;
use SkySQL\SCDS\API\managers\NodeManager;

class Task extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'task';
	protected static $headername = 'Task';
	
	protected static $updateSQL = 'UPDATE Task SET %s WHERE TaskID = :taskid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Task WHERE TaskID = :taskid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Task';
	protected static $insertSQL = 'INSERT INTO Task (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Task WHERE TaskID = :taskid';
	protected static $selectSQL = 'SELECT %s FROM Task WHERE TaskID = :taskid';
	protected static $selectAllSQL = 'SELECT %s FROM Task %s ORDER BY TaskID';
	
	protected static $getAllCTO = array('taskid');
	
	protected static $keys = array(
		'taskid' => array('sqlname' => 'TaskID', 'type' => 'int')
	);
	public $taskid = 0;
	
	protected $node = null;

	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'default' => 0, 'insertonly' => true),
		'privateip' => array('sqlname' => 'PrivateIP', 'default' => '', 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'Command', 'default' => '', 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'default' => '', 'insertonly' => true),
		'steps' => array('sqlname' => 'Steps', 'default' => ''),
		'started' => array('sqlname' => 'Started', 'default' => '', 'validate' => 'datetime', 'insertonly' => true),
		'pid' => array('sqlname' => 'PID', 'default' => 0),
		'completed' => array('sqlname' => 'Completed', 'default' => '', 'validate' => 'datetime'),
		'stepindex' => array('sqlname' => 'StepIndex', 'default' => 0),
		'state' => array('sqlname' => 'State', 'default' => 'running')
	);
	
	public function __construct ($taskid=0) {
		$this->taskid = $taskid;
	}
	
	public function insertOnCommand ($command) {
		$this->command = $command;
		parent::insert(false);
	}

	protected function insertedKey ($insertid) {
		$this->taskid = $insertid;
		return $insertid;
	}

	public function updatePIDandState ($pid) {
		$database = AdminDatabase::getInstance();
		$update = $database->prepare("UPDATE Task SET PID = :pid, State = 'running' WHERE TaskID = :taskid");
		$update->execute(array(':pid' => $pid, ':taskid' => $this->taskid));
		$this->pid = $pid;
		$this->state = 'running';
	}
	
	// Probably belongs elsewhere
	public function updateJobNumber ($number) {
		$database = AdminDatabase::getInstance();
		$update = $database->prepare("UPDATE Task SET ATJobNumber = :atjobnumber, NextStart = :nextstart WHERE TaskID = :taskid");
		$update->execute(array(':atjobnumber' => $number, ':nextstart' => $this->nextstart, ':taskid' => $this->taskid));
		$this->atjobnumber = $number;
	}
	
	public function markErrorCompletion () {
		$database = AdminDatabase::getInstance();
		$this->completed = date('Y-m-d H:i:s');
		$this->state = 'error';
		$update = $database->prepare("UPDATE Task SET State = 'error', Completed = :now WHERE TaskID = :taskid");
		$update->execute(array(':now' => $this->completed, ':taskid' => $this->taskid));
	}
	
	protected function getSteps () {
		$getcmd = AdminDatabase::getInstance()->prepare('SELECT Steps FROM NodeCommands WHERE Command = :command AND State = :state');
		$getcmd->execute(array(':command' => $this->command, ':state' => $this->node->state));
		$this->steps = $getcmd->fetchColumn();
		if (!$this->steps) Request::getInstance()->sendErrorResponse(sprintf("Command '%s' is not valid for specified node in its current state of '%s'", $this->command, $this->node->state), 400);
	}
	
	protected function validateInsert () {
		$request = Request::getInstance();
		foreach (array('systemid','nodeid','username') as $name) {
			if (empty($this->bind[':'.$name])) $errors[] = "Value for $name is required to run a command";
		}
		if (isset($errors)) $request->sendErrorResponse($errors, 400);
		$this->node = NodeManager::getInstance()->getByID($this->bind[':systemid'], $this->bind[':nodeid']);
		$this->privateip = $this->node->privateip;
		if (!$this->node) $request->sendErrorResponse("No node with system ID {$this->bind[':systemid']} and node ID {$this->bind[':nodeid']}", 400);
		$this->getSteps();
		//if (!empty($this->icalentry)) {
		//	$this->processCalendarEntry();
		//	$this->setInsertValue('state', 'scheduled');
		//}
		foreach (array('command','privateip', 'steps') as $name) {
			$this->setInsertValue($name, $this->$name);
		}
		$this->setCorrectFormatDate('completed');
		$this->setCorrectFormatDateWithDefault('started');
	}
	
	// Probably needs to be moved somewhere else
	protected function processCalendarEntry () {
		$calines = explode('|', $this->icalentry);
		$lastone = count($calines) - 1;
		foreach ($calines as $i=>$line) {
			$parts = explode(':', $line, 2);
			if (0 == $i AND ('BEGIN' != $parts[0] OR 'VEVENT' != $parts[1])) $errors[] = "iCalendar event should start with BEGIN:VEVENT";
			if ($lastone == $i AND ('END' != $parts[0] OR 'VEVENT' != $parts[1])) $errors[] = "iCalendar event should end with END:VEVENT";
			if ('DTSTART' == $parts[0]) $dtstart = $parts[1];
			elseif ('RRULE' == $parts[0]) $rrule = $parts[1];
		}
		if (empty($dtstart)) {
			$dtstart = $this->calendarDate();
		}
		if (!preg_match('/^\d{8}T\d{6}Z$/', $dtstart)) {
			$errors[] = "Start date $dtstart for schedule incorrectly formatted";
		}
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors,400);
		$this->updateNextStart($dtstart, $rrule);
		$this->state = 'scheduled';
	}
	
	// Probably needs to be moved elsewhere
	protected function updateNextStart ($dtstart, $rrule) {
		$event = new When();
		$event->recur($dtstart)->rrule($rrule);
		$this->nextstart = date('Y-m-d H:i:s', $event->nextAfter()->getTimeStamp());
		$this->runatonce = $event->alreadyDue();
	}
	
	protected function validateUpdate () {
		if (isset($this->bind[':completed']) AND 'done' == @$this->bind[':state']) {
			$unixtime = strtotime($this->bind[':completed']);
			if (!$unixtime) $this->bind[':completed'] = date('Y-m-d H:i:s');
			if (!isset($this->bind[':stepindex'])) {
				$sqlname = self::$fields['stepindex']['sqlname'];
				$this->setter[] = "$sqlname = :stepindex";
			}
			$this->bind[':stepindex'] = 0;
		}
	}

	// Optional parameters are fromdate and todate, comma separated, in $args[0]
	protected static function specialSelected ($args) {
		$selectors = explode(',', @$args[0]);
		foreach ($selectors as $selector) {
			$unixtime = strtotime($selector);
			if ($unixtime) $dates[] = date('Y-m-d H:i:s', $unixtime);
		}
		if (isset($dates)) {
			$bind[":startdate"] = $dates[0];
			if (1 == count($dates)) {
				$where[] = "started >= :startdate";
			}
			else {
				$where[] = "started >= :startdate AND started <= :enddate";
				$bind[":enddate"] = $dates[1];
			}
		}
		return array((array) @$where, (array) @$bind);
	}
}
