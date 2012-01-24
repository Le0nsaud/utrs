<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once('exceptions.php');
require_once('unblocklib.php');
require_once('userMgmtLogObject.php');


class User{
	
	private $username;
	private $userId;
	private $email;
	private $wikiAccount;
	private $approved;
	private $active;
	private $toolAdmin;
	private $checkuser;
	private $developer;
	private $useSecure;
	private $passwordHash;
	private $comments;
	private $registered;
	
	public function __construct(array $vars, $fromDB){
		debug('in constructor for user <br/>');
		if($fromDB){
			$this->username = $vars['username'];
			$this->userId = $vars['userID'];
			$this->email = $vars['email'];
			$this->wikiAccount = $vars['wikiAccount'];
			$this->approved = ($vars['approved'] == 1 || $vars['approved'] == '1' ? true : false);
			$this->active = ($vars['active'] == 1 || $vars['active'] == '1' ? true : false);
			$this->toolAdmin = ($vars['toolAdmin'] == 1 || $vars['toolAdmin'] == '1' ? true : false);
			$this->checkuser = ($vars['checkuser'] == 1 || $vars['checkuser'] == '1' ? true : false);
			$this->developer = ($vars['developer'] == 1 || $vars['developer'] == '1' ? true : false);
			$this->passwordHash = $vars['passwordHash'];
			$this->useSecure = ($vars['useSecure'] == 1 || $vars['useSecure'] == '1' ? true : false);
			$this->comments = $vars['comments'];
			$this->registered = $vars['registered'];
		}
		else{
			$this->username = $vars['username'];
			$this->email = $vars['email'];
			$this->wikiAccount = $vars['wikiAccount'];
			$this->approved = 0;
			$this->active = 0;
			$this->toolAdmin = 0;
			$this->checkuser = 0;
			$this->developer = 0;
			$this->useSecure = isset($vars['useSecure']);
			$this->passwordHash = hash("sha512", $vars['password']);
			
			$this->insert();
		}
		debug('leaving user constructor <br/>');
	}
	
	public function insert(){
		debug('in insert for User <br />');
		
		$db = connectToDB();
		
		$query = 'INSERT INTO user (username, email, wikiAccount, useSecure, passwordHash)';
		$query .= ' VALUES (\'' . $this->username . '\', ';
		$query .= '\'' . $this->email . '\', ';
		$query .= '\'' . $this->wikiAccount . '\', ';
		$query .= '\'' . $this->useSecure . '\', ';
		$query .= '\'' . $this->passwordHash . '\')';
		
		debug($query . '<br/>');
		
		$result = mysql_query($query, $db);
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		debug('Insert complete <br/>');
		
		$query = "SELECT userID, registered FROM user WHERE username='" . $this->username . "'";
		debug($query . '<br/>');
		$result = mysql_query($query, $db);
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		$row = mysql_fetch_assoc($result);
		
		$this->userId = $row['userID'];
		$this->registered = $row['registered'];		
		
		UserMgmtLog::insert('created account', 'New account', $this->userId, $this->userId);
		
		debug('exiting user insert <br/>');
	}
	
	public static function getUserById($id){
		$db = connectToDB();
		
		$query = 'SELECT * FROM user WHERE userID=\'' . $id . '\'';
		
		$result = mysql_query($query, $db);
		if(!$result){
			$error = mysql_error($db);
			throw new UTRSDatabaseException($error);
		}
		if(mysql_num_rows($result) == 0){
			throw new UTRSDatabaseException('No results were returned for user ID ' . $id);
		}
		if(mysql_num_rows($result) != 1){
			throw new UTRSDatabaseException('Please contact a tool developer. More '
				. 'than one result was returned for user ID ' . $id);
		}
		
		$values = mysql_fetch_assoc($result);
		
		return new User($values, true);
	}
	
	public static function getUserByUsername($username){
		$db = connectToDB();
		
		$query = 'SELECT * FROM user WHERE username=\'' . $username . '\'';
		
		$result = mysql_query($query, $db);
		if(!$result){
			$error = mysql_error($db);
			throw new UTRSDatabaseException($error);
		}
		if(mysql_num_rows($result) == 0){
			throw new UTRSDatabaseException('No results were returned for username ' . $username);
		}
		if(mysql_num_rows($result) != 1){
			throw new UTRSDatabaseException('Please contact a tool developer. More '
				. 'than one result was returned for user ID ' . $id);
		}
		
		$values = mysql_fetch_assoc($result);
		
		return new User($values, true);
	}
	
	public function getUsername(){
		return $this->username;
	}
	
	public function getWikiAccount(){
		return $this->wikiAccount;
	}
	
	public function getUseSecure(){
		return $this->useSecure;
	}
	
	public function getEmail(){
		return $this->email;
	}
	
	public function getPasswordHash(){
		return $this->passwordHash;
	}
	
	public function isApproved(){
		return $this->approved;
	}
	
	public function isActive(){
		return $this->active;
	}
	
	public function isAdmin(){
		return $this->toolAdmin;
	}
	
	public function isCheckuser(){
		return $this->checkuser;
	}
	
	public function isDeveloper(){
		return $this->developer;
	}
	
	public function getComments(){
		return $this->comments;
	}
	
	public function getRegistered(){
		return $this->registered;
	}
	
	public function setNewPreferences($newSecure, $newEmail){
		if($newEmail != null & !validEmail($newEmail)){
			throw new UTRSIllegalModificationException('The email address you have entered (' . $newEmail . ') is invalid.');
		}
		if(($newEmail == null | ($newEmail != null & $newEmail == $this->email)) & $newSecure == $this->useSecure){
			throw new UTRSIllegalModificationException('You have not changed any of your preferences.');
		}
		
		// ok to change	
		$secureInt = 0;	
		if($newSecure){
			$secureInt = 1;
		}
		$query = "UPDATE user SET useSecure='" . $secureInt . "', email='" . $newEmail . "' ";
		$query .= "WHERE userID='" . $this->userId . "'";
		
		debug($query);
		
		$db = connectToDB();
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		
		$this->email = $newEmail;
		$this->useSecure = $newSecure;
	}
	
	public function setNewPassword($oldpass, $newpass){
		if($oldpass == null | $oldpass != $this->passwordHash){
			throw new UTRSIllegalModificationException("Your current password is incorrect.");
		}
		
		// ok to update
		$query = "UPDATE user SET passwordHash='" . $newpass . "' WHERE userID='" . $this->userId . "'";
		
		debug($query);
		
		$db = connectToDB();
		
		$result = mysql_query($query, $db);
		
		if(!$result){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
	}
}

?>