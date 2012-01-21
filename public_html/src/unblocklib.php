<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
ini_set('session.use_cookies', '1');

require_once('exceptions.php');
require_once('userObject.php');

function loggedIn(){	
	if(!isset($_SESSION)){
		session_id('UTRSLogin');
		session_name('UTRSLogin');
		session_start();
	}
	
	if(isset($_SESSION['user']) && isset($_SESSION['passwordHash'])){
		// presumably good, but confirming that the cookie is valid...
		$user = $_SESSION['user'];
		$password = $_SESSION['passwordHash'];
		$db = connectToDB(true);
		$query = 'SELECT username FROM user WHERE username=\'' . $user . '\' AND passwordHash=\'' . $password . '\'';
		$result = mysql_query($query, $db);
		if($result === false){
			$error = mysql_error($db);
			debug('ERROR: ' . $error . '<br/>');
			throw new UTRSDatabaseException($error);
		}
		if(mysql_num_rows($result) == 1){
			return true;
		}
		if(mysql_num_rows($result) > 1){
			throw new UTRSDatabaseException('There is more than one record for your username. '
			. 'Please contact a tool developer immediately.');
		}
	}
	return false;
}

/**
 * Confirm user is logged in; if not, kick them out to the login page.
 * @param string $destination the page to go to once logged in ('home.php', 'mgmt.php', etc.)
 */
function verifyLogin($destination = 'home.php'){
	if(!loggedIn()){
		header("Location: " . getRootURL() . 'login.php?destination=' . $destination);
	}
}

function getRootURL(){
	return 'http://toolserver.org/~unblock/dev/';
}

/**
 * echo's a debugging string if $DEBUG_MODE is set to true
 * @param String $message the message to echo
 */
function debug($message){
	$DEBUG_MODE = false;
	if($DEBUG_MODE){
		echo $message;
	}
}

/**
 * Connects to and returns a resource link to the UTRS database
 * @param boolean suppressOutput - true to disable debug statements, should be used
 * 				only on redirection pages.
 * @throws UTRSDatabaseException
 */
function connectToDB($suppressOutput = false){
	if(!$suppressOutput){
		debug('connectToDB <br />');
	}
	$ts_pw = posix_getpwuid(posix_getuid());
	$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/.my.cnf");
	$db = mysql_connect("sql-s1-user.toolserver.org", $ts_mycnf['user'], $ts_mycnf['password'], true);
	if($db == false){
		debug(mysql_error());
		throw new UTRSDatabaseException("Failed to connect to database cluster sql-s1-user!");
	}
	mysql_select_db("p_unblock", $db);
	if(!$suppressOutput){
		debug('exiting connectToDB');
	}
	return $db;
}

/**
 * Returns a URL to the given page on the English Wikipedia.
 * @param String $page the page to link to, including namespace
 * @param boolean $useSecure true to use the secure server
 * @param String $queryOptions query options, such as used on some log pages,
 *  separated by (but not starting with) &'s.
 */
function getWikiLink($page, $useSecure = false, $queryOptions = ''){
	$url = "http";
	if($useSecure){
		$url .= "s";
	}
	
	$url .= "://en.wikipedia.org/";
	
	if($queryOptions){
		$url .= "w/index.php?title=" . $page . "&" . $queryOptions;
	}
	else{
		$url .= 'wiki/' . $page;
	}
	
	return $url;
}

function getCurrentUser(){
	if(loggedIn()){
		return User::getUserByUsername($_SESSION['user']);
	}
	return null;
}

/**
 Validate an email address.
 Provide email address (raw input)
 Returns true if the email address has the email
 address format and the domain exists.

 This function taken from http://www.linuxjournal.com/article/9585?page=0,3
 as it's for linux and posted for anyone to use, I shall assume it's ok
 with licensing and such.
 */
function validEmail($email)
{
	$isValid = true;
	$atIndex = strrpos($email, "@");
	if (is_bool($atIndex) && !$atIndex)
	{
		$isValid = false;
	}
	else
	{
		$domain = substr($email, $atIndex+1);
		$local = substr($email, 0, $atIndex);
		$localLen = strlen($local);
		$domainLen = strlen($domain);
		if ($localLen < 1 || $localLen > 64)
		{
			// local part length exceeded
			$isValid = false;
		}
		else if ($domainLen < 1 || $domainLen > 255)
		{
			// domain part length exceeded
			$isValid = false;
		}
		else if ($local[0] == '.' || $local[$localLen-1] == '.')
		{
			// local part starts or ends with '.'
			$isValid = false;
		}
		else if (preg_match('/\\.\\./', $local))
		{
			// local part has two consecutive dots
			$isValid = false;
		}
		else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
		{
			// character not valid in domain part
			$isValid = false;
		}
		else if (preg_match('/\\.\\./', $domain))
		{
			// domain part has two consecutive dots
			$isValid = false;
		}
		else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
		str_replace("\\\\","",$local)))
		{
			// character not valid in local part unless
			// local part is quoted
			if (!preg_match('/^"(\\\\"|[^"])+"$/',
			str_replace("\\\\","",$local)))
			{
				$isValid = false;
			}
		}
		if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
		{
			// domain not found in DNS
			$isValid = false;
		}
	}
	return $isValid;
}

/**
 * Displays a pretty error box
 */
function displayError($errorMsg){
	echo "<table class=\"error\"><tr><td>" . $errorMsg . "</td></tr></table>";
}

/**
 * Displays a pretty success box
 */
function displaySuccess($successMsg){
	echo "<table class=\"success\"><tr><td>" . $successMsg . "</td></tr></table>";
}

?>