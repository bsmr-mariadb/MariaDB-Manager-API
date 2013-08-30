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

class MonitorDatabase extends APIDatabase {
    protected static $instance = null;
	
	protected function checkAndConnect ($dbconfig) {
		$dboparts = explode(':', $dbconfig['monconnect']);
		$dbdirectory = dirname(@$dboparts[1]);
		if ('sqlite' != $dboparts[0] OR 2 > count($dboparts)) {
			$error = sprintf('Configuration for Monitor DB at %s should contain a PDO connection string for a SQLite database',_API_INI_FILE_LOCATION);
		}
		elseif (!file_exists($dbdirectory) OR !is_dir($dbdirectory) OR !is_writeable($dbdirectory)) {
			$error = "Database directory $dbdirectory does not exist or is not writeable - please check existence, permissions and SELinux constraints";
		}
		elseif (file_exists($dboparts[1])) {
			if (!is_writeable($dboparts[1])) $error = 'Database file exists but is not writeable';
		}
		else {
			$sqlfile = dirname(__FILE__).'/MonitorDatabase.sql';
			$nocomment = preg_replace('#/\*.*?\*/#s', '', file_get_contents($sqlfile));
			$sqldb = new SQLite3($dboparts[1]);
			$sqldb->exec($nocomment);
			$sqldb->close();
		}
		if (isset($error)) throw new PDOException($error);
		$pdo = new PDO($dbconfig['monconnect'], $dbconfig['monuser'], $dbconfig['monpassword']);
		return $pdo;
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
}