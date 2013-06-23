<?php
/**
* init file.
*
* This will init the system and load up the required controller based on the request
*
*/
use Core\Request as Request;

/**
 * Custom error handeling function
 * @param  integer  $errno        The error type that occured
 * @param  string   $message  The error message
 * @param  string   $file      The file where the error happened
 * @param  integer  $line      The line of the file where the error happened
 * @return boolean
 *
 * @todo  Expand this so certain errors are always logged in our logging system
 * @todo  cut off full path of $file so it just shows path relative to app root (/includes/model/users.php ...)
 * @todo  Add the ability to ignore notice and warnings and just log them
 */
function error_handler($errno, $message, $file, $line) {
	global $ERRORS;

	switch ($errno) {
		case E_NOTICE :
		case E_USER_NOTICE : {
			$type = "Notice";
		}
		case E_WARNING :
		case E_USER_WARNING :{
			$type = "Warning";
			break;
		}
		case E_ERROR :
		case E_USER_ERROR :{
			$type = "Fatal Error";
			break;
		}
		default : {
		$type = "Unknown";
		break;
		}
	}

	if(DEBUG_LEVEL) {

		if(Request::param('show_trace', false)) {
			debug_array(debug_backtrace());
		}

		if(isset($ERRORS) || !is_array($ERRORS)) {
			$ERRORS = array();
		}
		//save the error to be otputted later
		$ERRORS[] = array(
			'type' => $type,
			'message' => $message,
			'file' => $file,
			'line' => $line
		);
	}

	//log error in logging system
	if(ini_get('log_errors')) {
		error_log("PHP {$type}:  {$message} in {$file} on line {$line}\n", 3, LOG_FILE);
	}

	//make sure $php_errormsg is populated
	return true;
}


/**
 * Custom shutdown function.  Print any errors and try and clean up if needed
 * @todo  make this log error only and instad show a pretty mesage to the user
 */
function shutdown() {
	global $ERRORS;
	//@todo add a session variable that we could get that would make this show up for us only s (?show_errors=1);

	//if we are debugging  lets output this to screen
	if(DEBUG_LEVEL > 1 && isset($ERRORS)) {
		foreach($ERRORS as $e) {
			//ensure we are in a visible state
			echo '<div style="clear:both; position: absolute; bottom: 0px; background-color: #fff; z-index: 10000; color:red; display:block; visibility: visible; text-align: left; border: 2px #444444 solid; margin: 20px; width: 500px; padding:5px;">';
			echo "<strong>{$e['type']}</strong>:  {$e['message']} <br /><font color='black'>{$e['file']} on line {$e['line']}</font>";
			echo '</div>';
		}
	}
}


/**
 * Autoload function for missing class definitions
 */
function __autoload($class) {

	$file = strtolower($class);

	if(file_exists(PHP_PATH . 'model/'.$file.'.php')) {
		require(PHP_PATH . 'model/'.$file.'.php');
	}
	else if(file_exists(PHP_PATH . ''.$file.'.php')) {
		require(PHP_PATH . ''.$file.'.php');
	}
	else {

		$file = str_replace('_', '/', $file);
		$file = str_replace('\\', '/', $file);

		if(file_exists(PHP_PATH . ''.$file.'.php')) {
			require(PHP_PATH . ''.$file.'.php');
		}
		else {
			trigger_error("Unknown class {$class}");
		}
	}
}




//make sure this is set
defined('DEBUG_LEVEL') || define('DEBUG_LEVEL', 0);
defined('X_SENDFILE') || define('X_SENDFILE', 0);
defined('MEMCACHE_USE') || define('MEMCACHE_USE', 0);

//Don't waste time with auto-loader, include files that are always needed
require(PHP_PATH . 'view/view.php');
include(PHP_PATH . 'core/application.php');
include(PHP_PATH . 'core/dbi.php');
include(PHP_PATH . 'core/request.php');


	//register error handler and shut down functions
set_error_handler("error_handler");
register_shutdown_function('shutdown');

	//start php session
session_start();

