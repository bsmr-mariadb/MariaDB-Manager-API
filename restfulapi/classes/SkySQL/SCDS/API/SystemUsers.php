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

use \PDO;
use \PDOException;

class SystemUsers extends ImplementAPI {
	
	public function getUsers () {
		$query = $this->db->query("SELECT UserID AS id, UserName AS username, Name AS name FROM Users");
        $result = array(
            "users" => $query->fetchAll(PDO::FETCH_ASSOC)
        );
        $this->sendResponse($result);
	}
	
	public function putUser ($uriparts) {
		$username = @urldecode($uriparts[1]);
		if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
			$errors[] = "User name must only contain alphameric and underscore, $username submitted";
		}
		$name = $this->getParam('PUT', 'name');
		$password = $this->getParam('PUT', 'password');
		$salt = $this->getSalt($username);
		if ($salt) {
			$this->db->query('BEGIN IMMEDIATE TRANSACTION');
			$result['username'] = $username;
			if ($name) {
				$sets[] = 'Name = :name';
				$bind[':name'] = $name;
				$result['name'] = $name;
				
			}
			if ($password) {
				$sets[] = 'Password = :password';
				$bind[':password'] = sha1($salt.$password);
			}
			if (isset($sets)) {
				$bind[':username'] = $username;
				$query = $this->db->prepare('UPDATE Users SET '.implode(', ', $sets).' WHERE UserName = :username');
				$query->execute($bind);
			}
			$this->db->query('COMMIT TRANSACTION');
			$this->sendResponse($result);
		}
		else {
			if (!$password) $errors[] = 'No password provided for create user '.$username;
			if (isset($errors)) {
				$this->sendErrorResponse($errors, 400);
				exit;
			}
			$salt = $this->makeSalt();
			$passwordhash = sha1($salt.$password);
			try {
				$query = $this->db->prepare("INSERT INTO Users (UserName, Name, Password, Salt) VALUES (:username, :name, :password, :salt)");
				$query->execute(array(
					':username' => $username,
					':name' => $name,
					':password' => $passwordhash,
					':salt' => $salt
				));
				$this->sendResponse(array('username' => $username, 'name' => $name));
			}
			catch (PDOException $pe) {
				$this->sendErrorResponse('User insertion failed unexpectedly', 500, $pe);
			}
		}
	}
		
	protected function makeSalt () {
		return $this->makeRandomString(24);
	}
	
	protected function makeRandomString ($length=8) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!%,-:;@_{}~";
		for ($i = 0, $makepass = '', $len = strlen($chars); $i < $length; $i++) $makepass .= $chars[mt_rand(0, $len-1)];
		return $makepass;
	}

	public function deleteUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$query = $this->db->prepare('DELETE FROM Users WHERE UserName = :username');
		$query->execute(array(':username' => $username));
		if ($query->rowCount()) $this->sendResponse('ok');
		else $this->sendErrorResponse('Delete user did not match any user', 404);
	}
	
	public function loginUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$password = isset($_POST['password']) ? $_POST['password'] : '';
		$salt = $this->getSalt($username);
		$passwordhash = sha1($salt.$password);
		$query = $this->db->prepare('SELECT COUNT(*) FROM Users WHERE UserName = :username AND Password = :password');
		$query->execute(array(
			':username' => $username,
			':password' => $passwordhash
		));
		if ($query->fetch(PDO::FETCH_COLUMN)) $this->sendResponse('ok');
		else $this->sendErrorResponse('Login failed', 409);
	}
	
	protected function getSalt ($username) {
		$saltquery = $this->db->prepare('SELECT Salt FROM Users WHERE UserName = :username');
		$saltquery->execute(array(':username' => $username));
		return $saltquery->fetch(PDO::FETCH_COLUMN);
	}
}