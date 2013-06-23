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
 * The AdminDatabase class wraps a PDO instance and sets some defaults.
 * 
 */

namespace SkySQL\COMMON;

use PDO;
use PDOException;
use SkySQL\SCDS\API\Request;
use SQLite3;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class AdminDatabase {
    protected static $instance = null;
    protected $pdo = null;
    protected $sql = '';
    protected $trace = '';
    protected $lastcall = '';
	protected $transact = false;
	
    protected function __construct () {
        $config = Request::getInstance()->getConfig();
		$dbconfig = $config['database'];
		$this->pdo = $this->checkAndConnect($dbconfig);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
	}
	
	protected function checkAndConnect ($dbconfig) {
		$dboparts = explode(':', $dbconfig['pdoconnect']);
		$dbdirectory = dirname(@$dboparts[1]);
		if ('sqlite' != $dboparts[0] OR 2 > count($dboparts)) {
			$error = sprintf('Configuration at %s should contain a PDO connection string for a SQLite database',_API_INI_FILE_LOCATION);
		}
		elseif (!file_exists($dbdirectory) OR !is_dir($dbdirectory) OR !is_writeable($dbdirectory)) {
			$error = "Database directory $dbdirectory does not exist or is not writeable - please check existence, permissions and SELinux constraints";
		}
		elseif (file_exists($dboparts[1])) {
			if (!is_writeable($dboparts[1])) $error = 'Database file exists but is not writeable';
		}
		else {
			$sqlfile = dirname(__FILE__).'/AdminDatabase.sql';
			$nocomment = preg_replace('#/\*.*?\*/#s', '', file_get_contents($sqlfile));
			$sqldb = new SQLite3($dboparts[1]);
			$sqldb->exec($nocomment);
			$sqldb->close();
		}
		if (isset($error)) throw new PDOException($error);
		$pdo = new PDO($dbconfig['pdoconnect'], $dbconfig['user'], $dbconfig['password']);
		return $pdo;
	}
	
	public function __destruct () {
		if ($this->transact) $this->rollbackTransaction ();
		$this->pdo = null;
	}
	
	public function __call($name, $arguments) {
		$this->lastcall = $name;
		return call_user_func_array(array($this->pdo, $name), $arguments);
	}
	
	public function prepare () {
		return $this->saveAndCall('prepare', func_get_args());
	}
	
	public function query () {
		return $this->saveAndCall('query', func_get_args());
	}
	
	protected function saveAndCall ($type, $arguments) {
		$this->sql = $arguments[0];
		$this->trace = Diagnostics::trace();
		$this->lastcall = $type;
		return call_user_func_array(array($this->pdo, $type), $arguments);
	}
	
	public function getSQL () {
		return $this->sql;
	}
	
	public function getTrace () {
		return $this->trace;
	}
	
	public function getLastCall () {
		return $this->lastcall;
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function startImmediateTransaction () {
		$this->query('BEGIN IMMEDIATE TRANSACTION');
		$this->transact = true;
	}
	
	public function commitTransaction () {
		if ($this->transact) $this->query('COMMIT TRANSACTION');
		$this->transact = false;
	}
	
	public function rollbackTransaction () {
		if ($this->transact) $this->query('ROLLBACK TRANSACTION');
		$this->transact = false;
	}
}