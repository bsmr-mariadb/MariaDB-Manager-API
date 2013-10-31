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
 * The Request class is the main controller for the API.
 * 
 * The $uriTable array defines the API RESTful interface, and links calls to
 * classes and methods.  The Request class puts this into effect.
 * 
 * The constructor splits up the model URIs into their constituent parts.
 * 
 * The doControl method first fetches the request URI and extracts the relevant
 * part, then explodes it into parts.  Then the request URI is compared with 
 * the model URIs to find what is to be done (or an error return).
 * 
 * The sendResponse method is provided for the benefit of the classes that 
 * implement the API.
 * 
 */

namespace SkySQL\SCDS\API;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

final class CommandRequest extends Request {
	protected static $requestclass = __CLASS__;
	protected static $instance = null;
	
	protected $suppress = true;
	
	public static function getInstance() {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	protected function __construct () {
		global $argv;
		$this->requestmethod = @$argv[1];
		$this->requestviapost = true;
		if (isset($argv[3])) {
			$this->parse_str($argv[3], $_POST);
	        $_POST['suppress_response_codes'] = 'true';
		}
		$this->getHeaders();
		parent::__construct();
	}

	protected function checkSecurity () {
		// Do nothing when called from command line or script
	}

	protected function getHeaders () {
		if (isset($argv[4])) {
			$headers = explode('|', $argv[4]);
			foreach ($headers as $header) {
				$parts = explode(':', $header);
				if (2 == count($parts)) {
					$key = str_replace(" ","-",ucwords(strtolower(str_replace("-"," ",$parts[0]))));
					$this->headers[$key] = trim($parts[1]);
				}
			}
		}
	}

	public function sendHeaders ($status) {
		// Send no headers when called from command line or script
		return HTTP_PROTOCOL.' '.$status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
	}

	protected function getURI () {
		global $argv;
		return trim(@$argv[2], "/ \t\n\r\0\x0B");
	}
	
	protected function handleAccept () {
		$this->accept = 'application/json';
	}
}
