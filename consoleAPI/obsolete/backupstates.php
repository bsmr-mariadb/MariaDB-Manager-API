<?php

include 'commons.php';
include 'config.php';

class SkyConsoleAPI {
    // Constructor - open connection
    function __construct() {
    	global $DBPath;
		$this->db = new PDO('sqlite:'.$DBPath);
    }
 
    // Destructor - close connection
    function __destruct() {
    	$this->db = null;
    }
     
    function backupStates() {

		$nodestates_query = $this->db->query('SELECT State, Description FROM BackupStates');
			
		foreach ($nodestates_query as $row) {
			$state = $row['State'];
			$description = $row['Description'];
			$data[] = array("state" => $state, "description" => $description);
		}
			
       	$result = array(
            	"backupStates" => $data,
        );
        sendResponse(200, json_encode($result));
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->backupStates();
