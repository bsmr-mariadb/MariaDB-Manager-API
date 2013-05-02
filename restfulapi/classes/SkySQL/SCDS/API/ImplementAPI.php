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
 */

namespace SkySQL\SCDS\API;

use SkySQL\COMMON\AdminDatabase;

abstract class ImplementAPI {
	protected $db = null;
	protected $requestor = null;
	protected $config = array();
	protected $fieldnames = array();
	protected $requestmethod = '';

	public function __construct ($requestor) {
		$this->db = AdminDatabase::getInstance();
		$this->requestor = $requestor;
		$this->config = $requestor->getConfig();
		$this->requestmethod = $requestor->getMethod();
		$filter = $this->getParam('GET', 'fields');
		if ($filter) $this->fieldnames = array_map('trim', explode(',', $filter));
	}
	
	protected function getParam ($arrname, $name, $def=null, $mask=0) {
		return $this->requestor->getParam($arrname, $name, $def, $mask);
	}
	
	protected function paramEmpty ($arrname, $name) {
		return $this->requestor->paramEmpty($arrname, $name);
	}
	
	protected function settersAndBinds ($source, $fields) {
		$bind = $setter = $insname = $insvalue = array();
		foreach ($fields as $name=>$about) {
			if ($source) {
				$input = $this->getParam($source, $name, $about['default']);
				if ($input) {
					$insname[] = $about['sqlname'];
					$insvalue[] = ':'.$name;
					$bind[':'.$name] = $input;
					$setter[] = $about['sqlname'].' = :'.$name;
				}
			}
		}
		return array($insname, $insvalue, $setter, $bind);
	}
	
	protected function getSelects ($fields, $selects=array()) {
		foreach ($fields as $name=>$about) {
			$selects[] = $about['sqlname'].' AS '.$name;
		}
		return implode(',', $selects);
	}
	
	protected function filterResults ($results) {
		$filter = $this->getParam('GET', 'fields');
		if (count($this->fieldnames)) {
			foreach ($results as $key=>$value) {
				$filtered[$key] = $this->filterWords($value);
			}
			return $filtered;
		}
		else return $results;
	}
	
	protected function isFilterWord ($word) {
		return empty($this->fieldnames) OR in_array($word, $this->fieldnames);
	}
	
	protected function filterWords ($value) {
		foreach ($this->fieldnames as $word) if (isset($value[$word])) {
			$hits[$word] = $value[$word];
		}
		return empty($hits) ? null : (1 == count($hits)) ? $hits[$word] : $hits;
	}
	
	protected function startImmediateTransaction () {
		$this->db->startImmediateTransaction();
	}
	
	protected function sendResponse ($body='', $status=200, $content_type='application/json') {
		$this->db->commitTransaction();
		return $this->requestor->sendResponse($body, $status, $content_type);
	}

	protected function sendErrorResponse ($errors, $status=200, $content_type='application/json') {
		$this->db->rollbackTransaction();
		return $this->requestor->sendErrorResponse($errors, $status, $content_type);
	}
	
	protected function log ($data) {
		$this->requestor->log($data);
	}
}