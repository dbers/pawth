<?php
/**
 * Request object
 *
 * This class methods for safely getting request data
 *
 * @package	Core
 */
namespace Core;

class Request {
	
	static private $stored = null;

	/**
	 * Get a post data value
	 * @param	string	$field  	Name of field to get
	 * @param	mix   	$default	The default value for when one doesn't exist	
	 * @param	bool 	$is_numeric	Also make sure field is a numeric value
	 * @param	boolean	$to_string	Cast data to string (Defult: true) [mongo injection protection]
	 * @return	null, $default, OR $_POST[$field]
	 * @internal	Default values are not casted!
	 */
	public static function post($field, $default=null, $is_numeric=false, $to_string=false) {

		if(!isset($_POST[$field]) || ($is_numeric && !is_numeric($_POST[$field]))) {
			return $default;
		}

		if($to_string) {
			return (string)$_POST[$field];
		}
		
		return $_POST[$field];
	}
	
	
	/**
	 * Get a query string value
	 * @param	string	$field  	Name of field to get
	 * @param	mix 	$default	The default value for when one doesn't exist	
	 * @param	bool 	$is_numeric	Also make sure field is a numeric value
	 * @param	boolean	$to_string	Cast data to string (Defult: true) [mongo injection protection]
	 * @return	null, $default, OR $_GET[$field]
	 * @internal	Default values are not casted!
	 */
	public static function param($field, $default=null, $is_numeric=false, $to_string=false) {
		
		if(!isset($_GET[$field]) || ($is_numeric && !is_numeric($_GET[$field]))) {
			return $default;
		}

		if($to_string) {
			return (string)$_GET[$field];
		}
		
		return $_GET[$field];
	}

	
	/**
	 * Get a cookie value
	 * @param	string	$field  	Name of field to get
	 * @param	mix 	$default	The default value for when one doesn't exist	
	 * @param	boolean	$to_string	Cast data to string (Defult: true) [mongo injection protection]
	 * @return	null, $default, OR $_GET[$field]
	 * @internal	Default values are not casted!
	 */
	public static function cookie($field, $default=null, $to_string=false) {
		
		if(!isset($_COOKIE[$field])) {
			return $default;
		}

		if($to_string) {
			return (string)$_COOKIE[$field];
		}

		return $_COOKIE[$field];
	}


	
	/**
	 * Get a request value from any field
	 * @param	string	$field  	Name of field to get
	 * @param	mix 	$default	The default value for when one doesn't exist	
	 * @param	boolean	$to_string	Cast data to string (Defult: true) [mongo injection protection]
	 * @return	null, $default, OR $_GET[$field]
	 * @internal	Default values are not casted!
	 */
	public static function all($field, $default=null, $to_string=false) {
		
		if(!isset($_REQUEST[$field])) {
			return $default;
		}

		if($to_string) {
			return (string)$_REQUEST[$field];
		}

		return $_REQUEST[$field];
	}
	
	
	/**
	 * Store post data for a reload
	 */
	public static function store() {
	
		$_SESSION['POST_DATA'] = $_POST;
		
		
	}
	
	
	/**
	 * Fetch a saved version of post data.  Used by templates on error re-show of template
	 * @param	string	$field  	Name of field to get
	 * @param	mix   	$default	The default value for when one doesn't exist
	 */
	public static function fetch($field, $default=null) {
	
		if((self::$stored == null) && isset($_SESSION['POST_DATA'])) {
			self::$stored = $_SESSION['POST_DATA'] ;
			unset($_SESSION['POST_DATA']);
		}
		
		if(!isset(self::$stored[$field])) {
			return $default;
		}
				
		return self::$stored[$field];
	}
	
	
	
}



