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
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API\controllers;

use \PDO as PDO;
use SkySQL\SCDS\API\API;

class Commands extends ImplementAPI {

	public function getCommands () {
		$commands = $this->db->query("SELECT CommandID AS id, Name AS name, Description AS description, Icon AS icon, Steps AS steps FROM Commands WHERE UIOrder IS NOT NULL ORDER BY UIOrder");
		$results = $this->filterResults($commands->fetchAll(PDO::FETCH_ASSOC));
		if (count($results)) $this->sendResponse(array('commands' => $results));
		else $this->sendErrorResponse('', 404);
	}
	
	public function getStates () {
        $this->sendResponse(array("commandStates" => API::mergeStates(API::$commandstates)));
	}
	
	public function getSteps () {
		//$this->sendResponse(array('command_steps' => API::mergeStates(API::$commandsteps)));
		
		$stepstatement = $this->db->query('SELECT StepID AS id, Script AS script, 
			Icon AS icon, Description AS description FROM Step');
		$steps = $this->filterResults($stepstatement->fetchAll(PDO::FETCH_ASSOC));
		$this->sendResponse(array("command_steps" => $steps));
	}
}